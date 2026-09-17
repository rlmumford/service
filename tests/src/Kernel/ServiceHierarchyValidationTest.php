<?php

namespace Drupal\Tests\service\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormState;
use Drupal\service\Entity\Service;
use Drupal\service\Entity\ServiceType;
use Drupal\service\Form\ServiceForm;
use Drupal\service\Plugin\Validation\Constraint\ServiceHierarchyConstraint;
use Drupal\service\ServiceInterface;

/**
 * Tests preflight entity validation and save-time form error handling.
 *
 * @group service
 */
class ServiceHierarchyValidationTest extends ServiceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['service_hierarchy_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if ($this->container->get('database')->driver() === 'mysql') {
      $this->container->get('database')->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }
  }

  /**
   * Valid roots and moves validate without taking a storage write lock.
   */
  public function testValidPreflightDoesNotWrite(): void {
    $root = $this->createService();
    $child = $this->createService();
    // Preflight must work without the mutex table: only saves acquire its lock.
    $database = $this->container->get('database');
    $database->schema()->dropTable('service_hierarchy_lock');
    $this->assertSame([], $this->hierarchyViolations($root));
    $child->set('service', $root);
    $this->assertSame([], $this->hierarchyViolations($child));
    $storage = $this->container->get('entity_type.manager')->getStorage('service');
    $this->assertTrue($storage->loadUnchanged($child->id())->get('service')->isEmpty());
    $this->assertFalse($database->inTransaction());
  }

  /**
   * Invalid parents report a violation on the reference widget's target ID.
   */
  public function testCycleValidation(): void {
    $root = $this->createService();
    $child = $this->createService($root);
    $root->set('service', $child);
    $violations = $this->hierarchyViolations($root);
    $this->assertCount(1, $violations);
    $this->assertSame('service.0.target_id', $violations[0]->getPropertyPath());
    $this->assertStringContainsString('cycle', (string) $violations[0]->getMessage());
    $this->assertFalse($this->container->get('database')->inTransaction());
  }

  /**
   * Missing and unsaved parents give distinct actionable messages.
   */
  public function testMissingAndUnsavedParentValidation(): void {
    $service = $this->createService();
    $service->set('service', 9999);
    $violations = $this->hierarchyViolations($service);
    $this->assertCount(1, $violations);
    $this->assertStringContainsString('missing', (string) $violations[0]->getMessage());
    $service->set('service', Service::create(['type' => 'work']));
    $violations = $this->hierarchyViolations($service);
    $this->assertCount(1, $violations);
    $this->assertStringContainsString('Save the parent', (string) $violations[0]->getMessage());
  }

  /**
   * The same installation policy rejects an invalid scope before saving.
   */
  public function testScopeValidation(): void {
    ServiceType::create(['id' => 'other', 'label' => 'Other'])->save();
    $parent = Service::create(['type' => 'other', 'label' => 'Other parent']);
    $parent->save();
    $service = $this->createService();
    $service->set('service', $parent);
    $violations = $this->hierarchyViolations($service);
    $this->assertCount(1, $violations);
    $this->assertSame('service.0.target_id', $violations[0]->getPropertyPath());
    $this->assertStringContainsString('Cross-scope', (string) $violations[0]->getMessage());
  }

  /**
   * A relationship invalidated after preflight rebuilds the form with an error.
   */
  public function testSaveTimeHierarchyFailure(): void {
    $service = $this->createService();
    $service->set('label', 'Self parent from hook');
    $this->assertSame([], $this->hierarchyViolations($service));
    $form = ServiceForm::create($this->container);
    $form->setEntity($service);
    $state = new FormState();
    $this->assertNull($form->save([], $state));
    $this->assertTrue($state->isRebuilding());
    $messages = $this->container->get('messenger')->messagesByType('error');
    $this->assertCount(1, $messages);
    $this->assertStringContainsString('cycle', (string) $messages[0]);
    $storage = $this->container->get('entity_type.manager')->getStorage('service');
    $this->assertTrue($storage->loadUnchanged($service->id())->get('service')->isEmpty());
  }

  /**
   * Infrastructure failures are not disguised as correctable parent errors.
   */
  public function testOtherStorageFailuresPropagate(): void {
    $service = $this->createMock(ServiceInterface::class);
    $service->expects($this->once())->method('save')
      ->willThrowException(new EntityStorageException('Unrelated storage failure.'));
    $form = ServiceForm::create($this->container);
    $form->setEntity($service);
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Unrelated storage failure.');
    $form->save([], new FormState());
  }

  /**
   * Extracts the hierarchy violations from Drupal's full entity validation.
   */
  protected function hierarchyViolations(ServiceInterface $service): array {
    $violations = [];
    foreach ($service->validate() as $violation) {
      if ($violation->getConstraint() instanceof ServiceHierarchyConstraint) {
        $violations[] = $violation;
      }
    }
    return $violations;
  }

}

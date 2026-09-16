<?php

namespace Drupal\service\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\service\ServiceTypeInterface;

/**
 * ServiceType entity class.
 *
 * @ConfigEntityType(
 *   id = "service_type",
 *   label = @Translation("Service Type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\service\Form\ServiceTypeForm",
 *       "edit" = "Drupal\service\Form\ServiceTypeForm",
 *     },
 *     "list_builder" = "Drupal\service\ServiceTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer service types",
 *   config_prefix = "service_type",
 *   bundle_of = "service",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/service_types/add",
 *     "edit-form" = "/admin/structure/service_types/manage/{service_type}",
 *     "collection" = "/admin/structure/service_types",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "default_uri",
 *     "default_label",
 *   }
 * )
 */
class ServiceType extends ConfigEntityBundleBase implements ServiceTypeInterface {

  /**
   * The machine name of this service type
   *
   * @var string
   */
  protected $id;

  /**
   * The human readable name of this service type.
   *
   * @var string
   */
  protected $label;

  /**
   * The default uri of services of this type.
   *
   * @var string
   */
  protected $default_uri;

  /**
   * The default label of services of this type.
   *
   * @var string
   */
  protected $default_label;

}

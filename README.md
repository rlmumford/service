# Service hierarchy

The existing `service` field is the immediate parent of a service. No additional
parent column or migration is needed for hierarchy traversal.

`service.hierarchy` provides these internal data APIs:

- `getAncestry($service)` returns the service itself, then each ancestor up to the
  root, inclusive.
- `getRoot($service)` returns the last service in that ancestry. A service without
  a parent is its own root.
- `getReferencedAncestry($item)` starts at the service referenced by a
  `service_reference` field item. An empty reference produces an empty array.

The field's existing computed `all` and `root` properties use the same resolver.
`all` starts at the referenced service; `root` is its final ancestor, or NULL for
an empty reference. On a service's own parent field these properties therefore
start at its **parent**, whereas the service API includes the service itself.

Traversal is lazy and does not save entities. Persisted references resolve the
current default revision through entity storage, rather than retaining the
entity-reference property's previously loaded object. Re-reading computed
properties observes saved parent changes and deletions in the current request.
The starting entity passed to `getAncestry()` can contain an unsaved parent edit.
Unsaved referenced entities can be traversed too, with object identity used for
cycle detection. The result is not a historical snapshot of a revision's tree.

Cycles and missing references throw `InvalidServiceHierarchyException`. Callers
must treat this as invalid hierarchy data, not as an empty or shorter tree.
These internal APIs do not authorize access to returned entities; callers must
check access before displaying them. They introduce no inherited permissions,
managers, recipients, or lifecycle state. They do not provide rendered cache
metadata or a cross-request hierarchy cache.

## Writing hierarchy relationships

Normal service saves enforce the parent relationship inside the SQL storage
transaction, after presave hooks have run. They reject self/descendant parents,
missing parents, and unsaved parents. Save parents first; automatic recursive
saving of an unsaved parent is deliberately refused. Unsaved graphs can still be
inspected with the traversal API.

Use `service.mover->reparent($service, $parent, $account)` for an authorized move.
Pass NULL as the parent to detach. Both supplied entities must already be saved;
the operation uses their identifiers and reloads them, preserving newer unrelated
field values. It requires update access to the service and view access to the
destination. A deleted destination is an error, never a detach request. It returns
the saved service; the supplied objects are not updated in place.

Low-level entity saves retain Drupal's trusted-caller permission model, but still
enforce structural and installation scope rules. User-facing callers must apply
access checks or use the move API. Forms use Drupal's existing entity and reference
validation; dedicated hierarchy validation messages are still to be added.

### Scope policies

Implement `HierarchyScopePolicyInterface` and register a service tagged
`service.hierarchy_scope_policy`. All registered policies must permit the proposed
link, including detaching to NULL. Policies run for direct saves as well as moves.
With no registered policies, the installation is unscoped. Common does not infer
organization boundaries from managers, recipients, roles, or service bundles.

Policies must check affected descendants when a proposed change alters their
scope. They must not save entities, mutate the proposal, or perform external side
effects. Tests use bundle IDs only as a fixture-defined boundary, not as a Common
scope rule.

For MySQL/MariaDB, the move API and registered scope policies require the session
isolation level `READ COMMITTED`, Drupal's recommended setting. Configure
`isolation_level: READ COMMITTED` in the database connection options in
`settings.php`. Other isolation levels fail explicitly: locking reads protect the
graph, but ordinary entity reads must also see current permission and scope data.

### Locking and deletion

A single row in `service_hierarchy_lock` serializes service writes, deletion checks,
and task attachments. The database retains the lock until the **outermost**
transaction commits or rolls back; there is no expiring application lock lease.
Long outer transactions therefore delay competing writes. Deadlocks/timeouts
abort the transaction normally; callers must retry their whole operation, not
continue after an error.

Graph checks use current locking reads, bypassing entity caches and MySQL's
repeatable-read snapshots. Task storage supplies `hook_task_storage_prewrite()`
inside its transaction, after presave hooks, so attaching a task participates in
the same locking protocol. This prevents a concurrent service deletion from
leaving a new task pointing at a missing service.

Deletion refuses services with current child-service or task references regardless
of caller visibility. Detach or delete dependents explicitly first; batch deletion
of a parent and child together is also refused. Historic revisions do not block
deletion. Notes, communications, and arbitrary custom references are not covered
by this deletion policy yet. Direct SQL imports and low-level restoration must
provide their own integrity checks; normal entity saves are the supported path.

Existing installations must run `service_update_10001()` via database updates.
It creates and seeds the mutex without changing service records or legacy state.
Fresh installs create the same table and row through the schema/install hooks.
After manually rolling back an outer transaction, discard/reset cached entities,
as with other Drupal entity writes.

## Remaining P2 work

Descendant render-cache invalidation, form validation messages, and service/task
lifecycle gates remain open. Parent transitions still do not change child state,
and only the immediate service will gate task execution.

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

## Remaining P2 work

This is the read/traversal foundation. It does **not** yet prevent invalid writes:
serialized reparenting with access/scope validation, deletion protection,
descendant render-cache invalidation, and lifecycle/task execution gates remain
in the implementation plan. In particular, validating a proposed tree separately
from saving it cannot prevent two concurrent moves from introducing a cycle.

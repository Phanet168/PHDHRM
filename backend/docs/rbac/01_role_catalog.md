# RBAC Role Catalog

## System Roles (`system_roles`)
- `head`: Final approver for top-level unit.
- `deputy_head`: Secondary approver / delegated approver.
- `manager`: Unit manager / office chief.
- `reviewer`: Reviews documents, no final approval by default.
- `staff`: Operational processing role.
- `viewer`: Read-only role.

## Assignment Source of Truth
- Unit-based authority: `user_org_roles` (`system_role_id`, `department_id`, `scope_type`).
- Global app access: Spatie roles/permissions (`roles`, `permissions`).
- Workflow step authority: `workflow_definition_steps` (`system_role_id` + `org_role` code).

## Scope Meaning
- `self_only`: Own department only.
- `self_unit_only`: Same unit-type siblings.
- `self_and_children`: Department + child tree.
- `all`: Global scope.


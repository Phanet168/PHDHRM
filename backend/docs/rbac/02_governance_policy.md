# RBAC Governance Policy

## Permission Domains
- Legacy org master-data permissions: `*_department`.
- New org-governance permissions: `*_org_governance`.

Current implementation supports both for backward compatibility:
- Read: `read_org_governance` OR `read_department`
- Create: `create_org_governance` OR `create_department`
- Update: `update_org_governance` OR `update_department`
- Delete: `delete_org_governance` OR `delete_department`

## Rules
- Do not grant `Super Admin` only by `user_type_id`; maintain Spatie role alignment.
- Every active `user_org_roles` row should have `system_role_id`.
- Every workflow step should have `system_role_id`.
- Every permission-matrix row should have `system_role_id`.

## Release Safety
- Keep fallback permissions enabled for at least one release cycle.
- Run `php artisan rbac:audit-role-governance` before and after deployment.


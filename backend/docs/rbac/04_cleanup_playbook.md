# RBAC Cleanup Playbook

## Goal
Migrate fully from legacy `org_role`-only linkage to `system_role_id` linkage without breaking current users.

## Steps
1. Run audit command:
   - `php artisan rbac:audit-role-governance`
2. Fix missing links:
   - `user_org_roles.system_role_id`
   - `workflow_definition_steps.system_role_id`
   - `org_role_module_permissions.system_role_id`
3. Validate duplicate identities (same `full_name`) and confirm intended account ownership.
4. Review roles with correspondence permissions and remove unnecessary overlap.
5. Keep legacy `*_department` permission fallback during transition.
6. After stable release cycle, plan deprecation of fallback permissions.

## Rollback
- Keep DB backup before bulk updates.
- If access regression occurs, re-enable old grants and re-run audit for mismatch detection.


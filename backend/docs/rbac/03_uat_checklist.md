# RBAC UAT Checklist

## Access Control
- Confirm public web registration is disabled (`AUTH_ALLOW_PUBLIC_REGISTRATION=false`).
- Confirm non-admin users cannot access org-governance pages.
- Confirm users with either legacy `*_department` or new `*_org_governance` permissions can access the same screens.

## Role Assignment
- Create/edit/delete `user_org_roles` entries.
- Verify saved rows contain both `org_role` and `system_role_id`.
- Verify role labels display correctly in Khmer/English.

## Workflow
- Create/update workflow policy and verify each step stores `system_role_id`.
- Approve leave/notice with role-based users and verify step transitions match expected roles.

## Correspondence
- Verify action permissions still work with mixed legacy/new role mappings.
- Verify recipient auto-resolution works when role mapping uses `system_role_id`.

## Audit
- Run:
  - `php artisan rbac:audit-role-governance`
  - `php artisan rbac:audit-role-governance --json`
- Confirm no critical mismatches (missing system-role links).


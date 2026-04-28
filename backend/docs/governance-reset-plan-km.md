# ផែនការ reset backend governance layer

## រក្សាទុក

- `users` និង auth essentials របស់ Laravel/Spatie
- `employees`
- `departments`
- `positions`
- `employee_unit_postings`
- business module data ដូចជា correspondence letters, attendance, leave, notice, payroll, reports

## Reset / ដកចេញពី daily use

- `org_role_module_permissions` central permission matrix
- `user_org_roles` legacy org roles
- `system_roles` legacy responsibility catalog UI
- central `responsibility_templates`
- central `workflow-policies` screen
- central governance screens/routes ត្រូវបានបិទ លុះត្រាតែបើក `HR_SHOW_ADVANCED_CENTRAL_GOVERNANCE=true`

## Backup / cleanup

- Migration `2026_04_24_171000_archive_legacy_governance_layer.php` បង្កើត archive tables:
  - `legacy_org_role_module_permissions_archive`
  - `legacy_user_org_roles_archive`
  - `legacy_system_roles_archive`
- Migration នោះ deactivate `org_role_module_permissions` ដើម្បីកុំឲ្យ central matrix បន្តជា daily config។
- មិន drop old tables ភ្លាមៗ ដើម្បីរក្សា rollback/audit path។

## Governance ថ្មី

### Correspondence

- Config: `modules/Correspondence/Config/governance.php`
- Tables:
  - `correspondence_responsibility_templates`
  - `correspondence_user_responsibilities`
  - `correspondence_workflow_policies`
- Seeder: `Modules\Correspondence\Database\Seeders\CorrespondenceGovernanceSeeder`

### Attendance

- Config: `modules/HumanResource/Config/attendance_governance.php`
- Tables:
  - `attendance_responsibility_templates`
  - `attendance_user_responsibilities`
  - `attendance_workflow_policies`
- Seeder: `Modules\HumanResource\Database\Seeders\AttendanceGovernanceSeeder`

## ច្បាប់សំខាន់

- `positions` មិន grant module rights ទេ។
- Module rights មកពី module-specific responsibility template assignment។
- Legacy explicit permission អាចនៅ fallback បណ្ដោះអាសន្នសម្រាប់ transition។
- ប្រធានការិយាល័យទូទៅមិនអាចបង្កើតលិខិតដោយសារតែ `manager` role ទៀតទេ។

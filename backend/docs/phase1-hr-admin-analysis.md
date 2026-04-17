# Phase 1 Preparation (HR Admin Only)

Date: 2026-03-10
Project: Laravel HRM (`PHDHRM`)

## Scope to Keep
- Employees
- Departments/Divisions
- Attendance
- Leave
- Notices
- User management
- Settings
- Localization

## 1) Menus to Hide

### Main Sidebar (`resources/views/backend/layouts/sidebar.blade.php`)
Hide these menu groups for Phase 1:
- Award
- Loan
- Payroll
- Procurement
- Project Management
- Recruitment
- Reports
- Reward Points
- Setup Rules
- Message

Hide these sub-items:
- `Employee > Employee Performance`

Optional (strict Phase 1):
- `Dashboard` (if you want only the listed modules and no mixed dashboard data widgets)

Keep these menu groups:
- Attendance
- Department (including Division/Sub Department)
- Employee (including Position, Employee)
- Leave (Weekly Holiday, Holiday, Leave Application)
- Notice Board
- Settings

### Settings Left Menu (`modules/Setting/Resources/views/__setting_left.blade.php`)
Hide these items:
- Tax Setup
- Backup/Reset
- Any sales/product/purchase/invoice/pos settings entries
- ZKT setup
- Doc-expired setup

Keep these items:
- Application
- App setting
- Currency
- Mail setup
- User management (role list, user list)
- Language setup

## 2) Routes to Disable (No Code Deletion Yet)

Disable by route groups/prefix patterns first (safe and fast):

| Group | Pattern / Prefix | Approx Route Count | Notes |
|---|---|---:|---|
| Accounts | `accounts/*`, `acconts/*` | 128 | Out of scope for Phase 1 |
| Sales/Stock Reports module | `report/*` | 25 | Out of scope |
| Project management | `project/*` | 73 | Out of scope |
| Reward points module routes | `reward/*` | 13 | Out of scope UI (attendance controller still touches point tables) |
| Internal messages | `message/*` | 7 | Out of scope |
| Payroll | `payroll/*` | 15 | Out of scope |
| Recruitment family | `hr/recruitment*`, `hr/shortlist*`, `hr/interview*`, `hr/selection*` | 29 | Out of scope |
| Procurement family | `hr/procurement_request*`, `hr/quotation*`, `hr/bid*`, `hr/purchase*`, `hr/goods*`, `hr/vendor*`, `hr/committee*`, `hr/units*` | 61 | Out of scope |
| HR reports | `hr/reports*` and `reports.*` named routes | 70 | Out of scope |
| Business settings routes | `setting/sale-settings*`, `setting/product-settings*`, `setting/purchase-settings*`, `setting/invoice-settings*`, `setting/pos-invoice-settings*`, `setting/tax-settings*`, tax-group routes | 17 | Not needed for HR-only phase |
| Device setup | `setting/apimenuszkt/*` | 6 | Optional for later phase |

Disable these Home dashboard data endpoints (non-HR data):
- `todayAndYesterdaySales`
- `salesReturnAmount`
- `totalSalesAmount`
- `stockValuation`
- `invoiceDue`
- `totalExpense`
- `purchaseReturnAmount`
- `purchaseDue`
- `last30daySales`
- `last30dayPurchase`
- `getStockAlertProductList`
- `getLowStockProductList`
- `incomeExpense`
- `warehouseWiseStock`
- `bankAndCashBalance`
- `counterWiseSale`
- `cashierWiseSale`
- `mostSellingProduct`
- `lessSellingProduct`

Special note:
- There are two localization route sets (`/language/*` and `/localize/*`) mapped to two controllers with overlapping behavior. Keep one canonical path (recommended: `/language/*`) and disable the duplicate set later.

## 3) Permissions to Keep

### Menu-access permissions
- `read_attendance`
- `read_department`
- `read_sub_departments`
- `read_employee`
- `read_positions`
- `read_leave`
- `read_leave_application`
- `read_notice`
- `read_setting`
- `read_software_setup`
- `read_application`
- `read_apps_setting`
- `read_currency`
- `read_mail_setup`
- `read_user_management`
- `read_role_list`
- `read_user_list`
- `read_language`
- `read_language_list`

### Action permissions (CRUD/operations)

Departments/Divisions:
- `create_department`, `read_department`, `edit_department`, `delete_department`
- `create_sub_departments`, `read_sub_departments`, `edit_sub_departments`, `delete_sub_departments`

Employees:
- `create_employee`, `read_employee`, `update_employee`, `delete_employee`, `update_employee_status`
- `create_positions`, `read_positions`, `update_positions`, `delete_positions`

Attendance:
- `attendance_management`
- `create_attendance`, `read_attendance`
- `create_monthly_attendance`
- `create_missing_attendance`, `read_missing_attendance`

Leave:
- `read_weekly_holiday`, `update_weekly_holiday`
- `create_holiday`, `read_holiday`, `update_holiday`, `delete_holiday`
- `create_leave_type`, `read_leave_type`, `update_leave_type`, `delete_leave_type`
- `read_leave_generate`, `update_leave_generate`
- `create_leave_application`, `read_leave_application`, `update_leave_application`, `delete_leave_application`
- `create_leave_approval`, `read_leave_approval`

Notice:
- `create_notice`, `read_notice`, `edit_notice`, `delete_notice`

User management:
- `create_role_list`, `read_role_list`, `update_role_list`, `delete_role_list`
- `create_user_list`, `read_user_list`, `update_user_list`, `delete_user_list`

Settings/Localization:
- `update_application`
- `create_currency`, `update_currency`, `delete_currency`
- `create_mail_setup`
- `create_language_list`, `update_language_list`, `delete_language_list`
- `create_language_strings`, `read_language_strings`, `update_language_strings`, `delete_language_strings`

### Permission mismatches to resolve before rollout
These permissions are used in middleware but not seeded in `RoleTableSeeder`:
- `edit_department` (seed has `update_department`)
- `edit_sub_departments` (seed has `update_sub_departments`)
- `edit_notice` (seed has `update_notice`)
- `destroy_currency` (seed has `delete_currency`)

## 4) Controllers and Tables Used in Phase 1

| Feature | Controllers | Primary Tables |
|---|---|---|
| Departments/Divisions | `DepartmentController`, `DivisionController` | `departments` |
| Employees | `EmployeeController`, `PositionController` | `employees`, `positions`, `departments`, `users`, `employee_files`, `bank_infos`, `employee_docs`, `employee_academic_infos`, `employee_other_docs`, `employee_types`, `duty_types`, `pay_frequencies`, `genders`, `marital_statuses`, `countries`, `setup_rules`, `skill_types`, `certificate_types`, `employee_salary_types` |
| Attendance | `ManualAttendanceController` | `attendances`, `employees`, `holidays`, `week_holidays`, `point_settings`, `point_attendances`, `reward_points` |
| Leave | `LeaveController`, `LeaveTypeController`, `HolidayController` | `apply_leaves`, `leave_types`, `leave_type_years`, `week_holidays`, `holidays`, `employees`, `financial_years` |
| Notices | `NoticeController` | `notices` |
| User management | `RoleManagementController`, `UserManagementController`, `UserTypeController`, `PasswordSettingController` | `users`, `user_types`, `password_settings`, `per_menus`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` |
| Settings | `ApplicationController`, `SettingController` (limited), `CurrencyController`, `MailController`, `CountryController` | `applications`, `appsettings`, `currencies`, `email_configs`, `countries` |
| Localization | `LanguageController` (preferred), `LocalizeController` (legacy duplicate), `LocalizationController` | `languages`, `langstrings`, `langstrvals`, language files under `lang/*/language.php` |

## 5) Safe Refactor Plan (Non-Destructive)

### Step 1: Add a Phase-1 Feature Gate (config only)
- Add config flag `phase1_hr_admin=true`.
- Do not delete modules/controllers/routes yet.
- Keep rollback simple (`false` to restore all routes/menus).

### Step 2: Hide Menus via Conditional Rendering
- Gate sidebar/menu blocks with the Phase-1 flag + allowlist.
- Keep only allowed modules visible.
- Hide settings sub-items not in scope.

### Step 3: Disable Routes by Group
- Wrap out-of-scope route groups with middleware/condition that returns 404 in Phase 1.
- Start with high-volume groups: `accounts/*`, `report/*`, `project/*`, procurement, recruitment, payroll, reward, messages.
- Keep a short allowlist for Phase 1 routes to avoid accidental exposure.

### Step 4: Normalize Permission Names (critical)
- Add temporary alias mapping for mismatched names:
  - `edit_department -> update_department`
  - `edit_sub_departments -> update_sub_departments`
  - `edit_notice -> update_notice`
  - `destroy_currency -> delete_currency`
- Then update middleware names in controllers to canonical `update_*` / `delete_*`.

### Step 5: Lock Down Sensitive Settings/User Routes
- Add explicit permission middleware on user/role/menu/permission routes that are currently `auth`-only.
- Add explicit permission middleware on localization routes (`/language/*`, `/localize/*`) and backup routes.

### Step 6: Dependency Safety Checks
- Verify leave generation dependency on `financial_years` (accounts table) before disabling accounts migrations/data access.
- Verify attendance point writes (`reward_points`, `point_attendances`) still succeed if reward UI is disabled.
- Verify employee create/update flows still work with countries/currency/application data.

### Step 7: Test Matrix Before Any Deletion
- Route smoke test for Phase-1 allowlist (200/302 expected).
- Out-of-scope route test must return 404/403.
- Permission tests for HR Admin role.
- UI checks for sidebar/settings left menu visibility.

### Step 8: Prepare Phase 2 Cleanup (After Stabilization)
- After one stable release cycle, remove dead routes/controllers/views/modules.
- Remove unused permissions from role seeder.
- Remove duplicated localization path set (`/localize/*` or `/language/*`, keep one).


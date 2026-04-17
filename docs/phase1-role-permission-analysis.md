# Phase 1: Current System Architecture Analysis
## Role / Permission / Position / Approval System — Complete Analysis

> **Status**: Phase 1 - Analysis Only (No Code Changes)  
> **Date**: April 2026  
> **Purpose**: Analyze current structure, identify problems, recommend changes

---

## 1. ENTITY MAP (តារាង Database បច្ចុប្បន្ន)

### 1.1 Core Tables

| Table | Purpose | Module |
|-------|---------|--------|
| `users` | User accounts (login, auth) | UserManagement |
| `employees` | Employee HR data (150+ columns) | HumanResource |
| `departments` | Organizational units (self-referencing tree via `parent_id`) | HumanResource |
| `positions` | Job titles/positions | HumanResource |
| `org_unit_types` | Unit type definitions (PHD, OD, Hospital, HC...) | HumanResource |

### 1.2 Relationship/Mapping Tables

| Table | Purpose | Module |
|-------|---------|--------|
| `org_unit_type_rules` | Valid parent→child unit type relationships | HumanResource |
| `org_unit_type_positions` | Which positions valid per unit type (`hierarchy_rank`, `is_leadership`, `can_approve`) | HumanResource |
| `employee_unit_postings` | Employee ↔ Department ↔ Position assignments over time | HumanResource |
| `employee_status_transitions` | Audit trail for employee status changes | HumanResource |
| `employee_statuses` | Work status definitions (active, retired, etc.) | HumanResource |

### 1.3 Permission System Tables (3 Layers)

| Layer | Tables | Purpose |
|-------|--------|---------|
| **Layer 1: Spatie** | `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Global system permissions (menu access, feature gating) |
| **Layer 2: Org Roles** | `user_org_roles` | User ↔ Department role assignment (`head`, `deputy_head`, `manager`) with scope (`self`, `self_and_children`) |
| **Layer 3: Permission Matrix** | `org_role_module_permissions` | Module + Action → Org Role mapping (e.g., `correspondence::create_incoming` → `head`) |

### 1.4 Workflow/Approval Tables

| Table | Purpose |
|-------|---------|
| `workflow_definitions` | Define approval flows per module + request type (with `condition_json` for routing) |
| `workflow_definition_steps` | Steps in each workflow (order, action_type, org_role, is_final_approval) |
| `workflow_instances` | Track individual workflow executions (status: draft→pending→approved/rejected) |
| `workflow_instance_actions` | Record every action (submit, approve, reject, comment) with `acted_by`, timestamp |

---

## 2. CURRENT ARCHITECTURE DIAGRAM

### 2.1 Permission Check Flow

```
Request comes in
     │
     ▼
┌──────────────────┐   YES
│ Is System Admin?  │──────────► ALLOW (bypass all)
│ (user_type_id=1   │
│  or Super Admin)  │
└────────┬─────────┘
         │ NO
         ▼
┌──────────────────┐   YES
│ Spatie Permission │──────────► ALLOW (menu/feature level)
│ @can('perm_name') │
└────────┬─────────┘
         │ NO / Not configured
         ▼
┌──────────────────┐
│ UserOrgRole check │  → user_org_roles table
│ Has active role?  │  → head / deputy_head / manager
└────────┬─────────┘
         │ YES
         ▼
┌──────────────────┐
│ Permission Matrix │  → org_role_module_permissions
│ Role→Module→Action│  → Does role allow this action?
└────────┬─────────┘
         │ YES
         ▼
┌──────────────────┐
│ Department Scope  │  → self vs self_and_children
│ Can access target │  → Expand department tree
│ department?       │
└────────┬─────────┘
         │ YES
         ▼
     ✅ ALLOW
```

### 2.2 Organizational Hierarchy

```
Provincial Health Department (PHD) ─── unit_type: phd
├── Office (កការិយាល័យ) ─── unit_type: office
│   └── Bureau (ការិយាល័យរង) ─── unit_type: bureau
│       └── Program (កម្មវិធី) ─── unit_type: program
├── Operational District (ស្រុកប្រតិបត្តិ) ─── unit_type: operational_district
│   ├── OD Section (ផ្នែក) ─── unit_type: od_section
│   ├── Health Center (មណ្ឌលសុខភាព) ─── unit_type: health_center
│   │   └── Health Post (ប៉ុស្តិ៍សុខភាព) ─── unit_type: health_post
│   └── District Hospital (មន្ទីរពេទ្យស្រុក) ─── unit_type: district_hospital
└── Provincial Hospital (មន្ទីរពេទ្យបង្អែកខេត្ត) ─── unit_type: provincial_hospital
```

### 2.3 Three Sources of "User's Department"

```
Source 1: employees.department_id        → Used by PharmScope, CorrespondenceScope  
Source 2: employee_unit_postings         → Historical, supports multi-posting  
Source 3: user_org_roles.department_id   → Used by OrgHierarchyAccessService  
```

---

## 3. SUPPORT SERVICES (Business Logic Layer)

| Service | Location | Purpose | Key Methods |
|---------|----------|---------|-------------|
| `OrgHierarchyAccessService` | HumanResource/Support | Department-level access | `isSystemAdmin()`, `effectiveOrgRoles()`, `managedBranchIds()`, `canManageDepartment()`, `canApproveDepartment()` |
| `OrgRolePermissionService` | HumanResource/Support | Module-action permission | `canUserPerform($user, $module, $action, $deptId)`, `configuredRolesForAction()` |
| `OrgUnitRuleService` | HumanResource/Support | Hierarchy rules & tree | `branchIdsIncludingSelf()`, `validateParentRule()`, `allowedPositionsForDepartment()` |
| `WorkflowPolicyService` | HumanResource/Support | Workflow routing | `resolveDefinition()`, `buildPlan()`, `matchesCondition()` |

---

## 4. EXISTING CONTROLLERS & ROUTES

### 4.1 HR Module Routes (`/hr/...`)

| Route | Controller | Purpose |
|-------|-----------|---------|
| `/hr/user-org-roles` | UserOrgRoleController | CRUD for org role assignments |
| `/hr/org-role-module-permissions` | OrgRoleModulePermissionController | Permission matrix configuration |
| `/hr/workflow-policies` | WorkflowPolicyController | Workflow definition management |
| `/hr/departments` | DepartmentController | Department CRUD |
| `/hr/positions` | PositionController | Position management |
| `/hr/org-unit-types` | OrgUnitTypeController | Unit type management |
| `/hr/org-unit-type-positions` | OrgUnitTypePositionController | Position-unit mappings |

### 4.2 UserManagement Module Routes

| Route | Controller | Purpose |
|-------|-----------|---------|
| `/role-list` | RoleManagementController | Spatie roles CRUD |
| `/permission-list` | RoleManagementController | Spatie permissions CRUD |
| `/menu-list` | RoleManagementController | Menu hierarchy management |

### 4.3 Module-Specific Scope Traits

| Module | Trait | Methods |
|--------|-------|---------|
| Pharmaceutical | `PharmScope` | `pharmLevel()`, `pharmAccessibleDepartmentIds()`, `pharmLevelLabel()` |
| Correspondence | `CorrespondenceScope` | `corrLevel()`, `corrAccessibleDepartmentIds()`, `corrLevelLabel()` |
| Future modules | ??? | Will need their own trait — DRY violation |

---

## 5. IDENTIFIED PROBLEMS (បញ្ហាដែលរកឃើញ)

---

### Problem 1: Role vs Position Confusion

**បច្ចុប្បន្ន:**
- `user_org_roles.org_role` = `head` | `deputy_head` | `manager` (functional authority)
- `positions` table = actual job titles (ប្រធាន, អនុប្រធាន, etc.)
- `org_unit_type_positions` = position valid per unit type with `is_leadership`, `can_approve`

**បញ្ហា:**
- **NO CONNECTION** between `user_org_roles.org_role` and `positions`/`org_unit_type_positions`
- Someone with Position="Director" (head of PHD) must ALSO be manually assigned `org_role='head'` in `user_org_roles`
- **Double-entry**: Position says "Director" → org_role says "head" → configured separately
- `org_unit_type_positions.can_approve` is never used by workflow/permission checks

**ផលប៉ះពាល់:**
- Admin must manually sync positions and org roles
- If employee promoted to Deputy Director, admin must also update `user_org_roles`
- `is_leadership` and `can_approve` flags are essentially dead columns

---

### Problem 2: Only 3 Hardcoded Org Roles

**បច្ចុប្បន្ន:**
```php
ROLE_HEAD = 'head'
ROLE_DEPUTY_HEAD = 'deputy_head' 
ROLE_MANAGER = 'manager'
```

**បញ្ហា:**
- Real organization needs more roles: `staff`, `viewer`, `data_entry`, `reviewer`
- A data entry clerk at HC level needs access but isn't a `manager`
- Module-specific roles (pharmacist, lab technician) can't be represented
- Adding a new role requires code changes (enum in migration, constants in model)

**ផលប៉ះពាល់:**
- Staff users can't get scoped permissions without being called "manager"
- No viewer-only role for reporting
- Pharmaceutical module can't define `pharmacist`/`dispenser` roles

---

### Problem 3: Only 2 Scope Types

**បច្ចុប្បន្ន:**
```php
SCOPE_SELF = 'self'                     // Only assigned department
SCOPE_SELF_AND_CHILDREN = 'self_and_children'  // Department + all children
```

**បញ្ហា:**
- No `self_unit_only` (access all staff in your unit, not children)
- No `all` scope (system-wide without being user_type_id=1)
- PharmScope/CorrespondenceScope reinvented 4-level logic: `phd`, `od`, `hospital`, `hc`
- Each module creates its own scope interpretation

**ផលប៉ះពាល់:**
- Inconsistent scope handling across modules
- Can't differentiate "all HCs under OD X" from "only HC Y"

---

### Problem 4: Module Scope Traits Duplicate Logic (DRY Violation)

**បច្ចុប្បន្ន:**
```
PharmScope        → pharmLevel(), pharmAccessibleDepartmentIds(), pharmLevelLabel()
CorrespondenceScope → corrLevel(), corrAccessibleDepartmentIds(), corrLevelLabel()
Future Module X    → xLevel(), xAccessibleDepartmentIds(), xLevelLabel()
```

**បញ្ហា:**
- All traits do the same thing: department → unit_type → level → department IDs
- Copy-paste with renamed methods
- If business rule changes, must update EVERY trait
- N modules = N copies of identical logic

**ផលប៉ះពាល់:**
- Maintenance burden grows linearly with modules
- Bug fixes must be applied to N places

---

### Problem 5: Three Sources of Department Assignment

**បច្ចុប្បន្ន:**

| Source | Used By | Data |
|--------|---------|------|
| `employees.department_id` | PharmScope, CorrespondenceScope | Static FK |
| `employee_unit_postings` (is_primary=true) | Historical tracking | Date-ranged, supports multi-posting |
| `user_org_roles.department_id` | OrgHierarchyAccessService | Links user to dept for permissions |

**បញ្ហា:**
- Three different answers to "which department is this user in?"
- `employees.department_id` can be stale if posting changes
- `user_org_roles` is independent — user could have role at dept A but employee record says dept B
- No enforced consistency between these sources

**ផលប៉ះពាល់:**
- Scope traits check `employees.department_id` but permission service checks `user_org_roles.department_id`
- Possible conflicting access decisions

---

### Problem 6: Workflow ↔ Permission Matrix Disconnected

**បច្ចុប្បន្ន:**
- `org_role_module_permissions`: `leave::approve → head`
- `workflow_definition_steps`: step 3 requires `org_role='head'` with `action_type='approve'`
- `config/hr_workflow.php`: promotion approve → `[deputy_head, head]`

**បញ្ហា:**
- Three separate places define "who can approve"
- No validation that workflow step actors match permission matrix
- Possible mismatch: permission says role X can approve, but workflow says role Y

---

### Problem 7: No Unified User Assignment Concept

**បច្ចុប្បន្ន:**
User organizational context scattered across:
1. `users.user_type_id` → hard-coded admin type
2. `model_has_roles` → Spatie system role
3. `employees.department_id` + `employees.position_id`
4. `employee_unit_postings` → historical dept+position+dates
5. `user_org_roles` → org role + scope at department

**បញ្ហា:**
- No single table captures: "User X has Role Y at Department Z with Scope S"
- Different modules query different sources
- Changes require updates in multiple places

---

## 6. WHAT WORKS WELL (អ្វីដែលល្អហើយ — រក្សាទុក)

| # | Component | Why It's Good |
|---|-----------|---------------|
| 1 | `org_unit_types` + `org_unit_type_rules` | Clean hierarchy definition, extensible |
| 2 | `org_unit_type_positions` | Position→unit mapping with leadership/approve flags |
| 3 | `workflow_definitions` → `steps` → `instances` → `actions` | Complete workflow engine with conditional routing |
| 4 | `OrgRolePermissionService` | Centralized permission check with caching |
| 5 | `OrgHierarchyAccessService` | Clean department access service |
| 6 | `OrgUnitRuleService` | Good hierarchy traversal and validation |
| 7 | `WorkflowPolicyService` | Dynamic workflow routing with condition matching |
| 8 | `employee_unit_postings` | Historical department/position tracking |
| 9 | `employee_status_transitions` | Complete audit trail |
| 10 | UUID on all entities | API-ready |
| 11 | Soft deletes everywhere | Data preservation |
| 12 | Spatie Permission (Layer 1) | Global menu/feature access — keep for sidebar/menu gating |

---

## 7. CHANGE RECOMMENDATIONS (តើត្រូវផ្លាស់ប្តូរអ្វី)

### 7.1 MUST Change (ត្រូវផ្លាស់ប្តូរជាចាំបាច់)

| # | Problem | Proposed Solution |
|---|---------|-------------------|
| 1 | Hardcoded 3 org roles | Create `system_roles` table with configurable roles (head, deputy, manager, staff, viewer, + custom) |
| 2 | `user_org_roles.org_role` is enum | Change to FK → `system_roles.id` |
| 3 | Only 2 scope types | Add `self_unit_only` and `all` to scope options |
| 4 | Module scope traits duplicate | Create ONE shared `OrgScopeService` for all modules |
| 5 | Department source ambiguity | Define canonical: `employee_unit_postings.is_primary=true` for employee department, `user_org_roles` for permission scope |

### 7.2 SHOULD Change (គួរផ្លាស់ប្តូរ)

| # | Problem | Proposed Solution |
|---|---------|-------------------|
| 6 | Position ↔ OrgRole disconnected | Link position's `is_leadership`/`can_approve` to system_roles for auto-assignment suggestion |
| 7 | Workflow ↔ Permission disconnect | Validate workflow step actors against permission matrix at definition time |
| 8 | `config/hr_workflow.php` duplication | Migrate promotion roles into `org_role_module_permissions` |

### 7.3 KEEP As-Is (រក្សាទុកដដែល)

| Component | Reason |
|-----------|--------|
| `org_unit_types` + rules | Well-designed |
| `org_unit_type_positions` | Good design, just needs connection to system_roles |
| `workflow_*` tables | Complete engine, just update step role references |
| `employee_unit_postings` | Good historical tracking |
| Spatie Permission Layer | Still needed for global menu access |
| All 4 Support Services | Good design, just need updates for new role system |

---

## 8. PHASE 2 PREVIEW (Schema Proposal)

Phase 2 will propose:

1. **New `system_roles` table** — configurable roles replacing hardcoded enum
2. **Updated `user_org_roles`** — FK to `system_roles` instead of enum, 4 scope types
3. **Updated `org_role_module_permissions`** — FK to `system_roles` instead of enum  
4. **Updated `workflow_definition_steps`** — FK to `system_roles` instead of enum
5. **New `OrgScopeService`** — unified scope logic replacing PharmScope/CorrespondenceScope
6. **Migration plan** — ALTER existing tables, seed new roles, migrate old data
7. **No table deletions** — backward compatible

---

> **សូមពិនិត្យ Phase 1 Analysis នេះ រួចប្រាប់ខ្ញុំថា:**
> 1. មានអ្វីមួយដែលខ្ញុំវិភាគខុស ឬបាត់ទេ?
> 2. យល់ព្រមបញ្ហាទាំង 7 នេះទេ?  
> 3. យល់ព្រម recommendations ដែលខ្ញុំស្នើទេ?
> 4. ត្រៀមទៅ Phase 2 (Schema Proposal) ទេ?

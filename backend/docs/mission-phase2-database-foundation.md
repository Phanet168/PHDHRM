# Mission Management — Phase 2 Database Foundation

Implemented on 2026-09-14. Scope: database, enums, models, seed data, integrity rules and tests. Phase 3 has not started.

## Live preflight and migration reconciliation

- Connected successfully to the project's configured local database: MariaDB 10.4.32, Laravel 10.50.2, PHP 8.2.12.
- Checked the `migrations` table and actual `SHOW CREATE TABLE` definitions before writing migrations.
- `2026_09_13_093000_add_mission_order_details` was **Pending**, not applied. `missions.order_details` and assignment `name_on_order`, `position_on_order`, `sort_order` were absent. This mismatch was reported before modification.
- The 090000, 091000 and 092000 Mission migrations were already applied. The existing 093000 migration was run unchanged; its columns were not duplicated in the new migrations.
- Before migration: 11 missions, 11 assignments, 0 documents, 0 reports, 3 Planning funding sources. No reversed mission date ranges or orphaned mission workflow references were found.
- A local pre-change snapshot of these tables' schemas and rows was saved outside the repository at `%TEMP%/phdhrm-mission-phase2-before-20260914.json`.
- After migration, every pre-existing column value in those five tables was compared with the snapshot and remained unchanged. All 11 legacy missions retain NULL `creation_path`, `creation_source`, `created_by` and `lifecycle_status`.
- Unrelated pending migrations were not run.

## Applied migrations

All five migrations below are **Ran**, batch **104**:

| Migration | Effect |
|---|---|
| `2026_09_13_093000_add_mission_order_details` | Existing prerequisite, run unchanged |
| `2026_09_14_100000_create_mission_master_tables` | Two configurable master tables |
| `2026_09_14_101000_extend_mission_foundation` | Extend four existing Mission tables |
| `2026_09_14_102000_create_mission_destinations_and_history` | Destinations and lifecycle history |
| `2026_09_14_103000_enforce_mission_foundation_integrity` | Retention FKs, checks and 16 database triggers |

## Database changes

### New tables

| Table | Columns |
|---|---|
| `mission_types` | `id`, `code` unique, `name`, `name_km`, `description` nullable, `is_active`, `sort_order`, timestamps |
| `transport_types` | `id`, `code` unique, `name`, `name_km`, `is_active`, `sort_order`, timestamps |
| `mission_destinations` | `id`, `mission_id`, `scope`, `destination_type`, nullable `department_id`, `province_code`, nullable `district_code`/`commune_code`/`village_code`, `venue_name`, nullable `address`, `location_snapshot` JSON, `sort_order`, timestamps |
| `mission_status_histories` | `id`, `mission_id`, nullable `from_status`, `to_status`, `action`, nullable `acted_by`/`workflow_action_id`, `revision`, nullable `reason`/`metadata` JSON, `occurred_at`, `created_at`; no `updated_at` |

IDs/FKs are unsigned big integers. Province/district/commune/village codes are strings of length 2/4/6/8, verified against the existing Cambodia Gazetteer. Snapshot JSON preserves historical names when master data changes.

### Extended `missions`

| Columns | Type / behavior |
|---|---|
| `creation_path` | nullable varchar(32), `MissionCreationPath` cast |
| `creation_source` | nullable varchar(40), `MissionCreationSource` cast |
| `creation_reason` | nullable text |
| `created_by` | nullable FK to `users` |
| `requester_employee_id` | nullable FK to `employees` |
| `source_department_id`, `issuer_department_id` | nullable FKs to `departments` |
| `mission_type_id` | nullable FK to `mission_types` |
| `funding_source_id` | nullable FK to existing Planning `funding_sources` |
| `sponsor_name` | nullable varchar(255) |
| `transport_type_id` | nullable FK to `transport_types` |
| `transport_description` | nullable varchar(255) |
| `lifecycle_status` | nullable varchar(40), `MissionStatus` cast |
| `revision`, `lock_version` | unsigned integers, defaults 1 and 0 |
| `submitted_at`, `issued_at`, `actual_returned_at`, `report_due_at` | nullable datetime, datetime casts |

The existing `workflow_instance_id` remains nullable and gains a restrictive FK to `workflow_instances`. Path B does not need a workflow instance. The old `status` enum, values and Attendance interpretation are unchanged. No legacy mission is inferred to be a new request, direct mission or issued order.

Indexes cover path/source reporting, source and issuer unit lifecycle queues, report deadlines and the new foreign keys.

### Extended `mission_assignments`

- Retained unique `(mission_id, employee_id)`.
- Reused the existing 093000 snapshot columns and `sort_order`.
- Added nullable `honorific_on_order` varchar(100), `department_on_order` varchar(255).
- Changed mission and employee FKs from CASCADE to RESTRICT to retain administrative records.

### Extended `mission_reports`

- Added nullable `title` varchar(255), `activities`, `results`, `issues`, `recommendations` longtext, `submitted_at` datetime and unsigned `revision` default 1.
- Preserved existing `summary`, `employee_id`, `submitted_by` and unique `(mission_id, employee_id)`.
- Added submission lookup index and unique `(id, mission_id)` for same-mission attachment integrity.
- Mission and employee FKs now use RESTRICT; `submitted_by` gains a restrictive user FK.
- Historical reports are not automatically stamped as formally submitted.

### Extended `mission_documents`

- Added `document_kind` varchar(40), default `supporting`.
- Added nullable `mission_report_id`, `correspondence_letter_id`, `reference_number` varchar(150), `reference_date` date and `reference_issuer` varchar(255), plus `sort_order`.
- Made `path`, `mime_type`, `size` nullable to allow references to existing correspondence without copying files.
- A document must contain a file path or correspondence reference. The composite report/mission FK prevents attaching a report from another mission.
- Mission, report, correspondence and uploader references use RESTRICT.

### Integrity rules

- Database CHECK constraints enforce ordered dates, valid path/source pairs, valid lifecycle/scope values, positive revisions and nonempty submitted report summaries.
- `creation_path = employee_request` pairs with source `employee_request`.
- `creation_path = direct` pairs with `invitation_letter`, `director_instruction`, `administration_direct` or `other`.
- Both provenance fields may be NULL for legacy rows; partially populated or contradictory pairs are rejected.
- Completion requires at least one formally submitted report with nonempty summary. Draft reports and attachments alone do not satisfy this rule. A group does not require one report per participant.
- Model and database guards prevent completion without a report. Database guards also prevent withdrawing/deleting the last formal report after completion; report writes lock the mission row against competing completion/withdrawals.
- The existing report submission endpoint now stamps the authenticated actor and server submission time. The existing completion endpoint checks formal submission. These are backend compatibility changes, with no new report UI.
- History updates/deletes are rejected at both Eloquent and SQL levels. History FKs use RESTRICT, so parent deletion cannot erase or rewrite audit rows through a cascade.
- Submitted mission core edits increment revision in the database. Assignment, destination and reference-document mutations also increment the parent revision. New-path mission updates increment `lock_version`.
- Revision may advance more than once for an operation that changes multiple child rows. Consumers should treat it as a monotonic change marker and refresh the model after trigger-controlled writes.
- New lifecycle, creation actor and issuance timestamp fields are excluded from mass assignment. Legacy API request validation continues to whitelist fields and sets approval actors server-side. Future services must assign trusted actor/state fields explicitly and implement authorization and optimistic-lock comparisons.

The integrity migration intentionally requires MySQL/MariaDB rather than silently omitting these safeguards under SQLite. The real-engine tests use disposable databases on the configured local MySQL server.

## Enums and workflow reuse

- `MissionStatus`: draft, pending_office_head, office_head_endorsed, pending_director, director_approved, pending_mission_order, preparing_mission_order, issued, on_mission, pending_report, completed, returned_for_correction, rejected, cancelled.
- `MissionCreationPath`: employee_request, direct.
- `MissionCreationSource`: employee_request, invitation_letter, director_instruction, administration_direct, other.
- `ApprovalAction`: endorse, approve, reject, return_for_correction.
- `DestinationScope`: within_province, outside_province.

Path/source/lifecycle/scope and history status fields have Eloquent enum casts. `MissionStatusHistory.approval_action` returns an optional `ApprovalAction` enum; full history also contains non-approval actions. The shared `WorkflowInstanceAction.action_type` remains a string to preserve Leave/Attendance/other modules' action types.

No `mission_approvals` table was created. `currentWorkflow()` reuses `workflow_instances`, and history `workflowAction()` reuses `workflow_instance_actions`. Definitions, steps, actor resolution and operational transitions remain for later approved phases. The intended Path A remains Office Head **endorsement**, Director **approval**, then the Letter Manager's administrative queue. Letter Manager is not a request approver. No new workflow engine or approver IDs were introduced.

## Models and relationships

- `Mission`: creator, requesterEmployee, sourceDepartment, issuerDepartment, missionType, fundingSource (Planning), transportType, currentWorkflow, destinations, statusHistories; retains assigner, assignments, documents, reports.
- `MissionAssignment`: mission, employee; document snapshot fields remain ordinary stored attributes.
- `MissionReport`: mission, employee, submitter, documents; `submitted()` query scope.
- `MissionDocument`: mission, report, correspondenceLetter (Correspondence module), uploader.
- `MissionDestination`: mission, department; JSON snapshot and enum/string casts.
- `MissionStatusHistory`: mission, actor, workflowAction; immutable after insert.
- `MissionType` and `TransportType`: missions; configurable active flag and ordering.

No unrelated employee, department, authentication or Planning model was rewritten.

## Seed data

`MissionFoundationSeeder` was run explicitly. It uses `firstOrCreate` to preserve administrator customization and disabled entries on reruns.

- Mission types: `training` / វគ្គបណ្តុះបណ្តាល; `other_mission` / ការចុះបេសកកម្មផ្សេងៗ.
- Transport types: `unit_vehicle` / រថយន្តអង្គភាព; `rented_vehicle` / រថយន្តជួល; `private_vehicle` / រថយន្តផ្ទាល់ខ្លួន; `motorcycle` / ម៉ូតូ; `airplane` / យន្តហោះ; `boat` / ទូក; `other` / ផ្សេងៗ.
- Existing Planning funding sources: 3 rows, unchanged. No funding seeder was invoked.
- The broad legacy HR seeder was not invoked because it contains unrelated destructive reseeding operations.

## Tests

Detailed final regression results are recorded below after the final run.

Real-engine foundation coverage includes migration up/down/up, legacy data and status preservation, Attendance MissionResolver behavior, direct creation without workflow, employee request workflow reference, enum casts, invalid provenance/date/state/FK rejection, multiple participants and duplicate rejection, multiple destinations and leading-zero codes, snapshot stability, append-only model/SQL history, shared approval links, report completion and withdrawal guards, same-mission attachments, restrictive deletes, revision increments, seed idempotency and protected mass assignment.

The tests create databases named `phdhrm_mission_test_<random 12 hex digits>` and remove only their own database after each test. They never run a rollback or truncate against the HRM database.

Reproduce from `backend/` in PowerShell:

```powershell
$env:MISSION_FOUNDATION_MYSQL_TESTS = '1'
php vendor/phpunit/phpunit/phpunit --filter=MissionDatabaseFoundationTest --colors=never
php vendor/phpunit/phpunit/phpunit --filter=MissionManagementTest --colors=never
```

## Compatibility and rollback notes

1. `MissionResolverService` is unchanged: legacy Attendance still checks `status = approved`. Future phases must explicitly design the bridge to issued orders without reinterpreting the legacy field in Phase 2.
2. Existing Mission routes/UI/type constants remain operational. The new master tables are not yet wired into new screens or Flutter. No new requests are automatically assigned a path by old endpoints.
3. Official order, numbering and travel certification tables/business logic were not created. No UI, PDF, printing, Dashboard or Flutter file was changed in this phase.
4. Retained records now prevent physical parent deletion. Normal soft deletion remains available where supported by existing models.
5. The rollback test preserves legacy data and 093000 columns while reversing the four Phase 2 migrations. Live rollback was not performed.
6. Rolling back new tables/columns discards Phase 2-only data by definition: export that data and coordinate application rollback first. The extension migration explicitly refuses to restore old NOT NULL file fields if reference-only documents exist.
7. All five applied migrations share batch 104. An unqualified batch rollback also reverses the prerequisite 093000 migration. To retain that prerequisite, a reviewed rollback must target only the four Phase 2 migrations (latest four steps); do not run a broad module rollback.
8. MariaDB DDL is not transactional across the full migration batch. Application maintenance and a database backup remain necessary for deploying/rolling back on another populated installation. The migration fails on unresolved legacy dates/actors rather than repairing them with guessed data.

## Every file changed in this phase

Paths below are relative to `backend/`. Earlier work in this dirty workspace is excluded from this list.

### New files (16)

1. `modules/HumanResource/Database/Migrations/2026_09_14_100000_create_mission_master_tables.php`
2. `modules/HumanResource/Database/Migrations/2026_09_14_101000_extend_mission_foundation.php`
3. `modules/HumanResource/Database/Migrations/2026_09_14_102000_create_mission_destinations_and_history.php`
4. `modules/HumanResource/Database/Migrations/2026_09_14_103000_enforce_mission_foundation_integrity.php`
5. `modules/HumanResource/Database/Seeders/MissionFoundationSeeder.php`
6. `modules/HumanResource/Enums/Mission/MissionStatus.php`
7. `modules/HumanResource/Enums/Mission/MissionCreationPath.php`
8. `modules/HumanResource/Enums/Mission/MissionCreationSource.php`
9. `modules/HumanResource/Enums/Mission/ApprovalAction.php`
10. `modules/HumanResource/Enums/Mission/DestinationScope.php`
11. `modules/HumanResource/Entities/MissionType.php`
12. `modules/HumanResource/Entities/TransportType.php`
13. `modules/HumanResource/Entities/MissionDestination.php`
14. `modules/HumanResource/Entities/MissionStatusHistory.php`
15. `tests/Feature/MissionDatabaseFoundationTest.php`
16. `docs/mission-phase2-database-foundation.md`

### Updated files (6)

1. `modules/HumanResource/Entities/Mission.php` — new fields/casts/relationships and formal-report completion guard; legacy status unchanged.
2. `modules/HumanResource/Entities/MissionAssignment.php` — additional snapshot fields and ordering cast.
3. `modules/HumanResource/Entities/MissionDocument.php` — reference/report fields, casts and relationships.
4. `modules/HumanResource/Entities/MissionReport.php` — structured fields, submission scope and relationships.
5. `modules/HumanResource/Http/Controllers/MissionController.php` — server-stamped submission, formal-report completion check, null-path download returns 404.
6. `tests/Feature/MissionManagementTest.php` — SQLite report fixture extension and regressions for draft rejection/server-owned actors.

The existing 093000 migration was executed but its file was not edited. Stop at Phase 2; Phase 3 requires the user's next approval.

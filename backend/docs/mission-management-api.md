# គ្រប់គ្រងបេសកកម្មក្រៅអង្គភាព / Official missions

ម៉ូឌុលនេះសម្រាប់ចាត់តាំងមន្ត្រីចេញបំពេញការងារក្រៅអង្គភាព៖ ចុះត្រួតពិនិត្យមណ្ឌលសុខភាព ចុះតាមភូមិ/សហគមន៍ បំពេញការងារតាមខេត្ត បណ្ដុះបណ្ដាល និងប្រជុំ/សិក្ខាសាលា។

## ចូលប្រើ

- Laravel: **Sidebar → គ្រប់គ្រងបេសកកម្ម** (`/hr/missions`).
- Mobile: **បេសកកម្ម → ចុចបេសកកម្មមួយ** ដើម្បីមើលព័ត៌មាន ក្រុម និងឯកសារ ចាប់ផ្ដើម និងរាយការណ៍។
- Figma reference: file `4yvVLUd3t0sB10oFn1AyOI`, page `2:8`, Mission Detail frame `92:2998`.
- Types: `inspection`, `community_visit`, `provincial_assignment`, `training`, `meeting`, `other`.
- Required: title, destination (facility/village/district/province/training venue), start/end dates, at least one active officer. Optional: order number, purpose and supporting documents.

## លំហូរការងារ

1. អ្នកគ្រប់គ្រងបង្កើតសេចក្ដីព្រាង ឬស្នើសុំអនុម័ត ដោយកំណត់មន្ត្រីចូលរួម។
2. អ្នកមានសិទ្ធិអនុម័តពិនិត្យ និងអនុម័ត/បដិសេធ។ ការបដិសេធត្រូវមានមូលហេតុ។
3. មន្ត្រីក្នុងក្រុមចាប់ផ្ដើមបេសកកម្មក្នុងចន្លោះកាលបរិច្ឆេទដែលបានអនុម័ត។
4. មន្ត្រីរាយការណ៍លទ្ធផល; ការផ្ញើម្ដងទៀតធ្វើបច្ចុប្បន្នភាពរបាយការណ៍របស់មន្ត្រីនោះ។
5. អ្នកគ្រប់គ្រងបញ្ចប់បេសកកម្ម បន្ទាប់ពីមានរបាយការណ៍យ៉ាងហោចណាស់មួយ។

`status` retains the attendance-compatible approval values: `draft`, `pending`, `approved`, `rejected`, `cancelled`.
`display_status` returns `in_progress` after the team starts and `completed` after management closes it; approval remains `approved` so attendance still recognizes the authorized mission dates. Starting is shared by the team; reports are per officer. Completion requires a report, not reports from every officer. The date range is inclusive and the duration counts calendar days. Timestamps use the application's configured timezone. No automated completion solely because the end date passed.

Draft/pending missions can be edited. Approved missions cannot change dates or team; cancel and create a new request if these change. Approved overlaps for the same officer are rejected with 409. Cancelling deactivates all assignments and removes the mission exemption from future attendance calculations. Existing stored attendance snapshots must be regenerated through the existing attendance tools if a historical approval/cancellation changes them. Only draft/rejected/cancelled missions can be soft-deleted.

## Access

All `/api/v1/missions` endpoints require the existing Sanctum bearer token. Mobile defaults to the signed-in officer's active assignments and missions they created. Drafts are hidden from participants until submitted. Request body employee IDs never select the identity for starting/reporting.

| Permission | Use |
| --- | --- |
| `read_mission` | Sidebar, management list (`scope=managed`), managed details |
| `create_mission` | Create missions and search assignable officers |
| `update_mission` | Edit, attach documents, cancel, complete |
| `approve_mission` | Approve/reject pending missions |
| `delete_mission` | Soft-delete eligible missions |

System administrators have full management access. Other managers need the relevant permission and organizational responsibility covering the entire team (including sub-units supported by the existing hierarchy service). A mission containing an officer outside the manager's scope cannot be managed. Ordinary assigned officers can read their mission, download its documents, start it and report without management permissions. API resources expose only officers' IDs/names/codes, not full HR records. Reports are visible to the mission's authorized viewers.

The permission migration ensures these permissions exist and grants them to the existing Super Admin role. Assign permissions and organizational responsibilities to other managers through the existing role management screens.

## API

Base: `<Laravel base URL>/api/v1`. Headers: `Authorization: Bearer <token>`, `Accept: application/json`; JSON writes also use `Content-Type: application/json`.

| Method | Path | Behavior |
| --- | --- | --- |
| GET | `/missions` | Paginated own missions; management uses `scope=managed` |
| GET | `/missions/types` | Type values and Khmer labels |
| GET | `/missions/employees?q=...&page=1` | Scoped active officers, 30/page |
| POST | `/missions` | Create draft/pending (201) |
| GET | `/missions/{id}` | Detail, team, assigner, documents, reports and action flags |
| PUT | `/missions/{id}` | Update draft/pending; send required fields and entire team |
| POST | `/missions/{id}/review` | `decision: approved/rejected`, `reason` required on rejection |
| POST | `/missions/{id}/cancel` | Cancel eligible mission |
| POST | `/missions/{id}/start` | Assigned officer starts; repeat is idempotent while unfinished |
| POST | `/missions/{id}/report` | `summary` (1–20,000 characters); upsert own report |
| POST | `/missions/{id}/complete` | Manager closes an ongoing mission with a report |
| DELETE | `/missions/{id}` | Soft-delete eligible mission |
| POST | `/missions/{id}/documents` | Multipart field `document`; PDF/Word/Excel/JPEG/PNG, up to 10 MB |
| GET | `/missions/{id}/documents/{document}` | Authenticated file download |
| GET | `/missions/{id}/documents/{document}/signed-url` | Generate a 2-minute browser download link |

List filters: `q`, `mission_type`, `status`, `from_date`, `to_date`, `page`, `per_page` (1–100, default 20), `scope` (`mine` or `managed`). Date filters select overlapping mission date ranges. `status=approved` includes ongoing/completed approved missions; use `in_progress` or `completed` for the specific execution state.

Create example (employee IDs are database IDs returned by `/missions/employees`):

```json
{
  "title": "ចុះត្រួតពិនិត្យមណ្ឌលសុខភាព",
  "mission_type": "inspection",
  "order_number": "PHD-2026-123",
  "destination": "មណ្ឌលសុខភាព សេសាន ខេត្តស្ទឹងត្រែង",
  "start_date": "2026-09-14",
  "end_date": "2026-09-16",
  "purpose": "ត្រួតពិនិត្យគុណភាពសេវា និងស្ថានភាពមន្ត្រី",
  "employee_ids": [1, 2],
  "status": "pending"
}
```

Success responses follow the mobile envelope:

```json
{
  "response": {
    "status": "ok",
    "data": {
      "data": [],
      "current_page": 1,
      "per_page": 20,
      "last_page": 1,
      "total": 0
    }
  }
}
```

Detail and mutation responses put the mission directly under `response.data`. Fields include `id`, `uuid`, `title`, `mission_type`, `mission_type_label`, `order_number`, `destination`, `purpose`, ISO date-only `start_date`/`end_date`, `duration_days`, `status`, `display_status`, `assignments_count`, `assigner` (`id`, `name`, `initial`), `team_members` (`id`, `name`, `employee_id`, `initial`), `documents` (`id`, `name`, byte `size`, `mime_type`, authenticated `download_url`), approval/start/completion timestamps, `rejected_reason`, `reports`, and `actions.can_start/can_report`.

Use the action flags to enable mobile controls; the server checks them again on submission. Do not send `approved_by`, timestamps or execution state as editable fields. Documents are stored on the private `local` disk; no public storage link is needed. Open the short-lived signed URL for browsers that cannot send the bearer token. Anyone holding that URL can download until it expires, so do not persist or log it. Deleted missions no longer serve documents.

Errors use Laravel's JSON `message` and, for validation, `errors`: 401 unauthenticated, 403 insufficient permission/scope, 404 invisible/missing mission/document, 409 invalid transition/overlap, 422 invalid input. No message/email notification is sent by this module.

## Installation and checks

Run only the new migrations when updating an existing installation with unrelated pending migrations:

```powershell
cd backend
php artisan migrate --path=modules/HumanResource/Database/Migrations/2026_09_13_090000_extend_mission_management.php
php artisan migrate --path=modules/HumanResource/Database/Migrations/2026_09_13_091000_ensure_mission_permissions.php
php artisan migrate --path=modules/HumanResource/Database/Migrations/2026_09_13_092000_add_official_mission_classification.php
php artisan view:clear
php vendor/bin/phpunit tests/Feature/MissionManagementTest.php
cd ../mobile
flutter test test/mission_api_test.dart
```

The existing `2026_04_19_100000_create_shift_and_mission_tables` migration is a prerequisite. The new migrations have been applied to the current local installation. Restart/rebuild Flutter to include the new detail route and bundled Figma PNG assets. Existing application fonts, colors and weekday branding are reused.

Verified: 9 isolated Laravel feature tests (95 assertions), 3 Flutter service/widget tests, targeted Flutter analysis, and actual Laravel list/create/detail form rendering. The pre-existing `AttendanceV1SmokeTest` currently fails at shift creation (404 at line 48) before exercising Mission. Browser visual inspection was unavailable because no connected browser was exposed in this session.

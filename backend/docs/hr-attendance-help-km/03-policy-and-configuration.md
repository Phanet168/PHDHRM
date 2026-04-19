# 03) គោលនយោបាយ និងការកំណត់ស្តង់ដារ

## A. គោលនយោបាយវត្តមាន (Recommended)

1. កំណត់ម៉ោងធ្វើការ:
   - Work start: `attendance_start`
   - Work end: `attendance_end`
2. កំណត់ grace:
   - Late grace minutes
   - Early leave grace minutes
3. ស្ថានភាពថ្ងៃ:
   - `PRESENT`
   - `LATE`
   - `EARLY_LEAVE`
   - `LATE_EARLY_LEAVE`
   - `PARTIAL` (ឬ unpaired punches)

## B. QR + Geofence Standard

1. QR ត្រូវ short expiry (ណែនាំ 1-5 នាទី)
2. បុគ្គលិកត្រូវនៅក្នុង geofence អង្គភាព
3. ប្រព័ន្ធត្រូវបង្ហាញ `Distance` និង `Allowed`
4. ប្រព័ន្ធត្រូវកាត់ duplicate scan ក្នុងពេលខ្លី

## C. Settings ត្រូវតែមាន

នៅ `App Setting`:

- `latitude`
- `longitude`
- `acceptable_range`

នៅ `Point Settings`:

- `attendance_start`
- `attendance_end`

នៅ `.env` (optional tuning):

- `ATTENDANCE_LATE_GRACE_MINUTES=10`
- `ATTENDANCE_EARLY_LEAVE_GRACE_MINUTES=10`
- `ATTENDANCE_REQUIRE_QR_TOKEN=true` (recommended សម្រាប់ production)

## D. Data Governance

1. Role/Permission ត្រូវច្បាស់
2. រាល់ការកែ attendance ត្រូវមាន audit trail
3. Reports ប្រចាំថ្ងៃត្រូវពិនិត្យ exceptions
4. Backup និង data retention ត្រូវមានផែនការ


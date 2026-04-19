# 05) Troubleshooting FAQ

## 1) Login error `419`

មូលហេតុទូទៅ:

- Session/Cookie domain មិនត្រូវ
- CSRF token ផុតសុពលភាព
- APP_URL មិនត្រូវនឹង URL ពិតដែលប្រើ

ដោះស្រាយ:

1. ពិនិត្យ `.env`:
   - `APP_URL`
   - `SESSION_DOMAIN`
   - `SESSION_DRIVER`
2. Clear cache:
   - `php artisan config:clear`
   - `php artisan cache:clear`
   - `php artisan route:clear`
3. Restart web server/service

## 2) Scan error `invalid_qr` ឬ `expired_qr`

ដោះស្រាយ:

1. Generate QR ថ្មី
2. កុំប្រើ screenshot ចាស់
3. ពិនិត្យ expiry minutes

## 3) Scan error `out_of_range`

ដោះស្រាយ:

1. ពិនិត្យ GPS ទូរស័ព្ទ
2. ពិនិត្យ `acceptable_range` នៅ App Setting
3. ពិនិត្យ lat/lng អង្គភាព (department/division)

## 4) Scan error `no_gps`/permission denied

ដោះស្រាយ:

1. បើក Location service
2. អនុញ្ញាត App Location permission
3. បិទ battery optimization សម្រាប់ app

## 5) Attendance មើលទៅ IN/OUT ខុស

ចំណាំ:

- ប្រព័ន្ធគិត punch ជាគូ IN/OUT
- Scan ជាប់ៗគ្នាក្នុងពេលខ្លីអាចត្រូវចាត់ជា duplicate guard

ដោះស្រាយ:

1. ពិនិត្យ record ថ្ងៃនោះនៅ Attendance history
2. ពិនិត្យ exceptions (`UNPAIRED_PUNCH`)
3. កែតាម SOP `04-exceptions-and-corrections.md`

## 6) អ្នកប្រើថ្មី login មិនបាន (device pending)

ដោះស្រាយ:

1. HR/Admin ចូល Device management
2. អនុម័តឧបករណ៍ (pending -> active)
3. អ្នកប្រើ login ម្តងទៀត


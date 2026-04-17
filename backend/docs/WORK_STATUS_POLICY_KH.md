# Work Status Policy (Legacy `WorkStatus` -> HRM)

## គោលបំណង
- កំណត់ Mapping ស្ថានភាពមន្ត្រីពី DB ចាស់ (`WorkStatusID`) ទៅ `is_active` និង `is_left`
- ឲ្យសមស្របនឹងការគ្រប់គ្រងមន្ត្រីរាជការរដ្ឋាភិបាលកម្ពុជា

## Mapping ចម្បង (3-State)

### Active (`service_state=active`, `is_active=1`, `is_left=0`)
- 1 `Currently Working`
- 5 `Returned to Work`
- 8 `Reinstated`
- 23 `Transfer out of Central to Province` (សម្រាប់ប្រព័ន្ធខេត្ត ចាត់ជាចូលមកខេត្ត)
- 26..35 `New Staff 2005..2012`
- 37 `Titular`
- 39 `Transfer into Province from Province`
- 40 `Transfer into Province from Central`
- 41 `Transfer into Province from another Ministry`
- 43..50 `New Staff 2013..2020`

### Suspended (`service_state=suspended`, `is_active=1`, `is_left=0`)
- 2 `Absent without Pay by Request`
- 4 `On Notice`
- 7 `Assignement out of Cadre`
- 18 `On Probation`
- 19 `Study Overseas`
- 24 `Floating`
- 25 `Contracted Staff`
- 36 `Study in the Country`
- 38 `Probation Extended`

### Inactive (`service_state=inactive`, `is_active=0`, `is_left=1`)
- 0 `Unknown/Empty`
- 3 `Removed`
- 6 `Dismissed`
- 9 `Retirement by retirement age`
- 10 `Retired by Request`
- 11 `Removed - Abandonement`
- 12 `Dead`
- 13 `Transfer out of Province to Province`
- 14 `Transfer out of Province to Central`
- 15 `Transfer out of Province to another Ministry`
- 16 `Transfer out of MoH to other Ministry`
- 17 `Forced Retirement`
- 20 `Overstay of Leave Without Pay`
- 21 `Removed by request`
- 22 `Disability`
- 28 `UNKNOWN`
- 42 `Transfer into Central from Province` (មើលជាចេញពីខេត្ត)

## កន្លែងកូដដែលអនុវត្ត
- `app/Console/Commands/ImportLegacyStaff.php`
  - `FORCED_ACTIVE_STATUS_IDS`
  - `FORCED_INACTIVE_STATUS_IDS`
  - `classifyLegacyEmploymentStatus(...)`
  - `syncLegacyStatusHistory(...)`

## ចំណាំ
- បើនយោបាយស្ថាប័នផ្លាស់ប្តូរ អាចកែ list ID នៅក្នុង command បានភ្លាម។
- បច្ចុប្បន្ន `StatusHistory` ត្រូវបាន sync ទៅ `employee_service_histories` ជាប្រវត្តិ។

# មគ្គុទេសក៍កំណត់តួនាទី និងសិទ្ធិសម្រាប់ Pharmaceutical

## គោលបំណង

ឯកសារនេះបង្ហាញ mapping សិទ្ធិថ្មីសម្រាប់ module Pharmaceutical ដើម្បីឲ្យ Admin អាចកំណត់តួនាទីអ្នកប្រើតាមផ្នែកការងារ បានត្រឹមត្រូវ។

## Permission កម្រិតទូទៅ (Legacy/Fallback)

សិទ្ធិក្រុមចាស់នៅតែគាំទ្រ ដើម្បីមិនប៉ះពាល់ role ដែលកំពុងប្រើ:

- create_pharmaceutical_management
- read_pharmaceutical_management
- update_pharmaceutical_management
- delete_pharmaceutical_management

## Permission កម្រិតលម្អិត (Granular)

### Pharm Medicines

- create_pharm_medicines
- read_pharm_medicines
- update_pharm_medicines
- delete_pharm_medicines

### Pharm Distributions

- create_pharm_distributions
- read_pharm_distributions
- update_pharm_distributions
- delete_pharm_distributions

### Pharm Dispensings

- create_pharm_dispensings
- read_pharm_dispensings
- update_pharm_dispensings
- delete_pharm_dispensings

### Pharm Reports

- create_pharm_reports
- read_pharm_reports
- update_pharm_reports
- delete_pharm_reports

### Pharm Stock

- create_pharm_stock
- read_pharm_stock
- update_pharm_stock
- delete_pharm_stock

### Pharm Users

- create_pharm_users
- read_pharm_users
- update_pharm_users
- delete_pharm_users

## Menu និង Route ដែលពាក់ព័ន្ធ

- Medicines/Categories ប្រើសិទ្ធិ Pharm Medicines
- Distributions ប្រើសិទ្ធិ Pharm Distributions
- Dispensing ប្រើសិទ្ធិ Pharm Dispensings
- Reports និង Summary Reports ប្រើសិទ្ធិ Pharm Reports
- Stock និង Stock Adjustments ប្រើសិទ្ធិ Pharm Stock
- Users ប្រើសិទ្ធិ Pharm Users
- Help Center ប្រើសិទ្ធិ Read (fallback ឬ granular)

## របៀបកំណត់ Role

1. ចូលទៅ Settings > Role List
2. ចុច Edit លើ Role ដែលចង់កំណត់
3. រកក្រុម Pharmaceutical Management
4. ធីកសិទ្ធិតាមផ្នែកការងារ
5. ចុច Submit

## Recommendation តាមតួនាទី

### Pharmacy Clerk

- read_pharm_medicines
- create_pharm_dispensings
- read_pharm_dispensings
- read_pharm_stock

### Pharmacy Store Keeper

- read_pharm_medicines
- create_pharm_distributions
- read_pharm_distributions
- update_pharm_distributions
- create_pharm_stock
- read_pharm_stock
- update_pharm_stock

### Pharmacy Reporter

- read_pharm_reports
- create_pharm_reports
- update_pharm_reports
- read_pharm_stock

### Pharmacy Admin

- read/create/update/delete លើគ្រប់ក្រុម Pharm ...

## ចំណាំសំខាន់

- ប្រសិនបើ role ចាស់ធ្លាប់ប្រើ read_pharmaceutical_management នឹងនៅអាចចូលប្រើបានដដែល
- សម្រាប់ role ថ្មី គួរប្រើ granular permissions ជាចម្បង
- ក្រោយកែ role សូមឲ្យ user logout/login ម្តង ដើម្បី refresh permission cache
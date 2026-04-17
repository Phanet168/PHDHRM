# ១០) FAQ និង Troubleshooting

## Q1: មិនឃើញ Help ឬ Tab Help

**ពិនិត្យ**
1. សិទ្ធិអ្នកប្រើ (role/permission)
2. session/login ថ្មី
3. cache ប្រព័ន្ធ

**ដោះស្រាយ**
- Logout/Login
- Clear cache (`php artisan optimize:clear`)

## Q2: អក្សរខ្មែរ ខូច (Mojibake)

**មូលហេតុទូទៅ**
- Encoding មិនមែន UTF-8
- Locale/Language key ខុស
- Font មិនគាំទ្រ

**ដោះស្រាយ**
1. ពិនិត្យ file encoding = UTF-8
2. ពិនិត្យ language key ទាំង frontend/backend
3. ពិនិត្យ font និង browser render

## Q3: Save រួច តែទិន្នន័យមិន Update

**ពិនិត្យ**
- មាន validation error ឬទេ
- ថ្ងៃមានប្រសិទ្ធភាពត្រឹមត្រូវឬទេ
- កំពុងប៉ះ record ចាស់/record ថ្មីខុសគ្នាឬទេ

## Q4: សំណើនៅ Pending មិនចូល Approved

**ពិនិត្យ**
1. Workflow policy matrix
2. Role assignment របស់ approver
3. ស្ថានភាពសំណើបច្ចុប្បន្ន
4. មាន step ខ្វះមុនអនុម័តចុងក្រោយឬទេ

## Q5: ចំនួន KPI មិនត្រូវគ្នា

**មូលហេតុ**
- Filter មិនដូចគ្នា
- គណនា cutoff date ខុស
- រួម Draft/Pending/Approved ខុសច្បាប់គណនា

**ដោះស្រាយ**
1. ស្វែងយល់លក្ខខណ្ឌរាប់ច្បាស់
2. Reset filter
3. Recalculate

## Q6: មិនឃើញថ្ងៃនៅសល់ ឬស្ថានភាពប្រវត្តិ

**ពិនិត្យ**
- តើប្រភេទសំណើ/ប្រភេទការងារត្រូវទេ
- តើ history record ត្រូវបានបង្កើតរួចឬនៅ
- តើមាន policy cap/day rule ឬទេ

## Q7: Export/PDF បង្ហាញមិនត្រឹមត្រូវ

**ពិនិត្យ**
1. Data source ត្រឹមត្រូវ
2. Template mapping ត្រឹមត្រូវ
3. Print scale និង zoom
4. Font ដែលប្រើក្នុង report

## Q8: ករណីមិនដោះស្រាយបាន

សូមរាយការណ៍ព័ត៌មានខាងក្រោមទៅ Admin/Developer:

- Screenshot បញ្ហា
- URL ទំព័រ
- ពេលវេលាកើតបញ្ហា
- ជំហានដែលបានធ្វើមុនកើតបញ្ហា
- User role ដែលកំពុងប្រើ

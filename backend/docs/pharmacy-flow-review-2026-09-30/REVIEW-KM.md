# PHDHRM — ពិនិត្យលំហូរគ្រប់គ្រងឱសថ

កាលបរិច្ឆេទ៖ 30 កញ្ញា 2026 · Code baseline: `ba734a1` + working tree  
ទីតាំង៖ `C:/xampp/htdocs/PHDHRM/backend/modules/Pharmaceutical`

ឯកសារនេះសម្រាប់ពិភាក្សាកែ UI និង Logic។ Diagram បង្ហាញមុខងារបច្ចុប្បន្នតាម routes, controllers, Blade views, models និង migrations។ នេះជាការពិនិត្យកូដ និង UI templates; មិនបានចូលសាកល្បងជាមួយគណនីអ្នកប្រើ ឬផ្ទៀងផ្ទាត់ទិន្នន័យក្នុង Database ទេ។ មិនបានកែ application code ឬ Database។ ផ្នែក “សំណើ” ខាងក្រោមមិនទាន់អនុវត្ត។

## រូបភាព និងប្រភពអាចកែបាន

| លេខ | ខ្លឹមសារ | រូបភាព | Vector | Mermaid |
|---|---|---|---|---|
| 01 | ទិសដៅចែកចាយ និងរាយការណ៍រវាងគ្រឹះស្ថាន | [PNG](01-business-flow.png) | [SVG](01-business-flow.svg) | [Source](01-business-flow.mmd) |
| 02 | ប័ណ្ណចែកចាយ ស្តុក ផ្តល់ឱសថ និងស្ថានភាពរបាយការណ៍ | [PNG](02-process-flow.png) | [SVG](02-process-flow.svg) | [Source](02-process-flow.mmd) |
| 03 | Data Flow — ប្រតិបត្តិការ និងរបាយការណ៍ | [PNG](03-data-flow.png) | [SVG](03-data-flow.svg) | [Source](03-data-flow.mmd) |
| 04 | ER Diagram — ទំនាក់ទំនងតារាង | [PNG](04-data-model.png) | [SVG](04-data-model.svg) | [Source](04-data-model.mmd) |

បើក [index.html](index.html) ដើម្បីមើលរូបភាពទាំងអស់ និងទាញយក។ PNG មានចំណងជើង និងកំណត់សម្គាល់; SVG ជា Diagram សុទ្ធ។ Mermaid ជាប្រភពសម្រាប់កែ Node និង Arrow។

## 1. អ្នកប្រើ និងទិសដៅ

- **PHD / មន្ទីរសុខាភិបាលខេត្ត**៖ ចែកចាយទៅ OD ឬមន្ទីរពេទ្យ; ពិនិត្យ/អនុម័តរបាយការណ៍។
- **OD / ស្រុកប្រតិបត្តិ**៖ ទទួលពី PHD និងចែកចាយទៅ HC; ពិនិត្យ/អនុម័តរបាយការណ៍។
- **Hospital / មន្ទីរពេទ្យ និង HC / មណ្ឌលសុខភាព**៖ ទទួលឱសថ ផ្តល់ឱសថដល់អ្នកជំងឺ និងរៀបចំរបាយការណ៍។
- **Admin** មាន exception ក្នុង level checks។ សិទ្ធិ route នៅតែផ្អែកលើ permissions។ Scope ទិន្នន័យមកពី `OrgScopeService`។
- ទិសដៅរបាយការណ៍គឺ HC → OD → PHD និង Hospital → PHD តាម `parent_department_id`។ ប័ណ្ណនីមួយៗមានអង្គភាពមេមួយ; កូដមិនមានការបូករបាយការណ៍កូន និងបញ្ជូនបន្តទៅកម្រិតបន្ទាប់ដោយស្វ័យប្រវត្តិទេ។
- ប្រភេទចែកចាយ 3 ប្រភេទមានក្នុង model ប៉ុន្តែ `store()` មិនផ្ទៀងផ្ទាត់គូអ្នកផ្ញើ/អ្នកទទួលតាមប្រភេទអង្គភាព ឬ hierarchy ទាំងស្រុងទេ។ Diagram 01 បង្ហាញទិសដៅដែលប្រភេទទាំងនេះកំណត់។

## 2. ដំណើរការបច្ចុប្បន្ន

### ចែកចាយ និងទទួល

1. ជ្រើសប្រភេទចែកចាយ គ្រឹះស្ថានផ្ញើ គ្រឹះស្ថានទទួល កាលបរិច្ឆេទ ឱសថ ចំនួន Batch Expiry និងតម្លៃ។
2. រក្សាទុកជា `draft`។ មិនដកស្តុកនៅជំហាននេះ។
3. ចុច Send៖ ប្តូរទៅ `sent` និងដកស្តុកអ្នកផ្ញើ។
4. អ្នកទទួលបញ្ចូលចំនួនទទួល។ ប្រព័ន្ធបូកស្តុកអ្នកទទួលភ្លាម។
5. បើគ្រប់មុខមាន `quantity_received >= quantity_sent` នោះ `completed`; បើមិនគ្រប់ នោះ `partial` ហើយអាចទទួលម្តងទៀត។
6. Model មាន `received` ប៉ុន្តែ handler `receive()` បច្ចុប្បន្នសរសេរ `partial` ឬ `completed` តែប៉ុណ្ណោះ។

### ផ្តល់ឱសថ និងកែសម្រួល

- ផ្តល់ឱសថ៖ ឈ្មោះអ្នកជំងឺ លេខសម្គាល់ ភេទ អាយុ រោគវិនិច្ឆ័យ និងមុខឱសថ/ចំនួន/របៀបប្រើ។ រក្សាទុកហើយដកស្តុកភ្លាម; មិនមាន workflow អនុម័តប័ណ្ណនេះ។
- ព័ត៌មានអ្នកជំងឺរក្សាទុកលើ `pharm_dispensings`។ `patient_id_no` ជា text; មិនមែន FK ទៅ patient table ក្នុង schema នេះ។
- កែសម្រួល៖ `damaged`, `expired`, `loss` ដកស្តុក; `correction` បូកស្តុក។ Input quantity ត្រូវធំជាងសូន្យ។
- ស្តុកគិតតាម `department_id + medicine_id + batch_no`។ Expiry និងតម្លៃជាលក្ខណៈរបស់ stock row; មិនមាន stock-batch entity ដាច់ដោយឡែក។

### របាយការណ៍

- ប្រចាំខែ ត្រីមាស ឆ្នាំ ឬ Ad-hoc។ អាចបញ្ចូលដោយដៃ ឬ Auto-generate។
- UI៖ `draft → submitted → reviewed → approved` និងអាច `submitted → approved` ដោយផ្ទាល់។
- Draft អាចកែ និងលុប។ មិនមាន Reject / Return for correction នៅ workflow បច្ចុប្បន្ន។
- Auto-generate អាន closing របាយការណ៍មុនដែល submitted/reviewed/approved, ចំនួនទទួល, ចំនួនផ្តល់ និងការកែសម្រួលក្នុងគ្រា។
- បើ opening map ទទេ ប្រព័ន្ធយក **ស្តុកបច្ចុប្បន្ន** ជា opening fallback។
- រូបមន្តបច្ចុប្បន្ន៖ `Closing = max(0, Opening + Received − Dispensed − Damaged − Expired + Adjustment)`។
- Dashboard និង Summary អានកំណត់ត្រាទាំងនេះ; Summary បង្ហាញ variance រវាងស្តុកបច្ចុប្បន្ន និង Closing ដែលគណនា។ ការអនុម័តរបាយការណ៍មិនកែស្តុកជាក់ស្តែងទេ។

## 3. ចំណុច Logic គួរកែ — សំណើសម្រាប់ពិភាក្សា

| ID | អ្វីឃើញក្នុងកូដ | ផលប៉ះពាល់ / ឧទាហរណ៍ | សំណើ |
|---|---|---|---|
| L01 | Receive សរសេរជំនួស quantity_received ប៉ុន្តែបូកចំនួនបញ្ចូលទាំងមូលរាល់លើក | ផ្ញើ 100; ទទួល 40 រួចបញ្ចូល 100 ជាចំនួនសរុប → ស្តុក +140 ខណៈប័ណ្ណបង្ហាញ 100 | បង្កើត receipt header/items សម្រាប់ការទទួលម្តងៗ; input “ទទួលលើកនេះ”; គណនា cumulative និង remaining; ការពារបញ្ជូនស្ទួន |
| L02 | Receive មិនកំណត់ quantity ≤ remaining និងមិនចង item id ទៅប័ណ្ណបច្ចុប្បន្ន | ទទួលលើស ឬ update item របស់ប័ណ្ណផ្សេងអាចធ្វើឱ្យទិន្នន័យមិនត្រូវគ្នា | Validate item ownership, quantity, scope និង status នៅ server ក្នុង transaction |
| L03 | Send/Dispense/Adjustment ប្រើ max(0); dispensing អាចរក្សាទុកប័ណ្ណទោះរក stock មិនឃើញ | មាន 5 តែផ្តល់ 10 → ប័ណ្ណ 10 ស្តុកនៅ 0; មិនបង្ហាញកំហុសថាខ្វះ 5 | ពិនិត្យ available stock; block ចំនួនលើស; lock stock rows និង commit ប័ណ្ណ+ស្តុកជាមួយគ្នា |
| L04 | Dispensing ជ្រើស stock row ដំបូង; មិនមាន FEFO, expiry filter ឬការបែងចែកច្រើន Batch | មុខឱសថមានច្រើន Batch អាចដកមិនត្រូវ Batch ឬមិនគ្រប់ | ជ្រើស Batch ពីស្តុកពិត; FEFO = ផុតកំណត់មុនប្រើមុន; block Batch ផុតកំណត់ |
| L05 | របាយការណ៍មិនដក distributed-out; fallback យកស្តុកឥឡូវជា opening | PHD/OD ចែកចាយចេញ តែ Closing របាយការណ៍មិនដក; គណនាគ្រាចាស់អាចខុស | បន្ថែម Distributed out និង stock movement ledger; គណនា opening/closing តាមថ្ងៃគ្រាពិត |
| L06 | Partial receipt មាន received_date តែមួយលើ header និងត្រូវសរសេរថ្មីរាល់ទទួល | ទទួលឆ្លងខែ អាចនាំ cumulative quantity ទៅខែទទួលចុងក្រោយ | រក្សាទុកថ្ងៃ និង quantity នៃ receipt នីមួយៗ; aggregate តាម receipt date |
| L07 | review() មិនផ្ទៀងផ្ទាត់ current status និង report department scope ដូច UI | លំដាប់ review/approve មិនត្រូវបានធានាដោយ server | កំណត់ state transition, reviewer scope និង return-for-correction; រក្សាប្រវត្តិ review |
| L08 | Report store/update ទទួល Closing ពី input; update header/delete items/create items មិនស្ថិតក្នុង transaction មួយ | Closing អាចខុសរូបមន្ត; បរាជ័យកណ្តាលអាចទុក report មិនពេញលេញ | គណនាឡើងវិញនៅ server; atomic save; កំណត់គោលការណ៍ manual override និង audit |
| L09 | Adjustment មួយមានច្រើន rows ប៉ុន្តែមិនមាន transaction ជុំវិញទាំងអស់; Correction បូកតែប៉ុណ្ណោះ | ប្រតិបត្តិការកណ្តាលបរាជ័យអាចបានតែផ្នែក; មិនមាន correction ដកជាប្រភេទ correction | Atomic posting និងកំណត់ correction +/- ជាមួយហេតុផល និង audit |

នេះជាការសន្និដ្ឋានតាមកូដ មិនមែនលទ្ធផល integration tests ទេ។ ត្រូវផ្ទៀងផ្ទាត់ដោយ tests ពេលអនុវត្តកំណែថ្មី។

**រូបមន្តស្នើ (មិនទាន់អនុវត្ត)**៖  
`Closing = Opening + Receipts − Distributed out − Dispensed − Damaged − Expired − Loss + Signed corrections`។  
Opening ត្រូវមកពីប្រវត្តិចលនាស្តុកមុនថ្ងៃចាប់ផ្តើមគ្រា។ “Receipts” គួររាប់ការទទួលពីប្រភពខាងក្រៅផងដែរ បើបានយល់ព្រមបន្ថែមមុខងារនោះ។

## 4. UI ដែលស្នើរៀបចំឡើងវិញ

| ផ្នែក | UI បច្ចុប្បន្ន | សំណើ UI ភ្ជាប់ជាមួយ Logic |
|---|---|---|
| Dashboard | តួលេខប័ណ្ណ ស្តុកទាប និងជិតផុតកំណត់ | បែងជា “ត្រូវធ្វើថ្ងៃនេះ”: រង់ចាំទទួល / ទទួលមិនគ្រប់ / រង់ចាំពិនិត្យ / អស់ស្តុក / ផុតកំណត់ |
| Distributions | បញ្ជី + Form + Detail + Receive modal | Tabs ផ្ញើចេញ/ទទួលចូល; status timeline; បង្ហាញ Sent / Received to date / Remaining / Receive now |
| Stock | Summary ឬតាម Batch; បង្ហាញ quantity > 0 | Filter គ្រឹះស្ថាន ឱសថ Batch Expiry និង “រួមទាំងអស់ស្តុក”; Stock card បង្ហាញប្រវត្តិចូល-ចេញ |
| Dispensing | បញ្ចូលអ្នកជំងឺ; Batch ជា text; ស្តុកសរុបជាបន្ទះព័ត៌មាន | ស្វែងរកឱសថ → ជ្រើស Batch → Available/Expiry → Qty; ព្រមានមុនរក្សាទុក និងត្រួតពិនិត្យនៅ server |
| Adjustments | ប្រភេទ ចំនួន Batch និងមូលហេតុ | បង្ហាញ Before / Change / After; reason ច្បាស់; អនុម័តតាមគោលការណ៍ដែលអ្នកកំណត់ |
| Reports | Auto-generate + editable table + submit/review/approve | Generate → Reconcile variance → Submit → Review/Return → Approve; ចាក់សោ version ដែលអនុម័ត |
| Master data | Medicines និង Categories ជា tabs ផ្សេង | រួមក្រោម “ការកំណត់ឱសថ”; unit/strength/form ច្បាស់លាស់ |

Dashboard បច្ចុប្បន្នរាប់ pending distributions តែ draft/sent មិនរាប់ partial; low stock មាន threshold 10 ថេរ និងមិនរាប់ស្តុកសូន្យ។ ចំណុចទាំងនេះគួរភ្ជាប់ជាមួយ UI ថ្មី។

Menu ស្នើ៖ **ទិដ្ឋភាពទូទៅ · ស្តុក · ចែកចាយ/ទទួល · ផ្តល់ឱសថ · កែសម្រួល · របាយការណ៍ · ការកំណត់**។

## 5. ចំណុចសម្រាប់អ្នកសម្គាល់កែប្រែ

1. PHD ទទួលឱសថពីប្រភពណា? ត្រូវការប័ណ្ណទទួលពីឃ្លាំងកណ្តាល/អ្នកផ្គត់ផ្គង់ ឬប្រើ opening stock?
2. ប័ណ្ណចែកចាយត្រូវអនុម័តមុន Send ឬអនុញ្ញាតផ្ញើភ្លាម?
3. ការទទួលមិនគ្រប់៖ បន្តរង់ចាំ ទទួលបន្ថែម ឬបិទប័ណ្ណជាមួយ discrepancy?
4. ត្រូវការត្រឡប់ឱសថ/បោះបង់ប័ណ្ណ/កែប្រតិបត្តិការដែលបាន post ឬទេ?
5. របាយការណ៍ត្រូវអនុម័ត OD រួច PHD ជាបន្តបន្ទាប់ ឬអង្គភាពមេមួយអនុម័តគ្រប់គ្រាន់?
6. តើអ្នកប្រើអាចកែតួលេខ report ដោយដៃ ឬត្រូវគណនាពីប្រតិបត្តិការទាំងស្រុង?

សូមយោង **Diagram 01–04, L01–L09 ឬឈ្មោះផ្នែក UI** ពេលផ្តល់មតិ ដើម្បីកែបានច្បាស់។

## 6. ប្រភពកូដសំខាន់

ផ្លូវខាងក្រោម relative ទៅ backend:

| ប្រភព | តួនាទី / បន្ទាត់សំខាន់ |
|---|---|
| `modules/Pharmaceutical/Routes/web.php` | Routes និង permissions |
| `modules/Pharmaceutical/Routes/api.php` | Placeholder; មិនមាន pharmaceutical API handlers នៅ file នេះ |
| `modules/Pharmaceutical/Traits/PharmScope.php` | Level និង scope helpers |
| `modules/HumanResource/Support/OrgScopeService.php` | Unit types 1=PHD, 4=OD, 6=Hospital, 7=HC |
| `modules/Pharmaceutical/Http/Controllers/PharmDistributionController.php` | store:66, send:143, receive:179, stock helper:229 |
| `modules/Pharmaceutical/Http/Controllers/PharmDispensingController.php` | store:64; stock lookup:113; clamp:122 |
| `modules/Pharmaceutical/Http/Controllers/PharmStockAdjustmentController.php` | store:75; stock helper:136 |
| `modules/Pharmaceutical/Http/Controllers/PharmReportController.php` | store:87; review:190; generateData:375 |
| `modules/Pharmaceutical/Services/PharmReportService.php` | generateReportData:23; fallback:120; formula:155; stockSummaryReport:271 |
| `modules/Pharmaceutical/Http/Controllers/PharmDashboardController.php` | pending:93; lowStock:110; expiringSoon:116 |
| `modules/Pharmaceutical/Resources/views/_nav.blade.php` | Menu និង permission visibility |
| `modules/Pharmaceutical/Resources/views/distributions/show.blade.php` | Receive modal; default sent quantity:113 |
| `modules/Pharmaceutical/Resources/views/dispensings/create.blade.php` | Batch free text:83; available stock summary:95 |
| `modules/Pharmaceutical/Resources/views/reports/create.blade.php` | Client-side closing calculation:144 |
| `modules/Pharmaceutical/Resources/views/reports/show.blade.php` | UI review/approve conditions:55 |
| `modules/Pharmaceutical/Entities/*.php` | Table names, status constants, Eloquent relations |
| `modules/Pharmaceutical/Database/Migrations/2026_04_05_*.php` | 10 pharmaceutical tables និង fields |

ERD បង្ហាញទំនាក់ទំនងតាម model និង reference columns។ Pharm migrations ប្រើ indexed IDs ប៉ុន្តែមិនបានប្រកាស SQL foreign-key constraints សម្រាប់ទំនាក់ទំនងទាំងនេះទេ។ User audit columns និង HR access-control tables មិនបានបង្ហាញដើម្បីរក្សាភាពងាយអាន។ Unique stock key គឺ department + medicine + nullable batch_no; គួរពិនិត្យការរក្សាឯកត្តភាព Batch ទទេនៅ Database ពេលកែលម្អ។

Figma/FigJam៖ បានសាកល្បងបង្កើត Diagram ប៉ុន្តែ connector តម្រូវឱ្យជ្រើស Team/Organization ក្នុង widget មុន; មិនទាន់ទទួលបាន FigJam URL។ ឯកសារ PNG/SVG/Mermaid ខាងលើអាចប្រើភ្លាម។


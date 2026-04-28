# ជំនួយការគ្រប់គ្រងរចនាសម្ព័ន្ធអង្គភាព (Org Structure)

ឯកសារនេះសម្រាប់ផ្ទាំង `Org Structure` ប៉ុណ្ណោះ។ គោលបំណងគឺជួយឲ្យអ្នកប្រើអាចកំណត់សិទ្ធិ និងលំហូរអនុម័តបានត្រឹមត្រូវ ទោះជាករណីស្មុគស្មាញក៏ដោយ។

## 1) សង្ខេបស្រួលយល់: តើផ្ទាំងណាធ្វើអ្វី?

1. `Responsibilities`
   កំណត់តួនាទីអាជីវកម្មសម្រាប់អនុម័ត ឬពិនិត្យ (ឧ. `head`, `deputy_head`, `manager`)។
2. `Org Position Matrix`
   កំណត់ថា `Org Unit Type` ណាអាចភ្ជាប់ជាមួយ `Position` ណា ហើយមានលទ្ធភាព `can approve` ឬអត់។
3. `User Assignments`
   ភ្ជាប់មនុស្សពិតទៅតួនាទីពិត: `User + Org Unit + Responsibility + Scope + Effective Date`។
4. `Org Role Permission Matrix`
   អនុញ្ញាតសកម្មភាពតាមម៉ូឌុល: `Module + Action + Responsibility`។
5. `Workflow Policy Matrix`
   សរសេរជំហានអនុម័តជាក់ស្តែង (step by step) និងអ្នកអនុម័តតាម `Actor type`។
6. `Legacy Org Roles`
   ផ្ទុកទិន្នន័យ compatibility ប្រព័ន្ធចាស់។ ប្រព័ន្ធ sync ពី Assignment ទៅ Legacy ដោយស្វ័យប្រវត្តិ។

## 2) លំដាប់កំណត់ត្រឹមត្រូវ (គួរធ្វើតាមនេះ)

1. កំណត់ `Responsibilities` មុនគេ (អោយ `Active`)។
2. កំណត់ `Org Position Matrix` អោយសមនឹង unit type និងតំណែង។
3. បង្កើត `User Assignments` អោយអ្នកទទួលខុសត្រូវជាក់ស្តែង។
4. កំណត់ `Org Role Permission Matrix` សម្រាប់ action ចាំបាច់។
5. បង្កើត `Workflow Policy Matrix` ហើយចុច `Preview Resolution` មុន Save។
6. សាក transaction ពិតមួយជុំ (`Create -> Review -> Approve`) មុនដាក់ប្រើទូលំទូលាយ។

## 3) ពន្យល់លម្អិតមួយផ្ទាំងម្តងៗ

### 3.1 `Responsibilities`

ប្រើសម្រាប់កំណត់ "តួនាទីអាជីវកម្ម" ដែលត្រូវយកទៅប្រើនៅផ្ទាំងផ្សេងៗទៀត។

ពេលបង្កើត/កែ ត្រូវពិនិត្យ:

1. `Code`:
   គួរតែមើលងាយ និងថេរ (ឧ. `head`, `deputy_head`, `manager`)។ កុំប្តូរ Code ញឹកញាប់។
2. `Name (EN/KM)`:
   ឈ្មោះដែលអ្នកប្រើឃើញលើ UI។
3. `Can Approve`:
   បង្ហាញថាតួនាទីនេះមានសិទ្ធិអនុម័តជាទូទៅឬអត់។
4. `Status`:
   ត្រូវជា `Active` ប្រសិនបើចង់ឲ្យប្រើបានក្នុង Assignment/Workflow។

ចំណាំ: តួនាទី `is_system = true` មិនអាចលុបបាន។

### 3.2 `Org Position Matrix`

ផ្ទាំងនេះកំណត់ក្បួនភ្ជាប់ `Org Unit Type` និង `Position`។

field សំខាន់:

1. `Org Unit Type`
2. `Position`
3. `Rank`
4. `Leadership`
5. `Approval`
6. `Status`

គន្លឹះអនុវត្ត:

1. ប្រសិនបើតំណែងអាចអនុម័ត ត្រូវដាក់ `Approval = Yes`។
2. ប្រសិនបើជាតំណែងដឹកនាំ ត្រូវដាក់ `Leadership = Yes`។
3. កុំដាក់ Position មិនពាក់ព័ន្ធជាមួយ unit type នោះ។

### 3.3 `User Assignments`

នេះជាប្រភពសំខាន់បំផុតសម្រាប់ការរកអ្នកអនុម័ត។

field សំខាន់:

1. `User`
2. `Org Unit`
3. `Position` (optional)
4. `Responsibility` (required)
5. `Scope` (required)
6. `Primary assignment`
7. `Effective from` / `Effective to`
8. `Status`
9. `Note`

business rule សំខាន់:

1. user ម្នាក់អាចមាន assignment ច្រើនបាន។
2. ប៉ុន្តែ `Primary + Active` មិនគួរស្ទួនគ្នាក្នុងពេលវេលាដូចគ្នា។
3. `Effective from/to` មានឥទ្ធិពលផ្ទាល់លើការមើលឃើញប៊ូតុងអនុម័ត។

### 3.4 `Org Role Permission Matrix`

ផ្ទាំងនេះសម្រាប់អនុញ្ញាត action តាម responsibility។

field សំខាន់:

1. `Module`
2. `Action`
3. `Role/Responsibility`
4. `Status`
5. `Note`

គន្លឹះ:

1. បើគ្មាន permission ទោះមាន assignment ក៏មិនអាចអនុវត្ត action បាន។
2. បើបិទ `Status` = inactive action នោះនឹងមិនអនុវត្តបាន។

### 3.5 `Workflow Policy Matrix`

ផ្ទាំងនេះសរសេរលំហូរអនុម័តពិតៗ។

header field សំខាន់:

1. `Module`
2. `Request Type`
3. `Policy Name`
4. `Priority`
5. `Status`
6. `Condition JSON` (optional)

step field សំខាន់:

1. `Step Order`
2. `Step Name`
3. `Action Type` (`review`, `recommend`, `approve`)
4. `Actor Type` (`specific_user`, `position`, `responsibility`, `spatie_role`)
5. `Actor Target`
6. `Scope`
7. `Final Approval?`
8. `Required?`
9. `Can Return?`
10. `Can Reject?`

គន្លឹះ:

1. ត្រូវមានយ៉ាងហោចណាស់ 1 step ដែល `Final Approval = Yes`។
2. ប្រើ `Preview Resolution` រាល់ពេលកែ actor ឬ scope។

### 3.6 `Legacy Org Roles`

ប្រើសម្រាប់ backward compatibility ប៉ុណ្ណោះ។

ណែនាំ:

1. កែទិន្នន័យថ្មីនៅ `User Assignments` ជាចម្បង។
2. ប្រើ Legacy សម្រាប់ពិនិត្យ និងធៀប sync ប៉ុណ្ណោះ។

## 4) ឧទាហរណ៍អនុវត្តពេញមួយករណី

គោលបំណង: រៀបចំ flow អនុម័តសំណើ Leave សម្រាប់អង្គភាពថ្មីមួយ។

1. បង្កើត/ពិនិត្យ `Responsibilities`:
   ត្រូវមាន `manager`, `deputy_head`, `head` ហើយស្ថានភាព `Active`។
2. កំណត់ `Org Position Matrix`:
   ផ្ទៀងផ្ទាត់តំណែងដឹកនាំនិងតំណែងអាចអនុម័ត។
3. បង្កើត `User Assignments`:
   ភ្ជាប់មនុស្សពិតទៅតួនាទីទាំង 3 និងកំណត់ `scope` ត្រឹមត្រូវ។
4. កំណត់ `Org Role Permission Matrix`:
   សម្រាប់ module `leave` ត្រូវមាន action `review`, `recommend`, `approve`។
5. បង្កើត `Workflow Policy Matrix`:
   step 1 = manager review, step 2 = deputy recommend, step 3 = head approve។
6. ចុច `Preview Resolution`:
   បញ្ចូល `department_id` ដូចករណីពិត ដើម្បីពិនិត្យ candidate។
7. សាកប្រើពិត:
   បង្កើតសំណើ Leave test មួយ ហើយពិនិត្យថាលំដាប់អនុម័តរត់ត្រឹមត្រូវ។

## 5) ករណីការងារពិបាក និងរបៀបដោះស្រាយ

### ករណី A: ប្តូរប្រធានអង្គភាព (handover)

1. Assignment ចាស់ដាក់ `Effective To` = ថ្ងៃមុនចូលកាន់តំណែងថ្មី។
2. Assignment ថ្មីដាក់ `Active = Yes`, `Primary = Yes`, `Effective From` ច្បាស់។
3. ពិនិត្យ `Org Role Permission Matrix` អោយមាន action គ្រប់។
4. សាក `Preview Resolution` នៅ `Workflow Policy Matrix` ជាមួយ `department_id` ពាក់ព័ន្ធ។

### ករណី B: តែងតាំងជំនួសបណ្តោះអាសន្ន (acting)

1. បង្កើត assignment ថ្មី និងកំណត់ `Effective From/To` ច្បាស់។
2. ប្រើ `Primary = No` ប្រសិនបើមិនចង់ប្តូរ primary ចម្បង។
3. កំណត់ `Scope` អោយតូចត្រឹមត្រូវ (ឧ. `self_only`)។
4. ពេលបញ្ចប់បេសកកម្ម ត្រូវបិទ `Active` ឬកំណត់ `Effective To`។

### ករណី C: មនុស្សម្នាក់គ្រប់គ្រងច្រើនអង្គភាព

1. អាចបង្កើត assignment ច្រើនបាន។
2. ត្រូវបែងចែក `Scope` តាមកាតព្វកិច្ចនីមួយៗ។
3. បំពេញ `Note` ដើម្បីងាយពិនិត្យ audit នៅពេលក្រោយ។

### ករណី D: មិនឃើញប៊ូតុងអនុម័ត

ពិនិត្យលំដាប់នេះ:

1. `Org Role Permission Matrix`: មាន module/action ត្រូវគ្នា និង `Active = Yes`?
2. `User Assignments`: `Active` និងនៅក្នុង Effective date?
3. `Workflow Policy Matrix`: មាន policy/step សម្រាប់ request type នេះ?
4. `Actor Type` និង `Actor Target`: ត្រូវនឹងអ្នកអនុម័តពិតទេ?
5. `Scope`: គ្របដណ្តប់លើអង្គភាពប្រភព (`department_id`) ទេ?

### ករណី E: Workflow រើសអ្នកអនុម័តខុស

1. ចូល `Workflow Policy Matrix` -> `Preview Resolution`។
2. សាកជាមួយ `employee_id` ឬ `department_id` ពិតៗ។
3. ពិនិត្យ `actor_type` និង target field ត្រឹមត្រូវ។
4. ពិនិត្យ assignment របស់អ្នកអនុម័ត (`Active`, `Effective`, `Scope`)។
5. បើមាន Legacy mismatch ត្រូវកែពី Canonical Assignment មុន។

## 6) យល់ឲ្យច្បាស់អំពី `Scope`

1. `self_only`:
   អនុវត្តតែអង្គភាពដែលបានកំណត់លើ assignment។
2. `self_unit_only`:
   អនុវត្តលើអង្គភាពកម្រិតដូចគ្នា (unit type ដូចគ្នា ក្រោម parent ដូចគ្នា)។
3. `self_and_children`:
   អនុវត្តលើអង្គភាពខ្លួន និងអង្គភាពកូន។
4. `all`:
   អនុវត្តគ្រប់អង្គភាពទាំងប្រព័ន្ធ (ប្រើតែពេលចាំបាច់)។

## 7) Checklist មុនដាក់ប្រើពិត (Go-Live)

1. `Responsibilities` សំខាន់ៗមានគ្រប់ និង `Active`។
2. Assignment អ្នកអនុម័តសំខាន់ៗមិនផុត Effective date។
3. មិនមាន `Primary + Active` ស្ទួនពេលវេលាសម្រាប់ user ដូចគ្នា។
4. `Org Role Permission Matrix` មាន action សម្រាប់ module ប្រើពិត។
5. `Workflow Policy Matrix` មានយ៉ាងហោចណាស់ 1 `final approval` step។
6. សាក flow ពិតយ៉ាងហោចណាស់ 1 ករណីក្នុង module នីមួយៗ (Leave/Notice/Correspondence)។
7. ក្រុមការងារពាក់ព័ន្ធសាកល្បងជាមួយ account ពិតមុន go-live។

## 8) Do / Don't

Do:

1. សរសេរ `Note` ពេលកំណត់ assignment សំខាន់ៗ។
2. កំណត់ `Effective From/To` រាល់ការតែងតាំងបណ្តោះអាសន្ន។
3. ប្រើ `Preview Resolution` រាល់ពេលកែ workflow។
4. រក្សាឈ្មោះ `Code` ក្នុង Responsibilities អោយថេរ។

Don't:

1. កុំកែ Legacy ជាចម្បងសម្រាប់ទិន្នន័យថ្មី។
2. កុំប្រើ `Scope = all` បើមិនមានហេតុផលច្បាស់។
3. កុំ Save workflow មុនពិនិត្យ actor target និង permission។
4. កុំបិទ assignment ចាស់ដោយមិនកំណត់ថ្ងៃសុពលភាព។

---

បើអនុវត្តតាមលំដាប់ខាងលើ អ្នកនឹងកាត់បន្ថយបញ្ហា “មិនឃើញប៊ូតុងអនុម័ត”, “workflow ទៅអ្នកខុស”, និង “សិទ្ធិស្ទួន/លើសចាំបាច់” បានយ៉ាងច្បាស់។

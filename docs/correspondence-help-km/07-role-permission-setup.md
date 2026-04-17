# Role / Permission / Org Scope Setup — សម្រាប់ Admin

ឯកសារនេះសម្រាប់កំណត់សិទ្ធិឲ្យច្បាស់ថា **អ្នកណាអាចធ្វើអ្វី**, **នៅអង្គភាពណា**, និង **នៅជំហានណា** ដើម្បីការងារមិនជាន់គ្នា។

---

## ១) ស្រទាប់សិទ្ធិ (ត្រូវយល់ជាមុន)

ប្រព័ន្ធមាន 3 ស្រទាប់សំខាន់៖

1. **System Role**: តួនាទីទូទៅ (Admin, Officer, Employee...)
2. **Org Role**: តួនាទីតាមអង្គភាព (Head, Deputy, Manager, Officer)
3. **Workflow Policy**: ជំហានណាអាចអនុម័ត/ផ្តល់មតិបាន

> បើខ្វះស្រទាប់ណាមួយ អ្នកប្រើអាចមើលទំព័រ បាន តែគ្មានប៊ូតុង action។

---

## ២) Permission ស្នូលក្នុង Correspondence

- `read_correspondence_management` = មើលបញ្ជី/លម្អិត
- `create_correspondence_management` = បង្កើតលិខិតចូល/ចេញ
- `update_correspondence_management` = ចាត់តាំង, ចំណារ, អនុម័ត, បិទ

### គោលការណ៍ណែនាំ

- កុំផ្តល់ `update` ទូលំទូលាយទៅគណនីទូទៅ
- ផ្តល់តាមតួនាទីការងារពិត + org scope

---

## ៣) Org Role Management — របៀបកំណត់

វាលសំខាន់នៅពេល Add Org Role Assignment:

- **User**: អ្នកប្រើ
- **Org Unit**: អង្គភាពដែលសិទ្ធិនេះប្រើបាន
- **Role**: Head/Deputy/Manager/Officer
- **Scope**: Self ឬ Self And Children
- **Effective from/to**: រយៈពេលសុពលភាព
- **Active**: សកម្ម/អសកម្ម

### ការណែនាំ Scope

- **Self**: មើល/ធ្វើការងារតែអង្គភាពខ្លួន
- **Self And Children**: អាចគ្រប់គ្រងអង្គភាពរងក្រោមឱវាទ

---

## ៤) Workflow Policy Matrix — ត្រូវកំណត់ដូចម្តេច?

កំណត់ថា action មួយៗត្រូវឆ្លងតួនាទីណា៖

- Incoming: Clerk -> Office -> Deputy -> Director
- Outgoing: Draft -> Send -> Recipient acknowledged -> Completed

បើអង្គភាពចង់ផ្ទេរសិទ្ធិបណ្តោះអាសន្ន (acting) ត្រូវកំណត់ Effective date ឲ្យច្បាស់។

---

## ៥) គំរូសិទ្ធិប្រើប្រាស់ (Recommended Template)

### A. អ្នកទទួលលិខិត
- read + create
- អាចបង្កើតលិខិត
- មិនអាចអនុម័តចុងក្រោយ

### B. អ្នកគ្រប់គ្រងលិខិត
- read + create + update
- អាចចាត់តាំង/បែងចែក/តាមដាន

### C. អង្គភាពជំនាញ
- read + update (feedback scope)
- អាចចំណារ/មតិយោបល់តែការងារដែលបានចាត់តាំង

### D. អនុប្រធានមន្ទីរ
- read + update
- អាចពិនិត្យ និងផ្តល់គំនិត

### E. ប្រធានមន្ទីរ
- read + update
- អាចអនុម័ត/បដិសេធចុងក្រោយ

### F. Super Admin
- គ្រប់សិទ្ធិ
- កំណត់ matrix និងគ្រប់គ្រងសុវត្ថិភាព

---

## ៦) ករណីផ្ទេរសិទ្ធិ (Delegation)

ពេលប្រធាន/អនុប្រធានអវត្តមាន៖

1. បង្កើត Org role ថ្មីឲ្យ Acting user
2. កំណត់ Effective from/to ច្បាស់
3. បិទ Active ក្រោយម្ចាស់សិទ្ធិត្រឡប់

> កុំផ្លាស់ប្តូរគណនីដើមដោយផ្ទាល់ ប្រសិនអាចប្រើ delegation បាន។

---

## ៧) បញ្ជីត្រួតពិនិត្យមុនបើកប្រើផ្លូវការ

- [ ] Role ក្នុង User Management ត្រឹមត្រូវ
- [ ] Permission ត្រឹមត្រូវតាមតួនាទី
- [ ] Org Role មាន Org Unit + Scope + Effective date
- [ ] Workflow policy មានគ្រប់ជំហាន
- [ ] តេស្ត 3 scenario: incoming approve / incoming reject / outgoing send-complete

---

## ៨) បញ្ហាដែលជួបញឹកញាប់ពេល Setup

### មើលទំព័របាន តែចុច action មិនបាន
- ខ្វះ `update` permission ឬ Org role មិន active

### ចូលបាន តែមិនឃើញលិខិតអង្គភាពខ្លួន
- Scope មិនត្រឹមត្រូវ (Self vs Self And Children)

### អ្នកម្នាក់មានសិទ្ធិស្ទួនច្រើនពេក
- ត្រូវទុក role គោលមួយ + org role តាមរយៈ effective date

---

## ៩) គោលការណ៍សុវត្ថិភាព

- Least privilege: ផ្តល់សិទ្ធិតែចាំបាច់
- Time-bound delegation: ផ្ទេរសិទ្ធិមានកាលកំណត់
- Audit-first: គ្រប់ action ត្រូវមានអ្នកធ្វើ/ពេលវេលា/មូលហេតុ
- មិនប្រើគណនីរួម (shared account)

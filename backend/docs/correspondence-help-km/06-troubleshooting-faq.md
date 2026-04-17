# Troubleshooting & FAQ — បញ្ហាញឹកញាប់

ឯកសារនេះរៀបចំសម្រាប់ដោះស្រាយបញ្ហាដែលជួបញឹកញាប់នៅម៉ូឌុលលិខិតចូល/ចេញ។

---

## ១) 419 Page Expired ពេល Login ឬ Save

### រោគសញ្ញា
- បើក `/login` ឬ submit form ហើយបង្ហាញ `419 Page Expired`

### មូលហេតុ
- Session/CSRF token ផុតកំណត់
- Browser មាន cache/cookie ចាស់
- APP_URL/SESSION_DOMAIN មិនត្រូវ environment

### ដំណោះស្រាយលឿន
1. Refresh ទំព័រ ហើយ login វិញ
2. Logout -> Login ម្តងទៀត
3. បិទ/បើក browser ថ្មី
4. ជា Admin: clear cache config/route/view

---

## ២) 500 Internal Server Error

### រោគសញ្ញា
- ទំព័រមួយចូលមិនបាន ឬចុចប៊ូតុងហើយ 500

### មូលហេតុអាចមាន
- SQL error / migration ខ្វះ
- route/controller/view ខូច
- permission file/storage មិនត្រឹមត្រូវ

### ដំណោះស្រាយ
- ពិនិត្យ `storage/logs/laravel.log`
- ពិនិត្យ table/column ត្រូវនឹង code
- ពិនិត្យ .env DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD

---

## ៣) 404 Not Found ពេលចុច Notification

### មូលហេតុ
- link notification ចាស់
- record ត្រូវបានលុប ឬ user មិនមានសិទ្ធិ

### ដំណោះស្រាយ
- ចូលពីបញ្ជី Incoming/Outgoing ដោយស្វែងរកលេខលិខិត
- ពិនិត្យ Org Role និង scope របស់អ្នកប្រើ

---

## ៤) មិនឃើញប៊ូតុង Action (Assign/Approve/Send)

### មូលហេតុ
- មិនស្ថិតនៅជំហាន workflow បច្ចុប្បន្ន
- គ្មាន permission ឬ org role មិន active
- លិខិតស្ថិតក្នុង status មិនអនុញ្ញាត action នោះ

### ដំណោះស្រាយ
- ពិនិត្យ Action history និង Current status
- ពិនិត្យ `Org Role Management` (effective date + active)
- ពិនិត្យ permission matrix

---

## ៥) Attachment ចុច View ហើយទៅ Download

### មូលហេតុ
- browser មិនគាំទ្រ inline preview សម្រាប់ file type នោះ
- server response បញ្ជូនជា attachment

### ដំណោះស្រាយ
- សាក Chrome/Edge
- បើជាប្រភេទ office file ណែនាំ preview តាម browser tab
- បើមិនគាំទ្រ សូមប្រើ Download

---

## ៦) Attachment preview បង្ហាញតូចពេក

### ដំណោះស្រាយ UI
- បើក preview នៅ browser tab ថ្មី (full width)
- កំណត់ browser zoom = 100%
- ប្រើម៉ូនីទ័រទូលាយ/Full screen ពេលពិនិត្យឯកសារធំ

---

## ៧) Print layout មិនត្រឹមត្រូវ

### រោគសញ្ញា
- អត្ថបទបាក់បន្ទាត់ខុស
- logo ឬប្រអប់ចំណារមិននៅទីតាំង

### ដំណោះស្រាយ
- Print setting: A4, Portrait, Scale 100%
- បិទ Header/Footer របស់ browser
- Preview ម្តងមុនបោះពុម្ព

---

## ៨) ថ្ងៃខែឆ្នាំបង្ហាញខុស format

### ដំណោះស្រាយ
- ប្រើ format ថ្ងៃខែឆ្នាំឯកភាពតាម UI (`DD/MM/YYYY` ឬ `YYYY-MM-DD` តាម field)
- កុំបញ្ចូលថ្ងៃខុសស្តង់ដារ (ឧ. ខែ > 12)
- ពិនិត្យ locale/datepicker configuration

---

## ៩) អក្សរខ្មែរខូច (mojibake)

### មូលហេតុ
- encoding មិនជា UTF-8
- font មិនគាំទ្រខ្មែរ

### ដំណោះស្រាយ
- ឯកសារ source/translation រក្សា UTF-8
- ប្រើ font ខ្មែរ​ដែលគាំទ្រ Unicode
- ពិនិត្យ DB collation (`utf8mb4`)

---

## ១០) FAQ លឿន

### សំណួរ: To និង CC ខុសគ្នាដូចម្តេច?
- To = អ្នកទទួលសំខាន់
- CC = ជូនចម្លង

### សំណួរ: លិខិតចូលបញ្ចប់ពេលណា?
- ពេលប្រធានមន្ទីរសម្រេចចុងក្រោយ

### សំណួរ: លិខិតចេញបញ្ចប់ពេលណា?
- ពេលអ្នកទទួលពាក់ព័ន្ធទទួលបានតាមលក្ខខណ្ឌ

### សំណួរ: ហេតុអ្វីខ្ញុំឃើញតែខ្លះៗមិនឃើញលិខិតទាំងអស់?
- ព្រោះសិទ្ធិត្រូវបានកំណត់តាម Org scope និង workflow step

# 02) SOP សម្រាប់ HR/Admin (Web Flow)

## ម៉ឺនុយសំខាន់

- Workflow
- Attendance Form
- Monthly Attendance
- Missing Attendance
- Attendance Exceptions
- QR Attendance

## 1. Workflow (ត្រួតពិនិត្យប្រចាំថ្ងៃ)

1. បើកផ្ទាំង `Workflow`
2. ពិនិត្យ KPI:
   - Today attendance
   - Today missing
   - Today exceptions
3. ពិនិត្យ Device:
   - Pending/Active/Blocked
   - Online/Offline

## 2. Attendance Form (Manual)

ប្រើនៅពេលត្រូវបញ្ចូលវត្តមានដៃ (ករណីពិសេស):

1. ជ្រើស `Employee`
2. បញ្ចូល `Datetime`
3. Submit

## 3. Monthly Attendance

ប្រើនៅពេលបញ្ចូលកែសម្រួលជាខែ:

1. ជ្រើស Employee + Year + Month
2. បញ្ចូល Time In/Out
3. Submit

## 4. Missing Attendance

ប្រើសម្រាប់បុគ្គលិកអវត្តមាន ឬខ្វះ punch:

1. ស្វែងរកតាម Date
2. ជ្រើសបុគ្គលិកត្រូវកែ
3. បញ្ចូល In Time / Out Time
4. Submit

## 5. Attendance Exceptions

ប្រើត្រួតពិនិត្យករណីខុស (ឧ. `UNPAIRED_PUNCH`):

1. ជ្រើស Date
2. ពិនិត្យ Punch count + Reason
3. ចូល Details របស់បុគ្គលិក
4. អនុវត្តការកែតាម SOP `04-exceptions-and-corrections.md`

## 6. QR Attendance (Generate)

1. ជ្រើស `Workplace/Org unit`
2. កំណត់ `Expiry minutes`
3. ចុច Generate QR
4. ផ្តល់ QR ថ្មីទៅអង្គភាព


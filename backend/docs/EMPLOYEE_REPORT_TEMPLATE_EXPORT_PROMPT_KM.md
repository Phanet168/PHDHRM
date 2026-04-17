# Prompt: Employee Report Template Export (Lock Format)

Use this prompt when modifying Employee Report Template exports. Follow exactly and do not change any format without explicit user approval.

## Scope
- Report module: Employee Report Template
- Outputs: PDF, Excel, CSV
- Priority: Keep Khmer rendering and official letterhead/footer style stable

## Files and Responsibilities
- PDF layout/view: modules/HumanResource/Resources/views/reports/employee-report-template-pdf.blade.php
- Excel layout/export class: modules/HumanResource/Exports/EmployeeTemplateReportExport.php
- Export logic/controller (CSV/Excel/PDF + Khmer value mapping + headless fallback): modules/HumanResource/Http/Controllers/EmployeeReportTemplateController.php

## Hard Rules (Do Not Break)
1. PDF Khmer rendering
- Keep @font-face loading from storage/fonts.
- Keep Khmer OS Siemreap for body and Khmer M1 for heading lines.

2. PDF header structure
- Left block: logo on top, then admin/unit text.
- Center block: kingdom lines and diamonds.
- Left text stays left-aligned.
- Current logo display: 64x64, left shift -3cm.

3. PDF table/header style
- Header text not bold (font-weight 400).
- Border/padding/line-height unchanged unless user requests.

4. PDF signature/footer structure
- Right side: lunar date, solar date, HR manager.
- Left side: ឯកភាព, then ប្រធានមន្ទីរសុខាភិបាល.

5. Excel official format
- Drawing logo size: 96x96.
- Keep merged and alignment zones already configured (including B:D and right footer merge).
- Keep repeat table header row at top when printing.

6. CSV Khmer support
- Must include UTF-8 BOM before fputcsv.

7. PDF engine strategy
- Try Chrome/Edge headless first for Khmer shaping.
- If failed, auto fallback to DomPDF.
- Browser path order:
  - env(PDF_BROWSER_PATH)
  - auto-detect Chrome/Edge known paths + command names.

8. Khmer value mapping in report data
- Gender should render Khmer (ប្រុស/ស្រី).
- Employee grade should render Khmer code style.
- Column label for employee_grade must be: ឋានន្តរស័ក្តិ និងថ្នាក់.

## Verification Checklist (Required Before Finish)
1. Run:
- php -l modules/HumanResource/Http/Controllers/EmployeeReportTemplateController.php
- php -l modules/HumanResource/Exports/EmployeeTemplateReportExport.php
2. Clear cache:
- php artisan view:clear
- php artisan optimize:clear
3. Smoke test:
- PDF export opens with Khmer readable text.
- CSV opens in Excel with Khmer readable text.
- Excel export preserves letterhead/footer formatting.

## Ready-to-Use Prompt (Copy/Paste)
"""
Please modify Employee Report Template exports with strict format lock.

Rules:
- Only touch these files:
  1) modules/HumanResource/Resources/views/reports/employee-report-template-pdf.blade.php
  2) modules/HumanResource/Exports/EmployeeTemplateReportExport.php
  3) modules/HumanResource/Http/Controllers/EmployeeReportTemplateController.php
- Keep Khmer rendering support and official letterhead/footer style.
- Do not change spacing, margins, logo offsets, merge zones, font weights, or label text unless I explicitly request.
- Keep CSV UTF-8 BOM.
- Keep PDF headless Chrome/Edge first, then DomPDF fallback, and env PDF_BROWSER_PATH support.
- Keep Khmer value mapping for gender and employee grade.

After changes:
- Run php -l checks for edited PHP files.
- Run php artisan view:clear and php artisan optimize:clear.
- Report exact changed lines and why.
"""

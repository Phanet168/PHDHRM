# Khmer Localization Style Guide (Technical Terms)

## Goals
- Keep UI text clear, concise, and consistent.
- Prefer Khmer as primary wording; keep technical acronyms when needed.
- Preserve placeholders and code-like tokens exactly.

## Core Rules
- Use Khmer-first wording for labels and actions.
- Keep standard acronyms in uppercase: QR, JSON, SMS, HRM, CSV, PDF.
- For export labels, use: "នាំចេញជា <FORMAT>".
- For file type lists, keep extensions uppercase and comma-separated.
- Keep placeholders unchanged: _START_, _END_, _TOTAL_, _MAX_, :months.
- Keep code examples unchanged: active, leave_without_pay, incoming_letter.

## Recommended Patterns
- "Role" -> "តួនាទី"
- "Scope" -> "វិសាលភាព"
- "Workflow" -> "លំហូរការងារ"
- "Dashboard" -> "ផ្ទាំងគ្រប់គ្រង"
- "Report" -> "របាយការណ៍"
- "Setting" -> "ការកំណត់"

## Do and Don't
- Do: "កំណត់សារ SMS"
- Do: "លក្ខខណ្ឌ (ទម្រង់ JSON, មិនបង្ខំ)"
- Do: "នាំចេញជា CSV"
- Don't: Mix full English UI labels when Khmer equivalent is available.
- Don't: Change variable-like keys or slug examples.

## Review Checklist
- Is the phrase understandable for Khmer-speaking end users?
- Is technical meaning preserved?
- Are acronym casing and placeholders preserved?
- Does wording match existing keys in language.php style?

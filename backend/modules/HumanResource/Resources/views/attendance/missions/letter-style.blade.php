{{-- Shared A4 styling for the mission order and travel-visa letters. --}}
{{-- A letter is paper: keep phone browsers from auto-darkening it. --}}
<meta name="color-scheme" content="only light">
<style>
    /* The fonts of official government letters, served with the app so phones without them render the same. */
    @font-face { font-family: 'Khmer OS Siemreap'; src: local('Khmer OS Siemreap'), url('{{ asset('fonts/khmer-os/KhmerOSsiemreap.ttf') }}') format('truetype'); font-display: swap; }
    @font-face { font-family: 'Khmer OS Muol Light'; src: local('Khmer OS Muol Light'), url('{{ asset('fonts/khmer-os/KhmerOSmuollight.ttf') }}') format('truetype'); font-display: swap; }
    @font-face { font-family: 'Tacteing'; src: local('Tacteing'), url('{{ asset('fonts/khmer-os/Tacteing.ttf') }}') format('truetype'); font-display: block; }
    :root { color-scheme: only light; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #edf0f3; color: #111; font-family: 'Khmer OS Siemreap', sans-serif; font-size: 14px; line-height: 1.85; }
    .toolbar { display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 14px; padding: 15px; }
    .toolbar button, .toolbar a { font: inherit; padding: 8px 18px; border: 1px solid #236144; border-radius: 6px; background: white; color: #236144; cursor: pointer; text-decoration: none; }
    .toolbar button { background: #236144; color: white; }
    .sheet { width: 210mm; min-height: 297mm; padding: 14mm 18mm 16mm 22mm; margin: 0 auto 24px; background: white; box-shadow: 0 4px 24px #0002; }
    .letter-head { position: relative; }
    .national { text-align: center; font-family: 'Khmer OS Muol Light', serif; font-size: 15px; color: #1f3a8a; }
    .motto { font-size: 13px; margin-top: 4px; }
    /* Tacteing maps digits to the traditional flourishes: "6" under the motto, "3" under a letter title. */
    .ornament { font-family: 'Tacteing'; text-align: center; font-size: 42px; line-height: 1; font-weight: normal; }
    .logo { display: block; width: 26mm; height: 26mm; object-fit: contain; margin-top: -2mm; }
    .issuer { margin-top: 2mm; font-family: 'Khmer OS Muol Light', serif; font-size: 13px; color: #1f3a8a; white-space: pre-wrap; }
    .number-date { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; margin-top: 2mm; }
    .date { text-align: center; max-width: 65%; }
    h1 { text-align: center; font-family: 'Khmer OS Muol Light', serif; font-size: 19px; font-weight: normal; margin: 8mm 0 1mm; }
    .reference { margin-top: 6mm; padding-left: 0; }
    .reference-line { padding-left: 16mm; white-space: pre-wrap; overflow-wrap: anywhere; }
    .reference strong + .reference-line { display: inline; padding-left: 2mm; }
    .intro { text-indent: 16mm; margin: 1mm 0 3mm; }
    .signature { margin-left: 48%; margin-top: 10mm; text-align: center; break-inside: avoid; }
    .signature-title { font-family: 'Khmer OS Muol Light', serif; font-size: 14px; }
    .signature-space { height: 26mm; }
    .draft { border: 1px solid #999; padding: 3px 12px; text-align: center; margin-top: 5mm; font-weight: 700; }
    tr { break-inside: avoid; }
    @page { size: A4; margin: 14mm 18mm 16mm 22mm; }
    @media print {
        body { background: white; font-size: 12pt; }
        .toolbar { display: none; }
        .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        .sheet + .sheet { break-before: page; }
        h1 { break-after: avoid; }
    }
    @media screen and (max-width: 800px) { .sheet { width: 100%; min-height: 0; padding: 20px 16px; } body { font-size: 13px; } }
    /* Our "dark theme" is still white paper; declaring one stops Samsung Internet force-darkening the letter. */
    @media (prefers-color-scheme: dark) {
        body { background: #edf0f3; color: #111; }
        .sheet { background: #fff; color: #111; }
    }
</style>

<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>លិខិតបញ្ជាបេសកកម្ម — {{ $mission->order_number ?: $mission->title }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;600;700&family=Moul&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #edf0f3; color: #111; font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', sans-serif; font-size: 14px; line-height: 1.85; }
        .toolbar { display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 14px; padding: 15px; }
        .toolbar button, .toolbar a { font: inherit; padding: 8px 18px; border: 1px solid #236144; border-radius: 6px; background: white; color: #236144; cursor: pointer; text-decoration: none; }
        .toolbar button { background: #236144; color: white; }
        .sheet { width: 210mm; min-height: 297mm; padding: 16mm 18mm 18mm 22mm; margin: 0 auto 24px; background: white; box-shadow: 0 4px 24px #0002; }
        .national { text-align: center; margin-left: 40%; font-family: 'Moul', 'Khmer OS Muol Light', serif; font-size: 15px; }
        .motto { font-size: 13px; margin-top: 4px; }
        .ornament { text-align: center; font-size: 18px; line-height: 1.2; }
        .issuer { margin-top: 12mm; font-family: 'Moul', 'Khmer OS Muol Light', serif; font-size: 13px; white-space: pre-wrap; }
        .number-date { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; margin-top: 5mm; }
        .date { text-align: center; max-width: 65%; }
        h1 { text-align: center; font-family: 'Moul', 'Khmer OS Muol Light', serif; font-size: 19px; font-weight: normal; margin: 15mm 0 2mm; }
        .reference { margin-top: 8mm; white-space: pre-wrap; }
        .intro { text-indent: 10mm; margin: 2mm 0 5mm; }
        .officers { border-collapse: collapse; width: 100%; margin: 4mm 0 7mm; border-top: 1px solid black; border-bottom: 1px solid black; }
        .officers td { padding: 3mm 2mm; vertical-align: top; overflow-wrap: anywhere; }
        .officers .num { width: 8%; text-align: center; }
        .officers .name { width: 38%; font-weight: 700; }
        .total { text-align: center; font-weight: 700; margin: 6mm 0; }
        .facts { border-collapse: collapse; width: 100%; }
        .facts th { width: 32%; text-align: left; vertical-align: top; padding: 3mm 3mm 3mm 0; font-weight: 700; }
        .facts td { border-bottom: 1px solid #555; padding: 3mm 0; vertical-align: top; white-space: pre-wrap; overflow-wrap: anywhere; }
        .signature { margin-left: 48%; margin-top: 14mm; text-align: center; break-inside: avoid; }
        .signature-title { font-family: 'Moul', 'Khmer OS Muol Light', serif; font-size: 14px; }
        .signature-space { height: 30mm; }
        .draft { border: 1px solid #999; padding: 3px 12px; text-align: center; margin-top: 7mm; font-weight: 700; }
        tr { break-inside: avoid; }
        @page { size: A4; margin: 16mm 18mm 18mm 22mm; }
        @media print {
            body { background: white; font-size: 12pt; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            h1 { break-after: avoid; }
        }
        @media screen and (max-width: 800px) { .sheet { width: 100%; padding: 24px; } .national { margin-left: 20%; } }
    </style>
</head>
<body>
@php
    $digits = fn ($value) => strtr((string) $value, ['0'=>'០','1'=>'១','2'=>'២','3'=>'៣','4'=>'៤','5'=>'៥','6'=>'៦','7'=>'៧','8'=>'៨','9'=>'៩']);
    $months = ['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
    $dateLabel = function ($value) use ($digits, $months) {
        if (!$value) return 'ថ្ងៃទី........ ខែ................ ឆ្នាំ........';
        $date = \Carbon\Carbon::parse($value);
        return 'ថ្ងៃទី '.$digits($date->day).' ខែ'.$months[$date->month - 1].' ឆ្នាំ'.$digits($date->year);
    };
    $blank = '........................................................';
    $draftLabels = ['draft'=>'សេចក្ដីព្រាង — មិនទាន់អនុម័ត', 'pending'=>'សេចក្ដីព្រាង — រង់ចាំអនុម័ត', 'rejected'=>'បានបដិសេធ', 'cancelled'=>'បានបោះបង់'];
@endphp
<nav class="toolbar">
    <a href="{{ route('missions.show', $mission->id) }}">ត្រឡប់ទៅបេសកកម្ម</a>
    <button type="button" onclick="window.print()">បោះពុម្ព / រក្សាទុកជា PDF</button>
    <span>A4 · សូមបិទ Headers/Footers ក្នុងផ្ទាំងបោះពុម្ព</span>
</nav>
<main class="sheet">
    <header>
        <div class="national">ព្រះរាជាណាចក្រកម្ពុជា<div class="motto">ជាតិ សាសនា ព្រះមហាក្សត្រ</div><div class="ornament">❖</div></div>
        <div class="issuer">{{ $details['issuing_authority'] ?? $blank }}<br>{{ $details['issuing_department'] ?? $blank }}</div>
        <div class="number-date"><div>លេខ៖ {{ $mission->order_number ?: $blank }}</div><div class="date">
            @if(!empty($details['lunar_date']))<div>{{ $details['lunar_date'] }}</div>@endif
            {{ $details['issue_place'] ?? '................' }}, {{ $dateLabel($details['issued_on'] ?? null) }}
        </div></div>
        <h1>លិខិតបញ្ជាបេសកកម្ម</h1>
        <div class="ornament">❖</div>
        @if($mission->status !== 'approved')<div class="draft">{{ $draftLabels[$mission->status] ?? $mission->status }}</div>@endif
    </header>
    <div class="reference">- យោង៖ {{ $details['reference'] ?? $blank }}</div>
    <p class="intro">ចាត់តាំងមន្ត្រីដែលមានរាយនាមខាងក្រោម ឱ្យចេញទៅបំពេញបេសកកម្មនៅ៖ <strong>{{ $mission->destination }}</strong></p>
    <table class="officers" aria-label="បញ្ជីមន្ត្រីចេញបំពេញបេសកកម្ម"><tbody>
        @foreach($mission->assignments as $assignment)
            <tr><td class="num">{{ $digits($loop->iteration) }}-</td>
                <td class="name">{{ $assignment->name_on_order ?? $assignment->employee?->full_name ?? $blank }}</td>
                <td>{{ $assignment->position_on_order ?? ($assignment->employee?->position?->position_name_km ?: $assignment->employee?->position?->position_name) ?? $blank }}</td></tr>
        @endforeach
    </tbody></table>
    <div class="total">សរុបចំនួន៖ {{ $digits(str_pad((string) $mission->assignments->count(), 2, '0', STR_PAD_LEFT)) }} នាក់</div>
    <table class="facts"><tbody>
        <tr><th>មូលហេតុ</th><td>៖ {{ $mission->purpose ?: $mission->title }}</td></tr>
        <tr><th>ចេញដំណើរ</th><td>៖ {{ $dateLabel($mission->start_date) }}</td></tr>
        <tr><th>ត្រឡប់មកវិញ</th><td>៖ {{ $dateLabel($mission->end_date) }}</td></tr>
        <tr><th>មធ្យោបាយធ្វើដំណើរ</th><td>៖ {{ $details['transport'] ?? $blank }}</td></tr>
        <tr><th>ប្រភពថវិកា</th><td>៖ {{ $details['funding_source'] ?? $blank }}</td></tr>
    </tbody></table>
    <section class="signature">
        <div class="signature-title">{{ $details['signatory_title'] ?? $blank }}</div>
        <div class="signature-space"></div>
        <strong>{{ $details['signatory_name'] ?? '' }}</strong>
    </section>
</main>
</body>
</html>

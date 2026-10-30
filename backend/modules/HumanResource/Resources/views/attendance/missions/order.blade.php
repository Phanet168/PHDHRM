<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>លិខិតបញ្ជាបេសកកម្ម — {{ $mission->order_number ?: $mission->title }}</title>
    @include('humanresource::attendance.missions.letter-style')
    <style>
        .officers { border-collapse: collapse; width: 88%; margin: 4mm 0 0 12%; }
        .officers td { padding: 2mm 2mm; vertical-align: top; overflow-wrap: anywhere; }
        .officers .num { width: 26%; white-space: nowrap; }
        .officers .name { width: 28%; font-weight: 700; }
        .rule { border-top: 1px solid #000; margin: 0 0 0 30%; }
        .rule.wide { margin: 18mm 0 0 15%; }
        .total { text-align: center; font-weight: 700; margin: 6mm 0 3mm; font-size: 1.05em; }
        .facts { border-collapse: collapse; width: 100%; }
        .facts th { width: 24%; text-align: left; vertical-align: top; padding: 1.6mm 2mm 1.6mm 0; font-weight: 700; white-space: nowrap; }
        .facts td { border-bottom: 1px solid #000; padding: 1.6mm 0; vertical-align: top; white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
</head>
<body>
@php
    $digits = fn ($value) => strtr((string) $value, ['0'=>'០','1'=>'១','2'=>'២','3'=>'៣','4'=>'៤','5'=>'៥','6'=>'៦','7'=>'៧','8'=>'៨','9'=>'៩']);
    $months = ['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
    $dateLabel = function ($value) use ($digits, $months, $mission) {
        if (!$value) return 'ថ្ងៃទី........ ខែ................ ឆ្នាំ'.$digits($mission->start_date?->year ?? '........');
        $date = \Carbon\Carbon::parse($value);
        return 'ថ្ងៃទី '.$digits($date->day).' ខែ'.$months[$date->month - 1].' ឆ្នាំ'.$digits($date->year);
    };
    $blank = '........................................................';
    $draftLabels = ['draft'=>'សេចក្ដីព្រាង — មិនទាន់អនុម័ត', 'pending'=>'សេចក្ដីព្រាង — រង់ចាំអនុម័ត', 'rejected'=>'បានបដិសេធ', 'cancelled'=>'បានបោះបង់'];
    $officers = $mission->assignments->where('status', 'active')->values();
    $references = $mission->referenceLines();
    $issuer = trim(($details['issuing_department'] ?? '').(!empty($details['issuing_authority']) ? 'នៃ'.$details['issuing_authority'] : ''));
    $public = $public ?? false;
@endphp
<nav class="toolbar">
    @unless($public)
        <a href="{{ route('missions.show', $mission->id) }}">ត្រឡប់ទៅបេសកកម្ម</a>
        <a href="{{ route('missions.travel-visa', $mission->id) }}" target="_blank">លិខិតចុះទិដ្ឋការធ្វើដំណើរ</a>
    @endunless
    <button type="button" onclick="window.print()">បោះពុម្ព / រក្សាទុកជា PDF</button>
    <span>A4 · សូមបិទ Headers/Footers ក្នុងផ្ទាំងបោះពុម្ព</span>
</nav>
<main class="sheet">
    @include('humanresource::attendance.missions.letter-head', ['details' => $details])
    <div class="number-date">
        <div>លេខ៖ {{ $mission->order_number ?: '..................' }} {{ $details['letter_code'] ?? '' }}</div>
        <div class="date">
            @if(!empty($details['lunar_date']))<div>{{ $details['lunar_date'] }}</div>@endif
            {{ $details['issue_place'] ?? '................' }}, {{ $dateLabel($details['issued_on'] ?? null) }}
        </div>
    </div>
    <h1>លិខិតបញ្ជាបេសកកម្ម</h1>
    <div class="ornament" aria-hidden="true">3</div>
    @if(!$mission->isOrderIssued())<div class="draft">{{ $draftLabels[$mission->status] ?? 'សេចក្ដីព្រាង — មិនទាន់ចេញលិខិត' }}</div>@endif

    <div class="reference">
        <strong>-យោង៖</strong>
        @forelse($references as $line)
            <div class="reference-line">-{{ $line }}</div>
        @empty
            <div class="reference-line">-{{ $blank }}</div>
        @endforelse
    </div>
    <p class="intro">មន្ត្រីរាជការ{{ $issuer }} ដូចមានរាយនាមខាងក្រោមនេះ ត្រូវបានចាត់តាំងឱ្យទៅបំពេញបេសកកម្មនៅ៖ <strong>{{ $mission->destination }}</strong></p>
    <div class="rule"></div>
    <table class="officers" aria-label="បញ្ជីមន្ត្រីចេញបំពេញបេសកកម្ម"><tbody>
        @foreach($officers as $assignment)
            <tr><td class="num">{{ $digits($loop->iteration) }}-{{ $assignment->honorific_on_order }}</td>
                <td class="name">{{ $assignment->name_on_order ?? $assignment->employee?->full_name ?? $blank }}</td>
                <td>{{ $assignment->position_on_order ?? ($assignment->employee?->position?->position_name_km ?: $assignment->employee?->position?->position_name) ?? $blank }}</td></tr>
        @endforeach
    </tbody></table>
    <div class="rule wide"></div>
    <div class="total">សរុបចំនួន៖ {{ $digits(str_pad((string) $officers->count(), 2, '0', STR_PAD_LEFT)) }} នាក់</div>
    <table class="facts"><tbody>
        <tr><th>មូលហេតុ</th><td>៖ {{ $mission->purpose ?: $mission->title }}</td></tr>
        <tr><th>ចេញដំណើរ</th><td>៖ {{ $dateLabel($mission->start_date) }}</td></tr>
        <tr><th>ផុតកំណត់</th><td>៖ {{ $dateLabel($mission->end_date) }}</td></tr>
        <tr><th>មធ្យោបាយធ្វើដំណើរ</th><td>៖ {{ $mission->transportLabel() ?? $blank }}</td></tr>
        <tr><th>ឧបត្ថម្ភថវិកា</th><td>៖ {{ $mission->fundingLabel() ?? $blank }}</td></tr>
    </tbody></table>
    <section class="signature">
        <div class="signature-title">{{ $details['signatory_title'] ?? $blank }}</div>
        <div class="signature-space"></div>
        <strong>{{ $details['signatory_name'] ?? '' }}</strong>
    </section>
</main>
</body>
</html>

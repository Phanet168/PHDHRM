<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>លិខិតចុះទិដ្ឋការធ្វើដំណើរ — {{ $mission->order_number ?: $mission->title }}</title>
    @include('humanresource::attendance.missions.letter-style')
    <style>
        h1 { margin-top: 2mm; }
        .lines { margin: 2mm 0 0 8mm; }
        .lines div { overflow-wrap: anywhere; }
        .date-sign { margin-left: 45%; margin-top: 2mm; text-align: center; }
        .stops { width: 100%; border-collapse: collapse; margin-top: 18mm; font-size: 12px; line-height: 1.6; table-layout: fixed; }
        .stops.continued { margin-top: 0; }
        .stops th, .stops td { border: 1px solid #000; padding: 2mm; vertical-align: top; }
        .stops th { background: #e6e6e6; font-weight: 400; text-align: center; }
        .stops td { height: 27mm; }
        .stops.continued td { height: 30mm; }
        .stops .place { width: 19%; }
        .dots { color: #333; letter-spacing: 1px; }
        .dotline { border-bottom: 1px dotted #000; height: 1.6em; }
        @media screen and (max-width: 800px) { .stops { font-size: 10px; } .stops td { height: 22mm; } }
    </style>
</head>
<body>
@php
    $digits = fn ($value) => strtr((string) $value, ['0'=>'០','1'=>'១','2'=>'២','3'=>'៣','4'=>'៤','5'=>'៥','6'=>'៦','7'=>'៧','8'=>'៨','9'=>'៩']);
    $months = ['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
    $dateLabel = function ($value) use ($digits, $months) {
        $date = \Carbon\Carbon::parse($value);
        return 'ថ្ងៃទី '.$digits($date->day).' ខែ'.$months[$date->month - 1].' ឆ្នាំ'.$digits($date->year);
    };
    $year = $digits($mission->start_date->year);
    $issuedOn = $details['issued_on'] ?? null;
    $issuedLabel = $issuedOn ? $dateLabel($issuedOn) : 'ថ្ងៃទី................ខែ..................ឆ្នាំ'.$year;
    $place = trim((string) ($details['issue_place'] ?? ''));
    // The trip starts from the issuing province ("ខេត្តស្ទឹងត្រែង").
    $origin = $place === '' ? null : (preg_match('/^(ខេត្ត|រាជធានី)/u', $place) ? $place : 'ខេត្ត'.$place);
    $blank = '........................................................';
    $public = $public ?? false;
    $placeCell = fn (string $label) => '<div>'.e($label).'</div><div class="dotline"></div><div class="dotline"></div>';
@endphp
<nav class="toolbar">
    @unless($public)
        <a href="{{ route('missions.order', $mission->id) }}">លិខិតបញ្ជាបេសកកម្ម</a>
    @endunless
    <button type="button" onclick="window.print()">បោះពុម្ព / រក្សាទុកជា PDF</button>
    <span>A4 · {{ $digits($officers->count()) }} នាក់ · ម្នាក់ ២ ទំព័រ</span>
</nav>
@foreach($officers as $assignment)
    <main class="sheet">
        @include('humanresource::attendance.missions.letter-head', ['details' => $details])
        <h1>លិខិតចុះទិដ្ឋការធ្វើដំណើរ</h1>
        <div class="lines">
            <div>ផ្ដល់ជូន៖ {{ $assignment->honorific_on_order }} <strong>{{ $assignment->name_on_order ?? $assignment->employee?->full_name }}</strong></div>
            <div>ឋានៈ និងទីកន្លែងធ្វើការ៖ {{ $assignment->position_on_order ?? $blank }}</div>
            <div>បំពេញបេសកកម្មនៅ៖ <strong>{{ $mission->destination }}</strong></div>
            <div>លិខិតបញ្ជាបេសកកម្មលេខ៖ {{ $mission->order_number ?: '..................................' }} {{ $details['letter_code'] ?? '' }} ចុះ{{ $issuedLabel }}</div>
            <div>ចាប់តាំងពី{{ $dateLabel($mission->start_date) }} ដល់{{ $dateLabel($mission->end_date) }}</div>
            <div>មធ្យោបាយធ្វើដំណើរ៖ {{ $mission->transportLabel() ?? $blank }}</div>
            <div>ឧបត្ថម្ភថវិកា៖ {{ $mission->fundingLabel() ?? $blank }}</div>
        </div>
        <div class="date-sign">
            <div>{{ $place ?: '................' }}, {{ $issuedLabel }}</div>
            <div class="signature-title">{{ $details['signatory_title'] ?? $blank }}</div>
        </div>
        <table class="stops" aria-label="តារាងចុះទិដ្ឋការ">
            <thead><tr>
                <th class="place">ទីកន្លែងចេញដំណើរ និងមកដល់</th><th>ថ្ងៃ ខែ ឆ្នាំ</th><th>មធ្យោបាយ ធ្វើដំណើរ</th>
                <th style="width:8%">ចម្ងាយ</th><th style="width:9%">ចំនួនថ្ងៃ ស្នាក់នៅ</th><th style="width:31%">សេចក្ដីបញ្ជាក់របស់អង្គភាព ហត្ថលេខា និងត្រា</th>
            </tr></thead>
            <tbody>
                <tr><td>ទីកន្លែងចេញដំណើរ @if($origin)<div><strong>{{ $origin }}</strong></div>@else<div class="dotline"></div>@endif</td><td></td><td></td><td></td><td></td><td></td></tr>
                @foreach(['ទីកន្លែងមកដល់', 'ទីកន្លែងចេញដំណើរ', 'ទីកន្លែងមកដល់'] as $label)
                    <tr><td>{!! $placeCell($label) !!}</td><td></td><td></td><td></td><td></td><td></td></tr>
                @endforeach
            </tbody>
        </table>
    </main>
    <main class="sheet">
        <table class="stops continued" aria-label="តារាងចុះទិដ្ឋការ (ត)">
            <tbody>
                @for($i = 0; $i < 8; $i++)
                    <tr><td class="place">{!! $placeCell($i % 2 === 0 ? 'ទីកន្លែងចេញដំណើរ' : 'ទីកន្លែងមកដល់') !!}</td>
                        <td></td><td></td><td style="width:8%"></td><td style="width:9%"></td><td style="width:31%"></td></tr>
                @endfor
            </tbody>
        </table>
    </main>
@endforeach
</body>
</html>

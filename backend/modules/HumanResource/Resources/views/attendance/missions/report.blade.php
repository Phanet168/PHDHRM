<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>របាយការណ៍បេសកកម្ម — {{ $mission->order_number ?: $mission->title }}</title>
    @include('humanresource::attendance.missions.letter-style')
    <style>
        h1 { text-decoration: underline; text-underline-offset: 6px; margin-top: 8mm; }
        .ref { text-indent: 10mm; margin: 4mm 0 2mm; }
        .item { margin-top: 2mm; }
        .item > .label { font-weight: 700; }
        .item .body { padding-left: 10mm; white-space: pre-wrap; overflow-wrap: anywhere; }
        .results { margin: 0; padding-left: 16mm; }
        .results li { overflow-wrap: anywhere; }
        .officer-list { margin: 0; padding-left: 16mm; list-style: '- '; }
        .signatures { display: flex; justify-content: space-between; align-items: flex-start; gap: 10mm; margin-top: 10mm; break-inside: avoid; }
        .signatures > div { text-align: center; }
        .approve { margin-top: 18mm; }
        .approve .signature-title { margin-top: 1mm; }
        .reporter-name { margin-top: 22mm; font-weight: 700; }
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
    $blankDate = 'ថ្ងៃទី............ខែ...............ឆ្នាំ'.$year;
    $place = trim((string) ($details['issue_place'] ?? ''));
    $days = $mission->start_date->diffInDays($mission->end_date) + 1;
    $results = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $report->results) ?: [])));
    // Older reports only have a free-text summary: show it as the results.
    $legacy = ! $report->activities && ! $results && ! $report->recommendations;
    $reporterName = trim(($reporter?->honorific_on_order ? $reporter->honorific_on_order.' ' : '').($reporter?->name_on_order ?? $report->employee?->full_name ?? ''));
    $public = $public ?? false;
@endphp
<nav class="toolbar">
    @unless($public)
        <a href="{{ route('missions.show', $mission->id) }}">ត្រឡប់ទៅបេសកកម្ម</a>
    @endunless
    <button type="button" onclick="window.print()">បោះពុម្ព / រក្សាទុកជា PDF</button>
    <span>A4 · សូមបិទ Headers/Footers ក្នុងផ្ទាំងបោះពុម្ព</span>
</nav>
<main class="sheet">
    @include('humanresource::attendance.missions.letter-head', ['details' => $details, 'mottoOrnament' => '3'])
    <h1>របាយការណ៍បេសកកម្ម</h1>
    <p class="ref">យោងលិខិតបញ្ជាបេសកកម្មលេខ៖ {{ $mission->order_number ?: '................' }} {{ $details['letter_code'] ?? '' }}
        ចុះ{{ !empty($details['issued_on']) ? $dateLabel($details['issued_on']) : $blankDate }}</p>

    <div class="item"><span class="label">១- ថ្នាក់ដឹកនាំ</span>
        <ul class="officer-list">
            @foreach($officers as $officer)
                <li>{{ trim($officer->honorific_on_order.' '.($officer->name_on_order ?? $officer->employee?->full_name)) }}@if($officer->position_on_order) — {{ $officer->position_on_order }}@endif</li>
            @endforeach
        </ul>
    </div>
    <div class="item"><span class="label">២- គោលបំណង</span> ៖ {{ $mission->purpose ?: $mission->title }}</div>
    <div class="item"><span class="label">៣- ពេលវេលារយៈពេល</span> {{ $digits($days) }} ថ្ងៃ គិតចាប់ពី{{ $dateLabel($mission->start_date) }}
        <div class="body">ដល់{{ $dateLabel($mission->end_date) }}</div></div>
    <div class="item"><span class="label">៤- ទីកន្លែងនិងសកម្មភាពការងារ</span> ៖ {{ $mission->destination }}
        @if($report->activities)<div class="body">{{ $report->activities }}</div>@endif</div>
    <div class="item"><span class="label">៥- លទ្ធផលការងារ</span> ៖
        @if($legacy)
            <div class="body">{{ $report->summary }}</div>
        @else
            <ul class="results officer-list">
                @forelse($results as $line)<li>{{ $line }}</li>@empty<li>........................................</li>@endforelse
            </ul>
        @endif
    </div>
    <div class="item"><span class="label">៦- សំណូមពរ និងសេចក្ដីសន្និដ្ឋាន</span> ៖
        <div class="body">{{ $report->recommendations ?: '........................................................................' }}</div></div>

    <section class="signatures">
        <div class="approve">
            <div>បានឃើញ និងឯកភាព</div>
            <div>{{ $place ?: '................' }} {{ $blankDate }}</div>
            <div class="signature-title">{{ $details['signatory_title'] ?? '........................................' }}</div>
        </div>
        <div>
            <div>{{ $place ?: '................' }} {{ $report->submitted_at ? $dateLabel($report->submitted_at) : $blankDate }}</div>
            <div>អ្នកធ្វើរបាយការណ៍</div>
            <div class="reporter-name">{{ $reporterName }}</div>
        </div>
    </section>
</main>
</body>
</html>

{{-- One indicator as a list row of the data-entry table. --}}
@php($kh = fn ($v) => \Modules\Report\Services\PeriodCode::kh($v))
<tr class="rpt-row" data-id="{{ $r['id'] }}" data-dept="{{ $r['department_id'] }}" data-unit="{{ $r['unit_id'] }}"
    data-search="{{ mb_strtolower($r['code'] . ' ' . $r['name'] . ' ' . $r['unit'] . ' ' . $r['department']) }}"
    data-empty="{{ $r['value'] === null ? 1 : 0 }}">
    <td class="rpt-id" title="{{ $r['code'] }}">{{ \Modules\Report\Entities\ReportIndicator::shortCode($r['code']) }}</td>
    <td class="rpt-name">
        <span data-name>{{ $r['name'] }}</span>
        @if ($r['value_hc'] !== null || $r['value_hosp'] !== null)
            <div class="rpt-sub">HC {{ \Modules\Report\Services\PeriodCode::khNumber($r['value_hc']) }} • HOSP {{ \Modules\Report\Services\PeriodCode::khNumber($r['value_hosp']) }}</div>
        @endif
        @if ($r['computed'] && in_array($src = $r['computed']['source'] ?? null, ['his', 'hrm'], true))
            <div class="rpt-sub" style="color:var(--rpt-b700)" title="{{ $r[$src] }}">ពីទិន្នន័យ {{ strtoupper($src) }}{{ $r['computed']['of'] > 1 ? ' (' . $kh($r['computed']['months']) . '/' . $kh($r['computed']['of']) . ' ខែ)' : '' }}: <b>{{ \Modules\Report\Services\PeriodCode::khNumber($r['computed']['value']) }}</b>
                @if ($src === 'hrm')<a href="{{ route('report.health.hr.people', ['indicator' => $r['id'], 'period' => $p['code']]) }}">មើលឈ្មោះ</a>@endif
                — បញ្ចូលតម្លៃ ដើម្បីជំនួស</div>
        @elseif ($r['computed'])
            <div class="rpt-sub" style="color:var(--rpt-b700)">គណនាពីខែ ({{ \Modules\Report\Entities\ReportIndicator::AGGREGATIONS[$r['aggregation']] }} {{ $kh($r['computed']['months']) }}/{{ $kh($r['computed']['of']) }}): <b>{{ \Modules\Report\Services\PeriodCode::khNumber($r['computed']['value']) }}</b> — បញ្ចូលតម្លៃ ដើម្បីជំនួស</div>
        @endif
    </td>
    @foreach (['value', 'target'] as $field)
        <td data-label="{{ $field === 'value' ? 'តម្លៃ' : 'ផែនការ' }}">
            @if ($r['can_edit'])
                <input class="rpt-cell-input" inputmode="decimal" data-field="{{ $field }}" value="{{ $num($r[$field]) }}"
                    placeholder="{{ $field === 'value' && $r['computed'] ? $num($r['computed']['value']) : '0' }}"
                    aria-label="{{ $field === 'value' ? 'តម្លៃ' : 'ផែនការ' }} {{ $r['code'] }}">
            @else
                {{ \Modules\Report\Services\PeriodCode::khNumber($field === 'value' ? $r['display_value'] : $r[$field]) }}
            @endif
        </td>
    @endforeach
    <td data-status data-label="ស្ថានភាព">
        @if ($r['status'] === 'missing')
            <span class="rpt-badge rpt-badge-muted">មិនទាន់បញ្ចូល</span>
        @elseif ($r['status'] === 'computed')
            <span class="rpt-badge rpt-badge-info">{{ isset($r['computed']['source']) ? strtoupper($r['computed']['source']) : 'គណនា' }}</span>
        @elseif ($r['status'] === 'partial')
            <span class="rpt-badge rpt-badge-warn" title="មិនទាន់ដល់គោលដៅ">មិនទាន់គ្រប់</span>
        @else
            <span class="rpt-badge rpt-badge-ok">បានបញ្ចូល</span>
        @endif
    </td>
    <td>
        @if ($r['can_edit'])
            <button type="button" class="rpt-link" data-issue="{{ $r['id'] }}">{{ $r['actions']->isNotEmpty() ? 'បញ្ហា (' . $kh($r['actions']->count()) . ')' : 'បន្ថែមបញ្ហា' }}</button>
        @elseif ($r['actions']->isNotEmpty())
            <span class="rpt-badge rpt-badge-warn">បញ្ហា {{ $kh($r['actions']->count()) }}</span>
        @endif
    </td>
</tr>

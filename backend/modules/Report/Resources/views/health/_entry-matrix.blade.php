{{-- A unit whose indicators are "<item>(<variant>)" shown as one grid: item rows × variant columns. --}}
@php
    $PC = \Modules\Report\Services\PeriodCode::class;
    $cells = collect($m['rows'])->flatMap(fn ($row) => array_values($row['cells']));
@endphp
<tr class="rpt-row rpt-matrix-row" data-dept="{{ $first['department_id'] }}" data-unit="{{ $first['unit_id'] }}"
    data-search="{{ mb_strtolower($cells->map(fn ($r) => $r['code'] . ' ' . $r['name'])->implode(' ') . ' ' . $first['unit'] . ' ' . $first['department']) }}">
    <td colspan="6" style="padding:12px 16px">
        <div class="rpt-matrix-wrap">
            <table class="rpt-matrix">
                <thead>
                    <tr>
                        <th class="rpt-mlabel">សូចនាករ</th>
                        @foreach ($m['columns'] as $label)<th>{{ $label }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($m['rows'] as $row)
                        <tr>
                            <th class="rpt-mlabel" scope="row">{{ $row['label'] }}</th>
                            @foreach (array_keys($m['columns']) as $col)
                                @php($r = $row['cells'][$col] ?? null)
                                @if (!$r)
                                    <td class="rpt-mnone" aria-label="គ្មានសូចនាករ">—</td>
                                    @continue
                                @endif
                                @php($src = $r['computed']['source'] ?? null)
                                <td class="rpt-mcell" data-id="{{ $r['id'] }}" data-col="{{ $loop->index }}" data-empty="{{ $r['value'] === null ? 1 : 0 }}"
                                    data-calc="{{ $r['computed']['value'] ?? '' }}" data-display="{{ $r['display_value'] ?? '' }}"
                                    title="{{ $r['code'] }} · {{ $r['name'] }}{{ $r['computed'] ? ' — ' . ($src ? 'ពី ' . strtoupper($src) : 'គណនាពីខែ') . ': ' . $PC::khNumber($r['computed']['value']) : '' }}">
                                    <span data-name hidden>{{ $r['name'] }}</span>
                                    @if ($r['can_edit'])
                                        <input class="rpt-cell-input" inputmode="decimal" data-field="value" value="{{ $num($r['value']) }}"
                                            placeholder="{{ $r['computed'] ? $num($r['computed']['value']) : '' }}" aria-label="{{ $row['label'] }} — {{ $m['columns'][$col] }}">
                                        <input type="hidden" data-field="target" value="{{ $num($r['target']) }}">
                                    @else
                                        <span class="rpt-mvalue">{{ $PC::khNumber($r['display_value']) }}</span>
                                    @endif
                                    <div class="rpt-mfoot">
                                        <span data-status>
                                            @if ($r['status'] === 'missing')<span class="rpt-badge rpt-badge-muted" title="មិនទាន់បញ្ចូល">មិនទាន់បញ្ចូល</span>
                                            @elseif ($r['status'] === 'computed')<span class="rpt-badge rpt-badge-info" title="{{ $src ? strtoupper($src) : 'គណនា' }}">{{ $src ? strtoupper($src) : 'គណនា' }}</span>
                                            @elseif ($r['status'] === 'partial')<span class="rpt-badge rpt-badge-warn" title="មិនទាន់ដល់គោលដៅ">មិនទាន់គ្រប់</span>
                                            @else<span class="rpt-badge rpt-badge-ok" title="បានបញ្ចូល">បានបញ្ចូល</span>@endif
                                        </span>
                                        @if ($src === 'hrm')<a href="{{ route('report.health.hr.people', ['indicator' => $r['id'], 'period' => $p['code']]) }}" title="មើលឈ្មោះ">ឈ្មោះ</a>@endif
                                        @if ($r['can_edit'])
                                            <button type="button" class="rpt-link {{ $r['actions']->isNotEmpty() ? 'has-issue' : '' }}" data-issue="{{ $r['id'] }}" title="បញ្ហាប្រឈម និងសកម្មភាព">{{ $r['actions']->isNotEmpty() ? '⚑ ' . $PC::kh($r['actions']->count()) : '⚑' }}</button>
                                        @elseif ($r['actions']->isNotEmpty())
                                            <span class="rpt-badge rpt-badge-warn">⚑ {{ $PC::kh($r['actions']->count()) }}</span>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th class="rpt-mlabel">សរុប</th>
                        @foreach (array_keys($m['columns']) as $col)<td data-total="{{ $loop->index }}"></td>@endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="rpt-sub mt-2">⌨ Enter = ចុះក្រោម • Tab = ទៅស្តាំ • ផែនការ និងតម្លៃលម្អិត៖ <a href="{{ request()->fullUrlWithQuery(['layout' => 'list']) }}">បង្ហាញជាបញ្ជី</a></div>
    </td>
</tr>

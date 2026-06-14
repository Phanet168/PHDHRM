@php
    $item = $item ?? [];
    $itemIndex = $index;
    $indicatorModel = collect($indicators)->firstWhere('id', (int) ($item['indicator_id'] ?? 0));
    $orgUnitModel = collect($orgUnits)->firstWhere('id', (int) ($item['responsible_org_unit_id'] ?? 0));
    $createdAt = $item['created_at'] ?? now()->format('Y-m-d H:i');
@endphp

<tr data-plan-item-row>
    <td>
        <span data-display="created_at">{{ $createdAt }}</span>
        <div class="d-none">
            <input type="hidden" name="items[{{ $itemIndex }}][created_at]" value="{{ $createdAt }}" data-field="created_at">
            <input type="hidden" name="items[{{ $itemIndex }}][indicator_id]" value="{{ $item['indicator_id'] ?? '' }}" data-field="indicator_id">
            <input type="hidden" name="items[{{ $itemIndex }}][responsible_org_unit_id]" value="{{ $item['responsible_org_unit_id'] ?? $defaultOrgUnitId ?? '' }}" data-field="responsible_org_unit_id">
            <input type="hidden" name="items[{{ $itemIndex }}][item_code]" value="{{ $item['item_code'] ?? '' }}" data-field="item_code">
            <input type="hidden" name="items[{{ $itemIndex }}][title]" value="{{ $item['title'] ?? '' }}" data-field="title">
            <input type="hidden" name="items[{{ $itemIndex }}][baseline_value]" value="{{ $item['baseline_value'] ?? '' }}" data-field="baseline_value">
            <input type="hidden" name="items[{{ $itemIndex }}][target_value]" value="{{ $item['target_value'] ?? '' }}" data-field="target_value">
            <input type="hidden" name="items[{{ $itemIndex }}][achieved_value]" value="{{ $item['achieved_value'] ?? '' }}" data-field="achieved_value">
            <input type="hidden" name="items[{{ $itemIndex }}][target_unit]" value="{{ $item['target_unit'] ?? '' }}" data-field="target_unit">
            <input type="hidden" name="items[{{ $itemIndex }}][description]" value="{{ $item['description'] ?? '' }}" data-field="description">
            <input type="hidden" name="items[{{ $itemIndex }}][indicator_note]" value="{{ $item['indicator_note'] ?? '' }}" data-field="indicator_note">
        </div>
    </td>
    <td>
        <div class="fw-semibold" data-display="indicator_name">{{ $indicatorModel?->name ?: '-' }}</div>
        <div class="planning-meta small" data-display="item_code">{{ $item['item_code'] ?? '-' }}</div>
    </td>
    <td data-display="responsible_org_unit_name">{{ $orgUnitModel?->name ?: '-' }}</td>
    <td class="text-end" data-display="baseline_value">{{ ($item['baseline_value'] ?? '') !== '' ? number_format((float) $item['baseline_value'], 2) : '-' }}</td>
    <td class="text-end" data-display="target_value">{{ ($item['target_value'] ?? '') !== '' ? number_format((float) $item['target_value'], 2) : '-' }}</td>
    <td class="text-end" data-display="achieved_value">{{ ($item['achieved_value'] ?? '') !== '' ? number_format((float) $item['achieved_value'], 2) : '-' }}</td>
    <td>
        <div data-display="target_unit">{{ $item['target_unit'] ?? '-' }}</div>
        <div class="planning-meta small text-truncate" style="max-width: 220px;" data-display="title">{{ $item['title'] ?? '-' }}</div>
    </td>
    <td>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" data-edit-item>កែប្រែ</button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-item>លុប</button>
        </div>
    </td>
</tr>

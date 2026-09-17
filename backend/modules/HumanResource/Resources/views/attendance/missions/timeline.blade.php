@php
    use Modules\HumanResource\Enums\Mission\MissionStatus;

    $steps = [
        1 => 'ដាក់សំណើ',
        2 => 'ប្រធានការិយាល័យ',
        3 => 'ប្រធានមន្ទីរ',
        4 => 'រៀបចំលិខិត',
        5 => 'ចេញលិខិត',
        6 => 'បេសកកម្ម',
        7 => 'របាយការណ៍',
        8 => 'បញ្ចប់',
    ];
    $isDirect = $mission->creation_path?->value === 'direct';
    if ($isDirect) {
        unset($steps[2], $steps[3]);
    }

    $statusToStep = [
        MissionStatus::Draft->value => 1,
        MissionStatus::PendingOfficeHead->value => 2,
        MissionStatus::OfficeHeadEndorsed->value => 3,
        MissionStatus::PendingDirector->value => 3,
        MissionStatus::DirectorApproved->value => 4,
        MissionStatus::PendingMissionOrder->value => 4,
        MissionStatus::PreparingMissionOrder->value => 4,
        MissionStatus::Issued->value => 5,
        MissionStatus::OnMission->value => 6,
        MissionStatus::PendingReport->value => 7,
        MissionStatus::Completed->value => 8,
    ];
    $currentStep = $mission->lifecycle_status ? ($statusToStep[$mission->lifecycle_status->value] ?? 1) : ($isDirect ? 4 : 1);
    $isNegative = $mission->lifecycle_status && in_array($mission->lifecycle_status->value, ['rejected', 'cancelled', 'returned_for_correction'], true);
@endphp
@if($mission->lifecycle_status)
<div class="card mb-3"><div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between mission-timeline">
        @foreach($steps as $index => $label)
            @php
                $done = $index < $currentStep || ($index === 8 && $currentStep === 8);
                $isCurrent = $index === $currentStep && !$isNegative;
            @endphp
            <div class="text-center flex-fill px-1" style="min-width: 90px;">
                <div class="mx-auto mb-1 rounded-circle d-flex align-items-center justify-content-center
                    {{ $done ? 'bg-success text-white' : ($isCurrent ? 'bg-primary text-white' : 'bg-light text-muted border') }}"
                    style="width: 32px; height: 32px;">
                    @if($done)<i class="fa fa-check"></i>@else{{ $index }}@endif
                </div>
                <div class="small {{ $isCurrent ? 'fw-bold' : 'text-muted' }}">{{ $label }}</div>
            </div>
            @if(!$loop->last)<div class="flex-fill border-top mx-1" style="min-width: 20px;"></div>@endif
        @endforeach
    </div>
    @if($isNegative)
        <div class="alert alert-danger mt-3 mb-0">
            ស្ថានភាពបច្ចុប្បន្ន៖ <strong>{{ $mission->lifecycle_status->label() }}</strong>
            @if($mission->rejected_reason) — {{ $mission->rejected_reason }} @endif
        </div>
    @endif
</div></div>
@endif

@php
    $step1Classes = $currentStep === 1 ? 'btn-success text-white border-success' : 'btn-outline-secondary';
    $step2Classes = $currentStep === 2 ? 'btn-success text-white border-success' : ($plan->exists ? 'btn-outline-secondary' : 'btn-outline-secondary disabled');
    $step3Classes = $currentStep === 3 ? 'btn-success text-white border-success' : ($plan->exists ? 'btn-outline-secondary' : 'btn-outline-secondary disabled');
    $step4Classes = $currentStep === 4 ? 'btn-success text-white border-success' : ($plan->exists ? 'btn-outline-secondary' : 'btn-outline-secondary disabled');
    $step5Classes = $currentStep === 5 ? 'btn-success text-white border-success' : ($plan->exists ? 'btn-outline-secondary' : 'btn-outline-secondary disabled');
@endphp

<div class="planning-form-section mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <div class="planning-section-title mb-1">ដំណើរការបង្កើតផែនការ</div>
            <div class="planning-meta">ធ្វើតាមជំហានពីដើមដល់ចប់ ដើម្បីទទួលបានផែនការសម្រេចរបស់អង្គភាពមួយ។</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ $plan->exists ? route('planning.plans.edit', $plan) : route('planning.plans.create') }}"
               class="btn rounded-pill px-4 {{ $step1Classes }}">
                ១. កំណត់សូចនាករ និងគោលដៅ
            </a>
            @if ($plan->exists)
                <a href="{{ route('planning.plans.micro-plan.edit', $plan) }}"
                   class="btn rounded-pill px-4 {{ $step2Classes }}">
                    ២. រៀបចំសកម្មភាព និងថវិកា
                </a>
                <a href="{{ route('planning.plans.activity-plan.edit', $plan) }}"
                   class="btn rounded-pill px-4 {{ $step3Classes }}">
                    ៣. ផែនការប្រចាំត្រីមាស
                </a>
                <a href="{{ route('planning.plans.monthly-activity-plan.edit', $plan) }}"
                   class="btn rounded-pill px-4 {{ $step4Classes }}">
                    ៤. ផែនការប្រចាំខែ
                </a>
                <a href="{{ route('planning.plans.daily-activity-plan.edit', $plan) }}"
                   class="btn rounded-pill px-4 {{ $step5Classes }}">
                    ៥. ផែនការប្រចាំថ្ងៃ
                </a>
            @else
                <span class="btn rounded-pill px-4 {{ $step2Classes }}" aria-disabled="true">
                    ២. រៀបចំសកម្មភាព និងថវិកា
                </span>
                <span class="btn rounded-pill px-4 {{ $step3Classes }}" aria-disabled="true">
                    ៣. ផែនការប្រចាំត្រីមាស
                </span>
                <span class="btn rounded-pill px-4 {{ $step4Classes }}" aria-disabled="true">
                    ៤. ផែនការប្រចាំខែ
                </span>
                <span class="btn rounded-pill px-4 {{ $step5Classes }}" aria-disabled="true">
                    ៥. ផែនការប្រចាំថ្ងៃ
                </span>
            @endif
        </div>
    </div>
</div>

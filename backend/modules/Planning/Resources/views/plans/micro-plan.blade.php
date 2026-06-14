@extends('planning::layouts.app')

@php
    $pageTitle = 'ជំហានទី២: រៀបចំសកម្មភាពគាំទ្រសូចនាករ និងផែនការថវិកា';

    $indicatorOptions = $indicatorItems->map(function ($item) {
        $indicatorRow = $item->indicators->first();
        $indicator = $indicatorRow?->indicator;

        return [
            'indicator_id' => $indicator?->id,
            'indicator_code' => $indicator?->code,
            'indicator_name' => $indicator?->name ?: ($item->indicator_text ?: 'មិនមានសូចនាករ'),
            'program_name' => $indicator?->activityCluster?->subProgram?->program?->name,
            'sub_program_name' => $indicator?->activityCluster?->subProgram?->name,
            'cluster_name' => $indicator?->activityCluster?->name,
            'baseline_value' => $indicatorRow?->baseline_value,
            'target_value' => $indicatorRow?->target_value,
            'achieved_value' => $indicatorRow?->achieved_value,
            'target_unit' => $indicatorRow?->value_text ?: $item->target_unit,
            'responsible_org_unit_id' => $item->responsible_org_unit_id,
            'responsible_org_unit_name' => $item->responsibleOrgUnit?->name,
        ];
    })->filter(fn ($row) => !empty($row['indicator_id']))->values();

    $existingActivities = $activityItems->map(function ($item) {
        $indicatorRow = $item->indicators->first();

        return [
            'indicator_id' => $indicatorRow?->indicator_id,
            'responsible_org_unit_id' => $item->responsible_org_unit_id,
            'item_code' => $item->item_code,
            'title' => $item->title,
            'description' => $item->description,
            'costs' => $item->costs->map(fn ($cost) => [
                'chapter_id' => $cost->chapter_id,
                'account_id' => $cost->account_id,
                'sub_account_id' => $cost->sub_account_id,
                'funding_source_id' => $cost->funding_source_id,
                'cost_code' => $cost->cost_code,
                'cost_name' => $cost->cost_name,
                'qty' => $cost->qty,
                'implementer_count' => $cost->implementer_count,
                'occurrence_count' => $cost->occurrence_count,
                'unit' => $cost->unit,
                'unit_price' => $cost->unit_price,
                'currency_code' => $cost->currency_code,
                'note' => $cost->note,
            ])->values()->toArray(),
        ];
    })->values();

    $orgUnitOptions = $orgUnits->map(fn ($unit) => ['id' => $unit->id, 'name' => $unit->name])->values();
@endphp

@push('css')
    <style>
        .planning-indicator-toolbar {
            display: grid;
            grid-template-columns: minmax(220px, 1.4fr) minmax(170px, 0.8fr) auto;
            gap: 12px;
            align-items: end;
        }

        .planning-indicator-table-wrap {
            max-height: 460px;
            overflow: auto;
            border: 1px solid var(--planning-border);
            border-radius: 0.75rem;
        }

        .planning-indicator-table-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .planning-indicator-row-active {
            background: #eef8f1;
        }

        .planning-mini-stat {
            border: 1px solid var(--planning-border);
            border-radius: 0.75rem;
            padding: 0.85rem 1rem;
            background: #fdfefd;
        }

        .planning-mini-stat-label {
            color: var(--planning-muted);
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
        }

        .planning-mini-stat-value {
            color: #173b2d;
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
        }

        @media (max-width: 991.98px) {
            .planning-indicator-toolbar {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('planning-content')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <div class="planning-kicker">ជំហានទី២</div>
            <h1 class="h3 mt-2 mb-1 planning-page-title">រៀបចំសកម្មភាពគាំទ្រសូចនាករ និងផែនការថវិកា</h1>
            <p class="planning-meta mb-0">
                ជ្រើសសូចនាករដែលបានកំណត់ក្នុងជំហានទី១ រួចបង្កើតសកម្មភាពគាំទ្រ និងធ្វើ costing ដើម្បីទទួលបានថវិកាសរុបរបស់អង្គភាព។
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('planning.plans.show', $plan) }}" class="btn btn-light border">ត្រឡប់ទៅព័ត៌មានផែនការ</a>
            <a href="{{ route('planning.plans.edit', $plan) }}" class="btn btn-outline-secondary">កែជំហានទី១</a>
        </div>
    </div>

    @include('planning::plans.partials.wizard-steps', ['currentStep' => 2])

    <form action="{{ route('planning.plans.micro-plan.update', $plan) }}" method="post" id="micro-plan-form">
        @csrf
        @method('PUT')

        <div class="planning-form-section mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                <div>
                    <div class="planning-section-title mb-1">តារាងសូចនាករពីជំហានទី១</div>
                    <div class="planning-meta">សូចនាករនីមួយៗបង្ហាញគោលដៅ និងថវិកាសរុបក្រោយពីបាន costing។ ចុចប៊ូតុងបង្កើតសកម្មភាព ដើម្បីធ្វើការលើសូចនាករនោះ។</div>
                </div>
                <div class="planning-badge badge-info">អង្គភាព៖ {{ $plan->orgUnit?->name }}</div>
            </div>

            <div class="planning-indicator-toolbar mb-3">
                <div>
                    <label class="form-label">ស្វែងរកសូចនាករ</label>
                    <input type="text" class="form-control" id="indicator-search-input" placeholder="ស្វែងរកតាមកូដ ឬឈ្មោះសូចនាករ">
                </div>
                <div>
                    <label class="form-label">តម្រៀបតាមស្ថានភាព</label>
                    <select class="form-select" id="indicator-status-filter">
                        <option value="all">ទាំងអស់</option>
                        <option value="with_activity">មានសកម្មភាពរួច</option>
                        <option value="without_activity">មិនទាន់មានសកម្មភាព</option>
                    </select>
                </div>
                <div class="planning-mini-stat">
                    <div class="planning-mini-stat-label">សូចនាករដែលកំពុងបង្ហាញ</div>
                    <p class="planning-mini-stat-value" id="indicator-visible-count">0</p>
                </div>
            </div>

            <div class="planning-indicator-table-wrap">
                <table class="table table-bordered align-middle planning-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">ល.រ</th>
                            <th style="min-width: 320px;">សូចនាករ</th>
                            <th style="min-width: 180px;">គោលដៅ</th>
                            <th class="text-center" style="width: 130px;">ស្ថានភាព</th>
                            <th class="text-end" style="width: 120px;">សកម្មភាព</th>
                            <th class="text-end" style="min-width: 180px;">ថវិកា</th>
                            <th class="text-center" style="width: 200px;">សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody id="indicator-summary-body"></tbody>
                </table>
            </div>
            <div id="indicator-empty-state" class="planning-empty mt-3 d-none">
                មិនមានសូចនាករត្រូវនឹងលក្ខខណ្ឌស្វែងរកទេ។
            </div>
        </div>

        <div class="planning-form-section mb-4">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="planning-section-title mb-1">សូចនាករកំពុងធ្វើការ</div>
                    <div class="h5 mb-2" id="active-indicator-name">សូមជ្រើសសូចនាករមួយពីតារាងខាងលើ</div>
                    <div class="planning-meta" id="active-indicator-hierarchy">-</div>
                </div>
                <div class="col-lg-4">
                    <div class="row g-2">
                        <div class="col-6 col-lg-12">
                            <div class="planning-stat">
                                <div class="planning-stat-label">ចំនួនសកម្មភាព</div>
                                <p class="planning-stat-value" id="active-activity-count">0</p>
                            </div>
                        </div>
                        <div class="col-6 col-lg-12">
                            <div class="planning-stat">
                                <div class="planning-stat-label">ថវិកាសរុបសូចនាករ</div>
                                <p class="planning-stat-value" id="active-indicator-budget">0.00</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="planning-form-section mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                <div>
                    <div class="planning-section-title mb-1">តារាងសកម្មភាពគាំទ្រសូចនាករ</div>
                    <div class="planning-meta">បង្កើតសកម្មភាពគាំទ្រសូចនាករដែលបានជ្រើស ហើយបញ្ចូលបន្ទាត់ចំណាយដើម្បីគណនាផែនការថវិកា។</div>
                </div>
                <button type="button" class="btn btn-success" id="open-activity-modal-button" disabled>
                    <i class="fa fa-plus me-1"></i>បង្កើតសកម្មភាព
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle planning-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">ល.រ</th>
                            <th style="min-width: 260px;">សកម្មភាព</th>
                            <th style="min-width: 200px;">អង្គភាពទទួលខុសត្រូវ</th>
                            <th class="text-end" style="width: 120px;">បន្ទាត់ចំណាយ</th>
                            <th class="text-end" style="width: 170px;">ថវិកាសរុប</th>
                            <th class="text-end" style="width: 160px;">សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody id="activities-table-body"></tbody>
                </table>
            </div>

            <div id="activities-empty-state" class="planning-empty mt-3">
                មិនទាន់មានសកម្មភាពសម្រាប់សូចនាករនេះទេ។ សូមចុច <strong>បង្កើតសកម្មភាព</strong> ដើម្បីបញ្ចូលព័ត៌មាន។
            </div>

            <div id="activities-submit-container" class="d-none"></div>
        </div>

        <div class="planning-form-section">
            <div class="planning-section-title mb-3">សរុបផែនការថវិកាអង្គភាព</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="planning-stat">
                        <div class="planning-stat-label">ចំនួនសូចនាករ</div>
                        <p class="planning-stat-value">{{ $indicatorOptions->count() }}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="planning-stat">
                        <div class="planning-stat-label">ចំនួនសកម្មភាពសរុប</div>
                        <p class="planning-stat-value" id="overall-activity-count">0</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="planning-stat">
                        <div class="planning-stat-label">ថវិកាសរុបអង្គភាព</div>
                        <p class="planning-stat-value" id="overall-total-cost">0.00</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center gap-2 mt-4">
            <a href="{{ route('planning.plans.edit', $plan) }}" class="btn btn-light border">ត្រឡប់ទៅជំហានទី១</a>
            <button type="submit" class="btn btn-success">រក្សាទុក និងបន្តទៅជំហានទី៣</button>
        </div>
    </form>

    <template id="indicator-summary-template">
        <tr data-indicator-summary-row>
            <td class="text-center fw-semibold" data-display="row_no">1</td>
            <td>
                <div class="fw-semibold" data-display="indicator_name">-</div>
                <div class="planning-meta small" data-display="indicator_hierarchy">-</div>
            </td>
            <td data-display="target_label">-</td>
            <td class="text-center"><span class="planning-badge badge-info" data-display="indicator_status">-</span></td>
            <td class="text-end fw-semibold" data-display="activity_count">0</td>
            <td class="text-end fw-semibold" data-display="indicator_budget">0.00</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-success" data-select-indicator>បង្កើតសកម្មភាព</button>
            </td>
        </tr>
    </template>

    <template id="activity-row-template">
        <tr data-activity-row>
            <td class="text-center fw-semibold" data-display="row_no">1</td>
            <td>
                <div class="fw-semibold" data-display="title">-</div>
                <div class="planning-meta small" data-display="description">-</div>
                <div class="planning-meta small mt-1">កូដ: <span data-display="item_code">-</span></div>
                <div class="d-none">
                    <input type="hidden" data-field="indicator_id">
                    <input type="hidden" data-field="responsible_org_unit_id">
                    <input type="hidden" data-field="item_code">
                    <input type="hidden" data-field="title">
                    <input type="hidden" data-field="description">
                    <input type="hidden" data-field="costs_json">
                </div>
            </td>
            <td data-display="responsible_org_unit_name">-</td>
            <td class="text-end" data-display="cost_count">0</td>
            <td class="text-end fw-semibold" data-display="total_cost">0.00</td>
            <td>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-edit-activity>កែប្រែ</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-activity>លុប</button>
                </div>
            </td>
        </tr>
    </template>

    <div class="modal fade" id="activityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success-subtle">
                    <div>
                        <h5 class="modal-title mb-1">បង្កើតសកម្មភាពគាំទ្រសូចនាករ</h5>
                        <div class="small text-muted">ជ្រើសសូចនាករពីជំហានទី១ រួចបង្កើតសកម្មភាព និងបញ្ចូលបន្ទាត់ចំណាយដើម្បីទទួលបានថវិកា។</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="activity-modal-error"></div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">សូចនាករ</label>
                            <div class="form-control bg-light" id="modal-indicator-label">-</div>
                            <input type="hidden" data-modal-field="indicator_id">
                            <div class="form-text" id="indicator-meta-text">-</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">អង្គភាពទទួលខុសត្រូវ</label>
                            <select class="form-select" data-modal-field="responsible_org_unit_id">
                                <option value="">ជ្រើសរើសអង្គភាព</option>
                                @foreach ($orgUnits as $orgUnit)
                                    <option value="{{ $orgUnit->id }}">{{ $orgUnit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">កូដសកម្មភាព</label>
                            <input type="text" class="form-control" data-modal-field="item_code">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">ឈ្មោះសកម្មភាព</label>
                            <input type="text" class="form-control" data-modal-field="title" placeholder="ឧ. ចុះត្រួតពិនិត្យ តាមដាន និងគាំទ្រការអនុវត្តការងារ...">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">សេចក្តីពិពណ៌នា</label>
                            <textarea class="form-control" rows="3" data-modal-field="description"></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                        <div>
                            <h6 class="mb-1">បន្ទាត់ចំណាយ</h6>
                            <div class="small text-muted">រូបមន្តគណនា: ចំនួន x ចំនួនអ្នកប្រតិបត្ត x ចំនួនដង x តម្លៃឯកតា</div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="add-cost-row-button">បន្ថែមបន្ទាត់ចំណាយ</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 150px;">ជំពូក</th>
                                    <th style="min-width: 170px;">គណនេយ្យ</th>
                                    <th style="min-width: 180px;">អនុគណនេយ្យ</th>
                                    <th style="min-width: 150px;">ប្រភពថវិកា</th>
                                    <th style="min-width: 240px;">ឈ្មោះចំណាយ</th>
                                    <th style="width: 120px;">ចំនួន</th>
                                    <th style="width: 130px;">អ្នកប្រតិបត្ត</th>
                                    <th style="width: 120px;">ចំនួនដង</th>
                                    <th style="width: 120px;">ឯកតា</th>
                                    <th style="width: 150px;">តម្លៃឯកតា</th>
                                    <th style="width: 90px;">រូបិយប័ណ្ណ</th>
                                    <th style="width: 170px;">តម្លៃសរុប</th>
                                    <th style="min-width: 160px;">កំណត់ចំណាំ</th>
                                    <th style="width: 70px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cost-rows-container"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">បិទ</button>
                    <button type="button" class="btn btn-success" id="save-activity-button">រក្សាទុកទៅតារាង</button>
                </div>
            </div>
        </div>
    </div>

    <template id="cost-row-template">
        <tr data-cost-row>
            <td>
                <select class="form-select form-select-sm" data-cost-field="chapter_id">
                    <option value="">ជ្រើសជំពូក</option>
                    @foreach ($chapters as $chapter)
                        <option value="{{ $chapter->id }}">{{ $chapter->code }} - {{ $chapter->name }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select class="form-select form-select-sm" data-cost-field="account_id">
                    <option value="">ជ្រើសគណនេយ្យ</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" data-chapter-id="{{ $account->chapter_id }}">
                            {{ $account->code }} - {{ $account->name }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td>
                <select class="form-select form-select-sm" data-cost-field="sub_account_id">
                    <option value="">ជ្រើសអនុគណនេយ្យ</option>
                    @foreach ($subAccounts as $subAccount)
                        <option value="{{ $subAccount->id }}" data-account-id="{{ $subAccount->account_id }}">
                            {{ $subAccount->code }} - {{ $subAccount->name }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td>
                <select class="form-select form-select-sm" data-cost-field="funding_source_id">
                    <option value="">ជ្រើសប្រភពថវិកា</option>
                    @foreach ($fundingSources as $source)
                        <option value="{{ $source->id }}">{{ $source->code }} - {{ $source->name }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <div class="vstack gap-1">
                    <input type="text" class="form-control form-control-sm bg-light px-2" style="min-width: 180px;" data-cost-field="cost_code" placeholder="កូដចំណាយ" readonly>
                    <input type="text" class="form-control form-control-sm bg-light px-2" style="min-width: 220px;" data-cost-field="cost_name" placeholder="ឈ្មោះចំណាយ" readonly>
                </div>
            </td>
            <td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end px-2" style="min-width: 110px;" data-cost-field="qty" value="1"></td>
            <td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end px-2" style="min-width: 120px;" data-cost-field="implementer_count" value="1"></td>
            <td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end px-2" style="min-width: 110px;" data-cost-field="occurrence_count" value="1"></td>
            <td><input type="text" class="form-control form-control-sm px-2" style="min-width: 110px;" data-cost-field="unit" placeholder="ឯកតា"></td>
            <td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end px-2" style="min-width: 140px;" data-cost-field="unit_price" value="0"></td>
            <td>
                <select class="form-select form-select-sm" data-cost-field="currency_code">
                    <option value="KHR">រៀល</option>
                    <option value="USD">ដុល្លារ</option>
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm text-end bg-light px-2" style="min-width: 160px;" data-cost-total readonly></td>
            <td><input type="text" class="form-control form-control-sm" data-cost-field="note" placeholder="កំណត់ចំណាំ"></td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-cost-row><i class="fa fa-times"></i></button>
            </td>
        </tr>
    </template>
@endsection

@push('planning-scripts')
    <script>
        (() => {
            const indicatorOptions = @json($indicatorOptions);
            const initialActivities = @json($existingActivities);
            const orgUnits = @json($orgUnitOptions);

            const form = document.getElementById('micro-plan-form');
            const indicatorSummaryBody = document.getElementById('indicator-summary-body');
            const indicatorSummaryTemplate = document.getElementById('indicator-summary-template');
            const indicatorSearchInput = document.getElementById('indicator-search-input');
            const indicatorStatusFilter = document.getElementById('indicator-status-filter');
            const indicatorVisibleCount = document.getElementById('indicator-visible-count');
            const indicatorEmptyState = document.getElementById('indicator-empty-state');
            const tableBody = document.getElementById('activities-table-body');
            const activitiesTemplate = document.getElementById('activity-row-template');
            const emptyState = document.getElementById('activities-empty-state');
            const submitContainer = document.getElementById('activities-submit-container');
            const openActivityModalButton = document.getElementById('open-activity-modal-button');
            const activeIndicatorName = document.getElementById('active-indicator-name');
            const activeIndicatorHierarchy = document.getElementById('active-indicator-hierarchy');
            const activeActivityCount = document.getElementById('active-activity-count');
            const activeIndicatorBudget = document.getElementById('active-indicator-budget');
            const overallActivityCount = document.getElementById('overall-activity-count');
            const overallTotalCost = document.getElementById('overall-total-cost');

            const modalElement = document.getElementById('activityModal');
            const modal = new bootstrap.Modal(modalElement);
            const modalIndicatorLabel = document.getElementById('modal-indicator-label');
            const indicatorMeta = document.getElementById('indicator-meta-text');
            const errorBox = document.getElementById('activity-modal-error');
            const costsContainer = document.getElementById('cost-rows-container');
            const costRowTemplate = document.getElementById('cost-row-template');
            const addCostRowButton = document.getElementById('add-cost-row-button');
            const saveActivityButton = document.getElementById('save-activity-button');

            const modalFields = {
                indicator_id: modalElement.querySelector('[data-modal-field="indicator_id"]'),
                responsible_org_unit_id: modalElement.querySelector('[data-modal-field="responsible_org_unit_id"]'),
                item_code: modalElement.querySelector('[data-modal-field="item_code"]'),
                title: modalElement.querySelector('[data-modal-field="title"]'),
                description: modalElement.querySelector('[data-modal-field="description"]'),
            };

            let activeIndicatorId = indicatorOptions[0]?.indicator_id ?? null;
            let editingRow = null;

            const formatNumber = (value) => Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            const findIndicator = (indicatorId) => indicatorOptions.find((row) => String(row.indicator_id) === String(indicatorId));
            const findOrgUnit = (orgUnitId) => orgUnits.find((row) => String(row.id) === String(orgUnitId));
            const getAllRows = () => Array.from(tableBody.querySelectorAll('[data-activity-row]'));
            const getActivitiesForIndicator = (indicatorId) => getAllRows().filter((row) => String(getRowData(row).indicator_id) === String(indicatorId));
            const getIndicatorActivityCount = (indicatorId) => getActivitiesForIndicator(indicatorId).length;
            const getIndicatorBudget = (indicatorId) => getActivitiesForIndicator(indicatorId)
                .reduce((sum, row) => sum + calculateActivityTotal(getRowData(row).costs), 0);
            const getVisibleIndicators = () => {
                const searchText = indicatorSearchInput.value.trim().toLowerCase();
                const statusFilter = indicatorStatusFilter.value;

                return indicatorOptions.filter((indicator) => {
                    const activityCount = getIndicatorActivityCount(indicator.indicator_id);
                    const matchesSearch = searchText === ''
                        || `${indicator.indicator_code || ''} ${indicator.indicator_name || ''}`.toLowerCase().includes(searchText);
                    const matchesStatus = statusFilter === 'all'
                        || (statusFilter === 'with_activity' && activityCount > 0)
                        || (statusFilter === 'without_activity' && activityCount === 0);

                    return matchesSearch && matchesStatus;
                });
            };

            const calculateCostTotal = (cost) => {
                const qty = Number(cost.qty || 0);
                const implementerCount = Number(cost.implementer_count || 0);
                const occurrenceCount = Number(cost.occurrence_count || 0);
                const unitPrice = Number(cost.unit_price || 0);
                return qty * implementerCount * occurrenceCount * unitPrice;
            };

            const calculateActivityTotal = (costs) => costs.reduce((sum, cost) => sum + calculateCostTotal(cost), 0);

            const getRowData = (row) => ({
                indicator_id: row.querySelector('[data-field="indicator_id"]').value,
                responsible_org_unit_id: row.querySelector('[data-field="responsible_org_unit_id"]').value,
                item_code: row.querySelector('[data-field="item_code"]').value,
                title: row.querySelector('[data-field="title"]').value,
                description: row.querySelector('[data-field="description"]').value,
                costs: JSON.parse(row.querySelector('[data-field="costs_json"]').value || '[]'),
            });

            const updateOverallSummary = () => {
                const rows = getAllRows();
                overallActivityCount.textContent = rows.length;
                overallTotalCost.textContent = formatNumber(rows.reduce((sum, row) => sum + calculateActivityTotal(getRowData(row).costs), 0));
            };

            const renderIndicatorSummary = () => {
                indicatorSummaryBody.innerHTML = '';
                const visibleIndicators = getVisibleIndicators();

                indicatorVisibleCount.textContent = visibleIndicators.length;
                indicatorEmptyState.classList.toggle('d-none', visibleIndicators.length > 0);

                visibleIndicators.forEach((indicator, index) => {
                    indicatorSummaryBody.insertAdjacentHTML('beforeend', indicatorSummaryTemplate.innerHTML);
                    const row = indicatorSummaryBody.lastElementChild;
                    const budget = getIndicatorBudget(indicator.indicator_id);
                    const activityCount = getIndicatorActivityCount(indicator.indicator_id);

                    row.querySelector('[data-display="row_no"]').textContent = index + 1;
                    row.querySelector('[data-display="indicator_name"]').textContent =
                        `${indicator.indicator_code ? indicator.indicator_code + ' - ' : ''}${indicator.indicator_name}`;
                    row.querySelector('[data-display="indicator_hierarchy"]').textContent =
                        `កម្មវិធី: ${indicator.program_name || '-'} | អនុកម្មវិធី: ${indicator.sub_program_name || '-'} | ចង្កោមសកម្មភាព: ${indicator.cluster_name || '-'}`;
                    row.querySelector('[data-display="target_label"]').textContent =
                        `${indicator.target_value ?? '-'} ${indicator.target_unit || ''}`.trim();
                    row.querySelector('[data-display="indicator_status"]').textContent = activityCount > 0 ? 'មានសកម្មភាព' : 'មិនទាន់មាន';
                    row.querySelector('[data-display="activity_count"]').textContent = activityCount;
                    row.querySelector('[data-display="indicator_budget"]').textContent = formatNumber(budget);
                    row.classList.toggle('planning-indicator-row-active', String(activeIndicatorId) === String(indicator.indicator_id));

                    const actionButton = row.querySelector('[data-select-indicator]');
                    actionButton.classList.toggle('btn-success', String(activeIndicatorId) === String(indicator.indicator_id));
                    actionButton.classList.toggle('btn-outline-success', String(activeIndicatorId) !== String(indicator.indicator_id));
                    actionButton.textContent = String(activeIndicatorId) === String(indicator.indicator_id) ? 'កំពុងធ្វើការ' : 'បង្កើតសកម្មភាព';
                    actionButton.addEventListener('click', () => {
                        activeIndicatorId = indicator.indicator_id;
                        renderWorkspace();
                    });
                });

                if (visibleIndicators.length > 0 && !visibleIndicators.some((indicator) => String(indicator.indicator_id) === String(activeIndicatorId))) {
                    activeIndicatorId = visibleIndicators[0].indicator_id;
                }
            };

            const renderWorkspace = () => {
                const visibleIndicators = getVisibleIndicators();
                if (visibleIndicators.length > 0 && !visibleIndicators.some((indicator) => String(indicator.indicator_id) === String(activeIndicatorId))) {
                    activeIndicatorId = visibleIndicators[0].indicator_id;
                }
                if (visibleIndicators.length === 0) {
                    activeIndicatorId = null;
                }

                const indicator = findIndicator(activeIndicatorId);
                openActivityModalButton.disabled = !indicator;

                if (!indicator) {
                    activeIndicatorName.textContent = 'សូមជ្រើសសូចនាករមួយពីតារាងខាងលើ';
                    activeIndicatorHierarchy.textContent = '-';
                    activeActivityCount.textContent = '0';
                    activeIndicatorBudget.textContent = '0.00';
                    emptyState.classList.remove('d-none');
                    renderIndicatorSummary();
                    updateOverallSummary();
                    return;
                }

                activeIndicatorName.textContent = `${indicator.indicator_code ? indicator.indicator_code + ' - ' : ''}${indicator.indicator_name}`;
                activeIndicatorHierarchy.textContent =
                    `គោលដៅ: ${(indicator.target_value ?? '-')} ${indicator.target_unit || ''} | កម្មវិធី: ${indicator.program_name || '-'} | អនុកម្មវិធី: ${indicator.sub_program_name || '-'} | ចង្កោមសកម្មភាព: ${indicator.cluster_name || '-'}`;

                const rows = getActivitiesForIndicator(indicator.indicator_id);
                let visibleNo = 0;
                rows.forEach((row) => {
                    visibleNo += 1;
                    row.querySelector('[data-display="row_no"]').textContent = visibleNo;
                });

                getAllRows().forEach((row) => {
                    const visible = String(getRowData(row).indicator_id) === String(indicator.indicator_id);
                    row.classList.toggle('d-none', !visible);
                });

                activeActivityCount.textContent = rows.length;
                activeIndicatorBudget.textContent = formatNumber(rows.reduce((sum, row) => sum + calculateActivityTotal(getRowData(row).costs), 0));
                emptyState.classList.toggle('d-none', rows.length > 0);
                renderIndicatorSummary();
                updateOverallSummary();
            };

            const syncCostSelectors = (row) => {
                const chapterId = row.querySelector('[data-cost-field="chapter_id"]').value;
                const accountSelect = row.querySelector('[data-cost-field="account_id"]');
                const subAccountSelect = row.querySelector('[data-cost-field="sub_account_id"]');

                Array.from(accountSelect.options).forEach((option, index) => {
                    if (index === 0) {
                        option.hidden = false;
                        return;
                    }

                    const visible = !chapterId || option.dataset.chapterId === chapterId;
                    option.hidden = !visible;
                    if (!visible && option.selected) {
                        accountSelect.value = '';
                    }
                });

                const accountId = accountSelect.value;
                Array.from(subAccountSelect.options).forEach((option, index) => {
                    if (index === 0) {
                        option.hidden = false;
                        return;
                    }

                    const visible = !accountId || option.dataset.accountId === accountId;
                    option.hidden = !visible;
                    if (!visible && option.selected) {
                        subAccountSelect.value = '';
                    }
                });

                const selectedSubAccount = subAccountSelect.options[subAccountSelect.selectedIndex];
                const costCodeInput = row.querySelector('[data-cost-field="cost_code"]');
                const costNameInput = row.querySelector('[data-cost-field="cost_name"]');

                if (selectedSubAccount && selectedSubAccount.value) {
                    const parts = selectedSubAccount.textContent.split(' - ');
                    costCodeInput.value = parts[0]?.trim() || '';
                    costNameInput.value = parts.slice(1).join(' - ').trim() || '';
                } else {
                    costCodeInput.value = '';
                    costNameInput.value = '';
                }
            };

            const collectCostRow = (row) => ({
                chapter_id: row.querySelector('[data-cost-field="chapter_id"]').value,
                account_id: row.querySelector('[data-cost-field="account_id"]').value,
                sub_account_id: row.querySelector('[data-cost-field="sub_account_id"]').value,
                funding_source_id: row.querySelector('[data-cost-field="funding_source_id"]').value,
                cost_code: row.querySelector('[data-cost-field="cost_code"]').value.trim(),
                cost_name: row.querySelector('[data-cost-field="cost_name"]').value.trim(),
                qty: row.querySelector('[data-cost-field="qty"]').value,
                implementer_count: row.querySelector('[data-cost-field="implementer_count"]').value,
                occurrence_count: row.querySelector('[data-cost-field="occurrence_count"]').value,
                unit: row.querySelector('[data-cost-field="unit"]').value.trim(),
                unit_price: row.querySelector('[data-cost-field="unit_price"]').value,
                currency_code: row.querySelector('[data-cost-field="currency_code"]').value,
                note: row.querySelector('[data-cost-field="note"]').value.trim(),
            });

            const recalcCostRow = (row) => {
                row.querySelector('[data-cost-total]').value = formatNumber(calculateCostTotal(collectCostRow(row)));
            };

            const bindCostRow = (row) => {
                row.querySelector('[data-remove-cost-row]').addEventListener('click', () => {
                    row.remove();
                    if (!costsContainer.children.length) {
                        addCostRow();
                    }
                });

                row.querySelector('[data-cost-field="chapter_id"]').addEventListener('change', () => {
                    row.querySelector('[data-cost-field="account_id"]').value = '';
                    row.querySelector('[data-cost-field="sub_account_id"]').value = '';
                    syncCostSelectors(row);
                    recalcCostRow(row);
                });

                row.querySelector('[data-cost-field="account_id"]').addEventListener('change', () => {
                    row.querySelector('[data-cost-field="sub_account_id"]').value = '';
                    syncCostSelectors(row);
                    recalcCostRow(row);
                });

                row.querySelector('[data-cost-field="sub_account_id"]').addEventListener('change', () => {
                    syncCostSelectors(row);
                    recalcCostRow(row);
                });

                row.querySelectorAll('[data-cost-field]').forEach((field) => {
                    field.addEventListener('input', () => recalcCostRow(row));
                    field.addEventListener('change', () => recalcCostRow(row));
                });
            };

            const addCostRow = (cost = null) => {
                costsContainer.insertAdjacentHTML('beforeend', costRowTemplate.innerHTML);
                const row = costsContainer.lastElementChild;
                bindCostRow(row);

                if (cost) {
                    Object.entries(cost).forEach(([field, value]) => {
                        const input = row.querySelector(`[data-cost-field="${field}"]`);
                        if (input) {
                            input.value = value ?? '';
                        }
                    });
                }

                syncCostSelectors(row);
                recalcCostRow(row);
            };

            const resetModal = () => {
                editingRow = null;
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
                costsContainer.innerHTML = '';
                addCostRow();

                const indicator = findIndicator(activeIndicatorId);
                modalFields.indicator_id.value = indicator ? indicator.indicator_id : '';
                modalFields.responsible_org_unit_id.value = indicator?.responsible_org_unit_id || '';
                modalFields.item_code.value = '';
                modalFields.title.value = '';
                modalFields.description.value = '';
                modalIndicatorLabel.textContent = indicator
                    ? `${indicator.indicator_code ? indicator.indicator_code + ' - ' : ''}${indicator.indicator_name}`
                    : '-';
                indicatorMeta.textContent = indicator
                    ? `គោលដៅ: ${indicator.target_value ?? '-'} ${indicator.target_unit || ''} | សម្រេចបានឆ្នាំចាស់: ${indicator.baseline_value ?? '-'}`
                    : '-';
            };

            const collectModalValues = () => ({
                indicator_id: modalFields.indicator_id.value,
                responsible_org_unit_id: modalFields.responsible_org_unit_id.value,
                item_code: modalFields.item_code.value.trim(),
                title: modalFields.title.value.trim(),
                description: modalFields.description.value.trim(),
                costs: Array.from(costsContainer.querySelectorAll('[data-cost-row]')).map(collectCostRow),
            });

            const validateActivity = (activity) => {
                if (!activity.indicator_id) {
                    return 'សូមជ្រើសសូចនាករពីជំហានទី១ ជាមុនសិន។';
                }
                if (!activity.responsible_org_unit_id) {
                    return 'សូមជ្រើសអង្គភាពទទួលខុសត្រូវ។';
                }
                if (!activity.title) {
                    return 'សូមបញ្ចូលឈ្មោះសកម្មភាព។';
                }
                if (!activity.costs.length) {
                    return 'សូមបន្ថែមយ៉ាងហោចណាស់ ១ បន្ទាត់ចំណាយ។';
                }

                for (const cost of activity.costs) {
                    if (!cost.chapter_id || !cost.account_id || !cost.sub_account_id) {
                        return 'សូមបំពេញ ជំពូក គណនេយ្យ និងអនុគណនេយ្យ ឲ្យបានគ្រប់។';
                    }
                    if (!cost.qty || !cost.implementer_count || !cost.occurrence_count || !cost.unit_price) {
                        return 'សូមបំពេញ ចំនួន ចំនួនអ្នកប្រតិបត្ត ចំនួនដង និងតម្លៃឯកតា ឲ្យបានគ្រប់។';
                    }
                }

                return null;
            };

            const setRowData = (row, activity) => {
                row.querySelector('[data-field="indicator_id"]').value = activity.indicator_id;
                row.querySelector('[data-field="responsible_org_unit_id"]').value = activity.responsible_org_unit_id;
                row.querySelector('[data-field="item_code"]').value = activity.item_code || '';
                row.querySelector('[data-field="title"]').value = activity.title;
                row.querySelector('[data-field="description"]').value = activity.description || '';
                row.querySelector('[data-field="costs_json"]').value = JSON.stringify(activity.costs);

                const orgUnit = findOrgUnit(activity.responsible_org_unit_id);
                row.querySelector('[data-display="item_code"]').textContent = activity.item_code || '-';
                row.querySelector('[data-display="title"]').textContent = activity.title;
                row.querySelector('[data-display="description"]').textContent = activity.description || '-';
                row.querySelector('[data-display="responsible_org_unit_name"]').textContent = orgUnit ? orgUnit.name : '-';
                row.querySelector('[data-display="cost_count"]').textContent = activity.costs.length;
                row.querySelector('[data-display="total_cost"]').textContent = formatNumber(calculateActivityTotal(activity.costs));
            };

            const bindRowActions = (row) => {
                row.querySelector('[data-remove-activity]').addEventListener('click', () => {
                    row.remove();
                    renderWorkspace();
                });

                row.querySelector('[data-edit-activity]').addEventListener('click', () => {
                    const activity = getRowData(row);
                    editingRow = row;
                    errorBox.classList.add('d-none');
                    modalFields.indicator_id.value = activity.indicator_id || '';
                    modalFields.responsible_org_unit_id.value = activity.responsible_org_unit_id || '';
                    modalFields.item_code.value = activity.item_code || '';
                    modalFields.title.value = activity.title || '';
                    modalFields.description.value = activity.description || '';

                    const indicator = findIndicator(activity.indicator_id);
                    modalIndicatorLabel.textContent = indicator
                        ? `${indicator.indicator_code ? indicator.indicator_code + ' - ' : ''}${indicator.indicator_name}`
                        : '-';
                    indicatorMeta.textContent = indicator
                        ? `គោលដៅ: ${indicator.target_value ?? '-'} ${indicator.target_unit || ''} | សម្រេចបានឆ្នាំចាស់: ${indicator.baseline_value ?? '-'}`
                        : '-';

                    costsContainer.innerHTML = '';
                    (activity.costs.length ? activity.costs : [null]).forEach((cost) => addCostRow(cost));
                    modal.show();
                });
            };

            const addActivityRow = (activity) => {
                tableBody.insertAdjacentHTML('beforeend', activitiesTemplate.innerHTML);
                const row = tableBody.lastElementChild;
                bindRowActions(row);
                setRowData(row, activity);
                return row;
            };

            openActivityModalButton.addEventListener('click', () => {
                resetModal();
                modal.show();
            });

            indicatorSearchInput.addEventListener('input', () => renderWorkspace());
            indicatorStatusFilter.addEventListener('change', () => renderWorkspace());

            addCostRowButton.addEventListener('click', () => addCostRow());

            saveActivityButton.addEventListener('click', () => {
                const activity = collectModalValues();
                const error = validateActivity(activity);

                if (error) {
                    errorBox.textContent = error;
                    errorBox.classList.remove('d-none');
                    return;
                }

                errorBox.classList.add('d-none');

                if (editingRow) {
                    setRowData(editingRow, activity);
                } else {
                    addActivityRow(activity);
                }

                modal.hide();
                renderWorkspace();
            });

            form.addEventListener('submit', () => {
                submitContainer.innerHTML = '';

                getAllRows().forEach((row, index) => {
                    const activity = getRowData(row);
                    ['indicator_id', 'responsible_org_unit_id', 'item_code', 'title', 'description'].forEach((field) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `activities[${index}][${field}]`;
                        input.value = activity[field] ?? '';
                        submitContainer.appendChild(input);
                    });

                    activity.costs.forEach((cost, costIndex) => {
                        Object.entries(cost).forEach(([field, value]) => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = `activities[${index}][costs][${costIndex}][${field}]`;
                            input.value = value ?? '';
                            submitContainer.appendChild(input);
                        });
                    });
                });
            });

            initialActivities.forEach((activity) => addActivityRow(activity));
            renderWorkspace();
        })();
    </script>
@endpush

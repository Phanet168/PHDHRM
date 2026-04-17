@extends('backend.layouts.app')
@section('title', app()->getLocale() === 'en' ? 'Review promotion request' : 'ពិនិត្យសំណើដំឡើង')

@section('content')
    @include('humanresource::employee_header')
    @include('backend.layouts.common.validation')

    @php
        $isEnglishUi = app()->getLocale() === 'en';
        $ui = fn (string $km, string $en) => $isEnglishUi ? $en : $km;
        $fixKhmerText = function ($text) {
            $value = trim((string) $text);
            if ($value === '') {
                return '';
            }

            $iconv = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
            if (is_string($iconv) && $iconv !== '' && preg_match('/\p{Khmer}/u', $iconv)) {
                return trim($iconv);
            }

            $mb = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            if (is_string($mb) && $mb !== '' && preg_match('/\p{Khmer}/u', $mb)) {
                return trim($mb);
            }

            return $value;
        };
        $promotionTypeLabel = function (?string $type) use ($ui): string {
            return match (trim((string) $type)) {
                'annual_rank' => $ui('ដំឡើងឋានន្តរស័ក្តិប្រចាំឆ្នាំ', 'Annual rank promotion'),
                'degree_based' => $ui('តាមសញ្ញាបត្រ', 'By degree'),
                'honorary_pre_retirement' => $ui('ដំឡើងថ្នាក់កិត្តិយសមុននិវត្តន៍', 'Honorary pre-retirement'),
                default => $ui('ដំឡើងថ្នាក់ប្រចាំឆ្នាំ', 'Annual grade promotion'),
            };
        };

        $employeeUnit = $employee->sub_department?->department_name
            ?: ($employee->department?->department_name ?: '-');

        $proposalEffectiveDateIso = !empty($proposal->start_date)
            ? \Carbon\Carbon::parse((string) $proposal->start_date)->toDateString()
            : now()->toDateString();
        $proposalRequestDateIso = !empty($proposal->document_date)
            ? \Carbon\Carbon::parse((string) $proposal->document_date)->toDateString()
            : '';
        $reviewStatus = (string) ($review_status ?? ($proposal->status ?? 'proposed'));
        $reviewCanRecommend = (bool) ($review_can_recommend ?? false);
        $reviewCanApprove = (bool) ($review_can_approve ?? false);
        $reviewCanReject = (bool) ($review_can_reject ?? false);
    @endphp

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-1">{{ $ui('ពិនិត្យសំណើដំឡើង', 'Review promotion request') }}</h5>
                <small class="text-muted">
                    {{ $ui('ពិនិត្យព័ត៌មានសំណើ ហើយផ្តល់យោបល់/អនុម័ត/បដិសេធ', 'Verify request details, then recommend, approve, or reject') }}
                </small>
            </div>
            <a href="{{ route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'approvals']) }}"
                class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-arrow-left me-1"></i>{{ $ui('ត្រឡប់ទៅតារាងអនុម័ត', 'Back to approvals') }}
            </a>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <h6 class="mb-2">{{ $ui('ព័ត៌មានមន្ត្រី', 'Employee information') }}</h6>
                        <p class="mb-1"><strong>{{ $ui('អត្តលេខ', 'Code') }}:</strong> {{ $employee->employee_id ?: '-' }}</p>
                        <p class="mb-1"><strong>{{ $ui('ឈ្មោះ', 'Name') }}:</strong> {{ $fixKhmerText((string) ($employee->full_name ?? '-')) }}</p>
                        <p class="mb-0"><strong>{{ $ui('អង្គភាព', 'Unit') }}:</strong> {{ $fixKhmerText((string) $employeeUnit) }}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <h6 class="mb-2">{{ $ui('ព័ត៌មានសំណើ', 'Request information') }}</h6>
                        <p class="mb-1"><strong>{{ $ui('មូលដ្ឋានសំណើ', 'Request basis') }}:</strong> {{ $promotionTypeLabel($normalized_promotion_type) }}</p>
                        <p class="mb-1"><strong>{{ $ui('ថ្ងៃមានប្រសិទ្ធភាព', 'Effective date') }}:</strong> {{ display_date($proposal->start_date) }}</p>
                        <p class="mb-1"><strong>{{ $ui('ដំណាក់កាល', 'Stage') }}:</strong>
                            @if ($reviewStatus === 'recommended')
                                <span class="badge bg-info text-white">{{ $ui('បានផ្តល់យោបល់', 'Recommended') }}</span>
                            @else
                                <span class="badge bg-warning text-dark">{{ $ui('កំពុងស្នើ', 'Proposed') }}</span>
                            @endif
                        </p>
                        <p class="mb-0"><strong>{{ $ui('លេខយោងសំណើ', 'Request ref') }}:</strong> {{ $proposal->document_reference ?: '-' }}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <h6 class="mb-2">{{ $ui('ថ្នាក់បៀវត្ស', 'Pay level') }}</h6>
                        <p class="mb-1"><strong>{{ $ui('ថ្នាក់បច្ចុប្បន្ន', 'Current') }}:</strong> {{ $fixKhmerText((string) $current_pay_level_label) }}</p>
                        <p class="mb-0"><strong>{{ $ui('ថ្នាក់ត្រូវឡើង', 'Target') }}:</strong> {{ $fixKhmerText((string) $target_pay_level_label) }}</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('employee-pay-promotions.store') }}">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="_active_tab" value="approvals">
                <input type="hidden" name="proposal_id" value="{{ (int) $proposal->id }}">
                <input type="hidden" name="employee_id" value="{{ (int) $employee->id }}">
                <input type="hidden" name="effective_date" value="{{ old('effective_date', $proposalEffectiveDateIso) }}">
                <input type="hidden" name="promotion_type" value="{{ old('promotion_type', $normalized_promotion_type) }}">
                <input type="hidden" name="request_reference" value="{{ old('request_reference', (string) ($proposal->document_reference ?? '')) }}">
                <input type="hidden" name="request_date" value="{{ old('request_date', $proposalRequestDateIso) }}">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ $ui('ថ្នាក់ត្រូវឡើង', 'Target pay level') }} <span class="text-danger">*</span></label>
                        <select name="pay_level_id" class="form-select" required>
                            @foreach ($pay_levels as $payLevel)
                                @php
                                    $labelKm = trim((string) ($payLevel->level_name_km ?? ''));
                                    $payLevelLabel = $fixKhmerText($labelKm !== '' ? $labelKm : (string) ($payLevel->level_code ?? '-'));
                                @endphp
                                <option value="{{ (int) $payLevel->id }}" {{ (int) old('pay_level_id', (int) $proposal->pay_level_id) === (int) $payLevel->id ? 'selected' : '' }}>
                                    {{ $payLevelLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ localize('document_reference', 'Document reference') }}</label>
                        <input type="text" name="document_reference" class="form-control"
                            value="{{ old('document_reference', (string) ($proposal->document_reference ?? '')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ localize('document_date', 'Document date') }}</label>
                        <input type="date" name="document_date" class="form-control"
                            value="{{ old('document_date', $proposalRequestDateIso) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ localize('note', 'Note') }}</label>
                        <textarea name="note" rows="2" class="form-control">{{ old('note') }}</textarea>
                    </div>
                </div>

                <div class="mt-3 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-info text-white" name="record_mode" value="recommend" {{ $reviewCanRecommend ? '' : 'disabled' }} title="{{ $reviewCanRecommend ? '' : $ui('អាចផ្តល់យោបល់បានតែនៅដំណាក់កាល កំពុងស្នើ ប៉ុណ្ណោះ', 'Recommend is available only at Proposed stage and within your role scope.') }}">
                        <i class="fa fa-commenting me-1"></i>{{ $ui('ផ្តល់យោបល់', 'Recommend') }}
                    </button>
                    <button type="submit" class="btn btn-success" name="record_mode" value="approve" {{ $reviewCanApprove ? '' : 'disabled' }} title="{{ $reviewCanApprove ? '' : $ui('អនុម័តបានតែនៅដំណាក់កាល បានផ្តល់យោបល់ និងស្ថិតក្នុងសិទ្ធិរបស់អ្នក', 'Approve is available only at Recommended stage and within your role scope.') }}">
                        <i class="fa fa-check me-1"></i>{{ $ui('អនុម័ត', 'Approve') }}
                    </button>
                    <button type="submit" class="btn btn-outline-danger" name="record_mode" value="reject" {{ $reviewCanReject ? '' : 'disabled' }} title="{{ $reviewCanReject ? '' : $ui('អ្នកមិនមានសិទ្ធិបដិសេធសំណើនេះទេ', 'You do not have permission to reject this request.') }}"
                        onclick="return confirm('{{ $ui('តើបងប្រាកដថាចង់បដិសេធសំណើនេះមែនទេ?', 'Are you sure you want to reject this request?') }}');">
                        <i class="fa fa-times me-1"></i>{{ $ui('បដិសេធ', 'Reject') }}
                    </button>
                    <a href="{{ route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'approvals']) }}"
                        class="btn btn-light border">
                        {{ localize('cancel', 'Cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection

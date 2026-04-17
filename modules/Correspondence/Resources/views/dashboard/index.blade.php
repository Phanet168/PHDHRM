@extends('backend.layouts.app')

@section('title', localize('correspondence_management', 'ការគ្រប់គ្រងលិខិតរដ្ឋបាល'))

@push('css')
    <style>
        .corr-dashboard-stats .col-md-3 {
            display: flex;
        }

        .corr-dashboard-stats .corr-stat-card {
            border-radius: 14px;
            border: 1px solid rgba(15, 50, 35, 0.08);
            box-shadow: 0 8px 18px rgba(15, 50, 35, 0.08);
            padding: 14px;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            position: relative;
            overflow: hidden;
        }

        .corr-dashboard-stats .corr-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(15, 50, 35, 0.12);
        }

        .corr-dashboard-stats .corr-stat-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .corr-dashboard-stats .corr-stat-title {
            font-size: 12px;
            letter-spacing: 0.01em;
            font-weight: 600;
            color: #355142;
            margin: 0;
        }

        .corr-dashboard-stats .corr-stat-icon {
            width: 30px;
            height: 30px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        .corr-dashboard-stats .corr-stat-value {
            font-size: 40px;
            line-height: 1;
            font-weight: 700;
            color: #17382b;
            margin: 0;
        }

        .corr-dashboard-stats .corr-stat-sub {
            margin-top: 6px;
            font-size: 11px;
            color: #5e6f66;
        }

        .corr-dashboard-stats .corr-stat-incoming {
            background: linear-gradient(135deg, #e8f8ff 0%, #f7fbff 100%);
        }

        .corr-dashboard-stats .corr-stat-incoming .corr-stat-icon {
            background: #d8f0ff;
            color: #1679b8;
        }

        .corr-dashboard-stats .corr-stat-outgoing {
            background: linear-gradient(135deg, #edf6ff 0%, #f8fbff 100%);
        }

        .corr-dashboard-stats .corr-stat-outgoing .corr-stat-icon {
            background: #dbe8ff;
            color: #2b68c6;
        }

        .corr-dashboard-stats .corr-stat-pending {
            background: linear-gradient(135deg, #fff5e8 0%, #fffaf2 100%);
        }

        .corr-dashboard-stats .corr-stat-pending .corr-stat-icon {
            background: #ffe8c7;
            color: #c57900;
        }

        .corr-dashboard-stats .corr-stat-completed {
            background: linear-gradient(135deg, #e8f8ef 0%, #f5fbf8 100%);
        }

        .corr-dashboard-stats .corr-stat-completed .corr-stat-icon {
            background: #d5f2e0;
            color: #177b47;
        }

        @media (max-width: 768px) {
            .corr-dashboard-stats .corr-stat-value {
                font-size: 32px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $periodOptions = [
            'today' => localize('today', 'ថ្ងៃនេះ'),
            'yesterday' => localize('yesterday', 'ម្សិលមិញ'),
            'this_week' => localize('this_week', 'សប្តាហ៍នេះ'),
            'this_month' => localize('this_month', 'ខែនេះ'),
            'all' => localize('all_time', 'ទាំងអស់'),
            'custom' => localize('custom_range', 'ជ្រើសរើសថ្ងៃ'),
        ];
        $selectedPeriod = $period ?? 'today';
        $selectedStartDate = $startDate ?? '';
        $selectedEndDate = $endDate ?? '';
    @endphp

    <div class="body-content">
        @include('correspondence::_nav')

        @if (($level ?? 'unknown') === 'unknown')
            <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i>
                {{ localize('no_org_role_assigned', 'អ្នកមិនទាន់មានតួនាទីតាមអង្គភាព។ សូមទាក់ទងអ្នកគ្រប់គ្រងប្រព័ន្ធ។') }}
            </div>
        @endif

        <div class="card corr-card mb-3">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label mb-1">{{ localize('display_period', 'បង្ហាញតាមរយៈពេល') }}</label>
                        <select name="period" class="form-select form-select-sm">
                            @foreach ($periodOptions as $periodKey => $periodLabel)
                                <option value="{{ $periodKey }}" {{ $selectedPeriod === $periodKey ? 'selected' : '' }}>{{ $periodLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">{{ localize('from_date', 'ពីថ្ងៃ') }}</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $selectedStartDate }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">{{ localize('to_date', 'ដល់ថ្ងៃ') }}</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $selectedEndDate }}">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fa fa-filter me-1"></i>{{ localize('apply_filter', 'បង្ហាញ') }}
                        </button>
                        <a href="{{ route('correspondence.index') }}" class="btn btn-sm btn-outline-secondary">{{ localize('reset', 'កំណត់ឡើងវិញ') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3 corr-dashboard-stats">
            <div class="col-md-3">
                <div class="corr-stat-card corr-stat-incoming h-100">
                    <div class="corr-stat-head">
                        <div class="corr-stat-title">{{ localize('incoming_letters', 'លិខិតចូល') }}</div>
                        <span class="corr-stat-icon"><i class="fa fa-inbox"></i></span>
                    </div>
                    <p class="corr-stat-value">{{ $incomingCount }}</p>
                    <div class="corr-stat-sub">{{ localize('all_incoming_letters', 'ចំនួនលិខិតចូលសរុប') }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="corr-stat-card corr-stat-outgoing h-100">
                    <div class="corr-stat-head">
                        <div class="corr-stat-title">{{ localize('outgoing_letters', 'លិខិតចេញ') }}</div>
                        <span class="corr-stat-icon"><i class="fa fa-paper-plane"></i></span>
                    </div>
                    <p class="corr-stat-value">{{ $outgoingCount }}</p>
                    <div class="corr-stat-sub">{{ localize('all_outgoing_letters', 'ចំនួនលិខិតចេញសរុប') }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="corr-stat-card corr-stat-pending h-100">
                    <div class="corr-stat-head">
                        <div class="corr-stat-title">{{ localize('pending_processing', 'កំពុងដំណើរការ') }}</div>
                        <span class="corr-stat-icon"><i class="fa fa-hourglass-half"></i></span>
                    </div>
                    <p class="corr-stat-value">{{ $pendingCount }}</p>
                    <div class="corr-stat-sub">{{ localize('letters_in_progress', 'លិខិតកំពុងដំណើរការ') }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="corr-stat-card corr-stat-completed h-100">
                    <div class="corr-stat-head">
                        <div class="corr-stat-title">{{ localize('completed', 'បានបញ្ចប់') }}</div>
                        <span class="corr-stat-icon"><i class="fa fa-check-circle"></i></span>
                    </div>
                    <p class="corr-stat-value">{{ $completedCount }}</p>
                    <div class="corr-stat-sub">{{ localize('letters_completed', 'លិខិតបានបញ្ចប់') }}</div>
                </div>
            </div>
        </div>

        <div class="card corr-card">
            <div class="card-header">
                <h6 class="mb-0">{{ localize('correspondence_workflow', 'សេចក្តីសង្ខេបលំហូរការងារ') }}</h6>
            </div>
            <div class="card-body">
                <ol class="mb-0">
                    <li>{{ localize('incoming_receiver_step', 'អ្នកទទួលលិខិត ចុះឈ្មោះ និងរក្សាទុក') }}</li>
                    <li>{{ localize('delegate_step', 'អ្នកបែងចែកលិខិត: ប្រធាន/អនុប្រធានការិយាល័យរដ្ឋបាល បែងចែកទៅប្រធានអង្គភាពពាក់ព័ន្ធ') }}</li>
                    <li>{{ localize('office_comment_step_multi', 'អង្គភាពពាក់ព័ន្ធ (អាចលើសពី 2) ពិនិត្យ ផ្តល់យោបល់ និង CC ជូនអនុប្រធានមន្ទីរ') }}</li>
                    <li>{{ localize('deputy_review_step', 'អនុប្រធានមន្ទីរទទួលខុសត្រូវ ពិនិត្យ និងផ្តល់យោបល់ទៅប្រធានមន្ទីរ') }}</li>
                    <li>{{ localize('director_decision_step', 'ប្រធានមន្ទីរទទួល និងសម្រេចបញ្ចប់ដំណើរការ') }}</li>
                </ol>
            </div>
        </div>
    </div>
@endsection


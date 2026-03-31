@extends('backend.layouts.app')
@section('title', app()->getLocale() === 'en' ? 'Grade and rank management' : 'គ្រប់គ្រងថ្នាក់ និងឋានន្តរស័ក្តិ')

@push('css')
    <style>
        @font-face {
            font-family: 'Tacteing';
            src: url('{{ asset('backend/assets/dist/fonts/khmer/TACTENG.TTF') }}') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .employee-org-combo {
            position: relative;
        }

        .employee-org-combo-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            min-height: 31px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            background: #fff;
            padding: 4px 10px;
            text-align: left;
        }

        .employee-org-combo-toggle .combo-label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.82rem;
        }

        .employee-org-combo-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 1200;
            border: 1px solid #d8dee5;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        }

        .employee-org-combo.is-open .employee-org-combo-dropdown {
            display: block;
        }

        .employee-org-combo-tools {
            display: flex;
            gap: 8px;
            padding: 8px;
            border-bottom: 1px solid #e7edf3;
        }

        .employee-org-tree-filter-body {
            max-height: 300px;
            overflow: auto;
            padding: 8px 10px;
        }

        .employee-org-tree,
        .employee-org-tree ul {
            list-style: none;
            margin: 0;
            padding-left: 16px;
        }

        .employee-org-tree-item {
            position: relative;
            margin: 2px 0;
            padding-left: 14px;
        }

        .employee-org-tree-item::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 0;
            width: 12px;
            height: 16px;
            border-left: 1px dotted #9aa8b6;
            border-bottom: 1px dotted #9aa8b6;
        }

        .employee-org-tree-item::after {
            content: '';
            position: absolute;
            left: 0;
            top: 10px;
            bottom: -8px;
            border-left: 1px dotted #9aa8b6;
        }

        .employee-org-tree-item:last-child::after {
            display: none;
        }

        .employee-org-tree > .employee-org-tree-item {
            padding-left: 6px;
        }

        .employee-org-tree > .employee-org-tree-item::before,
        .employee-org-tree > .employee-org-tree-item::after {
            display: none;
        }

        .employee-org-tree-item > .employee-org-tree {
            display: none;
        }

        .employee-org-tree-item.is-open > .employee-org-tree {
            display: block;
        }

        .employee-org-tree-row {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .employee-org-tree-toggle {
            width: 16px;
            height: 16px;
            border: 1px solid #7f8da0;
            background: #fff;
            color: #1f2f40;
            padding: 0;
            line-height: 14px;
            text-align: center;
            border-radius: 2px;
            font-size: 12px;
            cursor: pointer;
        }

        .employee-org-tree-toggle-placeholder {
            width: 16px;
            height: 16px;
            display: inline-block;
        }

        .employee-org-tree-node-filter {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 12px;
            color: #1f2f40;
            text-decoration: none;
        }

        .employee-org-tree-node-filter:hover {
            background: #eef4f9;
            color: #0f5e95;
        }

        .employee-org-tree-node-filter.is-active {
            background: #1f75b8;
            color: #fff;
        }

        .employee-org-tree-order {
            min-width: 20px;
            padding: 0 4px;
            border: 1px solid #c3d0dc;
            border-radius: 10px;
            text-align: center;
            font-size: 10px;
            color: #45607a;
            background: #f1f6fb;
            line-height: 15px;
        }

        .employee-org-tree-icon {
            color: #8a7a12;
            font-size: 11px;
            width: 13px;
            text-align: center;
        }

        .employee-org-tree-name {
            font-weight: 600;
            line-height: 1.25;
        }

        .employee-org-tree-type {
            color: #6b7785;
            font-size: 10px;
        }

        .pay-promotion-page-header .breadcrumb {
            margin-bottom: 0.35rem;
            background: transparent;
            padding: 0;
            font-size: 0.9rem;
        }

        .pay-promotion-page-header .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
        }

        .pay-promotion-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.15rem;
        }

        .pay-promotion-subtitle {
            color: #5f6b7a;
            font-size: 0.9rem;
        }

        .pay-promotion-stat-badge {
            border: 1px solid #d5dce3;
            border-radius: 20px;
            padding: 6px 10px;
            font-size: 0.82rem;
            background: #f8fafc;
            color: #223246;
            white-space: nowrap;
        }

        .pay-promotion-header-tools .form-label {
            font-size: 0.75rem;
            margin-bottom: 0.2rem;
            color: #4e5a68;
        }

        .pay-promotion-table-tools .form-label {
            font-size: 0.76rem;
            margin-bottom: 0.2rem;
            color: #4e5a68;
        }

        .pay-promotion-next-actions .btn {
            min-width: 180px;
        }

        #pay-promotion-staff-table tr.employee-unit-main-group-row td,
        #pay-promotion-staff-table tr.employee-unit-group-row.depth-0 td,
        #pay-promotion-candidate-table tr.employee-unit-main-group-row td,
        #pay-promotion-candidate-table tr.employee-unit-group-row.depth-0 td {
            background: #eef5fb;
            color: #1a4368;
            font-weight: 700;
            border-top: 2px solid #d2e4f5;
            border-bottom: 1px solid #d2e4f5;
        }

        #pay-promotion-staff-table tr.employee-unit-sub-group-row td,
        #pay-promotion-staff-table tr.employee-unit-group-row.depth-1 td,
        #pay-promotion-staff-table tr.employee-unit-group-row.depth-2 td,
        #pay-promotion-staff-table tr.employee-unit-group-row.depth-3 td,
        #pay-promotion-staff-table tr.employee-unit-group-row.depth-4 td,
        #pay-promotion-candidate-table tr.employee-unit-sub-group-row td,
        #pay-promotion-candidate-table tr.employee-unit-group-row.depth-1 td,
        #pay-promotion-candidate-table tr.employee-unit-group-row.depth-2 td,
        #pay-promotion-candidate-table tr.employee-unit-group-row.depth-3 td,
        #pay-promotion-candidate-table tr.employee-unit-group-row.depth-4 td {
            background: #f7fbff;
            color: #284b69;
            font-weight: 600;
            border-top: 1px dashed #c4d8eb;
            border-bottom: 1px solid #e2edf7;
        }

        .pay-promotion-official-report {
            margin-top: 1rem;
            border: 1px solid #d4d9df;
            background: #fff;
            padding: 20px 24px;
            font-family: "Khmer OS Siemreap", "Noto Sans Khmer", serif;
            color: #0d2f5f;
        }

        .pay-promotion-official-report .report-header-center {
            text-align: center;
            line-height: 1.55;
            margin-bottom: 0.75rem;
        }

        .pay-promotion-official-report .report-header-center .line-main {
            font-family: "Khmer M1", "Khmer OS Muol Light", serif;
            font-size: 22px;
        }

        .pay-promotion-official-report .report-header-center .line-sub {
            font-family: "Khmer M1", "Khmer OS Muol Light", serif;
            font-size: 22px;
        }

        .pay-promotion-official-report .report-header-center .line-star {
            font-family: "Tacteing", "Khmer M1", serif;
            font-size: 80px;
            line-height: 0.85;
        }

        .pay-promotion-official-report .report-logo-block {
            min-width: 320px;
            text-align: center;
        }

        .pay-promotion-official-report .report-logo-block img {
            width: 96px !important;
            height: 96px !important;
            min-width: 96px;
            min-height: 96px;
            max-width: 96px;
            max-height: 96px;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            object-position: center;
            display: block;
            margin: 0 auto;
            border-radius: 0;
            background: transparent;
        }

        .pay-promotion-official-report .report-logo-text {
            margin-top: 0.35rem;
            font-size: 14px;
            font-family: "Khmer M1", "Khmer OS Muol Light", serif;
            line-height: 1.5;
        }

        .pay-promotion-official-report .report-logo-text > div {
            white-space: nowrap;
        }

        .pay-promotion-official-report .report-title {
            text-align: center;
            font-family: "Khmer M1", "Khmer OS Muol Light", serif;
            font-size: 20px;
            margin: 0.85rem 0 0.5rem;
        }

        .pay-promotion-official-report .report-subtitle {
            text-align: center;
            color: #243447;
            margin-bottom: 0.85rem;
            font-size: 14px;
        }

        .pay-promotion-official-report .report-table th,
        .pay-promotion-official-report .report-table td {
            border: 1px solid #1f2a3a !important;
            vertical-align: middle;
            padding: 6px 8px;
            font-size: 13px;
        }

        .pay-promotion-official-report .report-table th {
            text-align: center;
            font-family: "Khmer M1", "Khmer OS Muol Light", serif;
            font-weight: normal;
            background: #f4f7fb;
        }

        .pay-promotion-official-report .report-table td.text-center {
            text-align: center;
        }

        .pay-promotion-official-report .report-signature {
            margin-top: 1.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .pay-promotion-official-report .report-signature .signature-col {
            text-align: center;
            min-height: 120px;
        }

        .pay-promotion-official-report .report-signature .signature-title {
            font-family: "Khmer M1", "Khmer OS Muol Light", serif;
            font-size: 15px;
            margin-bottom: 68px;
        }

        .pay-promotion-official-report .report-signature .signature-title-approval {
            margin-bottom: 6px;
        }

        .pay-promotion-official-report .report-signature .signature-line {
            font-family: "Khmer OS Siemreap", "Noto Sans Khmer", serif;
            font-size: 14px;
        }

        @media print {
            body.print-pay-promotion-report * {
                visibility: hidden !important;
            }

            body.print-pay-promotion-report #payPromotionOfficialPrint,
            body.print-pay-promotion-report #payPromotionOfficialPrint * {
                visibility: visible !important;
            }

            body.print-pay-promotion-report #payPromotionOfficialPrint {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                border: none;
                padding: 12mm;
            }
        }
    </style>
@endpush

@section('content')
    @include('humanresource::employee_header')
    @include('backend.layouts.common.validation')

    @php
        $oldEmployeeIds = array_map('intval', (array) old('employee_ids', []));

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

        $fixPayLevelKm = function ($text) use ($fixKhmerText) {
            $value = $fixKhmerText($text);
            $value = preg_replace('/\.\.+/u', '.', $value) ?? $value;
            return trim((string) $value);
        };

        $eligibleEmployeeIds = array_map('intval', (array) ($eligible_employee_ids ?? []));
        $selectedEmployeeIds = !empty($oldEmployeeIds) ? $oldEmployeeIds : $eligibleEmployeeIds;
        $overdueReminders = collect($overdue_reminders ?? [])->values();
        $pendingProposals = collect($pending_proposals ?? [])->values();
        $proposalActionPermissions = (array) ($proposal_action_permissions ?? []);
        $proposalActionSummary = (array) ($proposal_action_summary ?? []);
        $approvalCanRecommendCount = (int) ($proposalActionSummary['can_recommend'] ?? 0);
        $approvalCanApproveCount = (int) ($proposalActionSummary['can_approve'] ?? 0);
        $approvalCanRejectCount = (int) ($proposalActionSummary['can_reject'] ?? 0);
        $approvalBlockedCount = (int) ($proposalActionSummary['blocked'] ?? 0);
        $pendingProposalEmployeeIds = collect($pending_proposal_employee_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        $pendingProposalEmployeeSet = array_flip($pendingProposalEmployeeIds);
        $autoPromotionCandidates = collect($auto_promotion_candidates ?? [])->values();

        $cutoffDateDisplay = !empty($cutoff_date)
            ? display_date($cutoff_date)
            : display_date(\Carbon\Carbon::create($year, 4, 1)->toDateString());
        $cutoffDateIso = !empty($cutoff_date)
            ? \Carbon\Carbon::parse($cutoff_date)->toDateString()
            : \Carbon\Carbon::create($year, 4, 1)->toDateString();
        $selectedUnitId = (int) ($selected_unit_id ?? request()->query('unit_id', 0));
        $selectedEmployeeTypeId = (int) ($selected_employee_type_id ?? request()->query('employee_type_id', 0));
        $selectedServiceState = trim((string) ($selected_service_state ?? request()->query('service_state', '')));
        $lastUpdatedDisplay = !empty($last_updated_at)
            ? \Carbon\Carbon::parse($last_updated_at)->format('d/m/Y H:i')
            : '-';
        $serviceStateOptions = [
            '' => localize('all_status', 'All status'),
            'active' => localize('active', 'Active'),
            'suspended' => localize('suspended', 'Suspended'),
            'inactive' => localize('inactive', 'Inactive'),
        ];
        $isEnglishUi = app()->getLocale() === 'en';
        $ui = function (string $km, string $en) use ($isEnglishUi) {
            return $isEnglishUi ? $en : $km;
        };
        $labelModuleTitle = $ui('គ្រប់គ្រងថ្នាក់ និងឋានន្តរស័ក្តិ', 'Grade and rank management');
        $labelTabDashboard = $ui('ផ្ទាំងសង្ខេប', 'Summary dashboard');
        $labelTabEligibleList = $ui('បញ្ជីគ្រប់លក្ខខណ្ឌ', 'Eligible list');
        $labelTabEmployeeInfo = $ui('ព័ត៌មានមន្ត្រី', 'Employee information');
        $labelTabCreateRequest = $ui('បង្កើតសំណើ', 'Create request');
        $labelTabPromotionHistory = $ui('ប្រវត្តិដំឡើង', 'Promotion history');
        $labelTabAllRequests = $ui('សំណើទាំងអស់', 'All requests');
        $labelTabApproval = $ui('ការអនុម័ត', 'Approvals');
        $labelTabReports = $ui('របាយការណ៍', 'Reports');
        $labelTabNotifications = $ui('ការជូនដំណឹង', 'Notifications');
        $labelTotalEmployees = $ui('មន្ត្រីសរុប', 'Total officials');
        $labelEligiblePromotion = $ui('គ្រប់លក្ខខណ្ឌដំឡើងថ្នាក់', 'Eligible for grade promotion');
        $labelPendingRequests = $ui('សំណើកំពុងរង់ចាំ', 'Pending requests');
        $labelOverdue3Years = $ui('ហួសកាលកំណត់លើស ៣ ឆ្នាំ', 'Overdue over 3 years');
        $labelNearNextCycle = $ui('ជិតដល់វដ្តបន្ទាប់', 'Near next cycle');
        $labelMissingDocs = $ui('ឯកសារខ្វះ', 'Missing documents');
        $labelPayGrade = $ui('ថ្នាក់បៀវត្ស', 'Pay grade');
        $labelServiceState = $ui('ស្ថានភាពការងារ', 'Service status');
        $labelCountableYears = $ui('អាយុកាលគិតបាន', 'Countable service years');
        $labelLastPromotionDate = $ui('ថ្ងៃដំឡើងចុងក្រោយ', 'Last promotion date');
        $labelAction = $ui('សកម្មភាព', 'Actions');

        // Backward compatibility with old tab names.
        $tabAliasMap = [
            'summary' => 'dashboard',
            'action' => 'form',
            'pending' => 'requests',
        ];
        $allowedTabs = ['dashboard', 'staff', 'detail', 'form', 'history', 'requests', 'approvals', 'reports', 'alerts'];
        $activeTab = old('_active_tab', request()->query('tab', $errors->any() ? 'form' : 'dashboard'));
        $activeTab = $tabAliasMap[$activeTab] ?? $activeTab;
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'dashboard';
        }

        $prefillProposalId = (int) old('proposal_id', request()->query('proposal_id', 0));
        $prefillProposal = $prefillProposalId > 0
            ? $pendingProposals->firstWhere('id', $prefillProposalId)
            : null;

        $prefillEmployeeId = (int) old('employee_id', request()->query('employee_id', (int) ($prefillProposal->employee_id ?? 0)));
        if ($prefillEmployeeId <= 0) {
            $prefillEmployeeId = (int) optional($employees->first())->id;
        }

        $prefillRecordMode = old('record_mode', request()->query('record_mode', 'request'));
        if (!in_array($prefillRecordMode, ['request', 'approve', 'reject'], true)) {
            $prefillRecordMode = 'request';
        }
        $prefillPayLevelId = (int) old('pay_level_id', request()->query('pay_level_id', (int) ($prefillProposal->pay_level_id ?? 0)));
        $prefillEffectiveDate = (string) old('effective_date', request()->query('effective_date', (string) ($prefillProposal->start_date ?? $cutoffDateIso)));
        $prefillNextReviewDate = (string) old('next_review_date', request()->query('next_review_date', ''));
        $prefillRequestReference = (string) old('request_reference', request()->query('request_reference', (string) ($prefillProposal->document_reference ?? '')));
        $prefillRequestDate = (string) old('request_date', request()->query('request_date', (string) ($prefillProposal->document_date ?? '')));
        $prefillDocumentReference = (string) old('document_reference', request()->query('document_reference', ''));
        $prefillDocumentDate = (string) old('document_date', request()->query('document_date', ''));
        $prefillNote = (string) old('note', request()->query('note', ''));

        $prefillPromotionType = (string) old('promotion_type', request()->query('promotion_type', (string) ($prefillProposal->promotion_type ?? 'annual_grade')));
        if (in_array($prefillPromotionType, ['regular', 'yearly_cycle'], true)) {
            $prefillPromotionType = 'annual_grade';
        } elseif ($prefillPromotionType === 'special_request') {
            $prefillPromotionType = 'degree_based';
        } elseif ($prefillPromotionType === 'special_case') {
            $prefillPromotionType = 'honorary_pre_retirement';
        }

        $employeesById = collect($employees)->keyBy('id');
        $detailEmployee = $employeesById->get($prefillEmployeeId) ?: $employees->first();
        $detailSnapshot = $detailEmployee ? ($employee_snapshots[$detailEmployee->id] ?? null) : null;

        $allSnapshots = collect($employee_snapshots ?? []);
        $stateCadreCount = $allSnapshots->where('is_state_cadre', true)->count();
        $dueRegularCount = $allSnapshots->where('is_due_regular', true)->count();
        $overdue3YearCount = $allSnapshots->where('is_overdue_3y', true)->count();
        $inactiveCadreSnapshots = $allSnapshots
            ->filter(function ($snapshot) {
                return (bool) ($snapshot['is_state_cadre'] ?? false)
                    && (($snapshot['service_state'] ?? 'active') !== 'active');
            })
            ->values();

        // Reminder bucket: near due for next cycle (~within 90 days to 2-year threshold).
        $nearDueSnapshots = $allSnapshots
            ->filter(function ($snapshot) {
                if (!(bool) ($snapshot['is_state_cadre'] ?? false)) {
                    return false;
                }
                if (($snapshot['service_state'] ?? 'active') !== 'active') {
                    return false;
                }
                $days = (int) ($snapshot['countable_days'] ?? 0);

                return $days >= 640 && $days < 730;
            })
            ->values();
        $honoraryDueSnapshots = $allSnapshots
            ->filter(function ($snapshot) {
                return (bool) ($snapshot['is_due_honorary_pre_retirement'] ?? false);
            })
            ->values();

        $detailEmployeePendingProposals = $detailEmployee
            ? $pendingProposals
                ->filter(fn ($row) => (int) $row->employee_id === (int) $detailEmployee->id)
                ->values()
            : collect();
        $missingDocumentCount = $pendingProposals
            ->filter(function ($proposal) {
                return trim((string) ($proposal->document_reference ?? '')) === '';
            })
            ->count();

        $chartData = $pay_promotion_chart ?? [
            'status_labels' => [],
            'status_values' => [],
            'trend_labels' => [],
            'trend_promoted' => [],
            'trend_requested' => [],
            'level_labels' => [],
            'level_promoted' => [],
            'level_requested' => [],
            'level_overdue' => [],
            'unit_labels' => [],
            'unit_promoted' => [],
            'unit_requested' => [],
            'unit_overdue' => [],
        ];
        $promotionTypeLabel = function (?string $type) use ($ui): string {
            $value = trim((string) $type);
            if (in_array($value, ['regular', 'yearly_cycle'], true)) {
                $value = 'annual_grade';
            } elseif ($value === 'special_request') {
                $value = 'degree_based';
            } elseif ($value === 'special_case') {
                $value = 'honorary_pre_retirement';
            }

            return match ($value) {
                'annual_rank' => $ui('ដំឡើងឋានន្តរស័ក្តិប្រចាំឆ្នាំ', 'Annual rank promotion'),
                'degree_based' => $ui('តាមសញ្ញាបត្រ', 'By degree'),
                'honorary_pre_retirement' => $ui('ដំឡើងថ្នាក់កិត្តិយសមុននិវត្តន៍', 'Honorary pre-retirement'),
                default => $ui('ដំឡើងថ្នាក់ប្រចាំឆ្នាំ', 'Annual grade promotion'),
            };
        };
        $candidateReasonLabel = function (array $row) use ($ui): string {
            $reasonCode = trim((string) ($row['reason_code'] ?? 'annual_grade'));
            $countableYears = (float) ($row['countable_years'] ?? 0);
            $daysToRetirement = $row['days_to_retirement'] ?? null;

            if ($reasonCode === 'honorary_pre_retirement') {
                if ($daysToRetirement !== null && (int) $daysToRetirement >= 0) {
                    return $ui(
                        'មុននិវត្តន៍ ១ ឆ្នាំ (នៅសល់ ' . (int) $daysToRetirement . ' ថ្ងៃ)',
                        'Honorary pre-retirement (' . (int) $daysToRetirement . ' days left)'
                    );
                }

                return $ui('មុននិវត្តន៍ ១ ឆ្នាំ', 'Honorary pre-retirement');
            }

            if ($countableYears >= 3) {
                return $ui(
                    'លើស ៣ ឆ្នាំ (' . number_format($countableYears, 2) . ' ឆ្នាំ)',
                    'Overdue over 3 years (' . number_format($countableYears, 2) . ' years)'
                );
            }

            return $ui(
                'គ្រប់វដ្ត ២ ឆ្នាំ (' . number_format($countableYears, 2) . ' ឆ្នាំ)',
                'Due by 2-year cycle (' . number_format($countableYears, 2) . ' years)'
            );
        };

        $staffEmployees = collect($employees_for_staff ?? $employees ?? [])->values();

        $staffToKhmerDigits = function ($value) {
            return strtr((string) $value, [
                '0' => '០',
                '1' => '១',
                '2' => '២',
                '3' => '៣',
                '4' => '៤',
                '5' => '៥',
                '6' => '៦',
                '7' => '៧',
                '8' => '៨',
                '9' => '៩',
            ]);
        };

        $staffNormalizeGender = function ($employee) {
            $gender = $employee->gender?->gender_name ?? $employee->gender_name ?? '';
            $gender = mb_strtolower(trim((string) $gender), 'UTF-8');

            if (
                str_contains($gender, 'male')
                || str_contains($gender, 'ប្រុស')
                || $gender === 'm'
            ) {
                return 'male';
            }

            if (
                str_contains($gender, 'female')
                || str_contains($gender, 'ស្រី')
                || $gender === 'f'
            ) {
                return 'female';
            }

            return 'other';
        };

        $staffGetSegments = function ($path) {
            $path = trim((string) $path);
            if ($path === '') {
                return ['-'];
            }

            $segments = array_values(array_filter(array_map('trim', explode('|', $path)), fn ($item) => $item !== ''));
            return !empty($segments) ? $segments : ['-'];
        };

        $staffGroupStats = [];
        foreach ($staffEmployees as $staffEmployee) {
            $staffPath = (string) ($employee_unit_paths[$staffEmployee->id] ?? '');
            $staffSegments = $staffGetSegments($staffPath);
            $staffGenderKey = $staffNormalizeGender($staffEmployee);

            foreach ($staffSegments as $depth => $segment) {
                $staffGroupKey = implode(' | ', array_slice($staffSegments, 0, $depth + 1));
                if (!isset($staffGroupStats[$staffGroupKey])) {
                    $staffGroupStats[$staffGroupKey] = ['total' => 0, 'male' => 0, 'female' => 0];
                }

                $staffGroupStats[$staffGroupKey]['total']++;
                if ($staffGenderKey === 'male') {
                    $staffGroupStats[$staffGroupKey]['male']++;
                } elseif ($staffGenderKey === 'female') {
                    $staffGroupStats[$staffGroupKey]['female']++;
                }
            }
        }

        $payLevelCollection = collect($pay_levels ?? [])->values();
        $payLevelById = $payLevelCollection->keyBy('id');
        $payLevelFamilyPrefix = function (?string $code, ?string $nameKm = null) {
            $cleanName = trim((string) $nameKm);
            if ($cleanName !== '') {
                $prefix = mb_substr($cleanName, 0, 1, 'UTF-8');
                $khMap = ['ក' => 'A', 'ខ' => 'B', 'គ' => 'C', 'ឃ' => 'D', 'ង' => 'E', 'ច' => 'F', 'ឆ' => 'G', 'ជ' => 'H'];
                if (isset($khMap[$prefix])) {
                    return $khMap[$prefix];
                }
            }

            $firstChar = mb_strtoupper(mb_substr(trim((string) $code), 0, 1, 'UTF-8'), 'UTF-8');
            return preg_match('/^[A-Z]$/u', $firstChar) ? $firstChar : '';
        };

        $candidateTargetOptionsByCurrent = [];
        foreach ($payLevelCollection as $currentLevel) {
            $currentId = (int) ($currentLevel->id ?? 0);
            if ($currentId <= 0) {
                continue;
            }

            $currentFamily = $payLevelFamilyPrefix($currentLevel->level_code ?? '', $currentLevel->level_name_km ?? '');
            $currentSortOrder = (int) ($currentLevel->sort_order ?? 0);
            $candidateTargetOptionsByCurrent[$currentId] = $payLevelCollection
                ->filter(function ($targetLevel) use ($currentId, $currentFamily, $currentSortOrder, $payLevelFamilyPrefix) {
                    $targetId = (int) ($targetLevel->id ?? 0);
                    if ($targetId <= 0 || $targetId === $currentId) {
                        return false;
                    }

                    $targetFamily = $payLevelFamilyPrefix($targetLevel->level_code ?? '', $targetLevel->level_name_km ?? '');
                    if ($targetFamily === '' || $targetFamily !== $currentFamily) {
                        return false;
                    }

                    $targetSortOrder = (int) ($targetLevel->sort_order ?? 0);
                    if ($currentSortOrder > 0 && $targetSortOrder > 0) {
                        return $targetSortOrder < $currentSortOrder;
                    }

                    return false;
                })
                ->sortBy(function ($targetLevel) {
                    return (int) ($targetLevel->sort_order ?? PHP_INT_MAX);
                })
                ->values();
        }

        $candidateGroupStats = [];
        foreach ($autoPromotionCandidates as $candidateRow) {
            $candidateSegments = $staffGetSegments((string) ($candidateRow['unit_path'] ?? ''));
            foreach ($candidateSegments as $depth => $segment) {
                $groupKey = implode(' | ', array_slice($candidateSegments, 0, $depth + 1));
                if (!isset($candidateGroupStats[$groupKey])) {
                    $candidateGroupStats[$groupKey] = ['total' => 0];
                }
                $candidateGroupStats[$groupKey]['total']++;
            }
        }

        $employeeCurrentLevelMap = collect($current_pay_level_state ?? [])
            ->mapWithKeys(function ($row, $employeeId) {
                return [(int) $employeeId => (int) ($row['current_id'] ?? 0)];
            })
            ->all();

        $candidateTargetOptionsPayload = [];
        foreach ($candidateTargetOptionsByCurrent as $currentId => $options) {
            $candidateTargetOptionsPayload[(string) $currentId] = collect($options)
                ->map(function ($level) use ($fixPayLevelKm) {
                    $label = $fixPayLevelKm($level->level_name_km ?? '');
                    if ($label === '') {
                        $label = (string) ($level->level_code ?? '-');
                    }
                    return [
                        'id' => (int) ($level->id ?? 0),
                        'label' => $label,
                    ];
                })
                ->filter(fn ($item) => (int) ($item['id'] ?? 0) > 0)
                ->values()
                ->all();
        }

        $employeeDisplayLabelMap = collect($employees)
            ->mapWithKeys(function ($employee) use ($fixKhmerText) {
                return [
                    (int) $employee->id => trim((string) $employee->employee_id . ' - ' . $fixKhmerText($employee->full_name)),
                ];
            })
            ->all();
        $promotionPreviousLevelLabels = (array) ($promotion_previous_level_labels ?? []);

        $officialReportRows = collect($promotions ?? [])->values();
        $officialReportLogo = app_setting()->logo ?? asset('assets/HRM2.png');
        $officialReportDate = \Carbon\Carbon::today();
        $officialReportTitle = $ui(
            'បញ្ជីរបាយការណ៍ការគ្រប់គ្រងថ្នាក់ និងឋានន្តរស័ក្តិ ប្រចាំឆ្នាំ ' . $staffToKhmerDigits((string) $year),
            'Grade and rank management report for year ' . $year
        );
        $officialReportDateLine = $ui(
            'ស្ទឹងត្រែង, ថ្ងៃទី ' . $staffToKhmerDigits($officialReportDate->format('d'))
            . ' ខែ ' . $staffToKhmerDigits($officialReportDate->format('m'))
            . ' ឆ្នាំ ' . $staffToKhmerDigits($officialReportDate->format('Y')),
            'Stung Treng, ' . $officialReportDate->format('d/m/Y')
        );
        $officialStatusLabel = function (?string $status) use ($ui): string {
            $value = trim((string) $status);
            return match ($value) {
                'active' => $ui('សកម្ម', 'Active'),
                'proposed' => $ui('កំពុងស្នើ', 'Proposed'),
                'recommended' => $ui('បានផ្តល់យោបល់', 'Recommended'),
                'approved' => $ui('បានអនុម័ត', 'Approved'),
                'rejected' => $ui('បដិសេធ', 'Rejected'),
                'inactive' => $ui('អសកម្ម', 'Inactive'),
                default => $ui('មិនកំណត់', 'Unknown'),
            };
        };
    @endphp

    <div class="card mb-3 fixed-tab-body">
        <div class="card-header pay-promotion-page-header">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">{{ localize('employee', 'Employee') }}</li>
                            <li class="breadcrumb-item active">{{ $labelModuleTitle }}</li>
                        </ol>
                    </nav>
                    <div class="pay-promotion-title">{{ $labelModuleTitle }}</div>
                    <div class="pay-promotion-subtitle">
                        {{ localize('grade_rank_subtitle', 'ត្រួតពិនិត្យលក្ខខណ្ឌ ដំឡើងថ្នាក់ បង្កើតសំណើ និងអនុម័ត') }}
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="pay-promotion-stat-badge">{{ localize('year', 'Year') }} {{ $year }}</span>
                    <span class="pay-promotion-stat-badge">{{ localize('cutoff', 'Cutoff') }} {{ $cutoffDateDisplay }}</span>
                    <span class="pay-promotion-stat-badge">{{ localize('last_updated', 'Last updated') }} {{ $lastUpdatedDisplay }}</span>
                </div>
            </div>

            <div class="pay-promotion-header-tools mt-3">
                <form id="yearFilterForm" method="GET" action="{{ route('employee-pay-promotions.index') }}">
                    <input id="yearFilterTabInput" type="hidden" name="tab" value="{{ $activeTab }}">
                    <div class="row g-2 align-items-end">
                        <div class="col-xl-2 col-lg-4 col-md-6">
                            <label class="form-label">{{ localize('year', 'Year') }}</label>
                            <input type="number" class="form-control form-control-sm" name="year" value="{{ $year }}"
                                min="1950" max="2100">
                        </div>
                        <div class="col-xl-2 col-lg-4 col-md-6">
                            <label class="form-label">{{ localize('cutoff_date', 'Cutoff date') }}</label>
                            <input type="date" class="form-control form-control-sm bg-light" value="{{ $cutoffDateIso }}" readonly>
                        </div>
                        <div class="col-xl-2 col-lg-4 col-md-6">
                            <label class="form-label">{{ localize('organization_unit', 'អង្គភាព') }}</label>
                            @php
                                $allUnitLabel = localize('all_organization_units', 'អង្គភាពទាំងអស់');
                            @endphp
                            <div id="year-filter-unit-tree-combo" class="employee-org-combo mb-1"
                                data-all-label="{{ $allUnitLabel }}">
                                <button type="button" id="year-filter-unit-tree-combo-toggle" class="employee-org-combo-toggle">
                                    <span id="year-filter-unit-tree-combo-label" class="combo-label">{{ $allUnitLabel }}</span>
                                    <i class="fa fa-chevron-down"></i>
                                </button>
                                <div class="employee-org-combo-dropdown">
                                    <div class="employee-org-combo-tools">
                                        <input type="text" id="year-filter-unit-tree-search" class="form-control form-control-sm"
                                            placeholder="{{ localize('search_department') }}">
                                        <button type="button" id="year-filter-unit-tree-clear"
                                            class="btn btn-outline-secondary btn-sm">
                                            {{ localize('reset') }}
                                        </button>
                                    </div>
                                    <div id="year-filter-unit-tree-panel" class="employee-org-tree-filter-body">
                                        @include('humanresource::employee.partials.filter-org-tree', ['nodes' => $org_unit_tree ?? []])
                                    </div>
                                </div>
                            </div>
                            <select id="year_filter_unit_id" class="form-select form-select-sm d-none" name="unit_id">
                                <option value="">{{ $allUnitLabel }}</option>
                                @foreach (($unit_options ?? collect()) as $unitOption)
                                    <option value="{{ $unitOption->id }}" {{ (int) $selectedUnitId === (int) $unitOption->id ? 'selected' : '' }}>
                                        {{ $fixKhmerText($unitOption->department_name ?? '-') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-lg-6 col-md-6">
                            <label class="form-label">{{ localize('employee_type', 'ប្រភេទបុគ្គលិក') }}</label>
                            <select class="form-select form-select-sm" name="employee_type_id">
                                <option value="">{{ localize('all_employee_types', 'ប្រភេទទាំងអស់') }}</option>
                                @foreach (($employee_type_options ?? collect()) as $employeeTypeOption)
                                    <option value="{{ $employeeTypeOption->id }}" {{ (int) $selectedEmployeeTypeId === (int) $employeeTypeOption->id ? 'selected' : '' }}>
                                        {{ $fixKhmerText($employeeTypeOption->employee_type_name ?? ($employeeTypeOption->name_km ?? ($employeeTypeOption->name ?? '-'))) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-lg-6 col-md-6">
                            <label class="form-label">{{ $labelServiceState }}</label>
                            <select class="form-select form-select-sm" name="service_state">
                                @foreach ($serviceStateOptions as $stateKey => $stateLabel)
                                    <option value="{{ $stateKey }}" {{ $selectedServiceState === (string) $stateKey ? 'selected' : '' }}>
                                        {{ $stateLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-lg-12 col-md-12">
                            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fa fa-sync-alt me-1"></i>{{ localize('recalculate', 'Recalculate') }}
                                </button>
                                <a class="btn btn-sm btn-success"
                                    href="{{ route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'form', 'record_mode' => 'request']) }}">
                                    <i class="fa fa-plus-circle me-1"></i>{{ $labelTabCreateRequest }}
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-6 col-xl-2">
                    <div class="alert alert-primary mb-0 py-2 h-100">
                        <div class="small">{{ $labelTotalEmployees }}</div>
                        <div class="fs-5 fw-bold">{{ $staffEmployees->count() }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-2">
                    <div class="alert alert-success mb-0 py-2 h-100">
                        <div class="small">{{ $labelEligiblePromotion }}</div>
                        <div class="fs-5 fw-bold">{{ $dueRegularCount }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-2">
                    <div class="alert alert-info mb-0 py-2 h-100">
                        <div class="small">{{ $labelPendingRequests }}</div>
                        <div class="fs-5 fw-bold">{{ $pendingProposals->count() }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-2">
                    <div class="alert alert-warning mb-0 py-2 h-100">
                        <div class="small">{{ $labelOverdue3Years }}</div>
                        <div class="fs-5 fw-bold">{{ $overdue3YearCount }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-2">
                    <div class="alert alert-secondary mb-0 py-2 h-100">
                        <div class="small">{{ $labelNearNextCycle }}</div>
                        <div class="fs-5 fw-bold">{{ $nearDueSnapshots->count() }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-2">
                    <div class="alert alert-danger mb-0 py-2 h-100">
                        <div class="small">{{ $labelMissingDocs }}</div>
                        <div class="fs-5 fw-bold">{{ $missingDocumentCount }}</div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs flex-nowrap overflow-auto mb-3" id="payPromotionTabs" role="tablist" style="white-space: nowrap;">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'dashboard' ? 'active' : '' }}" id="tab-dashboard-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-dashboard" type="button" role="tab"
                        aria-controls="tab-dashboard" aria-selected="{{ $activeTab === 'dashboard' ? 'true' : 'false' }}">
                        1. {{ $labelTabDashboard }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'staff' ? 'active' : '' }}" id="tab-staff-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-staff" type="button" role="tab"
                        aria-controls="tab-staff" aria-selected="{{ $activeTab === 'staff' ? 'true' : 'false' }}">
                        2. {{ $labelTabEligibleList }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'detail' ? 'active' : '' }}" id="tab-detail-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-detail" type="button" role="tab"
                        aria-controls="tab-detail" aria-selected="{{ $activeTab === 'detail' ? 'true' : 'false' }}">
                        3. {{ $labelTabEmployeeInfo }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'form' ? 'active' : '' }}" id="tab-form-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-form" type="button" role="tab"
                        aria-controls="tab-form" aria-selected="{{ $activeTab === 'form' ? 'true' : 'false' }}">
                        4. {{ $labelTabCreateRequest }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'requests' ? 'active' : '' }}" id="tab-requests-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-requests" type="button" role="tab"
                        aria-controls="tab-requests" aria-selected="{{ $activeTab === 'requests' ? 'true' : 'false' }}">
                        5. {{ $labelTabAllRequests }}
                        <span class="badge bg-secondary ms-1">{{ $pendingProposals->count() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'approvals' ? 'active' : '' }}" id="tab-approvals-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-approvals" type="button" role="tab"
                        aria-controls="tab-approvals" aria-selected="{{ $activeTab === 'approvals' ? 'true' : 'false' }}">
                        6. {{ $labelTabApproval }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'history' ? 'active' : '' }}" id="tab-history-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab"
                        aria-controls="tab-history" aria-selected="{{ $activeTab === 'history' ? 'true' : 'false' }}">
                        7. {{ $labelTabPromotionHistory }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'reports' ? 'active' : '' }}" id="tab-reports-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-reports" type="button" role="tab"
                        aria-controls="tab-reports" aria-selected="{{ $activeTab === 'reports' ? 'true' : 'false' }}">
                        8. {{ $labelTabReports }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'alerts' ? 'active' : '' }}" id="tab-alerts-trigger"
                        data-bs-toggle="tab" data-bs-target="#tab-alerts" type="button" role="tab"
                        aria-controls="tab-alerts" aria-selected="{{ $activeTab === 'alerts' ? 'true' : 'false' }}">
                        9. {{ $labelTabNotifications }}
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="payPromotionTabsContent">
                <div class="tab-pane fade {{ $activeTab === 'dashboard' ? 'show active' : '' }}" id="tab-dashboard" role="tabpanel"
                    aria-labelledby="tab-dashboard-trigger">

                    <div class="alert alert-info mb-3">
                        {{ localize('pay_promotion_summary_note', 'System checks regular promotion eligibility by countable service years up to cutoff date.') }}
                        <strong>{{ localize('cutoff_date', 'Cutoff date') }}: {{ $cutoffDateDisplay }}</strong>
                    </div>

                    <div class="pay-promotion-next-actions d-flex flex-wrap gap-2 mb-3">
                        <a class="btn btn-primary btn-sm" href="{{ route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'staff']) }}">
                            <i class="fa fa-list me-1"></i>{{ $labelTabEligibleList }}
                        </a>
                        <a class="btn btn-success btn-sm" href="{{ route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'form', 'record_mode' => 'request']) }}">
                            <i class="fa fa-plus-circle me-1"></i>{{ $labelTabCreateRequest }}
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'approvals']) }}">
                            <i class="fa fa-check-circle me-1"></i>{{ $labelTabApproval }}
                        </a>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header py-2">
                                    <strong>{{ localize('chart_status_summary', 'ស្ថិតិសរុបតាមឆ្នាំ') }}</strong>
                                </div>
                                <div class="card-body">
                                    <canvas id="payPromotionStatusChart" height="120"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header py-2">
                                    <strong>{{ localize('chart_yearly_trend', 'និន្នាការតាមឆ្នាំ') }}</strong>
                                </div>
                                <div class="card-body">
                                    <canvas id="payPromotionTrendChart" height="120"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header py-2">
                                    <strong>{{ localize('chart_by_unit_level', 'តាមកម្រិតអង្គភាព') }}</strong>
                                </div>
                                <div class="card-body">
                                    <canvas id="payPromotionLevelChart" height="140"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header py-2">
                                    <strong>{{ localize('chart_by_unit', 'តាមអង្គភាព') }}</strong>
                                </div>
                                <div class="card-body">
                                    <canvas id="payPromotionUnitChart" height="140"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header py-2">
                                    <strong>{{ localize('recent_activity', 'សកម្មភាពថ្មីៗ') }}</strong>
                                </div>
                                <div class="card-body">
                                    @if ($promotions->isEmpty())
                                        <div class="text-muted">{{ localize('empty_data', 'No data found') }}</div>
                                    @else
                                        <ul class="list-unstyled mb-0 small">
                                            @foreach ($promotions->take(8) as $activity)
                                                <li class="mb-2">
                                                    <strong>{{ $fixKhmerText($activity->employee?->full_name ?? '-') }}</strong>
                                                    - {{ display_date($activity->start_date) }}
                                                    <span class="text-muted">({{ $fixPayLevelKm($activity->payLevel?->level_name_km ?? ($activity->payLevel?->level_code ?? '-')) }})</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header py-2">
                                    <strong>{{ localize('important_alerts', 'ការជូនដំណឹងសំខាន់') }}</strong>
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0 small">
                                        <li>{{ $labelOverdue3Years }}: <strong>{{ $overdue3YearCount }}</strong></li>
                                        <li>{{ $labelPendingRequests }}: <strong>{{ $pendingProposals->count() }}</strong></li>
                                        <li>{{ $labelMissingDocs }}: <strong>{{ $missingDocumentCount }}</strong></li>
                                        <li>{{ app()->getLocale() === 'en' ? 'Honorary pre-retirement due' : 'មុននិវត្តន៍ ១ ឆ្នាំ (កិត្តិយស)' }}: <strong>{{ $honoraryDueSnapshots->count() }}</strong></li>
                                        <li>{{ localize('inactive_or_suspended', 'អសកម្ម/ផ្អាកការងារ') }}: <strong>{{ $inactiveCadreSnapshots->count() }}</strong></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($overdueReminders->isNotEmpty())
                        <div class="alert alert-warning mb-0">
                            <strong>{{ localize('overdue_list', 'Overdue reminder list') }}:</strong>
                            <div class="small mt-1">
                                @foreach ($overdueReminders->take(20) as $reminder)
                                    <span class="me-3 d-inline-block">
                                        - {{ $fixKhmerText($reminder['employee_code'] ?? '') }} - {{ $fixKhmerText($reminder['full_name'] ?? '') }}
                                        ({{ $reminder['countable_years'] ?? 0 }} {{ localize('years', 'years') }})
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <div class="tab-pane fade {{ $activeTab === 'staff' ? 'show active' : '' }}" id="tab-staff" role="tabpanel"
                    aria-labelledby="tab-staff-trigger">

                    <div class="pay-promotion-table-tools border rounded p-2 mb-3 bg-light">
                        <div class="row g-2 align-items-end">
                            <div class="col-lg-5 col-md-12">
                                <label class="form-label">{{ localize('search', 'Search') }}</label>
                                <input type="text" id="staffFilterKeyword" class="form-control form-control-sm"
                                    placeholder="{{ localize('search_by_name_code', 'Search by name or code') }}">
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label">{{ $labelServiceState }}</label>
                                <select id="staffFilterState" class="form-select form-select-sm">
                                    <option value="">{{ localize('all_status', 'All status') }}</option>
                                    <option value="active">{{ localize('active', 'Active') }}</option>
                                    <option value="suspended">{{ localize('suspended', 'Suspended') }}</option>
                                    <option value="inactive">{{ localize('inactive', 'Inactive') }}</option>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label">{{ $labelEligiblePromotion }}</label>
                                <select id="staffFilterDue" class="form-select form-select-sm">
                                    <option value="">{{ localize('all', 'All') }}</option>
                                    <option value="1">{{ localize('yes', 'Yes') }}</option>
                                    <option value="0">{{ localize('no', 'No') }}</option>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-12 text-md-end">
                                <button type="button" id="staffFilterReset" class="btn btn-sm btn-outline-secondary w-100">
                                    {{ localize('reset', 'Reset') }}
                                </button>
                            </div>
                            <div class="col-12">
                                <small class="text-muted" id="staffFilterResult"></small>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="pay-promotion-staff-table" class="table table-bordered table-striped align-middle">
                            <thead>
                                <tr>
                                    <th width="4%">#</th>
                                    <th>{{ localize('employee', 'Employee') }}</th>
                                    <th>{{ $labelPayGrade }}</th>
                                    <th>{{ $labelServiceState }}</th>
                                    <th>{{ $labelCountableYears }}</th>
                                    <th>{{ $labelLastPromotionDate }}</th>
                                    <th>{{ $labelEligiblePromotion }}</th>
                                    <th>{{ $labelAction }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $staffEmitted = [];
                                    $staffCounters = [];
                                    $staffLastPathByDepth = [];
                                    $staffRowIndex = 0;
                                @endphp

                                @forelse ($staffEmployees as $employee)
                                    @php
                                        $snapshot = $employee_snapshots[$employee->id] ?? [];
                                        $unitPath = (string) ($employee_unit_paths[$employee->id] ?? '-');
                                        $segments = $staffGetSegments($unitPath);
                                        $isDueRegular = (bool) ($snapshot['is_due_regular'] ?? false);
                                    @endphp

                                    @foreach ($segments as $depth => $segment)
                                        @php
                                            $groupKey = implode(' | ', array_slice($segments, 0, $depth + 1));
                                            if (isset($staffEmitted[$groupKey])) {
                                                continue;
                                            }

                                            $parentPath = $depth > 0 ? implode(' | ', array_slice($segments, 0, $depth)) : '';
                                            if ($depth === 0 || (($staffLastPathByDepth[$depth - 1] ?? null) !== $parentPath)) {
                                                $staffCounters[$depth] = 1;
                                            } else {
                                                $staffCounters[$depth] = ((int) ($staffCounters[$depth] ?? 0)) + 1;
                                            }

                                            $staffCounters = array_slice($staffCounters, 0, $depth + 1, true);
                                            $staffLastPathByDepth[$depth] = $groupKey;
                                            $staffLastPathByDepth = array_slice($staffLastPathByDepth, 0, $depth + 1, true);
                                            $numbering = implode('.', $staffCounters);
                                            $khNumbering = $staffToKhmerDigits($numbering);
                                            $stats = $staffGroupStats[$groupKey] ?? ['total' => 0, 'male' => 0, 'female' => 0];
                                            $indentPx = $depth * 18;
                                            $rowClass = 'employee-unit-group-row depth-' . $depth . ($depth === 0 ? ' employee-unit-main-group-row' : ' employee-unit-sub-group-row');
                                            $staffEmitted[$groupKey] = true;
                                        @endphp
                                        <tr class="{{ $rowClass }}">
                                            <td colspan="8" data-group-key="{{ $groupKey }}">
                                                <span style="display:inline-block;padding-left: {{ $indentPx }}px;">
                                                    {{ $depth > 0 ? '- ' : '' }}{{ $khNumbering }} {{ $fixKhmerText($segment) }}
                                                    <span class="ms-2 text-muted fw-normal">
                                                        ({{ localize('total', 'Total') }} {{ $stats['total'] }} |
                                                        {{ localize('male', 'Male') }} {{ $stats['male'] }} |
                                                        {{ localize('female', 'Female') }} {{ $stats['female'] }})
                                                    </span>
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach

                                    @php
                                        $stateKey = (string) ($snapshot['service_state'] ?? 'active');
                                        $groupKeysAttr = [];
                                        foreach ($segments as $depth => $_segmentName) {
                                            $groupKeysAttr[] = implode(' | ', array_slice($segments, 0, $depth + 1));
                                        }
                                    @endphp
                                    @php $staffRowIndex++; @endphp
                                    <tr class="pay-staff-employee-row"
                                        data-staff-name="{{ mb_strtolower(trim($employee->employee_id . ' ' . $fixKhmerText($employee->full_name)), 'UTF-8') }}"
                                        data-staff-state="{{ $stateKey }}"
                                        data-staff-due="{{ $isDueRegular ? '1' : '0' }}"
                                        data-group-keys="{{ implode('||', $groupKeysAttr) }}">
                                        <td>{{ $staffRowIndex }}</td>
                                        <td>{{ $employee->employee_id }} - {{ $fixKhmerText($employee->full_name) }}</td>
                                        <td>{{ $fixKhmerText($current_pay_level_labels[$employee->id] ?? '-') }}</td>
                                        <td>
                                            @if ($stateKey === 'active')
                                                <span class="badge bg-success">{{ localize('active', 'Active') }}</span>
                                            @elseif ($stateKey === 'suspended')
                                                <span class="badge bg-warning text-dark">{{ localize('suspended', 'Suspended') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ localize('inactive', 'Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) ($snapshot['countable_years'] ?? 0), 2) }}</td>
                                        <td>{{ display_date($snapshot['last_promotion_date'] ?? null) }}</td>
                                        <td>
                                            @if ($isDueRegular)
                                                <span class="badge bg-success">{{ localize('yes', 'Yes') }}</span>
                                            @else
                                                <span class="badge bg-light text-dark border">{{ localize('no', 'No') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            <a class="btn btn-sm btn-outline-primary"
                                                href="{{ route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'detail', 'employee_id' => $employee->id]) }}">
                                                {{ localize('view', 'View') }}
                                            </a>
                                            <a class="btn btn-sm btn-outline-success"
                                                href="{{ route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'form', 'employee_id' => $employee->id]) }}">
                                                {{ localize('open_form', 'Open form') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">{{ localize('empty_data', 'No data found') }}</td>
                                    </tr>
                                @endforelse
                                @if ($staffEmployees->isNotEmpty())
                                    <tr id="staffNoMatchRow" class="d-none">
                                        <td colspan="8" class="text-center text-muted">{{ localize('empty_data', 'No data found') }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade {{ $activeTab === 'detail' ? 'show active' : '' }}" id="tab-detail" role="tabpanel"
                    aria-labelledby="tab-detail-trigger">

                    @if ($detailEmployee)
                        @php
                            $detailUnit = $detailEmployee->sub_department?->department_name
                                ?: ($detailEmployee->department?->department_name ?: '-');
                        @endphp

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-2">{{ localize('basic_information', 'Basic information') }}</h6>
                                    <p class="mb-1"><strong>{{ localize('employee_code', 'Employee code') }}:</strong> {{ $detailEmployee->employee_id ?: '-' }}</p>
                                    <p class="mb-1"><strong>{{ localize('employee_name', 'Employee name') }}:</strong> {{ $fixKhmerText($detailEmployee->full_name) }}</p>
                                    <p class="mb-1"><strong>{{ localize('unit', 'Unit') }}:</strong> {{ $fixKhmerText($detailUnit) }}</p>
                                    <p class="mb-0"><strong>{{ localize('current_pay_level', 'Current pay level') }}:</strong> {{ $fixKhmerText($current_pay_level_labels[$detailEmployee->id] ?? '-') }}</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-2">{{ localize('promotion_snapshot', 'Promotion snapshot') }}</h6>
                                    <p class="mb-1"><strong>{{ $labelServiceState }}:</strong> {{ ucfirst((string) ($detailSnapshot['service_state'] ?? 'active')) }}</p>
                                    <p class="mb-1"><strong>{{ localize('anchor_date', 'Anchor date') }}:</strong> {{ display_date($detailSnapshot['anchor_date'] ?? null) }}</p>
                                    <p class="mb-1"><strong>{{ $labelCountableYears }}:</strong> {{ number_format((float) ($detailSnapshot['countable_years'] ?? 0), 2) }}</p>
                                    <p class="mb-0"><strong>{{ $labelLastPromotionDate }}:</strong> {{ display_date($detailSnapshot['last_promotion_date'] ?? null) }}</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-2">{{ localize('recommendation', 'Recommendation') }}</h6>
                                    @if ((bool) ($detailSnapshot['is_overdue_3y'] ?? false))
                                        <p class="mb-2 text-danger fw-semibold">{{ $labelOverdue3Years }}</p>
                                    @elseif ((bool) ($detailSnapshot['is_due_regular'] ?? false))
                                        <p class="mb-2 text-success fw-semibold">{{ $labelEligiblePromotion }}</p>
                                    @else
                                        <p class="mb-2 text-muted">{{ localize('not_due_yet', 'Not due yet') }}</p>
                                    @endif
                                    <div class="d-flex gap-2">
                                        <a class="btn btn-sm btn-success"
                                            href="{{ route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'form', 'employee_id' => $detailEmployee->id, 'record_mode' => 'request']) }}">
                                            {{ localize('create_request', 'Create request') }}
                                        </a>
                                        @if ($detailEmployeePendingProposals->isNotEmpty())
                                            @php
                                                $firstPendingProposal = $detailEmployeePendingProposals->first();
                                            @endphp
                                            <a class="btn btn-sm btn-primary"
                                                href="{{ route('employee-pay-promotions.review', ['proposal' => $firstPendingProposal->id, 'year' => $year]) }}">
                                                {{ app()->getLocale() === 'en' ? 'Review pending request' : 'ពិនិត្យសំណើកំពុងរង់ចាំ' }}
                                            </a>
                                        @else
                                            <a class="btn btn-sm btn-outline-secondary"
                                                href="{{ route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'approvals']) }}">
                                                {{ app()->getLocale() === 'en' ? 'Open approvals tab' : 'បើក Tab អនុម័ត' }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning mb-0">{{ localize('no_employee_selected', 'No employee selected.') }}</div>
                    @endif
                </div>

                <div class="tab-pane fade {{ $activeTab === 'form' ? 'show active' : '' }}" id="tab-form" role="tabpanel"
                    aria-labelledby="tab-form-trigger">
                    <div class="alert alert-info mb-3">
                        <strong>{{ app()->getLocale() === 'en' ? 'How to use this tab' : 'របៀបប្រើប្រាស់ Tab នេះ' }}</strong>
                        <div class="small mt-1">
                            {{ app()->getLocale() === 'en'
                                ? 'Step 1: Add one employee at a time from the form below. Step 2: Review/edit names in the batch table. Step 3: Click "Submit all requests" once.'
                                : 'ជំហានទី១៖ បន្ថែមមន្ត្រីម្នាក់ៗពី Form ខាងក្រោម។ ជំហានទី២៖ ពិនិត្យ/កែប្រែឈ្មោះក្នុងតារាងសំណើជាក្រុម។ ជំហានទី៣៖ ចុច «ដាក់សំណើទាំងអស់» ម្តងតែមួយ។' }}
                        </div>
                    </div>

                    <div id="requestFlowContainer" class="d-flex flex-column gap-3">
                    <div id="requestStepTwoSection" style="order:2;">
                    <form id="bulkRequestForm" action="{{ route('employee-pay-promotions.store') }}" method="POST" class="mb-3">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="record_mode" value="request">
                        <input type="hidden" name="_active_tab" value="form">
                        <input type="hidden" name="bulk_items" id="bulkItemsInput" value="">
                        <input type="hidden" name="bulk_removed_items" id="bulkRemovedItemsInput" value="">

                        <div class="card border mb-0">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <strong>{{ app()->getLocale() === 'en' ? 'Batch request table' : 'តារាងត្រៀមដាក់សំណើជាក្រុម' }}</strong>
                                    <div class="small text-muted">
                                        {{ app()->getLocale() === 'en'
                                            ? 'Review, update target level, remove with reason, then submit once.'
                                            : 'ពិនិត្យ/កែប្រែថ្នាក់ត្រូវឡើង និងដកចេញដោយបញ្ជាក់មូលហេតុ រួចដាក់សំណើម្តងតែមួយ។' }}
                                    </div>
                                    <div class="small text-muted">
                                        {{ app()->getLocale() === 'en'
                                            ? 'After successful submit, names are moved to the Requests/Approvals workflow and removed from this draft table.'
                                            : 'បន្ទាប់ពីដាក់សំណើជោគជ័យ ឈ្មោះនឹងផ្លាស់ទៅលំហូរ Tab សំណើ/ការអនុម័ត ហើយមិនបង្ហាញនៅតារាង Draft នេះទៀតទេ។' }}
                                    </div>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="badge bg-primary" id="bulkCandidateCountBadge">{{ $autoPromotionCandidates->count() }} {{ app()->getLocale() === 'en' ? 'names' : 'ឈ្មោះ' }}</span>
                                    <button type="button" id="bulkSubmitBtn" class="btn btn-sm btn-success">
                                        <i class="fa fa-paper-plane me-1"></i>{{ app()->getLocale() === 'en' ? 'Submit all requests' : 'ដាក់សំណើទាំងអស់' }}
                                    </button>
                                </div>
                            </div>

                            <div class="card-body border-bottom bg-light py-2">
                                <div class="small text-muted">
                                    {{ app()->getLocale() === 'en'
                                        ? 'Add names from the single form below. This table is for final review before bulk submit.'
                                        : 'សូមបន្ថែមឈ្មោះពី Form ខាងក្រោម។ តារាងនេះសម្រាប់ពិនិត្យចុងក្រោយមុនដាក់សំណើជាក្រុម។' }}
                                </div>
                            </div>

                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table id="pay-promotion-candidate-table" class="table table-sm table-bordered table-striped mb-0 align-middle">
                                        <thead>
                                            <tr>
                                                <th width="4%">#</th>
                                                <th>{{ localize('employee', 'Employee') }}</th>
                                                <th>{{ app()->getLocale() === 'en' ? 'Reason' : 'ហេតុផលស្នើដំឡើង' }}</th>
                                                <th>{{ $labelLastPromotionDate }}</th>
                                                <th>{{ app()->getLocale() === 'en' ? 'Current level' : 'ថ្នាក់បច្ចុប្បន្ន' }}</th>
                                                <th>{{ app()->getLocale() === 'en' ? 'Target level' : 'ថ្នាក់ត្រូវឡើង' }}</th>
                                                <th width="10%">{{ app()->getLocale() === 'en' ? 'Status' : 'ស្ថានភាពជួរ' }}</th>
                                                <th width="12%">{{ $labelAction }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bulkCandidateTableBody">
                                            @php
                                                $candidateEmitted = [];
                                                $candidateCounters = [];
                                                $candidateLastPathByDepth = [];
                                                $candidateRowIndex = 0;
                                            @endphp
                                            @forelse ($autoPromotionCandidates as $candidate)
                                                @php
                                                    $candidateSegments = $staffGetSegments((string) ($candidate['unit_path'] ?? ''));
                                                    $candidateEmployeeId = (int) ($candidate['employee_id'] ?? 0);
                                                    $candidateCurrentLevelId = (int) ($candidate['current_pay_level_id'] ?? 0);
                                                    $candidateNextLevelId = (int) ($candidate['next_pay_level_id'] ?? 0);
                                                    $candidatePromotionType = (string) ($candidate['reason_code'] ?? 'annual_grade');
                                                    $candidateEffectiveDate = $cutoffDateIso;
                                                    $candidateTargetOptions = collect($candidateTargetOptionsByCurrent[$candidateCurrentLevelId] ?? []);
                                                    if ($candidateTargetOptions->isEmpty() && $candidateNextLevelId > 0 && $payLevelById->has($candidateNextLevelId)) {
                                                        $candidateTargetOptions = collect([$payLevelById->get($candidateNextLevelId)]);
                                                    }
                                                    $selectedTargetLevelId = $candidateNextLevelId > 0
                                                        ? $candidateNextLevelId
                                                        : (int) optional($candidateTargetOptions->first())->id;
                                                    $candidateSelectId = 'candidate_target_level_' . ($loop->index + 1);
                                                    $candidateReasonText = $candidateReasonLabel((array) $candidate);
                                                @endphp

                                                @foreach ($candidateSegments as $depth => $segment)
                                                    @php
                                                        $groupKey = implode(' | ', array_slice($candidateSegments, 0, $depth + 1));
                                                        if (isset($candidateEmitted[$groupKey])) {
                                                            continue;
                                                        }

                                                        $parentPath = $depth > 0 ? implode(' | ', array_slice($candidateSegments, 0, $depth)) : '';
                                                        if ($depth === 0 || (($candidateLastPathByDepth[$depth - 1] ?? null) !== $parentPath)) {
                                                            $candidateCounters[$depth] = 1;
                                                        } else {
                                                            $candidateCounters[$depth] = ((int) ($candidateCounters[$depth] ?? 0)) + 1;
                                                        }

                                                        $candidateCounters = array_slice($candidateCounters, 0, $depth + 1, true);
                                                        $candidateLastPathByDepth[$depth] = $groupKey;
                                                        $candidateLastPathByDepth = array_slice($candidateLastPathByDepth, 0, $depth + 1, true);
                                                        $numbering = implode('.', $candidateCounters);
                                                        $khNumbering = $staffToKhmerDigits($numbering);
                                                        $stats = $candidateGroupStats[$groupKey] ?? ['total' => 0];
                                                        $indentPx = $depth * 18;
                                                        $rowClass = 'employee-unit-group-row depth-' . $depth . ($depth === 0 ? ' employee-unit-main-group-row' : ' employee-unit-sub-group-row');
                                                        $candidateEmitted[$groupKey] = true;
                                                    @endphp
                                                    <tr class="{{ $rowClass }}">
                                                        <td colspan="8" data-group-key="{{ $groupKey }}">
                                                            <span style="display:inline-block;padding-left: {{ $indentPx }}px;">
                                                                {{ $depth > 0 ? '- ' : '' }}{{ $khNumbering }} {{ $fixKhmerText($segment) }}
                                                                <span class="ms-2 text-muted fw-normal">
                                                                    ({{ localize('total', 'Total') }} {{ (int) ($stats['total'] ?? 0) }})
                                                                </span>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endforeach

                                                @php $candidateRowIndex++; @endphp
                                                <tr class="candidate-batch-row {{ !empty($candidate['can_request']) ? '' : 'table-warning' }}"
                                                    data-employee-id="{{ $candidateEmployeeId }}"
                                                    data-employee-name="{{ $fixKhmerText($candidate['full_name'] ?? '-') }}"
                                                    data-promotion-type="{{ $candidatePromotionType }}"
                                                    data-effective-date="{{ $candidateEffectiveDate }}"
                                                    data-row-status="{{ !empty($candidate['can_request']) ? 'auto' : 'review' }}"
                                                    data-note="">
                                                    <td class="candidate-row-no">{{ $candidateRowIndex }}</td>
                                                    <td>
                                                        {{ $candidate['employee_code'] ?? '-' }} -
                                                        {{ $fixKhmerText($candidate['full_name'] ?? '-') }}
                                                    </td>
                                                    <td class="candidate-reason-text">{{ $candidateReasonText }}</td>
                                                    <td>{{ display_date($candidate['last_promotion_date'] ?? null) }}</td>
                                                    <td>{{ $fixKhmerText($candidate['current_pay_level_label'] ?? '-') }}</td>
                                                    <td>
                                                        @if ($candidateTargetOptions->isNotEmpty())
                                                            <select id="{{ $candidateSelectId }}" class="form-select form-select-sm candidate-target-level">
                                                                @foreach ($candidateTargetOptions as $targetLevel)
                                                                    @php
                                                                        $targetLabel = $fixPayLevelKm($targetLevel->level_name_km ?? '');
                                                                        $targetLabel = $targetLabel !== '' ? $targetLabel : (string) ($targetLevel->level_code ?? '-');
                                                                    @endphp
                                                                    <option value="{{ (int) $targetLevel->id }}" {{ (int) $selectedTargetLevelId === (int) $targetLevel->id ? 'selected' : '' }}>
                                                                        {{ $targetLabel }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        @else
                                                            <span class="text-muted small">{{ app()->getLocale() === 'en' ? 'No next level' : 'មិនមានថ្នាក់បន្ទាប់' }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="candidate-row-status">
                                                        @if (!empty($candidate['can_request']))
                                                            <span class="badge bg-info text-dark">{{ app()->getLocale() === 'en' ? 'Auto' : 'ស្វ័យប្រវត្ត' }}</span>
                                                        @else
                                                            <span class="badge bg-secondary">{{ app()->getLocale() === 'en' ? 'Needs review' : 'ត្រូវពិនិត្យ' }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-danger candidate-remove-btn">
                                                            {{ app()->getLocale() === 'en' ? 'Remove' : 'ដកចេញ' }}
                                                        </button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr id="bulkEmptyRow">
                                                    <td colspan="8" class="text-center text-muted">
                                                        {{ app()->getLocale() === 'en'
                                                            ? 'No auto-generated candidates for this year/cutoff.'
                                                            : 'មិនមានបេក្ខជនស្វ័យប្រវត្តសម្រាប់ឆ្នាំ/ថ្ងៃកាត់នេះទេ។' }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </form>
                    </div>

                    <div id="requestStepOneSection" style="order:1;">
                    <div class="alert alert-light border mb-3">
                        <strong>{{ app()->getLocale() === 'en' ? 'Form purpose' : 'គោលបំណង Form' }}:</strong>
                        {{ app()->getLocale() === 'en'
                            ? 'Create proposal first, then process approval from the Approval tab.'
                            : 'សម្រាប់បង្កើតសំណើជាមុន ហើយដំណើរការអនុម័តនៅ Tab ការអនុម័ត។' }}
                        <div class="small mt-1 text-muted">
                            {{ app()->getLocale() === 'en'
                                ? 'Supported request basis: annual grade, annual rank, degree-based, and honorary pre-retirement.'
                                : 'គាំទ្រមូលដ្ឋានសំណើ ៤ ប្រភេទ៖ ដំឡើងថ្នាក់ប្រចាំឆ្នាំ, ដំឡើងឋានន្តរស័ក្តិប្រចាំឆ្នាំ, តាមសញ្ញាបត្រ, និងដំឡើងថ្នាក់កិត្តិយសមុននិវត្តន៍។' }}
                        </div>
                        <div class="small mt-1 text-muted">
                            {{ app()->getLocale() === 'en'
                                ? 'Rule: one pending request per employee. If pending exists, create form will block duplicate.'
                                : 'គោលការណ៍៖ មន្ត្រីម្នាក់មានសំណើកំពុងរង់ចាំបានតែ ១ ប៉ុណ្ណោះ។ បើមាន pending រួច Form នឹងទប់ស្កាត់មិនអោយស្នើស្ទួន។' }}
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                        <h6 class="mb-0">
                            {{ app()->getLocale() === 'en' ? 'Step 1: Add one employee to batch table' : 'ជំហានទី១៖ បន្ថែមមន្ត្រីម្នាក់ទៅតារាងសំណើជាក្រុម' }}
                        </h6>
                        <small class="text-muted">
                            {{ app()->getLocale() === 'en' ? 'After each add, review rows above and submit all once.' : 'បន្ថែមរួច សូមពិនិត្យតារាងខាងលើ ហើយដាក់សំណើទាំងអស់ម្តងតែមួយ។' }}
                        </small>
                    </div>

                    <form id="singlePromotionForm" action="{{ route('employee-pay-promotions.store') }}" method="POST" class="mb-0">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="_active_tab" value="form">
                        <input type="hidden" name="proposal_id" value="{{ $prefillProposalId > 0 ? $prefillProposalId : '' }}">
                        <input type="hidden" id="record_mode" name="record_mode" value="{{ $prefillRecordMode }}">

                        @if ($prefillProposal)
                            <div class="alert alert-warning mb-3 py-2">
                                {{ app()->getLocale() === 'en'
                                    ? 'Reviewing pending request'
                                    : 'កំពុងពិនិត្យសំណើកំពុងរង់ចាំ' }}
                                <strong>#{{ $prefillProposal->id }}</strong>
                                -
                                {{ app()->getLocale() === 'en' ? 'Employee' : 'មន្ត្រី' }}:
                                <strong>{{ $prefillProposal->employee?->employee_id }} - {{ $fixKhmerText($prefillProposal->employee?->full_name ?? '-') }}</strong>
                            </div>
                        @endif

                        <div class="row g-3 mb-2">
                            <div class="col-md-8">
                                <label class="form-label">{{ localize('select_employee', 'Select employee') }}</label>
                                <select id="single_employee_id" name="employee_id" class="form-select">
                                    <option value="">-- {{ localize('select_employee', 'Select employee') }} --</option>
                                    @foreach ($employees as $employee)
                                        @php $employeeSnapshot = $employee_snapshots[$employee->id] ?? []; @endphp
                                        <option value="{{ $employee->id }}"
                                            data-current-level="{{ $fixKhmerText($current_pay_level_labels[$employee->id] ?? ($employee->employee_grade ?? '-')) }}"
                                            data-due-regular="{{ !empty($employeeSnapshot['is_due_regular']) ? '1' : '0' }}"
                                            data-due-honorary="{{ !empty($employeeSnapshot['is_due_honorary_pre_retirement']) ? '1' : '0' }}"
                                            data-service-state="{{ (string) ($employeeSnapshot['service_state'] ?? 'active') }}"
                                            data-countable-years="{{ (string) ($employeeSnapshot['countable_years'] ?? '0') }}"
                                            data-last-promotion="{{ display_date($employeeSnapshot['last_promotion_date'] ?? null) }}"
                                            data-has-pending="{{ isset($pendingProposalEmployeeSet[(int) $employee->id]) ? '1' : '0' }}"
                                            {{ (int) $prefillEmployeeId === (int) $employee->id ? 'selected' : '' }}>
                                            {{ $employee->employee_id }} - {{ $fixKhmerText($employee->full_name) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">{{ localize('current_pay_level', 'Current pay level') }}</label>
                                <input type="text" id="old_pay_level_label" class="form-control bg-light" readonly value="-">
                            </div>
                        </div>

                        <div class="row g-3 mb-2">
                            <div class="col-md-4">
                                <label class="form-label">{{ localize('new_pay_level', 'New pay level') }} <span class="text-danger">*</span></label>
                                <select id="new_pay_level_id" name="pay_level_id" class="form-select" required>
                                    <option value="">-- {{ localize('select_one', 'Select one') }} --</option>
                                    @foreach ($pay_levels as $pay_level)
                                        @php
                                            $levelNameKm = $fixPayLevelKm($pay_level->level_name_km);
                                            $levelLabel = $levelNameKm !== '' ? $levelNameKm : $pay_level->level_code;
                                        @endphp
                                        <option value="{{ $pay_level->id }}" data-level-label="{{ $levelLabel }}" {{ (int) $prefillPayLevelId === (int) $pay_level->id ? 'selected' : '' }}>
                                            {{ $levelLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">{{ localize('effective_date', 'Effective date') }} <span class="text-danger">*</span></label>
                                <input type="date" id="single_effective_date" name="effective_date" class="form-control" value="{{ $prefillEffectiveDate }}" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">{{ app()->getLocale() === 'en' ? 'Request basis' : 'មូលដ្ឋានសំណើ' }} <span class="text-danger">*</span></label>
                                <select id="promotion_type" name="promotion_type" class="form-select" required>
                                    <option value="annual_grade" {{ $prefillPromotionType === 'annual_grade' ? 'selected' : '' }}>
                                        {{ app()->getLocale() === 'en' ? 'Annual grade promotion' : 'ដំឡើងថ្នាក់ប្រចាំឆ្នាំ' }}
                                    </option>
                                    <option value="annual_rank" {{ $prefillPromotionType === 'annual_rank' ? 'selected' : '' }}>
                                        {{ app()->getLocale() === 'en' ? 'Annual rank promotion' : 'ដំឡើងឋានន្តរស័ក្តិប្រចាំឆ្នាំ' }}
                                    </option>
                                    <option value="degree_based" {{ $prefillPromotionType === 'degree_based' ? 'selected' : '' }}>
                                        {{ app()->getLocale() === 'en' ? 'By degree' : 'តាមសញ្ញាបត្រ' }}
                                    </option>
                                    <option value="honorary_pre_retirement" {{ $prefillPromotionType === 'honorary_pre_retirement' ? 'selected' : '' }}>
                                        {{ app()->getLocale() === 'en' ? 'Honorary pre-retirement' : 'ដំឡើងថ្នាក់កិត្តិយសមុននិវត្តន៍' }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-4" id="request_reference_wrap" style="display: none;">
                                <label class="form-label">{{ app()->getLocale() === 'en' ? 'Proposal reference no.' : 'លេខយោងសំណើ' }}</label>
                                <input type="text" name="request_reference" class="form-control" value="{{ $prefillRequestReference }}">
                            </div>

                            <div class="col-md-4" id="request_date_wrap" style="display: none;">
                                <label class="form-label">{{ app()->getLocale() === 'en' ? 'Proposal date' : 'កាលបរិច្ឆេទសំណើ' }}</label>
                                <input type="date" name="request_date" class="form-control" value="{{ $prefillRequestDate }}">
                            </div>

                            <div class="col-md-4" id="document_reference_wrap" style="{{ $prefillRecordMode === 'request' ? 'display:none;' : '' }}">
                                <label class="form-label">{{ localize('document_reference', 'Document reference') }}</label>
                                <input type="text" id="document_reference" name="document_reference" class="form-control" value="{{ $prefillDocumentReference }}">
                            </div>

                            <div class="col-md-4" id="document_date_wrap" style="{{ $prefillRecordMode === 'request' ? 'display:none;' : '' }}">
                                <label class="form-label">{{ localize('document_date', 'Document date') }}</label>
                                <input type="date" id="document_date" name="document_date" class="form-control" value="{{ $prefillDocumentDate }}">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">{{ localize('note', 'Note') }}</label>
                                <input type="text" id="single_note" name="note" class="form-control" value="{{ $prefillNote }}">
                            </div>
                            <div class="col-12" id="employeeEligibilityHint"></div>
                        </div>

                        <div class="text-end d-flex flex-wrap justify-content-end gap-2">
                            @if ($prefillRecordMode === 'request')
                                <button type="button" id="singleSubmitBtn" class="btn btn-primary">
                                    <i class="fa fa-plus me-1"></i>{{ app()->getLocale() === 'en' ? 'Add to batch table' : 'បន្ថែមចូលតារាងសំណើ' }}
                                </button>
                            @else
                                <button type="button" id="singleSubmitBtn" class="btn btn-success">
                                    <i class="fa fa-save me-1"></i>{{ localize('save', 'Save') }}
                                </button>
                            @endif
                        </div>
                    </form>
                    @if ($prefillRecordMode === 'request')
                        <div class="small text-muted mt-2">
                            {{ app()->getLocale() === 'en'
                                ? 'Use this form to add one employee at a time into the batch table, then click "Submit all requests" above.'
                                : 'ប្រើ Form នេះសម្រាប់បញ្ចូលមន្ត្រីម្នាក់ៗចូលតារាងសំណើជាក្រុម បន្ទាប់មកចុច «ដាក់សំណើទាំងអស់» ខាងលើ។' }}
                        </div>
                    @endif
                    </div>
                    </div>
                </div>

                <div class="tab-pane fade {{ $activeTab === 'history' ? 'show active' : '' }}" id="tab-history" role="tabpanel" aria-labelledby="tab-history-trigger">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle" id="historyTable">
                            <thead>
                                <tr>
                                    <th width="5%">{{ localize('sl', '#') }}</th>
                                    <th>{{ localize('employee', 'Employee') }}</th>
                                    <th>{{ localize('department', 'Unit') }}</th>
                                    <th>{{ $labelPayGrade }}</th>
                                    <th>{{ app()->getLocale() === 'en' ? 'Request basis' : 'មូលដ្ឋានសំណើ' }}</th>
                                    <th>{{ localize('effective_date', 'Effective date') }}</th>
                                    <th>{{ localize('document_reference', 'Document reference') }}</th>
                                    <th>{{ localize('status', 'Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($promotions as $promotion)
                                    @php
                                        $unit = $promotion->employee?->sub_department?->department_name ?: ($promotion->employee?->department?->department_name ?: '-');
                                        $rowLevelNameKm = $fixPayLevelKm($promotion->payLevel?->level_name_km ?? '');
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $promotion->employee?->employee_id }} - {{ $fixKhmerText($promotion->employee?->full_name ?? '-') }}</td>
                                        <td>{{ $fixKhmerText($unit) }}</td>
                                        <td>{{ $rowLevelNameKm !== '' ? $rowLevelNameKm : ($promotion->payLevel?->level_code ?? '-') }}</td>
                                        <td>{{ $promotionTypeLabel($promotion->promotion_type) }}</td>
                                        <td>{{ display_date($promotion->start_date) }}</td>
                                        <td>{{ $promotion->document_reference ?: '-' }}</td>
                                        <td>
                                            @if ($promotion->status === 'active')
                                                <span class="badge bg-success">{{ localize('active', 'Active') }}</span>
                                            @elseif ($promotion->status === 'proposed')
                                                <span class="badge bg-warning text-dark">{{ localize('proposed', 'Proposed') }}</span>
                                            @elseif ($promotion->status === 'recommended')
                                                <span class="badge bg-info text-white">{{ localize('recommended', 'Recommended') }}</span>
                                            @elseif ($promotion->status === 'approved')
                                                <span class="badge bg-primary">{{ localize('approved', 'Approved') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ localize('inactive', 'Inactive') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center text-muted">{{ localize('empty_data', 'No data found') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade {{ $activeTab === 'requests' ? 'show active' : '' }}" id="tab-requests" role="tabpanel" aria-labelledby="tab-requests-trigger">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ localize('employee', 'Employee') }}</th>
                                    <th>{{ localize('department', 'Unit') }}</th>
                                    <th>{{ $labelPayGrade }}</th>
                                    <th>{{ app()->getLocale() === 'en' ? 'Request basis' : 'មូលដ្ឋានសំណើ' }}</th>
                                    <th>{{ localize('requested_effective_date', 'Requested effective date') }}</th>
                                    <th>{{ localize('request_reference', 'Request reference') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pendingProposals as $proposal)
                                    @php
                                        $proposalUnit = $proposal->employee?->sub_department?->department_name ?: ($proposal->employee?->department?->department_name ?: '-');
                                        $proposalPayLevelKm = $fixPayLevelKm($proposal->payLevel?->level_name_km ?? '');
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $proposal->employee?->employee_id }} - {{ $fixKhmerText($proposal->employee?->full_name ?? '-') }}</td>
                                        <td>{{ $fixKhmerText($proposalUnit) }}</td>
                                        <td>{{ $proposalPayLevelKm !== '' ? $proposalPayLevelKm : ($proposal->payLevel?->level_code ?? '-') }}</td>
                                        <td>{{ $promotionTypeLabel($proposal->promotion_type) }}</td>
                                        <td>{{ display_date($proposal->start_date) }}</td>
                                        <td>{{ $proposal->document_reference ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted">{{ localize('no_pending_proposals', 'No pending proposals') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade {{ $activeTab === 'approvals' ? 'show active' : '' }}" id="tab-approvals" role="tabpanel" aria-labelledby="tab-approvals-trigger">
                    <div class="alert alert-light border mb-3">
                        <strong>{{ app()->getLocale() === 'en' ? 'Approval tab purpose' : 'គោលបំណង Tab ការអនុម័ត' }}:</strong>
                        {{ app()->getLocale() === 'en'
                            ? 'Review pending proposals, verify supporting documents, then recommend/approve/reject each case.'
                            : 'សម្រាប់ពិនិត្យសំណើកំពុងរង់ចាំ ផ្ទៀងផ្ទាត់ឯកសារ ហើយផ្តល់យោបល់/អនុម័ត/មិនអនុម័តតាមករណី។' }}
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <div class="alert alert-info py-2 mb-0">
                                {{ app()->getLocale() === 'en' ? 'Can recommend' : 'អាចផ្តល់យោបល់' }}:
                                <strong>{{ $approvalCanRecommendCount }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="alert alert-success py-2 mb-0">
                                {{ app()->getLocale() === 'en' ? 'Can approve' : 'អាចអនុម័ត' }}:
                                <strong>{{ $approvalCanApproveCount }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="alert alert-warning py-2 mb-0">
                                {{ app()->getLocale() === 'en' ? 'Can reject' : 'អាចបដិសេធ' }}:
                                <strong>{{ $approvalCanRejectCount }}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="alert alert-secondary py-2 mb-0">
                                {{ app()->getLocale() === 'en' ? 'Blocked by policy' : 'ត្រូវបានរារាំងតាមគោលនយោបាយ' }}:
                                <strong>{{ $approvalBlockedCount }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-12">
                            <form id="approvalBatchForm" action="{{ route('employee-pay-promotions.batch-action') }}" method="POST">
                                @csrf
                                <input type="hidden" name="year" value="{{ $year }}">
                                <div class="border rounded p-2 mb-2 bg-light">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-3">
                                            <label for="approvalFilterKeyword" class="form-label mb-1">{{ app()->getLocale() === 'en' ? 'Search' : 'ស្វែងរក' }}</label>
                                            <input type="text" id="approvalFilterKeyword" class="form-control form-control-sm"
                                                placeholder="{{ app()->getLocale() === 'en' ? 'Employee code, name, request reference...' : 'លេខកូដមន្ត្រី ឈ្មោះ លេខយោងសំណើ...' }}">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="approvalFilterType" class="form-label mb-1">{{ app()->getLocale() === 'en' ? 'Request basis' : 'មូលដ្ឋានសំណើ' }}</label>
                                            <select id="approvalFilterType" class="form-select form-select-sm">
                                                <option value="">{{ app()->getLocale() === 'en' ? 'All request basis' : 'មូលដ្ឋានសំណើទាំងអស់' }}</option>
                                                <option value="annual_grade">{{ app()->getLocale() === 'en' ? 'Annual grade promotion' : 'ដំឡើងថ្នាក់ប្រចាំឆ្នាំ' }}</option>
                                                <option value="annual_rank">{{ app()->getLocale() === 'en' ? 'Annual rank promotion' : 'ដំឡើងឋានន្តរស័ក្តិប្រចាំឆ្នាំ' }}</option>
                                                <option value="degree_based">{{ app()->getLocale() === 'en' ? 'By degree' : 'តាមសញ្ញាបត្រ' }}</option>
                                                <option value="honorary_pre_retirement">{{ app()->getLocale() === 'en' ? 'Honorary pre-retirement' : 'ដំឡើងថ្នាក់កិត្តិយសមុននិវត្តន៍' }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="approvalFilterStage" class="form-label mb-1">{{ app()->getLocale() === 'en' ? 'Stage' : 'ជំហាន' }}</label>
                                            <select id="approvalFilterStage" class="form-select form-select-sm">
                                                <option value="">{{ app()->getLocale() === 'en' ? 'All stages' : 'ជំហានទាំងអស់' }}</option>
                                                <option value="proposed">{{ app()->getLocale() === 'en' ? 'Proposed' : 'កំពុងស្នើ' }}</option>
                                                <option value="recommended">{{ app()->getLocale() === 'en' ? 'Recommended' : 'បានផ្តល់យោបល់' }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="approvalFilterPermission" class="form-label mb-1">{{ app()->getLocale() === 'en' ? 'Permission' : 'សិទ្ធិ' }}</label>
                                            <select id="approvalFilterPermission" class="form-select form-select-sm">
                                                <option value="">{{ app()->getLocale() === 'en' ? 'All permissions' : 'សិទ្ធិទាំងអស់' }}</option>
                                                <option value="actionable">{{ app()->getLocale() === 'en' ? 'Actionable' : 'អាចអនុវត្តបាន' }}</option>
                                                <option value="blocked">{{ app()->getLocale() === 'en' ? 'Blocked' : 'ត្រូវបានរារាំង' }}</option>
                                                <option value="recommend">{{ app()->getLocale() === 'en' ? 'Can recommend' : 'អាចផ្តល់យោបល់' }}</option>
                                                <option value="approve">{{ app()->getLocale() === 'en' ? 'Can approve' : 'អាចអនុម័ត' }}</option>
                                                <option value="reject">{{ app()->getLocale() === 'en' ? 'Can reject' : 'អាចបដិសេធ' }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="approvalBatchNote" class="form-label mb-1">{{ app()->getLocale() === 'en' ? 'Remark' : 'មូលហេតុ/កំណត់សម្គាល់' }}</label>
                                            <input type="text" id="approvalBatchNote" name="note" class="form-control form-control-sm"
                                                value="{{ old('note') }}"
                                                placeholder="{{ app()->getLocale() === 'en' ? 'Required for reject' : 'ចាំបាច់ពេលបដិសេធ' }}">
                                        </div>
                                        <div class="col-md-1 text-md-end">
                                            <small id="approvalFilterResult" class="d-block text-muted"></small>
                                            <small id="approvalSelectedCount" class="d-block text-primary"></small>
                                        </div>
                                    </div>
                                    <div class="row g-2 align-items-end mt-1">
                                        <div class="col-md-4">
                                            <label for="approvalBatchDocumentReference" class="form-label mb-1">
                                                {{ app()->getLocale() === 'en' ? 'Approval document reference' : 'លេខលិខិតយោង (អនុម័តជាក្រុម)' }}
                                            </label>
                                            <input type="text"
                                                id="approvalBatchDocumentReference"
                                                name="batch_document_reference"
                                                class="form-control form-control-sm @error('batch_document_reference') is-invalid @enderror"
                                                value="{{ old('batch_document_reference') }}"
                                                placeholder="{{ app()->getLocale() === 'en' ? 'Example: ១២៣/២៦ សជណ' : 'ឧ. ១២៣/២៦ សជណ' }}">
                                            @error('batch_document_reference')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label for="approvalBatchDocumentDate" class="form-label mb-1">
                                                {{ app()->getLocale() === 'en' ? 'Approval document date' : 'កាលបរិច្ឆេទលិខិតអនុម័ត' }}
                                            </label>
                                            <input type="date"
                                                id="approvalBatchDocumentDate"
                                                name="batch_document_date"
                                                class="form-control form-control-sm @error('batch_document_date') is-invalid @enderror"
                                                value="{{ old('batch_document_date') }}">
                                            @error('batch_document_date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-5">
                                            <small class="text-muted d-block">
                                                {{ app()->getLocale() === 'en'
                                                    ? 'One approval letter can be applied to all selected employees.'
                                                    : 'លិខិតអនុម័ត ១ អាចអនុវត្តបានសម្រាប់មន្ត្រីដែលបានជ្រើសទាំងអស់។' }}
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" id="approvalSelectAllVisible">
                                        <label class="form-check-label" for="approvalSelectAllVisible">
                                            {{ app()->getLocale() === 'en' ? 'Select all visible rows' : 'ជ្រើសទាំងអស់ (ជួរដែលកំពុងបង្ហាញ)' }}
                                        </label>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="batch_action" value="recommend" id="approvalBatchRecommendBtn" class="btn btn-info btn-sm text-white" disabled>
                                            <i class="fa fa-commenting me-1"></i>{{ app()->getLocale() === 'en' ? 'Recommend selected' : 'ផ្តល់យោបល់ឈ្មោះដែលបានជ្រើស' }}
                                        </button>
                                        <button type="submit" name="batch_action" value="approve" id="approvalBatchApproveBtn" class="btn btn-success btn-sm" disabled>
                                            <i class="fa fa-check me-1"></i>{{ app()->getLocale() === 'en' ? 'Approve selected' : 'អនុម័តឈ្មោះដែលបានជ្រើស' }}
                                        </button>
                                        <button type="submit" name="batch_action" value="reject" id="approvalBatchRejectBtn" class="btn btn-danger btn-sm" disabled>
                                            <i class="fa fa-times me-1"></i>{{ app()->getLocale() === 'en' ? 'Reject selected' : 'បដិសេធឈ្មោះដែលបានជ្រើស' }}
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table id="approvalTable" class="table table-bordered table-striped align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th width="4%"></th>
                                                <th width="6%">#</th>
                                                <th>{{ localize('employee', 'Employee') }}</th>
                                                <th>{{ app()->getLocale() === 'en' ? 'Request basis' : 'មូលដ្ឋានសំណើ' }}</th>
                                                <th>{{ app()->getLocale() === 'en' ? 'Current level' : 'ថ្នាក់បច្ចុប្បន្ន' }}</th>
                                                <th>{{ app()->getLocale() === 'en' ? 'Target level' : 'ថ្នាក់ត្រូវឡើង' }}</th>
                                                <th>{{ localize('requested_effective_date', 'Requested effective date') }}</th>
                                                <th>{{ localize('request_reference', 'Request reference') }}</th>
                                                <th>{{ localize('status', 'Status') }}</th>
                                                <th>{{ app()->getLocale() === 'en' ? 'Approval level' : 'កម្រិតអនុម័ត' }}</th>
                                                <th>{{ app()->getLocale() === 'en' ? 'Your permission' : 'សិទ្ធិរបស់អ្នក' }}</th>
                                                <th width="14%">{{ $labelAction }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($pendingProposals as $proposal)
                                                @php
                                                    $approvalCurrentPayLevel = $fixKhmerText($current_pay_level_labels[$proposal->employee_id] ?? ($proposal->employee?->employee_grade ?? '-'));
                                                    $approvalTargetPayLevelKm = $fixPayLevelKm($proposal->payLevel?->level_name_km ?? '');
                                                    $approvalTargetPayLevel = $approvalTargetPayLevelKm !== '' ? $approvalTargetPayLevelKm : ($proposal->payLevel?->level_code ?? '-');
                                                    $approvalStage = (string) ($proposal->status ?? 'proposed');
                                                    $isRecommendedStage = $approvalStage === 'recommended';
                                                    $isSpecialBasis = in_array((string) ($proposal->promotion_type ?? ''), ['degree_based', 'honorary_pre_retirement', 'special_request', 'special_case'], true);
                                                    $rowPermission = (array) ($proposalActionPermissions[(int) ($proposal->id ?? 0)] ?? []);
                                                    $canRecommend = (bool) ($rowPermission['can_recommend'] ?? false);
                                                    $canApprove = (bool) ($rowPermission['can_approve'] ?? false);
                                                    $canReject = (bool) ($rowPermission['can_reject'] ?? false);
                                                    $canAnyAction = (bool) ($rowPermission['can_any'] ?? ($canRecommend || $canApprove || $canReject));
                                                    $recommendReason = trim((string) ($rowPermission['recommend_reason'] ?? ''));
                                                    $approveReason = trim((string) ($rowPermission['approve_reason'] ?? ''));
                                                    $rejectReason = trim((string) ($rowPermission['reject_reason'] ?? ''));
                                                    $blockedMessage = trim((string) ($rowPermission['blocked_message'] ?? ''));
                                                    $permissionHint = trim(implode(' | ', array_filter([
                                                        !$canRecommend && $recommendReason !== ''
                                                            ? ((app()->getLocale() === 'en' ? 'Recommend' : 'ផ្តល់យោបល់') . ': ' . $recommendReason)
                                                            : '',
                                                        !$canApprove && $approveReason !== ''
                                                            ? ((app()->getLocale() === 'en' ? 'Approve' : 'អនុម័ត') . ': ' . $approveReason)
                                                            : '',
                                                        !$canReject && $rejectReason !== ''
                                                            ? ((app()->getLocale() === 'en' ? 'Reject' : 'បដិសេធ') . ': ' . $rejectReason)
                                                            : '',
                                                    ])));
                                                    $approvalSearchText = mb_strtolower(trim(implode(' ', [
                                                        (string) ($proposal->employee?->employee_id ?? ''),
                                                        $fixKhmerText((string) ($proposal->employee?->full_name ?? '')),
                                                        (string) ($proposal->document_reference ?? ''),
                                                        (string) $approvalCurrentPayLevel,
                                                        (string) $approvalTargetPayLevel,
                                                    ])), 'UTF-8');
                                                @endphp
                                                <tr class="approval-row"
                                                    data-approval-type="{{ (string) ($proposal->promotion_type ?? '') }}"
                                                    data-approval-stage="{{ $approvalStage }}"
                                                    data-can-recommend="{{ $canRecommend ? '1' : '0' }}"
                                                    data-can-approve="{{ $canApprove ? '1' : '0' }}"
                                                    data-can-reject="{{ $canReject ? '1' : '0' }}"
                                                    data-permission-state="{{ $canAnyAction ? 'actionable' : 'blocked' }}"
                                                    data-approval-search="{{ $approvalSearchText }}">
                                                    <td>
                                                        <input class="form-check-input approval-select-item" type="checkbox"
                                                            name="proposal_ids[]" value="{{ $proposal->id }}"
                                                            {{ $canAnyAction ? '' : 'disabled' }}
                                                            title="{{ $canAnyAction ? '' : ($blockedMessage !== '' ? $blockedMessage : (app()->getLocale() === 'en' ? 'You do not have permission for this request.' : 'អ្នកមិនមានសិទ្ធិសម្រាប់សំណើនេះទេ។')) }}">
                                                    </td>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>{{ $proposal->employee?->employee_id }} - {{ $fixKhmerText($proposal->employee?->full_name ?? '-') }}</td>
                                                    <td>{{ $promotionTypeLabel($proposal->promotion_type) }}</td>
                                                    <td>{{ $approvalCurrentPayLevel }}</td>
                                                    <td>{{ $approvalTargetPayLevel }}</td>
                                                    <td>{{ display_date($proposal->start_date) }}</td>
                                                    <td>{{ $proposal->document_reference ?: '-' }}</td>
                                                    <td>
                                                        @if ($isRecommendedStage)
                                                            <span class="badge bg-info text-white">{{ app()->getLocale() === 'en' ? 'Recommended' : 'បានផ្តល់យោបល់' }}</span>
                                                        @else
                                                            <span class="badge bg-secondary">{{ app()->getLocale() === 'en' ? 'Proposed' : 'កំពុងស្នើ' }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($isRecommendedStage)
                                                            @if ($isSpecialBasis)
                                                                <span class="badge bg-primary">{{ app()->getLocale() === 'en' ? 'Final approver (Head)' : 'អនុម័តចុងក្រោយ (ប្រធាន)' }}</span>
                                                            @else
                                                                <span class="badge bg-primary">{{ app()->getLocale() === 'en' ? 'Final approver (Head/Deputy)' : 'អនុម័តចុងក្រោយ (ប្រធាន/អនុប្រធាន)' }}</span>
                                                            @endif
                                                        @else
                                                            <span class="badge bg-warning text-dark">{{ app()->getLocale() === 'en' ? 'Reviewer level' : 'កម្រិតពិនិត្យ/ផ្តល់យោបល់' }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-wrap gap-1">
                                                            @if ($canRecommend)
                                                                <span class="badge bg-info text-white">{{ app()->getLocale() === 'en' ? 'Recommend' : 'ផ្តល់យោបល់' }}</span>
                                                            @elseif($recommendReason !== '')
                                                                <span class="badge bg-light text-dark border"
                                                                    data-bs-toggle="tooltip"
                                                                    title="{{ $recommendReason }}">
                                                                    {{ app()->getLocale() === 'en' ? 'No recommend' : 'មិនអាចផ្តល់យោបល់' }}
                                                                </span>
                                                            @endif
                                                            @if ($canApprove)
                                                                <span class="badge bg-success">{{ app()->getLocale() === 'en' ? 'Approve' : 'អនុម័ត' }}</span>
                                                            @elseif($approveReason !== '')
                                                                <span class="badge bg-light text-dark border"
                                                                    data-bs-toggle="tooltip"
                                                                    title="{{ $approveReason }}">
                                                                    {{ app()->getLocale() === 'en' ? 'No approve' : 'មិនអាចអនុម័ត' }}
                                                                </span>
                                                            @endif
                                                            @if ($canReject)
                                                                <span class="badge bg-danger">{{ app()->getLocale() === 'en' ? 'Reject' : 'បដិសេធ' }}</span>
                                                            @elseif($rejectReason !== '')
                                                                <span class="badge bg-light text-dark border"
                                                                    data-bs-toggle="tooltip"
                                                                    title="{{ $rejectReason }}">
                                                                    {{ app()->getLocale() === 'en' ? 'No reject' : 'មិនអាចបដិសេធ' }}
                                                                </span>
                                                            @endif
                                                            @if (!$canAnyAction)
                                                                <span class="badge bg-secondary" data-bs-toggle="tooltip"
                                                                    title="{{ $blockedMessage !== '' ? $blockedMessage : $permissionHint }}">
                                                                    {{ app()->getLocale() === 'en' ? 'No permission' : 'គ្មានសិទ្ធិ' }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        @if (!$canAnyAction && $blockedMessage !== '')
                                                            <small class="d-block text-danger mt-1">{{ $blockedMessage }}</small>
                                                        @elseif($permissionHint !== '')
                                                            <small class="d-block text-muted mt-1">{{ $permissionHint }}</small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <a class="btn btn-sm btn-primary"
                                                            href="{{ route('employee-pay-promotions.review', ['proposal' => $proposal->id, 'year' => $year]) }}">
                                                            {{ app()->getLocale() === 'en' ? 'Review' : 'ពិនិត្យ' }}
                                                        </a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="12" class="text-center text-muted">{{ localize('no_pending_proposals', 'No pending proposals') }}</td>
                                                </tr>
                                            @endforelse
                                            @if ($pendingProposals->isNotEmpty())
                                                <tr id="approvalNoMatchRow" class="d-none">
                                                    <td colspan="12" class="text-center text-muted">{{ localize('empty_data', 'No data found') }}</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade {{ $activeTab === 'reports' ? 'show active' : '' }}" id="tab-reports" role="tabpanel" aria-labelledby="tab-reports-trigger">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="alert alert-success mb-0">{{ $labelEligiblePromotion }}: <strong>{{ $dueRegularCount }}</strong></div></div>
                        <div class="col-md-3"><div class="alert alert-warning mb-0">{{ $labelOverdue3Years }}: <strong>{{ $overdue3YearCount }}</strong></div></div>
                        <div class="col-md-3"><div class="alert alert-secondary mb-0">{{ $labelPendingRequests }}: <strong>{{ $pendingProposals->count() }}</strong></div></div>
                        <div class="col-md-3"><div class="alert alert-dark mb-0">{{ localize('inactive_or_suspended', 'Inactive/Suspended') }}: <strong>{{ $inactiveCadreSnapshots->count() }}</strong></div></div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-pay-promotion-print="true"><i class="fa fa-print me-1"></i>{{ localize('print', 'Print') }}</button>
                        <button type="button" class="btn btn-outline-success btn-sm" data-pay-promotion-export="csv"><i class="fa fa-download me-1"></i>{{ localize('export_csv', 'Export CSV') }}</button>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-pay-promotion-export="pdf"><i class="fa fa-file-pdf me-1"></i>{{ localize('export_pdf', 'Export PDF') }}</button>
                    </div>

                    <div id="payPromotionOfficialPrint" class="pay-promotion-official-report">
                        <div class="report-header-center">
                            <div class="line-main">{{ $ui('ព្រះរាជាណាចក្រកម្ពុជា', 'Kingdom of Cambodia') }}</div>
                            <div class="line-sub">{{ $ui('ជាតិ សាសនា ព្រះមហាក្សត្រ', 'Nation Religion King') }}</div>
                            <div class="line-star">6</div>
                        </div>

                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="report-logo-block">
                                <img src="{{ $officialReportLogo }}" alt="logo">
                                <div class="report-logo-text">
                                    <div>{{ $ui('រដ្ឋបាលខេត្តស្ទឹងត្រែង', 'Stung Treng Provincial Administration') }}</div>
                                    <div>{{ $ui('មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត', 'Department of Health') }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="report-title">{{ $officialReportTitle }}</div>
                        <div class="report-subtitle">
                            {{ $ui('តារាងសង្ខេបការស្នើសុំ និងការអនុម័តដំឡើងថ្នាក់/ឋានន្តរស័ក្តិ', 'Summary of promotion requests and approvals') }}
                        </div>

                        <div class="table-responsive">
                            <table class="table report-table mb-0">
                                <thead>
                                    <tr>
                                        <th width="5%">{{ $ui('ល.រ', '#') }}</th>
                                        <th width="10%">{{ $ui('អត្តលេខមន្ត្រី', 'Staff ID') }}</th>
                                        <th width="18%">{{ $ui('គោត្តនាម និងនាម', 'Name') }}</th>
                                        <th width="16%">{{ $ui('មូលដ្ឋានសំណើ', 'Request basis') }}</th>
                                        <th width="10%">{{ $ui('ថ្នាក់មុនឡើង', 'Previous level') }}</th>
                                        <th width="10%">{{ $ui('ថ្នាក់ស្នើ/បានឡើង', 'Proposed/approved level') }}</th>
                                        <th width="10%">{{ $ui('ថ្ងៃមានប្រសិទ្ធភាព', 'Effective date') }}</th>
                                        <th width="9%">{{ $ui('ស្ថានភាព', 'Status') }}</th>
                                        <th width="12%">{{ $ui('កំណត់សម្គាល់', 'Remark') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($officialReportRows as $reportRow)
                                        @php
                                            $reportPreviousLevel = $fixKhmerText((string) ($promotionPreviousLevelLabels[(int) ($reportRow->id ?? 0)] ?? '-'));
                                            if ($reportPreviousLevel === '') {
                                                $reportPreviousLevel = '-';
                                            }
                                            $reportTargetLevelKm = $fixPayLevelKm($reportRow->payLevel?->level_name_km ?? '');
                                            $reportTargetLevel = $reportTargetLevelKm !== '' ? $reportTargetLevelKm : ($reportRow->payLevel?->level_code ?? '-');
                                            $reportOfficialId10 = trim((string) ($reportRow->employee?->official_id_10 ?? ''));
                                            if ($reportOfficialId10 === '') {
                                                $reportOfficialId10 = (string) ($reportRow->employee?->employee_id ?? '-');
                                            }
                                        @endphp
                                        <tr>
                                            <td class="text-center">{{ $loop->iteration }}</td>
                                            <td class="text-center">{{ $reportOfficialId10 }}</td>
                                            <td>{{ $fixKhmerText($reportRow->employee?->full_name ?? '-') }}</td>
                                            <td>{{ $promotionTypeLabel($reportRow->promotion_type) }}</td>
                                            <td class="text-center">{{ $reportPreviousLevel }}</td>
                                            <td class="text-center">{{ $reportTargetLevel }}</td>
                                            <td class="text-center">{{ display_date($reportRow->start_date) }}</td>
                                            <td class="text-center">{{ $officialStatusLabel($reportRow->status) }}</td>
                                            <td>{{ $fixKhmerText((string) ($reportRow->note ?? '-')) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center">{{ localize('empty_data', 'No data found') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="report-signature">
                            <div class="signature-col">
                                <div class="signature-title signature-title-approval">{{ $ui('ឯកភាព', 'Approved by') }}</div>
                                <div class="signature-line">{{ $ui('ប្រធានមន្ទីរសុខាភិបាល', 'Head of Department') }}</div>
                            </div>
                            <div class="signature-col">
                                <div class="signature-line mb-2">{{ $officialReportDateLine }}</div>
                                <div class="signature-title">{{ $ui('មន្ត្រីគ្រប់គ្រងបុគ្គលិក', 'HR Officer') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade {{ $activeTab === 'alerts' ? 'show active' : '' }}" id="tab-alerts" role="tabpanel" aria-labelledby="tab-alerts-trigger">
                    <div class="row g-3">
                        <div class="col-md-3"><div class="border rounded p-3 h-100"><h6 class="text-warning mb-2">{{ $labelOverdue3Years }}</h6><p class="mb-0">{{ $overdue3YearCount }} {{ localize('employee', 'employees') }}</p></div></div>
                        <div class="col-md-3"><div class="border rounded p-3 h-100"><h6 class="text-info mb-2">{{ $labelNearNextCycle }}</h6><p class="mb-0">{{ $nearDueSnapshots->count() }} {{ localize('employee', 'employees') }}</p></div></div>
                        <div class="col-md-3"><div class="border rounded p-3 h-100"><h6 class="text-primary mb-2">{{ app()->getLocale() === 'en' ? 'Honorary pre-retirement due' : 'មុននិវត្តន៍ ១ ឆ្នាំ (កិត្តិយស)' }}</h6><p class="mb-0">{{ $honoraryDueSnapshots->count() }} {{ localize('employee', 'employees') }}</p></div></div>
                        <div class="col-md-3"><div class="border rounded p-3 h-100"><h6 class="text-dark mb-2">{{ localize('inactive_or_suspended', 'Inactive/Suspended') }}</h6><p class="mb-0">{{ $inactiveCadreSnapshots->count() }} {{ localize('employee', 'employees') }}</p></div></div>
                    </div>
                </div>

            </div>

            <div class="border-top mt-3 pt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <small class="text-muted">
                    {{ localize('audit_info_last_updated', 'បានធ្វើបច្ចុប្បន្នភាពចុងក្រោយ') }}: {{ $lastUpdatedDisplay }}
                </small>
                <small class="text-muted">{{ localize('report_actions_available_in_reports_tab', 'Export/Print actions are available in the Reports tab.') }}</small>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        (function() {
            "use strict";

            var singleEmployeeSelect = document.getElementById('single_employee_id');
            var newPayLevelSelect = document.getElementById('new_pay_level_id');
            var singlePromotionForm = document.getElementById('singlePromotionForm');
            var singleSubmitBtn = document.getElementById('singleSubmitBtn');
            var recordModeInput = document.getElementById('record_mode');
            var promotionTypeSelect = document.getElementById('promotion_type');
            var effectiveDateInput = document.getElementById('single_effective_date');
            var singleNoteInput = document.getElementById('single_note');
            var proposalIdInput = document.querySelector('input[name="proposal_id"]');
            var requestReferenceWrap = document.getElementById('request_reference_wrap');
            var requestDateWrap = document.getElementById('request_date_wrap');
            var documentReferenceWrap = document.getElementById('document_reference_wrap');
            var documentDateWrap = document.getElementById('document_date_wrap');
            var documentReferenceInput = document.getElementById('document_reference');
            var documentDateInput = document.getElementById('document_date');
            var oldPayLevelLabel = document.getElementById('old_pay_level_label');
            var employeeEligibilityHint = document.getElementById('employeeEligibilityHint');
            var staffFilterKeyword = document.getElementById('staffFilterKeyword');
            var staffFilterState = document.getElementById('staffFilterState');
            var staffFilterDue = document.getElementById('staffFilterDue');
            var staffFilterReset = document.getElementById('staffFilterReset');
            var staffFilterResult = document.getElementById('staffFilterResult');
            var approvalFilterKeyword = document.getElementById('approvalFilterKeyword');
            var approvalFilterType = document.getElementById('approvalFilterType');
            var approvalFilterStage = document.getElementById('approvalFilterStage');
            var approvalFilterPermission = document.getElementById('approvalFilterPermission');
            var approvalFilterResult = document.getElementById('approvalFilterResult');
            var approvalBatchForm = document.getElementById('approvalBatchForm');
            var approvalBatchNote = document.getElementById('approvalBatchNote');
            var approvalBatchDocumentReference = document.getElementById('approvalBatchDocumentReference');
            var approvalBatchDocumentDate = document.getElementById('approvalBatchDocumentDate');
            var approvalSelectAllVisible = document.getElementById('approvalSelectAllVisible');
            var approvalSelectedCount = document.getElementById('approvalSelectedCount');
            var approvalBatchRecommendBtn = document.getElementById('approvalBatchRecommendBtn');
            var approvalBatchApproveBtn = document.getElementById('approvalBatchApproveBtn');
            var approvalBatchRejectBtn = document.getElementById('approvalBatchRejectBtn');
            var printButtons = document.querySelectorAll('[data-pay-promotion-print="true"]');
            var exportCsvButtons = document.querySelectorAll('[data-pay-promotion-export="csv"]');
            var exportPdfButtons = document.querySelectorAll('[data-pay-promotion-export="pdf"]');
            var yearFilterUnitInput = document.getElementById('year_filter_unit_id');
            var yearFilterUnitCombo = document.getElementById('year-filter-unit-tree-combo');
            var yearFilterUnitComboToggle = document.getElementById('year-filter-unit-tree-combo-toggle');
            var yearFilterUnitComboLabel = document.getElementById('year-filter-unit-tree-combo-label');
            var yearFilterUnitPanel = document.getElementById('year-filter-unit-tree-panel');
            var yearFilterUnitSearch = document.getElementById('year-filter-unit-tree-search');
            var yearFilterUnitClear = document.getElementById('year-filter-unit-tree-clear');
            var bulkRequestForm = document.getElementById('bulkRequestForm');
            var bulkItemsInput = document.getElementById('bulkItemsInput');
            var bulkRemovedItemsInput = document.getElementById('bulkRemovedItemsInput');
            var bulkSubmitBtn = document.getElementById('bulkSubmitBtn');
            var bulkCandidateTable = document.getElementById('pay-promotion-candidate-table');
            var bulkCandidateTableBody = document.getElementById('bulkCandidateTableBody');
            var bulkCandidateCountBadge = document.getElementById('bulkCandidateCountBadge');
            var bulkManualEmployee = document.getElementById('bulkManualEmployee');
            var bulkManualPromotionType = document.getElementById('bulkManualPromotionType');
            var bulkManualTargetLevel = document.getElementById('bulkManualTargetLevel');
            var bulkManualNote = document.getElementById('bulkManualNote');
            var bulkManualAddBtn = document.getElementById('bulkManualAddBtn');
            var toastContainer = null;
            var chartData = @json($chartData);
            var employeeCurrentLevelMap = @json($employeeCurrentLevelMap);
            var candidateTargetOptionsByCurrent = @json($candidateTargetOptionsPayload);
            var employeeDisplayLabelMap = @json($employeeDisplayLabelMap);
            var bulkRemovedItems = [];

            function showToast(message, type) {
                if (!message) return;
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
                    toastContainer.style.zIndex = '1090';
                    document.body.appendChild(toastContainer);
                }

                var colorClass = 'text-bg-primary';
                if (type === 'success') colorClass = 'text-bg-success';
                if (type === 'warning') colorClass = 'text-bg-warning';
                if (type === 'danger') colorClass = 'text-bg-danger';
                if (type === 'info') colorClass = 'text-bg-info';

                var toastEl = document.createElement('div');
                toastEl.className = 'toast align-items-center border-0 ' + colorClass;
                toastEl.setAttribute('role', 'alert');
                toastEl.setAttribute('aria-live', 'assertive');
                toastEl.setAttribute('aria-atomic', 'true');
                toastEl.innerHTML = ''
                    + '<div class=\"d-flex\">'
                    + '  <div class=\"toast-body\">' + message + '</div>'
                    + '  <button type=\"button\" class=\"btn-close btn-close-white me-2 m-auto\" data-bs-dismiss=\"toast\" aria-label=\"Close\"></button>'
                    + '</div>';

                toastContainer.appendChild(toastEl);

                if (window.bootstrap && window.bootstrap.Toast) {
                    var instance = window.bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2600 });
                    toastEl.addEventListener('hidden.bs.toast', function() {
                        toastEl.remove();
                    });
                    instance.show();
                } else {
                    setTimeout(function() {
                        toastEl.remove();
                    }, 2600);
                }
            }

            function refreshBootstrapTooltips(scopeEl) {
                if (!window.bootstrap || !window.bootstrap.Tooltip) {
                    return;
                }

                var root = scopeEl || document;
                var tooltipEls = root.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltipEls.forEach(function(el) {
                    var existing = window.bootstrap.Tooltip.getInstance(el);
                    if (existing) {
                        existing.dispose();
                    }
                    window.bootstrap.Tooltip.getOrCreateInstance(el);
                });
            }

            function buildOrUpdateChart(canvasId, config) {
                if (typeof Chart === 'undefined') return;
                var canvas = document.getElementById(canvasId);
                if (!canvas) return;

                if (canvas._chartInstance) {
                    canvas._chartInstance.destroy();
                }
                canvas._chartInstance = new Chart(canvas, config);
            }

            function initializeDashboardCharts() {
                if (typeof Chart === 'undefined') {
                    return;
                }

                buildOrUpdateChart('payPromotionStatusChart', {
                    type: 'bar',
                    data: {
                        labels: chartData.status_labels || [],
                        datasets: [{
                            label: '{{ localize('count', 'Count') }}',
                            data: chartData.status_values || [],
                            backgroundColor: ['#28a745', '#0d6efd', '#ffc107'],
                            borderRadius: 6,
                            maxBarThickness: 52
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });

                buildOrUpdateChart('payPromotionTrendChart', {
                    type: 'line',
                    data: {
                        labels: chartData.trend_labels || [],
                        datasets: [{
                            label: '{{ localize('promoted', 'Promoted') }}',
                            data: chartData.trend_promoted || [],
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25,135,84,0.15)',
                            fill: true,
                            tension: 0.25
                        }, {
                            label: '{{ localize('requests', 'Requests') }}',
                            data: chartData.trend_requested || [],
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13,110,253,0.12)',
                            fill: true,
                            tension: 0.25
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });

                buildOrUpdateChart('payPromotionLevelChart', {
                    type: 'bar',
                    data: {
                        labels: chartData.level_labels || [],
                        datasets: [{
                            label: '{{ localize('promoted', 'Promoted') }}',
                            data: chartData.level_promoted || [],
                            backgroundColor: '#198754'
                        }, {
                            label: '{{ localize('requests', 'Requests') }}',
                            data: chartData.level_requested || [],
                            backgroundColor: '#0d6efd'
                        }, {
                            label: '{{ localize('overdue', 'Overdue') }}',
                            data: chartData.level_overdue || [],
                            backgroundColor: '#ffc107'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });

                buildOrUpdateChart('payPromotionUnitChart', {
                    type: 'bar',
                    data: {
                        labels: chartData.unit_labels || [],
                        datasets: [{
                            label: '{{ localize('promoted', 'Promoted') }}',
                            data: chartData.unit_promoted || [],
                            backgroundColor: '#198754'
                        }, {
                            label: '{{ localize('requests', 'Requests') }}',
                            data: chartData.unit_requested || [],
                            backgroundColor: '#0d6efd'
                        }, {
                            label: '{{ localize('overdue', 'Overdue') }}',
                            data: chartData.unit_overdue || [],
                            backgroundColor: '#ffc107'
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            }

            function normalizeSearchText(value) {
                return (value || '').toString().toLowerCase().trim();
            }

            function initYearFilterUnitTreeCombo() {
                if (!yearFilterUnitInput || !yearFilterUnitCombo || !yearFilterUnitPanel) {
                    return;
                }

                var allLabel = ((yearFilterUnitCombo.getAttribute('data-all-label') || '') + '').trim();
                var treeItems = Array.prototype.slice.call(
                    yearFilterUnitPanel.querySelectorAll('.employee-org-tree-item')
                );
                var treeNodes = Array.prototype.slice.call(
                    yearFilterUnitPanel.querySelectorAll('.employee-org-tree-node-filter')
                );

                function setTreeOpenState(item, isOpen) {
                    if (!item || !item.classList.contains('has-children')) {
                        return;
                    }
                    item.classList.toggle('is-open', !!isOpen);
                    var row = item.querySelector('.employee-org-tree-row');
                    var toggle = row ? row.querySelector('.employee-org-tree-toggle') : null;
                    if (!toggle) {
                        return;
                    }
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    var symbol = toggle.querySelector('.toggle-symbol');
                    if (symbol) {
                        symbol.textContent = isOpen ? '-' : '+';
                    }
                }

                function closeAllTreeItems() {
                    treeItems.forEach(function(item) {
                        setTreeOpenState(item, false);
                    });
                }

                function markSelection(unitId) {
                    var selectedUnitId = ((unitId || '') + '').trim();
                    yearFilterUnitPanel
                        .querySelectorAll('.employee-org-tree-node-filter.is-active')
                        .forEach(function(node) {
                            node.classList.remove('is-active');
                        });

                    if (!selectedUnitId) {
                        if (yearFilterUnitComboLabel) {
                            yearFilterUnitComboLabel.textContent = allLabel;
                        }
                        return;
                    }

                    var selectedNode = yearFilterUnitPanel.querySelector(
                        '.employee-org-tree-node-filter[data-org-unit-id="' + selectedUnitId + '"]'
                    );

                    if (!selectedNode) {
                        if (yearFilterUnitComboLabel) {
                            yearFilterUnitComboLabel.textContent = allLabel;
                        }
                        return;
                    }

                    selectedNode.classList.add('is-active');

                    var currentItem = selectedNode.closest('.employee-org-tree-item');
                    while (currentItem) {
                        setTreeOpenState(currentItem, true);
                        var parentTree = currentItem.parentElement;
                        currentItem = parentTree ? parentTree.closest('.employee-org-tree-item') : null;
                    }

                    if (yearFilterUnitComboLabel) {
                        var nameNode = selectedNode.querySelector('.employee-org-tree-name');
                        var nodeName = ((nameNode ? nameNode.textContent : '') || '').trim();
                        yearFilterUnitComboLabel.textContent = nodeName || allLabel;
                    }
                }

                function selectDepartment(unitId) {
                    yearFilterUnitInput.value = ((unitId || '') + '').trim();
                    markSelection(yearFilterUnitInput.value);
                }

                function openCombo() {
                    yearFilterUnitCombo.classList.add('is-open');
                    if (yearFilterUnitSearch) {
                        yearFilterUnitSearch.focus();
                    }
                }

                function closeCombo() {
                    yearFilterUnitCombo.classList.remove('is-open');
                }

                function filterTree(keyword) {
                    var term = normalizeSearchText(keyword);

                    if (!term) {
                        treeItems.forEach(function(item) {
                            item.style.display = '';
                        });
                        closeAllTreeItems();
                        markSelection(yearFilterUnitInput.value);
                        return;
                    }

                    closeAllTreeItems();
                    treeItems.forEach(function(item) {
                        item.style.display = 'none';
                    });

                    treeNodes.forEach(function(node) {
                        var text = normalizeSearchText(node.textContent || '');
                        if (text.indexOf(term) === -1) {
                            return;
                        }

                        var item = node.closest('.employee-org-tree-item');
                        if (!item) {
                            return;
                        }

                        item.style.display = '';
                        item.querySelectorAll('.employee-org-tree-item').forEach(function(childItem) {
                            childItem.style.display = '';
                        });
                        setTreeOpenState(item, true);

                        var parentTree = item.parentElement;
                        var parentItem = parentTree ? parentTree.closest('.employee-org-tree-item') : null;
                        while (parentItem) {
                            parentItem.style.display = '';
                            setTreeOpenState(parentItem, true);
                            parentTree = parentItem.parentElement;
                            parentItem = parentTree ? parentTree.closest('.employee-org-tree-item') : null;
                        }
                    });
                }

                yearFilterUnitPanel.querySelectorAll('.employee-org-tree-toggle').forEach(function(toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    var symbol = toggle.querySelector('.toggle-symbol');
                    if (symbol) {
                        symbol.textContent = '+';
                    }
                });

                if (yearFilterUnitComboToggle) {
                    yearFilterUnitComboToggle.addEventListener('click', function(event) {
                        event.preventDefault();
                        if (yearFilterUnitCombo.classList.contains('is-open')) {
                            closeCombo();
                        } else {
                            openCombo();
                        }
                    });
                }

                document.addEventListener('click', function(event) {
                    if (!yearFilterUnitCombo.contains(event.target)) {
                        closeCombo();
                    }
                });

                yearFilterUnitPanel.addEventListener('click', function(event) {
                    var toggle = event.target.closest('.employee-org-tree-toggle');
                    if (toggle) {
                        event.preventDefault();
                        event.stopPropagation();
                        var item = toggle.closest('.employee-org-tree-item');
                        if (item) {
                            setTreeOpenState(item, !item.classList.contains('is-open'));
                        }
                        return;
                    }

                    var node = event.target.closest('.employee-org-tree-node-filter');
                    if (!node) {
                        return;
                    }

                    event.preventDefault();
                    var unitId = ((node.getAttribute('data-org-unit-id') || '') + '').trim();
                    selectDepartment(unitId);
                    closeCombo();
                });

                if (yearFilterUnitSearch) {
                    yearFilterUnitSearch.addEventListener('input', function() {
                        filterTree(yearFilterUnitSearch.value);
                    });
                }

                if (yearFilterUnitClear) {
                    yearFilterUnitClear.addEventListener('click', function(event) {
                        event.preventDefault();
                        if (yearFilterUnitSearch) {
                            yearFilterUnitSearch.value = '';
                        }
                        filterTree('');
                        selectDepartment('');
                    });
                }

                markSelection(yearFilterUnitInput.value);
            }

            function applyStaffTableFilters() {
                var table = document.getElementById('pay-promotion-staff-table');
                if (!table) return;

                var employeeRows = table.querySelectorAll('tbody tr.pay-staff-employee-row');
                if (!employeeRows.length) return;

                var keyword = normalizeSearchText(staffFilterKeyword ? staffFilterKeyword.value : '');
                var state = (staffFilterState && staffFilterState.value) ? staffFilterState.value : '';
                var due = (staffFilterDue && staffFilterDue.value) ? staffFilterDue.value : '';
                var visibleCount = 0;
                var visibleGroupKeys = {};

                employeeRows.forEach(function(row) {
                    var rowName = normalizeSearchText(row.getAttribute('data-staff-name'));
                    var rowState = (row.getAttribute('data-staff-state') || '').trim();
                    var rowDue = (row.getAttribute('data-staff-due') || '').trim();
                    var matchesKeyword = !keyword || rowName.indexOf(keyword) !== -1;
                    var matchesState = !state || rowState === state;
                    var matchesDue = !due || rowDue === due;
                    var visible = matchesKeyword && matchesState && matchesDue;

                    row.style.display = visible ? '' : 'none';
                    if (visible) {
                        visibleCount++;
                        var groupKeys = (row.getAttribute('data-group-keys') || '').split('||');
                        groupKeys.forEach(function(groupKey) {
                            if (groupKey) {
                                visibleGroupKeys[groupKey] = true;
                            }
                        });
                    }
                });

                var groupRows = table.querySelectorAll('tbody tr.employee-unit-group-row');
                groupRows.forEach(function(groupRow) {
                    var groupKeyCell = groupRow.querySelector('td[data-group-key]');
                    var groupKey = groupKeyCell ? groupKeyCell.getAttribute('data-group-key') : '';
                    groupRow.style.display = visibleGroupKeys[groupKey] ? '' : 'none';
                });

                var noMatchRow = document.getElementById('staffNoMatchRow');
                if (noMatchRow) {
                    noMatchRow.classList.toggle('d-none', visibleCount !== 0);
                }

                if (staffFilterResult) {
                    var totalCount = employeeRows.length;
                    staffFilterResult.textContent = '{{ app()->getLocale() === 'en' ? 'Showing' : 'បង្ហាញ' }} ' +
                        visibleCount +
                        ' / ' +
                        totalCount +
                        ' {{ app()->getLocale() === 'en' ? 'employees' : 'នាក់' }}';
                }
            }

            function applyApprovalTableFilters() {
                var table = document.getElementById('approvalTable');
                if (!table) return;

                var rows = table.querySelectorAll('tbody tr.approval-row');
                if (!rows.length) return;

                var keyword = normalizeSearchText(approvalFilterKeyword ? approvalFilterKeyword.value : '');
                var type = approvalFilterType ? (approvalFilterType.value || '') : '';
                var stage = approvalFilterStage ? (approvalFilterStage.value || '') : '';
                var permission = approvalFilterPermission ? (approvalFilterPermission.value || '') : '';
                var visibleCount = 0;

                rows.forEach(function(row) {
                    var rowSearch = normalizeSearchText(row.getAttribute('data-approval-search'));
                    var rowType = (row.getAttribute('data-approval-type') || '').trim();
                    var rowStage = (row.getAttribute('data-approval-stage') || '').trim();
                    var rowPermissionState = (row.getAttribute('data-permission-state') || '').trim();
                    var rowCanRecommend = row.getAttribute('data-can-recommend') === '1';
                    var rowCanApprove = row.getAttribute('data-can-approve') === '1';
                    var rowCanReject = row.getAttribute('data-can-reject') === '1';
                    var matchesKeyword = !keyword || rowSearch.indexOf(keyword) !== -1;
                    var matchesType = !type || rowType === type;
                    var matchesStage = !stage || rowStage === stage;
                    var matchesPermission = !permission
                        || (permission === 'actionable' && rowPermissionState === 'actionable')
                        || (permission === 'blocked' && rowPermissionState === 'blocked')
                        || (permission === 'recommend' && rowCanRecommend)
                        || (permission === 'approve' && rowCanApprove)
                        || (permission === 'reject' && rowCanReject);
                    var visible = matchesKeyword && matchesType && matchesStage && matchesPermission;

                    row.style.display = visible ? '' : 'none';
                    if (visible) visibleCount++;
                });

                var noMatchRow = document.getElementById('approvalNoMatchRow');
                if (noMatchRow) {
                    noMatchRow.classList.toggle('d-none', visibleCount !== 0);
                }

                if (approvalFilterResult) {
                    var totalCount = rows.length;
                    approvalFilterResult.textContent = '{{ app()->getLocale() === 'en' ? 'Showing' : 'បង្ហាញ' }} ' +
                        visibleCount +
                        ' / ' +
                        totalCount;
                }

                updateApprovalSelectionState();
                refreshBootstrapTooltips(table);
            }

            function getVisibleApprovalRows() {
                var table = document.getElementById('approvalTable');
                if (!table) return [];
                var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr.approval-row'));
                return rows.filter(function(row) {
                    return row.style.display !== 'none';
                });
            }

            function getApprovalCheckboxes(onlyVisible) {
                var rows = onlyVisible ? getVisibleApprovalRows() : [];
                if (onlyVisible) {
                    return rows.map(function(row) {
                        return row.querySelector('input.approval-select-item');
                    }).filter(function(input) {
                        return !!input && !input.disabled;
                    });
                }

                return Array.prototype.slice.call(document.querySelectorAll('#approvalTable input.approval-select-item')).filter(function(input) {
                    return !input.disabled;
                });
            }

            function updateApprovalSelectionState() {
                var allCheckboxes = getApprovalCheckboxes(false);
                var visibleCheckboxes = getApprovalCheckboxes(true);
                var selectedCheckboxes = allCheckboxes.filter(function(input) {
                    return input.checked;
                });
                var selectedCount = selectedCheckboxes.length;
                var selectedCanRecommendCount = 0;
                var selectedCanApproveCount = 0;
                var selectedCanRejectCount = 0;
                var allSelectedCanRecommend = selectedCount > 0;
                var allSelectedCanApprove = selectedCount > 0;
                var allSelectedCanReject = selectedCount > 0;

                selectedCheckboxes.forEach(function(input) {
                    var row = input.closest('tr.approval-row');
                    var canRecommend = row && row.getAttribute('data-can-recommend') === '1';
                    var canApprove = row && row.getAttribute('data-can-approve') === '1';
                    var canReject = row && row.getAttribute('data-can-reject') === '1';

                    if (canRecommend) {
                        selectedCanRecommendCount++;
                    } else {
                        allSelectedCanRecommend = false;
                    }

                    if (canApprove) {
                        selectedCanApproveCount++;
                    } else {
                        allSelectedCanApprove = false;
                    }

                    if (canReject) {
                        selectedCanRejectCount++;
                    } else {
                        allSelectedCanReject = false;
                    }
                });

                if (approvalSelectedCount) {
                    approvalSelectedCount.textContent =
                        '{{ app()->getLocale() === 'en' ? 'Selected' : 'បានជ្រើស' }}: ' + selectedCount +
                        ' | {{ app()->getLocale() === 'en' ? 'Recommend' : 'ផ្តល់យោបល់' }}: ' + selectedCanRecommendCount +
                        ' | {{ app()->getLocale() === 'en' ? 'Approve' : 'អនុម័ត' }}: ' + selectedCanApproveCount +
                        ' | {{ app()->getLocale() === 'en' ? 'Reject' : 'បដិសេធ' }}: ' + selectedCanRejectCount;
                }

                if (approvalBatchApproveBtn) {
                    approvalBatchApproveBtn.disabled = !allSelectedCanApprove;
                    approvalBatchApproveBtn.title = selectedCount === 0
                        ? ''
                        : (allSelectedCanApprove
                            ? ''
                            : '{{ app()->getLocale() === 'en' ? 'Some selected rows cannot be approved by your role.' : 'ជួរដែលបានជ្រើសខ្លះ មិនស្ថិតក្នុងសិទ្ធិអនុម័តរបស់អ្នក។' }}');
                }
                if (approvalBatchRecommendBtn) {
                    approvalBatchRecommendBtn.disabled = !allSelectedCanRecommend;
                    approvalBatchRecommendBtn.title = selectedCount === 0
                        ? ''
                        : (allSelectedCanRecommend
                            ? ''
                            : '{{ app()->getLocale() === 'en' ? 'Some selected rows cannot be recommended by your role.' : 'ជួរដែលបានជ្រើសខ្លះ មិនស្ថិតក្នុងសិទ្ធិផ្តល់យោបល់របស់អ្នក។' }}');
                }
                if (approvalBatchRejectBtn) {
                    approvalBatchRejectBtn.disabled = !allSelectedCanReject;
                    approvalBatchRejectBtn.title = selectedCount === 0
                        ? ''
                        : (allSelectedCanReject
                            ? ''
                            : '{{ app()->getLocale() === 'en' ? 'Some selected rows cannot be rejected by your role.' : 'ជួរដែលបានជ្រើសខ្លះ មិនស្ថិតក្នុងសិទ្ធិបដិសេធរបស់អ្នក។' }}');
                }

                if (!approvalSelectAllVisible) {
                    return;
                }

                if (!visibleCheckboxes.length) {
                    approvalSelectAllVisible.checked = false;
                    approvalSelectAllVisible.indeterminate = false;
                    return;
                }

                var visibleCheckedCount = visibleCheckboxes.filter(function(input) {
                    return input.checked;
                }).length;

                if (visibleCheckedCount === 0) {
                    approvalSelectAllVisible.checked = false;
                    approvalSelectAllVisible.indeterminate = false;
                } else if (visibleCheckedCount === visibleCheckboxes.length) {
                    approvalSelectAllVisible.checked = true;
                    approvalSelectAllVisible.indeterminate = false;
                } else {
                    approvalSelectAllVisible.checked = false;
                    approvalSelectAllVisible.indeterminate = true;
                }
            }

            function refreshOldPayLevelLabel() {
                if (!singleEmployeeSelect || !oldPayLevelLabel) {
                    return;
                }
                var selectedOption = singleEmployeeSelect.options[singleEmployeeSelect.selectedIndex];
                oldPayLevelLabel.value = (!selectedOption || !selectedOption.value) ? '-' : (selectedOption.getAttribute('data-current-level') || '-');
            }

            function toggleRequestFields() {
                if (!promotionTypeSelect || !requestReferenceWrap || !requestDateWrap) {
                    return;
                }
                var isNonAnnual =
                    promotionTypeSelect.value === 'degree_based' ||
                    promotionTypeSelect.value === 'honorary_pre_retirement' ||
                    promotionTypeSelect.value === 'special_case' ||
                    promotionTypeSelect.value === 'special_request';
                requestReferenceWrap.style.display = isNonAnnual ? '' : 'none';
                requestDateWrap.style.display = isNonAnnual ? '' : 'none';
            }

            function toggleRecordModeFields() {
                if (!recordModeInput) {
                    return;
                }
                var mode = (recordModeInput.value || 'request').trim();
                var isRequestMode = mode === 'request';
                if (documentReferenceInput) documentReferenceInput.disabled = isRequestMode;
                if (documentDateInput) documentDateInput.disabled = isRequestMode;
                if (documentReferenceWrap) documentReferenceWrap.style.display = isRequestMode ? 'none' : '';
                if (documentDateWrap) documentDateWrap.style.display = isRequestMode ? 'none' : '';
            }

            function refreshEligibilityHint() {
                if (!employeeEligibilityHint || !singleEmployeeSelect || !promotionTypeSelect) {
                    return;
                }

                var selectedOption = singleEmployeeSelect.options[singleEmployeeSelect.selectedIndex];
                if (!selectedOption || !selectedOption.value) {
                    employeeEligibilityHint.innerHTML = '';
                    return;
                }

                var promotionType = (promotionTypeSelect.value || '').trim();
                var dueRegular = selectedOption.getAttribute('data-due-regular') === '1';
                var dueHonorary = selectedOption.getAttribute('data-due-honorary') === '1';
                var countableYears = selectedOption.getAttribute('data-countable-years') || '0';
                var serviceState = selectedOption.getAttribute('data-service-state') || '';

                var isEligible = true;
                var message = '';

                if (promotionType === 'annual_grade' || promotionType === 'annual_rank' || promotionType === 'yearly_cycle' || promotionType === 'regular') {
                    isEligible = dueRegular;
                    message = isEligible
                        ? '{{ app()->getLocale() === 'en' ? 'Eligible by annual cycle (countable service years: ' : 'គ្រប់លក្ខខណ្ឌវដ្តប្រចាំឆ្នាំ (អាយុកាលគិតបាន៖ ' }}' + countableYears + '{{ app()->getLocale() === 'en' ? ').' : ' ឆ្នាំ)।' }}'
                        : '{{ app()->getLocale() === 'en' ? 'Not yet eligible by annual cycle for this cutoff date.' : 'មិនទាន់គ្រប់លក្ខខណ្ឌវដ្តប្រចាំឆ្នាំត្រឹមថ្ងៃ cutoff នេះទេ។' }}';
                } else if (promotionType === 'honorary_pre_retirement' || promotionType === 'special_case') {
                    isEligible = dueHonorary;
                    message = isEligible
                        ? '{{ app()->getLocale() === 'en' ? 'Eligible for honorary pre-retirement (within one year before retirement).' : 'គ្រប់លក្ខខណ្ឌដំឡើងថ្នាក់កិត្តិយស (មុននិវត្តន៍ ១ ឆ្នាំ)។' }}'
                        : '{{ app()->getLocale() === 'en' ? 'Not within honorary pre-retirement window yet.' : 'មិនទាន់ស្ថិតក្នុងបង្អួចដំឡើងថ្នាក់កិត្តិយសមុននិវត្តន៍ទេ។' }}';
                } else {
                    isEligible = true;
                    message = '{{ app()->getLocale() === 'en' ? 'Degree-based request is processed case-by-case (manual review).' : 'សំណើតាមសញ្ញាបត្រ គឺដំណើរការតាមករណី (ពិនិត្យដោយដៃ)។' }}';
                }

                if (serviceState && serviceState !== 'active' && (promotionType === 'annual_grade' || promotionType === 'annual_rank' || promotionType === 'honorary_pre_retirement')) {
                    isEligible = false;
                    message += ' {{ app()->getLocale() === 'en' ? 'Service state is not active.' : 'ស្ថានភាពការងារមិនមែនសកម្ម។' }}';
                }

                employeeEligibilityHint.innerHTML = '<div class="alert ' + (isEligible ? 'alert-success' : 'alert-warning') + ' py-2 mb-0 small">' + message + '</div>';
            }

            function buildTargetLevelOptions(currentLevelId, selectedLevelId) {
                var options = candidateTargetOptionsByCurrent[(currentLevelId || '').toString()] || [];
                if (!options.length) {
                    return '<option value="">{{ app()->getLocale() === 'en' ? 'No next level' : 'មិនមានថ្នាក់បន្ទាប់' }}</option>';
                }

                return options.map(function(item) {
                    var selected = (selectedLevelId && parseInt(selectedLevelId, 10) === parseInt(item.id, 10)) ? ' selected' : '';
                    return '<option value=\"' + item.id + '\"' + selected + '>' + item.label + '</option>';
                }).join('');
            }

            function updateBulkCountBadge() {
                if (!bulkCandidateCountBadge || !bulkCandidateTableBody) {
                    return;
                }
                var rows = bulkCandidateTableBody.querySelectorAll('tr.candidate-batch-row');
                bulkCandidateCountBadge.textContent = rows.length + ' {{ app()->getLocale() === 'en' ? 'names' : 'ឈ្មោះ' }}';
            }

            function renumberBulkRows() {
                if (!bulkCandidateTableBody) {
                    return;
                }
                var rows = bulkCandidateTableBody.querySelectorAll('tr.candidate-batch-row');
                rows.forEach(function(row, index) {
                    var noCell = row.querySelector('.candidate-row-no');
                    if (noCell) noCell.textContent = (index + 1).toString();
                });
                updateBulkCountBadge();
            }

            function ensureBulkEmptyRow() {
                if (!bulkCandidateTableBody) {
                    return;
                }
                var rows = bulkCandidateTableBody.querySelectorAll('tr.candidate-batch-row');
                var emptyRow = document.getElementById('bulkEmptyRow');
                if (rows.length === 0) {
                    if (!emptyRow) {
                        emptyRow = document.createElement('tr');
                        emptyRow.id = 'bulkEmptyRow';
                        emptyRow.innerHTML = '<td colspan=\"8\" class=\"text-center text-muted\">{{ app()->getLocale() === 'en' ? 'No names selected for batch request.' : 'មិនមានឈ្មោះសម្រាប់ដាក់សំណើជាក្រុម។' }}</td>';
                        bulkCandidateTableBody.appendChild(emptyRow);
                    }
                } else if (emptyRow) {
                    emptyRow.remove();
                }
            }

            function setCandidateRowStatus(row, statusKey) {
                if (!row) return;
                row.setAttribute('data-row-status', statusKey || 'manual');
                var statusCell = row.querySelector('.candidate-row-status');
                if (!statusCell) return;

                var label = '{{ app()->getLocale() === 'en' ? 'New' : 'ថ្មី' }}';
                var cls = 'bg-primary';
                if (statusKey === 'updated') {
                    label = '{{ app()->getLocale() === 'en' ? 'Updated' : 'បានកែប្រែ' }}';
                    cls = 'bg-warning text-dark';
                } else if (statusKey === 'auto') {
                    label = '{{ app()->getLocale() === 'en' ? 'Auto' : 'ស្វ័យប្រវត្ត' }}';
                    cls = 'bg-info text-dark';
                } else if (statusKey === 'review') {
                    label = '{{ app()->getLocale() === 'en' ? 'Needs review' : 'ត្រូវពិនិត្យ' }}';
                    cls = 'bg-secondary';
                }

                statusCell.innerHTML = '<span class=\"badge ' + cls + '\">' + label + '</span>';
            }

            function refreshManualTargetLevelOptions() {
                if (!bulkManualEmployee || !bulkManualTargetLevel) {
                    return;
                }
                var employeeId = (bulkManualEmployee.value || '').toString();
                var currentLevelId = employeeCurrentLevelMap[employeeId] || 0;
                bulkManualTargetLevel.innerHTML = buildTargetLevelOptions(currentLevelId, '');
            }

            function getBulkRowByEmployeeId(employeeId) {
                if (!bulkCandidateTableBody || !employeeId) {
                    return null;
                }
                return bulkCandidateTableBody.querySelector('tr.candidate-batch-row[data-employee-id=\"' + employeeId + '\"]');
            }

            function isEmployeeAlreadyInBulkTable(employeeId) {
                return !!getBulkRowByEmployeeId(employeeId);
            }

            function promotionTypeLabelFromValue(value) {
                var map = {
                    annual_grade: '{{ app()->getLocale() === 'en' ? 'Annual grade promotion' : 'ដំឡើងថ្នាក់ប្រចាំឆ្នាំ' }}',
                    annual_rank: '{{ app()->getLocale() === 'en' ? 'Annual rank promotion' : 'ដំឡើងឋានន្តរស័ក្តិប្រចាំឆ្នាំ' }}',
                    degree_based: '{{ app()->getLocale() === 'en' ? 'By degree' : 'តាមសញ្ញាបត្រ' }}',
                    honorary_pre_retirement: '{{ app()->getLocale() === 'en' ? 'Honorary pre-retirement' : 'ដំឡើងថ្នាក់កិត្តិយសមុននិវត្តន៍' }}',
                    yearly_cycle: '{{ app()->getLocale() === 'en' ? 'Yearly cycle' : 'វដ្តប្រចាំឆ្នាំ' }}',
                    special_case: '{{ app()->getLocale() === 'en' ? 'Special case' : 'ករណីពិសេស' }}',
                    regular: '{{ app()->getLocale() === 'en' ? 'Regular' : 'ធម្មតា' }}',
                    special_request: '{{ app()->getLocale() === 'en' ? 'Special request' : 'សំណើពិសេស' }}'
                };
                return map[value] || value || '-';
            }

            function upsertBulkRowFromSingleForm() {
                if (!singleEmployeeSelect || !newPayLevelSelect || !promotionTypeSelect || !effectiveDateInput || !bulkCandidateTableBody) {
                    return;
                }

                var employeeId = (singleEmployeeSelect.value || '').toString();
                if (!employeeId) {
                    showToast('{{ app()->getLocale() === 'en' ? 'Please select an employee.' : 'សូមជ្រើសរើសមន្ត្រីជាមុន។' }}', 'warning');
                    return;
                }

                var selectedEmployeeOption = singleEmployeeSelect.options[singleEmployeeSelect.selectedIndex];
                if (selectedEmployeeOption && selectedEmployeeOption.getAttribute('data-has-pending') === '1') {
                    showToast(
                        '{{ app()->getLocale() === 'en' ? 'This employee already has a pending request. Please continue from the Approvals/Requests tab.' : 'មន្ត្រីនេះមានសំណើកំពុងរង់ចាំរួចហើយ។ សូមបន្តដំណើរការនៅ Tab សំណើ/ការអនុម័ត។' }}',
                        'info'
                    );
                    return;
                }

                var targetLevelId = (newPayLevelSelect.value || '').toString();
                if (!targetLevelId) {
                    showToast('{{ app()->getLocale() === 'en' ? 'Please select target level.' : 'សូមជ្រើសរើសថ្នាក់ត្រូវឡើង។' }}', 'warning');
                    return;
                }

                var effectiveDate = (effectiveDateInput.value || '').trim();
                if (!effectiveDate) {
                    showToast('{{ app()->getLocale() === 'en' ? 'Please choose effective date.' : 'សូមជ្រើសរើសថ្ងៃមានប្រសិទ្ធភាព។' }}', 'warning');
                    return;
                }

                var selectedTargetOption = newPayLevelSelect.options[newPayLevelSelect.selectedIndex];
                var employeeLabel = selectedEmployeeOption ? (selectedEmployeeOption.text || ('#' + employeeId)) : ('#' + employeeId);
                var currentLevelLabel = selectedEmployeeOption ? (selectedEmployeeOption.getAttribute('data-current-level') || '-') : '-';
                var targetLabel = selectedTargetOption ? (selectedTargetOption.getAttribute('data-level-label') || selectedTargetOption.text || '-') : '-';
                var promotionType = (promotionTypeSelect.value || 'annual_grade').toString();
                var noteText = singleNoteInput && singleNoteInput.value ? singleNoteInput.value.trim() : '';
                var reasonText = noteText || promotionTypeLabelFromValue(promotionType);
                var lastPromotionText = selectedEmployeeOption ? (selectedEmployeeOption.getAttribute('data-last-promotion') || '-') : '-';

                ensureBulkEmptyRow();

                var row = getBulkRowByEmployeeId(employeeId);
                var existed = !!row;
                if (!row) {
                    row = document.createElement('tr');
                    row.className = 'candidate-batch-row table-info';
                    row.setAttribute('data-employee-id', employeeId);
                    row.innerHTML = ''
                        + '<td class=\"candidate-row-no\">0</td>'
                        + '<td class=\"candidate-employee-label\"></td>'
                        + '<td class=\"candidate-reason-text\"></td>'
                        + '<td class=\"candidate-last-promotion\">-</td>'
                        + '<td class=\"candidate-current-level\"></td>'
                        + '<td><select class=\"form-select form-select-sm candidate-target-level\"></select></td>'
                        + '<td class=\"candidate-row-status\"></td>'
                        + '<td><button type=\"button\" class=\"btn btn-sm btn-outline-danger candidate-remove-btn\">{{ app()->getLocale() === 'en' ? 'Remove' : 'ដកចេញ' }}</button></td>';
                    bulkCandidateTableBody.appendChild(row);
                }

                row.setAttribute('data-employee-name', employeeLabel);
                row.setAttribute('data-promotion-type', promotionType);
                row.setAttribute('data-effective-date', effectiveDate);
                row.setAttribute('data-note', noteText);

                var employeeCell = row.querySelector('.candidate-employee-label');
                if (employeeCell) employeeCell.textContent = employeeLabel;
                var reasonCell = row.querySelector('.candidate-reason-text');
                if (reasonCell) reasonCell.textContent = reasonText;
                var lastPromotionCell = row.querySelector('.candidate-last-promotion');
                if (lastPromotionCell) lastPromotionCell.textContent = lastPromotionText || '-';
                var currentLevelCell = row.querySelector('.candidate-current-level');
                if (currentLevelCell) currentLevelCell.textContent = currentLevelLabel || '-';

                var targetSelect = row.querySelector('select.candidate-target-level');
                if (targetSelect) {
                    var currentLevelId = employeeCurrentLevelMap[employeeId] || 0;
                    targetSelect.innerHTML = buildTargetLevelOptions(currentLevelId, targetLevelId);
                    if (!targetSelect.value) {
                        var fallbackOption = document.createElement('option');
                        fallbackOption.value = targetLevelId;
                        fallbackOption.textContent = targetLabel;
                        fallbackOption.selected = true;
                        targetSelect.appendChild(fallbackOption);
                    }
                }
                setCandidateRowStatus(row, existed ? 'updated' : 'manual');

                renumberBulkRows();
                ensureBulkEmptyRow();
                if (existed) {
                    showToast('{{ app()->getLocale() === 'en' ? 'This employee already existed in the table, and the row has been updated.' : 'មន្ត្រីនេះមានក្នុងតារាងរួចហើយ ហើយទិន្នន័យត្រូវបានធ្វើបច្ចុប្បន្នភាព។' }}', 'info');
                } else {
                    showToast('{{ app()->getLocale() === 'en' ? 'Added to batch table successfully.' : 'បានបន្ថែមចូលតារាងសំណើជាក្រុមរួចរាល់។' }}', 'success');
                }
            }

            function addManualBulkRow() {
                if (!bulkCandidateTableBody || !bulkManualEmployee || !bulkManualTargetLevel || !bulkManualPromotionType) {
                    return;
                }

                var employeeId = (bulkManualEmployee.value || '').toString();
                if (!employeeId) {
                    showToast('{{ app()->getLocale() === 'en' ? 'Please select an employee.' : 'សូមជ្រើសរើសមន្ត្រីជាមុន។' }}', 'warning');
                    return;
                }
                if (isEmployeeAlreadyInBulkTable(employeeId)) {
                    showToast('{{ app()->getLocale() === 'en' ? 'This employee is already in the table.' : 'មន្ត្រីនេះមានក្នុងតារាងរួចហើយ។' }}', 'info');
                    return;
                }

                var targetLevelId = (bulkManualTargetLevel.value || '').toString();
                if (!targetLevelId) {
                    showToast('{{ app()->getLocale() === 'en' ? 'Please select target level.' : 'សូមជ្រើសរើសថ្នាក់ត្រូវឡើង។' }}', 'warning');
                    return;
                }

                var selectedTargetOption = bulkManualTargetLevel.options[bulkManualTargetLevel.selectedIndex];
                var targetLabel = selectedTargetOption ? selectedTargetOption.text : '-';
                var employeeLabel = employeeDisplayLabelMap[employeeId] || ('#' + employeeId);
                var promotionType = bulkManualPromotionType.value || 'annual_grade';
                var reasonText = '{{ app()->getLocale() === 'en' ? 'Added manually by HR review' : 'បន្ថែមដោយពិនិត្យរបស់ HR' }}';
                var noteText = (bulkManualNote && bulkManualNote.value) ? bulkManualNote.value.trim() : '';
                if (noteText) {
                    reasonText = noteText;
                }

                ensureBulkEmptyRow();

                var row = document.createElement('tr');
                row.className = 'candidate-batch-row table-info';
                row.setAttribute('data-employee-id', employeeId);
                row.setAttribute('data-employee-name', employeeLabel);
                row.setAttribute('data-promotion-type', promotionType);
                row.setAttribute('data-effective-date', '{{ $cutoffDateIso }}');
                row.setAttribute('data-note', noteText);
                row.innerHTML = ''
                    + '<td class=\"candidate-row-no\">0</td>'
                    + '<td>' + employeeLabel + '</td>'
                    + '<td class=\"candidate-reason-text\">' + reasonText + '</td>'
                    + '<td>-</td>'
                    + '<td>-</td>'
                    + '<td><select class=\"form-select form-select-sm candidate-target-level\">' + buildTargetLevelOptions(employeeCurrentLevelMap[employeeId] || 0, targetLevelId) + '</select></td>'
                    + '<td class=\"candidate-row-status\"></td>'
                    + '<td><button type=\"button\" class=\"btn btn-sm btn-outline-danger candidate-remove-btn\">{{ app()->getLocale() === 'en' ? 'Remove' : 'ដកចេញ' }}</button></td>';

                bulkCandidateTableBody.appendChild(row);
                setCandidateRowStatus(row, 'manual');
                if (bulkManualNote) bulkManualNote.value = '';
                renumberBulkRows();
                ensureBulkEmptyRow();
            }

            function removeBulkRow(row) {
                if (!row) {
                    return;
                }

                var reasonPrompt = '{{ app()->getLocale() === 'en' ? 'Please enter reason for removing this name:' : 'សូមបញ្ចូលមូលហេតុដកឈ្មោះនេះចេញ៖' }}';
                var reason = window.prompt(reasonPrompt, '');
                if (reason === null) {
                    return;
                }
                reason = (reason || '').trim();
                if (!reason) {
                    showToast('{{ app()->getLocale() === 'en' ? 'Removal reason is required.' : 'មូលហេតុដកចេញ គឺចាំបាច់។' }}', 'warning');
                    return;
                }

                bulkRemovedItems.push({
                    employee_id: parseInt(row.getAttribute('data-employee-id') || '0', 10),
                    employee_name: row.getAttribute('data-employee-name') || '',
                    reason: reason
                });

                row.remove();
                renumberBulkRows();
                ensureBulkEmptyRow();
            }

            function submitBulkRequests() {
                if (!bulkRequestForm || !bulkItemsInput || !bulkRemovedItemsInput || !bulkCandidateTableBody) {
                    return;
                }

                var rows = bulkCandidateTableBody.querySelectorAll('tr.candidate-batch-row');
                var payload = [];

                rows.forEach(function(row) {
                    var employeeId = parseInt(row.getAttribute('data-employee-id') || '0', 10);
                    var promotionType = row.getAttribute('data-promotion-type') || 'annual_grade';
                    var effectiveDate = row.getAttribute('data-effective-date') || '{{ $cutoffDateIso }}';
                    var note = row.getAttribute('data-note') || '';
                    var targetSelect = row.querySelector('select.candidate-target-level');
                    var payLevelId = targetSelect ? parseInt(targetSelect.value || '0', 10) : 0;

                    if (employeeId > 0 && payLevelId > 0) {
                        payload.push({
                            employee_id: employeeId,
                            pay_level_id: payLevelId,
                            promotion_type: promotionType,
                            effective_date: effectiveDate,
                            note: note
                        });
                    }
                });

                if (!payload.length) {
                    showToast('{{ app()->getLocale() === 'en' ? 'No valid names to submit.' : 'មិនមានឈ្មោះត្រឹមត្រូវសម្រាប់ដាក់សំណើទេ។' }}', 'warning');
                    return;
                }

                bulkItemsInput.value = JSON.stringify(payload);
                bulkRemovedItemsInput.value = JSON.stringify(bulkRemovedItems);
                bulkRequestForm.submit();
            }

            if (singleEmployeeSelect) singleEmployeeSelect.addEventListener('change', refreshOldPayLevelLabel);
            if (promotionTypeSelect) promotionTypeSelect.addEventListener('change', toggleRequestFields);
            if (singleEmployeeSelect) singleEmployeeSelect.addEventListener('change', refreshEligibilityHint);
            if (promotionTypeSelect) promotionTypeSelect.addEventListener('change', refreshEligibilityHint);

            refreshOldPayLevelLabel();
            toggleRequestFields();
            toggleRecordModeFields();
            refreshEligibilityHint();
            initYearFilterUnitTreeCombo();
            initializeDashboardCharts();
            applyStaffTableFilters();
            applyApprovalTableFilters();

            var tabButtons = document.querySelectorAll('#payPromotionTabs button[data-bs-toggle="tab"]');
            var yearFilterTabInput = document.getElementById('yearFilterTabInput');
            if (tabButtons.length && yearFilterTabInput) {
                tabButtons.forEach(function(btn) {
                    btn.addEventListener('shown.bs.tab', function(ev) {
                        var target = ev.target && ev.target.getAttribute('data-bs-target');
                        if (!target) return;
                        yearFilterTabInput.value = target.replace('#tab-', '');
                        if (target === '#tab-dashboard') {
                            setTimeout(function() {
                                initializeDashboardCharts();
                            }, 40);
                        }
                    });
                });
            }

            function printOfficialReport() {
                var reportNode = document.getElementById('payPromotionOfficialPrint');
                if (!reportNode) {
                    showToast('{{ app()->getLocale() === 'en' ? 'Official report block was not found.' : 'រកមិនឃើញប្លុករបាយការណ៍ផ្លូវការ។' }}', 'warning');
                    return;
                }

                var printWindow = window.open('', '_blank', 'width=1280,height=900');
                if (!printWindow) {
                    showToast('{{ app()->getLocale() === 'en' ? 'Please allow popup to print report.' : 'សូមអនុញ្ញាត Popup ដើម្បីបោះពុម្ពរបាយការណ៍។' }}', 'warning');
                    return;
                }

                var headParts = [];
                document.querySelectorAll('link[rel=\"stylesheet\"], style').forEach(function(node) {
                    headParts.push(node.outerHTML);
                });

                var printLayoutStyle = '<style>'
                    + '@page { size: A4 landscape; margin: 10mm; }'
                    + 'html, body { margin: 0; padding: 0; background: #fff; }'
                    + '#payPromotionOfficialPrint { margin: 0 !important; padding: 0 !important; border: none !important; box-shadow: none !important; }'
                    + '#payPromotionOfficialPrint .table-responsive { overflow: visible !important; }'
                    + '#payPromotionOfficialPrint .report-title, #payPromotionOfficialPrint .report-subtitle, #payPromotionOfficialPrint .report-signature { break-inside: avoid; page-break-inside: avoid; }'
                    + '#payPromotionOfficialPrint .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }'
                    + '#payPromotionOfficialPrint .report-table thead { display: table-header-group; }'
                    + '#payPromotionOfficialPrint .report-table tr { break-inside: avoid; page-break-inside: avoid; }'
                    + '</style>';

                printWindow.document.open();
                printWindow.document.write(
                    '<!doctype html><html><head><meta charset=\"utf-8\"><title>{{ app()->getLocale() === 'en' ? 'Official Promotion Report' : 'របាយការណ៍គ្រប់គ្រងថ្នាក់ និងឋានន្តរស័ក្តិ' }}</title>'
                    + headParts.join('')
                    + printLayoutStyle
                    + '</head><body>'
                    + reportNode.outerHTML
                    + '</body></html>'
                );
                printWindow.document.close();
                printWindow.focus();

                setTimeout(function() {
                    printWindow.print();
                    printWindow.close();
                }, 250);
            }

            printButtons.forEach(function(printButton) {
                printButton.addEventListener('click', function() {
                    printOfficialReport();
                });
            });

            exportPdfButtons.forEach(function(exportPdfButton) {
                exportPdfButton.addEventListener('click', function() {
                    printOfficialReport();
                });
            });

            exportCsvButtons.forEach(function(exportCsvButton) {
                exportCsvButton.addEventListener('click', function() {
                    var table = document.getElementById('historyTable');
                    if (!table) return;

                    var rows = [];
                    Array.prototype.forEach.call(table.querySelectorAll('tr'), function(tr) {
                        var cols = [];
                        Array.prototype.forEach.call(tr.querySelectorAll('th, td'), function(cell) {
                            var text = (cell.innerText || '').replace(/\s+/g, ' ').trim();
                            cols.push('"' + text.replace(/"/g, '""') + '"');
                        });
                        if (cols.length) rows.push(cols.join(','));
                    });

                    if (!rows.length) return;

                    var csv = "\uFEFF" + rows.join("\n");
                    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    var url = URL.createObjectURL(blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = 'pay-promotion-history-{{ $year }}.csv';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                });
            });

            if (staffFilterKeyword) {
                staffFilterKeyword.addEventListener('input', applyStaffTableFilters);
            }
            if (staffFilterState) {
                staffFilterState.addEventListener('change', applyStaffTableFilters);
            }
            if (staffFilterDue) {
                staffFilterDue.addEventListener('change', applyStaffTableFilters);
            }
            if (staffFilterReset) {
                staffFilterReset.addEventListener('click', function() {
                    if (staffFilterKeyword) staffFilterKeyword.value = '';
                    if (staffFilterState) staffFilterState.value = '';
                    if (staffFilterDue) staffFilterDue.value = '';
                    applyStaffTableFilters();
                });
            }

            if (approvalFilterKeyword) {
                approvalFilterKeyword.addEventListener('input', applyApprovalTableFilters);
            }
            if (approvalFilterType) {
                approvalFilterType.addEventListener('change', applyApprovalTableFilters);
            }
            if (approvalFilterStage) {
                approvalFilterStage.addEventListener('change', applyApprovalTableFilters);
            }
            if (approvalFilterPermission) {
                approvalFilterPermission.addEventListener('change', applyApprovalTableFilters);
            }
            if (approvalSelectAllVisible) {
                approvalSelectAllVisible.addEventListener('change', function() {
                    var checked = !!approvalSelectAllVisible.checked;
                    var visibleCheckboxes = getApprovalCheckboxes(true);
                    visibleCheckboxes.forEach(function(input) {
                        input.checked = checked;
                    });
                    updateApprovalSelectionState();
                });
            }
            var approvalTable = document.getElementById('approvalTable');
            if (approvalTable) {
                approvalTable.addEventListener('change', function(event) {
                    if (event.target && event.target.classList.contains('approval-select-item')) {
                        updateApprovalSelectionState();
                    }
                });
            }
            if (approvalBatchForm) {
                approvalBatchForm.addEventListener('submit', function(event) {
                    var submitter = event.submitter;
                    var selectedCheckboxes = getApprovalCheckboxes(false).filter(function(input) {
                        return input.checked;
                    });

                    if (!selectedCheckboxes.length) {
                        event.preventDefault();
                        showToast('{{ app()->getLocale() === 'en' ? 'Please select at least one request.' : 'សូមជ្រើសយ៉ាងហោចណាស់ ១ សំណើ។' }}', 'warning');
                        return;
                    }

                    var action = submitter ? (submitter.value || '') : '';
                    if (action) {
                        var actionKey = 'data-can-' + action;
                        var blockedByPolicy = selectedCheckboxes.some(function(input) {
                            var row = input.closest('tr.approval-row');
                            return !row || row.getAttribute(actionKey) !== '1';
                        });
                        if (blockedByPolicy) {
                            event.preventDefault();
                            showToast(
                                '{{ app()->getLocale() === 'en' ? 'Some selected rows are outside your permission for this action.' : 'ជួរដែលបានជ្រើសខ្លះ មិនស្ថិតក្នុងសិទ្ធិរបស់អ្នកសម្រាប់សកម្មភាពនេះ។' }}',
                                'warning'
                            );
                            return;
                        }
                    }
                    if (action === 'reject' && (!approvalBatchNote || !approvalBatchNote.value || approvalBatchNote.value.trim() === '')) {
                        event.preventDefault();
                        showToast('{{ app()->getLocale() === 'en' ? 'Please provide rejection reason.' : 'សូមបញ្ចូលមូលហេតុបដិសេធ។' }}', 'warning');
                        return;
                    }

                    if (action === 'approve') {
                        if (!approvalBatchDocumentReference || !approvalBatchDocumentReference.value || approvalBatchDocumentReference.value.trim() === '') {
                            event.preventDefault();
                            showToast('{{ app()->getLocale() === 'en' ? 'Please provide approval document reference.' : 'សូមបញ្ចូលលេខលិខិតយោងសម្រាប់អនុម័ត។' }}', 'warning');
                            if (approvalBatchDocumentReference) {
                                approvalBatchDocumentReference.focus();
                            }
                            return;
                        }

                        if (!approvalBatchDocumentDate || !approvalBatchDocumentDate.value || approvalBatchDocumentDate.value.trim() === '') {
                            event.preventDefault();
                            showToast('{{ app()->getLocale() === 'en' ? 'Please provide approval document date.' : 'សូមបញ្ចូលកាលបរិច្ឆេទលិខិតអនុម័ត។' }}', 'warning');
                            if (approvalBatchDocumentDate) {
                                approvalBatchDocumentDate.focus();
                            }
                            return;
                        }
                    }

                    var confirmText = '';
                    if (action === 'approve') {
                        confirmText = '{{ app()->getLocale() === 'en' ? 'Approve selected requests?' : 'បញ្ជាក់អនុម័តសំណើដែលបានជ្រើស?' }}';
                    } else if (action === 'recommend') {
                        confirmText = '{{ app()->getLocale() === 'en' ? 'Recommend selected requests for final approval?' : 'បញ្ជាក់ផ្តល់យោបល់សំណើដែលបានជ្រើសទៅអនុម័តចុងក្រោយ?' }}';
                    } else {
                        confirmText = '{{ app()->getLocale() === 'en' ? 'Reject selected requests?' : 'បញ្ជាក់បដិសេធសំណើដែលបានជ្រើស?' }}';
                    }

                    if (!window.confirm(confirmText)) {
                        event.preventDefault();
                    }
                });
            }

            if (bulkManualEmployee) {
                bulkManualEmployee.addEventListener('change', refreshManualTargetLevelOptions);
            }

            if (bulkManualAddBtn) {
                bulkManualAddBtn.addEventListener('click', addManualBulkRow);
            }

            if (bulkCandidateTable) {
                bulkCandidateTable.addEventListener('click', function(event) {
                    var removeButton = event.target.closest('.candidate-remove-btn');
                    if (!removeButton) {
                        return;
                    }
                    var row = removeButton.closest('tr.candidate-batch-row');
                    removeBulkRow(row);
                });
            }

            if (bulkSubmitBtn) {
                bulkSubmitBtn.addEventListener('click', submitBulkRequests);
            }

            if (singleSubmitBtn) {
                singleSubmitBtn.addEventListener('click', function() {
                    var mode = recordModeInput ? (recordModeInput.value || 'request').trim() : 'request';
                    if (mode === 'request') {
                        upsertBulkRowFromSingleForm();
                        return;
                    }
                    if (singlePromotionForm) {
                        singlePromotionForm.submit();
                    }
                });
            }

            if (singlePromotionForm) {
                singlePromotionForm.addEventListener('submit', function(event) {
                    var mode = recordModeInput ? (recordModeInput.value || 'request').trim() : 'request';
                    if (mode === 'request') {
                        event.preventDefault();
                        upsertBulkRowFromSingleForm();
                    }
                });
            }

            refreshManualTargetLevelOptions();
            renumberBulkRows();
            ensureBulkEmptyRow();
            updateApprovalSelectionState();
            refreshBootstrapTooltips(document);
            var initialRows = bulkCandidateTableBody ? bulkCandidateTableBody.querySelectorAll('tr.candidate-batch-row') : [];
            initialRows.forEach(function(row) {
                var status = row.getAttribute('data-row-status') || 'auto';
                setCandidateRowStatus(row, status);
            });
        })();

        (function($) {
            "use strict";
            if (!$ || !$.fn || !$.fn.select2) {
                return;
            }

            $('#single_employee_id').select2({
                width: '100%',
                allowClear: true,
                placeholder: "Select employee"
            });

            $('#single_employee_id').on('select2:select select2:clear', function() {
                this.dispatchEvent(new Event('change'));
            });
        })(window.jQuery);
    </script>
@endpush

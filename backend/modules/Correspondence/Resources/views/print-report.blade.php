<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>Correspondence report</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 12mm;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            width: 210mm;
            height: 297mm;
            overflow: visible;
        }

        body {
            font-family: "Khmer OS Siemreap", "Noto Sans Khmer", "Khmer OS", "Khmer OS Battambang", sans-serif;
            font-size: 12.5px;
            line-height: 1.55;
            color: #1f2b5b;
            direction: ltr;
            position: relative;
        }

        .font-title {
            font-family: "Khmer M1", "Khmer OS Muol", "Khmer OS Muol Light", "Khmer OS", "Noto Sans Khmer", sans-serif;
            font-weight: 400;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-muted { color: #4f5b7d; }

        .header {
            display: grid;
            grid-template-columns: 1fr 1.2fr 1fr;
            grid-template-rows: auto auto;
            align-items: start;
            row-gap: 6px;
            margin-bottom: 6px;
        }

        .header .logo {
            width: 72px;
            height: 72px;
            margin: 0 auto;
        }

        .header .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .header-title {
            font-size: 18px;
            margin-bottom: 2px;
        }

        .header-subtitle {
            font-size: 15px;
        }

        .org-block {
            margin-top: 6px;
            font-size: 12px;
            white-space: nowrap;
        }

        .header-center {
            grid-column: 2 / 3;
            grid-row: 1;
            text-align: center;
        }

        .left-org {
            grid-column: 1 / 2;
            grid-row: 2;
            justify-self: start;
            align-self: start;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .section-title {
            margin: 8px 0 6px;
            font-size: 14px;
        }

        .line {
            display: inline-block;
            border-bottom: 1px dotted #3b4a73;
            min-width: 220px;
            height: 16px;
            vertical-align: bottom;
        }

        .line.short { min-width: 120px; }
        .line.long { min-width: 320px; }

        .row {
            display: flex;
            gap: 14px;
            margin-bottom: 6px;
        }

        .row .col {
            flex: 1;
        }

        .label {
            display: inline-block;
            min-width: 110px;
        }

        .check-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 16px;
            margin-top: 4px;
        }

        .checkbox {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .box {
            border: 1px solid #3b4a73;
            min-height: 110px;
            padding: 6px;
            page-break-inside: avoid;
        }

        .box-title {
            text-align: center;
            font-weight: 400;
            margin-bottom: 4px;
        }

        .box-row {
            margin-bottom: 6px;
        }

        .box-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 8px;
            page-break-inside: avoid;
        }

        .comment-section {
            margin-top: 54px;
            page-break-inside: avoid;
        }

        .office-comment-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            min-height: 140px;
        }

        .office-comment-main {
            border-right: 1px dashed #9aa5c7;
            padding-right: 8px;
        }

        .office-comment-related {
            padding-left: 2px;
        }

        .office-related-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            align-content: start;
        }

        .office-related-item {
            padding: 0;
            min-height: 48px;
        }

        .office-related-item .comment-meta {
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed #9aa5c7;
            font-size: 11.5px;
            color: #3f4b72;
        }

        .office-related-item .signature-space {
            margin-top: 10px;
            min-height: 38px;
        }

        .office-related-item .signature-line {
            margin-top: 18px;
            border-bottom: 1px solid #3b4a73;
            width: 78%;
        }

        .right-note {
            margin: 6px 0 8px;
        }

        .right-column-block {
            width: 50%;
            margin-left: 50%;
            text-align: center;
        }

        .right-note .note-title {
            font-weight: 400;
        }

        .attachment-qr-section {
            margin-top: 8px;
            margin-bottom: 6px;
        }

        .attachment-qr-bottom-right {
            margin-top: 12px;
            width: 100%;
            display: flex;
            justify-content: flex-start;
            z-index: 10;
            page-break-inside: avoid;
        }

        .attachment-qr-wrap {
            width: 100%;
            text-align: left;
            page-break-inside: avoid;
        }

        .attachment-qr-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 6px;
        }

        .attachment-qr-item {
            border: 1px solid #3b4a73;
            padding: 6px;
            min-width: 145px;
            max-width: 180px;
            text-align: center;
            page-break-inside: avoid;
        }

        .attachment-qr-item svg {
            max-width: 100%;
            height: auto;
        }

        .attachment-qr-item img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .attachment-qr-name {
            font-size: 11px;
            color: #39476e;
            margin-bottom: 4px;
            word-break: break-word;
            text-align: center;
        }

        .comment-meta {
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed #9aa5c7;
            font-size: 11.5px;
            color: #3f4b72;
        }

        .signature-space {
            margin-top: 10px;
            min-height: 38px;
        }

        .signature-line {
            margin-top: 18px;
            border-bottom: 1px solid #3b4a73;
            width: 78%;
        }

        .info-card {
            border: 1px solid #3b4a73;
            border-radius: 4px;
            padding: 8px 10px;
            margin-top: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 14px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .info-item-label {
            width: 98px;
            color: #2f3d67;
            font-weight: 600;
            flex-shrink: 0;
        }

        .recipient-list {
            margin: 0;
            padding-left: 16px;
        }

        .recipient-list li {
            margin-bottom: 3px;
        }

        .outgoing-summary {
            border: 1px solid #3b4a73;
            border-radius: 4px;
            padding: 8px 10px;
            margin-top: 8px;
            min-height: 80px;
        }

        .outgoing-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 8px;
        }

        .feedback-list {
            margin: 0;
            padding-left: 16px;
        }

        .feedback-list li {
            margin-bottom: 6px;
        }

        .feedback-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 4px;
        }

        .feedback-card {
            border: 1px solid #3b4a73;
            min-height: 68px;
            padding: 6px;
            page-break-inside: avoid;
        }

        .feedback-card-target {
            font-weight: 600;
            margin-bottom: 3px;
            color: #2f3d67;
        }

        .feedback-card-note {
            margin-bottom: 4px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .feedback-meta {
            font-size: 11px;
            color: #4f5b7d;
        }
    </style>
</head>
<body>
    @php
        $logo = app_setting()->logo ?? asset('assets/HRM2.png');
        $letterDate = !empty($letter->letter_date) ? \Carbon\Carbon::parse($letter->letter_date)->format('d/m/Y') : '';
        $receivedDate = !empty($letter->received_date) ? \Carbon\Carbon::parse($letter->received_date)->format('d/m/Y') : '';
        $sentDate = !empty($letter->sent_date) ? \Carbon\Carbon::parse($letter->sent_date)->format('d/m/Y') : '';
        $dueDate = !empty($letter->due_date) ? \Carbon\Carbon::parse($letter->due_date)->format('d/m/Y') : '';
        $systemDate = !empty($letter->created_at) ? \Carbon\Carbon::parse($letter->created_at)->format('d/m/Y') : '';
        $khmerMonths = [
            1 => 'មករា', 2 => 'កុម្ភៈ', 3 => 'មីនា', 4 => 'មេសា',
            5 => 'ឧសភា', 6 => 'មិថុនា', 7 => 'កក្កដា', 8 => 'សីហា',
            9 => 'កញ្ញា', 10 => 'តុលា', 11 => 'វិច្ឆិកា', 12 => 'ធ្នូ',
        ];
        $toKhmerNum = function ($value) {
            return strtr((string) $value, [
                '0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤',
                '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩',
            ]);
        };
        $formatKhmerDate = function ($value) use ($khmerMonths, $toKhmerNum) {
            if (empty($value)) {
                return '';
            }
            $dt = \Carbon\Carbon::parse($value);
            $day = $toKhmerNum($dt->format('d'));
            $month = $khmerMonths[(int) $dt->format('n')] ?? '';
            $year = $toKhmerNum($dt->format('Y'));
            return "ថ្ងៃទី {$day} ខែ {$month} ឆ្នាំ{$year}";
        };
        $systemDateText = $formatKhmerDate($letter->created_at);
        $letterDateText = $formatKhmerDate($letter->letter_date);
        $receivedDateText = $formatKhmerDate($letter->received_date ?: $letter->created_at);
        $sentDateText = $formatKhmerDate($letter->sent_date);
        $isIncoming = $letter->letter_type === \Modules\Correspondence\Entities\CorrespondenceLetter::TYPE_INCOMING;
        $priority = (string) ($letter->priority ?? 'normal');
        $priorityMap = [
            'normal' => localize('normal_km', 'ធម្មតា'),
            'urgent' => localize('urgent_km', 'បន្ទាន់'),
            'confidential' => localize('confidential_km', 'សម្ងាត់'),
        ];
        $priorityText = $priorityMap[$priority] ?? $priority;
        $recipientDepartments = $letter->distributions
            ? $letter->distributions->load('targetDepartment')
                ->pluck('targetDepartment.department_name')
                ->filter()
                ->unique()
                ->values()
                ->all()
            : [];
        $toRecipients = $letter->distributions
            ? $letter->distributions
                ->filter(function ($dist) {
                    return in_array($dist->distribution_type, [
                        \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::TYPE_TO,
                        null,
                        '',
                    ], true);
                })
                ->map(function ($dist) {
                    if ($dist->targetDepartment) {
                        return $dist->targetDepartment->department_name;
                    }
                    if ($dist->targetUser) {
                        $name = trim((string) ($dist->targetUser->full_name ?? ''));
                        $email = trim((string) ($dist->targetUser->email ?? ''));
                        return $email !== '' ? "{$name} ({$email})" : $name;
                    }
                    return null;
                })
                ->filter()
                ->unique()
                ->values()
                ->all()
            : [];
        $ccRecipients = $letter->distributions
            ? $letter->distributions->where('distribution_type', \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::TYPE_CC)
                ->map(function ($dist) {
                    if ($dist->targetDepartment) {
                        return $dist->targetDepartment->department_name;
                    }
                    if ($dist->targetUser) {
                        $name = trim((string) ($dist->targetUser->full_name ?? ''));
                        $email = trim((string) ($dist->targetUser->email ?? ''));
                        return $email !== '' ? "{$name} ({$email})" : $name;
                    }
                    return null;
                })
                ->filter()
                ->unique()
                ->values()
                ->all()
            : [];
        $recipientText = '';
        if (!empty($toRecipients)) {
            $recipientText .= 'To: ' . implode(', ', $toRecipients);
        }
        if (!empty($ccRecipients)) {
            $recipientText .= ($recipientText !== '' ? ' | ' : '') . 'CC: ' . implode(', ', $ccRecipients);
        }
        $originOrgText = $letter->originDepartment?->department_name ?: '';
        $originOrgText = $originOrgText !== '' ? $originOrgText : (string) ($letter->from_org ?? '');
        $creatorId = (int) ($letter->created_by ?? 0);
        $creatorName = $creatorId > 0 ? ($userMap[$creatorId] ?? ('#' . $creatorId)) : '-';
        $creatorRole = $creatorId > 0 ? ($userRoleMap[$creatorId] ?? '-') : '-';

        $attachments = is_array($letter->attachment_path)
            ? $letter->attachment_path
            : json_decode((string) $letter->attachment_path, true);
        if (!is_array($attachments)) {
            $attachments = !empty($letter->attachment_path) ? [(string) $letter->attachment_path] : [];
        }
        $attachments = array_values(array_filter($attachments, fn ($item) => !empty($item)));

        $latestOfficeComment = $letter->actions
            ->where('action_type', 'office_comment')
            ->sortByDesc('id')
            ->first();
        $latestDeputyReview = $letter->actions
            ->where('action_type', 'deputy_review')
            ->sortByDesc('id')
            ->first();
        $latestDirectorDecision = $letter->actions
            ->filter(function ($row) {
                return in_array((string) ($row->action_type ?? ''), ['director_approved', 'director_rejected'], true);
            })
            ->sortByDesc('id')
            ->first();

        $officeCommentText = trim((string) ($latestOfficeComment->note ?? ''));
        $deputyReviewText = trim((string) ($latestDeputyReview->note ?? ''));
        $directorDecisionText = trim((string) ($latestDirectorDecision->note ?? ''));
        if ($directorDecisionText === '') {
            $directorDecisionText = trim((string) ($letter->decision_note ?? ''));
        }
        $directorDecisionStatus = '';
        if ($latestDirectorDecision) {
            $directorDecisionStatus = (string) ($latestDirectorDecision->action_type ?? '') === 'director_approved'
                ? '✓ ' . localize('approved_km', 'បានអនុម័ត')
                : '✕ ' . localize('rejected_km', 'បានបដិសេធ');
        }

        $officeCommentActorId = (int) ($latestOfficeComment->acted_by ?? 0);
        $deputyReviewActorId = (int) ($latestDeputyReview->acted_by ?? 0);
        $directorDecisionActorId = (int) ($latestDirectorDecision->acted_by ?? 0);

        $officeCommentActorName = $officeCommentActorId > 0 ? ($userMap[$officeCommentActorId] ?? ('#' . $officeCommentActorId)) : '-';
        $deputyReviewActorName = $deputyReviewActorId > 0 ? ($userMap[$deputyReviewActorId] ?? ('#' . $deputyReviewActorId)) : '-';
        $directorDecisionActorName = $directorDecisionActorId > 0 ? ($userMap[$directorDecisionActorId] ?? ('#' . $directorDecisionActorId)) : '-';

        $officeCommentActorRole = $officeCommentActorId > 0 ? ($userRoleMap[$officeCommentActorId] ?? '-') : '-';
        $deputyReviewActorRole = $deputyReviewActorId > 0 ? ($userRoleMap[$deputyReviewActorId] ?? '-') : '-';
        $directorDecisionActorRole = $directorDecisionActorId > 0 ? ($userRoleMap[$directorDecisionActorId] ?? '-') : '-';

        $officeCommentDateText = $formatKhmerDate($latestOfficeComment?->acted_at);
        $deputyReviewDateText = $formatKhmerDate($latestDeputyReview?->acted_at);
        $directorDecisionDateText = $formatKhmerDate($latestDirectorDecision?->acted_at ?: $letter->decision_at);

        $distributionFeedbacks = collect($letter->distributions ?? [])
            ->filter(function ($dist) {
                return trim((string) ($dist->feedback_note ?? '')) !== '';
            })
            ->map(function ($dist) use ($userMap, $userRoleMap, $formatKhmerDate) {
                $actorId = (int) ($dist->target_user_id ?? 0);
                $targetLabel = $dist->targetDepartment?->department_name;
                if (!$targetLabel && $dist->targetUser) {
                    $targetLabel = trim((string) ($dist->targetUser->full_name ?? ''));
                }

                $actorName = $actorId > 0
                    ? ($userMap[$actorId] ?? ('#' . $actorId))
                    : ($targetLabel ?: '-');
                $actorRole = $actorId > 0 ? ($userRoleMap[$actorId] ?? '-') : '-';

                $rawAt = $dist->feedback_at ?: $dist->updated_at;

                return [
                    'target' => $targetLabel ?: '-',
                    'name' => $actorName,
                    'role' => $actorRole,
                    'note' => trim((string) ($dist->feedback_note ?? '')),
                    'at' => optional($rawAt)->format('d/m/Y H:i') ?: '-',
                    'date_text' => $formatKhmerDate($rawAt),
                    'raw_at' => $rawAt,
                ];
            });

        $relatedActorFeedbacks = collect($letter->actions ?? [])
            ->filter(function ($action) {
                return (string) ($action->action_type ?? '') === 'office_comment_related'
                    && trim((string) ($action->note ?? '')) !== '';
            })
            ->map(function ($action) use ($userMap, $userRoleMap, $formatKhmerDate) {
                $actorId = (int) ($action->acted_by ?? 0);
                $targetLabel = $actorId > 0 ? ($userMap[$actorId] ?? ('#' . $actorId)) : '-';
                $actorRole = $actorId > 0 ? ($userRoleMap[$actorId] ?? '-') : '-';
                $rawAt = $action->acted_at ?: $action->updated_at;

                return [
                    'target' => $targetLabel,
                    'name' => $targetLabel,
                    'role' => $actorRole,
                    'note' => trim((string) ($action->note ?? '')),
                    'at' => optional($rawAt)->format('d/m/Y H:i') ?: '-',
                    'date_text' => $formatKhmerDate($rawAt),
                    'raw_at' => $rawAt,
                ];
            });

        $relatedFeedbacks = $distributionFeedbacks
            ->concat($relatedActorFeedbacks)
            ->sortByDesc(function ($row) {
                return optional($row['raw_at'] ?? null)->timestamp ?? 0;
            })
            ->values()
            ->all();
    @endphp

    <div class="header">
        <div class="header-center">
            <div class="header-title font-title">ព្រះរាជាណាចក្រកម្ពុជា</div>
            <div class="header-subtitle font-title">ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
        </div>
        <div class="left-org">
            <div class="logo">
                <img src="{{ $logo }}" alt="logo">
            </div>
            <div class="org-block font-title text-center">
                <div>រដ្ឋបាលខេត្តស្ទឹងត្រែង</div>
                <div>មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត</div>
            </div>
        </div>
    </div>

    <div class="text-center section-title font-title">កំណត់បង្ហាញរឿងលិខិត</div>
    @if ($isIncoming)
        <div class="box-row">
            យោងលិខិតលេខ {{ $letter->letter_no ?: '' }} ថ្ងៃទី {{ $letterDateText }} អង្គភាព {{ $originOrgText }}
        </div>
        <div class="box-row">
            ខ្លឹមសារលិខិត៖ {{ $letter->summary ?: ($letter->subject ?: '') }}
        </div>
        <div class="box-row">
            ឯកសារផ្ញើរ៖ {{ $recipientText }}
        </div>

        <div class="right-note right-column-block">
            <div class="note-title font-title">ប្រធានការិយាល័យរដ្ឋបាលបុគ្គលិក</div>
            <div class="text-muted">គោរពជូនលោកប្រធានជ្រាប</div>
            <div class="text-muted">{{ $systemDateText }}</div>
        </div>

        <div class="comment-section">
            <div class="box-title font-title">យោបល់ការិយាល័យជំនាញ</div>
            <div class="box" style="min-height: 140px;">
                <div class="office-comment-split">
                    <div class="office-comment-main">
                        <div>{!! $officeCommentText !== '' ? nl2br(e($officeCommentText)) : '-' !!}</div>
                        <div class="comment-meta">
                            <div>{{ $officeCommentDateText !== '' ? $officeCommentDateText : '-' }}</div>
                            <div>{{ localize('name', 'ឈ្មោះ') }}: {{ $officeCommentActorName }}</div>
                            <div>{{ localize('role', 'តួនាទី') }}: {{ $officeCommentActorRole }}</div>
                        </div>
                        <div class="signature-space">
                            <div class="text-muted">{{ localize('signature', 'ហត្ថលេខា') }}</div>
                            <div class="signature-line"></div>
                        </div>
                    </div>
                    <div class="office-comment-related">
                        <div class="office-related-list">
                            @forelse ($relatedFeedbacks as $feedback)
                                <div class="office-related-item">
                                    <div class="feedback-card-note">{!! nl2br(e($feedback['note'])) !!}</div>
                                    <div class="comment-meta">
                                        <div>{{ !empty($feedback['date_text']) ? $feedback['date_text'] : $feedback['at'] }}</div>
                                        <div>{{ localize('name', 'ឈ្មោះ') }}: {{ $feedback['name'] ?? $feedback['target'] }}</div>
                                        <div>{{ localize('role', 'តួនាទី') }}: {{ $feedback['role'] ?? '-' }}</div>
                                    </div>
                                    <div class="signature-space">
                                        <div class="text-muted">{{ localize('signature', 'ហត្ថលេខា') }}</div>
                                        <div class="signature-line"></div>
                                    </div>
                                </div>
                            @empty
                                <div class="office-related-item">-</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="box-grid">
            <div>
                <div class="box-title font-title">ប្រធានមន្ទីរ</div>
                <div class="box">
                    @if ($directorDecisionStatus !== '')
                        <div style="margin-bottom: 4px;">{{ $directorDecisionStatus }}</div>
                    @endif
                    <div>{!! $directorDecisionText !== '' ? nl2br(e($directorDecisionText)) : '-' !!}</div>
                    <div class="comment-meta">
                        <div>{{ $directorDecisionDateText !== '' ? $directorDecisionDateText : '-' }}</div>
                        <div>{{ localize('name', 'ឈ្មោះ') }}: {{ $directorDecisionActorName }}</div>
                        <div>{{ localize('role', 'តួនាទី') }}: {{ $directorDecisionActorRole }}</div>
                    </div>
                    <div class="signature-space">
                        <div class="text-muted">{{ localize('signature', 'ហត្ថលេខា') }}</div>
                        <div class="signature-line"></div>
                    </div>
                </div>
            </div>
            <div>
                <div class="box-title font-title">អនុប្រធានមន្ទីរ</div>
                <div class="box">
                    <div>{!! $deputyReviewText !== '' ? nl2br(e($deputyReviewText)) : '-' !!}</div>
                    <div class="comment-meta">
                        <div>{{ $deputyReviewDateText !== '' ? $deputyReviewDateText : '-' }}</div>
                        <div>{{ localize('name', 'ឈ្មោះ') }}: {{ $deputyReviewActorName }}</div>
                        <div>{{ localize('role', 'តួនាទី') }}: {{ $deputyReviewActorRole }}</div>
                    </div>
                    <div class="signature-space">
                        <div class="text-muted">{{ localize('signature', 'ហត្ថលេខា') }}</div>
                        <div class="signature-line"></div>
                    </div>
                </div>
            </div>
        </div>

    @else
        <div class="info-card">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-item-label">{{ localize('registry_no', 'លេខចុះបញ្ជី') }}</div>
                    <div>: {{ $letter->registry_no ?: '-' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">{{ localize('letter_no', 'លេខលិខិត') }}</div>
                    <div>: {{ $letter->letter_no ?: '-' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">{{ localize('letter_date', 'ថ្ងៃលិខិត') }}</div>
                    <div>: {{ $letterDateText !== '' ? $letterDateText : '-' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">{{ localize('received_date', 'ថ្ងៃទទួល') }}</div>
                    <div>: {{ $receivedDateText !== '' ? $receivedDateText : '-' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">{{ localize('sent_date', 'ថ្ងៃផ្ញើ') }}</div>
                    <div>: {{ $sentDateText !== '' ? $sentDateText : '-' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">{{ localize('priority', 'អាទិភាព') }}</div>
                    <div>: {{ $priorityText }}</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">{{ localize('from_org', 'អង្គភាពផ្ញើ') }}</div>
                    <div>: {{ $originOrgText !== '' ? $originOrgText : '-' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">{{ localize('subject', 'កម្មវត្ថុ') }}</div>
                    <div>: {{ $letter->subject ?: '-' }}</div>
                </div>
            </div>
        </div>

        <div class="info-card">
            <div class="box-title font-title" style="text-align: left; margin-bottom: 6px;">{{ localize('recipients', 'អ្នកទទួល') }}</div>
            <div class="info-item" style="margin-bottom: 6px;">
                <div class="info-item-label">To</div>
                <div>
                    @if (!empty($toRecipients))
                        <ul class="recipient-list">
                            @foreach ($toRecipients as $toName)
                                <li>{{ $toName }}</li>
                            @endforeach
                        </ul>
                    @else
                        -
                    @endif
                </div>
            </div>
            <div class="info-item">
                <div class="info-item-label">CC</div>
                <div>
                    @if (!empty($ccRecipients))
                        <ul class="recipient-list">
                            @foreach ($ccRecipients as $ccName)
                                <li>{{ $ccName }}</li>
                            @endforeach
                        </ul>
                    @else
                        -
                    @endif
                </div>
            </div>
        </div>

        <div class="outgoing-grid">
            <div class="outgoing-summary" style="margin-top: 0;">
                <div class="box-title font-title" style="text-align: left; margin-bottom: 6px;">{{ localize('summary', 'ខ្លឹមសារលិខិត') }}</div>
                <div>{!! ($letter->summary ?: $letter->subject) ? nl2br(e($letter->summary ?: $letter->subject)) : '-' !!}</div>
            </div>

            <div class="outgoing-summary" style="margin-top: 0;">
                <div class="box-title font-title" style="text-align: left; margin-bottom: 6px;">{{ localize('related_feedback', 'មតិយោបល់អ្នកពាក់ព័ន្ធ') }}</div>
                @if (!empty($relatedFeedbacks))
                    <ul class="feedback-list">
                        @foreach ($relatedFeedbacks as $feedback)
                            <li>
                                <div><strong>{{ $feedback['target'] }}</strong>: {!! nl2br(e($feedback['note'])) !!}</div>
                                <div class="feedback-meta">{{ localize('date', 'កាលបរិច្ឆេទ') }}: {{ $feedback['at'] }}</div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div>-</div>
                @endif
            </div>
        </div>

        <div class="right-note right-column-block" style="margin-top: 14px;">
            <div class="note-title font-title">{{ localize('prepared_by', 'អ្នករៀបចំលិខិត') }}</div>
            <div class="text-muted">{{ localize('name', 'ឈ្មោះ') }}: {{ $creatorName }}</div>
            <div class="text-muted">{{ localize('role', 'តួនាទី') }}: {{ $creatorRole }}</div>
            <div class="text-muted">{{ $systemDateText !== '' ? $systemDateText : '-' }}</div>
        </div>
    @endif

    @if (!empty($attachments))
        <div class="attachment-qr-bottom-right">
            <div class="attachment-qr-wrap attachment-qr-section">
                <div class="attachment-qr-grid">
                    @foreach ($attachments as $path)
                        @php
                            $storedPath = ltrim((string) $path, '/');
                            $normalizedPath = preg_replace('#^(storage/|public/)#', '', $storedPath) ?: $storedPath;
                            $fileUrl = asset('storage/' . ltrim($normalizedPath, '/'));
                            // Generate QR code as base64 PNG image for better print compatibility
                            $qrCode = app('DNS2D')->getBarcodePNG($fileUrl, 'QRCODE', 2.8, 2.8);
                        @endphp
                        <div class="attachment-qr-item">
                            <div class="attachment-qr-name">{{ localize('attachment_km', 'ឯកសារភ្ជាប់') }}</div>
                            @if (!empty($qrCode))
                                <img src="data:image/png;base64,{{ $qrCode }}" alt="QR Code" style="width: 140px; height: 140px; display: block; margin: 0 auto;">
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <script>
        (function() {
        })();
    </script>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 150);
        });
    </script>
</body>
</html>

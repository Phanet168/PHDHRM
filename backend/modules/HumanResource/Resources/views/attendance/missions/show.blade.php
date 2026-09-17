@extends('backend.layouts.app')
@section('title', 'បេសកកម្មលម្អិត')
@section('content')
    @include('humanresource::attendance.missions.nav')
    <div class="d-flex justify-content-between align-items-center mb-3"><h4>បេសកកម្មលម្អិត</h4><div class="d-flex gap-2">
        @if($canManageOrder && $mission->lifecycle_status && in_array($mission->lifecycle_status->value, ['pending_mission_order', 'preparing_mission_order'], true))
            <a class="btn btn-warning" href="{{ route('missions.order.prepare', $mission->id) }}">រៀបចំលិខិតបញ្ជាបេសកកម្ម</a>
        @endif
        <a class="btn btn-primary" href="{{ route('missions.order', $mission->id) }}" target="_blank" rel="noopener">មើល/បោះពុម្ពលិខិតបេសកកម្ម</a>
        <a class="btn btn-outline-secondary" href="{{ route('missions.index') }}">ត្រឡប់ទៅបញ្ជី</a></div></div>
    @include('backend.layouts.common.validation')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @include('humanresource::attendance.missions.timeline')
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3"><div class="card-body">
                <div class="d-flex justify-content-between gap-3"><h4>{{ $mission->title }}</h4><div>@include('humanresource::attendance.missions.status')</div></div>
                <p class="text-muted">{{ $mission->destination }}</p>
                <p>{{ $detail['mission_type_label'] }}</p>
                @if($mission->order_number)<p>លិខិតបង្គាប់ការលេខ៖ <strong>{{ $mission->order_number }}</strong></p>@endif
                @if(data_get($mission->order_details, 'transport'))<p>មធ្យោបាយធ្វើដំណើរ៖ {{ data_get($mission->order_details, 'transport') }}</p>@endif
                @if(data_get($mission->order_details, 'funding_source'))<p>ប្រភពថវិកា៖ {{ data_get($mission->order_details, 'funding_source') }}</p>@endif
                <p>{{ $mission->start_date->format('Y-m-d') }} — {{ $mission->end_date->format('Y-m-d') }} <strong class="text-success ms-3">{{ $detail['duration_days'] }} ថ្ងៃ</strong></p><hr>
                @if($mission->requesterEmployee)
                    <p><span class="text-muted">អ្នកស្នើសុំ៖</span> <strong>{{ $mission->requesterEmployee->full_name }}</strong>
                        @if($mission->requesterEmployee->position)
                            <span class="text-muted">({{ $mission->requesterEmployee->position->position_name_km ?: $mission->requesterEmployee->position->position_name }})</span>
                        @endif
                    </p>
                    @if(data_get($mission->order_details, 'preferred_office_head'))
                        <p><span class="text-muted">តាមរយៈប្រធានការិយាល័យ៖</span> {{ data_get($mission->order_details, 'preferred_office_head') }}</p>
                    @endif
                @else
                    <p><span class="text-muted">អ្នកចាត់តាំង៖</span> {{ $mission->assigner?->full_name ?? '—' }}</p>
                @endif
                @if(!empty($currentApprovers))
                    <p><span class="text-muted">កំពុងរង់ចាំសម្រេចដោយ (តាមរយៈ)៖</span> <strong class="text-primary">{{ implode(', ', $currentApprovers) }}</strong></p>
                @endif
                <p style="white-space: pre-wrap">{{ $mission->purpose }}</p>
                @if($mission->rejected_reason)<div class="alert alert-danger">{{ $mission->rejected_reason }}</div>@endif
                @if($mission->started_at)<p>ចាប់ផ្ដើម៖ {{ $mission->started_at->format('Y-m-d H:i') }}</p>@endif
                @if($mission->completed_at)<p>បានបញ្ចប់៖ {{ $mission->completed_at->format('Y-m-d H:i') }}</p>@endif
            </div></div>
            <div class="card mb-3"><div class="card-header">សមាជិកក្រុម</div><div class="card-body d-flex flex-wrap gap-3">
                @foreach($mission->assignments as $assignment)<div class="border rounded p-3">{{ $assignment->employee?->full_name ?? '—' }}<small class="d-block text-muted">{{ $assignment->employee?->employee_id }} · {{ $assignment->status }}</small></div>@endforeach
            </div></div>
            @php($referenceDocuments = $mission->documents->where('document_kind', 'reference'))
            @if($referenceDocuments->isNotEmpty())
                <div class="card mb-3"><div class="card-header">លិខិតយោង</div><div class="card-body">
                    @foreach($referenceDocuments as $reference)
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>
                                @if($reference->path)<a href="{{ route('missions.documents.download', [$mission->id, $reference->id]) }}">{{ $reference->name }}</a>@else{{ $reference->name }}@endif
                                @if($reference->reference_number) — {{ $reference->reference_number }}@endif
                                @if($reference->reference_issuer) ({{ $reference->reference_issuer }})@endif
                            </span>
                            @if($reference->reference_date)<span class="text-muted small">{{ \Illuminate\Support\Carbon::parse($reference->reference_date)->format('Y-m-d') }}</span>@endif
                        </div>
                    @endforeach
                </div></div>
            @endif
            @if(!$mission->isLegacyFlow() && $mission->statusHistories->isNotEmpty())
                <div class="card mb-3"><div class="card-header">ការអនុម័ត / ប្រវត្តិសកម្មភាព</div><div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @foreach($mission->statusHistories as $history)
                            <li class="d-flex justify-content-between border-bottom py-2">
                                <span>
                                    <strong>{{ $history->to_status?->label() ?? $history->action }}</strong>
                                    @if($history->actor)<span class="text-muted"> — {{ $history->actor->full_name }}</span>@endif
                                    @if($history->reason)<div class="small text-muted">{{ $history->reason }}</div>@endif
                                </span>
                                <span class="text-muted small">{{ $history->occurred_at?->format('Y-m-d H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div></div>
            @endif
            <div class="card mb-3"><div class="card-header">ឯកសារ</div><div class="card-body">
                @php($supportingDocuments = $mission->documents->where('document_kind', '!=', 'reference'))
                @forelse($supportingDocuments as $document)<div class="d-flex justify-content-between py-2 border-bottom"><a href="{{ route('missions.documents.download', [$mission->id, $document->id]) }}">{{ $document->name }}</a><span class="text-muted">{{ number_format($document->size / 1024, 1) }} KB</span></div>@empty<p class="text-muted">មិនទាន់មានឯកសារ។</p>@endforelse
                @if($canManage && in_array($mission->status, ['draft','pending','approved']) && !$mission->completed_at)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('missions.documents.store', $mission->id) }}" class="mt-3">@csrf
                        <label for="document" class="form-label">ភ្ជាប់ឯកសារ (PDF, Word, Excel, រូបភាព — អតិបរមា 10 MB)</label>
                        <input id="document" class="form-control mb-2" type="file" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required><button class="btn btn-outline-primary">បញ្ចូលឯកសារ</button>
                    </form>
                @endif
            </div></div>
            <div class="card mb-3"><div class="card-header">របាយការណ៍បេសកកម្ម</div><div class="card-body">
                @forelse($mission->reports as $report)<article class="border-bottom mb-3"><strong>{{ $mission->assignments->firstWhere('employee_id', $report->employee_id)?->employee?->full_name ?? '—' }}</strong><small class="text-muted ms-2">{{ $report->updated_at->format('Y-m-d H:i') }}</small><p style="white-space: pre-wrap">{{ $report->summary }}</p></article>@empty<p class="text-muted">មិនទាន់មានរបាយការណ៍។</p>@endforelse
                @if($detail['actions']['can_report'])<form method="POST" action="{{ route('missions.report', $mission->id) }}">@csrf<label for="summary" class="form-label">របាយការណ៍របស់អ្នក</label><textarea id="summary" name="summary" class="form-control mb-2" rows="5" maxlength="20000" required>{{ old('summary') }}</textarea><button class="btn btn-primary">រាយការណ៍</button></form>@endif
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-header">សកម្មភាព</div><div class="card-body">
                @if($detail['actions']['can_start'])<form method="POST" action="{{ route('missions.start', $mission->id) }}" class="mb-3">@csrf<button class="btn btn-success">ចាប់ផ្ដើម</button></form>@endif
                @if($canReview && $mission->status === 'pending' && $mission->isLegacyFlow())
                    <form method="POST" action="{{ route('missions.review', $mission->id) }}" class="mb-3">@csrf<input type="hidden" name="decision" value="approved"><button class="btn btn-success">អនុម័ត</button></form>
                    <form method="POST" action="{{ route('missions.review', $mission->id) }}" class="mb-3">@csrf<input type="hidden" name="decision" value="rejected"><label for="reason" class="form-label">មូលហេតុបដិសេធ</label><textarea id="reason" name="reason" class="form-control mb-2" required maxlength="1000"></textarea><button class="btn btn-outline-danger">បដិសេធ</button></form>
                @endif
                @if($canDecide)
                    @php($decideVerb = $mission->lifecycle_status?->value === 'pending_office_head' ? 'endorse' : 'approve')
                    <form method="POST" action="{{ route('missions.decide', $mission->id) }}" class="mb-2">@csrf
                        <input type="hidden" name="decision" value="{{ $decideVerb }}">
                        <button class="btn btn-success w-100" onclick="return confirm('បញ្ជាក់{{ $decideVerb === 'endorse' ? 'ការឯកភាព' : 'ការអនុម័ត' }}?');">{{ $decideVerb === 'endorse' ? 'ឯកភាព' : 'អនុម័ត' }}</button>
                    </form>
                    <form method="POST" action="{{ route('missions.decide', $mission->id) }}" class="mb-2">
                        @csrf<input type="hidden" name="decision" value="return">
                        <label class="form-label">មូលហេតុបញ្ជូនត្រឡប់ (ត្រូវការកែសម្រួល)</label>
                        <textarea name="note" class="form-control mb-2" maxlength="1000" required></textarea>
                        <button class="btn btn-outline-warning w-100">បញ្ជូនត្រឡប់</button>
                    </form>
                    <form method="POST" action="{{ route('missions.decide', $mission->id) }}" class="mb-3">
                        @csrf<input type="hidden" name="decision" value="reject">
                        <label class="form-label">មូលហេតុបដិសេធ</label>
                        <textarea name="note" class="form-control mb-2" maxlength="1000" required></textarea>
                        <button class="btn btn-outline-danger w-100">បដិសេធ</button>
                    </form>
                @endif
                @if($canManage && $mission->status === 'approved' && $mission->started_at && !$mission->completed_at && $mission->reports->isNotEmpty())<form method="POST" action="{{ route('missions.complete', $mission->id) }}" class="mb-3">@csrf<button class="btn btn-primary">បញ្ចប់បេសកកម្ម</button></form>@endif
                @if($canManage && in_array($mission->status, ['draft','pending','approved']) && !$mission->completed_at)<form method="POST" action="{{ route('missions.cancel', $mission->id) }}" class="mb-3" onsubmit="return confirm('បោះបង់បេសកកម្មនេះ?')">@csrf<button class="btn btn-outline-danger">បោះបង់បេសកកម្ម</button></form>@endif
                @if($canDelete && in_array($mission->status, ['draft','rejected','cancelled']))<form method="POST" action="{{ route('missions.destroy', $mission->id) }}" onsubmit="return confirm('លុបបេសកកម្មនេះ?')">@csrf @method('DELETE')<button class="btn btn-danger">លុប</button></form>@endif
            </div></div>
            @if($canManage && in_array($mission->status, ['draft','pending']))
                <div class="card"><div class="card-header">កែប្រែបេសកកម្ម</div><div class="card-body"><form method="POST" action="{{ route('missions.update', $mission->id) }}">@csrf @method('PUT')
                    @include('humanresource::attendance.missions.fields')
                </form></div></div>
            @endif
        </div>
    </div>
@endsection
@push('js')<script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>@endpush

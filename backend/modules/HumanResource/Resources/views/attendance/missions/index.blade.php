@extends('backend.layouts.app')
@section('title', 'គ្រប់គ្រងបេសកកម្ម')
@section('content')
    @include('humanresource::attendance.missions.nav')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h4>គ្រប់គ្រងបេសកកម្ម</h4><p class="text-muted mb-0">ចាត់តាំងមន្ត្រីចេញបំពេញការងារក្រៅអង្គភាព និងតាមដានរបាយការណ៍លទ្ធផល</p></div>
        @can('create_mission')<a href="{{ route('missions.index', ['mode' => 'create']) }}" class="btn btn-primary">បង្កើតបេសកកម្ម</a>@endcan
    </div>
    @include('backend.layouts.common.validation')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card mb-3"><div class="card-body">
        <form method="GET" action="{{ route('missions.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4"><label for="search" class="form-label">ស្វែងរក</label><input id="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="ចំណងជើង ឬគោលដៅ"></div>
            <div class="col-md-2"><label for="status" class="form-label">ស្ថានភាព</label><select id="status" name="status" class="form-select">
                <option value="">ទាំងអស់</option>
                @foreach(['draft'=>'សេចក្ដីព្រាង','pending'=>'រង់ចាំអនុម័ត','approved'=>'បានអនុម័ត','in_progress'=>'កំពុងដំណើរការ','completed'=>'បានបញ្ចប់','rejected'=>'បានបដិសេធ','cancelled'=>'បានបោះបង់'] as $value=>$label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select></div>
            <div class="col-md-2"><label for="from" class="form-label">ចាប់ពីថ្ងៃ</label><input id="from" type="date" name="from_date" value="{{ request('from_date') }}" class="form-control"></div>
            <div class="col-md-2"><label for="to" class="form-label">ដល់ថ្ងៃ</label><input id="to" type="date" name="to_date" value="{{ request('to_date') }}" class="form-control"></div>
            <div class="col-md-2"><button class="btn btn-primary">ស្វែងរក</button> <a href="{{ route('missions.index') }}" class="btn btn-light">សម្អាត</a></div>
            <div class="col-md-4"><label for="type-filter" class="form-label">ប្រភេទបេសកកម្ម</label><select id="type-filter" name="mission_type" class="form-select"><option value="">ទាំងអស់</option>
                @foreach(\Modules\HumanResource\Entities\Mission::TYPES as $value => $label)<option value="{{ $value }}" @selected(request('mission_type') === $value)>{{ $label }}</option>@endforeach
            </select></div>
        </form>
    </div></div>
    <div class="row g-3">
        <div class="{{ request('mode') === 'create' ? 'col-lg-8' : 'col-12' }}">
            <div class="card"><div class="card-header">បញ្ជីបេសកកម្ម <span class="badge bg-secondary">{{ $missions->total() }}</span></div>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead><tr><th>លរ</th><th>ចំណងជើង</th><th>គោលដៅ</th><th>កាលបរិច្ឆេទ</th><th>សមាជិក</th><th>ស្ថានភាព</th><th></th></tr></thead>
                    <tbody>@forelse($missions as $mission)
                        <tr><td>{{ $missions->firstItem() + $loop->index }}</td><td><a href="{{ route('missions.show', $mission->id) }}">{{ $mission->title }}</a></td>
                            <td>{{ $mission->destination }}</td><td>{{ $mission->start_date->format('Y-m-d') }}<br>{{ $mission->end_date->format('Y-m-d') }}</td>
                            <td>{{ $mission->assignments_count }}</td><td>@include('humanresource::attendance.missions.status')</td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('missions.show', $mission->id) }}">មើលលម្អិត</a></td></tr>
                    @empty<tr><td colspan="7" class="text-center text-muted py-5">មិនទាន់មានបេសកកម្មទេ។</td></tr>@endforelse</tbody>
                </table></div><div class="card-body">{{ $missions->links() }}</div>
            </div>
        </div>
        @can('create_mission')
            @if(request('mode') === 'create')
                <div class="col-lg-4"><div class="card"><div class="card-header">បង្កើតបេសកកម្ម</div><div class="card-body">
                    <form action="{{ route('missions.store') }}" method="POST">@csrf
                        @include('humanresource::attendance.missions.fields', ['mission' => null])
                    </form>
                </div></div></div>
            @endif
        @endcan
    </div>
@endsection
@push('js')<script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>@endpush

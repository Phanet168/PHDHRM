@extends('backend.layouts.app')
@section('title', $title)
@section('content')
    @include('humanresource::attendance.missions.nav')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h4>{{ $title }}</h4></div>
    </div>
    @include('backend.layouts.common.validation')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="card">
        <div class="card-header">បញ្ជីបេសកកម្ម <span class="badge bg-secondary">{{ $missions->total() }}</span></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>លរ</th><th>ចំណងជើង</th><th>គោលដៅ</th><th>កាលបរិច្ឆេទ</th><th>សមាជិក</th><th>ស្ថានភាព</th><th></th></tr></thead>
            <tbody>
                @forelse($missions as $mission)
                    <tr>
                        <td>{{ $missions->firstItem() + $loop->index }}</td>
                        <td><a href="{{ route('missions.show', $mission->id) }}">{{ $mission->title }}</a></td>
                        <td>{{ $mission->destination }}</td>
                        <td>{{ $mission->start_date->format('Y-m-d') }}<br>{{ $mission->end_date->format('Y-m-d') }}</td>
                        <td>{{ $mission->assignments_count }}</td>
                        <td>@include('humanresource::attendance.missions.status')</td>
                        <td class="d-flex gap-1 flex-wrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('missions.show', $mission->id) }}">មើលលម្អិត</a>
                            @if($decideAction)
                                <form action="{{ route('missions.decide', $mission->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="decision" value="{{ $decideAction }}">
                                    <button class="btn btn-sm btn-success" type="submit" onclick="return confirm('បញ្ជាក់{{ $decideAction === 'endorse' ? 'ការឯកភាព' : 'ការអនុម័ត' }}?');">
                                        {{ $decideAction === 'endorse' ? 'ឯកភាព' : 'អនុម័ត' }}
                                    </button>
                                </form>
                            @endif
                            @if($queueKey === 'approved')
                                @can('manage_mission_order')
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('missions.order.prepare', $mission->id) }}">រៀបចំលិខិត</a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">មិនមានទិន្នន័យ។</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="card-body">{{ $missions->links() }}</div>
    </div>
@endsection

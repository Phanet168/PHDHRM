@extends('planning::layouts.app')

@php
    $pageTitle = $meta['label'];
@endphp

@section('planning-content')
    <style>
        .org-tree-cell {
            min-width: 360px;
        }

        .org-tree-name {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #173b2d;
            font-weight: 700;
        }

        .org-tree-node {
            display: inline-flex;
            width: 14px;
            height: 14px;
            border-radius: 999px;
            background: #cfe8d7;
            border: 2px solid #1a6e3e;
            flex: 0 0 auto;
        }

        .org-tree-meta {
            margin-top: 0.3rem;
            color: #5f6f68;
            font-size: 0.84rem;
        }

        .org-tree-path {
            display: inline-flex;
            margin-top: 0.35rem;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            background: #eef6f0;
            color: #145530;
            font-size: 0.76rem;
            font-weight: 600;
        }
    </style>

    @php
        $unitTypeLabels = [
            'provincial_health_department' => 'មន្ទីរសុខាភិបាលខេត្ត',
            'phd_office' => 'ការិយាល័យនៃមន្ទីរ',
            'operational_district' => 'ស្រុកប្រតិបត្តិ',
            'od_office' => 'ការិយាល័យស្រុកប្រតិបត្តិ',
            'provincial_hospital' => 'មន្ទីរពេទ្យខេត្ត',
            'referral_hospital' => 'មន្ទីរពេទ្យបង្អែក',
            'health_center' => 'មណ្ឌលសុខភាព',
            'other' => 'ផ្សេងៗ',
        ];
    @endphp

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1 planning-page-title">{{ $meta['label'] }}</h1>
            <p class="planning-meta mb-0">គ្រប់គ្រងទិន្នន័យគោលសម្រាប់ការរៀបចំផែនការ និងថវិកា។</p>
        </div>
        <a href="{{ route('planning.master-data.create', $resource) }}" class="btn btn-success">
            <i class="fa fa-plus me-1"></i>បន្ថែម{{ $meta['singular_label'] }}
        </a>
    </div>

    <div class="card planning-filter-card mb-4">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">ស្វែងរក</label>
                    <input type="text" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}" placeholder="ស្វែងរក{{ $meta['label'] }}">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-sm btn-primary" type="submit"><i class="fa fa-search me-1"></i>ស្វែងរក</button>
                    <a href="{{ route('planning.master-data.index', $resource) }}" class="btn btn-sm btn-outline-secondary">កំណត់ឡើងវិញ</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card pharm-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">បញ្ជី{{ $meta['label'] }}</h6>
            <span class="planning-meta">សរុប {{ $records->total() }} ធាតុ</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle planning-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ល.រ</th>
                            @foreach ($meta['columns'] as $label)
                                <th>{{ $label }}</th>
                            @endforeach
                            <th class="text-end">សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td>{{ $records->firstItem() + $loop->index }}</td>
                                @foreach ($meta['columns'] as $key => $label)
                                    <td>
                                        @if ($resource === 'org_units' && $key === 'name')
                                            @php
                                                $level = max(1, (int) ($record->level ?? 1));
                                                $indent = ($level - 1) * 1.25;
                                                $parentTrail = collect([$record->parent?->name, $record->org_path_code])->filter()->implode(' • ');
                                            @endphp
                                            <div class="org-tree-cell">
                                                <div class="org-tree-name" style="padding-left: {{ $indent }}rem;">
                                                    <span class="org-tree-node"></span>
                                                    <span>{{ $record->name }}</span>
                                                </div>
                                                <div class="org-tree-meta" style="padding-left: calc({{ $indent }}rem + 1.5rem);">
                                                    កម្រិត {{ $level }}
                                                    @if (!empty($unitTypeLabels[$record->unit_type]))
                                                        • {{ $unitTypeLabels[$record->unit_type] }}
                                                    @endif
                                                    @if ($record->manager_name)
                                                        • {{ $record->manager_name }}
                                                    @endif
                                                </div>
                                                @if ($parentTrail !== '')
                                                    <div class="org-tree-path" style="margin-left: calc({{ $indent }}rem + 1.5rem);">
                                                        {{ $parentTrail }}
                                                    </div>
                                                @endif
                                            </div>
                                        @elseif ($resource === 'org_units' && $key === 'unit_type')
                                            {{ $unitTypeLabels[$record->unit_type] ?? $record->unit_type ?? '-' }}
                                        @elseif ($key === 'is_active')
                                            <span class="planning-badge {{ data_get($record, $key) ? 'badge-approved' : 'badge-rejected' }}">
                                                {{ data_get($record, $key) ? 'ដំណើរការ' : 'ផ្អាក' }}
                                            </span>
                                        @else
                                            {{ data_get($record, $key) ?: '-' }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-end">
                                    <a href="{{ route('planning.master-data.edit', [$resource, $record->id]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-edit me-1"></i>កែប្រែ
                                    </a>
                                    <form action="{{ route('planning.master-data.destroy', [$resource, $record->id]) }}" method="post" class="d-inline" onsubmit="return confirm('តើអ្នកពិតជាចង់លុបទិន្នន័យនេះមែនទេ?');">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fa fa-trash me-1"></i>លុប
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($meta['columns']) + 2 }}">
                                    <div class="planning-empty">មិនមានទិន្នន័យទេ។</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $records->links() }}</div>
        </div>
    </div>
@endsection

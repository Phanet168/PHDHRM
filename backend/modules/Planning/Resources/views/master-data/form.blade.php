@extends('planning::layouts.app')

@php
    $pageTitle = ($isEdit ? 'កែប្រែ' : 'បន្ថែម') . $meta['singular_label'];
    $currentCluster = $record->activityCluster ?? null;
    $currentSubProgram = $currentCluster?->subProgram;
    $currentProgram = $currentSubProgram?->program;
@endphp

@section('planning-content')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1 planning-page-title">{{ $isEdit ? 'កែប្រែ' : 'បន្ថែម' }}{{ $meta['singular_label'] }}</h1>
            <p class="planning-meta mb-0">បញ្ចូល និងកែសម្រួលទិន្នន័យគោលសម្រាប់ការរៀបចំផែនការ។</p>
        </div>
        <a href="{{ route('planning.master-data.index', $resource) }}" class="btn btn-outline-secondary">
            <i class="fa fa-arrow-left me-1"></i>ត្រឡប់ទៅបញ្ជី{{ $meta['label'] }}
        </a>
    </div>

    <form method="post" action="{{ $isEdit ? route('planning.master-data.update', [$resource, $record->id]) : route('planning.master-data.store', $resource) }}">
        @csrf
        @if ($isEdit)
            @method('put')
        @endif

        <div class="card pharm-card mb-4">
            <div class="card-header">
                <h6 class="mb-0">ព័ត៌មាន{{ $meta['singular_label'] }}</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @if ($resource === 'org_units')
                        <div class="col-md-4">
                            <label class="form-label">កូដ</label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $record->code) }}">
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ឈ្មោះ</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $record->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ឈ្មោះខ្មែរ</label>
                            <input type="text" name="name_km" class="form-control @error('name_km') is-invalid @enderror" value="{{ old('name_km', $record->name_km) }}">
                            @error('name_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">អង្គភាពលើ</label>
                            <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                                <option value="">ជ្រើសរើស</option>
                                @foreach ($options['parents'] ?? [] as $parent)
                                    <option value="{{ $parent->id }}" @selected((string) old('parent_id', $record->parent_id) === (string) $parent->id)>{{ ($parent->code ? $parent->code . ' - ' : '') . $parent->name }}</option>
                                @endforeach
                            </select>
                            @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ប្រភេទអង្គភាព</label>
                            <select name="unit_type" class="form-select @error('unit_type') is-invalid @enderror" required>
                                @foreach ([
                                    'provincial_health_department' => 'មន្ទីរសុខាភិបាលខេត្ត',
                                    'phd_office' => 'ការិយាល័យនៃមន្ទីរ',
                                    'operational_district' => 'ស្រុកប្រតិបត្តិ',
                                    'od_office' => 'ការិយាល័យស្រុកប្រតិបត្តិ',
                                    'provincial_hospital' => 'មន្ទីរពេទ្យខេត្ត',
                                    'referral_hospital' => 'មន្ទីរពេទ្យបង្អែក',
                                    'health_center' => 'មណ្ឌលសុខភាព',
                                    'other' => 'ផ្សេងៗ',
                                ] as $typeValue => $typeLabel)
                                    <option value="{{ $typeValue }}" @selected(old('unit_type', $record->unit_type ?: 'other') === $typeValue)>{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                            @error('unit_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">កម្រិត</label>
                            <input type="number" name="level" class="form-control @error('level') is-invalid @enderror" value="{{ old('level', $record->level ?: 1) }}" min="1" required>
                            @error('level')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">អង្គភាពដើម</label>
                            <select name="source_department_id" class="form-select @error('source_department_id') is-invalid @enderror">
                                <option value="">ជ្រើសរើស</option>
                                @foreach ($options['departments'] ?? [] as $department)
                                    <option value="{{ $department->id }}" @selected((string) old('source_department_id', $record->source_department_id) === (string) $department->id)>{{ $department->department_name }}</option>
                                @endforeach
                            </select>
                            @error('source_department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">លេខថានានុក្រម</label>
                            <input type="text" name="org_path_code" class="form-control @error('org_path_code') is-invalid @enderror" value="{{ old('org_path_code', $record->org_path_code) }}">
                            @error('org_path_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">អ្នកគ្រប់គ្រង</label>
                            <input type="text" name="manager_name" class="form-control @error('manager_name') is-invalid @enderror" value="{{ old('manager_name', $record->manager_name) }}">
                            @error('manager_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @elseif ($resource === 'funding_sources')
                        <div class="col-md-3">
                            <label class="form-label">កូដ</label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $record->code) }}" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ឈ្មោះ</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $record->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">ឈ្មោះខ្មែរ</label>
                            <input type="text" name="name_km" class="form-control @error('name_km') is-invalid @enderror" value="{{ old('name_km', $record->name_km) }}">
                            @error('name_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">ពិពណ៌នា</label>
                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $record->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @elseif ($resource === 'chapters')
                        <div class="col-md-3">
                            <label class="form-label">កូដ</label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $record->code) }}" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">ឈ្មោះ</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $record->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ឈ្មោះខ្មែរ</label>
                            <input type="text" name="name_km" class="form-control @error('name_km') is-invalid @enderror" value="{{ old('name_km', $record->name_km) }}">
                            @error('name_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @elseif ($resource === 'accounts')
                        <div class="col-md-4">
                            <label class="form-label">ជំពូក</label>
                            <select name="chapter_id" class="form-select @error('chapter_id') is-invalid @enderror" required>
                                <option value="">ជ្រើសរើស</option>
                                @foreach ($options['chapters'] as $chapter)
                                    <option value="{{ $chapter->id }}" @selected((string) old('chapter_id', $record->chapter_id) === (string) $chapter->id)>{{ $chapter->code }} - {{ $chapter->name }}</option>
                                @endforeach
                            </select>
                            @error('chapter_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">កូដ</label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $record->code) }}" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">ឈ្មោះ</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $record->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ឈ្មោះខ្មែរ</label>
                            <input type="text" name="name_km" class="form-control @error('name_km') is-invalid @enderror" value="{{ old('name_km', $record->name_km) }}">
                            @error('name_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @elseif ($resource === 'sub_accounts')
                        <div class="col-md-4">
                            <label class="form-label">គណនេយ្យ</label>
                            <select name="account_id" class="form-select @error('account_id') is-invalid @enderror" required>
                                <option value="">ជ្រើសរើស</option>
                                @foreach ($options['accounts'] as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('account_id', $record->account_id) === (string) $account->id)>{{ $account->chapter?->code }} / {{ $account->code }} - {{ $account->name }}</option>
                                @endforeach
                            </select>
                            @error('account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">កូដ</label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $record->code) }}" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">ឈ្មោះ</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $record->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ឈ្មោះខ្មែរ</label>
                            <input type="text" name="name_km" class="form-control @error('name_km') is-invalid @enderror" value="{{ old('name_km', $record->name_km) }}">
                            @error('name_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @elseif ($resource === 'programs')
                        @include('planning::master-data.partials.program-fields')
                    @elseif ($resource === 'sub_programs')
                        <div class="col-md-4">
                            <label class="form-label">កម្មវិធី</label>
                            <select name="program_id" class="form-select @error('program_id') is-invalid @enderror" required>
                                <option value="">ជ្រើសរើស</option>
                                @foreach ($options['programs'] as $program)
                                    <option value="{{ $program->id }}" @selected((string) old('program_id', $record->program_id) === (string) $program->id)>{{ $program->code }} - {{ $program->name }}</option>
                                @endforeach
                            </select>
                            @error('program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @include('planning::master-data.partials.program-fields')
                    @elseif ($resource === 'activity_clusters')
                        <div class="col-md-4">
                            <label class="form-label">កម្មវិធី</label>
                            <select class="form-select" data-program-select>
                                <option value="">ជ្រើសរើស</option>
                                @foreach ($options['programs'] as $program)
                                    <option value="{{ $program->id }}" @selected((string) old('program_id', $record->subProgram?->program_id) === (string) $program->id)>{{ $program->code }} - {{ $program->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">អនុកម្មវិធី</label>
                            <select name="sub_program_id" class="form-select @error('sub_program_id') is-invalid @enderror" data-sub-program-select required>
                                <option value="">ជ្រើសរើស</option>
                                @foreach ($options['sub_programs'] as $subProgram)
                                    <option value="{{ $subProgram->id }}" data-program-id="{{ $subProgram->program_id }}" @selected((string) old('sub_program_id', $record->sub_program_id) === (string) $subProgram->id)>{{ $subProgram->program?->code }} / {{ $subProgram->code }} - {{ $subProgram->name }}</option>
                                @endforeach
                            </select>
                            @error('sub_program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @include('planning::master-data.partials.program-fields')
                    @elseif ($resource === 'indicators')
                        <div class="col-md-4">
                            <label class="form-label">កម្មវិធី</label>
                            <select name="program_id" class="form-select @error('program_id') is-invalid @enderror" data-program-select required>
                                <option value="">ជ្រើសរើស</option>
                                @foreach ($options['programs'] as $program)
                                    <option value="{{ $program->id }}" @selected((string) old('program_id', $currentProgram?->id) === (string) $program->id)>{{ $program->code }} - {{ $program->name }}</option>
                                @endforeach
                            </select>
                            @error('program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">អនុកម្មវិធី</label>
                            <select name="sub_program_id" class="form-select @error('sub_program_id') is-invalid @enderror" data-sub-program-select required>
                                <option value="">ជ្រើសរើស</option>
                                @foreach ($options['sub_programs'] as $subProgram)
                                    <option value="{{ $subProgram->id }}" data-program-id="{{ $subProgram->program_id }}" @selected((string) old('sub_program_id', $currentSubProgram?->id) === (string) $subProgram->id)>{{ $subProgram->program?->code }} / {{ $subProgram->code }} - {{ $subProgram->name }}</option>
                                @endforeach
                            </select>
                            @error('sub_program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ចង្កោមសកម្មភាព</label>
                            <select name="activity_cluster_id" class="form-select @error('activity_cluster_id') is-invalid @enderror" data-activity-cluster-select required>
                                <option value="">ជ្រើសរើស</option>
                                @foreach ($options['activity_clusters'] as $cluster)
                                    <option value="{{ $cluster->id }}" data-sub-program-id="{{ $cluster->sub_program_id }}" @selected((string) old('activity_cluster_id', $record->activity_cluster_id) === (string) $cluster->id)>{{ $cluster->subProgram?->program?->code }} / {{ $cluster->subProgram?->code }} / {{ $cluster->code }} - {{ $cluster->name }}</option>
                                @endforeach
                            </select>
                            @error('activity_cluster_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">កូដ</label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $record->code) }}" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">សូចនាករ</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $record->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ឈ្មោះខ្មែរ</label>
                            <input type="text" name="name_km" class="form-control @error('name_km') is-invalid @enderror" value="{{ old('name_km', $record->name_km) }}">
                            @error('name_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ឯកតា</label>
                            <input type="text" name="unit_of_measure" class="form-control @error('unit_of_measure') is-invalid @enderror" value="{{ old('unit_of_measure', $record->unit_of_measure) }}" placeholder="ឧ. ដង, %, នាក់">
                            @error('unit_of_measure')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ប្រភេទតម្លៃ</label>
                            <select name="value_type" class="form-select @error('value_type') is-invalid @enderror" required>
                                @foreach (['number' => 'ចំនួន', 'percentage' => 'ភាគរយ', 'currency' => 'រូបិយប័ណ្ណ', 'text' => 'អក្សរ'] as $typeValue => $typeLabel)
                                    <option value="{{ $typeValue }}" @selected(old('value_type', $record->value_type ?: 'number') === $typeValue)>{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                            @error('value_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">ពិពណ៌នា</label>
                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $record->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    @if (!in_array($resource, ['org_units', 'funding_sources'], true))
                        <div class="col-md-3">
                            <label class="form-label">លំដាប់</label>
                            <input type="number" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $record->sort_order ?: 1) }}" min="1">
                            @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <div class="col-md-3">
                        <label class="form-label">ស្ថានភាព</label>
                        <select name="is_active" class="form-select">
                            <option value="1" @selected((string) old('is_active', $record->is_active ?? 1) === '1')>ដំណើរការ</option>
                            <option value="0" @selected((string) old('is_active', $record->is_active ?? 1) === '0')>ផ្អាក</option>
                        </select>
                    </div>

                    @if (in_array($resource, ['programs', 'sub_programs', 'activity_clusters'], true))
                        <div class="col-12">
                            <label class="form-label">ពិពណ៌នា</label>
                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $record->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between gap-2">
            <a href="{{ route('planning.master-data.index', $resource) }}" class="btn btn-outline-secondary">បោះបង់</a>
            <button type="submit" class="btn btn-success">
                <i class="fa fa-save me-1"></i>{{ $isEdit ? 'រក្សាទុកការកែប្រែ' : 'រក្សាទុក' }}
            </button>
        </div>
    </form>
@endsection

@push('planning-scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const programSelect = document.querySelector('[data-program-select]');
            const subProgramSelect = document.querySelector('[data-sub-program-select]');
            const activityClusterSelect = document.querySelector('[data-activity-cluster-select]');

            function filterOptions(select, matcher) {
                if (!select) return;

                Array.from(select.options).forEach(function (option, index) {
                    if (index === 0) {
                        option.hidden = false;
                        return;
                    }

                    option.hidden = !matcher(option);
                });
            }

            function resetSelectIfHidden(select) {
                if (!select || !select.selectedOptions.length) return;

                const selected = select.selectedOptions[0];
                if (selected.hidden) {
                    select.value = '';
                }
            }

            function syncSubPrograms() {
                if (!programSelect || !subProgramSelect) return;

                const programId = programSelect.value;
                filterOptions(subProgramSelect, function (option) {
                    return !programId || option.dataset.programId === programId;
                });
                resetSelectIfHidden(subProgramSelect);
            }

            function syncActivityClusters() {
                if (!subProgramSelect || !activityClusterSelect) return;

                const subProgramId = subProgramSelect.value;
                filterOptions(activityClusterSelect, function (option) {
                    return !subProgramId || option.dataset.subProgramId === subProgramId;
                });
                resetSelectIfHidden(activityClusterSelect);
            }

            if (programSelect) {
                programSelect.addEventListener('change', function () {
                    syncSubPrograms();
                    syncActivityClusters();
                });
            }

            if (subProgramSelect) {
                subProgramSelect.addEventListener('change', syncActivityClusters);
            }

            syncSubPrograms();
            syncActivityClusters();
        });
    </script>
@endpush

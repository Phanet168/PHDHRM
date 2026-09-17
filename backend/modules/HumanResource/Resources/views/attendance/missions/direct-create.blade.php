@extends('backend.layouts.app')
@section('title', 'បង្កើតបេសកកម្មដោយផ្ទាល់')
@section('content')
    <meta name="mission-lunar-date-url" content="{{ route('missions.lunar-date') }}">
    @include('humanresource::attendance.missions.nav')
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:56px;height:56px;">
            <i class="fa fa-bolt fs-3"></i>
        </div>
        <div>
            <h3 class="mb-0 fw-bold">បង្កើតបេសកកម្មដោយផ្ទាល់</h3>
            <p class="text-muted mb-0">សម្រាប់មន្ត្រីគ្រប់គ្រងលិខិតបេសកកម្ម — មិនចាំបាច់ឆ្លងកាត់ការអនុម័តទេ អាចរៀបចំលិខិតបញ្ជាបានភ្លាមៗ</p>
        </div>
    </div>
    @include('backend.layouts.common.validation')
    <form action="{{ route('missions.direct.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="card mb-3">
            <div class="card-header bg-light"><i class="fa fa-clipboard-list me-2 text-primary"></i><span class="fs-5 fw-bold">ប្រភពនៃការបង្កើត</span></div>
            <div class="card-body">
                <select name="creation_source" class="form-select" required>
                    <option value="invitation_letter" @selected(old('creation_source') === 'invitation_letter')>លិខិតអញ្ជើញ</option>
                    <option value="director_instruction" @selected(old('creation_source') === 'director_instruction')>សេចក្ដីណែនាំរបស់ប្រធានមន្ទីរ</option>
                    <option value="administration_direct" @selected(old('creation_source', 'administration_direct') === 'administration_direct')>រដ្ឋបាលកំណត់ដោយផ្ទាល់</option>
                    <option value="other" @selected(old('creation_source') === 'other')>ផ្សេងៗ</option>
                </select>
            </div>
        </div>
        @include('humanresource::attendance.missions.fields', ['mission' => null, 'hideStatusField' => true, 'hideOrderNumberField' => true])
        @include('humanresource::attendance.missions.extra-fields', ['mission' => null])
    </form>
@endsection
@push('js')
    <script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
    <script src="{{ module_asset('HumanResource/js/mission-destination.js') }}"></script>
    <script src="{{ module_asset('HumanResource/js/mission-form.js') }}"></script>
@endpush

@extends('backend.layouts.app')
@section('title', localize('employee_performance', 'ការវាយតម្លៃសមិទ្ធផលបុគ្គលិក'))
@section('content')
    <!--/.Content Header (Page header)-->
    <div class="body-content">
        @include('backend.layouts.common.validation')
        <div class="container" id="print-table">
            <div class="card card-default mb-4">
                <div class="card-body">
                    <div class="text-end d-print-none" id="print">
                        <button type="button" class="btn btn-warning" id="btnPrint">
                            <i class="fa fa-print"></i>
                        </button>
                    </div>
                    <div id="printArea">
                        <div style="padding: 20px;">
                            <div class="row">
                                <div class="col-md-3">
                                    <img class="" style="width:80px; margin-top: -15px;"
                                        src="{{ app_setting()->logo ? url('/public/storage/' . app_setting()->logo) : asset('public/newAdmin/assets/dist/img/logo.png') }}"
                                        alt="">
                                </div>
                                <div class="col-md-5">
                                    <h3 style="text-align:center;">{{ date('Y', strtotime($employee_performance->date)) }}
                                        {{ strtoupper(localize('performance_appraisal', 'ការវាយតម្លៃសមិទ្ធផល')) }}</h3>
                                </div>
                                <div class="col-md-4" style="text-align:right;">
                                    <span>{{ localize('serial_no', 'លេខស៊េរី') }}: #{{ $employee_performance->performance_code }}</span>
                                </div>
                            </div>
                            <div class="row mt-5">
                                <div class="col-md-6 mt-3">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <span class="fs-16 fw-700">{{ localize('name_of_employee') }}:</span>
                                        </div>
                                        <div class="col-md-7 d-flex justify-content-center"
                                            style="border-bottom: 1px solid #000;">
                                            <span class="fs-16">{{ $employee_performance->employee?->full_name }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mt-3">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="fs-16 fw-700">{{ localize('department') }}:</span>
                                        </div>
                                        <div class="col-md-8 d-flex justify-content-center"
                                            style="border-bottom: 1px solid #000;">
                                            <span
                                                class="fs-16">{{ $employee_performance->employee?->department?->department_name }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mt-3">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="fs-16 fw-700">{{ localize('position', 'មុខតំណែង') }}:</span>
                                        </div>
                                        <div class="col-md-8 d-flex justify-content-center"
                                            style="border-bottom: 1px solid #000;">
                                            <span
                                                class="fs-16">{{ $employee_performance->employee?->position?->position_name }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mt-3">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="fs-16 fw-700">{{ localize('review_period', 'រយៈពេលវាយតម្លៃ') }}:</span>
                                        </div>
                                        <div class="col-md-8 d-flex justify-content-center"
                                            style="border-bottom: 1px solid #000;">
                                            <span class="fs-16"> {{ $employee_performance->review_period }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12 mt-3">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <span class="fs-16 fw-700">{{ localize('supervisor_name_and_position', 'ឈ្មោះ និងមុខតំណែងរបស់អ្នកគ្រប់គ្រង/ប្រធានអង្គភាព') }}
                                                :</span>
                                        </div>
                                        <div class="col-md-7 d-flex justify-content-center"
                                            style="border-bottom: 1px solid #000;">
                                            <span class="fs-16"> {{ $employee_performance->position_of_supervisor }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <br>
                            <div class="row">
                                <div class="col-md-12">
                                    <p class="fs-17 mt-3 fs-i">{{ localize('performance_assessment_instruction', 'សូមផ្តល់ការវាយតម្លៃសមិទ្ធផលការងាររបស់បុគ្គលិកក្នុងអំឡុងពេលវាយតម្លៃ ដោយប្រើមាត្រដ្ឋានខាងក្រោម។ សូមផ្តល់ឧទាហរណ៍នៅពេលចាំបាច់ ហើយអាចប្រើសន្លឹកបន្ថែមបានប្រសិនបើត្រូវការ។') }}
                                    </p>
                                </div>
                            </div>
                            <table class="table table-bordered w-65 mt-3">
                                <thead>
                                    <tr>
                                        <th>P</th>
                                        <th>NI</th>
                                        <th>G</th>
                                        <th>VG</th>
                                        <th>E</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Poor</td>
                                        <td>Needs Improvement</td>
                                        <td>Good</td>
                                        <td>Very Good</td>
                                        <td>Excellent</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="row">
                                <h3 class="mt-3">A. ASSESSMENT OF GOALS/OBJECTIVES SET DURING THE REVIEW PERIOD</h3>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Criteria</th>
                                            <th>P <br> (0)</th>
                                            <th>NI <br> (3)</th>
                                            <th>G <br> (6)</th>
                                            <th>VG <br> (9)</th>
                                            <th>E <br> (12)</th>
                                            <th>Score</th>
                                            <th>Comments and Examples</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        @foreach ($asssesment_one as $i => $item)
                                            <tr>
                                                <td>{{ $item->employee_performance_criteria?->title }}</td>
                                                <td><?php if ($item->emp_perform_eval == 0) {
                                                    echo '0';
                                                } ?></td>
                                                <td><?php if ($item->emp_perform_eval == 3) {
                                                    echo '3';
                                                } ?></td>
                                                <td><?php if ($item->emp_perform_eval == 6) {
                                                    echo '6';
                                                } ?></td>
                                                <td><?php if ($item->emp_perform_eval == 9) {
                                                    echo '9';
                                                } ?></td>
                                                <td><?php if ($item->emp_perform_eval == 12) {
                                                    echo '12';
                                                } ?></td>
                                                <td>{{ $item->score }}</td>
                                                <td>{{ $item->comments }}</td>
                                            </tr>
                                        @endforeach
                                        <tr>
                                            <td></td>
                                            <td colspan="5">{{ localize('total_score_max_60', 'ពិន្ទុសរុប (អតិបរមា = 60)') }}</td>
                                            <td>{{ $asssesment_one->sum('score') }}</td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>


                            <div class="row">
                                <h3 class="mt-3">{{ strtoupper(localize('performance_section_b', 'ខ. ការវាយតម្លៃស្តង់ដារ និងសូចនាករសមិទ្ធផលផ្សេងទៀត')) }}</h3>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{{ localize('criteria', 'លក្ខខណ្ឌវាយតម្លៃ') }}</th>
                                            <th>P <br> (2)</th>
                                            <th>NI <br> (4)</th>
                                            <th>G <br> (6)</th>
                                            <th>VG <br> (9)</th>
                                            <th>E <br> (10)</th>
                                            <th>{{ localize('score', 'ពិន្ទុ') }}</th>
                                            <th>{{ localize('comments_and_examples', 'មតិយោបល់ និងឧទាហរណ៍') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($asssesment_two as $item_two)
                                            <tr>
                                                <td>{{ $item_two->employee_performance_criteria?->title }}</td>
                                                <td><?php if ($item_two->emp_perform_eval == 2) {
                                                    echo '2';
                                                } ?></td>
                                                <td><?php if ($item_two->emp_perform_eval == 4) {
                                                    echo '4';
                                                } ?></td>
                                                <td><?php if ($item_two->emp_perform_eval == 6) {
                                                    echo '6';
                                                } ?></td>
                                                <td><?php if ($item_two->emp_perform_eval == 9) {
                                                    echo '9';
                                                } ?></td>
                                                <td><?php if ($item_two->emp_perform_eval == 10) {
                                                    echo '10';
                                                } ?></td>
                                                <td>{{ $item_two->score }}</td>
                                                <td>{{ $item_two->comments }}</td>
                                            </tr>
                                        @endforeach
                                        <tr>
                                            <td></td>
                                            <td colspan="5">{{ localize('total_score_max_40', 'ពិន្ទុសរុប (អតិបរមា = 40)') }}</td>
                                            <td>{{ $asssesment_two->sum('score') }}</td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="row">
                                <h3 class="mt-3">{{ strtoupper(localize('performance_section_c', 'គ. ពិន្ទុសរុប')) }}</h3>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{{ localize('total_score_score_a_b', 'ពិន្ទុសរុប (ពិន្ទុ A + ពិន្ទុ B)') }}</th>
                                            <th>{{ localize('overall_comments_reviewer', 'មតិយោបល់/អនុសាសន៍រួមរបស់អ្នកវាយតម្លៃ') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <table style="border: solid white !important;">
                                                    <tr style="border: 0px;">
                                                        <th style="padding: 10px; border: solid white !important;">
                                                            {{ $asssesment_one->sum('score') }} </th>
                                                        <th style="padding: 10px; border: solid white !important;">+</th>
                                                        <th style="padding: 10px; border: solid white !important;">
                                                            {{ $asssesment_two->sum('score') }} </th>
                                                        <th style="padding: 10px; border: solid white !important;">=</th>
                                                        <th style="padding: 10px; border: solid white !important;">
                                                            {{ $asssesment_one->sum('score') + $asssesment_two->sum('score') }}
                                                        </th>
                                                    </tr>
                                                </table>
                                                <div>
                                                    <p class="fw-700">{{ localize('classification_of_employee', 'ចំណាត់ថ្នាក់បុគ្គលិក') }}:</p>
                                                </div>
                                                <table style="border: solid white !important;">
                                                    <tr>
                                                        <th
                                                            style="padding: 5px; text-align: center; border: solid white !important;">
                                                            EE</th>
                                                        <th
                                                            style="padding: 5px; text-align: center; border: solid white !important;">
                                                            AE</th>
                                                        <th
                                                            style="padding: 5px; text-align: center; border: solid white !important;">
                                                            UE</th>
                                                    </tr>
                                                    <tr>
                                                        <th style="padding: 10px; border: solid white !important;">(80-100)
                                                        </th>
                                                        <th style="padding: 10px; border: solid white !important;">(75-85)
                                                        </th>
                                                        <th style="padding: 10px; border: solid white !important;">(0-70)
                                                        </th>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td>
                                                <div>
                                                    <p class="fw-700">{{ localize('name') }}:</p>
                                                </div>
                                                <div>
                                                    <p class="fw-700">{{ localize('signature') }}:</p>
                                                </div>
                                                <div>
                                                    <p class="fw-700">{{ localize('date') }}:</p>
                                                </div>
                                                <div>
                                                    <p class="fw-700">{{ localize('next_review_period', 'រយៈពេលវាយតម្លៃបន្ទាប់') }}:</p>
                                                </div>
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <p class="fs-17 mt-3 fs-i">{{ localize('pip_required_note', 'ប្រសិនបើបុគ្គលិកមានលទ្ធផលការងារមិនអាចទទួលយកបាន សូមភ្ជាប់ផែនការកែលម្អសមិទ្ធផល (PIP) ផង។') }}</p>
                                </div>
                            </div>

                            <br>

                            <div class="row">
                                <h3 class="mt-3">{{ strtoupper(localize('performance_section_d', 'ឃ. មតិយោបល់របស់បុគ្គលិក')) }}</h3>
                                <div class="form-group">
                                    <textarea class="form-control" id="exampleFormControlTextarea1" rows="3">{{ $employee_performance->employee_comments }}</textarea>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-4">
                                    <p>{{ localize('name') }}:</p>
                                </div>
                                <div class="col-md-4">
                                    <p>{{ localize('signature') }}:</p>
                                </div>
                                <div class="col-md-4">
                                    <p>{{ localize('date') }}:</p>
                                </div>
                            </div>

                            <br>

                            <div class="row">
                                <h3 class="mt-3">{{ strtoupper(localize('performance_section_e', 'ង. ផែនការអភិវឌ្ឍន៍')) }}</h3>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th width="25%">{{ localize('recommended_areas_for_improvement', 'ផ្នែកដែលណែនាំឲ្យកែលម្អ/អភិវឌ្ឍ') }}</th>
                                            <th width="20%">{{ localize('expected_outcomes', 'លទ្ធផលរំពឹងទុក') }}</th>
                                            <th width="35%">{{ localize('responsible_persons_for_plan', 'អ្នកទទួលខុសត្រូវជួយអនុវត្តផែនការ') }}</th>
                                            <th width="10%">{{ localize('start_date', 'ថ្ងៃចាប់ផ្តើម') }}</th>
                                            <th width="10%">{{ localize('end_date', 'ថ្ងៃបញ្ចប់') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($development_plans as $key => $row)
                                            <tr>
                                                <td>{{ $row->recommend_areas }}</td>
                                                <td>
                                                    {{ $row->expected_outcomes }}
                                                </td>
                                                <td>{{ $row->responsible_person }}</td>
                                                <td>{{ $row->start_date }}</td>
                                                <td>{{ $row->end_date }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="row">
                                <h3 class="mt-3">{{ strtoupper(localize('performance_section_f', 'ច. គោលដៅសំខាន់សម្រាប់រយៈពេលវាយតម្លៃបន្ទាប់')) }}</h3>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{{ localize('goals_set_with_employee', 'គោលដៅដែលបានកំណត់ និងព្រមព្រៀងជាមួយបុគ្គលិក') }}</th>
                                            <th>{{ localize('proposed_completion_period', 'រយៈពេលបញ្ចប់ដែលបានស្នើ') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($key_goals as $key => $goal)
                                            <tr>
                                                <td>{{ $goal->key_goals }}</td>
                                                <td>{{ $goal->completion_period }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="row mt-20">
                                <h3 class="mt-3">G. REVIEW / COMMENTS</h3>
                                <div class="col-md-4">
                                    <p>Name:</p>
                                </div>
                                <div class="col-md-4">
                                    <p>Signature:</p>
                                </div>
                                <div class="col-md-4">
                                    <p>Date:</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
    </div>
    </div>
    <!--/.body content-->
@endsection
@push('js')
    <script src="{{ module_asset('HumanResource/js/employee-performance-show.js') }}"></script>
@endpush

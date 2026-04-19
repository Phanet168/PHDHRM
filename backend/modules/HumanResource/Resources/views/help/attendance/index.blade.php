@extends('backend.layouts.app')

@section('title', $isEnglish ? 'Attendance Help Center' : 'ជំនួយការគ្រប់គ្រងវត្តមាន')

@push('css')
    <style>
        .attendance-help-layout {
            display: grid;
            grid-template-columns: 300px minmax(0, 1fr);
            gap: 20px;
        }

        .attendance-help-sidebar .list-group-item {
            border: 0;
            border-bottom: 1px solid rgba(25, 135, 84, 0.1);
            padding: 12px 14px;
            font-size: 0.95rem;
        }

        .attendance-help-sidebar .list-group-item.active {
            background: linear-gradient(135deg, #198754, #157347);
            border-color: transparent;
            color: #fff;
        }

        .attendance-help-content .card-header {
            background: linear-gradient(135deg, #f1fbf7, #edf6ff);
        }

        .attendance-help-article {
            font-size: 15px;
            line-height: 1.75;
            color: #17352a;
        }

        .attendance-help-article h1,
        .attendance-help-article h2,
        .attendance-help-article h3 {
            color: #10462a;
            margin-top: 1.4rem;
            margin-bottom: 0.75rem;
        }

        .attendance-help-article h1 {
            font-size: 1.8rem;
            margin-top: 0;
        }

        .attendance-help-article h2 {
            font-size: 1.3rem;
            border-bottom: 1px solid rgba(25, 135, 84, 0.15);
            padding-bottom: 0.35rem;
        }

        .attendance-help-article ul,
        .attendance-help-article ol {
            padding-left: 1.4rem;
        }

        .attendance-help-article li + li {
            margin-top: 0.35rem;
        }

        .attendance-help-article code {
            background: #f2f7f3;
            color: #145530;
            padding: 2px 6px;
            border-radius: 6px;
        }

        .attendance-help-tip {
            background: #edf7f0;
            border: 1px solid rgba(25, 135, 84, 0.14);
            border-radius: 14px;
        }

        @media (max-width: 991.98px) {
            .attendance-help-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @include('humanresource::attendance_header')

    <div class="body-content">
        <div class="attendance-help-layout">
            <div class="card attendance-help-sidebar">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fa fa-life-ring me-1"></i>{{ $isEnglish ? 'Help topics' : 'ប្រធានបទជំនួយ' }}
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($articles as $slug => $item)
                        <a href="{{ route('attendances.help', ['article' => $slug]) }}"
                            class="list-group-item list-group-item-action {{ $activeArticle === $slug ? 'active' : '' }}">
                            <i class="fa {{ $item['icon'] }} me-2"></i>{{ $isEnglish ? $item['title_en'] : $item['title_km'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="card attendance-help-content mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">{{ $articleTitle }}</h5>
                            <div class="text-muted small">
                                {{ $isEnglish
                                    ? 'Practical guide for mobile scan flow, web attendance workflows, policy, and troubleshooting.'
                                    : 'មគ្គុទេសក៍អនុវត្តសម្រាប់ការស្កេនលើទូរសព្ទ លំហូរ Web ការកំណត់គោលនយោបាយ និងដោះស្រាយបញ្ហា។' }}
                            </div>
                        </div>
                        <a href="{{ route('attendances.help') }}" class="btn btn-sm btn-outline-success">
                            <i class="fa fa-home me-1"></i>{{ $isEnglish ? 'Back to overview' : 'ត្រឡប់ទៅមើលទូទៅ' }}
                        </a>
                    </div>
                    <div class="card-body attendance-help-article">
                        {!! $articleHtml !!}
                    </div>
                </div>

                <div class="card attendance-help-tip">
                    <div class="card-body py-3">
                        <div class="fw-semibold mb-1">{{ $isEnglish ? 'Usage tip' : 'គន្លឹះប្រើប្រាស់' }}</div>
                        <div class="small text-muted mb-0">
                            {{ $isEnglish
                                ? 'Start from overview, then train employee flow and admin flow before rolling out correction workflow.'
                                : 'សូមចាប់ផ្តើមពីមើលទូទៅ មុនបណ្តុះបណ្តាលលំហូរបុគ្គលិក និង HR/Admin បន្ទាប់មកទើបអនុវត្តលំហូរកែសម្រួលករណីខុស។' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


@extends('backend.layouts.app')

@section('title', localize('org_structure_help_center', app()->getLocale() === 'en' ? 'Org Structure Help Center' : 'ជំនួយគ្រប់គ្រងរចនាសម្ព័ន្ធអង្គភាព'))

@push('css')
    <style>
        .org-help-layout {
            display: grid;
            grid-template-columns: 300px minmax(0, 1fr);
            gap: 20px;
        }

        .org-help-sidebar .list-group-item {
            border: 0;
            border-bottom: 1px solid rgba(44, 111, 187, 0.12);
            padding: 12px 14px;
            font-size: 0.95rem;
        }

        .org-help-sidebar .list-group-item.active {
            background: linear-gradient(135deg, #2c6fbb, #1e4f8a);
            border-color: transparent;
            color: #fff;
        }

        .org-help-content .card-header {
            background: linear-gradient(135deg, #eff6ff, #edf7ff);
        }

        .org-help-article {
            font-size: 15px;
            line-height: 1.75;
            color: #153454;
        }

        .org-help-article h1,
        .org-help-article h2,
        .org-help-article h3 {
            color: #12345a;
            margin-top: 1.4rem;
            margin-bottom: 0.75rem;
        }

        .org-help-article h1 {
            font-size: 1.8rem;
            margin-top: 0;
        }

        .org-help-article h2 {
            font-size: 1.3rem;
            border-bottom: 1px solid rgba(44, 111, 187, 0.16);
            padding-bottom: 0.35rem;
        }

        .org-help-article ul,
        .org-help-article ol {
            padding-left: 1.4rem;
        }

        .org-help-article li + li {
            margin-top: 0.35rem;
        }

        .org-help-article code {
            background: #f0f5fb;
            color: #194b7a;
            padding: 2px 6px;
            border-radius: 6px;
        }

        .org-help-tip {
            background: #eff6ff;
            border: 1px solid rgba(44, 111, 187, 0.16);
            border-radius: 14px;
        }

        @media (max-width: 991.98px) {
            .org-help-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @include('humanresource::master-data.org-structure.header')

    @php
        $ui = static function (string $key, string $en, string $km): string {
            return localize($key, app()->getLocale() === 'en' ? $en : $km);
        };
    @endphp

    <div class="body-content">
        <div class="org-help-layout">
            <div class="card org-help-sidebar">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fa fa-life-ring me-1"></i>{{ $ui('help_topics', 'Help topics', 'ប្រធានបទជំនួយ') }}
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($articles as $slug => $item)
                        <a href="{{ route('org-structure.help', ['article' => $slug]) }}"
                            class="list-group-item list-group-item-action {{ $activeArticle === $slug ? 'active' : '' }}">
                            @php
                                $titleFallback = $isEnglish ? $item['title_en'] : $item['title_km'];
                                $titleKey = (string) ($item['title_key'] ?? '');
                                $titleText = $titleKey !== '' ? localize($titleKey, $titleFallback) : $titleFallback;
                            @endphp
                            <i class="fa {{ $item['icon'] }} me-2"></i>{{ $titleText }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="card org-help-content mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">{{ $articleTitle }}</h5>
                            <div class="text-muted small">
                                {{ $ui(
                                    'org_structure_help_subtitle',
                                    'Practical guide for complex Org Structure setup and troubleshooting',
                                    'មគ្គុទេសក៍អនុវត្តសម្រាប់ការកំណត់ស្មុគស្មាញ និងដោះស្រាយបញ្ហា ក្នុងផ្ទាំងរចនាសម្ព័ន្ធអង្គភាព'
                                ) }}
                            </div>
                        </div>
                        <a href="{{ route('org-structure.help') }}" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-home me-1"></i>{{ $ui('back_to_overview', 'Back to overview', 'ត្រឡប់ទៅទិដ្ឋភាពទូទៅ') }}
                        </a>
                    </div>
                    <div class="card-body org-help-article">
                        {!! $articleHtml !!}
                    </div>
                </div>

                <div class="card org-help-tip">
                    <div class="card-body py-3">
                        <div class="fw-semibold mb-1">{{ $ui('usage_tip', 'Usage tip', 'គន្លឹះប្រើប្រាស់') }}</div>
                        <div class="small text-muted mb-0">
                            {{ $ui(
                                'org_structure_help_recommended_sequence',
                                'Recommended sequence: Responsibilities -> Org Position Matrix -> User Assignments -> Org Role Permission Matrix -> Workflow Policy Matrix -> End-to-end test.',
                                'លំដាប់ណែនាំ: Responsibilities -> Org Position Matrix -> User Assignments -> Org Role Permission Matrix -> Workflow Policy Matrix -> សាកល្បង flow ពេញមួយជុំ។'
                            ) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

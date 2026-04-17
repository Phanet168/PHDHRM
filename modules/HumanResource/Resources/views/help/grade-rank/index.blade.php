@extends('backend.layouts.app')

@section('title', app()->getLocale() === 'en' ? 'Grade/Rank Help Center' : 'ជំនួយ៖ គ្រប់គ្រងថ្នាក់ និងឋានន្តរស័ក្តិ')

@push('css')
    <style>
        .grade-help-layout {
            display: grid;
            grid-template-columns: 290px minmax(0, 1fr);
            gap: 20px;
        }

        .grade-help-sidebar .list-group-item {
            border: 0;
            border-bottom: 1px solid rgba(25, 135, 84, 0.1);
            padding: 12px 14px;
            font-size: 0.95rem;
        }

        .grade-help-sidebar .list-group-item.active {
            background: linear-gradient(135deg, #198754, #157347);
            border-color: transparent;
            color: #fff;
        }

        .grade-help-content .card-header {
            background: linear-gradient(135deg, #f1fbf7, #edf6ff);
        }

        .grade-help-article {
            font-size: 15px;
            line-height: 1.75;
            color: #17352a;
        }

        .grade-help-article h1,
        .grade-help-article h2,
        .grade-help-article h3 {
            color: #10462a;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }

        .grade-help-article h1 {
            font-size: 1.8rem;
            margin-top: 0;
        }

        .grade-help-article h2 {
            font-size: 1.3rem;
            border-bottom: 1px solid rgba(25, 135, 84, 0.15);
            padding-bottom: 0.35rem;
        }

        .grade-help-article ul,
        .grade-help-article ol {
            padding-left: 1.4rem;
        }

        .grade-help-article li + li {
            margin-top: 0.35rem;
        }

        .grade-help-article code {
            background: #f2f7f3;
            color: #145530;
            padding: 2px 6px;
            border-radius: 6px;
        }

        .grade-help-tip {
            background: #edf7f0;
            border: 1px solid rgba(25, 135, 84, 0.14);
            border-radius: 14px;
        }

        @media (max-width: 991.98px) {
            .grade-help-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @include('humanresource::employee_header')

    <div class="body-content">
        <div class="grade-help-layout">
            <div class="card grade-help-sidebar">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fa fa-life-ring me-1"></i>{{ app()->getLocale() === 'en' ? 'Help topics' : 'ប្រធានបទជំនួយ' }}
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    @foreach($articles as $slug => $item)
                        <a href="{{ route('employee-pay-promotions.help', ['article' => $slug]) }}"
                            class="list-group-item list-group-item-action {{ $activeArticle === $slug ? 'active' : '' }}">
                            <i class="fa {{ $item['icon'] }} me-2"></i>{{ $item['title'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="card grade-help-content mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">{{ $articleMeta['title'] }}</h5>
                            <div class="text-muted small">
                                {{ app()->getLocale() === 'en'
                                    ? 'Guide for using Grade and Rank Management flow in the HR module'
                                    : 'មគ្គុទេសក៍ប្រើប្រាស់លំហូរការងារ គ្រប់គ្រងថ្នាក់ និងឋានន្តរស័ក្តិ ក្នុងម៉ូឌុល HR' }}
                            </div>
                        </div>
                        <a href="{{ route('employee-pay-promotions.help') }}" class="btn btn-sm btn-outline-success">
                            <i class="fa fa-home me-1"></i>{{ app()->getLocale() === 'en' ? 'Back to overview' : 'ត្រឡប់ទៅទិដ្ឋភាពទូទៅ' }}
                        </a>
                    </div>
                    <div class="card-body grade-help-article">
                        {!! $articleHtml !!}
                    </div>
                </div>

                <div class="card grade-help-tip">
                    <div class="card-body py-3">
                        <div class="fw-semibold mb-1">{{ app()->getLocale() === 'en' ? 'Usage tip' : 'គន្លឹះក្នុងការប្រើ Help' }}</div>
                        <div class="small text-muted mb-0">
                            {{ app()->getLocale() === 'en'
                                ? 'Start from Overview and Workflow first, then continue to Create Request and Approvals.'
                                : 'សូមចាប់ផ្តើមពី ទិដ្ឋភាពទូទៅ និង លំហូរការងារ សិន បន្ទាប់មកអានផ្នែក បង្កើតសំណើ និង ការអនុម័ត។' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


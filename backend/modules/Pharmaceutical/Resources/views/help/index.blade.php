@extends('backend.layouts.app')

@section('title', localize('help_center', 'Help Center'))

@push('css')
<style>
    .pharm-help-layout {
        display: grid;
        grid-template-columns: 280px minmax(0, 1fr);
        gap: 20px;
    }

    .pharm-help-sidebar,
    .pharm-help-content {
        border: 0;
        box-shadow: var(--pharm-shadow);
    }

    .pharm-help-sidebar .list-group-item {
        border: 0;
        border-bottom: 1px solid rgba(26, 110, 62, 0.08);
        padding: 12px 14px;
    }

    .pharm-help-sidebar .list-group-item.active {
        background: linear-gradient(135deg, var(--pharm-primary), var(--pharm-primary-dark));
        border-color: transparent;
        color: #fff;
    }

    .pharm-help-content .card-header {
        background: linear-gradient(135deg, #f4faf6, #eef7fb);
    }

    .pharm-help-article {
        font-size: 15px;
        line-height: 1.75;
        color: #17352a;
    }

    .pharm-help-article h1,
    .pharm-help-article h2,
    .pharm-help-article h3 {
        color: #10462a;
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
    }

    .pharm-help-article h1 {
        font-size: 1.8rem;
        margin-top: 0;
    }

    .pharm-help-article h2 {
        font-size: 1.3rem;
        border-bottom: 1px solid rgba(26, 110, 62, 0.12);
        padding-bottom: 0.35rem;
    }

    .pharm-help-article ul,
    .pharm-help-article ol {
        padding-left: 1.4rem;
    }

    .pharm-help-article li + li {
        margin-top: 0.35rem;
    }

    .pharm-help-article code {
        background: #f2f7f3;
        color: #145530;
        padding: 2px 6px;
        border-radius: 6px;
    }

    .pharm-help-tip {
        background: #edf7f0;
        border: 1px solid rgba(26, 110, 62, 0.14);
        border-radius: 14px;
    }

    @media (max-width: 991.98px) {
        .pharm-help-layout {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="pharm-help-layout">
            <div class="card pharm-card pharm-help-sidebar">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa fa-life-ring me-1"></i>{{ localize('help_topics', 'ប្រធានបទជំនួយ') }}</h6>
                </div>
                <div class="list-group list-group-flush">
                    @foreach($articles as $slug => $item)
                        <a href="{{ route('pharmaceutical.help', ['article' => $slug]) }}"
                           class="list-group-item list-group-item-action {{ $activeArticle === $slug ? 'active' : '' }}">
                            <i class="fa {{ $item['icon'] }} me-2"></i>{{ $item['title'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="card pharm-card pharm-help-content mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">{{ $articleMeta['title'] }}</h5>
                            <div class="text-muted small">{{ localize('pharmaceutical_help_center', 'មជ្ឈមណ្ឌលជំនួយសម្រាប់ការប្រើប្រាស់ប្រព័ន្ធឱសថ') }}</div>
                        </div>
                        <a href="{{ route('pharmaceutical.help') }}" class="btn btn-sm btn-outline-success">
                            <i class="fa fa-home me-1"></i>{{ localize('back_to_overview', 'ត្រឡប់ទៅទិដ្ឋភាពទូទៅ') }}
                        </a>
                    </div>
                    <div class="card-body pharm-help-article">
                        {!! $articleHtml !!}
                    </div>
                </div>

                <div class="card pharm-card pharm-help-tip">
                    <div class="card-body py-3">
                        <div class="fw-semibold mb-1">{{ localize('help_tip', 'គន្លឹះក្នុងការប្រើ Help') }}</div>
                        <div class="small text-muted mb-0">
                            {{ localize('help_tip_text', 'សូមចាប់ផ្តើមពីទិដ្ឋភាពទូទៅ សិន បន្ទាប់មកអានតាម module ដែលអ្នកប្រើប្រចាំថ្ងៃ ដូចជា Medicines, Stock, Dispensing និង Reports។') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
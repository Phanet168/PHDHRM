@extends('backend.layouts.app')

@section('title', localize('help_center', 'មជ្ឈមណ្ឌលជំនួយ'))

@push('css')
<style>
    .corr-help-layout {
        display: grid;
        grid-template-columns: 280px minmax(0, 1fr);
        gap: 20px;
    }

    .corr-help-sidebar,
    .corr-help-content {
        border: 0;
        box-shadow: var(--corr-shadow);
    }

    .corr-help-sidebar .list-group-item {
        border: 0;
        border-bottom: 1px solid rgba(27, 141, 106, 0.08);
        padding: 12px 14px;
    }

    .corr-help-sidebar .list-group-item.active {
        background: linear-gradient(135deg, var(--corr-primary), var(--corr-primary-dark));
        border-color: transparent;
        color: #fff;
    }

    .corr-help-content .card-header {
        background: linear-gradient(135deg, #f1fbf7, #edf6ff);
    }

    .corr-help-article {
        font-size: 15px;
        line-height: 1.75;
        color: #17352a;
    }

    .corr-help-article h1,
    .corr-help-article h2,
    .corr-help-article h3 {
        color: #10462a;
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
    }

    .corr-help-article h1 {
        font-size: 1.8rem;
        margin-top: 0;
    }

    .corr-help-article h2 {
        font-size: 1.3rem;
        border-bottom: 1px solid rgba(27, 141, 106, 0.12);
        padding-bottom: 0.35rem;
    }

    .corr-help-article ul,
    .corr-help-article ol {
        padding-left: 1.4rem;
    }

    .corr-help-article li + li {
        margin-top: 0.35rem;
    }

    .corr-help-article code {
        background: #f2f7f3;
        color: #145530;
        padding: 2px 6px;
        border-radius: 6px;
    }

    .corr-help-tip {
        background: #edf7f0;
        border: 1px solid rgba(27, 141, 106, 0.14);
        border-radius: 14px;
    }

    @media (max-width: 991.98px) {
        .corr-help-layout {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
    <div class="body-content">
        @include('correspondence::_nav')

        <div class="corr-help-layout">
            <div class="card corr-card corr-help-sidebar">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa fa-life-ring me-1"></i>{{ localize('help_topics', 'ប្រធានបទជំនួយ') }}</h6>
                </div>
                <div class="list-group list-group-flush">
                    @foreach($articles as $slug => $item)
                        <a href="{{ route('correspondence.help', ['article' => $slug]) }}"
                           class="list-group-item list-group-item-action {{ $activeArticle === $slug ? 'active' : '' }}">
                            <i class="fa {{ $item['icon'] }} me-2"></i>{{ $item['title'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="card corr-card corr-help-content mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">{{ $articleMeta['title'] }}</h5>
                            <div class="text-muted small">{{ localize('correspondence_help_center', 'មជ្ឈមណ្ឌលជំនួយសម្រាប់ការប្រើប្រាស់ប្រព័ន្ធគ្រប់គ្រងលិខិតរដ្ឋបាល') }}</div>
                        </div>
                        <a href="{{ route('correspondence.help') }}" class="btn btn-sm btn-outline-success">
                            <i class="fa fa-home me-1"></i>{{ localize('back_to_overview', 'ត្រឡប់ទៅទិដ្ឋភាពទូទៅ') }}
                        </a>
                    </div>
                    <div class="card-body corr-help-article">
                        {!! $articleHtml !!}
                    </div>
                </div>

                <div class="card corr-card corr-help-tip">
                    <div class="card-body py-3">
                        <div class="fw-semibold mb-1">{{ localize('help_tip', 'គន្លឹះក្នុងការប្រើ Help') }}</div>
                        <div class="small text-muted mb-0">
                            {{ localize('correspondence_help_tip_text', 'សូមចាប់ផ្តើមពីទិដ្ឋភាពទូទៅ និងដំណើរការ Workflow សិន បន្ទាប់មកអានផ្នែកលិខិតចូល លិខិតចេញ និងចំណារ/មតិយោបល់។') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


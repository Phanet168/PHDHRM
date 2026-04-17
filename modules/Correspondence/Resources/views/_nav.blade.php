@once
    @push('css')
        <style>
            :root {
                --corr-primary: #1a6e3e;
                --corr-primary-dark: #145530;
                --corr-primary-soft: #edf7f0;
                --corr-accent-soft: #eef5ff;
                --corr-shadow: 0 10px 24px rgba(15, 48, 30, 0.08);
                --corr-shadow-hover: 0 14px 30px rgba(15, 48, 30, 0.12);
            }

            .correspondence-ui .corr-card {
                border: 1px solid rgba(26, 110, 62, 0.12);
                border-radius: 14px;
                box-shadow: var(--corr-shadow);
                overflow: hidden;
                transition: box-shadow 0.2s ease;
            }

            .correspondence-ui .corr-card:hover {
                box-shadow: var(--corr-shadow-hover);
            }

            .correspondence-ui .corr-card .card-body {
                background: linear-gradient(120deg, var(--corr-primary-soft) 0%, var(--corr-accent-soft) 100%);
            }

            .correspondence-ui .corr-tabs .nav-link {
                border: 0;
                border-radius: 999px;
                padding: 7px 16px;
                margin-right: 6px;
                background: rgba(255, 255, 255, 0.8);
                color: #0f3a2f;
                font-weight: 600;
                transition: all 0.2s ease;
            }

            .correspondence-ui .corr-tabs .nav-link:hover {
                background: #ffffff;
                color: #124e34;
                transform: translateY(-1px);
                box-shadow: 0 6px 12px rgba(18, 78, 52, 0.14);
            }

            .correspondence-ui .corr-tabs .nav-link.active {
                background: var(--corr-primary);
                color: #fff;
                box-shadow: 0 8px 18px rgba(26, 110, 62, 0.25);
            }

            .correspondence-ui .corr-actions .btn {
                border-radius: 999px;
                font-weight: 600;
                box-shadow: 0 4px 10px rgba(15, 48, 30, 0.12);
                transition: all 0.2s ease;
            }

            .correspondence-ui .corr-actions .btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 8px 18px rgba(15, 48, 30, 0.16);
            }

            .correspondence-ui .corr-actions .btn-success {
                background: var(--corr-primary);
                border-color: var(--corr-primary);
            }

            .correspondence-ui .corr-actions .btn-success:hover {
                background: var(--corr-primary-dark);
                border-color: var(--corr-primary-dark);
            }

            .correspondence-ui .corr-level-badge {
                font-size: 11px;
                border-radius: 999px;
                padding: 7px 10px;
                box-shadow: 0 4px 10px rgba(46, 125, 215, 0.22);
            }

            .correspondence-ui .corr-admin-wrap {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                padding: 6px 10px;
                border-radius: 12px;
                border: 1px solid rgba(26, 110, 62, 0.14);
                background: rgba(255, 255, 255, 0.82);
                backdrop-filter: blur(1px);
            }

            .correspondence-ui .corr-admin-title {
                font-size: 11px;
                font-weight: 700;
                color: #295743;
                white-space: nowrap;
            }

            .correspondence-ui .corr-admin-title i {
                color: var(--corr-primary);
            }

            .correspondence-ui .corr-admin-controls {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            @media (max-width: 992px) {
                .correspondence-ui .corr-card .card-body {
                    padding-top: 10px;
                    padding-bottom: 10px;
                }

                .correspondence-ui .corr-actions {
                    width: 100%;
                    justify-content: flex-end;
                }

                .correspondence-ui .corr-admin-wrap {
                    width: 100%;
                    justify-content: space-between;
                }

                .correspondence-ui .corr-admin-controls {
                    justify-content: flex-end;
                }
            }

            @media (max-width: 576px) {
                .correspondence-ui .corr-admin-wrap {
                    flex-direction: column;
                    align-items: stretch;
                }

                .correspondence-ui .corr-admin-controls {
                    width: 100%;
                    justify-content: flex-start;
                }
            }
        </style>
    @endpush
@endonce

@canany(['read_correspondence_management'])
<div class="correspondence-ui mb-3">
    <div class="card corr-card fixed-tab">
        <div class="card-body py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <ul class="nav corr-tabs flex-wrap mb-0">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('correspondence.index') ? 'active' : '' }}"
                            href="{{ route('correspondence.index') }}">
                            <i class="fa fa-tachometer-alt me-1"></i>{{ localize('correspondence_dashboard', 'ផ្ទាំងតាមដានលិខិត') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('correspondence.incoming') ? 'active' : '' }}"
                            href="{{ route('correspondence.incoming') }}">
                            <i class="fa fa-inbox me-1"></i>{{ localize('incoming_letters', 'លិខិតចូល') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('correspondence.outgoing') ? 'active' : '' }}"
                            href="{{ route('correspondence.outgoing') }}">
                            <i class="fa fa-paper-plane me-1"></i>{{ localize('outgoing_letters', 'លិខិតចេញ') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('correspondence.help') ? 'active' : '' }}"
                            href="{{ route('correspondence.help') }}">
                            <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'ជំនួយ') }}
                        </a>
                    </li>
            </ul>

            <div class="d-flex gap-2 align-items-center flex-wrap corr-actions">
                <div class="corr-admin-wrap">
                    <div class="corr-admin-title">
                        <i class="fa fa-sliders-h me-1"></i>{{ localize('management_section', 'ផ្នែកគ្រប់គ្រង') }}
                    </div>

                    <div class="corr-admin-controls">
                        @if (!empty($corrLevelLabel ?? ''))
                            <span class="badge bg-info text-white corr-level-badge">
                                <i class="fa fa-building"></i>
                                {{ $corrLevelLabel }} {{ !empty($corrDepartmentName ?? '') ? '- ' . $corrDepartmentName : '' }}
                            </span>
                        @endif

                        @can('create_correspondence_management')
                        @if (($canCreateIncoming ?? false) || auth()->user()?->admin())
                            <a href="{{ route('correspondence.create', 'incoming') }}" class="btn btn-sm btn-success">
                                <i class="fa fa-plus-circle"></i> {{ localize('add_incoming_letter', 'បន្ថែមលិខិតចូល') }}
                            </a>
                        @endif
                        @if (($canCreateOutgoing ?? false) || auth()->user()?->admin())
                            <a href="{{ route('correspondence.create', 'outgoing') }}" class="btn btn-sm btn-primary">
                                <i class="fa fa-plus-circle"></i> {{ localize('add_outgoing_letter', 'បន្ថែមលិខិតចេញ') }}
                            </a>
                        @endif
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endcanany


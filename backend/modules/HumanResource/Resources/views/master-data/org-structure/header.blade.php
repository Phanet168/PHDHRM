@once
    @push('css')
        <style>
            :root {
                --org-primary: #2c6fbb;
                --org-primary-dark: #1e4f8a;
                --org-accent: #3498db;
                --org-soft: #edf2fa;
                --org-shadow: 0 10px 24px rgba(20, 50, 100, 0.08);
                --org-muted: #6b7b8c;
            }

            .org-structure-ui .org-tabs .nav-link {
                border: 0;
                border-radius: 999px;
                padding: 6px 16px;
                margin-right: 6px;
                margin-bottom: 6px;
                background: rgba(255, 255, 255, 0.8);
                color: #1a3a5c;
                font-weight: 600;
                transition: all 0.2s ease;
            }

            .org-structure-ui .org-tabs .nav-link.active {
                background: var(--org-primary);
                color: #fff;
                box-shadow: 0 8px 18px rgba(44, 111, 187, 0.25);
            }

            .org-card {
                border: 0;
                box-shadow: var(--org-shadow);
            }

            .org-card .card-body {
                background: #f4f8fc;
                border-bottom: 1px solid rgba(44, 111, 187, 0.12);
            }

            .org-structure-ui .org-tab-section-label {
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: var(--org-muted);
                margin-bottom: 8px;
            }

            .org-structure-ui .org-tab-section + .org-tab-section {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px dashed rgba(44, 111, 187, 0.18);
            }

            .org-structure-ui .org-advanced-tools {
                margin-top: 10px;
            }

            .org-structure-ui .org-advanced-tools summary {
                list-style: none;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 6px 14px;
                border-radius: 999px;
                background: rgba(44, 111, 187, 0.08);
                color: #1a3a5c;
                font-weight: 600;
            }

            .org-structure-ui .org-advanced-tools summary::-webkit-details-marker {
                display: none;
            }

            .org-structure-ui .org-advanced-tools[open] summary,
            .org-structure-ui .org-advanced-tools.is-active summary {
                background: rgba(44, 111, 187, 0.14);
                color: var(--org-primary-dark);
            }

            .org-structure-ui .org-advanced-tools-body {
                margin-top: 10px;
                padding-top: 8px;
                border-top: 1px dashed rgba(44, 111, 187, 0.18);
            }

            .org-structure-ui .org-screen-note {
                margin-top: 10px;
                font-size: 13px;
                color: var(--org-muted);
            }
        </style>
    @endpush
@endonce

<div class="org-structure-ui mb-3">
    @php
        $showAdvancedGovernance = (bool) config('hr_governance.ui.show_advanced_central_governance', false);
        $advancedRouteActive = request()->routeIs([
            'responsibility-templates.*',
            'user-org-roles.*',
            'workflow-policies.*',
            'org-role-module-permissions.*',
        ]);
    @endphp
    <div class="card org-card">
        <div class="card-body py-2">
            @canany(['read_org_governance', 'read_department'])
                <div class="org-tab-section">
                    <div class="org-tab-section-label">{{ localize('main_tools', 'Main Tools') }}</div>
                    <ul class="nav org-tabs flex-wrap">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('org-unit-type-positions.*') ? 'active' : '' }}"
                               href="{{ route('org-unit-type-positions.index') }}">
                                <i class="fa fa-th-list me-1"></i>{{ localize('org_position_matrix_simple', 'រចនាសម្ព័ន្ធតួនាទី') }}
                            </a>
                        </li>
                        @if ($showAdvancedGovernance)
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('user-assignments.*') ? 'active' : '' }}"
                                   href="{{ route('user-assignments.index') }}">
                                    <i class="fa fa-user-check me-1"></i>{{ localize('user_assignments_simple', 'កំណត់អ្នកគ្រប់គ្រង') }}
                                </a>
                            </li>
                        @endif
                        @if (\Illuminate\Support\Facades\Route::has('system-roles.index'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('system-roles.*') ? 'active' : '' }}"
                                   href="{{ route('system-roles.index') }}">
                                    <i class="fa fa-id-badge me-1"></i>{{ localize('responsibilities_simple', 'តួនាទីការងារ') }}
                                </a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('org-structure.help') ? 'active' : '' }}"
                               href="{{ route('org-structure.help', ['article' => 'org-structure-overview']) }}">
                                <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'ជំនួយ') }}
                            </a>
                        </li>
                    </ul>
                </div>

                @if ($showAdvancedGovernance)
                    <div class="org-tab-section">
                        <div class="org-tab-section-label">{{ localize('advanced_tools', 'Advanced Tools') }}</div>
                        <details class="org-advanced-tools {{ $advancedRouteActive ? 'is-active' : '' }}" {{ $advancedRouteActive ? 'open' : '' }}>
                            <summary>
                                <i class="fa fa-sliders-h"></i>
                                <span>{{ localize('show_advanced_tools', 'បង្ហាញឧបករណ៍បន្ថែម') }}</span>
                            </summary>
                            <div class="org-advanced-tools-body">
                                <ul class="nav org-tabs flex-wrap">
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('responsibility-templates.*') ? 'active' : '' }}"
                                           href="{{ route('responsibility-templates.index') }}">
                                            <i class="fa fa-layer-group me-1"></i>{{ localize('responsibility_templates_simple', 'គំរូតួនាទី') }}
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('user-org-roles.*') ? 'active' : '' }}"
                                           href="{{ route('user-org-roles.index') }}">
                                            <i class="fa fa-history me-1"></i>{{ localize('legacy_org_roles_simple', 'របៀបចាស់') }}
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('workflow-policies.*') ? 'active' : '' }}"
                                           href="{{ route('workflow-policies.index') }}">
                                            <i class="fa fa-project-diagram me-1"></i>{{ localize('workflow_policy_matrix_simple', 'លំដាប់អនុម័ត') }}
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('org-role-module-permissions.*') ? 'active' : '' }}"
                                           href="{{ route('org-role-module-permissions.index') }}">
                                            <i class="fa fa-shield-alt me-1"></i>{{ localize('org_role_permission_matrix_advanced_simple', 'សិទ្ធិបន្ថែម') }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </details>
                        <div class="org-screen-note">
                            {{ localize('advanced_tools_note', 'សម្រាប់ការកំណត់ធម្មតា អ្នកភាគច្រើនប្រើតែ "កំណត់អ្នកគ្រប់គ្រង" ប៉ុណ្ណោះ។') }}
                        </div>
                    </div>
                @endif
            @endcanany
        </div>
    </div>
</div>

@once
    @push('css')
        <style>
            :root {
                --org-primary: #2c6fbb;
                --org-primary-dark: #1e4f8a;
                --org-accent: #3498db;
                --org-soft: #edf2fa;
                --org-shadow: 0 10px 24px rgba(20, 50, 100, 0.08);
            }

            .org-structure-ui .org-tabs .nav-link {
                border: 0;
                border-radius: 999px;
                padding: 6px 16px;
                margin-right: 6px;
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
        </style>
    @endpush
@endonce

<div class="org-structure-ui mb-3">
    <div class="card org-card">
        <div class="card-body py-2">
            <ul class="nav org-tabs flex-wrap">
                @canany(['read_org_governance', 'read_department'])
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('org-unit-type-positions.*') ? 'active' : '' }}"
                           href="{{ route('org-unit-type-positions.index') }}">
                            <i class="fa fa-th-list me-1"></i>{{ localize('org_position_matrix', 'Org Position Matrix') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('user-assignments.*') ? 'active' : '' }}"
                           href="{{ route('user-assignments.index') }}">
                            <i class="fa fa-user-check me-1"></i>{{ localize('user_assignments', 'User Assignments') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('user-org-roles.*') ? 'active' : '' }}"
                           href="{{ route('user-org-roles.index') }}">
                            <i class="fa fa-history me-1"></i>{{ localize('legacy_org_roles', 'Legacy Org Roles') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('workflow-policies.*') ? 'active' : '' }}"
                           href="{{ route('workflow-policies.index') }}">
                            <i class="fa fa-project-diagram me-1"></i>{{ localize('workflow_policy_matrix', 'Workflow Policy Matrix') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('org-role-module-permissions.*') ? 'active' : '' }}"
                           href="{{ route('org-role-module-permissions.index') }}">
                            <i class="fa fa-shield-alt me-1"></i>{{ localize('org_role_permission_matrix', 'Org Role Permission Matrix') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('system-roles.*') ? 'active' : '' }}"
                           href="{{ route('system-roles.index') }}">
                            <i class="fa fa-id-badge me-1"></i>{{ localize('responsibilities', 'Responsibilities') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('org-structure.help') ? 'active' : '' }}"
                           href="{{ route('org-structure.help', ['article' => 'org-governance-rbac']) }}">
                            <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'ជំនួយ') }}
                        </a>
                    </li>
                @endcanany
            </ul>
        </div>
    </div>
</div>

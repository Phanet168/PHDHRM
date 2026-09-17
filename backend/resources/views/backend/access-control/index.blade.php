@extends('backend.layouts.app')

@section('title', 'មជ្ឈមណ្ឌលគ្រប់គ្រងសិទ្ធិ')

@push('css')
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('backend/assets/dist/css/access-control-center.css') }}?v=20260917-2">
@endpush

@section('content')
<div class="body-content pt-0 acc-page">

    <div class="acc-header">
        <div class="acc-heading">
            <div class="acc-heading-icon" aria-hidden="true"><i class="fas fa-shield-alt"></i></div>
            <div>
                <span class="sub-en">Access Control Center</span>
                <h4>មជ្ឈមណ្ឌលគ្រប់គ្រងសិទ្ធិ</h4>
                <p class="desc">គ្រប់គ្រងអ្នកប្រើប្រាស់ តួនាទី សិទ្ធិ វិសាលភាព ការអនុម័ត និងការផ្ទេរសិទ្ធិពីកន្លែងតែមួយ។</p>
            </div>
        </div>
        <div class="acc-summary" id="acc-summary-cards">
            <div class="stat-card">
                <div class="acc-stat-icon" aria-hidden="true"><i class="fas fa-users"></i></div>
                <div><div class="num">{{ $summary['active_users'] }}</div><div class="lbl">អ្នកប្រើប្រាស់សកម្ម<span class="stat-en">Active users</span></div></div>
            </div>
            <div class="stat-card">
                <div class="acc-stat-icon is-blue" aria-hidden="true"><i class="fas fa-user-tag"></i></div>
                <div><div class="num">{{ $summary['roles'] }}</div><div class="lbl">តួនាទី<span class="stat-en">Roles</span></div></div>
            </div>
            <div class="stat-card">
                <div class="acc-stat-icon is-amber" aria-hidden="true"><i class="fas fa-key"></i></div>
                <div><div class="num">{{ $summary['permissions'] }}</div><div class="lbl">សិទ្ធិ<span class="stat-en">Permissions</span></div></div>
            </div>
            <div class="stat-card">
                <div class="acc-stat-icon is-purple" aria-hidden="true"><i class="fas fa-sitemap"></i></div>
                <div><div class="num">{{ $summary['organizational_units'] }}</div><div class="lbl">អង្គភាព<span class="stat-en">Organizational units</span></div></div>
            </div>
        </div>
    </div>

    <div class="acc-tabs">
        <ul class="nav nav-tabs" role="tablist" aria-label="មុខងារគ្រប់គ្រងសិទ្ធិ">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-roles" type="button" id="acc-tab-roles" role="tab" aria-controls="tab-roles" aria-selected="true"><i class="fas fa-user-shield" aria-hidden="true"></i><span>តួនាទី និងសិទ្ធិ</span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-users" type="button" id="acc-tab-users" role="tab" aria-controls="tab-users" aria-selected="false"><i class="fas fa-users" aria-hidden="true"></i><span>អ្នកប្រើប្រាស់</span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-scope" type="button" id="acc-tab-scope" role="tab" aria-controls="tab-scope" aria-selected="false"><i class="fas fa-sitemap" aria-hidden="true"></i><span>វិសាលភាពអង្គភាព</span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-approval" type="button" id="acc-tab-approval" role="tab" aria-controls="tab-approval" aria-selected="false"><i class="fas fa-clipboard-check" aria-hidden="true"></i><span>ការអនុម័ត</span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-delegation" type="button" id="acc-tab-delegation" role="tab" aria-controls="tab-delegation" aria-selected="false"><i class="fas fa-exchange-alt" aria-hidden="true"></i><span>ផ្ទេរសិទ្ធិ</span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-effective" type="button" id="acc-tab-effective" role="tab" aria-controls="tab-effective" aria-selected="false"><i class="fas fa-key" aria-hidden="true"></i><span>សិទ្ធិជាក់ស្តែង</span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-audit" type="button" id="acc-tab-audit" role="tab" aria-controls="tab-audit" aria-selected="false"><i class="fas fa-history" aria-hidden="true"></i><span>ប្រវត្តិ</span></button></li>
        </ul>

        <div class="tab-content acc-body">

            {{-- ROLES & PERMISSIONS --}}
            <div class="tab-pane fade show active" id="tab-roles" role="tabpanel" aria-labelledby="acc-tab-roles" tabindex="0">
                <div class="acc-section-heading"><div><h5>តួនាទី និងសិទ្ធិ</h5><p>ជ្រើសរើសតួនាទី ដើម្បីពិនិត្យ និងកំណត់សិទ្ធិប្រើប្រាស់តាមមុខងារ។</p></div><span class="acc-section-note"><i class="fas fa-layer-group" aria-hidden="true"></i> សិទ្ធិតាមតួនាទី</span></div>
                <div class="acc-split">
                    <div class="acc-list-panel">
                        <div class="panel-head">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="small">បញ្ជីតួនាទី</strong>
                                <button class="btn btn-sm btn-success" id="acc-role-add-btn"><i class="fa fa-plus"></i> បន្ថែម</button>
                            </div>
                            <div class="acc-search-field"><i class="fas fa-search" aria-hidden="true"></i><input type="text" class="form-control form-control-sm" id="acc-role-search" placeholder="ស្វែងរកតួនាទី..." aria-label="ស្វែងរកតួនាទី"></div>
                        </div>
                        <div class="panel-body" id="acc-role-list"></div>
                    </div>
                    <div class="acc-detail-panel" id="acc-role-detail">
                        <div class="acc-empty-state"><i class="fas fa-user-shield" aria-hidden="true"></i><div><strong>ជ្រើសរើសតួនាទីដើម្បីចាប់ផ្តើម</strong>សូមជ្រើសរើសតួនាទីមួយពីបញ្ជី ដើម្បីមើលនិងកែប្រែសិទ្ធិ។</div></div>
                    </div>
                </div>
            </div>

            {{-- USERS --}}
            <div class="tab-pane fade" id="tab-users" role="tabpanel" aria-labelledby="acc-tab-users" tabindex="0">
                <div class="acc-section-heading"><div><h5>អ្នកប្រើប្រាស់ និងតួនាទី</h5><p>ស្វែងរកអ្នកប្រើប្រាស់ ដើម្បីមើលតួនាទី វិសាលភាព និងសិទ្ធិរបស់គាត់។</p></div></div>
                <div class="acc-split">
                    <div class="acc-list-panel">
                        <div class="panel-head">
                            <strong class="small d-block mb-2">ស្វែងរកអ្នកប្រើប្រាស់</strong>
                            <div class="acc-search-field"><i class="fas fa-search" aria-hidden="true"></i><input type="text" class="form-control form-control-sm" id="acc-user-search" placeholder="ឈ្មោះ, លេខកូដបុគ្គលិក, អ៊ីមែល..." aria-label="ស្វែងរកអ្នកប្រើប្រាស់"></div>
                        </div>
                        <div class="panel-body" id="acc-user-list">
                            <div class="p-3 text-muted small text-center">សូមវាយបញ្ចូលដើម្បីស្វែងរក</div>
                        </div>
                    </div>
                    <div class="acc-detail-panel" id="acc-user-detail">
                        <div class="acc-empty-state"><i class="fa fa-user"></i><div>សូមស្វែងរក និងជ្រើសរើសអ្នកប្រើប្រាស់មួយ។</div></div>
                    </div>
                </div>
            </div>

            {{-- ORGANIZATION SCOPE --}}
            <div class="tab-pane fade" id="tab-scope" role="tabpanel" aria-labelledby="acc-tab-scope" tabindex="0">
                <div class="acc-section-heading"><div><h5>វិសាលភាពអង្គភាព</h5><p>កំណត់វិសាលភាពអង្គភាពសម្រាប់ការទទួលខុសត្រូវរបស់អ្នកប្រើប្រាស់។</p></div></div>
                <div class="row g-2 mb-3 acc-filter-bar">
                    <div class="col-md-8 position-relative">
                        <div class="acc-search-field"><i class="fas fa-search" aria-hidden="true"></i><input type="text" class="form-control form-control-sm" id="acc-scope-user-search" placeholder="ស្វែងរកអ្នកប្រើប្រាស់ដើម្បីគ្រប់គ្រងវិសាលភាព..." aria-label="ស្វែងរកអ្នកប្រើប្រាស់ដើម្បីគ្រប់គ្រងវិសាលភាព"></div>
                        <div id="acc-scope-user-results" class="list-group position-absolute acc-search-results"></div>
                    </div>
                </div>
                <div class="acc-split">
                    <div class="acc-list-panel">
                        <div class="panel-head"><strong class="small">ការទទួលខុសត្រូវ (Assignments)</strong></div>
                        <div class="panel-body" id="acc-scope-group-list">
                            <div class="p-3 text-muted small text-center">សូមស្វែងរក និងជ្រើសរើសអ្នកប្រើប្រាស់</div>
                        </div>
                    </div>
                    <div class="acc-detail-panel" id="acc-scope-editor">
                        <div class="acc-empty-state"><i class="fa fa-sitemap"></i><div>សូមជ្រើសរើសអ្នកប្រើប្រាស់ និងការទទួលខុសត្រូវមួយ ដើម្បីកែវិសាលភាព។</div></div>
                    </div>
                </div>
            </div>

            {{-- APPROVAL AUTHORITY --}}
            <div class="tab-pane fade" id="tab-approval" role="tabpanel" aria-labelledby="acc-tab-approval" tabindex="0">
                <div class="acc-section-heading"><div><h5>ការអនុម័ត (Approval Authority)</h5><p>មើល និងកែសម្រួលជំហានអនុម័តសម្រាប់ម៉ូឌុលនីមួយៗ។ អ្នកអនុម័តត្រូវបានកំណត់ដោយតួនាទីការងារ ការទទួលខុសត្រូវ តួនាទីប្រព័ន្ធ ឬអ្នកប្រើប្រាស់ជាក់លាក់។</p></div></div>
                <div class="acc-split">
                    <div class="acc-list-panel">
                        <div class="panel-head"><strong class="small">គោលការណ៍អនុម័ត (Workflow Definitions)</strong></div>
                        <div class="panel-body" id="acc-approval-list"></div>
                    </div>
                    <div class="acc-detail-panel" id="acc-approval-detail">
                        <div class="acc-empty-state"><i class="fas fa-clipboard-check" aria-hidden="true"></i><div><strong>ជ្រើសរើសគោលការណ៍អនុម័តដើម្បីចាប់ផ្តើម</strong>សូមជ្រើសរើសមួយពីបញ្ជី ដើម្បីមើល ឬកែសម្រួលជំហានអនុម័ត។</div></div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="acc-section-heading"><div><h6 class="mb-1">ត្រួតពិនិត្យសិទ្ធិអនុម័តរបស់អ្នកប្រើប្រាស់</h6><p class="mb-0">ស្វែងរកអ្នកប្រើប្រាស់ ដើម្បីមើលថាតើគាត់មានលក្ខណៈសម្បត្តិអនុម័តជំហានណាខ្លះបានក្នុងបច្ចុប្បន្ន (មិនគិតវិសាលភាពអង្គភាព)។</p></div></div>
                <div class="row g-2 mb-3 acc-filter-bar">
                    <div class="col-md-6 position-relative">
                        <div class="acc-search-field"><i class="fas fa-search" aria-hidden="true"></i><input type="text" class="form-control form-control-sm" id="acc-approval-user-search" placeholder="ស្វែងរកអ្នកប្រើប្រាស់..." aria-label="ស្វែងរកអ្នកប្រើប្រាស់ដើម្បីមើលសិទ្ធិអនុម័ត"></div>
                        <div id="acc-approval-user-results" class="list-group position-absolute acc-search-results"></div>
                    </div>
                </div>
                <div id="acc-approval-user-result">
                    <div class="acc-empty-state"><i class="fa fa-user-check"></i><div>សូមស្វែងរកអ្នកប្រើប្រាស់ដើម្បីមើលសិទ្ធិអនុម័តរបស់គាត់។</div></div>
                </div>
            </div>

            {{-- DELEGATION --}}
            <div class="tab-pane fade" id="tab-delegation" role="tabpanel" aria-labelledby="acc-tab-delegation" tabindex="0">
                <div class="acc-section-heading"><div><h5>ផ្ទេរសិទ្ធិបណ្តោះអាសន្ន (Temporary Delegation)</h5><p>ផ្ទេរសិទ្ធិអនុម័តមួយចំនួនពីអ្នកប្រើប្រាស់ម្នាក់ទៅអ្នកប្រើប្រាស់ម្នាក់ទៀត សម្រាប់រយៈពេលកំណត់មួយ។ សិទ្ធិផ្សេងទៀត (តួនាទី សិទ្ធិប្រព័ន្ធ ការគ្រប់គ្រងបុគ្គលិក។ល។) នឹងមិនត្រូវបានផ្ទេរជាមួយឡើយ។</p></div></div>

                <div class="row g-2 mb-3 acc-filter-bar">
                    <div class="col-md-8">
                        <div class="btn-group btn-group-sm" role="group" aria-label="តម្រងស្ថានភាព">
                            <button type="button" class="btn btn-outline-secondary acc-deleg-state-btn active" data-state="active">សកម្ម</button>
                            <button type="button" class="btn btn-outline-secondary acc-deleg-state-btn" data-state="upcoming">នឹងមកដល់</button>
                            <button type="button" class="btn btn-outline-secondary acc-deleg-state-btn" data-state="expired">ផុតកំណត់</button>
                            <button type="button" class="btn btn-outline-secondary acc-deleg-state-btn" data-state="revoked">បានដកហូត</button>
                            <button type="button" class="btn btn-outline-secondary acc-deleg-state-btn" data-state="all">ទាំងអស់</button>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-success btn-sm" id="acc-deleg-new-btn"><i class="fa fa-plus"></i> ផ្ទេរសិទ្ធិថ្មី</button>
                    </div>
                </div>

                <div class="acc-split">
                    <div class="acc-list-panel">
                        <div class="panel-head"><strong class="small">បញ្ជីការផ្ទេរសិទ្ធិ</strong></div>
                        <div class="panel-body" id="acc-deleg-list"></div>
                    </div>
                    <div class="acc-detail-panel" id="acc-deleg-detail">
                        <div class="acc-empty-state"><i class="fas fa-exchange-alt" aria-hidden="true"></i><div><strong>ជ្រើសរើស ឬបង្កើតការផ្ទេរសិទ្ធិ</strong>សូមជ្រើសរើសការផ្ទេរសិទ្ធិមួយពីបញ្ជី ឬចុច "ផ្ទេរសិទ្ធិថ្មី" ដើម្បីបង្កើត។</div></div>
                    </div>
                </div>
            </div>

            {{-- Delegation create modal --}}
            <div class="modal fade acc-modal" id="accDelegationFormModal" tabindex="-1" aria-labelledby="accDelegationFormTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title" id="accDelegationFormTitle">ផ្ទេរសិទ្ធិថ្មី (New Delegation)</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="បិទ"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">ពី (FROM) — អ្នកផ្ទេរសិទ្ធិ</label>
                                    <div class="acc-search-field"><i class="fas fa-search" aria-hidden="true"></i><input type="text" class="form-control form-control-sm" id="acc-deleg-from-search" placeholder="ស្វែងរកអ្នកប្រើប្រាស់..."></div>
                                    <div id="acc-deleg-from-results" class="list-group acc-search-results" style="position:relative;"></div>
                                    <div id="acc-deleg-from-selected" class="small mt-1"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">ទៅ (TO) — អ្នកទទួលសិទ្ធិ</label>
                                    <div class="acc-search-field"><i class="fas fa-search" aria-hidden="true"></i><input type="text" class="form-control form-control-sm" id="acc-deleg-to-search" placeholder="ស្វែងរកអ្នកប្រើប្រាស់..."></div>
                                    <div id="acc-deleg-to-results" class="list-group acc-search-results" style="position:relative;"></div>
                                    <div id="acc-deleg-to-selected" class="small mt-1"></div>
                                </div>
                            </div>

                            <hr>
                            <div class="fw-semibold small mb-2">សិទ្ធិអនុម័តដែលអាចផ្ទេរបាន (Authority)</div>
                            <div id="acc-deleg-authority-box"><div class="text-muted small">សូមជ្រើសរើស "ពី" សិន</div></div>

                            <hr>
                            <div class="fw-semibold small mb-2">វិសាលភាពអង្គភាព (Scope)</div>
                            <div id="acc-deleg-scope-options" class="mb-2"></div>
                            <div id="acc-deleg-unit-picker-wrap" class="d-none">
                                <input type="text" class="form-control form-control-sm mb-2" id="acc-deleg-unit-search" placeholder="ស្វែងរកអង្គភាព...">
                                <div id="acc-deleg-unit-picker" style="max-height:220px;overflow-y:auto;border:1px solid #eaecf0;border-radius:8px;padding:8px 12px;"></div>
                            </div>

                            <hr>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">ចាប់ផ្តើម (Starts At)</label>
                                    <input type="datetime-local" class="form-control form-control-sm" id="acc-deleg-starts-at">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">បញ្ចប់ (Ends At)</label>
                                    <input type="datetime-local" class="form-control form-control-sm" id="acc-deleg-ends-at">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1">មូលហេតុ (Reason)</label>
                                    <textarea class="form-control form-control-sm" id="acc-deleg-reason" rows="2"></textarea>
                                </div>
                            </div>

                            <hr>
                            <div id="acc-deleg-preview"></div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">បោះបង់</button>
                            <button class="btn btn-success btn-sm" id="acc-deleg-save-btn">រក្សាទុក</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- EFFECTIVE ACCESS --}}
            <div class="tab-pane fade" id="tab-effective" role="tabpanel" aria-labelledby="acc-tab-effective" tabindex="0">
                <div class="acc-section-heading"><div><h5>សិទ្ធិជាក់ស្តែង</h5><p>ពិនិត្យសិទ្ធិដែលអ្នកប្រើប្រាស់ទទួលបាន និងប្រភពនៃសិទ្ធិនីមួយៗ។</p></div></div>
                <div class="row g-2 mb-3 acc-filter-bar">
                    <div class="col-xl-5 col-md-12 position-relative">
                        <div class="acc-search-field"><i class="fas fa-search" aria-hidden="true"></i><input type="text" class="form-control form-control-sm" id="acc-ea-search" placeholder="ស្វែងរកអ្នកប្រើប្រាស់ដើម្បីមើលសិទ្ធិជាក់ស្តែង..." aria-label="ស្វែងរកអ្នកប្រើប្រាស់ដើម្បីមើលសិទ្ធិជាក់ស្តែង"></div>
                        <div id="acc-ea-search-results" class="list-group position-absolute acc-search-results"></div>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <select class="form-select form-select-sm" id="acc-ea-filter-module" aria-label="តម្រងមុខងារ"><option value="">គ្រប់ Module</option></select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <select class="form-select form-select-sm" id="acc-ea-filter-allowed" aria-label="តម្រងសិទ្ធិអនុញ្ញាត">
                            <option value="">ទាំងអស់</option>
                            <option value="1">អនុញ្ញាតតែប៉ុណ្ណោះ</option>
                            <option value="0">មិនអនុញ្ញាតតែប៉ុណ្ណោះ</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-4">
                        <select class="form-select form-select-sm" id="acc-ea-filter-source" aria-label="តម្រងប្រភពសិទ្ធិ">
                            <option value="">គ្រប់ប្រភព</option>
                            <option value="role">Role</option>
                            <option value="direct_user_permission">Direct Permission</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                </div>
                <div id="acc-ea-result">
                    <div class="acc-empty-state"><i class="fa fa-key"></i><div>សូមស្វែងរកអ្នកប្រើប្រាស់ដើម្បីមើលសិទ្ធិជាក់ស្តែងរបស់គាត់។</div></div>
                </div>
            </div>

            {{-- AUDIT HISTORY (Phase 3E) --}}
            <div class="tab-pane fade" id="tab-audit" role="tabpanel" aria-labelledby="acc-tab-audit" tabindex="0">
                <div class="acc-section-heading"><div><h5>ប្រវត្តិសកម្មភាព (Audit History)</h5><p>កំណត់ត្រាការផ្លាស់ប្តូរសុវត្ថិភាព/សិទ្ធិទាំងអស់ក្នុង Access Control Center — តួនាទី សិទ្ធិ វិសាលភាពអង្គភាព សិទ្ធិអនុម័ត និងការផ្ទេរសិទ្ធិ។</p></div></div>

                <div class="row g-2 mb-3 acc-filter-bar">
                    <div class="col-xl-2 col-md-4">
                        <select class="form-select form-select-sm" id="acc-audit-filter-category" aria-label="តម្រងប្រភេទ"><option value="">គ្រប់ប្រភេទ</option></select>
                    </div>
                    <div class="col-xl-3 col-md-4 position-relative">
                        <div class="acc-search-field"><i class="fas fa-search" aria-hidden="true"></i><input type="text" class="form-control form-control-sm" id="acc-audit-filter-actor" placeholder="ស្វែងរកអ្នកសម្រេច (Actor)..." aria-label="ស្វែងរកអ្នកសម្រេច"></div>
                        <div id="acc-audit-actor-results" class="list-group position-absolute acc-search-results"></div>
                        <input type="hidden" id="acc-audit-filter-actor-id">
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <input type="date" class="form-control form-control-sm" id="acc-audit-filter-date-from" aria-label="ចាប់ពីថ្ងៃទី">
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <input type="date" class="form-control form-control-sm" id="acc-audit-filter-date-to" aria-label="ដល់ថ្ងៃទី">
                    </div>
                    <div class="col-xl-3 col-md-8">
                        <input type="text" class="form-control form-control-sm" id="acc-audit-filter-q" placeholder="ស្វែងរកសកម្មភាព / គោលដៅ..." aria-label="ស្វែងរក">
                    </div>
                </div>

                <div class="acc-list-panel" style="width:100%;">
                    <div class="panel-body" id="acc-audit-list"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="small text-muted" id="acc-audit-summary"></div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="acc-audit-prev"><i class="fa fa-chevron-left"></i></button>
                        <button type="button" class="btn btn-outline-secondary" id="acc-audit-next"><i class="fa fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Add / Rename Role Modal --}}
<div class="modal fade acc-modal" id="accRoleFormModal" tabindex="-1" aria-labelledby="accRoleFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="accRoleFormTitle">បន្ថែមតួនាទីថ្មី</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="បិទ"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="acc-role-form-id">
                <label class="form-label small" for="acc-role-form-name">ឈ្មោះតួនាទី (Role Name)</label>
                <input type="text" class="form-control" id="acc-role-form-name" placeholder="ឧ. HR Officer">
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">បោះបង់</button>
                <button class="btn btn-success btn-sm" id="acc-role-form-save">រក្សាទុក</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
(function ($) {
    'use strict';

    const CSRF = $('meta[name="csrf-token"]').attr('content');
    const ROUTES = {
        catalog: @json(route('access-control.catalog')),
        roles: @json(route('access-control.roles.index')),
        roleShow: (id) => @json(route('access-control.roles.show', ':id')).replace(':id', id),
        roleUpdate: (id) => @json(route('access-control.roles.update', ':id')).replace(':id', id),
        rolePermUpdate: (id) => @json(route('access-control.roles.permissions.update', ':id')).replace(':id', id),
        roleDestroy: (id) => @json(route('access-control.roles.destroy', ':id')).replace(':id', id),
        usersSearch: @json(route('access-control.users.search')),
        userShow: (id) => @json(route('access-control.users.show', ':id')).replace(':id', id),
        userRolesUpdate: (id) => @json(route('access-control.users.roles.update', ':id')).replace(':id', id),
        userDirectPermUpdate: (id) => @json(route('access-control.users.direct-permissions.update', ':id')).replace(':id', id),
        userEffectiveAccess: (id) => @json(route('role.user.effective-access', ':id')).replace(':id', id),
        orgTree: @json(route('access-control.org-tree')),
        userScopes: (id) => @json(route('access-control.users.scopes.index', ':id')).replace(':id', id),
        userScopeUpdate: (id, groupKey) => @json(route('access-control.users.scopes.update', [':id', ':gk'])).replace(':id', id).replace(':gk', groupKey),
        approvals: @json(route('access-control.approvals.index')),
        approvalOptions: @json(route('access-control.approvals.options')),
        approvalShow: (id) => @json(route('access-control.approvals.show', ':id')).replace(':id', id),
        approvalUpdate: (id) => @json(route('access-control.approvals.update', ':id')).replace(':id', id),
        userApprovalAuthority: (id) => @json(route('access-control.users.approval-authority', ':id')).replace(':id', id),
        delegationsIndex: @json(route('access-control.delegations.index')),
        delegationOptions: @json(route('access-control.delegations.options')),
        delegationAuthorities: (id) => @json(route('access-control.delegations.authorities', ':id')).replace(':id', id),
        delegationBoundary: (id) => @json(route('access-control.delegations.boundary', ':id')).replace(':id', id),
        delegationStore: @json(route('access-control.delegations.store')),
        delegationRevoke: (uuid) => @json(route('access-control.delegations.revoke', ':id')).replace(':id', uuid),
        auditHistory: @json(route('access-control.audit-history.index')),
        auditHistoryOptions: @json(route('access-control.audit-history.options')),
    };
    const SCOPE_PRESENTATION_OPTIONS = [
        { value: 'SELF', label: 'ខ្លួនឯង (Self)' },
        { value: 'UNIT', label: 'អង្គភាពរបស់ខ្លួន (Unit)' },
        { value: 'UNIT_TREE', label: 'អង្គភាពរបស់ខ្លួន និងអង្គភាពក្រោមឱវាទ (Unit + Children)' },
        { value: 'SELECTED_UNITS', label: 'អង្គភាពដែលបានជ្រើសរើស (Selected Units)' },
        { value: 'ORGANIZATION', label: 'អង្គភាពទាំងមូល (Organization)' },
    ];
    const SCOPE_LABELS = {
        self_only: 'ខ្លួនឯង',
        self_unit_only: 'អង្គភាពរបស់ខ្លួន',
        self_and_children: 'អង្គភាពរបស់ខ្លួន និងអង្គភាពក្រោមឱវាទ',
        all: 'អង្គភាពទាំងមូល',
    };

    function ajax(opts) {
        return $.ajax(Object.assign({ headers: { 'X-CSRF-TOKEN': CSRF } }, opts));
    }
    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function apiError(xhr) {
        const msg = xhr?.responseJSON?.message || 'មានបញ្ហាកើតឡើង សូមព្យាយាមម្តងទៀត។';
        toastr.error(msg);
    }

    let CATALOG = [];
    let ROLE_CACHE = [];
    let currentRoleId = null;
    let currentRolePermIds = [];
    let currentUserId = null;

    function loadCatalog() {
        return ajax({ url: ROUTES.catalog }).done(function (res) {
            CATALOG = res.data || [];
            const $moduleFilter = $('#acc-ea-filter-module');
            CATALOG.forEach(m => $moduleFilter.append(`<option value="${esc(m.module)}">${esc(m.module_label)}</option>`));
        });
    }

    // ---------------- ROLES & PERMISSIONS ----------------

    function renderRoleList(filterText) {
        const $list = $('#acc-role-list').empty();
        const term = (filterText || '').toLowerCase();
        const roles = ROLE_CACHE.filter(r => r.name.toLowerCase().includes(term));
        if (!roles.length) {
            $list.append('<div class="p-3 text-muted small text-center">មិនមានទិន្នន័យ</div>');
            return;
        }
        roles.forEach(r => {
            const active = r.id === currentRoleId ? 'active' : '';
            $list.append(`
                <div class="acc-list-item ${active}" data-id="${r.id}">
                    <div>
                        <div class="name">${esc(r.name)} ${r.protected ? '<span class="acc-badge-protected">System</span>' : ''}</div>
                        <div class="meta">${r.user_count} អ្នកប្រើប្រាស់</div>
                    </div>
                    <i class="fa fa-chevron-right text-muted"></i>
                </div>`);
        });
    }

    function loadRoles(selectAfter) {
        return ajax({ url: ROUTES.roles }).done(function (res) {
            ROLE_CACHE = res.data || [];
            renderRoleList($('#acc-role-search').val());
            if (selectAfter) selectRole(selectAfter);
        });
    }

    function buildMatrix(checkedIds, moduleClickable) {
        let html = '';
        CATALOG.forEach(m => {
            html += `<div class="acc-module-block" data-module="${esc(m.module)}">
                <div class="module-head">
                    <span>${esc(m.module_label)}</span>
                    ${moduleClickable ? `<label class="small mb-0"><input type="checkbox" class="acc-module-toggle" data-module="${esc(m.module)}"> ជ្រើសទាំងអស់</label>` : ''}
                </div>`;
            m.resources.forEach(r => {
                html += `<div class="resource-row"><div class="resource-title">${esc(r.display_name)}</div>`;
                r.permissions.forEach(p => {
                    const checked = checkedIds.includes(p.id) ? 'checked' : '';
                    html += `<label class="acc-action-chip" title="${esc(p.name)}">
                        <input type="checkbox" class="acc-perm-checkbox" data-id="${p.id}" data-action="${esc(p.action)}" data-module="${esc(m.module)}" ${checked} ${moduleClickable ? '' : 'disabled'}>
                        ${esc(p.display_name)}
                    </label>`;
                });
                html += `</div>`;
            });
            html += `</div>`;
        });
        return html || '<div class="text-muted small p-3">មិនមានសិទ្ធិណាមួយ</div>';
    }

    function selectRole(id) {
        currentRoleId = id;
        renderRoleList($('#acc-role-search').val());
        ajax({ url: ROUTES.roleShow(id) }).done(function (res) {
            const role = res.data;
            currentRolePermIds = (role.permission_ids || []).slice();
            const protectedNote = role.protected
                ? `<div class="acc-direct-warning">នេះជាតួនាទីប្រព័ន្ធ (Super Admin) ដែលមានសិទ្ធិគ្រប់យ៉ាងស្រាប់ដោយស្វ័យប្រវត្តិ — មិនអាចកែប្រែ ឬលុបបានឡើយ។</div>`
                : '';
            const actions = role.protected ? '' : `
                <button class="btn btn-outline-secondary btn-sm" id="acc-role-edit-btn"><i class="fa fa-pencil"></i> កែឈ្មោះ</button>
                <button class="btn btn-outline-danger btn-sm" id="acc-role-delete-btn" ${role.user_count > 0 ? 'disabled title="មិនអាចលុបបានទេ ព្រោះមានអ្នកប្រើប្រាស់កំពុងប្រើ"' : ''}><i class="fa fa-trash"></i> លុប</button>`;

            $('#acc-role-detail').html(`
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="mb-0">${esc(role.name)}</h6>
                        <div class="text-muted small">${role.user_count} អ្នកប្រើប្រាស់</div>
                    </div>
                    <div class="d-flex gap-2">${actions}</div>
                </div>
                ${protectedNote}
                <div id="acc-role-matrix">${buildMatrix(currentRolePermIds, !role.protected)}</div>
                ${role.protected ? '' : '<div class="text-end mt-3"><button class="btn btn-success" id="acc-role-save-perms">រក្សាទុកសិទ្ធិ</button></div>'}
            `);
        });
    }

    $(document).on('click', '.acc-list-item', function () { selectRole($(this).data('id')); });
    $('#acc-role-search').on('input', function () { renderRoleList($(this).val()); });

    $(document).on('change', '.acc-module-toggle', function () {
        const module = $(this).data('module');
        const check = $(this).is(':checked');
        $(`.acc-perm-checkbox[data-module="${module}"]`).prop('checked', check);
    });

    $(document).on('click', '#acc-role-save-perms', function () {
        const ids = $('.acc-perm-checkbox:checked').map(function () { return parseInt($(this).data('id'), 10); }).get();
        const added = ids.filter(i => !currentRolePermIds.includes(i)).length;
        const removed = currentRolePermIds.filter(i => !ids.includes(i)).length;
        Swal.fire({
            title: 'បញ្ជាក់ការផ្លាស់ប្តូរសិទ្ធិ?',
            html: `បន្ថែម <b>${added}</b> សិទ្ធិ, ដក <b>${removed}</b> សិទ្ធិ`,
            icon: 'question', showCancelButton: true, confirmButtonText: 'រក្សាទុក', cancelButtonText: 'បោះបង់',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            ajax({ url: ROUTES.rolePermUpdate(currentRoleId), method: 'PUT', data: { permission_ids: ids } })
                .done(function (res) {
                    toastr.success(res.message);
                    currentRolePermIds = ids;
                    loadRoles();
                }).fail(apiError);
        });
    });

    $('#acc-role-add-btn').on('click', function () {
        $('#acc-role-form-id').val('');
        $('#acc-role-form-name').val('');
        $('#accRoleFormTitle').text('បន្ថែមតួនាទីថ្មី');
        new bootstrap.Modal(document.getElementById('accRoleFormModal')).show();
    });
    $(document).on('click', '#acc-role-edit-btn', function () {
        const role = ROLE_CACHE.find(r => r.id === currentRoleId);
        $('#acc-role-form-id').val(currentRoleId);
        $('#acc-role-form-name').val(role ? role.name : '');
        $('#accRoleFormTitle').text('កែឈ្មោះតួនាទី');
        new bootstrap.Modal(document.getElementById('accRoleFormModal')).show();
    });
    $('#acc-role-form-save').on('click', function () {
        const id = $('#acc-role-form-id').val();
        const name = $('#acc-role-form-name').val().trim();
        if (!name) { toastr.error('សូមបញ្ចូលឈ្មោះតួនាទី'); return; }
        const req = id
            ? ajax({ url: ROUTES.roleUpdate(id), method: 'PUT', data: { name: name } })
            : ajax({ url: ROUTES.roles, method: 'POST', data: { name: name } });
        req.done(function (res) {
            toastr.success(res.message);
            bootstrap.Modal.getInstance(document.getElementById('accRoleFormModal')).hide();
            loadRoles(res.data.id);
        }).fail(apiError);
    });
    $(document).on('click', '#acc-role-delete-btn', function () {
        Swal.fire({
            title: 'តើអ្នកប្រាកដទេ?', text: 'សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។', icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'បាទ/ចាស លុប', cancelButtonText: 'បោះបង់',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            ajax({ url: ROUTES.roleDestroy(currentRoleId), method: 'DELETE' }).done(function (res) {
                toastr.success(res.message);
                currentRoleId = null;
                $('#acc-role-detail').html('<div class="acc-empty-state"><i class="fa fa-users-cog"></i><div>សូមជ្រើសរើសតួនាទីមួយនៅខាងឆ្វេង។</div></div>');
                loadRoles();
            }).fail(apiError);
        });
    });

    // ---------------- USERS ----------------

    let userSearchTimer = null;
    $('#acc-user-search').on('input', function () {
        clearTimeout(userSearchTimer);
        const q = $(this).val();
        userSearchTimer = setTimeout(function () { searchUsers(q, '#acc-user-list', selectUser); }, 300);
    });

    function searchUsers(q, targetSelector, onClick) {
        const $target = $(targetSelector);
        if (!q || q.trim().length < 1) {
            $target.html('<div class="p-3 text-muted small text-center">សូមវាយបញ្ចូលដើម្បីស្វែងរក</div>');
            return;
        }
        ajax({ url: ROUTES.usersSearch, data: { q: q } }).done(function (res) {
            const users = res.data || [];
            if (!users.length) { $target.html('<div class="p-3 text-muted small text-center">រកមិនឃើញ</div>'); return; }
            $target.empty();
            users.forEach(u => {
                const $item = $(`
                    <div class="acc-list-item" data-id="${u.id}">
                        <div>
                            <div class="name">${esc(u.full_name)}</div>
                            <div class="meta">${esc(u.employee_code || '-')} ${u.unit_name ? '· ' + esc(u.unit_name) : ''}</div>
                        </div>
                        ${u.is_active ? '' : '<span class="acc-badge-protected">អសកម្ម</span>'}
                    </div>`);
                $item.on('click', function () { onClick(u.id); });
                $target.append($item);
            });
        });
    }

    function selectUser(id) {
        currentUserId = id;
        ajax({ url: ROUTES.userShow(id) }).done(function (res) {
            const u = res.data;
            const roleChips = CATALOG.length ? '' : '';
            let roleOptions = '';
            ROLE_CACHE.forEach(r => {
                const checked = u.roles.some(ur => ur.id === r.id) ? 'checked' : '';
                roleOptions += `<label class="acc-action-chip"><input type="checkbox" class="acc-user-role-checkbox" data-id="${r.id}" ${checked}> ${esc(r.name)}</label>`;
            });

            const scopeLabel = u.scope && u.scope.type ? (SCOPE_LABELS[u.scope.type] || u.scope.type) : '-';

            $('#acc-user-detail').html(`
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="mb-0">${esc(u.full_name)} ${u.super_admin ? '<span class="badge bg-dark ms-1">SYSTEM ACCESS · Super Administrator</span>' : ''}</h6>
                        <div class="text-muted small">${esc(u.employee_code || '-')} ${u.unit_name ? '· ' + esc(u.unit_name) : ''} ${u.position_name ? '· ' + esc(u.position_name) : ''}</div>
                        <div class="text-muted small">${esc(u.email || '')} ${u.is_active ? '<span class="badge bg-success ms-1">សកម្ម</span>' : '<span class="badge bg-secondary ms-1">អសកម្ម</span>'}</div>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" id="acc-user-view-effective"><i class="fa fa-key"></i> មើលសិទ្ធិជាក់ស្តែង</button>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded p-2 text-center h-100">
                            <div class="fw-bold">${u.effective_permission_count}</div>
                            <div class="small text-muted">សិទ្ធិសរុប (Effective)</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2 text-center h-100">
                            <div class="fw-bold">${u.roles.length}</div>
                            <div class="small text-muted">តួនាទី</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2 text-center h-100">
                            <div><span class="acc-scope-badge">${esc(scopeLabel)}</span></div>
                            <div class="small text-muted mt-1">វិសាលភាពអង្គភាព</div>
                        </div>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-semibold small">វិសាលភាព (Scopes)</div>
                    <button class="btn btn-sm btn-outline-secondary" id="acc-user-manage-scope"><i class="fa fa-sitemap"></i> គ្រប់គ្រងវិសាលភាព</button>
                </div>
                <div id="acc-user-scope-summary">${renderScopeSummary(u.scope_groups)}</div>

                <hr>
                <div class="fw-semibold small mb-2">តួនាទី (អាចជ្រើសបានច្រើន)</div>
                <div id="acc-user-roles-box">${roleOptions || '<div class="text-muted small">មិនមានតួនាទីនៅឡើយ</div>'}</div>
                <div class="text-end mt-2"><button class="btn btn-sm btn-success" id="acc-user-save-roles">រក្សាទុកតួនាទី</button></div>

                <hr>
                <div class="acc-direct-warning"><strong>សិទ្ធិផ្ទាល់ខ្លួន (Direct User Permission)</strong><br>ប្រើសម្រាប់ករណីពិសេសប៉ុណ្ណោះ។ ជាទូទៅគួរផ្តល់សិទ្ធិតាមតួនាទី។</div>
                <div id="acc-user-direct-matrix">${buildMatrix(u.direct_permissions.map(p => p.id), true)}</div>
                <div class="text-end mt-2"><button class="btn btn-sm btn-warning" id="acc-user-save-direct">រក្សាទុកសិទ្ធិផ្ទាល់ខ្លួន</button></div>
            `);
            $('#acc-user-direct-matrix .acc-module-block').addClass('user-direct');
        });
    }

    $(document).on('click', '#acc-user-save-roles', function () {
        const ids = $('#acc-user-roles-box .acc-user-role-checkbox:checked').map(function () { return parseInt($(this).data('id'), 10); }).get();
        ajax({ url: ROUTES.userRolesUpdate(currentUserId), method: 'PUT', data: { role_ids: ids } })
            .done(function (res) { toastr.success(res.message); selectUser(currentUserId); }).fail(apiError);
    });
    $(document).on('click', '#acc-user-save-direct', function () {
        const ids = $('#acc-user-direct-matrix .acc-perm-checkbox:checked').map(function () { return parseInt($(this).data('id'), 10); }).get();
        ajax({ url: ROUTES.userDirectPermUpdate(currentUserId), method: 'PUT', data: { permission_ids: ids } })
            .done(function (res) { toastr.success(res.message); selectUser(currentUserId); }).fail(apiError);
    });
    $(document).on('click', '#acc-user-view-effective', function () {
        $('button[data-bs-target="#tab-effective"]').tab('show');
        loadEffectiveAccess(currentUserId);
    });

    function renderScopeSummary(groups) {
        if (!groups || !groups.length) return '<div class="text-muted small">មិនមានការទទួលខុសត្រូវ (assignment) នៅឡើយ</div>';
        return groups.map(function (g) {
            const units = g.presentation_scope === 'SELECTED_UNITS'
                ? '<div class="small text-muted">' + g.selected_departments.map(d => esc(d.name)).join(', ') + '</div>'
                : '';
            return `<div class="d-flex justify-content-between align-items-start border-bottom py-2">
                <div><div class="fw-semibold small">${esc(g.responsibility_name)}</div>${units}</div>
                <span class="acc-scope-badge">${esc(g.presentation_scope_label)}</span>
            </div>`;
        }).join('');
    }

    $(document).on('click', '#acc-user-manage-scope', function () {
        $('button[data-bs-target="#tab-scope"]').tab('show');
        loadScopeUserById(currentUserId);
    });

    // ---------------- ORGANIZATION SCOPE ----------------

    let ORG_TREE = [];
    let scopeCurrentUserId = null;
    let scopeGroups = [];
    let scopeSearchTimer = null;

    function loadOrgTree() {
        return ajax({ url: ROUTES.orgTree }).done(function (res) { ORG_TREE = res.data || []; });
    }

    $('#acc-scope-user-search').on('input', function () {
        clearTimeout(scopeSearchTimer);
        const q = $(this).val();
        scopeSearchTimer = setTimeout(function () {
            searchUsers(q, '#acc-scope-user-results', function (id) {
                $('#acc-scope-user-results').empty();
                loadScopeUserById(id);
            });
        }, 300);
    });

    function loadScopeUserById(userId) {
        scopeCurrentUserId = userId;
        ajax({ url: ROUTES.userScopes(userId) }).done(function (res) {
            scopeGroups = res.data || [];
            renderScopeGroupList();
            $('#acc-scope-editor').html('<div class="acc-empty-state"><i class="fa fa-hand-pointer-o"></i><div>សូមជ្រើសរើសការទទួលខុសត្រូវនៅខាងឆ្វេង។</div></div>');
        }).fail(apiError);
    }

    function renderScopeGroupList() {
        const $list = $('#acc-scope-group-list').empty();
        if (!scopeGroups.length) {
            $list.append('<div class="p-3 text-muted small text-center">អ្នកប្រើប្រាស់នេះមិនមានការទទួលខុសត្រូវ (UserAssignment) នៅឡើយទេ។ សូមបង្កើតជាមុនសិនតាមរយៈអេក្រង់ Org Governance។</div>');
            return;
        }
        scopeGroups.forEach(g => {
            const $item = $(`
                <div class="acc-list-item" data-key="${esc(g.group_key)}">
                    <div>
                        <div class="name">${esc(g.responsibility_name)}</div>
                        <div class="meta">${esc(g.presentation_scope_label)}</div>
                    </div>
                    <i class="fa fa-chevron-right text-muted"></i>
                </div>`);
            $item.on('click', function () { renderScopeEditor(g.group_key); });
            $list.append($item);
        });
    }

    function renderScopeEditor(groupKey) {
        $('#acc-scope-group-list .acc-list-item').removeClass('active').filter(`[data-key="${groupKey}"]`).addClass('active');
        const g = scopeGroups.find(x => x.group_key === groupKey);
        if (!g) return;

        const optionsHtml = SCOPE_PRESENTATION_OPTIONS.map(o => `
            <div class="form-check">
                <input class="form-check-input acc-scope-type-radio" type="radio" name="acc-scope-type" id="acc-scope-type-${o.value}" value="${o.value}" ${g.presentation_scope === o.value ? 'checked' : ''}>
                <label class="form-check-label small" for="acc-scope-type-${o.value}">${o.label}</label>
            </div>`).join('');

        const selectedIds = g.selected_departments.map(d => d.id);
        const pickerHtml = ORG_TREE.map(node => `
            <label class="d-block small py-1">
                <input type="checkbox" class="acc-scope-unit-checkbox" value="${node.id}" ${selectedIds.includes(node.id) ? 'checked' : ''}>
                ${esc(node.label)}
            </label>`).join('');

        $('#acc-scope-editor').html(`
            <h6 class="mb-1">${esc(g.responsibility_name)}</h6>
            <div class="text-muted small mb-3">${g.effective_unit_count === null ? 'អនុវត្តលើអង្គភាពទាំងមូល (ALL)' : 'អង្គភាពជាក់ស្តែង: ' + g.effective_unit_count}</div>

            <div class="fw-semibold small mb-2">ប្រភេទវិសាលភាព (Scope Type)</div>
            <div id="acc-scope-type-options" class="mb-3">${optionsHtml}</div>

            <div id="acc-scope-unit-picker-wrap" class="${g.presentation_scope === 'SELECTED_UNITS' ? '' : 'd-none'}">
                <div class="fw-semibold small mb-2">អង្គភាព (Organization Units)</div>
                <input type="text" class="form-control form-control-sm mb-2" id="acc-scope-unit-search" placeholder="ស្វែងរកអង្គភាព...">
                <div id="acc-scope-unit-picker" style="max-height:260px;overflow-y:auto;border:1px solid #eaecf0;border-radius:8px;padding:8px 12px;">${pickerHtml}</div>
            </div>

            <div class="text-end mt-3"><button class="btn btn-success btn-sm" id="acc-scope-save" data-group="${esc(groupKey)}">រក្សាទុកវិសាលភាព</button></div>
        `);
    }

    $(document).on('change', '.acc-scope-type-radio', function () {
        $('#acc-scope-unit-picker-wrap').toggleClass('d-none', $(this).val() !== 'SELECTED_UNITS');
    });
    $(document).on('input', '#acc-scope-unit-search', function () {
        const term = $(this).val().toLowerCase();
        $('#acc-scope-unit-picker label').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(term));
        });
    });

    $(document).on('click', '#acc-scope-save', function () {
        const groupKey = $(this).data('group');
        const scopeType = $('input[name="acc-scope-type"]:checked').val();
        let departmentIds;
        if (scopeType === 'SELECTED_UNITS') {
            departmentIds = $('.acc-scope-unit-checkbox:checked').map(function () { return parseInt($(this).val(), 10); }).get();
            if (!departmentIds.length) { toastr.error('សូមជ្រើសរើសអង្គភាពយ៉ាងហោចណាស់មួយ។'); return; }
        } else {
            const g = scopeGroups.find(x => x.group_key === groupKey);
            departmentIds = g && g.selected_departments.length ? [g.selected_departments[0].id] : [];
            if (!departmentIds.length) { toastr.error('គ្មានអង្គភាពមូលដ្ឋានសម្រាប់ការទទួលខុសត្រូវនេះទេ។'); return; }
        }

        ajax({ url: ROUTES.userScopeUpdate(scopeCurrentUserId, groupKey), method: 'PUT', data: { scope_type: scopeType, department_ids: departmentIds } })
            .done(function (res) {
                toastr.success(res.message);
                loadScopeUserById(scopeCurrentUserId);
            }).fail(apiError);
    });

    // ---------------- APPROVAL AUTHORITY ----------------

    let APPROVAL_OPTIONS = null;
    let APPROVAL_DEFS = [];
    let currentApprovalDefId = null;

    function loadApprovalOptions() {
        return ajax({ url: ROUTES.approvalOptions }).done(function (res) { APPROVAL_OPTIONS = res.data; });
    }

    function loadApprovalDefinitions(selectAfter) {
        return ajax({ url: ROUTES.approvals }).done(function (res) {
            APPROVAL_DEFS = res.data || [];
            renderApprovalList();
            if (selectAfter) selectApprovalDefinition(selectAfter);
        });
    }

    function renderApprovalList() {
        const $list = $('#acc-approval-list').empty();
        if (!APPROVAL_DEFS.length) {
            $list.append('<div class="p-3 text-muted small text-center">មិនមានទិន្នន័យ</div>');
            return;
        }
        APPROVAL_DEFS.forEach(d => {
            const active = d.id === currentApprovalDefId ? 'active' : '';
            $list.append(`
                <div class="acc-list-item ${active}" data-id="${d.id}">
                    <div>
                        <div class="name">${esc(d.name)}</div>
                        <div class="meta">${esc(d.module_label)} · ${d.step_count} ជំហាន ${d.is_active ? '' : '<span class="acc-badge-no">អសកម្ម</span>'}</div>
                    </div>
                    <i class="fa fa-chevron-right text-muted"></i>
                </div>`);
        });
    }
    $(document).on('click', '#acc-approval-list .acc-list-item', function () { selectApprovalDefinition(parseInt($(this).data('id'), 10)); });

    function actorPickerHtml(step) {
        const t = step.actor_type;
        const wrap = (inner) => `<select class="form-select form-select-sm acc-step-actor-picker">${inner}</select>`;
        if (t === 'specific_user') {
            const opts = (APPROVAL_OPTIONS.users || []).map(u => `<option value="${u.id}" ${step.actor_user_id === u.id ? 'selected' : ''}>${esc(u.full_name)}</option>`).join('');
            return wrap('<option value="">-- ជ្រើសរើសអ្នកប្រើប្រាស់ --</option>' + opts);
        }
        if (t === 'position') {
            const opts = (APPROVAL_OPTIONS.positions || []).map(p => `<option value="${p.id}" ${step.actor_position_id === p.id ? 'selected' : ''}>${esc(p.position_name_km || p.position_name)}</option>`).join('');
            return wrap('<option value="">-- ជ្រើសរើសតួនាទីការងារ --</option>' + opts);
        }
        if (t === 'spatie_role') {
            const opts = (APPROVAL_OPTIONS.spatie_roles || []).map(r => `<option value="${r.id}" ${step.actor_role_id === r.id ? 'selected' : ''}>${esc(r.name)}</option>`).join('');
            return wrap('<option value="">-- ជ្រើសរើសតួនាទីប្រព័ន្ធ --</option>' + opts);
        }
        const opts = (APPROVAL_OPTIONS.responsibilities || []).map(r => `<option value="${r.id}" ${step.actor_responsibility_id === r.id ? 'selected' : ''}>${esc(r.name_km || r.name)}</option>`).join('');
        return wrap('<option value="">-- ជ្រើសរើសការទទួលខុសត្រូវ --</option>' + opts);
    }

    function approvalStepCardHtml(step, index) {
        const actorTypeOpts = APPROVAL_OPTIONS.actor_type_options.map(o => `<option value="${o.value}" ${step.actor_type === o.value ? 'selected' : ''}>${esc(o.label)}</option>`).join('');
        const actionTypeOpts = APPROVAL_OPTIONS.action_type_options.map(o => `<option value="${o.value}" ${step.action_type === o.value ? 'selected' : ''}>${esc(o.label)}</option>`).join('');
        const scopeTypeOpts = APPROVAL_OPTIONS.scope_type_options.map(o => `<option value="${o.value}" ${step.scope_type === o.value ? 'selected' : ''}>${esc(o.label)}</option>`).join('');
        const flag = (key, label) => `<label class="acc-action-chip"><input type="checkbox" class="acc-step-flag" data-flag="${key}" ${step[key] ? 'checked' : ''}> ${label}</label>`;

        return `
            <div class="acc-step-card" data-index="${index}">
                <div class="row g-2 align-items-center mb-2">
                    <div class="col-2 col-md-1"><input type="number" min="1" class="form-control form-control-sm acc-step-order" value="${step.step_order}" title="លំដាប់"></div>
                    <div class="col-10 col-md-6"><input type="text" class="form-control form-control-sm acc-step-name" value="${esc(step.step_name)}" placeholder="ឈ្មោះជំហាន"></div>
                    <div class="col-8 col-md-4"><select class="form-select form-select-sm acc-step-action-type">${actionTypeOpts}</select></div>
                    <div class="col-4 col-md-1 text-end"><button type="button" class="btn btn-outline-danger btn-sm acc-step-remove" title="លុបជំហាននេះ"><i class="fa fa-trash"></i></button></div>
                </div>
                <div class="row g-2 align-items-center mb-2">
                    <div class="col-md-3">
                        <select class="form-select form-select-sm acc-step-actor-type">${actorTypeOpts}</select>
                    </div>
                    <div class="col-md-4 acc-step-actor-picker-wrap">${actorPickerHtml(step)}</div>
                    <div class="col-md-5">
                        <select class="form-select form-select-sm acc-step-scope-type">${scopeTypeOpts}</select>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    ${flag('is_final_approval', 'ការអនុម័តចុងក្រោយ')}
                    ${flag('is_required', 'ចាំបាច់')}
                    ${flag('can_return', 'អាចត្រឡប់វិញ')}
                    ${flag('can_reject', 'អាចបដិសេធ')}
                </div>
            </div>`;
    }

    function selectApprovalDefinition(id) {
        currentApprovalDefId = id;
        renderApprovalList();
        ajax({ url: ROUTES.approvalShow(id) }).done(function (res) {
            renderApprovalDetail(res.data);
        }).fail(apiError);
    }

    function renderApprovalDetail(def) {
        const stepsHtml = (def.steps || []).map((s, i) => approvalStepCardHtml(s, i)).join('') || '<div class="text-muted small p-2">មិនមានជំហាននៅឡើយ</div>';

        $('#acc-approval-detail').html(`
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h6 class="mb-0">${esc(def.name)}</h6>
                    <div class="text-muted small">${esc(def.module_label)} · ${esc(def.request_type_key)}</div>
                </div>
                <label class="acc-action-chip"><input type="checkbox" id="acc-approval-active" ${def.is_active ? 'checked' : ''}> សកម្ម</label>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label small mb-1">ឈ្មោះគោលការណ៍</label>
                    <input type="text" class="form-control form-control-sm" id="acc-approval-name" value="${esc(def.name)}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">អាទិភាព (Priority)</label>
                    <input type="number" min="1" class="form-control form-control-sm" id="acc-approval-priority" value="${def.priority}">
                </div>
            </div>
            <div class="fw-semibold small mb-2">ជំហានអនុម័ត (តាមលំដាប់)</div>
            <div id="acc-approval-steps">${stepsHtml}</div>
            <div class="d-flex justify-content-between mt-2">
                <button class="btn btn-outline-secondary btn-sm" id="acc-approval-step-add"><i class="fa fa-plus"></i> បន្ថែមជំហាន</button>
                <button class="btn btn-success btn-sm" id="acc-approval-save"><i class="fa fa-save"></i> រក្សាទុក</button>
            </div>
        `);
    }

    $(document).on('change', '.acc-step-actor-type', function () {
        const $card = $(this).closest('.acc-step-card');
        const step = { actor_type: $(this).val(), actor_user_id: null, actor_position_id: null, actor_responsibility_id: null, actor_role_id: null };
        $card.find('.acc-step-actor-picker-wrap').html(actorPickerHtml(step));
    });

    $(document).on('click', '.acc-step-remove', function () {
        $(this).closest('.acc-step-card').remove();
    });

    $(document).on('click', '#acc-approval-step-add', function () {
        const nextOrder = $('#acc-approval-steps .acc-step-card').length + 1;
        const blank = { step_order: nextOrder, step_name: '', action_type: 'approve', actor_type: 'responsibility', scope_type: 'self_and_children', is_final_approval: false, is_required: true, can_return: true, can_reject: true };
        $('#acc-approval-steps').append(approvalStepCardHtml(blank, $('#acc-approval-steps .acc-step-card').length));
    });

    $(document).on('click', '#acc-approval-save', function () {
        if (!currentApprovalDefId) return;
        const steps = $('#acc-approval-steps .acc-step-card').map(function () {
            const $c = $(this);
            const step = {
                step_order: parseInt($c.find('.acc-step-order').val(), 10) || 1,
                step_name: $c.find('.acc-step-name').val(),
                action_type: $c.find('.acc-step-action-type').val(),
                actor_type: $c.find('.acc-step-actor-type').val(),
                scope_type: $c.find('.acc-step-scope-type').val(),
                is_final_approval: $c.find('[data-flag="is_final_approval"]').is(':checked'),
                is_required: $c.find('[data-flag="is_required"]').is(':checked'),
                can_return: $c.find('[data-flag="can_return"]').is(':checked'),
                can_reject: $c.find('[data-flag="can_reject"]').is(':checked'),
            };
            const actorVal = $c.find('.acc-step-actor-picker').val() || null;
            const actorId = actorVal ? parseInt(actorVal, 10) : null;
            step.actor_user_id = step.actor_type === 'specific_user' ? actorId : null;
            step.actor_position_id = step.actor_type === 'position' ? actorId : null;
            step.actor_role_id = step.actor_type === 'spatie_role' ? actorId : null;
            step.actor_responsibility_id = step.actor_type === 'responsibility' ? actorId : null;
            return step;
        }).get();

        if (!steps.length) { toastr.error('ត្រូវការជំហានយ៉ាងហោចណាស់មួយ។'); return; }

        ajax({
            url: ROUTES.approvalUpdate(currentApprovalDefId),
            method: 'PUT',
            data: {
                name: $('#acc-approval-name').val(),
                priority: $('#acc-approval-priority').val(),
                is_active: $('#acc-approval-active').is(':checked'),
                steps: steps,
            },
        }).done(function (res) {
            toastr.success(res.message);
            loadApprovalDefinitions(currentApprovalDefId);
            renderApprovalDetail(res.data);
        }).fail(apiError);
    });

    // ---- Per-user approval authority lookup ----

    let approvalUserSearchTimer = null;
    $('#acc-approval-user-search').on('input', function () {
        clearTimeout(approvalUserSearchTimer);
        const q = $(this).val();
        approvalUserSearchTimer = setTimeout(function () {
            searchUsers(q, '#acc-approval-user-results', function (id) {
                $('#acc-approval-user-results').empty();
                loadUserApprovalAuthority(id);
            });
        }, 300);
    });

    function loadUserApprovalAuthority(userId) {
        ajax({ url: ROUTES.userApprovalAuthority(userId) }).done(function (res) {
            renderUserApprovalAuthority(res.data || []);
        }).fail(apiError);
    }

    function renderUserApprovalAuthority(rows) {
        if (!rows.length) {
            $('#acc-approval-user-result').html('<div class="acc-empty-state"><i class="fa fa-user-check"></i><div>មិនមានគោលការណ៍អនុម័តសកម្មនៅឡើយទេ។</div></div>');
            return;
        }
        const byModule = {};
        rows.forEach(r => { (byModule[r.module_label] = byModule[r.module_label] || []).push(r); });

        let html = '';
        Object.keys(byModule).forEach(function (moduleLabel) {
            html += `<div class="acc-module-block"><div class="module-head"><span>${esc(moduleLabel)}</span></div>`;
            byModule[moduleLabel].forEach(function (r) {
                html += `<div class="acc-ea-row">
                    <div><div class="name">${esc(r.definition_name)} — ${esc(r.step_name)}</div><div class="src">${esc(r.action_type)} ${r.is_final_approval ? '· ការអនុម័តចុងក្រោយ' : ''}</div></div>
                    <div>${r.can_act ? '<span class="acc-badge-yes">អាចអនុម័ត</span>' : '<span class="acc-badge-no">មិនអាច</span>'}</div>
                </div>`;
            });
            html += `</div>`;
        });
        $('#acc-approval-user-result').html(html);
    }

    // ---------------- DELEGATION ----------------

    let DELEG_OPTIONS = null;
    let delegFromUser = null;
    let delegToUser = null;
    let delegAuthorities = [];
    let delegSelectedAuthorities = [];
    let delegBoundaryTree = [];
    let delegState = 'active';
    let currentDelegationUuid = null;

    function loadDelegationOptions() {
        return ajax({ url: ROUTES.delegationOptions }).done(function (res) { DELEG_OPTIONS = res.data; });
    }

    function loadDelegations() {
        return ajax({ url: ROUTES.delegationsIndex, data: { state: delegState } }).done(function (res) {
            renderDelegationList(res.data || []);
        }).fail(apiError);
    }

    function renderDelegationList(rows) {
        const $list = $('#acc-deleg-list').empty();
        if (!rows.length) {
            $list.append('<div class="p-3 text-muted small text-center">មិនមានទិន្នន័យ</div>');
            return;
        }
        const stateLabels = { active: 'សកម្ម', upcoming: 'នឹងមកដល់', expired: 'ផុតកំណត់', revoked: 'បានដកហូត' };
        rows.forEach(r => {
            const active = r.uuid === currentDelegationUuid ? 'active' : '';
            $list.append(`
                <div class="acc-list-item ${active}" data-uuid="${esc(r.uuid)}">
                    <div>
                        <div class="name">${esc(r.delegator?.full_name || '-')} → ${esc(r.delegatee?.full_name || '-')}</div>
                        <div class="meta">${esc(r.authority_module_label)} · ${stateLabels[r.state] || r.state}</div>
                    </div>
                    <i class="fa fa-chevron-right text-muted"></i>
                </div>`);
        });
    }

    $(document).on('click', '.acc-deleg-state-btn', function () {
        $('.acc-deleg-state-btn').removeClass('active btn-secondary').addClass('btn-outline-secondary');
        $(this).addClass('active btn-secondary').removeClass('btn-outline-secondary');
        delegState = $(this).data('state');
        loadDelegations();
    });

    $(document).on('click', '#acc-deleg-list .acc-list-item', function () {
        currentDelegationUuid = $(this).data('uuid');
        ajax({ url: ROUTES.delegationsIndex, data: { state: 'all' } }).done(function (res) {
            const row = (res.data || []).find(r => r.uuid === currentDelegationUuid);
            if (row) renderDelegationDetail(row);
        });
        $('#acc-deleg-list .acc-list-item').removeClass('active').filter(`[data-uuid="${currentDelegationUuid}"]`).addClass('active');
    });

    function fmtDateTime(iso) {
        if (!iso) return '-';
        const d = new Date(iso);
        return isNaN(d) ? iso : d.toLocaleString();
    }

    function renderDelegationDetail(r) {
        const stateLabels = { active: 'សកម្ម', upcoming: 'នឹងមកដល់', expired: 'ផុតកំណត់', revoked: 'បានដកហូត' };
        const canRevoke = r.state === 'active' || r.state === 'upcoming';
        $('#acc-deleg-detail').html(`
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h6 class="mb-0">${esc(r.delegator?.full_name || '-')} <i class="fa fa-arrow-right mx-1"></i> ${esc(r.delegatee?.full_name || '-')}</h6>
                    <div class="text-muted small">${esc(r.authority_module_label)}</div>
                </div>
                <span class="acc-scope-badge">${stateLabels[r.state] || r.state}</span>
            </div>
            <div class="acc-module-block">
                <div class="module-head"><span>ព័ត៌មានលម្អិត</span></div>
                <div class="acc-ea-row"><div class="name">រយៈពេល</div><div class="src">${fmtDateTime(r.starts_at)} → ${fmtDateTime(r.ends_at)}</div></div>
                <div class="acc-ea-row"><div class="name">វិសាលភាព</div><div class="src">${esc(r.scope_type)} (${(r.scope_department_ids || []).length} អង្គភាព)</div></div>
                ${r.reason ? `<div class="acc-ea-row"><div class="name">មូលហេតុ</div><div class="src">${esc(r.reason)}</div></div>` : ''}
                ${r.revoked_at ? `<div class="acc-ea-row"><div class="name">បានដកហូតនៅ</div><div class="src">${fmtDateTime(r.revoked_at)}</div></div>` : ''}
            </div>
            ${canRevoke ? `<div class="text-end mt-3"><button class="btn btn-outline-danger btn-sm" id="acc-deleg-revoke-btn" data-uuid="${esc(r.uuid)}"><i class="fa fa-ban"></i> ដកហូតសិទ្ធិ</button></div>` : ''}
        `);
    }

    $(document).on('click', '#acc-deleg-revoke-btn', function () {
        const uuid = $(this).data('uuid');
        Swal.fire({
            title: 'តើអ្នកប្រាកដទេ?', text: 'សិទ្ធិដែលបានផ្ទេរនេះនឹងឈប់ដំណើរការភ្លាមៗ។', icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'ដកហូត', cancelButtonText: 'បោះបង់',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            ajax({ url: ROUTES.delegationRevoke(uuid), method: 'POST' }).done(function (res) {
                toastr.success(res.message);
                loadDelegations();
                renderDelegationDetail(res.data);
            }).fail(apiError);
        });
    });

    // ---- Create modal ----

    $('#acc-deleg-new-btn').on('click', function () {
        delegFromUser = null; delegToUser = null; delegAuthorities = []; delegSelectedAuthorities = []; delegBoundaryTree = [];
        $('#acc-deleg-from-search, #acc-deleg-to-search').val('');
        $('#acc-deleg-from-selected, #acc-deleg-to-selected').empty();
        $('#acc-deleg-authority-box').html('<div class="text-muted small">សូមជ្រើសរើស "ពី" សិន</div>');
        $('#acc-deleg-scope-options').empty();
        $('#acc-deleg-unit-picker-wrap').addClass('d-none');
        $('#acc-deleg-starts-at, #acc-deleg-ends-at, #acc-deleg-reason').val('');
        renderDelegationPreview();
        new bootstrap.Modal(document.getElementById('accDelegationFormModal')).show();
    });

    let delegFromSearchTimer = null;
    $('#acc-deleg-from-search').on('input', function () {
        clearTimeout(delegFromSearchTimer);
        const q = $(this).val();
        delegFromSearchTimer = setTimeout(function () {
            searchUsers(q, '#acc-deleg-from-results', function (id) {
                selectDelegFrom(id);
                $('#acc-deleg-from-results').empty();
                $('#acc-deleg-from-search').val('');
            });
        }, 300);
    });
    let delegToSearchTimer = null;
    $('#acc-deleg-to-search').on('input', function () {
        clearTimeout(delegToSearchTimer);
        const q = $(this).val();
        delegToSearchTimer = setTimeout(function () {
            searchUsers(q, '#acc-deleg-to-results', function (id) {
                selectDelegTo(id);
                $('#acc-deleg-to-results').empty();
                $('#acc-deleg-to-search').val('');
            });
        }, 300);
    });

    function userBadge(u) {
        return `<span class="acc-scope-badge">${esc(u.full_name)} ${u.employee_code ? '· ' + esc(u.employee_code) : ''} ${u.position_name ? '· ' + esc(u.position_name) : ''} ${u.unit_name ? '· ' + esc(u.unit_name) : ''}</span>`;
    }

    function selectDelegFrom(id) {
        ajax({ url: ROUTES.userShow(id) }).done(function (res) {
            delegFromUser = res.data;
            $('#acc-deleg-from-selected').html(userBadge(res.data));
            loadDelegAuthorities(id);
            loadDelegBoundary(id);
        }).fail(apiError);
    }
    function selectDelegTo(id) {
        ajax({ url: ROUTES.userShow(id) }).done(function (res) {
            delegToUser = res.data;
            $('#acc-deleg-to-selected').html(userBadge(res.data));
            renderDelegationPreview();
        }).fail(apiError);
    }

    function loadDelegAuthorities(userId) {
        ajax({ url: ROUTES.delegationAuthorities(userId) }).done(function (res) {
            delegAuthorities = res.data || [];
            delegSelectedAuthorities = [];
            if (!delegAuthorities.length) {
                $('#acc-deleg-authority-box').html('<div class="text-muted small">អ្នកប្រើប្រាស់នេះមិនមានសិទ្ធិអនុម័តដែលអាចផ្ទេរបានទេ។</div>');
                return;
            }
            $('#acc-deleg-authority-box').html(delegAuthorities.map(a => `
                <label class="acc-action-chip"><input type="checkbox" class="acc-deleg-authority-cb" value="${esc(a.module_key)}"> ${esc(a.module_label)}</label>
            `).join(''));
        }).fail(apiError);
    }
    $(document).on('change', '.acc-deleg-authority-cb', function () {
        delegSelectedAuthorities = $('.acc-deleg-authority-cb:checked').map(function () { return $(this).val(); }).get();
        renderDelegationPreview();
    });

    function loadDelegBoundary(userId) {
        ajax({ url: ROUTES.delegationBoundary(userId) }).done(function (res) {
            delegBoundaryTree = res.data.tree || [];
            renderDelegScopeOptions();
        }).fail(apiError);
    }

    function renderDelegScopeOptions() {
        const options = (DELEG_OPTIONS && DELEG_OPTIONS.scope_type_options) || [];
        $('#acc-deleg-scope-options').html(options.map(o => `
            <div class="form-check">
                <input class="form-check-input acc-deleg-scope-radio" type="radio" name="acc-deleg-scope" id="acc-deleg-scope-${o.value}" value="${o.value}">
                <label class="form-check-label small" for="acc-deleg-scope-${o.value}">${esc(o.label)}</label>
            </div>`).join(''));
        renderDelegUnitPicker();
    }

    function renderDelegUnitPicker() {
        $('#acc-deleg-unit-picker').html(delegBoundaryTree.map(node => `
            <label class="d-block small py-1" style="padding-left:${(node.depth || 0) * 16}px;" ${node.in_scope ? '' : `title="${esc(node.disabled_reason || '')}"`}>
                <input type="checkbox" class="acc-deleg-unit-checkbox" value="${node.id}" ${node.in_scope ? '' : 'disabled'}>
                ${esc(node.label)} ${node.in_scope ? '' : '<span class="text-muted">(' + esc(node.disabled_reason || 'Outside scope') + ')</span>'}
            </label>`).join(''));
    }

    $(document).on('change', '.acc-deleg-scope-radio', function () {
        const val = $(this).val();
        const needsPicker = val !== 'ORGANIZATION';
        $('#acc-deleg-unit-picker-wrap').toggleClass('d-none', !needsPicker);
        if (needsPicker && val !== 'SELECTED_UNITS') {
            // single-select behavior for SELF/UNIT/UNIT_TREE
            $('.acc-deleg-unit-checkbox').off('click.singleSelect').on('click.singleSelect', function () {
                $('.acc-deleg-unit-checkbox').not(this).prop('checked', false);
            });
        } else {
            $('.acc-deleg-unit-checkbox').off('click.singleSelect');
        }
        renderDelegationPreview();
    });
    $(document).on('change', '.acc-deleg-unit-checkbox', renderDelegationPreview);
    $(document).on('input', '#acc-deleg-unit-search', function () {
        const term = $(this).val().toLowerCase();
        $('#acc-deleg-unit-picker label').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(term));
        });
    });
    $('#acc-deleg-starts-at, #acc-deleg-ends-at, #acc-deleg-reason').on('input', renderDelegationPreview);

    function renderDelegationPreview() {
        const authorityLabels = delegAuthorities.filter(a => delegSelectedAuthorities.includes(a.module_key)).map(a => a.module_label);
        const scopeType = $('input[name="acc-deleg-scope"]:checked').val();
        const unitLabels = $('.acc-deleg-unit-checkbox:checked').map(function () {
            return $(this).closest('label').text().trim();
        }).get();
        const starts = $('#acc-deleg-starts-at').val();
        const ends = $('#acc-deleg-ends-at').val();
        const neverGrant = (DELEG_OPTIONS && DELEG_OPTIONS.never_delegatable) || [];

        $('#acc-deleg-preview').html(`
            <div class="acc-module-block">
                <div class="module-head"><span>មើលជាមុន (Preview)</span></div>
                <div class="acc-ea-row"><div class="name">ពី → ទៅ</div><div class="src">${delegFromUser ? esc(delegFromUser.full_name) : '-'} → ${delegToUser ? esc(delegToUser.full_name) : '-'}</div></div>
                <div class="acc-ea-row"><div class="name">សិទ្ធិអនុម័ត</div><div class="src">${authorityLabels.length ? authorityLabels.map(esc).join(', ') : '-'}</div></div>
                <div class="acc-ea-row"><div class="name">វិសាលភាព</div><div class="src">${scopeType ? esc(scopeType) : '-'}${unitLabels.length ? ': ' + unitLabels.map(esc).join(', ') : ''}</div></div>
                <div class="acc-ea-row"><div class="name">រយៈពេល</div><div class="src">${starts ? esc(starts) : '-'} → ${ends ? esc(ends) : '-'}</div></div>
            </div>
            <div class="acc-direct-warning"><strong>នឹងមិនផ្ដល់សិទ្ធិ (Will NOT grant):</strong><br>${neverGrant.map(esc).join(' · ')}</div>
        `);
    }

    $('#acc-deleg-save-btn').on('click', function () {
        const scopeType = $('input[name="acc-deleg-scope"]:checked').val();
        const departmentIds = $('.acc-deleg-unit-checkbox:checked').map(function () { return parseInt($(this).val(), 10); }).get();

        if (!delegFromUser || !delegToUser) { toastr.error('សូមជ្រើសរើសទាំង "ពី" និង "ទៅ"។'); return; }
        if (delegSelectedAuthorities.length !== 1) { toastr.error('សូមជ្រើសរើសសិទ្ធិអនុម័តមួយ។'); return; }
        if (!scopeType) { toastr.error('សូមជ្រើសរើសវិសាលភាព។'); return; }
        if (scopeType !== 'ORGANIZATION' && !departmentIds.length) { toastr.error('សូមជ្រើសរើសអង្គភាពយ៉ាងហោចណាស់មួយ។'); return; }
        if (!$('#acc-deleg-starts-at').val() || !$('#acc-deleg-ends-at').val()) { toastr.error('សូមបញ្ចូលរយៈពេល។'); return; }

        ajax({
            url: ROUTES.delegationStore,
            method: 'POST',
            data: {
                delegator_user_id: delegFromUser.id,
                delegatee_user_id: delegToUser.id,
                authority_module_key: delegSelectedAuthorities[0],
                scope_type: scopeType,
                department_ids: departmentIds,
                starts_at: $('#acc-deleg-starts-at').val(),
                ends_at: $('#acc-deleg-ends-at').val(),
                reason: $('#acc-deleg-reason').val(),
            },
        }).done(function (res) {
            toastr.success(res.message);
            bootstrap.Modal.getInstance(document.getElementById('accDelegationFormModal')).hide();
            currentDelegationUuid = res.data.uuid;
            loadDelegations();
        }).fail(apiError);
    });

    // ---------------- EFFECTIVE ACCESS ----------------

    let eaData = null;
    let eaSearchTimer = null;
    $('#acc-ea-search').on('input', function () {
        clearTimeout(eaSearchTimer);
        const q = $(this).val();
        eaSearchTimer = setTimeout(function () { searchUsers(q, '#acc-ea-search-results', loadEffectiveAccess); }, 300);
    });
    $('#acc-ea-filter-module, #acc-ea-filter-allowed, #acc-ea-filter-source').on('change', renderEffectiveAccess);

    let eaScopeGroups = [];
    let eaApprovalRows = [];

    function loadEffectiveAccess(userId) {
        $('#acc-ea-search-results').empty();
        ajax({ url: ROUTES.userEffectiveAccess(userId) }).done(function (res) {
            eaData = res.data;
            renderEffectiveAccess();
        }).fail(apiError);
        ajax({ url: ROUTES.userScopes(userId) }).done(function (res) {
            eaScopeGroups = res.data || [];
            renderEffectiveAccess();
        });
        ajax({ url: ROUTES.userApprovalAuthority(userId) }).done(function (res) {
            eaApprovalRows = res.data || [];
            renderEffectiveAccess();
        });
    }

    function renderEffectiveAccess() {
        if (!eaData) return;
        const moduleFilter = $('#acc-ea-filter-module').val();
        const allowedFilter = $('#acc-ea-filter-allowed').val();
        const sourceFilter = $('#acc-ea-filter-source').val();

        let html = `<div class="mb-3">
            <strong>${esc(eaData.roles.join(', ') || 'គ្មានតួនាទី')}</strong>
            ${eaData.super_admin ? '<span class="badge bg-dark ms-2">Super Administrator</span>' : ''}
            <span class="acc-scope-badge ms-2">${esc(SCOPE_LABELS[eaData.scope.type] || eaData.scope.type || '-')}</span>
        </div>`;

        if (eaScopeGroups.length) {
            html += `<div class="acc-module-block"><div class="module-head"><span>វិសាលភាពអង្គភាព (WHERE — តាមការទទួលខុសត្រូវនីមួយៗ)</span></div>`;
            eaScopeGroups.forEach(function (g) {
                const units = g.presentation_scope === 'SELECTED_UNITS'
                    ? g.selected_departments.map(d => esc(d.name)).join(', ')
                    : (g.effective_unit_count === null ? 'អង្គភាពទាំងមូល' : g.effective_unit_count + ' អង្គភាព');
                html += `<div class="acc-ea-row">
                    <div><div class="name">${esc(g.responsibility_name)}</div><div class="src">${esc(units)}</div></div>
                    <span class="acc-scope-badge">${esc(g.presentation_scope_label)}</span>
                </div>`;
            });
            html += `</div>
            <div class="text-muted small mb-3" style="padding:0 14px;">
                <i class="fa fa-info-circle"></i> ចំណាំ៖ សិទ្ធិ (Permission, តាម Role) និងវិសាលភាព (Scope, តាមការទទួលខុសត្រូវ/Responsibility) ជាប្រព័ន្ធពីរផ្សេងគ្នាក្នុងស្ថាបត្យកម្មបច្ចុប្បន្ន — មិនទាន់ភ្ជាប់គ្នាដោយផ្ទាល់ទេ។ បញ្ជីខាងលើបង្ហាញវិសាលភាពសរុបរបស់អ្នកប្រើប្រាស់នេះ ដាច់ដោយឡែកពីប្រភពសិទ្ធិខាងក្រោម។
            </div>`;
        }

        const canActRows = (eaApprovalRows || []).filter(r => r.can_act);
        if (canActRows.length) {
            html += `<div class="acc-module-block"><div class="module-head"><span>សិទ្ធិអនុម័ត (APPROVAL — តាមលំហូរការងារ)</span></div>`;
            canActRows.forEach(function (r) {
                html += `<div class="acc-ea-row">
                    <div><div class="name">${esc(r.module_label)}</div><div class="src">${esc(r.definition_name)} · ${esc(r.step_name)}</div></div>
                    <span class="acc-badge-yes">${r.is_final_approval ? 'អនុម័តចុងក្រោយ' : 'អនុញ្ញាតឲ្យសម្រេច'}</span>
                </div>`;
            });
            html += `</div>`;
        }

        const temporaryDelegations = eaData.temporary_delegations || [];
        if (temporaryDelegations.length) {
            html += `<div class="acc-module-block"><div class="module-head"><span>សិទ្ធិផ្ទេរបណ្តោះអាសន្ន (TEMPORARY — Central Delegation)</span></div>`;
            temporaryDelegations.forEach(function (d) {
                const scopeText = (d.scope_department_ids || []).length ? d.scope_department_ids.length + ' អង្គភាព' : (d.scope_type || '-');
                html += `<div class="acc-ea-row">
                    <div><div class="name">${esc(d.authority_module_key)}</div><div class="src">ពី ${esc(d.from_user_name || '-')} · ${esc(scopeText)}</div></div>
                    <span class="acc-scope-badge">${fmtDateTime(d.ends_at)} រហូតដល់</span>
                </div>`;
            });
            html += `</div>`;
        }

        Object.keys(eaData.modules || {}).forEach(function (moduleKey) {
            if (moduleFilter && moduleFilter !== moduleKey) return;
            const moduleData = eaData.modules[moduleKey];
            const rows = (moduleData.permissions || []).filter(function (p) {
                if (allowedFilter !== '' && String(p.allowed ? 1 : 0) !== allowedFilter) return false;
                if (sourceFilter && !(p.sources || []).some(s => s.type === sourceFilter)) return false;
                return true;
            });
            if (!rows.length) return;

            html += `<div class="acc-module-block"><div class="module-head"><span>${esc(moduleData.label)}</span></div>`;
            rows.forEach(function (p) {
                const srcText = (p.sources || []).map(function (s) {
                    if (s.type === 'role') return 'Role: ' + s.name;
                    if (s.type === 'direct_user_permission') return 'Direct Permission';
                    if (s.type === 'super_admin') return 'Super Admin';
                    return s.type;
                }).join(', ') || '-';
                html += `<div class="acc-ea-row">
                    <div>
                        <div class="name">${esc(p.display_name)}</div>
                        <div class="src">ប្រភព: ${esc(srcText)}</div>
                    </div>
                    <div>${p.allowed ? '<span class="acc-badge-yes">អនុញ្ញាត</span>' : '<span class="acc-badge-no">មិនអនុញ្ញាត</span>'}</div>
                </div>`;
            });
            html += `</div>`;
        });

        $('#acc-ea-result').html(html || '<div class="text-muted small p-3">មិនមានលទ្ធផលត្រូវនឹងតម្រង</div>');
    }

    // ---------------- AUDIT HISTORY (Phase 3E) ----------------
    let auditPage = 1;
    let auditPerPage = 20;
    let auditSelectedActorId = null;
    let auditSelectedActorName = '';

    function loadAuditHistoryOptions() {
        return ajax({ url: ROUTES.auditHistoryOptions }).done(function (res) {
            const $sel = $('#acc-audit-filter-category');
            (res.data.category_options || []).forEach(o => {
                $sel.append(`<option value="${esc(o.value)}">${esc(o.label)}</option>`);
            });
        });
    }

    function auditFilters() {
        return {
            category: $('#acc-audit-filter-category').val() || '',
            actor_id: auditSelectedActorId || '',
            date_from: $('#acc-audit-filter-date-from').val() || '',
            date_to: $('#acc-audit-filter-date-to').val() || '',
            q: $('#acc-audit-filter-q').val() || '',
            page: auditPage,
            per_page: auditPerPage,
        };
    }

    function loadAuditHistory() {
        $('#acc-audit-list').html('<div class="p-4 text-center text-muted small"><i class="fa fa-spinner fa-spin"></i></div>');
        return ajax({ url: ROUTES.auditHistory, data: auditFilters() }).done(function (res) {
            renderAuditList(res.data || [], res.total || 0, res.page || 1, res.per_page || auditPerPage);
        }).fail(apiError);
    }

    function fmtAuditValue(v) {
        if (v === null || v === undefined || v === '') return '<span class="text-muted">-</span>';
        if (Array.isArray(v)) return v.length ? v.map(esc).join(', ') : '<span class="text-muted">-</span>';
        if (typeof v === 'object') {
            return Object.keys(v).map(k => `<div><span class="text-muted">${esc(k)}:</span> ${fmtAuditValue(v[k])}</div>`).join('');
        }
        return esc(String(v));
    }

    function renderAuditList(rows, total, page, perPage) {
        const $list = $('#acc-audit-list').empty();
        if (!rows.length) {
            $list.append('<div class="p-4 text-muted small text-center">មិនមានប្រវត្តិសកម្មភាពត្រូវនឹងតម្រង</div>');
        }
        rows.forEach((r, idx) => {
            const detailId = 'acc-audit-detail-' + idx + '-' + (r.id || '').replace(/[^a-zA-Z0-9]/g, '');
            const actorName = r.actor ? esc(r.actor.full_name) : '<span class="text-muted">-</span>';
            const d = new Date(r.occurred_at);
            const when = isNaN(d) ? (r.occurred_at || '-') : d.toLocaleString();
            $list.append(`
                <div class="acc-list-item" style="cursor:default;flex-direction:column;align-items:stretch;">
                    <div class="d-flex justify-content-between align-items-start w-100" style="cursor:pointer;" data-toggle-detail="${detailId}">
                        <div>
                            <div class="name">${esc(r.action_label)} <span class="acc-badge-protected">${esc(r.category_label)}</span></div>
                            <div class="meta">${when} · ${actorName} → <strong>${esc(r.target_label)}</strong></div>
                        </div>
                        <i class="fa fa-chevron-down text-muted"></i>
                    </div>
                    <div id="${detailId}" class="small mt-2 d-none" style="border-top:1px solid #eee;padding-top:8px;">
                        <div class="row g-2">
                            <div class="col-md-6"><div class="text-muted fw-semibold mb-1">មុន (Before)</div>${fmtAuditValue(r.before)}</div>
                            <div class="col-md-6"><div class="text-muted fw-semibold mb-1">ក្រោយ (After)</div>${fmtAuditValue(r.after)}</div>
                        </div>
                        <div class="text-muted mt-2" style="font-size:11px;">ប្រភព: ${esc(r.source)}</div>
                    </div>
                </div>`);
        });

        const start = total === 0 ? 0 : (page - 1) * perPage + 1;
        const end = Math.min(page * perPage, total);
        $('#acc-audit-summary').text(total ? `${start}–${end} នៃ ${total}` : '');
        $('#acc-audit-prev').prop('disabled', page <= 1);
        $('#acc-audit-next').prop('disabled', end >= total);
    }

    $(document).on('click', '[data-toggle-detail]', function () {
        $('#' + $(this).data('toggle-detail')).toggleClass('d-none');
        $(this).find('.fa-chevron-down, .fa-chevron-up').toggleClass('fa-chevron-down fa-chevron-up');
    });

    $('#acc-audit-filter-category, #acc-audit-filter-date-from, #acc-audit-filter-date-to').on('change', function () {
        auditPage = 1;
        loadAuditHistory();
    });
    let auditQTimer = null;
    $('#acc-audit-filter-q').on('input', function () {
        clearTimeout(auditQTimer);
        auditQTimer = setTimeout(function () { auditPage = 1; loadAuditHistory(); }, 350);
    });
    $('#acc-audit-prev').on('click', function () { if (auditPage > 1) { auditPage--; loadAuditHistory(); } });
    $('#acc-audit-next').on('click', function () { auditPage++; loadAuditHistory(); });

    let auditActorSearchTimer = null;
    $('#acc-audit-filter-actor').on('input', function () {
        const q = $(this).val();
        clearTimeout(auditActorSearchTimer);
        if (!q) {
            auditSelectedActorId = null;
            $('#acc-audit-actor-results').empty();
            auditPage = 1;
            loadAuditHistory();
            return;
        }
        auditActorSearchTimer = setTimeout(function () {
            searchUsers(q, '#acc-audit-actor-results', function (id) {
                auditSelectedActorId = id;
                ajax({ url: ROUTES.usersSearch, data: { q: q } }).done(function (res) {
                    const u = (res.data || []).find(x => x.id === id);
                    $('#acc-audit-filter-actor').val(u ? u.full_name : '');
                });
                $('#acc-audit-actor-results').empty();
                auditPage = 1;
                loadAuditHistory();
            });
        }, 300);
    });

    // ---------------- INIT ----------------
    loadCatalog().done(function () { loadRoles(); });
    loadOrgTree();
    loadApprovalOptions().done(function () { loadApprovalDefinitions(); });
    loadDelegationOptions().done(function () { loadDelegations(); });
    loadAuditHistoryOptions().done(function () { loadAuditHistory(); });
})(jQuery);
</script>
@endpush

@extends('backend.layouts.app')
@section('title', localize('mobile_devices', 'ឧបករណ៍ទូរសព្ទ'))

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="fs-17 fw-semi-bold mb-0">
                    {{ localize('mobile_device_management', 'ការគ្រប់គ្រងឧបករណ៍ទូរសព្ទ') }}
                </h6>
                <a href="{{ route('role.user.list') }}" class="btn btn-outline-secondary btn-sm">
                    {{ localize('back_to_user_list', 'ត្រលប់ទៅបញ្ជីអ្នកប្រើ') }}
                </a>
            </div>
        </div>

        <div class="card-body border-bottom">
            <h6 class="mb-3">{{ localize('manual_device_registration', 'បញ្ចូលឧបករណ៍ដោយដៃ') }}</h6>
            <form method="POST" action="{{ route('mobile-devices.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label fw-semibold">{{ localize('user_email', 'អ៊ីមែលអ្នកប្រើ') }}</label>
                    <input type="email" name="user_email" class="form-control" value="{{ old('user_email') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">{{ localize('device_id', 'លេខឧបករណ៍') }}</label>
                    <input type="text" name="device_id" class="form-control" value="{{ old('device_id') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">{{ localize('device_name', 'ឈ្មោះឧបករណ៍') }}</label>
                    <input type="text" name="device_name" class="form-control" value="{{ old('device_name') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">{{ localize('platform', 'វេទិកា') }}</label>
                    <select name="platform" class="form-select">
                        <option value="">--</option>
                        <option value="android" @selected(old('platform') === 'android')>android</option>
                        <option value="ios" @selected(old('platform') === 'ios')>ios</option>
                        <option value="web" @selected(old('platform') === 'web')>web</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">{{ localize('status', 'ស្ថានភាព') }}</label>
                    <select name="status" class="form-select" required>
                        <option value="active" @selected(old('status', 'active') === 'active')>active</option>
                        <option value="pending" @selected(old('status') === 'pending')>pending</option>
                        <option value="blocked" @selected(old('status') === 'blocked')>blocked</option>
                        <option value="rejected" @selected(old('status') === 'rejected')>rejected</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">IMEI</label>
                    <input type="text" name="imei" class="form-control" value="{{ old('imei') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Fingerprint</label>
                    <input type="text" name="fingerprint" class="form-control" value="{{ old('fingerprint') }}">
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">{{ localize('save', 'រក្សាទុក') }}</button>
                </div>
            </form>
        </div>

        {{-- Status Tabs --}}
        <div class="card-body border-bottom pb-0 pt-2">
            <ul class="nav nav-tabs border-0">
                @php
                    $tabs = [
                        'pending'  => ['label' => localize('pending_approval','រង់ចាំអនុម័ត'), 'badge' => 'warning'],
                        'active'   => ['label' => localize('active','សកម្ម'),                  'badge' => 'success'],
                        'blocked'  => ['label' => localize('blocked','បិទ'),                   'badge' => 'danger'],
                        'rejected' => ['label' => localize('rejected','ច្រានចោល'),             'badge' => 'secondary'],
                    ];
                @endphp
                @foreach ($tabs as $key => $meta)
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                           href="{{ route('mobile-devices.index', array_filter(['tab' => $key, 'search' => $search])) }}">
                            {{ $meta['label'] }}
                            @if(($counts[$key] ?? 0) > 0)
                                <span class="badge bg-{{ $meta['badge'] }} ms-1">{{ $counts[$key] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Search bar --}}
        <div class="card-body border-bottom py-2">
            <form method="GET" action="{{ route('mobile-devices.index') }}" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control"
                        placeholder="{{ localize('search_email_name','ស្វែងរក អ៊ីមែល / ឈ្មោះ') }}"
                        value="{{ $search }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>{{ localize('filter','ត្រង') }}
                    </button>
                    @if($search)
                        <a href="{{ route('mobile-devices.index', ['tab' => $tab]) }}"
                           class="btn btn-secondary ms-1">{{ localize('reset','សង្គ្រោះ') }}</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            @include('backend.layouts.common.validation')
            @include('backend.layouts.common.message')

            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:45px">#</th>
                            <th>{{ localize('employee','មន្រ្តី') }}</th>
                            <th>{{ localize('device_info','ព័ត៌មានឧបករណ៍') }}</th>
                            <th>{{ localize('imei','IMEI / Serial') }}</th>
                            <th>{{ localize('platform','វេទិកា') }}</th>
                            <th>{{ localize('register_ip','IP ចុះឈ្មោះ') }}</th>
                            <th>{{ localize('registered_at','ថ្ងៃចុះឈ្មោះ') }}</th>
                            @if($tab === 'active')
                                <th>{{ localize('last_login','ចូលប្រើចុងក្រោយ') }}</th>
                                <th>{{ localize('approved_by','អនុម័តដោយ') }}</th>
                            @elseif($tab === 'blocked')
                                <th>{{ localize('blocked_by','បិទដោយ') }}</th>
                            @elseif($tab === 'rejected')
                                <th>{{ localize('rejected_reason','មូលហេតុ') }}</th>
                            @endif
                            <th class="text-center" style="width:150px">{{ localize('action','សកម្មភាព') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($devices as $device)
                            <tr>
                                <td class="text-center">{{ $devices->firstItem() + $loop->index }}</td>
                                <td>
                                    @php $emp = optional($device->user)->employee; @endphp
                                    <div class="fw-semibold">{{ optional($device->user)->full_name ?? optional($device->user)->email ?? '—' }}</div>
                                    @if($emp)
                                        <small class="text-muted">{{ $emp->employee_id ?? '' }}</small>
                                    @endif
                                    <div class="text-muted small">{{ optional($device->user)->email }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold small">{{ $device->device_name ?? '—' }}</div>
                                    <code class="small text-muted">{{ Str::limit($device->device_id, 28) }}</code>
                                </td>
                                <td>
                                    @if($device->imei)
                                        <code class="small">{{ $device->imei }}</code>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($device->platform)
                                        <span class="badge bg-info text-dark">{{ $device->platform }}</span>
                                    @else —
                                    @endif
                                </td>
                                <td><small>{{ $device->register_ip ?? '—' }}</small></td>
                                <td>{{ $device->created_at->format('Y-m-d H:i') }}</td>

                                @if($tab === 'active')
                                    <td>{{ $device->last_login_at ? $device->last_login_at->format('Y-m-d H:i') : '—' }}</td>
                                    <td><small>{{ optional($device->approver)->full_name ?? '—' }}</small></td>
                                @elseif($tab === 'blocked')
                                    <td>
                                        <small>{{ optional($device->blocker)->full_name ?? '—' }}</small><br>
                                        <small class="text-muted">{{ $device->blocked_at?->format('Y-m-d') }}</small>
                                    </td>
                                @elseif($tab === 'rejected')
                                    <td><small class="text-danger">{{ $device->rejection_reason ?? '—' }}</small></td>
                                @endif

                                <td>
                                    <div class="d-flex gap-1 justify-content-center flex-wrap">
                                        {{-- Approve/Activate --}}
                                        @if(!$device->isActive() && !$device->isBlocked())
                                            <form method="POST" action="{{ route('mobile-devices.approve', $device->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success"
                                                    title="{{ localize('approve','អនុម័ត') }}">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Reject with reason modal trigger (pending only) --}}
                                        @if($device->isPending())
                                            {{-- Reject with reason modal trigger --}}
                                            <button type="button" class="btn btn-sm btn-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#rejectModal{{ $device->id }}"
                                                title="{{ localize('reject','ច្រានចោល') }}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        @endif

                                        {{-- Block/Unblock --}}
                                        @if($device->isActive())
                                            <form method="POST" action="{{ route('mobile-devices.block', $device->id) }}"
                                                onsubmit="return confirm('{{ localize('confirm_block_device','បិទឧបករណ៍?') }}')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-warning"
                                                    title="{{ localize('block','បិទ') }}">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @if($device->isBlocked())
                                            <form method="POST" action="{{ route('mobile-devices.unblock', $device->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success"
                                                    title="{{ localize('unblock','បើក') }}">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Delete --}}
                                        <form method="POST" action="{{ route('mobile-devices.destroy', $device->id) }}"
                                            onsubmit="return confirm('{{ localize('confirm_delete_device','លុបឧបករណ៍?') }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                title="{{ localize('delete','លុប') }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            {{-- Reject Modal --}}
                            @if($device->isPending())
                            <div class="modal fade" id="rejectModal{{ $device->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="POST" action="{{ route('mobile-devices.reject', $device->id) }}">
                                        @csrf
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">{{ localize('reject_device','ច្រានចោលឧបករណ៍') }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="mb-2">
                                                    <strong>{{ optional($device->user)->full_name }}</strong>
                                                    — <code>{{ $device->device_name ?? $device->device_id }}</code>
                                                </p>
                                                <label class="form-label">{{ localize('rejection_reason','មូលហេតុ (ស្រេចចិត្ត)') }}</label>
                                                <input type="text" name="rejection_reason" class="form-control"
                                                    placeholder="{{ localize('enter_reason','បញ្ចូលមូលហេតុ...') }}" maxlength="255">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    {{ localize('cancel','បោះបង់') }}
                                                </button>
                                                <button type="submit" class="btn btn-danger">
                                                    {{ localize('confirm_reject','ច្រានចោល') }}
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            @endif

                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    {{ localize('no_devices_found','រកមិនឃើញឧបករណ៍ណាមួយ') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($devices->hasPages())
                <div class="card-footer">{{ $devices->links() }}</div>
            @endif
        </div>
    </div>
@endsection

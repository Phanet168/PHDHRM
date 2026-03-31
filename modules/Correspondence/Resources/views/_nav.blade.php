<div class="card mb-3">
    <div class="card-body py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('correspondence.index') ? 'active' : '' }}"
                    href="{{ route('correspondence.index') }}">
                    {{ localize('correspondence_dashboard', 'Letter dashboard') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('correspondence.incoming') ? 'active' : '' }}"
                    href="{{ route('correspondence.incoming') }}">
                    {{ localize('incoming_letters', 'Incoming letters') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('correspondence.outgoing') ? 'active' : '' }}"
                    href="{{ route('correspondence.outgoing') }}">
                    {{ localize('outgoing_letters', 'Outgoing letters') }}
                </a>
            </li>
        </ul>

        <div class="d-flex gap-2">
            <a href="{{ route('correspondence.create', 'incoming') }}" class="btn btn-sm btn-success">
                <i class="fa fa-plus-circle"></i> {{ localize('add_incoming_letter', 'Add incoming') }}
            </a>
            <a href="{{ route('correspondence.create', 'outgoing') }}" class="btn btn-sm btn-primary">
                <i class="fa fa-plus-circle"></i> {{ localize('add_outgoing_letter', 'Add outgoing') }}
            </a>
        </div>
    </div>
</div>

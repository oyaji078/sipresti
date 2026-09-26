@php
    $user = auth()->user();
@endphp
<header class="sip-topbar">
    <button class="btn btn-light border d-lg-none" type="button" id="sipSidebarToggle" aria-label="Buka menu">
        <i class="bi bi-list"></i>
    </button>

    <div class="d-none d-md-block">
        <div class="fw-semibold">{{ config('sipresti.long_name') }}</div>
        <div class="text-muted" style="font-size:.78rem">{{ config('sipresti.instansi') }}</div>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2">
        <span class="badge {{ $user->role->badgeClass() }} d-none d-sm-inline">{{ $user->role->label() }}</span>

        <div class="dropdown">
            <button class="btn btn-light border d-flex align-items-center gap-2 dropdown-toggle" data-bs-toggle="dropdown" type="button">
                <span class="sip-avatar">{{ $user->initials() }}</span>
                <span class="d-none d-sm-inline text-start" style="line-height:1.1">
                    <span class="d-block fw-semibold" style="font-size:.82rem">{{ $user->name }}</span>
                    <span class="d-block text-muted" style="font-size:.72rem">{{ '@'.$user->username }}</span>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><a class="dropdown-item" href="{{ route('profil.edit') }}"><i class="bi bi-person-circle me-2"></i>Profil Saya</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

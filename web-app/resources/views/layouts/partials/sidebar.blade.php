@php
    $user = auth()->user();
@endphp
<aside class="sip-sidebar" id="sipSidebar">
    <div class="sip-sidebar__brand">
        <div class="sip-sidebar__logo"><i class="bi bi-activity"></i></div>
        <div>
            <div class="sip-sidebar__title">{{ config('sipresti.short_name') }}</div>
            <div class="sip-sidebar__subtitle">{{ config('sipresti.instansi') }}</div>
        </div>
    </div>

    <nav class="sip-sidebar__nav">
        <div class="sip-sidebar__section">Menu Utama</div>

        <a href="{{ route('dashboard') }}" class="sip-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i><span>Dashboard</span>
        </a>

        <a href="{{ route('balita.index') }}" class="sip-nav-link {{ request()->routeIs('balita.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i><span>Data Balita</span>
        </a>

        <a href="{{ route('pemeriksaan.index') }}" class="sip-nav-link {{ request()->routeIs('pemeriksaan.*') ? 'active' : '' }}">
            <i class="bi bi-clipboard2-pulse"></i><span>{{ $user->isAdmin() ? 'Data Pemeriksaan' : 'Pemeriksaan' }}</span>
        </a>

        <a href="{{ route('perkembangan.index') }}" class="sip-nav-link {{ request()->routeIs('perkembangan.*') ? 'active' : '' }}">
            <i class="bi bi-graph-up"></i><span>Perkembangan Balita</span>
        </a>

        <a href="{{ route('prediksi.index') }}" class="sip-nav-link {{ request()->routeIs('prediksi.*') ? 'active' : '' }}">
            <i class="bi bi-cpu"></i><span>{{ $user->isAdmin() ? 'Hasil Prediksi' : 'Prediksi Stunting' }}</span>
        </a>

        @if ($user->isPetugasGizi())
            <a href="{{ route('import.index') }}" class="sip-nav-link {{ request()->routeIs('import.*') ? 'active' : '' }}">
                <i class="bi bi-upload"></i><span>Import E-PPBGM</span>
            </a>

            <a href="{{ route('monitoring.index') }}" class="sip-nav-link {{ request()->routeIs('monitoring.*') ? 'active' : '' }}">
                <i class="bi bi-graph-up-arrow"></i><span>Monitoring</span>
            </a>

            <a href="{{ route('laporan.index') }}" class="sip-nav-link {{ request()->routeIs('laporan.*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-bar-graph"></i><span>Laporan</span>
            </a>
        @endif

        @if ($user->isAdmin())
            <div class="sip-sidebar__section">Administrasi</div>

            <a href="{{ route('admin.models.index') }}" class="sip-nav-link {{ request()->routeIs('admin.models.*') ? 'active' : '' }}">
                <i class="bi bi-diagram-3"></i><span>Model XGBoost</span>
            </a>

            <a href="{{ route('admin.users.index') }}" class="sip-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <i class="bi bi-person-gear"></i><span>Pengguna</span>
            </a>

            <a href="{{ route('admin.audit-logs.index') }}" class="sip-nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i><span>Audit Log</span>
            </a>

            <a href="{{ route('admin.settings.edit') }}" class="sip-nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                <i class="bi bi-sliders"></i><span>Pengaturan</span>
            </a>
        @endif

        <div class="sip-sidebar__section">Akun</div>

        <a href="{{ route('profil.edit') }}" class="sip-nav-link {{ request()->routeIs('profil.*') ? 'active' : '' }}">
            <i class="bi bi-person-circle"></i><span>Profil</span>
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sip-nav-link border-0 bg-transparent w-100 text-start">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </button>
        </form>
    </nav>
</aside>

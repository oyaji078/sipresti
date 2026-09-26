<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('sipresti.short_name') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🩺</text></svg>">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sipresti.css') }}">
    @stack('styles')
</head>
<body>
<div class="sip-layout">
    @include('layouts.partials.sidebar')

    <div class="sip-backdrop" id="sipBackdrop"></div>

    <div class="sip-main">
        @include('layouts.partials.topbar')

        <main class="sip-content">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    @hasSection('breadcrumb')
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">@yield('breadcrumb')</ol>
                        </nav>
                    @endif
                    <h1 class="sip-page-title">@yield('page-title', View::getSection('title', 'Dashboard'))</h1>
                    @hasSection('page-subtitle')
                        <p class="sip-page-subtitle">@yield('page-subtitle')</p>
                    @endif
                </div>
                <div class="sip-no-print">@yield('page-actions')</div>
            </div>

            @include('layouts.partials.alerts')

            @yield('content')
        </main>

        <footer class="sip-footer d-flex flex-wrap justify-content-between gap-2">
            <span>{{ config('sipresti.short_name') }} &mdash; {{ config('sipresti.long_name') }}</span>
            <span>{{ config('sipresti.instansi') }} &copy; {{ date('Y') }}</span>
        </footer>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="sipToastContainer"></div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/sipresti.js') }}"></script>

@if (session('toast'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            SipToast.show(@json(session('toast.message')), @json(session('toast.type', 'success')));
        });
    </script>
@endif

@stack('scripts')
</body>
</html>

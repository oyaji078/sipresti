@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show sip-no-print" role="alert">
        <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show sip-no-print" role="alert">
        <i class="bi bi-exclamation-octagon me-1"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show sip-no-print" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>{{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if (session('status'))
    <div class="alert alert-info alert-dismissible fade show sip-no-print" role="alert">
        <i class="bi bi-info-circle me-1"></i>{{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if ($errors->any() && $errors->count() > 1)
    <div class="alert alert-danger alert-dismissible fade show sip-no-print" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon me-1"></i>Periksa kembali isian berikut:</div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@extends('layouts.app')

@section('title', 'Tambah Pemeriksaan')
@section('page-title', 'Tambah Pemeriksaan Antropometri')
@section('page-subtitle', 'Usia dihitung otomatis dari tanggal lahir dan tanggal pemeriksaan.')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pemeriksaan.index') }}">Pemeriksaan</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-8">
        <form method="POST" action="{{ route('pemeriksaan.store') }}">
            @csrf
            <div class="card">
                <div class="card-header"><i class="bi bi-clipboard2-plus me-1"></i>Data Pemeriksaan</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="toddler_id" class="form-label fw-semibold sip-required">Balita</label>
                            <select id="toddler_id" name="toddler_id" class="form-select @error('toddler_id') is-invalid @enderror" required>
                                <option value="">— Pilih balita —</option>
                                @foreach ($toddlers as $t)
                                    <option value="{{ $t->id }}"
                                            data-birth="{{ $t->birth_date->toDateString() }}"
                                            @selected(old('toddler_id', $selected?->id) == $t->id)>
                                        {{ $t->code }} — {{ $t->name }} ({{ $t->gender }})
                                    </option>
                                @endforeach
                            </select>
                            @error('toddler_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if ($toddlers->isEmpty())
                                <div class="form-text text-danger">
                                    Belum ada data balita. <a href="{{ route('balita.create') }}">Tambahkan balita</a> terlebih dahulu.
                                </div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label for="measurement_date" class="form-label fw-semibold sip-required">Tanggal Pemeriksaan</label>
                            <input type="date" id="measurement_date" name="measurement_date" max="{{ now()->toDateString() }}"
                                   class="form-control @error('measurement_date') is-invalid @enderror"
                                   value="{{ old('measurement_date', now()->toDateString()) }}" required>
                            @error('measurement_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="age_display" class="form-label fw-semibold">Usia (bulan)</label>
                            <input type="text" id="age_display" class="form-control bg-light" readonly value="—">
                            <div class="form-text">Dihitung otomatis. Disimpan sebagai riwayat dan tidak berubah kemudian.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="weight_kg" class="form-label fw-semibold sip-required">Berat Badan (kg)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" id="weight_kg" name="weight_kg"
                                       class="form-control @error('weight_kg') is-invalid @enderror"
                                       value="{{ old('weight_kg') }}" required>
                                <span class="input-group-text">kg</span>
                                @error('weight_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="height_cm" class="form-label fw-semibold sip-required">Tinggi / Panjang Badan (cm)</label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0" id="height_cm" name="height_cm"
                                       class="form-control @error('height_cm') is-invalid @enderror"
                                       value="{{ old('height_cm') }}" required>
                                <span class="input-group-text">cm</span>
                                @error('height_cm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="muac_cm" class="form-label fw-semibold">LiLA / Lingkar Lengan Atas (cm)</label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0" id="muac_cm" name="muac_cm"
                                       class="form-control @error('muac_cm') is-invalid @enderror"
                                       value="{{ old('muac_cm') }}">
                                <span class="input-group-text">cm</span>
                                @error('muac_cm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text">Opsional. Kosongkan bila tidak diukur.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary" @disabled($toddlers->isEmpty())>
                        <i class="bi bi-save me-1"></i>Simpan
                    </button>
                    <a href="{{ route('pemeriksaan.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var select = document.getElementById('toddler_id');
    var dateInput = document.getElementById('measurement_date');
    var display = document.getElementById('age_display');

    function monthsBetween(from, to) {
        var months = (to.getFullYear() - from.getFullYear()) * 12 + (to.getMonth() - from.getMonth());
        if (to.getDate() < from.getDate()) months--;
        return months;
    }

    function update() {
        var option = select.options[select.selectedIndex];
        var birth = option ? option.getAttribute('data-birth') : null;

        if (!birth || !dateInput.value) {
            display.value = '—';
            return;
        }

        var from = new Date(birth);
        var to = new Date(dateInput.value);

        if (to < from) {
            display.value = 'Tanggal pemeriksaan sebelum tanggal lahir';
            return;
        }

        var months = monthsBetween(from, to);
        var years = Math.floor(months / 12);
        var rest = months % 12;
        display.value = months + ' bulan' + (years > 0 ? ' (' + years + ' th ' + rest + ' bln)' : '');
    }

    select.addEventListener('change', update);
    dateInput.addEventListener('change', update);
    update();
})();
</script>
@endpush

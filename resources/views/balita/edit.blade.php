@extends('layouts.app')

@section('title', 'Ubah Balita')
@section('page-title', 'Ubah Data Balita')
@section('page-subtitle', $toddler->code . ' — ' . $toddler->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('balita.index') }}">Data Balita</a></li>
    <li class="breadcrumb-item"><a href="{{ route('balita.show', $toddler) }}">{{ $toddler->code }}</a></li>
    <li class="breadcrumb-item active">Ubah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-9">
        <form method="POST" action="{{ route('balita.update', $toddler) }}">
            @method('PUT')
            <div class="card">
                <div class="card-header"><i class="bi bi-pencil-square me-1"></i>Identitas Balita</div>
                <div class="card-body">
                    @include('balita._form')
                </div>
                <div class="card-body border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
                    <a href="{{ route('balita.show', $toddler) }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </form>

        <div class="card mt-3 border-danger-subtle">
            <div class="card-header text-danger"><i class="bi bi-trash me-1"></i>Hapus Data</div>
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="small text-muted">
                    Data balita hanya dapat dihapus bila belum memiliki data pemeriksaan.
                </div>
                <form method="POST" action="{{ route('balita.destroy', $toddler) }}"
                      data-confirm="Hapus data balita {{ $toddler->code }}? Tindakan ini tidak dapat dibatalkan.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>Hapus Balita
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

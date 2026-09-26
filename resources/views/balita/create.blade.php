@extends('layouts.app')

@section('title', 'Tambah Balita')
@section('page-title', 'Tambah Data Balita')
@section('page-subtitle', 'Kode balita dibuat otomatis oleh sistem.')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('balita.index') }}">Data Balita</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-9">
        <form method="POST" action="{{ route('balita.store') }}">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-plus me-1"></i>Identitas Balita</span>
                    <span class="badge text-bg-light border font-monospace">{{ $nextCode }}</span>
                </div>
                <div class="card-body">
                    @include('balita._form')
                </div>
                <div class="card-body border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
                    <a href="{{ route('balita.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

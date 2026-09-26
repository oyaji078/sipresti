@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label fw-semibold sip-required">Nama Balita</label>
        <input type="text" id="name" name="name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $toddler->name ?? '') }}" required autofocus>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="nik" class="form-label fw-semibold">NIK</label>
        <input type="text" id="nik" name="nik" inputmode="numeric" maxlength="16"
               class="form-control @error('nik') is-invalid @enderror"
               value="{{ old('nik', $toddler->nik ?? '') }}" placeholder="16 digit (opsional)">
        @error('nik')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="birth_date" class="form-label fw-semibold sip-required">Tanggal Lahir</label>
        <input type="date" id="birth_date" name="birth_date" max="{{ now()->toDateString() }}"
               class="form-control @error('birth_date') is-invalid @enderror"
               value="{{ old('birth_date', isset($toddler) ? $toddler->birth_date->toDateString() : '') }}" required>
        @error('birth_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold sip-required d-block">Jenis Kelamin</label>
        @php
    $gender = old('gender', $toddler->gender ?? '');
@endphp
        <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="gender" id="genderL" value="L" @checked($gender === 'L') required>
            <label class="btn btn-outline-primary" for="genderL"><i class="bi bi-gender-male me-1"></i>Laki-laki</label>

            <input type="radio" class="btn-check" name="gender" id="genderP" value="P" @checked($gender === 'P')>
            <label class="btn btn-outline-primary" for="genderP"><i class="bi bi-gender-female me-1"></i>Perempuan</label>
        </div>
        @error('gender')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="mother_name" class="form-label fw-semibold">Nama Ibu</label>
        <input type="text" id="mother_name" name="mother_name"
               class="form-control @error('mother_name') is-invalid @enderror"
               value="{{ old('mother_name', $toddler->mother_name ?? '') }}">
        @error('mother_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="address" class="form-label fw-semibold">Alamat</label>
        <input type="text" id="address" name="address"
               class="form-control @error('address') is-invalid @enderror"
               value="{{ old('address', $toddler->address ?? '') }}">
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="village" class="form-label fw-semibold">Desa</label>
        <input type="text" id="village" name="village" list="villageList"
               class="form-control @error('village') is-invalid @enderror"
               value="{{ old('village', $toddler->village ?? '') }}">
        <datalist id="villageList">
            @foreach ($villages as $village)<option value="{{ $village }}"></option>@endforeach
        </datalist>
        @error('village')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="posyandu" class="form-label fw-semibold">Posyandu</label>
        <input type="text" id="posyandu" name="posyandu" list="posyanduList"
               class="form-control @error('posyandu') is-invalid @enderror"
               value="{{ old('posyandu', $toddler->posyandu ?? '') }}">
        <datalist id="posyanduList">
            @foreach ($posyandus as $posyandu)<option value="{{ $posyandu }}"></option>@endforeach
        </datalist>
        @error('posyandu')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

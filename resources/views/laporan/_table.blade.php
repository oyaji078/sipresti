@if ($type === 'pemeriksaan')
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Tanggal</th><th>Kode</th><th>Nama</th><th>JK</th><th>Usia</th><th>BB</th><th>TB</th><th>Desa</th><th>Posyandu</th><th>Sumber</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $m)
                <tr>
                    <td class="small">{{ $m->measurement_date->format('d/m/Y') }}</td>
                    <td class="small font-monospace">{{ $m->toddler->code }}</td>
                    <td class="small fw-semibold">{{ $m->toddler->name }}</td>
                    <td class="small">{{ $m->toddler->gender }}</td>
                    <td class="small">{{ $m->age_months }} bln</td>
                    <td class="small">{{ $m->weight_kg }} kg</td>
                    <td class="small">{{ $m->height_cm }} cm</td>
                    <td class="small">{{ $m->toddler->village ?? '-' }}</td>
                    <td class="small">{{ $m->toddler->posyandu ?? '-' }}</td>
                    <td class="small">{{ $m->source->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="10"><div class="sip-empty"><i class="bi bi-inbox"></i>Tidak ada data untuk filter ini.</div></td></tr>
            @endforelse
        </tbody>
    </table>

@elseif ($type === 'prediksi')
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Tanggal</th><th>Kode</th><th>Nama</th><th>JK</th><th>Usia</th><th>BB</th><th>TB</th><th>Hasil</th><th class="text-end">Prob.</th><th>Model</th><th>Petugas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $p)
                <tr>
                    <td class="small">{{ $p->predicted_at->format('d/m/Y H:i') }}</td>
                    <td class="small font-monospace">{{ $p->toddler->code }}</td>
                    <td class="small fw-semibold">{{ $p->toddler->name }}</td>
                    <td class="small">{{ $p->toddler->gender }}</td>
                    <td class="small">{{ $p->measurement?->age_months ?? '-' }} bln</td>
                    <td class="small">{{ $p->measurement?->weight_kg ?? '-' }}</td>
                    <td class="small">{{ $p->measurement?->height_cm ?? '-' }}</td>
                    <td class="small">
                        <span class="badge text-bg-{{ $p->statusClass() }}">{{ $p->statusLabel() }}</span>
                    </td>
                    <td class="small text-end fw-semibold">{{ $p->probabilityPercent() }}</td>
                    <td class="small font-monospace">{{ $p->modelVersion?->version ?? '-' }}</td>
                    <td class="small">{{ $p->predictor?->name ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="11"><div class="sip-empty"><i class="bi bi-inbox"></i>Tidak ada data untuk filter ini.</div></td></tr>
            @endforelse
        </tbody>
    </table>

@else
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Kode</th><th>Nama</th><th>NIK</th><th>JK</th><th>Tgl Lahir</th><th>Umur</th><th>Nama Ibu</th><th>Desa</th><th>Posyandu</th><th>Periksa Terakhir</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $t)
                <tr>
                    <td class="small font-monospace">{{ $t->code }}</td>
                    <td class="small fw-semibold">{{ $t->name }}</td>
                    <td class="small font-monospace">{{ $t->maskedNik() }}</td>
                    <td class="small">{{ $t->gender }}</td>
                    <td class="small">{{ $t->birth_date->format('d/m/Y') }}</td>
                    <td class="small">{{ $t->ageLabel() }}</td>
                    <td class="small">{{ $t->mother_name ?? '-' }}</td>
                    <td class="small">{{ $t->village ?? '-' }}</td>
                    <td class="small">{{ $t->posyandu ?? '-' }}</td>
                    <td class="small">{{ $t->latestMeasurement?->measurement_date?->format('d/m/Y') ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="10"><div class="sip-empty"><i class="bi bi-inbox"></i>Tidak ada data untuk filter ini.</div></td></tr>
            @endforelse
        </tbody>
    </table>
@endif

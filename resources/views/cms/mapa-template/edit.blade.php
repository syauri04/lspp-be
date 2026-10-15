@extends('layouts.app')

@section('title', 'Template MAPA')

@php
    $labelKolom = [
        'observasi_langsung' => 'Observasi Langsung',
        'kegiatan_terstruktur' => 'Kegiatan Terstruktur',
        'tanya_jawab' => 'Tanya Jawab',
        'verifikasi_portofolio' => 'Verifikasi Portofolio',
        'reviu_produk' => 'Reviu Produk',
        'verifikasi_pihak_ketiga' => 'Verifikasi Pihak Ketiga',
    ];
    $pola = \App\Services\Muk\MapaTemplateService::polaDefault($metode, '{unit}');
    $barisPenyusun = fn (string $bagian) => old("penandatangan.{$bagian}") !== null
        ? collect(old("penandatangan.{$bagian}"))->map(fn ($b) => (object) $b)
        : ($penandatangan[$bagian] ?? collect());
@endphp

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Sertifikat',
            'subtitle' => 'Skema Sertifikasi',
            'title' => 'Template FR.MAPA.01 & FR.MAPA.02',
            'action' => null,
        ])

        <div class="container-fluid">
            <div class="page-content-wrapper">

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-1">{{ $skema->title['id'] ?? '-' }}</h5>
                            <div class="text-muted small">{{ $skema->nomor_skema ?? '-' }} · {{ $skema->jenis_skema ?? '-' }}</div>
                        </div>
                        <ul class="nav nav-pills">
                            @foreach ($metodeLabel as $kode => $label)
                                <li class="nav-item">
                                    <a class="nav-link {{ $kode === $metode ? 'active' : '' }}"
                                        href="{{ route('skema-sertifikasi.mapa.edit', [$skema, $kode]) }}">{{ $label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <form action="{{ route('skema-sertifikasi.mapa.update', [$skema, $metode]) }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    {{-- §2 Rencana Asesmen --}}
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                <div>
                                    <h5 class="card-title mb-0">FR.MAPA.01 · 2. Mempersiapkan Rencana Asesmen</h5>
                                    <p class="text-muted small mb-0">Bukti, jenis bukti, serta metode & perangkat asesmen per unit kompetensi
                                        ({{ $metodeLabel[$metode] }}).</p>
                                </div>
                                @if ($pola)
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-isi-pola">
                                        <i class="mdi mdi-auto-fix me-1"></i> Isi baris kosong dengan pola {{ strtoupper($metode) }}
                                    </button>
                                @endif
                            </div>
                            <p class="small text-muted">
                                CL (Ceklis Observasi), DIT (Daftar Instruksi Terstruktur), DPL (Daftar Pertanyaan Lisan),
                                DPT (Daftar Pertanyaan Tertulis), VPK (Verifikasi Pihak Ketiga), CVP (Ceklis Verifikasi Portofolio),
                                CRP (Ceklis Reviu Produk), PW (Pertanyaan Wawancara)
                            </p>

                            @if ($skema->unitKompetensis->isEmpty())
                                <div class="alert alert-warning mb-0">Skema ini belum memiliki unit kompetensi.</div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle" id="tabel-rencana">
                                        <thead class="table-light">
                                            <tr>
                                                <th rowspan="2" style="min-width:200px;">Unit Kompetensi</th>
                                                <th rowspan="2" style="min-width:260px;">Bukti-Bukti</th>
                                                <th colspan="3" class="text-center">Jenis Bukti</th>
                                                <th colspan="6" class="text-center">Metode dan Perangkat Asesmen</th>
                                            </tr>
                                            <tr>
                                                <th class="text-center">L</th>
                                                <th class="text-center">TL</th>
                                                <th class="text-center">T</th>
                                                @foreach ($kolomMetode as $k)
                                                    <th class="small" style="min-width:95px;">{{ $labelKolom[$k] }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($skema->unitKompetensis as $i => $unit)
                                                @php $r = $rencana->get($unit->id); @endphp
                                                <tr data-judul="{{ $unit->judul_unit }}">
                                                    <td>
                                                        <div class="fw-semibold">{{ $i + 1 }}. {{ $unit->judul_unit }}</div>
                                                        <code class="small">{{ $unit->kode_unit }}</code>
                                                    </td>
                                                    <td>
                                                        <textarea class="form-control form-control-sm isian-bukti" rows="3" maxlength="2000"
                                                            name="rencana[{{ $unit->id }}][bukti]">{{ old("rencana.{$unit->id}.bukti", $r?->bukti) }}</textarea>
                                                    </td>
                                                    @foreach (['jenis_l', 'jenis_tl', 'jenis_t'] as $j)
                                                        <td class="text-center">
                                                            <input type="hidden" name="rencana[{{ $unit->id }}][{{ $j }}]" value="0">
                                                            <input type="checkbox" class="form-check-input isian-{{ $j }}" value="1"
                                                                name="rencana[{{ $unit->id }}][{{ $j }}]"
                                                                {{ old("rencana.{$unit->id}.{$j}", $r?->{$j}) ? 'checked' : '' }}>
                                                        </td>
                                                    @endforeach
                                                    @foreach ($kolomMetode as $k)
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm isian-{{ $k }}" maxlength="100"
                                                                name="rencana[{{ $unit->id }}][{{ $k }}]"
                                                                value="{{ old("rencana.{$unit->id}.{$k}", $r?->{$k}) }}">
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Konfirmasi dengan orang yang relevan --}}
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">FR.MAPA.01 · Konfirmasi dengan Orang yang Relevan</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Orang yang Relevan</th>
                                            <th style="min-width:200px;">Nama</th>
                                            <th style="min-width:240px;">Tanda Tangan</th>
                                            <th style="width:170px;">Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($penandatangan['konfirmasi'] ?? [] as $i => $row)
                                            <tr>
                                                <td>
                                                    {{ $row->peran }}
                                                    <input type="hidden" name="penandatangan[konfirmasi][{{ $i }}][id]" value="{{ $row->id }}">
                                                    <input type="hidden" name="penandatangan[konfirmasi][{{ $i }}][peran]" value="{{ $row->peran }}">
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" maxlength="255"
                                                        name="penandatangan[konfirmasi][{{ $i }}][nama]"
                                                        value="{{ old("penandatangan.konfirmasi.{$i}.nama", $row->nama) }}">
                                                </td>
                                                <td>@include('cms.mapa-template.partials.ttd', ['nama' => "penandatangan[konfirmasi][{$i}]", 'row' => $row])</td>
                                                <td>
                                                    <input type="date" class="form-control form-control-sm"
                                                        name="penandatangan[konfirmasi][{{ $i }}][tanggal]"
                                                        value="{{ old("penandatangan.konfirmasi.{$i}.tanggal", $row->tanggal?->format('Y-m-d')) }}">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Penyusun & Validator MAPA.01 / MAPA.02 --}}
                    @foreach (['penyusun_mapa01' => 'FR.MAPA.01', 'penyusun_mapa02' => 'FR.MAPA.02'] as $bagian => $kodeDok)
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">{{ $kodeDok }} · Penyusun dan Validator</h5>
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-tambah-penyusun" data-bagian="{{ $bagian }}">
                                        <i class="mdi mdi-plus-circle me-1"></i> Tambah
                                    </button>
                                </div>
                                <input type="hidden" name="ada_{{ $bagian }}" value="1">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:140px;">Status</th>
                                                <th style="min-width:200px;">Nama</th>
                                                <th style="min-width:170px;">No. Reg (MET)</th>
                                                <th style="min-width:240px;">Tanda Tangan</th>
                                                <th style="width:170px;">Tanggal</th>
                                                <th style="width:50px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="baris-{{ $bagian }}">
                                            @foreach ($barisPenyusun($bagian) as $i => $row)
                                                @include('cms.mapa-template.partials.baris-penyusun', ['bagian' => $bagian, 'i' => $i, 'row' => $row])
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="d-flex justify-content-between mb-4">
                        <a href="{{ route('skema-sertifikasi.edit', $skema) }}" class="btn btn-light">&larr; Kembali ke Skema</a>
                        <button type="submit" class="btn btn-primary">Simpan Template {{ strtoupper($metode) }}</button>
                    </div>
                </form>

                {{-- Template baris baru (diklon JS) --}}
                <template id="tpl-baris-penyusun">
                    @include('cms.mapa-template.partials.baris-penyusun', ['bagian' => '__BAGIAN__', 'i' => '__I__', 'row' => null])
                </template>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            // Tambah / hapus baris penyusun & validator. Indeks unik agar berkas ttd terpasang ke baris yang benar.
            document.querySelectorAll('.btn-tambah-penyusun').forEach((btn) => btn.addEventListener('click', () => {
                const bagian = btn.dataset.bagian;
                const html = document.getElementById('tpl-baris-penyusun').innerHTML
                    .replaceAll('__BAGIAN__', bagian)
                    .replaceAll('__I__', 'b' + Date.now());
                document.getElementById('baris-' + bagian).insertAdjacentHTML('beforeend', html);
            }));
            document.addEventListener('click', (e) => {
                const hapus = e.target.closest('.btn-hapus-baris');
                if (hapus) hapus.closest('tr').remove();
            });

            // Isi baris kosong dengan pola metode.
            const pola = @json($pola);
            const btnPola = document.getElementById('btn-isi-pola');
            if (btnPola && pola) {
                btnPola.addEventListener('click', () => {
                    document.querySelectorAll('#tabel-rencana tbody tr').forEach((tr) => {
                        const bukti = tr.querySelector('.isian-bukti');
                        if (bukti.value.trim() !== '') return; // jangan timpa isian yang sudah ada
                        bukti.value = pola.bukti.replace('{unit}', tr.dataset.judul);
                        ['jenis_l', 'jenis_tl', 'jenis_t'].forEach((j) => tr.querySelector('.isian-' + j).checked = !!pola[j]);
                        @json($kolomMetode).forEach((k) => tr.querySelector('.isian-' + k).value = pola[k] ?? '');
                    });
                });
            }
        })();
    </script>
@endpush

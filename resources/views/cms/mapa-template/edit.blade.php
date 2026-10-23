@extends('layouts.app')

@section('title', 'Template Perangkat Asesmen')

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
            'title' => 'Template Perangkat Asesmen (MAPA & Instrumen)',
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

                    @if ($metode !== 'umum')
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
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    @endif

                    @if ($metode === 'umum')
                        @php
                            $ia06 = $template->instrumenUntuk(\App\Models\MapaTemplate::IA_06);
                            $soalIa06 = old('instrumen.ia06.soal', $ia06['soal'] ?? []);
                        @endphp

                        {{-- FR.IA.06 Pertanyaan Tertulis Esai (semua metode) --}}
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h5 class="card-title mb-0">FR.IA.06 · Soal Esai &amp; Kunci Jawaban</h5>
                                        <p class="text-muted small mb-0">
                                            Pertanyaan tampil di FR.IA.06A (dijawab asesi). Kunci jawaban hanya tampil di FR.IA.06B untuk asesor
                                            dan <strong>tidak pernah</strong> dikirim ke asesi.
                                        </p>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-tambah-soal">
                                        <i class="mdi mdi-plus-circle me-1"></i> Tambah Soal
                                    </button>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Waktu</label>
                                        <input type="text" class="form-control form-control-sm" maxlength="100" placeholder="mis. 30 Menit"
                                            name="instrumen[ia06][waktu]" value="{{ old('instrumen.ia06.waktu', $ia06['waktu'] ?? '') }}">
                                    </div>
                                </div>
                                <input type="hidden" name="instrumen[ia06][soal]" value="">
                                <div id="daftar-soal">
                                    @foreach ($soalIa06 as $i => $soal)
                                        @include('cms.mapa-template.partials.baris-soal', ['i' => $i, 'soal' => $soal])
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($metode === 'kt')
                        @php
                            $ia04a = $template->instrumenUntuk(\App\Models\MapaTemplate::IA_04A);
                            $aspekIa04b = old('instrumen.ia04b.aspek', $template->instrumenUntuk(\App\Models\MapaTemplate::IA_04B)['aspek'] ?? []);
                        @endphp
                        {{-- FR.IA.04A Daftar Instruksi Terstruktur --}}
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0">FR.IA.04A · Penjelasan Proyek Singkat / Kegiatan Terstruktur</h5>
                                <p class="text-muted small">Ditampilkan ke asesor & asesi pada FR.IA.04A (tidak dapat diubah asesor).</p>
                                <label class="form-label small fw-semibold">Hal yang harus disiapkan atau dilakukan atau dihasilkan</label>
                                <textarea class="form-control mb-3" rows="12" maxlength="15000"
                                    name="instrumen[ia04a][hal_disiapkan]">{{ old('instrumen.ia04a.hal_disiapkan', $ia04a['hal_disiapkan'] ?? '') }}</textarea>
                                <label class="form-label small fw-semibold">Hal yang perlu didemonstrasikan/ dipresentasikan</label>
                                <textarea class="form-control" rows="14" maxlength="15000"
                                    name="instrumen[ia04a][hal_didemonstrasikan]">{{ old('instrumen.ia04a.hal_didemonstrasikan', $ia04a['hal_didemonstrasikan'] ?? '') }}</textarea>
                                <label class="form-label small fw-semibold mt-3">Waktu</label>
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text">1.</span>
                                    <textarea class="form-control" rows="2" maxlength="1000" placeholder="Anda diberi waktu untuk menyelesaikan Tugas tersebut selama 60 (enam puluh) menit, dan mempresentasikan"
                                        name="instrumen[ia04a][waktu_pengerjaan]">{{ old('instrumen.ia04a.waktu_pengerjaan', $ia04a['waktu_pengerjaan'] ?? '') }}</textarea>
                                </div>
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text">2.</span>
                                    <textarea class="form-control" rows="2" maxlength="1000" placeholder="Tugas tersebut selama 30 (tiga puluh) menit Sesi tanya jawab selama 30 (tiga puluh) menit"
                                        name="instrumen[ia04a][waktu_tanya_jawab]">{{ old('instrumen.ia04a.waktu_tanya_jawab', $ia04a['waktu_tanya_jawab'] ?? '') }}</textarea>
                                </div>
                                <label class="form-label small fw-semibold">Total waktu</label>
                                <textarea class="form-control form-control-sm" rows="2" maxlength="1000" placeholder="Total waktu penyelesaian Tugas, Pemaparan Tugas, dan Tanya Jawab selama 120 (seratus dua puluh) menit"
                                    name="instrumen[ia04a][total_waktu]">{{ old('instrumen.ia04a.total_waktu', $ia04a['total_waktu'] ?? '') }}</textarea>
                            </div>
                        </div>

                        {{-- FR.IA.04B Aspek Penilaian --}}
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h5 class="card-title mb-0">FR.IA.04B · Aspek Penilaian</h5>
                                        <p class="text-muted small mb-0">Asesor mengisi tanggapan & pencapaian, dan dapat menambah pertanyaan sendiri.</p>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-tambah-aspek">
                                        <i class="mdi mdi-plus-circle me-1"></i> Tambah Aspek
                                    </button>
                                </div>
                                <input type="hidden" name="instrumen[ia04b][aspek]" value="">
                                <div id="daftar-aspek">
                                    @foreach ($aspekIa04b as $i => $aspek)
                                        @include('cms.mapa-template.partials.baris-aspek', ['i' => $i, 'aspek' => $aspek])
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($metode === 'vp')
                        @php
                            $pertanyaanIa09 = old('instrumen.ia09.pertanyaan', $template->instrumenUntuk(\App\Models\MapaTemplate::IA_09)['pertanyaan'] ?? []);
                            $buktiIa08 = old('instrumen.ia08.bukti', $template->instrumenUntuk(\App\Models\MapaTemplate::IA_08)['bukti'] ?? []);
                        @endphp
                        {{-- Bukti Portofolio (FR.APL.01 lampiran bukti relevan -> FR.IA.08 / IA.09) --}}
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h5 class="card-title mb-0">FR.IA.08 · Bukti Portofolio</h5>
                                        <p class="text-muted small mb-0">Tampil di FR.APL.01 bagian "Lampiran Bukti yang relevan" untuk diunggah asesi (asesi tidak dapat menghapus butir ini). Bukti yang diunggah menjadi bukti portofolio FR.IA.08 &amp; FR.IA.09.</p>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-tambah-bukti">
                                        <i class="mdi mdi-plus-circle me-1"></i> Tambah
                                    </button>
                                </div>
                                <input type="hidden" name="instrumen[ia08][bukti]" value="">
                                <div id="daftar-bukti">
                                    @foreach ($buktiIa08 as $teks)
                                        @include('cms.mapa-template.partials.baris-pertanyaan', ['teks' => $teks, 'nama' => 'instrumen[ia08][bukti][]', 'maks' => 500])
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        @php
                            $materiIa08 = array_map('intval', (array) old('instrumen.ia08.materi', $template->instrumenUntuk(\App\Models\MapaTemplate::IA_08)['materi'] ?? []));
                        @endphp
                        {{-- FR.IA.08 Materi/Substansi Wawancara (KUK yang diklarifikasi) --}}
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-0">FR.IA.08 · Materi/Substansi Wawancara/KUK</h5>
                                <p class="text-muted small">Pilih KUK per unit kompetensi yang ditampilkan di tabel klarifikasi FR.IA.08. Asesor dapat menambah atau menghapus saat mengisi.</p>
                                <input type="hidden" name="instrumen[ia08][materi]" value="">
                                @foreach ($skema->unitKompetensis as $u => $unit)
                                    @php
                                        $kukUnit = $unit->elemenKompetensi->flatMap(fn ($e) => $e->kriteriaUnjukKerja)->pluck('id')->all();
                                    @endphp
                                    <div class="border rounded p-2 mb-2">
                                        <div class="small fw-semibold mb-2">Unit {{ $u + 1 }} · <span class="font-monospace">{{ $unit->kode_unit }}</span> {{ $unit->judul_unit }}</div>
                                        <div class="daftar-materi" id="daftar-materi-{{ $unit->id }}">
                                            @foreach (array_intersect($materiIa08, $kukUnit) as $kukId)
                                                @include('cms.mapa-template.partials.baris-materi', ['unit' => $unit, 'dipilih' => $kukId])
                                            @endforeach
                                        </div>
                                        @if ($kukUnit === [])
                                            <span class="text-muted small">Unit ini belum memiliki KUK.</span>
                                        @else
                                            <button type="button" class="btn btn-link btn-sm p-0 btn-tambah-materi" data-unit="{{ $unit->id }}">
                                                <i class="mdi mdi-plus-circle me-1"></i>Tambah KUK
                                            </button>
                                            <template id="tpl-materi-{{ $unit->id }}">
                                                @include('cms.mapa-template.partials.baris-materi', ['unit' => $unit, 'dipilih' => null])
                                            </template>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        {{-- FR.IA.09 Pertanyaan Wawancara --}}
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h5 class="card-title mb-0">FR.IA.09 · Pertanyaan Wawancara</h5>
                                        <p class="text-muted small mb-0">Asesor menulis kesimpulan jawaban & pencapaian, dan dapat menambah pertanyaan sendiri.</p>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-tambah-pertanyaan">
                                        <i class="mdi mdi-plus-circle me-1"></i> Tambah
                                    </button>
                                </div>
                                <input type="hidden" name="instrumen[ia09][pertanyaan]" value="">
                                <div id="daftar-pertanyaan">
                                    @foreach ($pertanyaanIa09 as $teks)
                                        @include('cms.mapa-template.partials.baris-pertanyaan', ['teks' => $teks, 'nama' => 'instrumen[ia09][pertanyaan][]'])
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($metode === 'tpd')
                        @php
                            $ia02 = $template->instrumenUntuk(\App\Models\MapaTemplate::IA_02);
                            $pertanyaanIa03 = old('instrumen.ia03.pertanyaan', $template->instrumenUntuk(\App\Models\MapaTemplate::IA_03)['pertanyaan'] ?? []);
                            $judulSkema = $skema->title['id'] ?? '';
                            $contohSkenario = "Saat ini anda akan mengikuti sertifikasi kompetensi dengan skema {$judulSkema}.\n\n"
                                ."Untuk itu anda diminta untuk mendemonstrasikan keterampilan dan sikap kerja anda, dengan lingkup pekerjaan:\n"
                                .$skema->unitKompetensis->values()->map(fn ($u, $i) => ($i + 1).'. '.$u->judul_unit)->implode("\n")
                                ."\n\nUntuk melakukan hal diatas, anda akan dilengkapi dengan alat dan bahan :\n1. ";
                        @endphp

                        {{-- FR.IA.02 Tugas Praktik Demonstrasi --}}
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                    <div>
                                        <h5 class="card-title mb-0">FR.IA.02 · Skenario Tugas Praktik Demonstrasi</h5>
                                        <p class="text-muted small mb-0">Ditampilkan ke asesor & asesi pada FR.IA.02 (tidak dapat diubah asesor).</p>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-contoh-skenario">
                                        <i class="mdi mdi-auto-fix me-1"></i> Isi contoh skenario
                                    </button>
                                </div>
                                <input type="hidden" name="instrumen[ia02][skenario]" value="">
                                <textarea class="form-control mb-3" rows="14" maxlength="10000" id="isian-skenario"
                                    name="instrumen[ia02][skenario]">{{ old('instrumen.ia02.skenario', $ia02['skenario'] ?? '') }}</textarea>
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Waktu</label>
                                        <input type="text" class="form-control form-control-sm" maxlength="100" placeholder="mis. 20 Menit"
                                            name="instrumen[ia02][waktu]" value="{{ old('instrumen.ia02.waktu', $ia02['waktu'] ?? '') }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- FR.IA.03 Pertanyaan untuk Mendukung Observasi --}}
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h5 class="card-title mb-0">FR.IA.03 · Pertanyaan untuk Mendukung Observasi</h5>
                                        <p class="text-muted small mb-0">Asesor mengisi tanggapan & pencapaian, dan dapat menambah pertanyaan sendiri.</p>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-tambah-pertanyaan">
                                        <i class="mdi mdi-plus-circle me-1"></i> Tambah
                                    </button>
                                </div>
                                <input type="hidden" name="instrumen[ia03][pertanyaan]" value="">
                                <div id="daftar-pertanyaan">
                                    @foreach ($pertanyaanIa03 as $teks)
                                        @include('cms.mapa-template.partials.baris-pertanyaan', ['teks' => $teks])
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Penyusun & Validator per dokumen --}}
                    @foreach (array_intersect_key(['penyusun_mapa01' => 'FR.MAPA.01', 'penyusun_mapa02' => 'FR.MAPA.02', 'penyusun_ia02' => 'FR.IA.02', 'penyusun_ia06b' => 'FR.IA.06B', 'penyusun_ia09' => 'FR.IA.09', 'penyusun_ia04a' => 'FR.IA.04A', 'penyusun_ia04b' => 'FR.IA.04B'], array_flip($bagianPenyusun)) as $bagian => $kodeDok)
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
                <template id="tpl-baris-aspek">
                    @include('cms.mapa-template.partials.baris-aspek', ['i' => '__I__', 'aspek' => []])
                </template>
                <template id="tpl-baris-soal">
                    @include('cms.mapa-template.partials.baris-soal', ['i' => '__I__', 'soal' => []])
                </template>
                <template id="tpl-baris-pertanyaan">
                    @include('cms.mapa-template.partials.baris-pertanyaan', ['teks' => '', 'nama' => $metode === 'vp' ? 'instrumen[ia09][pertanyaan][]' : 'instrumen[ia03][pertanyaan][]'])
                </template>
                @if ($metode === 'vp')
                    <template id="tpl-baris-bukti">
                        @include('cms.mapa-template.partials.baris-pertanyaan', ['teks' => '', 'nama' => 'instrumen[ia08][bukti][]', 'maks' => 500])
                    </template>
                @endif
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

            // FR.IA.03: tambah / hapus pertanyaan + penomoran.
            const daftar = document.getElementById('daftar-pertanyaan');
            const nomori = () => daftar?.querySelectorAll('.nomor-pertanyaan').forEach((el, i) => el.textContent = (i + 1) + '.');
            document.getElementById('btn-tambah-pertanyaan')?.addEventListener('click', () => {
                daftar.insertAdjacentHTML('beforeend', document.getElementById('tpl-baris-pertanyaan').innerHTML);
                nomori();
            });
            daftar?.addEventListener('click', (e) => {
                const hapus = e.target.closest('.btn-hapus-pertanyaan');
                if (hapus) { hapus.closest('.baris-pertanyaan').remove(); nomori(); }
            });

            // Bukti portofolio (template VP): tambah / hapus + penomoran.
            const daftarBukti = document.getElementById('daftar-bukti');
            const nomoriBukti = () => daftarBukti?.querySelectorAll('.nomor-pertanyaan').forEach((el, i) => el.textContent = (i + 1) + '.');
            document.getElementById('btn-tambah-bukti')?.addEventListener('click', () => {
                daftarBukti.insertAdjacentHTML('beforeend', document.getElementById('tpl-baris-bukti').innerHTML);
                nomoriBukti();
            });
            daftarBukti?.addEventListener('click', (e) => {
                const hapus = e.target.closest('.btn-hapus-pertanyaan');
                if (hapus) { hapus.closest('.baris-pertanyaan').remove(); nomoriBukti(); }
            });
            nomoriBukti();

            // Materi/substansi wawancara IA.08: tambah dropdown KUK per unit / hapus (ikon x).
            document.querySelectorAll('.btn-tambah-materi').forEach((btn) => btn.addEventListener('click', () => {
                const unit = btn.dataset.unit;
                document.getElementById('daftar-materi-' + unit)
                    .insertAdjacentHTML('beforeend', document.getElementById('tpl-materi-' + unit).innerHTML);
            }));
            document.addEventListener('click', (e) => {
                const hapus = e.target.closest('.btn-hapus-materi');
                if (hapus) hapus.closest('.baris-materi').remove();
            });
            nomori();

            // FR.IA.04B: tambah / hapus aspek penilaian + penomoran.
            const daftarAspek = document.getElementById('daftar-aspek');
            const nomoriAspek = () => daftarAspek?.querySelectorAll('.nomor-aspek').forEach((el, i) => el.textContent = (i + 1) + '.');
            document.getElementById('btn-tambah-aspek')?.addEventListener('click', () => {
                daftarAspek.insertAdjacentHTML('beforeend', document.getElementById('tpl-baris-aspek').innerHTML.replaceAll('__I__', 'b' + Date.now()));
                nomoriAspek();
            });
            daftarAspek?.addEventListener('click', (e) => {
                const hapus = e.target.closest('.btn-hapus-aspek');
                if (hapus) { hapus.closest('.baris-aspek').remove(); nomoriAspek(); }
            });
            nomoriAspek();

            // FR.IA.06: tambah / hapus soal + penomoran. Indeks unik agar pasangan pertanyaan-kunci tetap sejajar.
            const daftarSoal = document.getElementById('daftar-soal');
            const nomoriSoal = () => daftarSoal?.querySelectorAll('.nomor-soal').forEach((el, i) => el.textContent = (i + 1) + '.');
            document.getElementById('btn-tambah-soal')?.addEventListener('click', () => {
                daftarSoal.insertAdjacentHTML('beforeend', document.getElementById('tpl-baris-soal').innerHTML.replaceAll('__I__', 'b' + Date.now()));
                nomoriSoal();
            });
            daftarSoal?.addEventListener('click', (e) => {
                const hapus = e.target.closest('.btn-hapus-soal');
                if (hapus) { hapus.closest('.baris-soal').remove(); nomoriSoal(); }
            });
            nomoriSoal();

            // FR.IA.02: contoh skenario dari judul skema & unit (tidak menimpa isian yang ada).
            document.getElementById('btn-contoh-skenario')?.addEventListener('click', () => {
                const ta = document.getElementById('isian-skenario');
                if (ta.value.trim() !== '' && !confirm('Ganti skenario yang sudah ada dengan contoh?')) return;
                ta.value = @json($contohSkenario ?? '');
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

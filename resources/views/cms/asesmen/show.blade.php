@extends('layouts.app')

@section('title', 'Detail Asesmen')

@php
    $statusBadge = match ($jadwalAsesmen->status) {
        'terjadwal' => 'bg-info',
        'berlangsung' => 'bg-warning text-dark',
        'selesai' => 'bg-success',
        'dibatalkan' => 'bg-danger',
        default => 'bg-secondary',
    };
@endphp

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Asesmen',
            'title' => 'Detail Asesmen',
            'action' => null,
        ])
        <!-- end page title -->
        <div class="container-fluid">

            <div class="page-content-wrapper">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="row">
                    {{-- Kolom kiri: Info pendaftaran & jadwal --}}
                    <div class="col-lg-8">

                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Informasi Pendaftaran</h5>
                                    <span class="badge {{ $statusBadge }}">
                                        {{ ucfirst($jadwalAsesmen->status) }}
                                    </span>
                                </div>

                                <table class="table table-borderless table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <th style="width:220px;">Kode Pendaftaran</th>
                                            <td>{{ $jadwalAsesmen->pendaftaran->kode_pendaftaran ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Asesi</th>
                                            <td>{{ $jadwalAsesmen->pendaftaran->asesi->name ?? '-' }}
                                                <span class="text-muted">
                                                    ({{ $jadwalAsesmen->pendaftaran->asesi->email ?? '-' }})
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Skema Sertifikasi</th>
                                            <td>{{ $jadwalAsesmen->pendaftaran->skemaSertifikasi->title['id'] ?? '-' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Jadwal Asesmen</h5>

                                <table class="table table-borderless table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <th style="width:220px;">Asesor</th>
                                            <td>{{ $jadwalAsesmen->asesor->nama ?? '-' }}
                                                <span class="text-muted">
                                                    ({{ $jadwalAsesmen->asesor->email ?? '-' }})
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>TUK</th>
                                            <td>{{ $jadwalAsesmen->tuk->title ?? '-' }}
                                                @if ($jadwalAsesmen->tuk->address ?? null)
                                                    <div class="text-muted small">{{ $jadwalAsesmen->tuk->address }}
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Asesmen</th>
                                            <td>{{ $jadwalAsesmen->tanggal_asesmen?->format('d M Y') ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Waktu</th>
                                            <td>
                                                {{ \Illuminate\Support\Str::limit($jadwalAsesmen->waktu_mulai, 5, '') }}
                                                @if ($jadwalAsesmen->waktu_selesai)
                                                    - {{ \Illuminate\Support\Str::limit($jadwalAsesmen->waktu_selesai, 5, '') }}
                                                @endif
                                            </td>
                                        </tr>
                                        @if ($jadwalAsesmen->catatan)
                                            <tr>
                                                <th>Catatan</th>
                                                <td>{{ $jadwalAsesmen->catatan }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <th>Dijadwalkan Oleh</th>
                                            <td>{{ $jadwalAsesmen->dijadwalkanOleh->name ?? '-' }} pada
                                                {{ $jadwalAsesmen->created_at?->format('d M Y, H:i') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Progres MUK (dokumen asesmen) --}}
                        @php
                            $penilaian = $jadwalAsesmen->penilaianMuk;
                            $statusPenilaian = [
                                'menunggu_pengisian_apl' => ['bg-secondary', 'Menunggu pengisian APL'],
                                'menunggu_review' => ['bg-info', 'Menunggu review'],
                                'berlangsung' => ['bg-warning text-dark', 'Berlangsung'],
                                'selesai_kompeten' => ['bg-success', 'Selesai - Kompeten'],
                                'selesai_belum_kompeten' => ['bg-danger', 'Selesai - Belum Kompeten'],
                            ];
                            $statusDokumen = [
                                'belum_diisi' => ['bg-light text-dark', 'Belum diisi'],
                                'draft' => ['bg-warning text-dark', 'Draft'],
                                'menunggu_review' => ['bg-info', 'Menunggu review'],
                                'menunggu_persetujuan' => ['bg-info', 'Menunggu persetujuan asesi'],
                                'selesai' => ['bg-success', 'Selesai'],
                            ];
                            $metodeNama = ['kt' => 'Kegiatan Terstruktur', 'tpd' => 'Tugas Praktik Demonstrasi', 'vp' => 'Verifikasi Portofolio'];
                        @endphp
                        @if ($penilaian)
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="card-title mb-0">Progres Asesmen (MUK)</h5>
                                        @php $sp = $statusPenilaian[$penilaian->status] ?? ['bg-secondary', $penilaian->status]; @endphp
                                        <span class="badge {{ $sp[0] }}">{{ $sp[1] }}</span>
                                    </div>
                                    @php
                                        $dokumen = $penilaian->dokumenPenilaian;
                                        $selesai = $dokumen->where('status', 'selesai')->count();
                                    @endphp
                                    <table class="table table-borderless table-sm mb-3">
                                        <tbody>
                                            <tr>
                                                <th style="width:220px;">Metode Asesmen</th>
                                                <td>{{ $metodeNama[$penilaian->metode_asesmen] ?? 'Belum ditentukan' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Dokumen Selesai</th>
                                                <td>{{ $selesai }} dari {{ $dokumen->count() }}</td>
                                            </tr>
                                            @if ($penilaian->selesai_at)
                                                <tr>
                                                    <th>Selesai Pada</th>
                                                    <td>{{ $penilaian->selesai_at->format('d M Y, H:i') }}</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:130px;">Kode</th>
                                                    <th>Dokumen</th>
                                                    <th style="width:200px;">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($dokumen->sortBy(fn ($d) => $d->jenisDokumen->urutan_default) as $d)
                                                    @php $sd = $statusDokumen[$d->status] ?? ['bg-secondary', $d->status]; @endphp
                                                    <tr>
                                                        <td><code>{{ $d->jenisDokumen->kode }}</code></td>
                                                        <td>{{ $d->jenisDokumen->nama }}</td>
                                                        <td><span class="badge {{ $sd[0] }}">{{ $sd[1] }}</span></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>

                    {{-- Kolom kanan --}}
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Asesor</h5>

                                @if ($jadwalAsesmen->asesor?->photo)
                                    <img src="{{ asset($jadwalAsesmen->asesor->photo) }}" alt="Foto Asesor"
                                        class="img-fluid rounded mb-3" style="max-height:150px;">
                                @endif

                                <table class="table table-borderless table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <th style="width:130px;">Nama</th>
                                            <td>{{ $jadwalAsesmen->asesor->nama ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>No Registrasi</th>
                                            <td>{{ $jadwalAsesmen->asesor->nomor_registrasi ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>No Telepon</th>
                                            <td>{{ $jadwalAsesmen->asesor->no_telepon ?? '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <a href="{{ route('asesmen.index') }}" class="btn btn-light w-100">
                            &larr; Kembali ke Daftar
                        </a>
                    </div>
                </div>
                <!-- end row -->
            </div>

        </div> <!-- container-fluid -->
    </div>
@endsection

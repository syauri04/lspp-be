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

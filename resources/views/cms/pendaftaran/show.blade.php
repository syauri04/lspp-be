@extends('layouts.app')

@push('styles')
    <link href="{{ asset('resources/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('title', 'Detail Pendaftaran')

@php
    // Mapping status -> class badge Bootstrap. Sesuaikan warnanya kalau template kamu
    // punya varian badge sendiri (mis. badge-soft-*).
    $statusBadge = match ($pendaftaran->status) {
        'submitted' => 'bg-secondary',
        'awaiting_payment' => 'bg-warning text-dark',
        'paid' => 'bg-info',
        'completed' => 'bg-success',
        'rejected' => 'bg-danger',
        'expired' => 'bg-dark',
        'cancelled' => 'bg-light text-dark',
        default => 'bg-secondary',
    };
@endphp

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Pendaftaran',
            'title' => 'Review Sertifikasi',
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

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row">
                    {{-- Kolom kiri: Info pendaftaran + dokumen + riwayat --}}
                    <div class="col-lg-8">

                        {{-- Info Asesi & Skema --}}
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Informasi Pendaftaran</h5>
                                    <span class="badge {{ $statusBadge }}">
                                        {{ ucwords(str_replace('_', ' ', $pendaftaran->status)) }}
                                    </span>
                                </div>

                                <table class="table table-borderless table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <th style="width:220px;">Kode Pendaftaran</th>
                                            <td>{{ $pendaftaran->kode_pendaftaran }}</td>
                                        </tr>
                                        <tr>
                                            <th>Asesi</th>
                                            <td>{{ $pendaftaran->asesi->name ?? '-' }}
                                                <span class="text-muted">({{ $pendaftaran->asesi->email ?? '-' }})</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Skema Sertifikasi</th>
                                            <td>{{ $pendaftaran->skemaSertifikasi->title['id'] ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Harga (snapshot saat daftar)</th>
                                            <td>Rp {{ number_format($pendaftaran->harga_snapshot, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Submit</th>
                                            <td>{{ $pendaftaran->submitted_at?->format('d M Y, H:i') ?? '-' }}</td>
                                        </tr>
                                        @if ($pendaftaran->reviewed_at)
                                            <tr>
                                                <th>Direview Oleh</th>
                                                <td>{{ $pendaftaran->reviewer->name ?? '-' }} pada
                                                    {{ $pendaftaran->reviewed_at->format('d M Y, H:i') }}</td>
                                            </tr>
                                        @endif
                                        @if ($pendaftaran->catatan_admin)
                                            <tr>
                                                <th>Catatan Admin</th>
                                                <td>{{ $pendaftaran->catatan_admin }}</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Dokumen Upload --}}
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Dokumen Terlampir</h5>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Jenis Dokumen</th>
                                                <th>Nama File</th>
                                                <th>Ukuran</th>
                                                <th class="text-end">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($pendaftaran->dokumen as $dokumen)
                                                <tr>
                                                    <td>{{ ucwords(str_replace('_', ' ', $dokumen->jenis_dokumen)) }}</td>
                                                    <td>{{ $dokumen->nama_file_asli }}</td>
                                                    <td>{{ number_format($dokumen->ukuran_file / 1024, 2) }} MB</td>
                                                    <td class="text-end">
                                                        {{-- previewUrl() generate signed URL sementara --}}
                                                        <a href="{{ $dokumen->previewUrl() }}" target="_blank"
                                                            rel="noopener" class="btn btn-sm btn-outline-primary">
                                                            Lihat Dokumen
                                                        </a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted">Tidak ada dokumen.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- Riwayat Status (timeline) --}}
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Riwayat Status</h5>

                                <ul class="list-group list-group-flush">
                                    @forelse ($pendaftaran->riwayatStatus->sortBy('created_at') as $riwayat)
                                        <li class="list-group-item px-0">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <strong>{{ $riwayat->status_dari ?? 'Awal' }}</strong>
                                                    &rarr;
                                                    <strong>{{ $riwayat->status_ke }}</strong>
                                                    @if ($riwayat->changedBy)
                                                        <span class="text-muted">(oleh
                                                            {{ $riwayat->changedBy->name }})</span>
                                                    @endif
                                                    @if ($riwayat->keterangan)
                                                        <div class="text-muted small mt-1">{{ $riwayat->keterangan }}</div>
                                                    @endif
                                                </div>
                                                <small class="text-muted text-nowrap ms-3">
                                                    {{ $riwayat->created_at->format('d M Y, H:i') }}
                                                </small>
                                            </div>
                                        </li>
                                    @empty
                                        <li class="list-group-item px-0 text-muted">Belum ada riwayat.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>

                    </div>

                    {{-- Kolom kanan: Pembayaran + Aksi Approve/Reject --}}
                    <div class="col-lg-4">

                        @if ($pendaftaran->pembayaran)
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Info Pembayaran</h5>
                                    <table class="table table-borderless table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <th>Kode Transaksi</th>
                                                <td>{{ $pendaftaran->pembayaran->kode_transaksi }}</td>
                                            </tr>
                                            <tr>
                                                <th>Jumlah</th>
                                                <td>Rp
                                                    {{ number_format($pendaftaran->pembayaran->jumlah, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Status</th>
                                                <td>{{ ucwords($pendaftaran->pembayaran->status) }}</td>
                                            </tr>
                                            @if ($pendaftaran->pembayaran->expired_at)
                                                <tr>
                                                    <th>Batas Bayar</th>
                                                    <td>{{ $pendaftaran->pembayaran->expired_at->format('d M Y, H:i') }}
                                                    </td>
                                                </tr>
                                            @endif
                                            @if ($pendaftaran->pembayaran->paid_at)
                                                <tr>
                                                    <th>Dibayar Pada</th>
                                                    <td>{{ $pendaftaran->pembayaran->paid_at->format('d M Y, H:i') }}
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if ($pendaftaran->status === 'submitted')
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Review Pendaftaran</h5>

                                    {{-- Approve --}}
                                    <form action="{{ route('pendaftaran.approve', $pendaftaran->kode_pendaftaran) }}"
                                        method="POST" class="mb-4">
                                        @csrf
                                        <div class="mb-3">
                                            <label for="catatan_approve" class="form-label">Catatan (opsional)</label>
                                            <textarea name="catatan" id="catatan_approve" class="form-control" rows="2"
                                                placeholder="Catatan untuk asesi (opsional)"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-success w-100"
                                            onclick="return confirm('Approve pendaftaran ini?')">
                                            Approve
                                        </button>
                                    </form>

                                    <hr>

                                    {{-- Reject --}}
                                    <form action="{{ route('pendaftaran.reject', $pendaftaran->kode_pendaftaran) }}"
                                        method="POST" class="mt-4">
                                        @csrf
                                        <div class="mb-3">
                                            <label for="catatan_reject" class="form-label">
                                                Alasan Penolakan <span class="text-danger">*</span>
                                            </label>
                                            <textarea name="catatan" id="catatan_reject" class="form-control" rows="2"
                                                placeholder="Wajib diisi, akan dikirim ke asesi" required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-danger w-100"
                                            onclick="return confirm('Tolak pendaftaran ini?')">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif

                        <a href="{{ route('pendaftaran.index') }}" class="btn btn-light w-100">
                            &larr; Kembali ke Daftar
                        </a>

                    </div>
                </div>
                <!-- end row -->
            </div>

        </div> <!-- container-fluid -->
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('resources/assets/libs/parsleyjs/parsley.min.js') }}"></script>
    <script src="{{ asset('resources/assets/js/pages/form-validation.init.js') }}"></script>
@endpush

@extends('layouts.app')

@section('title', 'Verifikasi Pembayaran')

@php
    $filterLabel = [
        'menunggu' => 'Menunggu Verifikasi',
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak / Dibatalkan',
        'semua' => 'Semua',
    ];
    $badge = [
        'menunggu' => 'bg-warning text-dark',
        'disetujui' => 'bg-success',
        'ditolak' => 'bg-danger',
        'dibatalkan' => 'bg-secondary',
    ];
@endphp

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Verifikasi Pembayaran',
            'title' => 'Verifikasi Pembayaran',
            'action' => null,
        ])

        <div class="container-fluid">
            <div class="page-content-wrapper">

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle-outline me-1"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-1"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <p class="text-muted small">
                                    Bukti transfer manual yang diunggah asesi. Setelah disetujui, pendaftaran menjadi
                                    <strong>Lunas</strong> dan dapat dijadwalkan di menu Penjadwalan Asesmen.
                                </p>

                                <ul class="nav nav-pills mb-3">
                                    @foreach ($filterLabel as $key => $label)
                                        <li class="nav-item">
                                            <a href="{{ route('verifikasi-pembayaran.index', ['status' => $key]) }}"
                                                class="nav-link {{ $filter === $key ? 'active' : '' }}">{{ $label }}</a>
                                        </li>
                                    @endforeach
                                </ul>

                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <thead>
                                            <tr>
                                                <th class="text-center" style="width:60px;">No</th>
                                                <th>Asesi</th>
                                                <th>Skema Sertifikasi</th>
                                                <th class="text-end">Tagihan</th>
                                                <th class="text-end">Ditransfer</th>
                                                <th>Rekening Tujuan</th>
                                                <th>Dikirim</th>
                                                <th>Status</th>
                                                <th style="width:110px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($buktis as $i => $b)
                                                @php $pendaftaran = $b->pembayaran->pendaftaranSertifikasi; @endphp
                                                <tr>
                                                    <td class="text-center">{{ $buktis->firstItem() + $i }}</td>
                                                    <td>
                                                        {{ $pendaftaran->asesi->name ?? '-' }}
                                                        <div class="text-muted small">{{ $pendaftaran->asesi->email ?? '' }}</div>
                                                    </td>
                                                    <td>{{ $pendaftaran->skemaSertifikasi->title['id'] ?? '-' }}</td>
                                                    <td class="text-end">Rp {{ number_format($b->pembayaran->jumlah, 0, ',', '.') }}</td>
                                                    <td class="text-end">
                                                        Rp {{ number_format($b->jumlah_transfer, 0, ',', '.') }}
                                                        @if ($b->jumlahBerbeda())
                                                            <div><span class="badge bg-danger">Tidak sesuai</span></div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        {{ $b->rekening_tujuan['nama_bank'] ?? '-' }}
                                                        <div class="text-muted small">{{ $b->rekening_tujuan['nomor_rekening'] ?? '' }}</div>
                                                    </td>
                                                    <td>{{ $b->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</td>
                                                    <td>
                                                        <span class="badge {{ $badge[$b->status] ?? 'bg-secondary' }}">
                                                            {{ \App\Models\BuktiPembayaran::STATUS_LABEL[$b->status] ?? $b->status }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="{{ route('verifikasi-pembayaran.show', $b) }}"
                                                            class="btn btn-sm {{ $b->status === 'menunggu' ? 'btn-primary' : 'btn-light' }}">
                                                            {{ $b->status === 'menunggu' ? 'Verifikasi' : 'Lihat' }}
                                                        </a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted py-4">
                                                        Tidak ada bukti transfer pada filter ini.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                {{ $buktis->links() }}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

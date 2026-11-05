@extends('layouts.app')

@section('title', 'Verifikasi Pembayaran')

@php
    $pembayaran = $bukti->pembayaran;
    $pendaftaran = $pembayaran->pendaftaranSertifikasi;
    $berbeda = $bukti->jumlahBerbeda();
    $badge = [
        'menunggu' => 'bg-warning text-dark',
        'disetujui' => 'bg-success',
        'ditolak' => 'bg-danger',
        'dibatalkan' => 'bg-secondary',
    ];
    $label = \App\Models\BuktiPembayaran::STATUS_LABEL;
@endphp

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Verifikasi Pembayaran',
            'title' => 'Detail Bukti Transfer',
            'action' => null,
        ])

        <div class="container-fluid">
            <div class="page-content-wrapper">

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-1"></i> {{ session('error') }}
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

                <div class="row">
                    {{-- Kolom kiri: berkas & data transfer --}}
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Bukti Transfer</h5>
                                    <a href="{{ route('verifikasi-pembayaran.berkas', $bukti) }}" target="_blank" rel="noopener"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="mdi mdi-open-in-new me-1"></i> Buka di tab baru
                                    </a>
                                </div>

                                @if (str_starts_with($bukti->mime_type, 'image/'))
                                    <div class="border rounded p-2 text-center bg-light">
                                        <img src="{{ route('verifikasi-pembayaran.berkas', $bukti) }}" alt="Bukti transfer"
                                            class="img-fluid" style="max-height:520px;">
                                    </div>
                                @else
                                    <div class="border rounded p-4 text-center bg-light">
                                        <i class="mdi mdi-file-pdf-box text-danger" style="font-size:48px;"></i>
                                        <p class="mb-0">{{ $bukti->nama_file_asli }} ({{ $bukti->ukuran_file }} KB)</p>
                                    </div>
                                @endif

                                <table class="table table-sm table-borderless mt-3 mb-0">
                                    <tbody>
                                        <tr>
                                            <th style="width:200px;">Rekening Tujuan</th>
                                            <td>
                                                {{ $bukti->rekening_tujuan['nama_bank'] ?? '-' }}
                                                &middot; {{ $bukti->rekening_tujuan['nomor_rekening'] ?? '-' }}
                                                &middot; a.n. {{ $bukti->rekening_tujuan['atas_nama'] ?? '-' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Nama Pengirim</th>
                                            <td>{{ $bukti->nama_pengirim }}</td>
                                        </tr>
                                        <tr>
                                            <th>Bank Pengirim</th>
                                            <td>{{ $bukti->bank_pengirim }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Transfer</th>
                                            <td>{{ $bukti->tanggal_transfer->locale('id')->translatedFormat('d F Y') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Jumlah Ditransfer</th>
                                            <td>
                                                <strong>Rp {{ number_format($bukti->jumlah_transfer, 0, ',', '.') }}</strong>
                                                @if ($berbeda)
                                                    <span class="badge bg-danger ms-1">Tidak sesuai tagihan</span>
                                                @else
                                                    <span class="badge bg-success ms-1">Sesuai tagihan</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @if ($bukti->catatan_asesi)
                                            <tr>
                                                <th>Catatan Asesi</th>
                                                <td>{{ $bukti->catatan_asesi }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <th>Dikirim</th>
                                            <td>{{ $bukti->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if ($pembayaran->buktiPembayaran->count() > 1)
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Riwayat Bukti Transfer Tagihan Ini</h5>
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>Dikirim</th>
                                                <th class="text-end">Jumlah</th>
                                                <th>Status</th>
                                                <th>Catatan Admin</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($pembayaran->buktiPembayaran as $riwayat)
                                                <tr class="{{ $riwayat->id === $bukti->id ? 'table-active' : '' }}">
                                                    <td>
                                                        <a href="{{ route('verifikasi-pembayaran.show', $riwayat) }}">
                                                            {{ $riwayat->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}
                                                        </a>
                                                    </td>
                                                    <td class="text-end">Rp {{ number_format($riwayat->jumlah_transfer, 0, ',', '.') }}</td>
                                                    <td><span class="badge {{ $badge[$riwayat->status] ?? 'bg-secondary' }}">{{ $label[$riwayat->status] ?? $riwayat->status }}</span></td>
                                                    <td>{{ $riwayat->catatan_admin ?? '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Kolom kanan: tagihan & aksi --}}
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Tagihan</h5>
                                <table class="table table-borderless table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <th>Asesi</th>
                                            <td>{{ $pendaftaran->asesi->name ?? '-' }}
                                                <div class="text-muted small">{{ $pendaftaran->asesi->email ?? '' }}</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Skema</th>
                                            <td>{{ $pendaftaran->skemaSertifikasi->title['id'] ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Kode Transaksi</th>
                                            <td>{{ $pembayaran->kode_transaksi }}</td>
                                        </tr>
                                        <tr>
                                            <th>Jumlah Tagihan</th>
                                            <td><strong>Rp {{ number_format($pembayaran->jumlah, 0, ',', '.') }}</strong></td>
                                        </tr>
                                        <tr>
                                            <th>Status Tagihan</th>
                                            <td>{{ ucwords($pembayaran->status) }}</td>
                                        </tr>
                                        <tr>
                                            <th>Status Bukti</th>
                                            <td><span class="badge {{ $badge[$bukti->status] ?? 'bg-secondary' }}">{{ $label[$bukti->status] ?? $bukti->status }}</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <a href="{{ route('pendaftaran.show', $pendaftaran->kode_pendaftaran) }}" class="btn btn-sm btn-light mt-2">
                                    Lihat Pendaftaran
                                </a>
                            </div>
                        </div>

                        @if ($bukti->status === 'menunggu')
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Verifikasi</h5>
                                    <p class="text-muted small">
                                        Pastikan dana sudah masuk ke rekening tujuan (cek mutasi rekening) sebelum menyetujui.
                                    </p>

                                    <form action="{{ route('verifikasi-pembayaran.setujui', $bukti) }}" method="POST" class="mb-4">
                                        @csrf
                                        <div class="mb-3">
                                            <label for="catatan_setujui" class="form-label">Catatan (opsional)</label>
                                            <textarea name="catatan" id="catatan_setujui" class="form-control" rows="2" maxlength="1000"
                                                placeholder="Catatan internal (opsional)"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-success w-100"
                                            onclick="return confirm('{{ $berbeda ? 'Jumlah transfer TIDAK sesuai tagihan. Tetap setujui pembayaran ini?' : 'Setujui pembayaran ini? Pendaftaran akan berstatus lunas.' }}')">
                                            Setujui Pembayaran
                                        </button>
                                    </form>

                                    <hr>

                                    <form action="{{ route('verifikasi-pembayaran.tolak', $bukti) }}" method="POST" class="mt-4">
                                        @csrf
                                        <div class="mb-3">
                                            <label for="alasan" class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                                            <textarea name="alasan" id="alasan" class="form-control" rows="3" maxlength="1000" required
                                                placeholder="Wajib diisi, dikirim ke asesi (mis. bukti tidak terbaca, dana belum masuk)">{{ old('alasan') }}</textarea>
                                        </div>
                                        <button type="submit" class="btn btn-danger w-100"
                                            onclick="return confirm('Tolak bukti transfer ini? Asesi diminta mengunggah ulang.')">
                                            Tolak Bukti Transfer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Hasil Verifikasi</h5>
                                    <p class="mb-1">
                                        <span class="badge {{ $badge[$bukti->status] ?? 'bg-secondary' }}">{{ $label[$bukti->status] ?? $bukti->status }}</span>
                                    </p>
                                    @if ($bukti->verifikator)
                                        <p class="mb-1 small">Oleh {{ $bukti->verifikator->name }}
                                            @if ($bukti->diverifikasi_at)
                                                &middot; {{ $bukti->diverifikasi_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
                                            @endif
                                        </p>
                                    @endif
                                    @if ($bukti->catatan_admin)
                                        <p class="mb-0 small text-muted">Catatan: {{ $bukti->catatan_admin }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <a href="{{ route('verifikasi-pembayaran.index') }}" class="btn btn-light w-100">&larr; Kembali ke Daftar</a>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Pengaturan Pembayaran')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Pengaturan Pembayaran',
            'title' => 'Pengaturan Pembayaran',
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
                    {{-- Metode pembayaran --}}
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-1">Metode Pembayaran</h5>
                                <p class="text-muted small">Metode yang ditampilkan ke asesi di halaman pembayaran website.</p>

                                <form action="{{ route('pengaturan-pembayaran.metode') }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch" id="doku_aktif" name="doku_aktif" value="1"
                                            {{ $dokuAktif ? 'checked' : '' }}>
                                        <label class="form-check-label" for="doku_aktif">
                                            <strong>Bayar Online (DOKU)</strong>
                                            <div class="text-muted small">VA, e-wallet, QRIS, dll. Lunas otomatis.</div>
                                        </label>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch" id="manual_aktif" name="manual_aktif" value="1"
                                            {{ $manualAktif ? 'checked' : '' }}>
                                        <label class="form-check-label" for="manual_aktif">
                                            <strong>Transfer Manual</strong>
                                            <div class="text-muted small">Asesi transfer ke rekening di samping & unggah bukti; diverifikasi admin.</div>
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Simpan Metode</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Rekening --}}
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h5 class="card-title mb-0">Rekening Tujuan Transfer</h5>
                                        <p class="text-muted small mb-0">Hanya rekening aktif yang ditampilkan ke asesi.</p>
                                    </div>
                                    <a href="{{ route('pengaturan-pembayaran.rekening.create') }}" class="btn btn-primary btn-sm">
                                        <i class="mdi mdi-plus-circle me-1"></i> Tambah Rekening
                                    </a>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:60px;" class="text-center">Urutan</th>
                                                <th>Bank</th>
                                                <th>Nomor Rekening</th>
                                                <th>Atas Nama</th>
                                                <th>Status</th>
                                                <th style="width:150px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($rekenings as $r)
                                                <tr>
                                                    <td class="text-center">{{ $r->urutan }}</td>
                                                    <td>
                                                        {{ $r->nama_bank }}
                                                        @if ($r->keterangan)
                                                            <div class="text-muted small">{{ $r->keterangan }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="font-monospace">{{ $r->nomor_rekening }}</td>
                                                    <td>{{ $r->atas_nama }}</td>
                                                    <td>
                                                        <span class="badge {{ $r->aktif ? 'bg-success' : 'bg-secondary' }}">{{ $r->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                                                        @if ($r->bukti_pembayaran_count)
                                                            <div class="text-muted small">{{ $r->bukti_pembayaran_count }} bukti transfer</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <a href="{{ route('pengaturan-pembayaran.rekening.edit', $r) }}" class="btn btn-sm btn-light">Ubah</a>
                                                        <form action="{{ route('pengaturan-pembayaran.rekening.destroy', $r) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                onclick="return confirm('Hapus rekening ini? Bukti transfer lama tetap menyimpan data rekeningnya. Disarankan cukup dinonaktifkan.')">
                                                                Hapus
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        Belum ada rekening. Transfer manual tidak ditampilkan ke asesi sampai ada rekening aktif.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

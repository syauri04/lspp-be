@extends('layouts.app')

@section('title', 'Voucher')

@section('content')
    @php
        $zona = config('muk.zona_waktu');
        $warnaStatus = ['Berlaku' => 'bg-success', 'Belum berlaku' => 'bg-info', 'Kedaluwarsa' => 'bg-secondary', 'Nonaktif' => 'bg-danger'];
    @endphp
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Voucher',
            'title' => 'Daftar Voucher',
            'action' => [
                'label' => 'Tambah Voucher',
                'url' => route('vouchers.create'),
                'icon' => 'mdi mdi-plus-circle',
                'type' => 'success',
            ],
        ])

        <div class="container-fluid">
            <div class="page-content-wrapper">

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle-outline me-1"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Voucher dipakai asesi di halaman pembayaran (checkout). Kuota menghitung pemakaian pada tagihan yang
                            menunggu pembayaran atau sudah lunas; tagihan kedaluwarsa otomatis mengembalikan kuota.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Potongan</th>
                                        <th>Periode (WIB)</th>
                                        <th class="text-center">Terpakai / Kuota</th>
                                        <th class="text-center">Per Asesi</th>
                                        <th>Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($vouchers as $v)
                                        @php $status = $v->statusLabel(); @endphp
                                        <tr>
                                            <td>
                                                <span class="font-monospace fw-bold">{{ $v->kode }}</span>
                                                @if ($v->nama)
                                                    <div class="small text-muted">{{ $v->nama }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $v->labelNilai() }}</td>
                                            <td class="small">
                                                {{ $v->mulai_at->timezone($zona)->format('d/m/Y H:i') }}<br>
                                                s.d. {{ $v->berakhir_at->timezone($zona)->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="text-center">
                                                {{ $v->pemakaian_terpakai_count }} / {{ $v->kuota ?? '∞' }}
                                            </td>
                                            <td class="text-center">{{ $v->maks_per_asesi ? $v->maks_per_asesi.'x' : '∞' }}</td>
                                            <td><span class="badge {{ $warnaStatus[$status] ?? 'bg-secondary' }}">{{ $status }}</span></td>
                                            <td class="text-center text-nowrap">
                                                <a href="{{ route('vouchers.show', $v) }}" class="btn btn-sm btn-light" title="Detail & pemakaian">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('vouchers.edit', $v) }}" class="btn btn-sm btn-outline-secondary" title="Ubah">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </a>
                                                <form action="{{ route('vouchers.destroy', $v) }}" method="POST" class="d-inline"
                                                    onsubmit="return confirm('Hapus voucher {{ $v->kode }}?\n\nKode tidak bisa dipakai lagi. Riwayat pemakaian & potongan pada tagihan tetap tersimpan.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Belum ada voucher.</td>
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
@endsection

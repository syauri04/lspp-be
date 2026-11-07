@extends('layouts.app')

@section('title', 'Voucher '.$voucher->kode)

@section('content')
    @php
        $zona = config('muk.zona_waktu');
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        $warnaTagihan = ['paid' => 'bg-success', 'pending' => 'bg-warning text-dark', 'expired' => 'bg-secondary', 'cancelled' => 'bg-secondary', 'failed' => 'bg-danger', 'refunded' => 'bg-info'];
        $labelTagihan = ['paid' => 'Lunas', 'pending' => 'Menunggu bayar', 'expired' => 'Kedaluwarsa', 'cancelled' => 'Dibatalkan', 'failed' => 'Gagal', 'refunded' => 'Refund'];
    @endphp
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Voucher',
            'title' => 'Voucher '.$voucher->kode,
            'action' => [
                'label' => 'Ubah Voucher',
                'url' => route('vouchers.edit', $voucher),
                'icon' => 'mdi mdi-pencil',
                'type' => 'primary',
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

                <div class="row">
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="font-monospace mb-1">{{ $voucher->kode }}</h5>
                                @if ($voucher->nama)
                                    <p class="text-muted mb-3">{{ $voucher->nama }}</p>
                                @endif
                                <table class="table table-sm mb-0">
                                    <tr><th class="fw-normal text-muted">Status</th><td>{{ $voucher->statusLabel() }}</td></tr>
                                    <tr><th class="fw-normal text-muted">Potongan</th><td>{{ $voucher->labelNilai() }}</td></tr>
                                    <tr><th class="fw-normal text-muted">Mulai</th><td>{{ $voucher->mulai_at->timezone($zona)->format('d/m/Y H:i') }} WIB</td></tr>
                                    <tr><th class="fw-normal text-muted">Berakhir</th><td>{{ $voucher->berakhir_at->timezone($zona)->format('d/m/Y H:i') }} WIB</td></tr>
                                    <tr><th class="fw-normal text-muted">Terpakai</th><td>{{ $voucher->pemakaian_terpakai_count }} / {{ $voucher->kuota ?? '∞' }}</td></tr>
                                    <tr><th class="fw-normal text-muted">Per asesi</th><td>{{ $voucher->maks_per_asesi ? $voucher->maks_per_asesi.'x' : 'Tanpa batas' }}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Riwayat Pemakaian</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0 small">
                                        <thead>
                                            <tr>
                                                <th>Asesi</th>
                                                <th>Skema</th>
                                                <th>Kode Transaksi</th>
                                                <th class="text-end">Harga</th>
                                                <th class="text-end">Potongan</th>
                                                <th class="text-end">Dibayar</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($pemakaian as $p)
                                                @php $st = $p->pembayaran?->status; @endphp
                                                <tr @class(['text-muted' => ! $p->aktif])>
                                                    <td>
                                                        {{ $p->asesi?->name ?? '-' }}
                                                        <div class="text-muted">{{ $p->asesi?->email }}</div>
                                                    </td>
                                                    <td>{{ $p->skemaSertifikasi?->title['id'] ?? ($p->skemaSertifikasi?->title['en'] ?? '-') }}</td>
                                                    <td class="font-monospace">{{ $p->pembayaran?->kode_transaksi ?? '-' }}</td>
                                                    <td class="text-end">{{ $rp($p->jumlah_awal) }}</td>
                                                    <td class="text-end">- {{ $rp($p->potongan) }}</td>
                                                    <td class="text-end fw-semibold">{{ $rp($p->jumlah_akhir) }}</td>
                                                    <td>
                                                        @if (! $p->aktif)
                                                            <span class="badge bg-light text-dark" title="Dilepas {{ $p->dilepas_at?->timezone($zona)->format('d/m/Y H:i') }}">Dilepas</span>
                                                        @else
                                                            <span class="badge {{ $warnaTagihan[$st] ?? 'bg-secondary' }}">{{ $labelTagihan[$st] ?? $st }}</span>
                                                        @endif
                                                        <div class="text-muted">{{ $p->created_at->timezone($zona)->format('d/m/Y H:i') }}</div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted">Voucher belum pernah dipakai.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">{{ $pemakaian->links('pagination::bootstrap-5') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

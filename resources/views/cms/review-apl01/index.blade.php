@extends('layouts.app')

@section('title', 'Review FR.APL.01')

@php
    $filterLabel = [
        'menunggu_review' => 'Menunggu Review',
        'dikembalikan' => 'Dikembalikan ke Asesi',
        'selesai' => 'Diterima',
        'semua' => 'Semua',
    ];
@endphp

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Review FR.APL.01',
            'title' => 'Review FR.APL.01',
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

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">

                                <ul class="nav nav-pills mb-3">
                                    @foreach ($filterLabel as $key => $label)
                                        <li class="nav-item">
                                            <a href="{{ route('review-apl01.index', ['status' => $key]) }}"
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
                                                <th>Asesor</th>
                                                <th>Tanggal Asesmen</th>
                                                <th>Dikirim</th>
                                                <th>Status</th>
                                                <th style="width:110px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($dokumens as $i => $dok)
                                                @php
                                                    $pendaftaran = $dok->penilaianMuk->pendaftaran;
                                                    $jadwal = $dok->penilaianMuk->jadwalAsesmen;
                                                @endphp
                                                <tr>
                                                    <td class="text-center">{{ $dokumens->firstItem() + $i }}</td>
                                                    <td>
                                                        {{ $pendaftaran->asesi->name ?? '-' }}
                                                        <div class="text-muted small">{{ $pendaftaran->asesi->email ?? '' }}</div>
                                                    </td>
                                                    <td>{{ $pendaftaran->skemaSertifikasi->title['id'] ?? '-' }}</td>
                                                    <td>{{ $jadwal?->asesor?->nama ?? '-' }}</td>
                                                    <td>{{ $jadwal?->tanggal_asesmen?->format('d M Y') ?? '-' }}</td>
                                                    <td>{{ $dok->submitted_at?->format('d M Y, H:i') ?? '-' }}</td>
                                                    <td>
                                                        @if ($dok->status === 'menunggu_review')
                                                            <span class="badge bg-warning text-dark">Menunggu Review</span>
                                                        @elseif ($dok->status === 'selesai')
                                                            <span class="badge bg-success">Diterima</span>
                                                        @elseif ($dok->hasil_rekomendasi_admin === 'ditolak')
                                                            <span class="badge bg-danger">Dikembalikan</span>
                                                        @else
                                                            <span class="badge bg-secondary">{{ $dok->status }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <a href="{{ route('review-apl01.show', $dok) }}"
                                                            class="btn btn-sm {{ $dok->status === 'menunggu_review' ? 'btn-primary' : 'btn-light' }}">
                                                            {{ $dok->status === 'menunggu_review' ? 'Review' : 'Lihat' }}
                                                        </a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-4">
                                                        Tidak ada FR.APL.01 pada filter ini.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                {{ $dokumens->links() }}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Penerbitan Sertifikat')

@section('content')
    @php
        $warna = ['menunggu_dokumen' => 'bg-warning text-dark', 'siap_diselesaikan' => 'bg-info', 'selesai' => 'bg-success'];
    @endphp
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Penerbitan Sertifikat',
            'title' => 'Penerbitan Sertifikat',
            'action' => null,
        ])

        <div class="container-fluid">
            <div class="page-content-wrapper">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <ul class="nav nav-pills">
                                        @foreach (\App\Http\Controllers\CMS\PenerbitanSertifikatController::FILTER as $key => $label)
                                            <li class="nav-item">
                                                <a href="{{ route('penerbitan-sertifikat.index', ['status' => $key, 'q' => $cari ?: null]) }}"
                                                    class="nav-link {{ $filter === $key ? 'active' : '' }}">{{ $label }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <form method="GET" action="{{ route('penerbitan-sertifikat.index') }}" class="d-flex gap-2">
                                        <input type="hidden" name="status" value="{{ $filter }}">
                                        <input type="search" name="q" value="{{ $cari }}" maxlength="100" class="form-control form-control-sm"
                                            placeholder="Nama asesi / kode / no. sertifikat" style="min-width:260px;">
                                        <button class="btn btn-sm btn-light" type="submit"><i class="mdi mdi-magnify"></i></button>
                                    </form>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <thead>
                                            <tr>
                                                <th class="text-center" style="width:60px;">No</th>
                                                <th>Asesi</th>
                                                <th>Skema Sertifikasi</th>
                                                <th>Hasil</th>
                                                <th>Dokumen SK</th>
                                                <th>E-Sertifikat</th>
                                                <th>Status</th>
                                                <th style="width:110px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($data as $i => $row)
                                                <tr>
                                                    <td class="text-center">{{ $data->firstItem() + $i }}</td>
                                                    <td>
                                                        {{ $row->pendaftaran->asesi->name ?? '-' }}
                                                        <div class="text-muted small">{{ $row->pendaftaran->asesi->email ?? '' }}</div>
                                                    </td>
                                                    <td>{{ $row->pendaftaran->skemaSertifikasi->title['id'] ?? '-' }}</td>
                                                    <td>
                                                        <span class="badge {{ $row->kompeten() ? 'bg-success' : 'bg-danger' }}">
                                                            {{ \App\Models\PenerbitanSertifikasi::HASIL_LABEL[$row->hasil] ?? $row->hasil }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $row->dokumen_count }} / {{ $jumlahJenis }}</td>
                                                    <td>
                                                        @if (! $row->kompeten())
                                                            <span class="text-muted">Tidak perlu</span>
                                                        @elseif ($row->sertifikat)
                                                            <span class="font-monospace small">{{ $row->sertifikat->no_sertifikat }}</span>
                                                        @else
                                                            <span class="text-warning">Belum</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge {{ $warna[$row->status] ?? 'bg-secondary' }}">
                                                            {{ \App\Models\PenerbitanSertifikasi::STATUS_LABEL[$row->status] ?? $row->status }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="{{ route('penerbitan-sertifikat.show', $row) }}"
                                                            class="btn btn-sm {{ $row->selesai() ? 'btn-light' : 'btn-primary' }}">
                                                            {{ $row->selesai() ? 'Lihat' : 'Proses' }}
                                                        </a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-4">Tidak ada data pada filter ini.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                {{ $data->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

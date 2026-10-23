@extends('layouts.app')

@push('styles')
    <!-- DataTables -->
    <link href="{{ asset('resources/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('resources/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css') }}"
        rel="stylesheet" />

    <!-- Responsive datatable -->
    <link href="{{ asset('resources/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}"
        rel="stylesheet" />
@endpush

@section('title', 'Daftar Asesmen')

@php
    $statusBadge = [
        'terjadwal' => 'bg-info',
        'berlangsung' => 'bg-warning text-dark',
        'selesai' => 'bg-success',
        'dibatalkan' => 'bg-danger',
    ];
    // Hasil & metode dari penilaian MUK.
    $hasilBadge = [
        'selesai_kompeten' => ['bg-success', 'Kompeten'],
        'selesai_belum_kompeten' => ['bg-danger', 'Belum Kompeten'],
    ];
    $metodeLabel = ['kt' => 'KT', 'tpd' => 'TPD', 'vp' => 'VP'];
@endphp

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Asesmen',
            'title' => 'Daftar Asesmen',
            'action' => null,
        ])
        <!-- end page title -->
        <div class="container-fluid">


            <div class="page-content-wrapper">

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show mx-3 mt-3" role="alert">
                        <i class="mdi mdi-check-circle-outline me-1"></i>
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show mx-3 mt-3" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-1"></i>
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif


                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">

                                <form action="{{ route('asesmen.index') }}" method="GET" class="row g-2 mb-3">
                                    <div class="col-auto">
                                        <select name="status" class="form-select">
                                            <option value="">-- Semua Status --</option>
                                            @foreach (['terjadwal', 'berlangsung', 'selesai', 'dibatalkan'] as $status)
                                                <option value="{{ $status }}"
                                                    {{ request('status') == $status ? 'selected' : '' }}>
                                                    {{ ucfirst($status) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select name="hasil" class="form-select">
                                            <option value="">-- Semua Hasil --</option>
                                            <option value="kompeten" {{ request('hasil') === 'kompeten' ? 'selected' : '' }}>Kompeten</option>
                                            <option value="belum_kompeten" {{ request('hasil') === 'belum_kompeten' ? 'selected' : '' }}>Belum Kompeten</option>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-outline-secondary">
                                            <i class="mdi mdi-filter-variant me-1"></i> Filter
                                        </button>
                                    </div>
                                    @if (request()->filled('status') || request()->filled('hasil'))
                                        <div class="col-auto">
                                            <a href="{{ route('asesmen.index') }}" class="btn btn-light">Reset</a>
                                        </div>
                                    @endif
                                </form>

                                <table class="table table-bordered datatable datatable-buttons">
                                    <thead>
                                        <tr>
                                            <th class="dt-no text-center">No</th>
                                            <th>Kode Pendaftaran</th>
                                            <th>Asesi</th>
                                            <th>Skema Sertifikasi</th>
                                            <th>Asesor</th>
                                            <th>TUK</th>
                                            <th>Tanggal &amp; Waktu</th>
                                            <th>Metode</th>
                                            <th>Status</th>
                                            <th>Hasil</th>
                                            <th class="dt-action">Aksi</th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        @forelse ($jadwals as $item)
                                            <tr>
                                                <td></td>
                                                <td width="120">{{ $item->pendaftaran->kode_pendaftaran ?? '-' }}</td>
                                                <td>{{ $item->pendaftaran->asesi->name ?? '-' }}</td>
                                                <td>{{ $item->pendaftaran->skemaSertifikasi->title['id'] ?? '-' }}</td>
                                                <td>{{ $item->asesor->nama ?? '-' }}</td>
                                                <td>{{ $item->tuk->title ?? '-' }}</td>
                                                <td>
                                                    {{ $item->tanggal_asesmen?->format('d M Y') }}
                                                    <div class="text-muted small">
                                                        {{ \Illuminate\Support\Str::limit($item->waktu_mulai, 5, '') }}
                                                        @if ($item->waktu_selesai)
                                                            - {{ \Illuminate\Support\Str::limit($item->waktu_selesai, 5, '') }}
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>{{ $metodeLabel[$item->penilaianMuk?->metode_asesmen] ?? '-' }}</td>
                                                <td>
                                                    <span class="badge {{ $statusBadge[$item->status] ?? 'bg-secondary' }}">
                                                        {{ ucfirst($item->status) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if ($hasil = $hasilBadge[$item->penilaianMuk?->status] ?? null)
                                                        <span class="badge {{ $hasil[0] }}">{{ $hasil[1] }}</span>
                                                        <div class="text-muted small">{{ $item->penilaianMuk->selesai_at?->format('d M Y') }}</div>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('asesmen.show', $item->id) }}"
                                                        class="btn btn-outline-secondary btn-sm" title="Detail">
                                                        <i class="dripicons-preview"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="11" class="text-center">Belum ada jadwal asesmen.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>


                            </div>
                        </div>
                    </div> <!-- end col -->
                </div> <!-- end row -->


            </div>


        </div> <!-- container-fluid -->
    </div>
@endsection

@push('scripts')
    <!-- Required datatable js -->
    <script src="{{ asset('resources/assets/libs/datatables.net/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('resources/assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js') }}"></script>

    <!-- Responsive examples -->
    <script src="{{ asset('resources/assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('resources/assets/libs/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js') }}">
    </script>

    <!-- Datatable init js -->
    <script src="{{ asset('resources/assets/js/pages/datatablescustom.init.js') }}"></script>
@endpush

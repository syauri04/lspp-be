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

@section('title', 'Pendaftaran')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Pendaftaran',
            'title' => 'Sertifikasi',
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



                                <table class="table table-bordered datatable datatable-buttons">
                                    <thead>
                                        <tr>
                                            <th class="dt-no text-center">No</th>
                                            <th>Kode Pendaftaran</th>
                                            <th>Asesi</th>
                                            <th>Skema Sertifikasi</th>
                                            <th>Status</th>
                                            <th>Tanggal Submit</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        @forelse ($pendaftaran as $item)
                                            <tr>
                                                <td></td>
                                                <td width="120">{{ $item->kode_pendaftaran }}</td>
                                                <td>{{ $item->asesi->name ?? '-' }}</td>
                                                <td>{{ $item->skemaSertifikasi->title['id'] ?? '-' }}</td>
                                                <td>
                                                    <span>{{ ucwords(str_replace('_', ' ', $item->status)) }}</span>
                                                </td>
                                                <td>{{ $item->submitted_at?->format('d M Y, H:i') ?? '-' }}</td>
                                                <td>
                                                    <a href="{{ route('pendaftaran.show', $item->kode_pendaftaran) }}"
                                                        class="btn btn-outline-secondary btn-sm" title="Detail">
                                                        <i class="dripicons dripicons-preview"></i>
                                                    </a>

                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6">Belum ada pendaftaran.</td>
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

    <script>
        function confirmDelete() {
            return confirm(
                'Data ini akan dihapus secara permanen.\n\nApakah Anda yakin?'
            );
        }
    </script>
@endpush

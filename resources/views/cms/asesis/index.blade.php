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

@section('title', 'Asesis')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Content Website',
            'subtitle' => 'Asesis',
            'title' => 'List',
            'action' => request()->is('asesis')
                ? [
                    'label' => 'Tambah Asesi',
                    'url' => route('asesis.create'),
                    'icon' => 'mdi mdi-plus-circle',
                    'type' => 'success',
                ]
                : null,
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

                                            <th>Nama</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th class="dt-action">Aksi</th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        @forelse ($asesi as $index => $p)
                                            <tr>
                                                <td></td>



                                                <td>
                                                    {{ $p->name ?? '-' }}
                                                </td>

                                                <td>
                                                    {{ $p->email ?? '-' }}
                                                </td>

                                                <td>
                                                    @php

                                                        if ($p->hasVerifiedEmail()) {
                                                            $badgeClass = 'bg-success';
                                                            $stat = 'Verified';
                                                        } else {
                                                            $badgeClass = 'bg-warning';
                                                            $stat = 'Belum Verified';
                                                        }
                                                    @endphp

                                                    <span class="badge {{ $badgeClass }}">
                                                        {{ $stat ?? '-' }}
                                                    </span>

                                                    @if ($p->nonaktif())
                                                        <span class="badge bg-danger"
                                                            title="Dinonaktifkan {{ $p->nonaktif_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}">
                                                            Nonaktif
                                                        </span>
                                                    @endif

                                                    @if ($p->pendaftaran_sertifikasi_count > 0)
                                                        <span class="badge bg-info">
                                                            {{ $p->pendaftaran_sertifikasi_count }} pendaftaran
                                                        </span>
                                                    @endif
                                                </td>

                                                <td class="text-center">
                                                    <div class="d-inline-flex gap-1">
                                                        {{-- EDIT: disembunyikan sementara (AsesiController@edit belum diimplementasikan) --}}
                                                        {{--
                                                        <a href="{{ route('asesis.edit', $p->id) }}"
                                                            class="btn btn-outline-secondary btn-sm" title="Edit">
                                                            <i class="fas fa-pencil-alt"></i>
                                                        </a>
                                                        --}}

                                                        {{-- NONAKTIFKAN / AKTIFKAN --}}
                                                        @if ($p->nonaktif())
                                                            <form action="{{ route('asesis.aktifkan', $p->id) }}" method="POST"
                                                                class="d-inline"
                                                                onsubmit="return confirm('Aktifkan kembali akun asesi ini?')">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="btn btn-outline-success btn-sm"
                                                                    title="Aktifkan">
                                                                    <i class="fas fa-user-check"></i>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <form action="{{ route('asesis.nonaktifkan', $p->id) }}" method="POST"
                                                                class="d-inline"
                                                                onsubmit="return confirm('Nonaktifkan akun asesi ini?\n\nAsesi tidak dapat login ke website maupun MUK dan semua sesi login akan diakhiri. Data pendaftaran & asesmen tetap tersimpan.')">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="btn btn-outline-warning btn-sm"
                                                                    title="Nonaktifkan">
                                                                    <i class="fas fa-user-slash"></i>
                                                                </button>
                                                            </form>
                                                        @endif

                                                        {{-- DELETE: hanya untuk asesi yang belum pernah mendaftar --}}
                                                        @if ($p->pendaftaran_sertifikasi_count === 0)
                                                            <form action="{{ route('asesis.destroy', $p->id) }}" method="POST"
                                                                class="d-inline" onsubmit="return confirmDelete()">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                                                    title="Hapus">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <span class="d-inline-block" tabindex="0"
                                                                title="Tidak dapat dihapus: asesi memiliki {{ $p->pendaftaran_sertifikasi_count }} pendaftaran. Gunakan Nonaktifkan.">
                                                                <button type="button" class="btn btn-outline-danger btn-sm" disabled>
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </td>

                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center">Tidak ada Asesi</td>
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

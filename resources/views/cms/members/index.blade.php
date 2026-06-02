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

@section('title', 'Anggota')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Content Website',
            'subtitle' => 'Stuktur Organisasi ',
            'title' => 'Anggota',
            'action' => request()->is('organizational/members')
                ? [
                    'label' => 'Tambah Anggota',
                    'url' => route('members.create'),
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
                                            <th>Photo</th>
                                            <th>Nama</th>
                                            <th>Divisi</th>
                                            <th>Sort Order</th>
                                            <th>Status</th>
                                            <th class="dt-action">Aksi</th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        @forelse ($members as $index => $p)
                                            <tr>
                                                <td></td>

                                                <td width="120">

                                                    @if ($p->photo)
                                                        <img src="{{ asset($p->photo) }}" width="100">
                                                    @endif

                                                </td>

                                                <td>
                                                    {{ $p->name ?? '-' }}
                                                </td>

                                                <td>
                                                    {{ $p->division->name['id'] ?? '-' }}
                                                </td>

                                                <td>
                                                    {{ $p->sort_order ?? 0 }}
                                                </td>

                                                <td>
                                                    @php

                                                        if ($p->is_active == 0) {
                                                            $badgeClass = 'bg-warning';
                                                            $stat = 'Inactive';
                                                        } elseif ($p->is_active == 1) {
                                                            $badgeClass = 'bg-success';
                                                            $stat = 'Active';
                                                        }
                                                    @endphp

                                                    <span class="badge {{ $badgeClass }}">
                                                        {{ $stat ?? '-' }}
                                                    </span>

                                                </td>

                                                <td class="text-center">
                                                    <div class="d-inline-flex gap-1">
                                                        {{-- EDIT --}}
                                                        <a href="{{ route('members.edit', $p->id) }}"
                                                            class="btn btn-outline-secondary btn-sm" title="Edit">
                                                            <i class="fas fa-pencil-alt"></i>
                                                        </a>

                                                        {{-- DELETE --}}
                                                        <form action="{{ route('members.destroy', $p->id) }}"
                                                            method="POST" class="d-inline"
                                                            onsubmit="return confirmDelete()">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-outline-danger btn-sm"
                                                                title="Hapus">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>

                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center">Tidak ada anggota</td>
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

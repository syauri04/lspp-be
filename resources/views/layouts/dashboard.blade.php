@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'LSPP 306',
            'subtitle' => 'Dashboard',
            'title' => 'Dashboard',
        ])
        <!-- end page title -->
        <div class="container-fluid">
            <div class="page-content-wrapper">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="row">
                            <div class="col-xl-3 col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-center">
                                            <p class="font-size-16">Total Asesi Aktif</p>
                                            <div class="mini-stat-icon mx-auto mb-4 mt-3">
                                                <span class="avatar-title rounded-circle bg-soft-primary">
                                                    <i class="mdi mdi-account-check text-primary font-size-20"></i>
                                                </span>
                                            </div>
                                            <h5 class="font-size-22">128</h5>

                                            <!-- <p class="text-muted">70% Target</p> -->

                                            <div class="progress mt-3" style="height: 4px">
                                                <div class="progress-bar progress-bar bg-primary" role="progressbar"
                                                    style="width: 100%" aria-valuenow="100" aria-valuemin="0"
                                                    aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-center">
                                            <p class="font-size-16">Batch Berjalan</p>
                                            <div class="mini-stat-icon mx-auto mb-4 mt-3">
                                                <span class="avatar-title rounded-circle bg-soft-success">
                                                    <i class="mdi mdi-progress-check text-success font-size-20"></i>
                                                </span>
                                            </div>
                                            <h5 class="font-size-22">6</h5>

                                            <!-- <p class="text-muted">80% Target</p> -->

                                            <div class="progress mt-3" style="height: 4px">
                                                <div class="progress-bar progress-bar bg-success" role="progressbar"
                                                    style="width: 100%" aria-valuenow="100" aria-valuemin="0"
                                                    aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-center">
                                            <p class="font-size-16">Dokumen Pending</p>
                                            <div class="mini-stat-icon mx-auto mb-4 mt-3">
                                                <span class="avatar-title rounded-circle bg-soft-primary">
                                                    <i class="mdi mdi-account-clock text-primary font-size-20"></i>
                                                </span>
                                            </div>
                                            <h5 class="font-size-22">23</h5>

                                            <!-- <p class="text-muted">70% Target</p> -->

                                            <div class="progress mt-3" style="height: 4px">
                                                <div class="progress-bar progress-bar bg-primary" role="progressbar"
                                                    style="width: 100%" aria-valuenow="100" aria-valuemin="0"
                                                    aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-center">
                                            <p class="font-size-16">Sertifikat Terbit</p>
                                            <div class="mini-stat-icon mx-auto mb-4 mt-3">
                                                <span class="avatar-title rounded-circle bg-soft-success">
                                                    <i class="mdi mdi-file-document text-success font-size-20"></i>
                                                </span>
                                            </div>
                                            <h5 class="font-size-22">64</h5>

                                            <!-- <p class="text-muted">80% Target</p> -->

                                            <div class="progress mt-3" style="height: 4px">
                                                <div class="progress-bar progress-bar bg-success" role="progressbar"
                                                    style="width: 100%" aria-valuenow="100" aria-valuemin="0"
                                                    aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="header-title mb-4">
                                    Jadwal Assesment Terdekat
                                </h4>

                                <ul class="list-unstyled activity-wid mb-0">
                                    <li class="activity-list activity-border">
                                        <div class="activity-icon avatar-sm">
                                            <span class="avatar-title bg-soft-primary text-primary rounded-circle">
                                                <i class="mdi mdi-file-document font-size-16"></i>
                                            </span>
                                        </div>
                                        <div class="media">
                                            <div class="me-3">
                                                <h5 class="font-size-14 mb-1">
                                                    Sertifikasi Melaksanakan Konsultasi Perencanaan
                                                    Destinasi Parawisata
                                                </h5>
                                                <p class="text-muted font-size-12 mb-0">
                                                    29 February 2026
                                                </p>
                                            </div>

                                            <div class="media-body">
                                                <div class="text-end d-none d-md-block" style="width: 65px">
                                                    <p class="text-muted font-size-13 mt-2 pt-1 mb-0">
                                                        <i class="mdi mdi-timer-outline font-size-15 text-primary"></i>
                                                        1 days
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="activity-list activity-border">
                                        <div class="activity-icon avatar-sm">
                                            <span class="avatar-title bg-soft-primary text-primary rounded-circle">
                                                <i class="mdi mdi-file-document font-size-16"></i>
                                            </span>
                                        </div>
                                        <div class="media">
                                            <div class="me-3">
                                                <h5 class="font-size-14 mb-1">
                                                    Sertifikasi Melaksanakan Konsultasi Perencanaan
                                                    Destinasi Parawisata
                                                </h5>
                                                <p class="text-muted font-size-12 mb-0">
                                                    29 February 2026
                                                </p>
                                            </div>

                                            <div class="media-body">
                                                <div class="text-end d-none d-md-block" style="width: 65px">
                                                    <p class="text-muted font-size-13 mt-2 pt-1 mb-0">
                                                        <i class="mdi mdi-timer-outline font-size-15 text-primary"></i>
                                                        1 days
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="activity-list activity-border">
                                        <div class="activity-icon avatar-sm">
                                            <span class="avatar-title bg-soft-primary text-primary rounded-circle">
                                                <i class="mdi mdi-file-document font-size-16"></i>
                                            </span>
                                        </div>
                                        <div class="media">
                                            <div class="me-3">
                                                <h5 class="font-size-14 mb-1">
                                                    Sertifikasi Melaksanakan Konsultasi Perencanaan
                                                    Destinasi Parawisata
                                                </h5>
                                                <p class="text-muted font-size-12 mb-0">
                                                    29 February 2026
                                                </p>
                                            </div>

                                            <div class="media-body">
                                                <div class="text-end d-none d-md-block" style="width: 65px">
                                                    <p class="text-muted font-size-13 mt-2 pt-1 mb-0">
                                                        <i class="mdi mdi-timer-outline font-size-15 text-primary"></i>
                                                        1 days
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="header-title mb-4">Sertifikasi Pending</h4>
                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Sertifikasi</th>
                                                <th>Tujuan Asesi</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>#2356</td>
                                                <td>
                                                    Melaksanakan Konsultasi Perencanaan Destinasi
                                                    Parawisata
                                                </td>
                                                <td>Sertifikasi</td>
                                                <td>
                                                    <span
                                                        class="badge badge-pill badge-soft-primary font-size-13">Pending</span>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td>#2357</td>
                                                <td>
                                                    Melaksanakan Konsultasi Perencanaan Destinasi
                                                    Parawisata
                                                </td>
                                                <td>Sertifikasi</td>
                                                <td>
                                                    <span
                                                        class="badge badge-pill badge-soft-primary font-size-13">Pending</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>#2357</td>
                                                <td>
                                                    Melaksanakan Konsultasi Perencanaan Destinasi
                                                    Parawisata
                                                </td>
                                                <td>Sertifikasi</td>
                                                <td>
                                                    <span
                                                        class="badge badge-pill badge-soft-primary font-size-13">Pending</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>#2357</td>
                                                <td>
                                                    Melaksanakan Konsultasi Perencanaan Destinasi
                                                    Parawisata
                                                </td>
                                                <td>Sertifikasi</td>
                                                <td>
                                                    <span
                                                        class="badge badge-pill badge-soft-primary font-size-13">Pending</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>#2357</td>
                                                <td>
                                                    Melaksanakan Konsultasi Perencanaan Destinasi
                                                    Parawisata
                                                </td>
                                                <td>Sertifikasi</td>
                                                <td>
                                                    <span
                                                        class="badge badge-pill badge-soft-primary font-size-13">Pending</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- end table-responsive -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


@push('scripts')
    @vite('resources/js/app.js')
@endpush

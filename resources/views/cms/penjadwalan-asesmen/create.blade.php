@extends('layouts.app')

@push('styles')
    <link href="{{ asset('resources/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('title', 'Penjadwalan Asesmen')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Penjadwalan Asesmen',
            'title' => 'Tentukan Asesor & Jadwal',
            'action' => null,
        ])
        <!-- end page title -->
        <div class="container-fluid">

            <div class="page-content-wrapper">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row">
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Informasi Pendaftaran</h5>

                                <table class="table table-borderless table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <th style="width:150px;">Kode Pendaftaran</th>
                                            <td>{{ $pendaftaran->kode_pendaftaran }}</td>
                                        </tr>
                                        <tr>
                                            <th>Asesi</th>
                                            <td>{{ $pendaftaran->asesi->name ?? '-' }}
                                                <div class="text-muted small">{{ $pendaftaran->asesi->email ?? '-' }}
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Skema Sertifikasi</th>
                                            <td>{{ $pendaftaran->skemaSertifikasi->title['id'] ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Harga</th>
                                            <td>Rp {{ number_format($pendaftaran->harga_snapshot, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Submit</th>
                                            <td>{{ $pendaftaran->submitted_at?->format('d M Y, H:i') ?? '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Tentukan Asesor & Jadwal</h5>

                                <form action="{{ route('penjadwalan-asesmen.store', $pendaftaran->kode_pendaftaran) }}"
                                    method="POST" class="needs-validation" novalidate>
                                    @csrf

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="asesor_id" class="form-label">Asesor</label>
                                                <select class="form-select" id="asesor_id" name="asesor_id" required>
                                                    <option value="">-- Pilih Asesor --</option>
                                                    @foreach ($asesors as $asesor)
                                                        <option value="{{ $asesor->id }}"
                                                            {{ old('asesor_id') == $asesor->id ? 'selected' : '' }}>
                                                            {{ $asesor->nama }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <div class="invalid-feedback">
                                                    Please select a valid asesor.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="tuk_id" class="form-label">TUK</label>
                                                <select class="form-select" id="tuk_id" name="tuk_id" required>
                                                    <option value="">-- Pilih TUK --</option>
                                                    @foreach ($tuks as $tuk)
                                                        <option value="{{ $tuk->id }}"
                                                            {{ old('tuk_id') == $tuk->id ? 'selected' : '' }}>
                                                            {{ $tuk->title }} @if ($tuk->city)
                                                                ({{ $tuk->city }})
                                                            @endif
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <div class="invalid-feedback">
                                                    Please select a valid TUK.
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="tanggal_asesmen" class="form-label">Tanggal Asesmen</label>
                                                <input type="date" name="tanggal_asesmen" class="form-control"
                                                    id="tanggal_asesmen" min="{{ date('Y-m-d') }}"
                                                    value="{{ old('tanggal_asesmen') }}" required>

                                                <div class="invalid-feedback">
                                                    Please choose a valid date.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="waktu_mulai" class="form-label">Waktu Mulai</label>
                                                <input type="time" name="waktu_mulai" class="form-control"
                                                    id="waktu_mulai" value="{{ old('waktu_mulai') }}" required>

                                                <div class="invalid-feedback">
                                                    Please choose a start time.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="waktu_selesai" class="form-label">Waktu Selesai</label>
                                                <input type="time" name="waktu_selesai" class="form-control"
                                                    id="waktu_selesai" value="{{ old('waktu_selesai') }}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="catatan" class="form-label">Catatan (opsional)</label>
                                                <textarea name="catatan" class="form-control" id="catatan" rows="3"
                                                    placeholder="Catatan untuk asesor / asesi">{{ old('catatan') }}</textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button class="btn btn-primary" type="submit"
                                            onclick="return confirm('Simpan jadwal asesmen ini?')">
                                            Simpan Jadwal
                                        </button>
                                        <a href="{{ route('penjadwalan-asesmen.index') }}"
                                            class="btn btn-light">Batal</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->
            </div>


        </div> <!-- container-fluid -->
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('resources/assets/libs/parsleyjs/parsley.min.js') }}"></script>
    <script src="{{ asset('resources/assets/js/pages/form-validation.init.js') }}"></script>
@endpush

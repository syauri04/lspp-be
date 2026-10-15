@extends('layouts.app')

@push('styles')
    <link href="{{ asset('resources/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
@endpush


@section('title', 'Asesor')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Users',
            'subtitle' => 'Asesor',
            'title' => $asesor->exists ? 'Edit Asesor' : 'Input Asesor',
            'action' => null,
        ])
        <!-- end page title -->
        <div class="container-fluid">

            <div class="page-content-wrapper">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <form action="{{ $action }}" method="POST" enctype="multipart/form-data"
                                    class="needs-validation" novalidate>
                                    @csrf

                                    @if ($method !== 'POST')
                                        @method($method)
                                    @endif
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="nama" class="form-label">Nama</label>
                                                <input type="text" name="nama" class="form-control" id="nama"
                                                    placeholder="Nama" value="{{ old('nama', $asesor->nama ?? '') }}"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" name="email" class="form-control" id="email"
                                                    placeholder="Email" value="{{ old('email', $asesor->email ?? '') }}"
                                                    required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="nomor_registrasi" class="form-label">No Registrasi</label>
                                                <input type="text" name="nomor_registrasi" class="form-control"
                                                    id="nomor_registrasi" placeholder="No Registrasi"
                                                    value="{{ old('nomor_registrasi', $asesor->nomor_registrasi ?? '') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="no_telepon" class="form-label">No Telepon</label>
                                                <input type="text" name="no_telepon" class="form-control" id="no_telepon"
                                                    placeholder="No Telepon"
                                                    value="{{ old('no_telepon', $asesor->no_telepon ?? '') }}">
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ================= Password ================= --}}
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="password" class="form-label">
                                                    Password
                                                    @if ($asesor->exists)
                                                        <span class="text-muted small">(kosongkan jika tidak ingin
                                                            mengubah)</span>
                                                    @endif
                                                </label>
                                                <div class="input-group">
                                                    <input type="text" name="password" class="form-control"
                                                        id="password" placeholder="Password" autocomplete="new-password"
                                                        {{ $asesor->exists ? '' : 'required' }}>
                                                    <button class="btn btn-outline-secondary" type="button"
                                                        id="btn-generate-password">
                                                        Generate
                                                    </button>
                                                </div>
                                                <div class="form-text">Minimal 8 karakter.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="password_confirmation" class="form-label">Konfirmasi
                                                    Password</label>
                                                <input type="text" name="password_confirmation" class="form-control"
                                                    id="password_confirmation" placeholder="Konfirmasi Password"
                                                    autocomplete="new-password" {{ $asesor->exists ? '' : 'required' }}>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ================= Image & Status ================= --}}
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Upload Photo</label>

                                                <input type="file" class="form-control file-input" name="photo"
                                                    accept="image/*" data-preview="photo">

                                                <div id="preview-photo" class="mt-2 d-none">
                                                    <img src="" alt="Preview Photo" class="img-fluid rounded"
                                                        style="max-height:200px;">
                                                </div>

                                                @if (!empty($asesor->photo))
                                                    <div class="mt-2">
                                                        <!-- Preview photo lama -->
                                                        <img src="{{ asset($asesor->photo) }}" alt="Preview Photo Lama"
                                                            class="img-fluid rounded" style="max-height:200px;">
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="validationCustom01" class="form-label">Status
                                                </label>
                                                <select class="form-select" id="statuswew" name="status">
                                                    <option value="aktif"
                                                        {{ old('status', $asesor->status ?? '') == 'aktif' ? 'selected' : '' }}>
                                                        Aktif
                                                    </option>
                                                    <option value="nonaktif"
                                                        {{ old('status', $asesor->status ?? '') == 'nonaktif' ? 'selected' : '' }}>
                                                        Nonaktif
                                                    </option>
                                                </select>
                                            </div>
                                        </div>


                                    </div>

                                    <div>
                                        <button class="btn btn-primary" type="submit">Submit</button>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            document.querySelectorAll('.file-input').forEach(input => {
                input.addEventListener('change', function() {

                    const key = this.dataset.preview; // dokumen / abstraks
                    if (!key) return;

                    const preview = document.getElementById(`preview-${key}`);
                    if (!preview) return;

                    const nameSpan = preview.querySelector('.file-name');
                    if (!nameSpan) return;

                    if (this.files && this.files.length > 0) {
                        nameSpan.textContent = this.files[0].name;
                        preview.classList.remove('d-none');
                    } else {
                        preview.classList.add('d-none');
                    }
                });
            });

            // Generate password random, isi otomatis ke field Password + Konfirmasi Password
            const btnGenerate = document.getElementById('btn-generate-password');
            if (btnGenerate) {
                btnGenerate.addEventListener('click', function() {
                    const password = generateRandomPassword(12);
                    document.getElementById('password').value = password;
                    document.getElementById('password_confirmation').value = password;
                });
            }

            function generateRandomPassword(length) {
                const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // tanpa I, O biar tidak rancu
                const lower = 'abcdefghijkmnopqrstuvwxyz'; // tanpa l
                const numbers = '23456789'; // tanpa 0, 1
                const symbols = '!@#$%';
                const all = upper + lower + numbers + symbols;

                // Pastikan minimal 1 karakter dari tiap jenis, sisanya random dari gabungan semua
                let password = [
                    upper[Math.floor(Math.random() * upper.length)],
                    lower[Math.floor(Math.random() * lower.length)],
                    numbers[Math.floor(Math.random() * numbers.length)],
                    symbols[Math.floor(Math.random() * symbols.length)],
                ];

                for (let i = password.length; i < length; i++) {
                    password.push(all[Math.floor(Math.random() * all.length)]);
                }

                // Acak urutan supaya karakter wajib di atas tidak selalu di posisi awal
                return password.sort(() => Math.random() - 0.5).join('');
            }
        });
    </script>
@endpush

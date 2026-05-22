@extends('layouts.app')

@push('styles')
    <link href="{{ Vite::asset('resources/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
@endpush


@section('title', 'Divisi')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Content Website',
            'subtitle' => 'Struktur Organisasi',
            'title' => 'Input Anggota',
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
                                                <label for="division_id" class="form-label">Divisi</label>
                                                <select class="form-select" id="division_id" name="division_id" required>
                                                    <option value="">-- Pilih Divisi --</option>
                                                    @foreach ($divisions as $divisi)
                                                        <option value="{{ $divisi->id }}"
                                                            {{ old('division_id', $data->division_id ?? '') == $divisi->id ? 'selected' : '' }}>
                                                            {{ $divisi->name['id'] }}
                                                        </option>
                                                    @endforeach
                                                </select>


                                                <div class="invalid-feedback">
                                                    Please select a valid divisi.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="name_en" class="form-label">Nama</label>
                                                <input type="text" name="name" class="form-control" id="name"
                                                    placeholder="Nama" value="{{ old('name', $data->name ?? '') }}"
                                                    required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="position_id" class="form-label">Jabatan ID</label>
                                                <input type="text" name="position_id" class="form-control"
                                                    id="position_id" placeholder="Jabatan ID"
                                                    value="{{ old('position_id', $data->position['id'] ?? '') }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="position_en" class="form-label">Jabatan EN</label>
                                                <input type="text" name="position_en" class="form-control"
                                                    id="position_en" placeholder="Jabatan EN"
                                                    value="{{ old('position_en', $data->position['en'] ?? '') }}" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="linkedin_url" class="form-label">Url Linkedin</label>
                                                <input type="text" name="linkedin_url" class="form-control"
                                                    id="linkedin_url" placeholder="Url Linkedin"
                                                    value="{{ old('linkedin_url', $data->linkedin_url ?? '') }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="instagram_url" class="form-label">Url Instagram</label>
                                                <input type="text" name="instagram_url" class="form-control"
                                                    id="instagram_url" placeholder="Url Instagram"
                                                    value="{{ old('instagram_url', $data->instagram_url ?? '') }}"
                                                    required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Upload Photo</label>

                                                <input type="file" class="form-control file-input" name="photo"
                                                    accept="image/*" data-preview="image">

                                                <div id="preview-image" class="mt-2 d-none">
                                                    <img src="" alt="Preview Image" class="img-fluid rounded"
                                                        style="max-height:200px;">
                                                </div>

                                                @if (!empty($data->photo))
                                                    <div class="mt-2">
                                                        <!-- Preview image lama -->
                                                        <img src="{{ asset($data->photo) }}" alt="Preview Image Lama"
                                                            class="img-fluid rounded" style="max-height:200px;">
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="sort_order" class="form-label">Sort Order</label>
                                                <input type="number" name="sort_order" class="form-control"
                                                    id="sort_order" placeholder="Sort Order"
                                                    value="{{ old('sort_order', $data->sort_order ?? 0) }}" />
                                            </div>
                                        </div>



                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="validationCustom01" class="form-label">Status
                                                </label>
                                                <select class="form-select" id="is_active" name="is_active">
                                                    <option value="1"
                                                        {{ old('is_active', $data->is_active ?? '') == '1' ? 'selected' : '' }}>
                                                        Active
                                                    </option>
                                                    <option value="0"
                                                        {{ old('is_active', $data->is_active ?? '') == '0' ? 'selected' : '' }}>
                                                        Inactive
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
    <script src="{{ Vite::asset('resources/assets/libs/parsleyjs/parsley.min.js') }}"></script>

    <script src="{{ Vite::asset('resources/assets/js/pages/form-validation.init.js') }}"></script>

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

        });
    </script>
@endpush

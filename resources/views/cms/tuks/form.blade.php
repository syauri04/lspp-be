@extends('layouts.app')

@push('styles')
    <link href="{{ asset('resources/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
@endpush


@section('title', 'Divisi')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Content Website',
            'subtitle' => 'Lokasi TUK',
            'title' => 'Input Lokasi TUK',
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
                                                <label for="title" class="form-label">Title</label>
                                                <input type="text" name="title" class="form-control" id="title"
                                                    placeholder="Title" value="{{ old('title', $data->title ?? '') }}"
                                                    required>

                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="city" class="form-label">Kota</label>
                                                <input type="text" name="city" class="form-control" id="city"
                                                    placeholder="Kota" value="{{ old('city', $data->city ?? '') }}"
                                                    required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="address" class="form-label">Alamat</label>
                                                <input type="text" name="address" class="form-control" id="address"
                                                    placeholder="Alamat" value="{{ old('address', $data->address ?? '') }}"
                                                    required>

                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="google_maps_url" class="form-label">Link Map</label>
                                                <input type="text" name="google_maps_url" class="form-control"
                                                    id="google_maps_url" placeholder="Link Map"
                                                    value="{{ old('google_maps_url', $data->google_maps_url ?? '') }}"
                                                    required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="open_days" class="form-label">Open Days</label>
                                                <input type="text" name="open_days" class="form-control" id="open_days"
                                                    placeholder="ex: Senin - Jumat"
                                                    value="{{ old('open_days', $data->open_days ?? '') }}" required>

                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="open_hours" class="form-label">Open Hours</label>
                                                <input type="text" name="open_hours" class="form-control" id="open_hours"
                                                    placeholder="ex: 08:00 - 20:00"
                                                    value="{{ old('open_hours', $data->open_hours ?? '') }}" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
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

        });
    </script>
@endpush

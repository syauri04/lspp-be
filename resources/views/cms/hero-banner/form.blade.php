@extends('layouts.app')

@push('styles')
    <link href="{{ asset('resources/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
@endpush


@section('title', 'Hero Banner')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Content Website',
            'subtitle' => 'Hero Banner',
            'title' => 'Input Hero Banner',
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
                                                <label for="title_id" class="form-label">Title ID</label>
                                                <input type="text" name="title_id" class="form-control" id="title_id"
                                                    placeholder="Title ID"
                                                    value="{{ old('title_id', $data->title['id'] ?? '') }}" required>

                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="title_en" class="form-label">Title EN</label>
                                                <input type="text" name="title_en" class="form-control" id="title_en"
                                                    placeholder="Title EN"
                                                    value="{{ old('title_en', $data->title['en'] ?? '') }}" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="summary_id" class="form-label">Summary ID</label>
                                                <textarea required class="form-control" name="summary_id" rows="2" placeholder="Summary ID">{{ old('summary_id', $data->summary['id'] ?? '') }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="summary_en" class="form-label">Summary EN</label>
                                                <textarea required class="form-control" name="summary_en" rows="2" placeholder="Summary EN">{{ old('summary_en', $data->summary['en'] ?? '') }}</textarea>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ================= Image ================= --}}
                                    <div class="row">
                                        <div class="col-md-6">


                                            <div class="mb-3">
                                                <label class="form-label">Upload Image</label>

                                                <input type="file" class="form-control file-input" name="image"
                                                    accept="image/*" data-preview="image">

                                                <div id="preview-image" class="mt-2 d-none">
                                                    <img src="" alt="Preview Image" class="img-fluid rounded"
                                                        style="max-height:200px;">
                                                </div>

                                                @if (!empty($data->image))
                                                    <div class="mt-2">
                                                        <!-- Preview image lama -->
                                                        <img src="{{ asset($data->image) }}" alt="Preview Image Lama"
                                                            class="img-fluid rounded" style="max-height:200px;">
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="validationCustom01" class="form-label">Status
                                                </label>
                                                <select class="form-select" id="is_active" name="is_active">
                                                    <option value="1"
                                                        {{ old('is_active', $data->is_active ?? '') == '1' ? 'selected' : '' }}>
                                                        Publish
                                                    </option>
                                                    <option value="0"
                                                        {{ old('is_active', $data->is_active ?? '') == '0' ? 'selected' : '' }}>
                                                        Draft
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

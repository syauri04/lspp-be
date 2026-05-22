@extends('layouts.app')

@push('styles')
    {{-- <link href="{{ Vite::asset('resources/assets/libs/summernote/summernote-bs4.min.css') }}" rel="stylesheet"
        type="text/css" /> --}}
@endpush


@section('title', 'About Page')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Content Website',
            'subtitle' => 'Tentang Kami',
            'title' => 'Form',
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

                                <form action="{{ route('about-page.update') }}" method="POST" enctype="multipart/form-data"
                                    class="needs-validation" novalidate>
                                    @csrf


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
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label>Description Home ID</label>
                                                <div>
                                                    <textarea class="editor" name="desc_home_id">{{ old('desc_home_id', $data->desc_home['id'] ?? '') }}</textarea>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label>Description Home EN</label>
                                                <div>
                                                    <textarea class="editor" name="desc_home_en">{{ old('desc_home_en', $data->desc_home['en'] ?? '') }}</textarea>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label>Description Detail ID</label>
                                                <div>
                                                    <textarea class="editor" name="desc_detail_id">{{ old('desc_detail_id', $data->desc_detail['id'] ?? '') }}</textarea>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label>Description Detail EN</label>
                                                <div>
                                                    <textarea class="editor" name="desc_detail_en">{{ old('desc_detail_en', $data->desc_detail['en'] ?? '') }}</textarea>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label>Visi ID</label>
                                                <div>
                                                    <textarea class="editor" name="vision_id">{{ old('vision_id', $data->vision['id'] ?? '') }}</textarea>

                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label>Visi EN</label>
                                                <div>
                                                    <textarea class="editor" name="vision_en">{{ old('vision_en', $data->vision['en'] ?? '') }}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label>Misi ID</label>
                                                <div>
                                                    <textarea class="editor" name="mission_id">{{ old('mission_id', $data->mission['id'] ?? '') }}</textarea>

                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label>Misi EN</label>
                                                <div>
                                                    <textarea class="editor" name="mission_en">{{ old('mission_en', $data->mission['en'] ?? '') }}</textarea>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Upload Image Visi</label>

                                                <input type="file" class="form-control file-input" name="image_vision"
                                                    accept="image/*" data-preview="image_vision">

                                                <div id="preview-image-vision" class="mt-2 d-none">
                                                    <img src="" alt="Preview Image" class="img-fluid rounded"
                                                        style="max-height:200px;">
                                                </div>

                                                @if (!empty($data->image_vision))
                                                    <div class="mt-2">
                                                        <!-- Preview image lama -->
                                                        <img src="{{ asset($data->image_vision) }}"
                                                            alt="Preview Image Lama" class="img-fluid rounded"
                                                            style="max-height:200px;">
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Upload Image Misi</label>

                                                <input type="file" class="form-control file-input"
                                                    name="image_mission" accept="image/*" data-preview="image_mission">

                                                <div id="preview-image-mission" class="mt-2 d-none">
                                                    <img src="" alt="Preview Image" class="img-fluid rounded"
                                                        style="max-height:200px;">
                                                </div>

                                                @if (!empty($data->image_mission))
                                                    <div class="mt-2">
                                                        <!-- Preview image lama -->
                                                        <img src="{{ asset($data->image_mission) }}"
                                                            alt="Preview Image Lama" class="img-fluid rounded"
                                                            style="max-height:200px;">
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ================= Image ================= --}}
                                    <div class="row">
                                        <div class="card-body callout callout-info">
                                            <div class="mb-3">
                                                <label class="form-label">Upload Background Image</label>

                                                <input type="file" class="form-control file-input"
                                                    name="background_image" accept="image/*"
                                                    data-preview="background_image">

                                                <div id="preview-background-image" class="mt-2 d-none">
                                                    <img src="" alt="Preview Gambar" class="img-fluid rounded"
                                                        style="max-height:200px;">
                                                </div>

                                                @if (!empty($data->background_image))
                                                    <div class="mt-2">
                                                        <!-- Preview image lama -->
                                                        <img src="{{ asset($data->background_image) }}"
                                                            alt="Preview Gambar Lama" class="img-fluid rounded"
                                                            style="max-height:200px;">
                                                    </div>
                                                @endif
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
    <script src="{{ asset('assets/libs/tinymce/tinymce.min.js') }}"></script>

    <!-- Summernote js -->
    {{-- <script src="{{ Vite::asset('resources/assets/libs/summernote/summernote-bs4.min.js') }}"></script> --}}

    <!-- init js -->

    <script>
        tinymce.init({
            selector: '.editor',
            height: 400,

            plugins: `
            advlist autolink lists link image charmap preview anchor
            searchreplace visualblocks code fullscreen
            insertdatetime media table help wordcount
        `,

            toolbar: `
            undo redo |
            formatselect |
            bold italic underline |
            forecolor backcolor |
            alignleft aligncenter alignright alignjustify |
            bullist numlist outdent indent |
            link image media table |
            code fullscreen preview
        `,

            automatic_uploads: true,

            images_upload_url: '{{ route('tinymce.upload') }}',

            images_upload_credentials: true,

            relative_urls: false,

            remove_script_host: false,

            convert_urls: true,

            file_picker_types: 'image',

            images_reuse_filename: true,

            file_picker_callback: function(callback, value, meta) {

                if (meta.filetype === 'image') {

                    const input = document.createElement('input');

                    input.setAttribute('type', 'file');

                    input.setAttribute('accept', 'image/*');

                    input.onchange = function() {

                        const file = this.files[0];

                        const formData = new FormData();

                        formData.append('file', file);

                        fetch('{{ route('tinymce.upload') }}', {

                                method: 'POST',

                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },

                                body: formData

                            })
                            .then(response => response.json())

                            .then(result => {

                                callback(result.location, {
                                    alt: file.name
                                });

                            })

                            .catch(() => {
                                alert('Upload image gagal');
                            });

                    };

                    input.click();
                }
            }
        });
    </script>





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

@extends('layouts.app')

@push('styles')
    <link href="{{ asset('resources/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
@endpush


@section('title', 'Faqs')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Content Website',
            'subtitle' => 'FAQs',
            'title' => 'Input FAQ',
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
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label>Content ID</label>
                                                <div>
                                                    <textarea class="editor" name="content_id">{{ old('content_id', $data->content['id'] ?? '') }}</textarea>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label>Content EN</label>
                                                <div>
                                                    <textarea class="editor" name="content_en">{{ old('content_en', $data->content['en'] ?? '') }}</textarea>

                                                </div>
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
    <script src="{{ asset('assets/libs/tinymce/tinymce.min.js') }}"></script>

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

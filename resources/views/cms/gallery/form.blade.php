@extends('layouts.app')

@push('styles')
    <link href="{{ asset('resources/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
    <style>
        /* ===== DROP ZONE ===== */
        .photo-dropzone {
            border: 2px dashed #ced4da;
            border-radius: 8px;
            padding: 2rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            background: #f8f9fa;
        }

        .photo-dropzone:hover,
        .photo-dropzone.dragover {
            border-color: #556ee6;
            background: #eef0fb;
        }

        .photo-dropzone i {
            font-size: 2rem;
            color: #adb5bd;
        }

        /* ===== PHOTO ITEM ===== */
        .photo-item {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .07);
        }

        .photo-item .photo-thumb {
            position: relative;
            aspect-ratio: 4/3;
            background: #e9ecef;
            overflow: hidden;
        }

        .photo-item .photo-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .photo-item .sort-no {
            position: absolute;
            top: 6px;
            left: 6px;
            background: rgba(0, 0, 0, .55);
            color: #fff;
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 3px;
        }

        .photo-item .btn-remove-new {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(220, 53, 69, .85);
            color: #fff;
            border: none;
            font-size: 11px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .photo-item .btn-delete-existing {
            position: absolute;
            top: 6px;
            right: 6px;
            background: rgba(220, 53, 69, .85);
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 2px 7px;
            font-size: 11px;
            cursor: pointer;
        }

        .photo-item .caption-area {
            padding: 8px;
        }

        .photo-item .caption-area input {
            font-size: 12px;
        }

        /* ===== GRID ===== */
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
    </style>
@endpush

@section('title', 'Gallery Album')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Content Website',
            'subtitle' => 'Gallery',
            'title' => isset($data) ? 'Edit Gallery Album' : 'Input Gallery Album',
            'action' => null,
        ])

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

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <form id="album-form" action="{{ $action }}" method="POST"
                                    enctype="multipart/form-data">
                                    @csrf
                                    @if ($method !== 'POST')
                                        @method($method)
                                    @endif

                                    {{-- ===== TITLE ===== --}}
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Title ID <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="title_id" class="form-control"
                                                    placeholder="Judul Album"
                                                    value="{{ old('title_id', $data->title['id'] ?? '') }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Title EN <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="title_en" class="form-control"
                                                    placeholder="Album Title"
                                                    value="{{ old('title_en', $data->title['en'] ?? '') }}" required>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ===== SUMMARY ===== --}}
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Summary ID</label>
                                                <textarea class="form-control" name="summary_id" rows="2" placeholder="Deskripsi singkat">{{ old('summary_id', $data->summary['id'] ?? '') }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Summary EN</label>
                                                <textarea class="form-control" name="summary_en" rows="2" placeholder="Short description">{{ old('summary_en', $data->summary['en'] ?? '') }}</textarea>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ===== COVER + META ===== --}}
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    Cover Image @if (!isset($data))
                                                        <span class="text-danger">*</span>
                                                    @endif
                                                </label>
                                                <input type="file" class="form-control" name="cover_image"
                                                    accept="image/*" id="cover-input" {{ !isset($data) ? 'required' : '' }}>
                                                <div id="cover-preview-new" class="mt-2 d-none">
                                                    <img id="cover-preview-img" src="" alt=""
                                                        class="img-fluid rounded" style="max-height:150px;">
                                                </div>
                                                @if (!empty($data->cover_image))
                                                    <div id="cover-preview-existing" class="mt-2">
                                                        <img src="{{ asset($data->cover_image) }}" alt="Cover"
                                                            class="img-fluid rounded" style="max-height:150px;">
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Tanggal Event</label>
                                                <input type="date" name="event_date" class="form-control"
                                                    value="{{ old('event_date', isset($data->event_date) ? \Carbon\Carbon::parse($data->event_date)->format('Y-m-d') : '') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="is_active">
                                                    <option value="1"
                                                        {{ old('is_active', $data->is_active ?? 1) == 1 ? 'selected' : '' }}>
                                                        Publish</option>
                                                    <option value="0"
                                                        {{ old('is_active', $data->is_active ?? 1) == 0 ? 'selected' : '' }}>
                                                        Draft</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    {{-- ===== EXISTING PHOTOS (mode edit) ===== --}}
                                    @if (isset($data) && $data->photos->isNotEmpty())
                                        <div class="mb-4">
                                            <h6 class="fw-semibold mb-1">
                                                Foto Tersimpan
                                                <span class="badge bg-secondary ms-1">{{ $data->photos->count() }}</span>
                                            </h6>
                                            <p class="text-muted small mb-0">Edit caption langsung. Klik 🗑 untuk hapus.</p>

                                            <div class="photos-grid">
                                                @foreach ($data->photos as $photo)
                                                    <div class="photo-item">
                                                        <div class="photo-thumb">
                                                            <img src="{{ asset($photo->image) }}"
                                                                alt="Foto {{ $loop->iteration }}" loading="lazy">
                                                            <span class="sort-no">#{{ $loop->iteration }}</span>
                                                            <button type="submit" class="btn-delete-existing"
                                                                title="Hapus foto"
                                                                form="delete-photo-{{ $photo->id }}">
                                                                <i class="mdi mdi-trash-can-outline"></i>
                                                            </button>
                                                        </div>
                                                        <div class="caption-area">
                                                            <input type="text"
                                                                name="existing_captions_id[{{ $photo->id }}]"
                                                                class="form-control form-control-sm"
                                                                placeholder="Caption ID (opsional)"
                                                                value="{{ old('existing_captions_id.' . $photo->id, $photo->caption['id'] ?? '') }}">
                                                            <input type="text"
                                                                name="existing_captions_en[{{ $photo->id }}]"
                                                                class="form-control form-control-sm"
                                                                placeholder="Caption EN (opsional)"
                                                                value="{{ old('existing_captions_en.' . $photo->id, $photo->caption['en'] ?? '') }}">
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    {{-- ===== UPLOAD FOTO BARU ===== --}}
                                    <div class="mb-4">
                                        <h6 class="fw-semibold mb-1">
                                            {{ isset($data) ? 'Tambah Foto Baru' : 'Upload Foto' }}
                                        </h6>
                                        <p class="text-muted small mb-3">
                                            Format: JPG, PNG, WEBP. Maks 5 MB/foto. Sort order otomatis dari urutan pilih.
                                        </p>

                                        {{--
                                            Input file WAJIB di luar dropzone div agar tidak
                                            ter-intercept click event dropzone secara tidak sengaja.
                                            Disembunyikan tapi tetap bagian dari form → ikut submit.
                                        --}}
                                        <input type="file" name="photos[]" id="photos-input" accept="image/*"
                                            multiple style="position:absolute; left:-9999px; opacity:0;">

                                        <div class="photo-dropzone" id="photo-dropzone">
                                            <i class="mdi mdi-image-multiple-outline d-block mb-2"></i>
                                            <p class="mb-1 fw-medium">Klik atau seret foto ke sini</p>
                                            <p class="text-muted small mb-0">Bisa pilih banyak foto sekaligus</p>
                                        </div>

                                        <div class="photos-grid" id="new-photos-grid"></div>

                                        <div id="photo-count-info" class="mt-2 text-muted small d-none">
                                            <span id="photo-count">0</span> foto dipilih &mdash;
                                            <button type="button" class="btn btn-link btn-sm p-0 text-danger"
                                                id="clear-all-photos">Hapus semua</button>
                                        </div>
                                    </div>

                                    {{-- ===== SUBMIT ===== --}}
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="mdi mdi-content-save me-1"></i>
                                            {{ isset($data) ? 'Simpan Perubahan' : 'Simpan Album' }}
                                        </button>
                                        <a href="{{ route('gallery-albums.index') }}" class="btn btn-light">Batal</a>
                                    </div>

                                </form>
                                @if (isset($data) && $data->photos->isNotEmpty())
                                    @foreach ($data->photos as $photo)
                                        <form id="delete-photo-{{ $photo->id }}" method="POST"
                                            action="{{ route('gallery-photos.destroy', $photo->id) }}"
                                            class="form-delete-photo d-none">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('resources/assets/libs/parsleyjs/parsley.min.js') }}"></script>
    <script src="{{ asset('resources/assets/js/pages/form-validation.init.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // ============================================================
            // COVER PREVIEW
            // ============================================================
            const coverInput = document.getElementById('cover-input');
            if (coverInput) {
                coverInput.addEventListener('change', function() {
                    const wrap = document.getElementById('cover-preview-new');
                    const img = document.getElementById('cover-preview-img');
                    const old = document.getElementById('cover-preview-existing');
                    if (this.files && this.files[0]) {
                        const r = new FileReader();
                        r.onload = e => {
                            img.src = e.target.result;
                            wrap.classList.remove('d-none');
                            if (old) old.classList.add('d-none');
                        };
                        r.readAsDataURL(this.files[0]);
                    }
                });
            }

            // ============================================================
            // NEW PHOTOS
            // Strategi: gunakan DataTransfer untuk kumpulkan file,
            // lalu GANTI input file asli dengan input baru yang membawa
            // FileList tersebut — ini cara paling reliable lintas browser.
            // ============================================================
            const dropzone = document.getElementById('photo-dropzone');
            const photosInput = document.getElementById('photos-input');
            const grid = document.getElementById('new-photos-grid');
            const countInfo = document.getElementById('photo-count-info');
            const countSpan = document.getElementById('photo-count');
            const clearBtn = document.getElementById('clear-all-photos');

            // DataTransfer sebagai buffer file yang dipilih
            let fileBuffer = []; // simpan sebagai array of File objects

            // Klik dropzone → buka file picker
            dropzone.addEventListener('click', () => photosInput.click());

            // Drag over / leave / drop
            dropzone.addEventListener('dragover', e => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
            dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
            dropzone.addEventListener('drop', e => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
                addFiles(Array.from(e.dataTransfer.files));
            });

            photosInput.addEventListener('change', function() {
                addFiles(Array.from(this.files));
            });

            // Hapus semua
            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    fileBuffer = [];
                    syncInputFiles();
                    grid.innerHTML = '';
                    updateCount();
                });
            }

            function addFiles(newFiles) {
                newFiles.forEach(file => {
                    if (!file.type.startsWith('image/')) return;
                    fileBuffer.push(file);
                    renderCard(file, fileBuffer.length - 1);
                });
                syncInputFiles();
                updateCount();
            }

            function renderCard(file, index) {
                const reader = new FileReader();
                reader.onload = e => {
                    const card = document.createElement('div');
                    card.className = 'photo-item';
                    card.dataset.index = index;
                    card.innerHTML = `
                    <div class="photo-thumb">
                        <img src="${e.target.result}" alt="${file.name}">
                        <span class="sort-no">#${index + 1}</span>
                        <button type="button" class="btn-remove-new" data-index="${index}" title="Hapus">✕</button>
                    </div>
                    <div class="caption-area">
                        <input type="text"
                               name="captions_id[]"
                               class="form-control form-control-sm"
                               placeholder="Caption ID (opsional)">
                        <input type="text"
                               name="captions_en[]"
                               class="form-control form-control-sm"
                               placeholder="Caption EN (opsional)">
                               
                    </div>
                `;
                    card.querySelector('.btn-remove-new').addEventListener('click', () => {
                        fileBuffer.splice(index, 1);
                        syncInputFiles();
                        rebuildGrid();
                        updateCount();
                    });
                    grid.appendChild(card);
                };
                reader.readAsDataURL(file);
            }

            function rebuildGrid() {
                grid.innerHTML = '';
                fileBuffer.forEach((f, i) => renderCard(f, i));
            }

            /**
             * Sync fileBuffer → input file asli menggunakan DataTransfer.
             * Ini cara satu-satunya yang benar untuk set FileList secara programatik.
             */
            function syncInputFiles() {
                const dt = new DataTransfer();
                fileBuffer.forEach(f => dt.items.add(f));
                photosInput.files = dt.files;
            }

            function updateCount() {
                const n = fileBuffer.length;
                if (countSpan) countSpan.textContent = n;
                if (countInfo) countInfo.classList.toggle('d-none', n === 0);
            }

            // ============================================================
            // DELETE EXISTING PHOTO — konfirmasi
            // ============================================================
            document.querySelectorAll('.form-delete-photo').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (confirm('Hapus foto ini secara permanen?')) this.submit();
                });
            });

        });
    </script>
@endpush

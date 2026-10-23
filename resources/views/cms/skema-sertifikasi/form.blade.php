@extends('layouts.app')

@push('styles')
    <link href="{{ asset('resources/assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
@endpush


@section('title', 'Skema Sertifikasi')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Content Website',
            'subtitle' => 'Skema Sertifikasi',
            'title' => 'Input Skema Sertifikasi',
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
                                                <label for="category_skema_id" class="form-label">Category</label>
                                                <select class="form-select" id="category_skema_id" name="category_skema_id"
                                                    required>
                                                    <option value="">-- Pilih Category --</option>
                                                    @foreach ($categories as $category)
                                                        <option value="{{ $category->id }}"
                                                            {{ old('category_skema_id', $data->category_skema_id ?? '') == $category->id ? 'selected' : '' }}>
                                                            {{ $category->kategori['id'] }}
                                                        </option>
                                                    @endforeach
                                                </select>


                                                <div class="invalid-feedback">
                                                    Please select a valid category.
                                                </div>
                                            </div>
                                        </div>

                                    </div>

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
                                                <label for="jenis_skema" class="form-label">Jenis Skema</label>
                                                <select class="form-select" id="jenis_skema" name="jenis_skema" required>
                                                    <option value="">-- Pilih Jenis Skema --</option>
                                                    @foreach (['KKNI', 'Okupasi', 'Klaster'] as $jenis)
                                                        <option value="{{ $jenis }}"
                                                            {{ old('jenis_skema', $data->jenis_skema ?? '') == $jenis ? 'selected' : '' }}>
                                                            {{ $jenis }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <div class="invalid-feedback">
                                                    Please select a valid jenis skema.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="nomor_skema" class="form-label">Nomor Skema</label>
                                                <input type="text" name="nomor_skema" class="form-control"
                                                    id="nomor_skema" placeholder="ex: SKM.01/LSP/2025"
                                                    value="{{ old('nomor_skema', $data->nomor_skema ?? '') }}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="summary_id" class="form-label">Summary ID</label>
                                                <textarea name="summary_id" class="form-control" id="summary_id" placeholder="Summary ID">{{ old('summary_id', $data->summary['id'] ?? '') }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="summary_en" class="form-label">Summary EN</label>
                                                <textarea name="summary_en" class="form-control" id="summary_en" placeholder="Summary EN">{{ old('summary_en', $data->summary['en'] ?? '') }}</textarea>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label>Description ID</label>
                                                <div>
                                                    <textarea class="editor" name="description_id">{{ old('description_id', $data->description['id'] ?? '') }}</textarea>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label>Description EN</label>
                                                <div>
                                                    <textarea class="editor" name="description_en">{{ old('description_en', $data->description['en'] ?? '') }}</textarea>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label>Standar Kompetensi Kerja</label>
                                                <div>
                                                    <textarea class="editor" name="standar_kompetensi_kerja">{{ old('standar_kompetensi_kerja', $data->standar_kompetensi_kerja ?? '') }}</textarea>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label mb-0">Unit Kompetensi</label>
                                                <p class="text-muted small mb-2">Daftar unit kompetensi yang
                                                    dipersyaratkan pada skema ini.</p>

                                                <div class="table-responsive">
                                                    @php
                                                        $oldUnit = old('unit_kompetensi');

                                                        if ($oldUnit) {
                                                            // old() balikin shape mentah dari input form:
                                                            // ['id' => [...], 'kode_unit' => [...], 'judul_unit' => [...]]
                                                            // -> perlu di-"zip" jadi array per-baris biar bisa di-foreach.
                                                            $unitRows = [];
                                                            foreach ($oldUnit['kode_unit'] ?? [] as $i => $kodeUnit) {
                                                                $unitRows[] = [
                                                                    'id' => $oldUnit['id'][$i] ?? null,
                                                                    'kode_unit' => $kodeUnit,
                                                                    'judul_unit' => $oldUnit['judul_unit'][$i] ?? '',
                                                                ];
                                                            }
                                                        } elseif (isset($data)) {
                                                            $unitRows = $data->unitKompetensis
                                                                ->map(
                                                                    fn($u) => [
                                                                        'id' => $u->id,
                                                                        'kode_unit' => $u->kode_unit,
                                                                        'judul_unit' => $u->judul_unit,
                                                                    ],
                                                                )
                                                                ->all();
                                                        } else {
                                                            $unitRows = [
                                                                [
                                                                    'id' => null,
                                                                    'kode_unit' => '',
                                                                    'judul_unit' => '',
                                                                ],
                                                            ];
                                                        }
                                                    @endphp
                                                    <table class="table table-bordered align-middle mb-2"
                                                        id="unit-kompetensi-table">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 60px;">No</th>
                                                                <th style="width: 30%;">Kode Unit</th>
                                                                <th>Judul Unit</th>
                                                                <th style="width: 160px;"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="unit-kompetensi-rows">
                                                            @foreach ($unitRows as $index => $unit)
                                                                <tr class="unit-kompetensi-row">
                                                                    <td class="text-center unit-row-no">
                                                                        {{ $index + 1 }}</td>
                                                                    <td>
                                                                        <input type="hidden" name="unit_kompetensi[id][]"
                                                                            value="{{ $unit['id'] ?? '' }}">
                                                                        <input type="text"
                                                                            name="unit_kompetensi[kode_unit][]"
                                                                            class="form-control"
                                                                            value="{{ $unit['kode_unit'] ?? '' }}">
                                                                    </td>
                                                                    <td>
                                                                        <input type="text"
                                                                            name="unit_kompetensi[judul_unit][]"
                                                                            class="form-control"
                                                                            value="{{ $unit['judul_unit'] ?? '' }}">
                                                                    </td>
                                                                    <td class="text-center">
                                                                        @if (!empty($unit['id']))
                                                                            <a href="{{ route('skema-sertifikasi.unit-kompetensi.elemen.edit', [$data->id, $unit['id']]) }}"
                                                                                class="btn btn-outline-primary btn-sm"
                                                                                title="Kelola Elemen & KUK">
                                                                                <i
                                                                                    class="mdi mdi-format-list-bulleted"></i>
                                                                            </a>
                                                                        @endif
                                                                        <button type="button"
                                                                            class="btn btn-outline-danger btn-sm btn-remove-unit-kompetensi">
                                                                            <i class="mdi mdi-trash-can-outline"></i>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <button type="button" class="btn btn-outline-primary btn-sm"
                                                    id="btn-add-unit-kompetensi">
                                                    <i class="mdi mdi-plus-circle me-1"></i> Tambah Unit Kompetensi
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Template FR.MAPA.01/02 per metode (hanya untuk skema yang sudah tersimpan) --}}
                                    @isset($data)
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-label mb-0">Template Perangkat Asesmen (MAPA &amp; Instrumen IA)</label>
                                                    <p class="text-muted small mb-2">Rencana asesmen per unit, konfirmasi orang yang relevan,
                                                        penyusun &amp; validator, dan instrumen IA untuk setiap metode; template Umum berisi soal esai
                                                        FR.IA.06 (semua metode). Simpan perubahan unit terlebih dahulu.</p>
                                                    @foreach (['kt' => 'KT', 'tpd' => 'TPD', 'vp' => 'VP', 'umum' => 'Umum (IA.06)'] as $kodeMetode => $labelMetode)
                                                        <a href="{{ route('skema-sertifikasi.mapa.edit', [$data->id, $kodeMetode]) }}"
                                                            class="btn btn-outline-secondary btn-sm me-1">
                                                            <i class="mdi mdi-file-document-edit-outline me-1"></i> Template {{ $labelMetode }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endisset

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label mb-0">Bukti Persyaratan Dasar</label>
                                                <p class="text-muted small mb-2">Daftar bukti/dokumen persyaratan
                                                    dasar yang harus dilampirkan asesi untuk skema ini (ex: SK
                                                    pengangkatan jabatan, sertifikat pelatihan, dll).</p>

                                                <div class="table-responsive">
                                                    @php
                                                        $oldBukti = old('bukti_persyaratan_dasar');

                                                        if ($oldBukti) {
                                                            // old() balikin shape mentah dari input form:
                                                            // ['id' => [...], 'deskripsi' => [...]]
                                                            // -> perlu di-"zip" jadi array per-baris biar bisa di-foreach.
                                                            $buktiRows = [];
                                                            foreach ($oldBukti['deskripsi'] ?? [] as $i => $deskripsi) {
                                                                $buktiRows[] = [
                                                                    'id' => $oldBukti['id'][$i] ?? null,
                                                                    'deskripsi' => $deskripsi,
                                                                ];
                                                            }
                                                        } elseif (isset($data)) {
                                                            $buktiRows = $data->buktiPersyaratanDasar
                                                                ->map(
                                                                    fn($b) => [
                                                                        'id' => $b->id,
                                                                        'deskripsi' => $b->deskripsi,
                                                                    ],
                                                                )
                                                                ->all();
                                                        } else {
                                                            $buktiRows = [
                                                                [
                                                                    'id' => null,
                                                                    'deskripsi' => '',
                                                                ],
                                                            ];
                                                        }
                                                    @endphp
                                                    <table class="table table-bordered align-middle mb-2"
                                                        id="bukti-persyaratan-dasar-table">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 60px;">No</th>
                                                                <th>Deskripsi Bukti Persyaratan</th>
                                                                <th style="width: 60px;"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="bukti-persyaratan-dasar-rows">
                                                            @foreach ($buktiRows as $index => $bukti)
                                                                <tr class="bukti-persyaratan-dasar-row">
                                                                    <td class="text-center bukti-row-no">
                                                                        {{ $index + 1 }}</td>
                                                                    <td>
                                                                        <input type="hidden"
                                                                            name="bukti_persyaratan_dasar[id][]"
                                                                            value="{{ $bukti['id'] ?? '' }}">
                                                                        <input type="text"
                                                                            name="bukti_persyaratan_dasar[deskripsi][]"
                                                                            class="form-control"
                                                                            placeholder="ex: SK pengangkatan jabatan Meeting/Conference Project Manager"
                                                                            value="{{ $bukti['deskripsi'] ?? '' }}">
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <button type="button"
                                                                            class="btn btn-outline-danger btn-sm btn-remove-bukti-persyaratan-dasar">
                                                                            <i class="mdi mdi-trash-can-outline"></i>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <button type="button" class="btn btn-outline-primary btn-sm"
                                                    id="btn-add-bukti-persyaratan-dasar">
                                                    <i class="mdi mdi-plus-circle me-1"></i> Tambah Bukti Persyaratan
                                                </button>
                                            </div>
                                        </div>
                                    </div>

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
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="amount" class="form-label">Amount</label>
                                                <input type="text" name="amount" class="form-control" id="amount"
                                                    placeholder="ex: 1000000"
                                                    value="{{ old('amount', $data->amount ?? '') }}" required>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const rowsBody = document.getElementById('unit-kompetensi-rows');
            const addBtn = document.getElementById('btn-add-unit-kompetensi');

            function renumberRows() {
                rowsBody.querySelectorAll('.unit-kompetensi-row').forEach((row, index) => {
                    row.querySelector('.unit-row-no').textContent = index + 1;
                });
            }

            function buildRow() {
                const row = document.createElement('tr');
                row.className = 'unit-kompetensi-row';
                row.innerHTML = `
                    <td class="text-center unit-row-no"></td>
                    <td>
                        <input type="hidden" name="unit_kompetensi[id][]" value="">
                        <input type="text" name="unit_kompetensi[kode_unit][]" class="form-control"
                            placeholder="ex: N.79KEW00.001.2">
                    </td>
                    <td>
                        <input type="text" name="unit_kompetensi[judul_unit][]" class="form-control"
                            placeholder="Judul Unit Kompetensi">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-unit-kompetensi">
                            <i class="mdi mdi-trash-can-outline"></i>
                        </button>
                    </td>
                `;
                return row;
            }

            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    rowsBody.appendChild(buildRow());
                    renumberRows();
                });
            }

            if (rowsBody) {
                rowsBody.addEventListener('click', function(e) {
                    const removeBtn = e.target.closest('.btn-remove-unit-kompetensi');
                    if (!removeBtn) return;

                    const rows = rowsBody.querySelectorAll('.unit-kompetensi-row');
                    if (rows.length <= 1) {
                        removeBtn.closest('.unit-kompetensi-row').querySelectorAll('input').forEach(
                            input => input.value = '');
                        return;
                    }

                    removeBtn.closest('.unit-kompetensi-row').remove();
                    renumberRows();
                });
            }

        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const rowsBody = document.getElementById('bukti-persyaratan-dasar-rows');
            const addBtn = document.getElementById('btn-add-bukti-persyaratan-dasar');

            function renumberRows() {
                rowsBody.querySelectorAll('.bukti-persyaratan-dasar-row').forEach((row, index) => {
                    row.querySelector('.bukti-row-no').textContent = index + 1;
                });
            }

            function buildRow() {
                const row = document.createElement('tr');
                row.className = 'bukti-persyaratan-dasar-row';
                row.innerHTML = `
                    <td class="text-center bukti-row-no"></td>
                    <td>
                        <input type="hidden" name="bukti_persyaratan_dasar[id][]" value="">
                        <input type="text" name="bukti_persyaratan_dasar[deskripsi][]" class="form-control"
                            placeholder="ex: SK pengangkatan jabatan Meeting/Conference Project Manager">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-bukti-persyaratan-dasar">
                            <i class="mdi mdi-trash-can-outline"></i>
                        </button>
                    </td>
                `;
                return row;
            }

            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    rowsBody.appendChild(buildRow());
                    renumberRows();
                });
            }

            if (rowsBody) {
                rowsBody.addEventListener('click', function(e) {
                    const removeBtn = e.target.closest('.btn-remove-bukti-persyaratan-dasar');
                    if (!removeBtn) return;

                    const rows = rowsBody.querySelectorAll('.bukti-persyaratan-dasar-row');
                    if (rows.length <= 1) {
                        removeBtn.closest('.bukti-persyaratan-dasar-row').querySelectorAll('input').forEach(
                            input => input.value = '');
                        return;
                    }

                    removeBtn.closest('.bukti-persyaratan-dasar-row').remove();
                    renumberRows();
                });
            }

        });
    </script>
@endpush

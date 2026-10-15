@extends('layouts.app')

@section('title', 'Elemen & KUK')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Content Website',
            'subtitle' => 'Skema Sertifikasi',
            'title' => 'Elemen & KUK',
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

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-1">{{ $skema->title['id'] ?? '-' }}</h5>
                                <p class="text-muted mb-0">
                                    Unit Kompetensi: <strong>{{ $unitKompetensi->kode_unit }}</strong> &mdash;
                                    {{ $unitKompetensi->judul_unit }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title mb-0">Elemen Kompetensi &amp; Kriteria Unjuk Kerja
                                        </h5>
                                        <p class="text-muted small mb-0">
                                            Setiap elemen minimal memiliki satu Kriteria Unjuk Kerja (KUK).
                                        </p>
                                    </div>
                                </div>

                                <form action="{{ route('skema-sertifikasi.unit-kompetensi.elemen.update', [$skema->id, $unitKompetensi->id]) }}"
                                    method="POST">
                                    @csrf
                                    @method('PUT')

                                    <div id="elemen-container">
                                        @php
                                            $elemenRows =
                                                old('elemen') ??
                                                ($unitKompetensi->elemenKompetensi->isNotEmpty()
                                                    ? $unitKompetensi->elemenKompetensi
                                                        ->map(
                                                            fn($e) => [
                                                                'id' => $e->id,
                                                                'nama_elemen' => $e->nama_elemen,
                                                                'kuk' => $e->kriteriaUnjukKerja
                                                                    ->map(
                                                                        fn($k) => [
                                                                            'nomor_kuk' => $k->nomor_kuk,
                                                                            'deskripsi' => $k->deskripsi,
                                                                        ],
                                                                    )
                                                                    ->all(),
                                                            ],
                                                        )
                                                        ->all()
                                                    : [
                                                        [
                                                            'nama_elemen' => '',
                                                            'kuk' => [['nomor_kuk' => '', 'deskripsi' => '']],
                                                        ],
                                                    ]);
                                        @endphp

                                        @foreach ($elemenRows as $ei => $elemen)
                                            <div class="card elemen-block mb-3" data-index="{{ $ei }}">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="mb-0">Elemen <span
                                                                class="elemen-no">{{ $ei + 1 }}</span></h6>
                                                        <button type="button"
                                                            class="btn btn-outline-danger btn-sm btn-remove-elemen">
                                                            <i class="mdi mdi-trash-can-outline"></i> Hapus Elemen
                                                        </button>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Elemen</label>
                                                        {{-- ID elemen lama: dipakai sinkronisasi agar jawaban FR.APL.02 asesi tidak hilang --}}
                                                        <input type="hidden" class="id-elemen-input"
                                                            name="elemen[{{ $ei }}][id]"
                                                            value="{{ $elemen['id'] ?? '' }}">
                                                        <input type="text" class="form-control nama-elemen-input"
                                                            name="elemen[{{ $ei }}][nama_elemen]"
                                                            placeholder="ex: Merencanakan kegiatan usaha"
                                                            value="{{ $elemen['nama_elemen'] ?? '' }}" required>
                                                    </div>

                                                    <label class="form-label mb-1">Kriteria Unjuk Kerja (KUK)</label>
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered align-middle mb-2 kuk-table">
                                                            <thead>
                                                                <tr>
                                                                    <th style="width: 60px;">No</th>
                                                                    <th style="width: 150px;">Nomor KUK</th>
                                                                    <th>Deskripsi</th>
                                                                    <th style="width: 60px;"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="kuk-rows">
                                                                @foreach ($elemen['kuk'] ?? [] as $ki => $kuk)
                                                                    <tr class="kuk-block" data-index="{{ $ki }}">
                                                                        <td class="text-center kuk-no">
                                                                            {{ $ki + 1 }}</td>
                                                                        <td>
                                                                            <input type="text"
                                                                                class="form-control nomor-kuk-input"
                                                                                name="elemen[{{ $ei }}][kuk][{{ $ki }}][nomor_kuk]"
                                                                                placeholder="ex: 1.1" maxlength="20"
                                                                                value="{{ $kuk['nomor_kuk'] ?? '' }}"
                                                                                required>
                                                                        </td>
                                                                        <td>
                                                                            <textarea class="form-control deskripsi-kuk-input" name="elemen[{{ $ei }}][kuk][{{ $ki }}][deskripsi]" rows="2"
                                                                                placeholder="Deskripsi KUK" required>{{ $kuk['deskripsi'] ?? '' }}</textarea>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <button type="button"
                                                                                class="btn btn-outline-danger btn-sm btn-remove-kuk">
                                                                                <i
                                                                                    class="mdi mdi-trash-can-outline"></i>
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>

                                                    <button type="button"
                                                        class="btn btn-outline-secondary btn-sm btn-add-kuk">
                                                        <i class="mdi mdi-plus-circle me-1"></i> Tambah KUK
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <button type="button" class="btn btn-outline-primary" id="btn-add-elemen">
                                        <i class="mdi mdi-plus-circle me-1"></i> Tambah Elemen Kompetensi
                                    </button>

                                    <hr class="my-4">

                                    <div class="d-flex gap-2">
                                        <button class="btn btn-primary" type="submit">Simpan Elemen &amp; KUK</button>
                                        <a href="{{ route('skema-sertifikasi.edit', $skema->id) }}"
                                            class="btn btn-light">Kembali</a>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const container = document.getElementById('elemen-container');
            const addElemenBtn = document.getElementById('btn-add-elemen');

            function buildKukRow() {
                const row = document.createElement('tr');
                row.className = 'kuk-block';
                row.innerHTML = `
                    <td class="text-center kuk-no"></td>
                    <td>
                        <input type="text" class="form-control nomor-kuk-input" placeholder="ex: 1.1" maxlength="20" required>
                    </td>
                    <td>
                        <textarea class="form-control deskripsi-kuk-input" rows="2" placeholder="Deskripsi KUK" required></textarea>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-kuk">
                            <i class="mdi mdi-trash-can-outline"></i>
                        </button>
                    </td>
                `;
                return row;
            }

            function buildElemenBlock() {
                const block = document.createElement('div');
                block.className = 'card elemen-block mb-3';
                block.innerHTML = `
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0">Elemen <span class="elemen-no"></span></h6>
                            <button type="button" class="btn btn-outline-danger btn-sm btn-remove-elemen">
                                <i class="mdi mdi-trash-can-outline"></i> Hapus Elemen
                            </button>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Elemen</label>
                            <input type="text" class="form-control nama-elemen-input"
                                placeholder="ex: Merencanakan kegiatan usaha" required>
                        </div>

                        <label class="form-label mb-1">Kriteria Unjuk Kerja (KUK)</label>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-2 kuk-table">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">No</th>
                                        <th style="width: 150px;">Nomor KUK</th>
                                        <th>Deskripsi</th>
                                        <th style="width: 60px;"></th>
                                    </tr>
                                </thead>
                                <tbody class="kuk-rows"></tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-outline-secondary btn-sm btn-add-kuk">
                            <i class="mdi mdi-plus-circle me-1"></i> Tambah KUK
                        </button>
                    </div>
                `;
                block.querySelector('.kuk-rows').appendChild(buildKukRow());
                return block;
            }

            function reindexAll() {
                container.querySelectorAll('.elemen-block').forEach((block, ei) => {
                    block.dataset.index = ei;
                    block.querySelector('.elemen-no').textContent = ei + 1;

                    const namaInput = block.querySelector('.nama-elemen-input');
                    if (namaInput) namaInput.name = `elemen[${ei}][nama_elemen]`;

                    const idInput = block.querySelector('.id-elemen-input');
                    if (idInput) idInput.name = `elemen[${ei}][id]`;

                    block.querySelectorAll('.kuk-block').forEach((kukRow, ki) => {
                        kukRow.dataset.index = ki;
                        kukRow.querySelector('.kuk-no').textContent = ki + 1;

                        const nomorInput = kukRow.querySelector('.nomor-kuk-input');
                        if (nomorInput) nomorInput.name = `elemen[${ei}][kuk][${ki}][nomor_kuk]`;

                        const deskripsiInput = kukRow.querySelector('.deskripsi-kuk-input');
                        if (deskripsiInput) deskripsiInput.name = `elemen[${ei}][kuk][${ki}][deskripsi]`;
                    });
                });
            }

            if (addElemenBtn) {
                addElemenBtn.addEventListener('click', function() {
                    container.appendChild(buildElemenBlock());
                    reindexAll();
                });
            }

            if (container) {
                container.addEventListener('click', function(e) {

                    // ===== Tambah KUK =====
                    const addKukBtn = e.target.closest('.btn-add-kuk');
                    if (addKukBtn) {
                        const kukRows = addKukBtn.closest('.elemen-block').querySelector('.kuk-rows');
                        kukRows.appendChild(buildKukRow());
                        reindexAll();
                        return;
                    }

                    // ===== Hapus KUK =====
                    const removeKukBtn = e.target.closest('.btn-remove-kuk');
                    if (removeKukBtn) {
                        const kukRow = removeKukBtn.closest('.kuk-block');
                        const kukRows = removeKukBtn.closest('.kuk-rows');

                        if (kukRows.querySelectorAll('.kuk-block').length <= 1) {
                            kukRow.querySelector('.nomor-kuk-input').value = '';
                            kukRow.querySelector('.deskripsi-kuk-input').value = '';
                            return;
                        }

                        kukRow.remove();
                        reindexAll();
                        return;
                    }

                    // ===== Hapus Elemen =====
                    const removeElemenBtn = e.target.closest('.btn-remove-elemen');
                    if (removeElemenBtn) {
                        const block = removeElemenBtn.closest('.elemen-block');

                        if (container.querySelectorAll('.elemen-block').length <= 1) {
                            block.querySelector('.nama-elemen-input').value = '';
                            block.querySelectorAll('.kuk-block').forEach((row, i) => {
                                if (i === 0) {
                                    row.querySelector('.nomor-kuk-input').value = '';
                                    row.querySelector('.deskripsi-kuk-input').value = '';
                                } else {
                                    row.remove();
                                }
                            });
                            reindexAll();
                            return;
                        }

                        block.remove();
                        reindexAll();
                    }
                });
            }

        });
    </script>
@endpush

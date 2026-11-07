@extends('layouts.app')

@section('title', 'Voucher')

@section('content')
    @php
        $zona = config('muk.zona_waktu');
        $tipe = old('tipe', $voucher->tipe);
        $nilaiAwal = $voucher->nilai !== null
            ? ($voucher->tipe === \App\Models\Voucher::PERSENTASE ? rtrim(rtrim((string) $voucher->nilai, '0'), '.') : (int) $voucher->nilai)
            : null;
    @endphp
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Voucher',
            'title' => $method === 'POST' ? 'Tambah Voucher' : 'Ubah Voucher',
            'action' => null,
        ])

        <div class="container-fluid">
            <div class="page-content-wrapper">
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
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
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <form action="{{ $action }}" method="POST">
                                    @csrf
                                    @if ($method !== 'POST')
                                        @method($method)
                                    @endif

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="kode" class="form-label">Kode Voucher <span class="text-danger">*</span></label>
                                            <input type="text" name="kode" id="kode" class="form-control font-monospace text-uppercase"
                                                maxlength="32" required placeholder="mis. HEMAT50" value="{{ old('kode', $voucher->kode) }}"
                                                {{ $sudahDipakai ? 'readonly' : '' }}>
                                            <div class="form-text">
                                                {{ $sudahDipakai ? 'Kode tidak dapat diubah karena voucher sudah pernah dipakai.' : 'Huruf, angka, - dan _ (3-32 karakter). Otomatis huruf besar.' }}
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="nama" class="form-label">Nama / Keterangan</label>
                                            <input type="text" name="nama" id="nama" class="form-control" maxlength="150"
                                                placeholder="mis. Promo Hari Pariwisata" value="{{ old('nama', $voucher->nama) }}">
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="tipe" class="form-label">Tipe Potongan <span class="text-danger">*</span></label>
                                            <select name="tipe" id="tipe" class="form-select" required>
                                                @foreach (\App\Models\Voucher::TIPE_LABEL as $nilai => $label)
                                                    <option value="{{ $nilai }}" @selected($tipe === $nilai)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="nilai" class="form-label">
                                                <span data-tipe="nominal">Nominal Potongan (Rp)</span>
                                                <span data-tipe="persentase">Persentase Potongan (%)</span>
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" name="nilai" id="nilai" class="form-control" required min="0.01" step="any"
                                                value="{{ old('nilai', $nilaiAwal) }}">
                                        </div>
                                        <div class="col-md-4 mb-3" data-tipe="persentase">
                                            <label for="maks_potongan" class="form-label">Maks. Potongan (Rp)</label>
                                            <input type="number" name="maks_potongan" id="maks_potongan" class="form-control" min="1" step="1"
                                                placeholder="Tanpa batas" value="{{ old('maks_potongan', $voucher->maks_potongan !== null ? (int) $voucher->maks_potongan : null) }}">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="mulai_at" class="form-label">Berlaku Mulai (WIB) <span class="text-danger">*</span></label>
                                            <input type="datetime-local" name="mulai_at" id="mulai_at" class="form-control" required
                                                value="{{ old('mulai_at', $voucher->mulai_at?->timezone($zona)->format('Y-m-d\TH:i') ?? now($zona)->format('Y-m-d\T00:00')) }}">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="berakhir_at" class="form-label">Berlaku Sampai (WIB) <span class="text-danger">*</span></label>
                                            <input type="datetime-local" name="berakhir_at" id="berakhir_at" class="form-control" required
                                                value="{{ old('berakhir_at', $voucher->berakhir_at?->timezone($zona)->format('Y-m-d\TH:i')) }}">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="kuota" class="form-label">Kuota Total Pemakaian</label>
                                            <input type="number" name="kuota" id="kuota" class="form-control" min="1" step="1"
                                                placeholder="Kosongkan = tanpa batas" value="{{ old('kuota', $voucher->kuota) }}">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="maks_per_asesi" class="form-label">Maks. Pemakaian per Asesi</label>
                                            <input type="number" name="maks_per_asesi" id="maks_per_asesi" class="form-control" min="1" step="1"
                                                placeholder="Kosongkan = tanpa batas" value="{{ old('maks_per_asesi', $voucher->maks_per_asesi) }}">
                                        </div>

                                        <div class="col-12 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" id="aktif" name="aktif" value="1"
                                                    {{ old('aktif', $voucher->aktif) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="aktif">Aktif (dapat dipakai asesi selama periode berlaku)</label>
                                            </div>
                                        </div>
                                    </div>

                                    @if ($sudahDipakai)
                                        <div class="alert alert-info small">
                                            Perubahan potongan hanya berlaku untuk pemakaian berikutnya. Tagihan yang sudah memakai voucher ini
                                            tetap memakai potongan saat voucher dipasang.
                                        </div>
                                    @endif

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Simpan</button>
                                        <a href="{{ $voucher->exists ? route('vouchers.show', $voucher) : route('vouchers.index') }}" class="btn btn-light">Batal</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const tipe = document.getElementById('tipe');
            const nilai = document.getElementById('nilai');
            const atur = () => {
                document.querySelectorAll('[data-tipe]').forEach((el) => {
                    el.style.display = el.dataset.tipe === tipe.value ? '' : 'none';
                });
                nilai.max = tipe.value === 'persentase' ? '100' : '';
                nilai.step = tipe.value === 'persentase' ? '0.01' : '1';
            };
            tipe.addEventListener('change', atur);
            atur();
        })();
    </script>
@endpush

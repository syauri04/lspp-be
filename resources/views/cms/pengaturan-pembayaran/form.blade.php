@extends('layouts.app')

@section('title', 'Rekening Pembayaran')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Pengaturan Pembayaran',
            'title' => $method === 'POST' ? 'Tambah Rekening' : 'Ubah Rekening',
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
                                            <label for="nama_bank" class="form-label">Nama Bank <span class="text-danger">*</span></label>
                                            <input type="text" name="nama_bank" id="nama_bank" class="form-control" maxlength="100" required
                                                placeholder="mis. Bank Mandiri" value="{{ old('nama_bank', $rekening->nama_bank) }}">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="nomor_rekening" class="form-label">Nomor Rekening <span class="text-danger">*</span></label>
                                            <input type="text" name="nomor_rekening" id="nomor_rekening" class="form-control font-monospace" maxlength="50" required
                                                placeholder="mis. 123 000 4567 890" value="{{ old('nomor_rekening', $rekening->nomor_rekening) }}">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="atas_nama" class="form-label">Atas Nama <span class="text-danger">*</span></label>
                                            <input type="text" name="atas_nama" id="atas_nama" class="form-control" maxlength="150" required
                                                placeholder="mis. LSP Pariwisata 306" value="{{ old('atas_nama', $rekening->atas_nama) }}">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="keterangan" class="form-label">Keterangan</label>
                                            <input type="text" name="keterangan" id="keterangan" class="form-control" maxlength="255"
                                                placeholder="mis. KCP Cipayung (opsional)" value="{{ old('keterangan', $rekening->keterangan) }}">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="urutan" class="form-label">Urutan Tampil</label>
                                            <input type="number" name="urutan" id="urutan" class="form-control" min="0" max="999"
                                                value="{{ old('urutan', $rekening->urutan ?? 0) }}">
                                        </div>
                                        <div class="col-md-9 mb-3 d-flex align-items-end">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" id="aktif" name="aktif" value="1"
                                                    {{ old('aktif', $rekening->aktif) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="aktif">Aktif (ditampilkan ke asesi)</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Simpan</button>
                                        <a href="{{ route('pengaturan-pembayaran.index') }}" class="btn btn-light">Batal</a>
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

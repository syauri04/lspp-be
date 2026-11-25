@extends('layouts.app')

@section('title', 'Kontak Website')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Content Website',
            'subtitle' => 'Kontak Kami',
            'title' => 'Ubah Kontak',
            'action' => null,
        ])

        <div class="container-fluid">
            <div class="page-content-wrapper">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle-outline me-1"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
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
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-body">
                                <p class="text-muted mb-4">
                                    Data ini tampil di <strong>footer "Kontak Kami"</strong> dan <strong>tombol WhatsApp mengambang</strong>
                                    di seluruh halaman website. Email yang sama dipakai di keduanya. Perubahan tampil di website paling lambat 5 menit.
                                </p>

                                <form action="{{ route('kontak-website.update') }}" method="POST">
                                    @csrf
                                    @method('PUT')

                                    <div class="mb-3">
                                        <label for="nama" class="form-label">Nama Kontak <span class="text-danger">*</span></label>
                                        <input type="text" name="nama" id="nama" class="form-control @error('nama') is-invalid @enderror" maxlength="100" required
                                            placeholder="mis. Admin LSPP306" value="{{ old('nama', $kontak['nama']) }}">
                                        <small class="text-muted">Tampil sebagai judul tombol WhatsApp.</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="whatsapp" class="form-label">Nomor WhatsApp <span class="text-danger">*</span></label>
                                        <input type="tel" name="whatsapp" id="whatsapp" class="form-control @error('whatsapp') is-invalid @enderror" maxlength="25" required
                                            placeholder="mis. 0822-4962-8093" value="{{ old('whatsapp', $whatsappTampil) }}">
                                        <small class="text-muted">Nomor Indonesia; boleh diawali 0 atau +62, spasi/tanda hubung diabaikan.</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" maxlength="255" required
                                            placeholder="mis. support@lspp306.com" value="{{ old('email', $kontak['email']) }}">
                                        <small class="text-muted">Dipakai di footer dan tombol WhatsApp.</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="alamat" class="form-label">Alamat <span class="text-danger">*</span></label>
                                        <textarea name="alamat" id="alamat" rows="3" class="form-control @error('alamat') is-invalid @enderror" maxlength="500" required>{{ old('alamat', $kontak['alamat']) }}</textarea>
                                        <small class="text-muted">Tampil di footer.</small>
                                    </div>

                                    <div class="mb-4">
                                        <label for="pesan_wa" class="form-label">Pesan Awal WhatsApp</label>
                                        <textarea name="pesan_wa" id="pesan_wa" rows="2" class="form-control @error('pesan_wa') is-invalid @enderror" maxlength="300"
                                            placeholder="mis. Halo Admin LSPP306, saya ingin bertanya tentang sertifikasi.">{{ old('pesan_wa', $kontak['pesan_wa']) }}</textarea>
                                        <small class="text-muted">Opsional. Terisi otomatis di kolom chat saat pengunjung membuka WhatsApp.</small>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                </form>

                                @if ($kontak['diubah_oleh'])
                                    <p class="text-muted small mt-3 mb-0">
                                        Terakhir diubah oleh {{ $kontak['diubah_oleh'] }} pada
                                        {{ \Illuminate\Support\Carbon::parse($kontak['diubah_at'])->timezone(config('app.timezone'))->format('d M Y, H:i') }}.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Tampilan Saat Ini</h5>
                                <h6 class="text-muted text-uppercase small">Tombol WhatsApp</h6>
                                <div class="border rounded-pill d-inline-flex align-items-center gap-2 px-3 py-2 mb-4">
                                    <i class="mdi mdi-whatsapp text-success fs-3"></i>
                                    <div class="lh-sm">
                                        <div class="fw-bold">{{ $kontak['nama'] }}</div>
                                        <div class="small text-muted">{{ $whatsappTampil }}</div>
                                        <div class="small text-muted">{{ $kontak['email'] }}</div>
                                    </div>
                                </div>
                                <h6 class="text-muted text-uppercase small">Footer "Kontak Kami"</h6>
                                <p class="mb-1"><i class="mdi mdi-map-marker-outline me-1"></i>{{ $kontak['alamat'] }}</p>
                                <p class="mb-3"><i class="mdi mdi-email-outline me-1"></i>{{ $kontak['email'] }}</p>
                                <a href="{{ \App\Support\KontakLembaga::urlWhatsapp($kontak) }}" target="_blank" rel="noopener noreferrer"
                                    class="btn btn-sm btn-outline-success">
                                    <i class="mdi mdi-whatsapp me-1"></i> Uji tautan WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

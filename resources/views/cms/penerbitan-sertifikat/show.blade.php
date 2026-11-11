@extends('layouts.app')

@section('title', 'Penerbitan Sertifikat')

@section('content')
    @php
        $zona = config('muk.zona_waktu');
        $pendaftaran = $p->pendaftaran;
        $jadwal = $p->penilaianMuk?->jadwalAsesmen;
        $sertifikat = $p->sertifikat;
        $selesai = $p->selesai();
        $warnaStatus = ['menunggu_dokumen' => 'bg-warning text-dark', 'siap_diselesaikan' => 'bg-info', 'selesai' => 'bg-success'];
        $labelAksi = [
            'dokumen_diunggah' => 'Dokumen diunggah',
            'dokumen_diganti' => 'Dokumen diganti',
            'sertifikat_diterbitkan' => 'E-Sertifikat diterbitkan',
            'sertifikat_diubah' => 'E-Sertifikat diubah',
            'status_sertifikat' => 'Status sertifikat diubah',
            'diselesaikan' => 'Proses diselesaikan',
        ];
        $aktifAwal = old('tanggal_aktif', $sertifikat?->tanggal_aktif?->toDateString());
        $expiredAwal = old('tanggal_expired', $sertifikat?->tanggal_expired?->toDateString());
    @endphp
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Penerbitan Sertifikat',
            'title' => 'Penerbitan Sertifikat',
            'action' => [
                'label' => 'Kembali',
                'url' => route('penerbitan-sertifikat.index'),
                'icon' => 'mdi mdi-arrow-left',
                'type' => 'light',
            ],
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
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @unless ($bisaKelola)
                    <div class="alert alert-info"><i class="mdi mdi-information-outline me-1"></i>
                        Anda dapat melihat data ini. Unggah dokumen dan penyelesaian proses hanya dapat dilakukan oleh super admin.</div>
                @endunless

                <div class="row">
                    {{-- Ringkasan --}}
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="mb-1">{{ $pendaftaran->asesi->name ?? '-' }}</h5>
                                <p class="text-muted mb-3">{{ $pendaftaran->asesi->email ?? '' }}</p>
                                <table class="table table-sm mb-0">
                                    <tr><th class="fw-normal text-muted">Skema</th><td>{{ $pendaftaran->skemaSertifikasi->title['id'] ?? '-' }}</td></tr>
                                    <tr><th class="fw-normal text-muted">Hasil asesmen</th><td>
                                        <span class="badge {{ $p->kompeten() ? 'bg-success' : 'bg-danger' }}">{{ \App\Models\PenerbitanSertifikasi::HASIL_LABEL[$p->hasil] }}</span>
                                    </td></tr>
                                    <tr><th class="fw-normal text-muted">Asesor</th><td>{{ $jadwal?->asesor?->nama ?? '-' }}</td></tr>
                                    <tr><th class="fw-normal text-muted">Tanggal asesmen</th><td>{{ $jadwal?->tanggal_asesmen?->format('d M Y') ?? '-' }}</td></tr>
                                    <tr><th class="fw-normal text-muted">Status</th><td>
                                        <span class="badge {{ $warnaStatus[$p->status] ?? 'bg-secondary' }}">{{ \App\Models\PenerbitanSertifikasi::STATUS_LABEL[$p->status] ?? $p->status }}</span>
                                    </td></tr>
                                    @if ($bisaKelola && $p->penilaianMuk)
                                        <tr><th class="fw-normal text-muted">Dokumen MUK</th><td>
                                            <form method="POST" action="{{ route('muk.buka', $p->penilaianMuk) }}" target="_blank">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <i class="mdi mdi-printer me-1"></i> Buka &amp; Cetak di MUK
                                                </button>
                                            </form>
                                        </td></tr>
                                    @endif
                                    @if ($selesai)
                                        <tr><th class="fw-normal text-muted">Diselesaikan</th><td>
                                            {{ $p->diselesaikan_at?->timezone($zona)->format('d/m/Y H:i') }} WIB
                                            <div class="small text-muted">oleh {{ $p->diselesaikanOleh->name ?? '-' }}</div>
                                        </td></tr>
                                    @endif
                                </table>
                            </div>
                        </div>

                        @unless ($selesai)
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-2">Selesaikan Proses</h5>
                                    @if ($kekurangan)
                                        <p class="small text-danger mb-1">Belum dapat diselesaikan. Lengkapi:</p>
                                        <ul class="small text-danger ps-3">
                                            @foreach ($kekurangan as $pesan)
                                                <li>{{ $pesan }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="small text-muted">Semua dokumen lengkap. Setelah diselesaikan, status pendaftaran menjadi
                                            <strong>Selesai</strong> dan asesi dapat mengunduh SK hasil uji{{ $p->kompeten() ? ' dan e-sertifikat' : '' }} di profil.</p>
                                    @endif
                                    @if ($bisaKelola)
                                        <form method="POST" action="{{ route('penerbitan-sertifikat.selesaikan', $p) }}"
                                            onsubmit="return confirm('Selesaikan proses sertifikasi? Asesi akan dikabari melalui email.');">
                                            @csrf
                                            <button type="submit" class="btn btn-success w-100" @disabled($kekurangan)>
                                                <i class="mdi mdi-check-decagram-outline me-1"></i> Selesaikan
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endunless
                    </div>

                    <div class="col-lg-8">
                        {{-- Dokumen SK 1-4 --}}
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-1">Dokumen SK</h5>
                                <p class="small text-muted mb-3">Format PDF, maksimal {{ (int) ($maxKb / 1024) }} MB per berkas.
                                    Hanya dokumen bertanda <span class="badge bg-soft-success text-success">Dilihat asesi</span> yang dapat diunduh asesi setelah proses selesai.</p>

                                @foreach ($jenisDokumen as $jenis => $j)
                                    @php
                                        $row = $dokumen->get($jenis);
                                    @endphp
                                    <div class="border rounded p-3 mb-2">
                                        <div class="d-flex flex-wrap justify-content-between gap-2">
                                            <div>
                                                <div class="fw-semibold">{{ $loop->iteration }}. {{ $j['label'] }}</div>
                                                @if ($j['asesi'])
                                                    <span class="badge bg-soft-success text-success">Dilihat asesi</span>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary">Internal</span>
                                                @endif
                                            </div>
                                            @if ($row)
                                                <div class="text-end small">
                                                    <a href="{{ route('penerbitan-sertifikat.dokumen.berkas', [$p, $jenis]) }}" target="_blank" rel="noopener">
                                                        <i class="mdi mdi-file-pdf-box"></i> {{ $row->nama_file_asli }}
                                                    </a>
                                                    <div class="text-muted">{{ number_format($row->ukuran_file, 0, ',', '.') }} KB ·
                                                        {{ $row->updated_at?->timezone($zona)->format('d/m/Y H:i') }} · {{ $row->pengunggah->name ?? '-' }}</div>
                                                </div>
                                            @else
                                                <span class="small text-warning">Belum diunggah</span>
                                            @endif
                                        </div>
                                        @if ($bisaKelola)
                                            <form method="POST" action="{{ route('penerbitan-sertifikat.dokumen.unggah', [$p, $jenis]) }}"
                                                enctype="multipart/form-data" class="row g-2 mt-1 align-items-center">
                                                @csrf
                                                <div class="{{ $selesai ? 'col-md-5' : 'col-md-9' }}">
                                                    <input type="file" name="file" accept="application/pdf,.pdf" required class="form-control form-control-sm"
                                                        aria-label="Berkas {{ $j['label'] }}">
                                                </div>
                                                @if ($selesai)
                                                    <div class="col-md-4">
                                                        <input type="text" name="alasan" required maxlength="1000" class="form-control form-control-sm"
                                                            placeholder="Alasan penggantian" aria-label="Alasan penggantian">
                                                    </div>
                                                @endif
                                                <div class="col-md-3">
                                                    <button type="submit" class="btn btn-sm btn-primary w-100">
                                                        <i class="mdi mdi-upload me-1"></i> {{ $row ? 'Ganti' : 'Unggah' }}
                                                    </button>
                                                </div>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- E-Sertifikat (no. 5) --}}
                        @if ($p->kompeten())
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                        <div>
                                            <h5 class="card-title mb-1">{{ count($jenisDokumen) + 1 }}. E-Sertifikat</h5>
                                            <span class="badge bg-soft-success text-success">Dilihat asesi &amp; verifikasi publik</span>
                                        </div>
                                        @if ($sertifikat)
                                            <div class="text-end small">
                                                <span class="badge {{ $sertifikat->berlaku() ? 'bg-success' : 'bg-danger' }} mb-1">
                                                    {{ $sertifikat->berlaku() ? 'Berlaku' : 'Tidak Berlaku' }}
                                                </span>
                                                <div><a href="{{ route('penerbitan-sertifikat.sertifikat.berkas', $p) }}" target="_blank" rel="noopener">
                                                    <i class="mdi mdi-file-pdf-box"></i> {{ $sertifikat->nama_file_asli }}</a></div>
                                            </div>
                                        @endif
                                    </div>

                                    <form method="POST" action="{{ route('penerbitan-sertifikat.sertifikat.simpan', $p) }}" enctype="multipart/form-data">
                                        @csrf
                                        <fieldset @disabled(! $bisaKelola)>
                                            <div class="row g-3">
                                                <div class="col-md-12">
                                                    <label class="form-label" for="nama_pemegang">Nama pemegang</label>
                                                    <input type="text" id="nama_pemegang" name="nama_pemegang" required maxlength="255" class="form-control"
                                                        value="{{ old('nama_pemegang', $sertifikat?->nama_pemegang ?? $namaAwal) }}">
                                                    <div class="form-text">Samakan persis dengan nama pada e-sertifikat BNSP.</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label" for="no_blanko">Nomor blanko</label>
                                                    <input type="text" id="no_blanko" name="no_blanko" required maxlength="50" class="form-control font-monospace"
                                                        value="{{ old('no_blanko', $sertifikat?->no_blanko) }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label" for="no_sertifikat">Nomor sertifikat</label>
                                                    <input type="text" id="no_sertifikat" name="no_sertifikat" required maxlength="100" class="form-control font-monospace"
                                                        value="{{ old('no_sertifikat', $sertifikat?->no_sertifikat) }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label" for="tanggal_aktif">Tanggal aktif</label>
                                                    <input type="date" id="tanggal_aktif" name="tanggal_aktif" required class="form-control" value="{{ $aktifAwal }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label" for="tanggal_expired">Tanggal kedaluwarsa</label>
                                                    <input type="date" id="tanggal_expired" name="tanggal_expired" required class="form-control" value="{{ $expiredAwal }}">
                                                    <div class="form-text">Otomatis {{ $masaBerlaku }} tahun dari tanggal aktif; dapat diubah.</div>
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label" for="file_sertifikat">Berkas e-sertifikat (PDF){{ $sertifikat ? ' — kosongkan bila tidak diganti' : '' }}</label>
                                                    <input type="file" id="file_sertifikat" name="file" accept="application/pdf,.pdf" class="form-control" @required(! $sertifikat)>
                                                </div>
                                                @if ($selesai)
                                                    <div class="col-md-12">
                                                        <label class="form-label" for="alasan_sertifikat">Alasan perubahan</label>
                                                        <input type="text" id="alasan_sertifikat" name="alasan" required maxlength="1000" class="form-control">
                                                    </div>
                                                @endif
                                            </div>
                                            @if ($bisaKelola)
                                                <button type="submit" class="btn btn-primary mt-3">
                                                    <i class="mdi mdi-content-save me-1"></i> {{ $sertifikat ? 'Simpan Perubahan' : 'Terbitkan E-Sertifikat' }}
                                                </button>
                                            @endif
                                        </fieldset>
                                    </form>

                                    @if ($sertifikat && $bisaKelola)
                                        <hr>
                                        <form method="POST" action="{{ route('penerbitan-sertifikat.sertifikat.status', $p) }}" class="row g-2 align-items-end"
                                            onsubmit="return confirm('Ubah status sertifikat? Perubahan langsung tampil di verifikasi publik.');">
                                            @csrf
                                            <div class="col-md-3">
                                                <label class="form-label" for="status_sertifikat">Status sertifikat</label>
                                                <select id="status_sertifikat" name="status" class="form-select">
                                                    <option value="berlaku" @selected($sertifikat->status === 'berlaku')>Berlaku</option>
                                                    <option value="tidak_berlaku" @selected($sertifikat->status === 'tidak_berlaku')>Tidak berlaku (dicabut)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="alasan_status">Alasan</label>
                                                <input type="text" id="alasan_status" name="alasan" required maxlength="1000" class="form-control"
                                                    value="{{ $sertifikat->alasan_status }}">
                                            </div>
                                            <div class="col-md-3">
                                                <button type="submit" class="btn btn-outline-danger w-100">Ubah Status</button>
                                            </div>
                                            <div class="col-12 form-text">Sertifikat yang melewati tanggal kedaluwarsa otomatis tampil "Tidak Berlaku".</div>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Riwayat --}}
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Riwayat</h5>
                                @forelse ($p->riwayat as $r)
                                    <div class="d-flex gap-2 small mb-2">
                                        <div class="text-muted" style="min-width:120px;">{{ $r->created_at?->timezone($zona)->format('d/m/Y H:i') }}</div>
                                        <div>
                                            <strong>{{ $labelAksi[$r->aksi] ?? $r->aksi }}</strong>
                                            @if ($r->keterangan) — {{ $r->keterangan }} @endif
                                            <span class="text-muted">oleh {{ $r->user->name ?? '-' }}</span>
                                            @if (! empty($r->meta['alasan']))
                                                <div class="text-muted">Alasan: {{ $r->meta['alasan'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted small mb-0">Belum ada aktivitas.</p>
                                @endforelse
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
        // Tanggal kedaluwarsa otomatis +{{ $masaBerlaku }} tahun dari tanggal aktif (selama belum diubah manual).
        (function () {
            var aktif = document.getElementById('tanggal_aktif');
            var expired = document.getElementById('tanggal_expired');
            if (!aktif || !expired) return;
            var manual = !!expired.value;
            expired.addEventListener('input', function () { manual = true; });
            aktif.addEventListener('change', function () {
                if (manual || !aktif.value) return;
                var d = new Date(aktif.value + 'T00:00:00');
                d.setFullYear(d.getFullYear() + {{ $masaBerlaku }});
                var pad = function (n) { return String(n).padStart(2, '0'); };
                expired.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            });
        })();
    </script>
@endpush

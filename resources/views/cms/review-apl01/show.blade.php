@extends('layouts.app')

@section('title', 'Review FR.APL.01')

@php
    $pendaftaran = $dokumen->penilaianMuk->pendaftaran;
    $jadwal = $dokumen->penilaianMuk->jadwalAsesmen;
    $pribadi = $p['isian']['pribadi'];
    $pekerjaan = $p['isian']['pekerjaan'];
    $verifikasiLabel = ['memenuhi' => 'Memenuhi Syarat', 'tidak_memenuhi' => 'Tidak Memenuhi Syarat', 'tidak_ada' => 'Tidak Ada'];
    $nilaiPersyaratan = fn ($b) => old("persyaratan.{$b['id']}", $b['ada'] === false
        ? 'tidak_ada'
        : ($b['memenuhi_syarat'] === true ? 'memenuhi' : ($b['memenuhi_syarat'] === false ? 'tidak_memenuhi' : null)));
    $nilaiAdministratif = fn ($b) => old("administratif.{$b['jenis']}", $b['verifikasi']);
    $tujuan = $p['isian']['tujuan_asesmen'] ? ($tujuanLabel[$p['isian']['tujuan_asesmen']] ?? $p['isian']['tujuan_asesmen']) : '-';
    if ($p['isian']['tujuan_asesmen'] === 'lainnya' && $p['isian']['tujuan_lainnya']) {
        $tujuan .= ': ' . $p['isian']['tujuan_lainnya'];
    }
    $aksiLabel = [
        'mulai_diisi' => 'Mulai diisi asesi',
        'submit' => 'Dikirim asesi',
        'tanda_tangan_asesi' => 'Ditandatangani asesi',
        'tanda_tangan_dihapus' => 'Tanda tangan dihapus (isi berubah)',
        'unggah_bukti' => 'Unggah bukti',
        'hapus_bukti' => 'Hapus bukti',
        'review_admin_diterima' => 'Diterima admin',
        'review_admin_dikembalikan' => 'Dikembalikan admin',
    ];
@endphp

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'Management Sertifikasi',
            'subtitle' => 'Review FR.APL.01',
            'title' => 'Review FR.APL.01',
            'action' => null,
        ])

        <div class="container-fluid">
            <div class="page-content-wrapper">

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
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

                @if (! $bisaReview)
                    <div class="alert {{ $dokumen->status === 'selesai' ? 'alert-success' : 'alert-warning' }}">
                        @if ($dokumen->status === 'selesai')
                            FR.APL.01 sudah <strong>diterima</strong> oleh {{ $dokumen->direviewOlehUser->name ?? 'admin' }}
                            pada {{ $dokumen->tanggal_tanda_tangan_admin?->format('d M Y') }}.
                        @elseif ($dokumen->hasil_rekomendasi_admin === 'ditolak')
                            FR.APL.01 <strong>dikembalikan</strong> ke asesi untuk direvisi. Menunggu asesi mengirim ulang.
                        @else
                            FR.APL.01 belum dikirim asesi.
                        @endif
                    </div>
                @endif

                <form action="{{ route('review-apl01.update', $dokumen) }}" method="POST" id="form-review">
                    @csrf
                    <div class="row">
                        {{-- Kolom kiri: isi FR.APL.01 + verifikasi --}}
                        <div class="col-lg-8">

                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Bagian 1: Rincian Data Pemohon</h5>
                                    <h6 class="text-muted">a. Data Pribadi</h6>
                                    <table class="table table-borderless table-sm mb-3">
                                        <tbody>
                                            <tr><th style="width:220px;">Nama Lengkap</th><td>{{ $pribadi['nama_lengkap'] ?? '-' }}</td></tr>
                                            <tr><th>No. KTP/NIK/Paspor</th><td>{{ $pribadi['nik'] ?? '-' }}</td></tr>
                                            <tr><th>Tempat/Tgl. Lahir</th><td>{{ $pribadi['tempat_lahir'] ?? '-' }} / {{ $pribadi['tanggal_lahir'] ? \Illuminate\Support\Carbon::parse($pribadi['tanggal_lahir'])->format('d M Y') : '-' }}</td></tr>
                                            <tr><th>Jenis Kelamin</th><td>{{ ['laki_laki' => 'Laki-Laki', 'perempuan' => 'Wanita'][$pribadi['jenis_kelamin']] ?? '-' }}</td></tr>
                                            <tr><th>Kebangsaan</th><td>{{ $pribadi['kebangsaan'] ?? '-' }}</td></tr>
                                            <tr><th>Alamat Rumah</th><td>{{ $pribadi['alamat_rumah'] ?? '-' }} {{ $pribadi['kode_pos'] ? '('.$pribadi['kode_pos'].')' : '' }}</td></tr>
                                            <tr><th>Telp. Rumah / Kantor</th><td>{{ $pribadi['telp_rumah'] ?? '-' }} / {{ $pribadi['telp_kantor'] ?? '-' }}</td></tr>
                                            <tr><th>HP / E-Mail</th><td>{{ $pribadi['hp'] ?? '-' }} / {{ $pribadi['email'] ?? '-' }}</td></tr>
                                            <tr><th>Kualifikasi Pendidikan</th><td>{{ $pribadi['kualifikasi_pendidikan'] ?? '-' }}</td></tr>
                                        </tbody>
                                    </table>
                                    <h6 class="text-muted">b. Data Pekerjaan Sekarang</h6>
                                    <table class="table table-borderless table-sm mb-0">
                                        <tbody>
                                            <tr><th style="width:220px;">Institusi/Perusahaan</th><td>{{ $pekerjaan['nama_institusi'] ?? '-' }}</td></tr>
                                            <tr><th>Jabatan</th><td>{{ $pekerjaan['jabatan'] ?? '-' }}</td></tr>
                                            <tr><th>Alamat Kantor</th><td>{{ $pekerjaan['alamat_kantor'] ?? '-' }} {{ $pekerjaan['kode_pos'] ? '('.$pekerjaan['kode_pos'].')' : '' }}</td></tr>
                                            <tr><th>Telp / Fax / E-Mail</th><td>{{ $pekerjaan['telp'] ?? '-' }} / {{ $pekerjaan['fax'] ?? '-' }} / {{ $pekerjaan['email'] ?? '-' }}</td></tr>
                                            <tr><th>Kualifikasi Pendidikan</th><td>{{ $pekerjaan['kualifikasi_pendidikan'] ?? '-' }}</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Bagian 2: Data Sertifikasi</h5>
                                    <table class="table table-borderless table-sm mb-3">
                                        <tbody>
                                            <tr><th style="width:220px;">Skema</th><td>{{ $p['skema']['judul'] ?? '-' }} ({{ $p['skema']['jenis'] ?? '-' }})</td></tr>
                                            <tr><th>Nomor Skema</th><td>{{ $p['skema']['nomor'] ?? '-' }}</td></tr>
                                            <tr><th>Tujuan Asesmen</th><td>{{ $tujuan }}</td></tr>
                                        </tbody>
                                    </table>
                                    <table class="table table-bordered table-sm mb-0">
                                        <thead><tr><th style="width:50px;">No</th><th style="width:180px;">Kode Unit</th><th>Judul Unit</th></tr></thead>
                                        <tbody>
                                            @foreach ($p['unit_kompetensi'] as $i => $u)
                                                <tr><td class="text-center">{{ $i + 1 }}</td><td><code>{{ $u['kode_unit'] }}</code></td><td>{{ $u['judul_unit'] }}</td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-1">Bagian 3: Bukti Kelengkapan Pemohon</h5>
                                    <p class="text-muted small mb-3">Buka setiap berkas, lalu tentukan hasil verifikasinya.</p>

                                    <h6>1. Bukti Persyaratan Dasar Pemohon</h6>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:40px;">No</th>
                                                    <th>Bukti Persyaratan Dasar</th>
                                                    <th style="width:170px;">Berkas Asesi</th>
                                                    @foreach ($verifikasiLabel as $label)
                                                        <th class="text-center" style="width:95px;">{{ $label }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($p['bukti_persyaratan'] as $i => $b)
                                                    <tr>
                                                        <td class="text-center">{{ $i + 1 }}</td>
                                                        <td>{{ $b['deskripsi'] }}</td>
                                                        <td>
                                                            @if ($b['file'])
                                                                <a href="{{ route('review-apl01.bukti', [$dokumen, $b['id']]) }}" target="_blank" rel="noopener">
                                                                    <i class="mdi mdi-file-document-outline"></i> {{ \Illuminate\Support\Str::limit($b['file']['nama'], 24) }}
                                                                </a>
                                                            @else
                                                                <span class="text-muted small">Tidak diunggah</span>
                                                            @endif
                                                        </td>
                                                        @foreach (array_keys($verifikasiLabel) as $nilai)
                                                            <td class="text-center">
                                                                <input type="radio" class="form-check-input" name="persyaratan[{{ $b['id'] }}]" value="{{ $nilai }}"
                                                                    aria-label="{{ $verifikasiLabel[$nilai] }}"
                                                                    {{ $nilaiPersyaratan($b) === $nilai ? 'checked' : '' }} {{ $bisaReview ? '' : 'disabled' }}>
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <h6 class="mt-3">2. Bukti Administratif</h6>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:40px;">No</th>
                                                    <th>Bukti Administratif</th>
                                                    <th style="width:170px;">Berkas Pendaftaran</th>
                                                    @foreach ($verifikasiLabel as $label)
                                                        <th class="text-center" style="width:95px;">{{ $label }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($p['bukti_administratif'] as $i => $b)
                                                    <tr>
                                                        <td class="text-center">{{ $i + 1 }}</td>
                                                        <td>{{ $b['label'] }}</td>
                                                        <td>
                                                            @if ($berkas = $berkasPendaftaran->get($b['jenis']))
                                                                <a href="{{ $berkas->previewUrl() }}" target="_blank" rel="noopener">
                                                                    <i class="mdi mdi-file-document-outline"></i> {{ \Illuminate\Support\Str::limit($berkas->nama_file_asli, 24) }}
                                                                </a>
                                                            @else
                                                                <span class="text-muted small">Tidak diunggah</span>
                                                            @endif
                                                        </td>
                                                        @foreach (array_keys($verifikasiLabel) as $nilai)
                                                            <td class="text-center">
                                                                <input type="radio" class="form-check-input" name="administratif[{{ $b['jenis'] }}]" value="{{ $nilai }}"
                                                                    aria-label="{{ $verifikasiLabel[$nilai] }}"
                                                                    {{ $nilaiAdministratif($b) === $nilai ? 'checked' : '' }} {{ $bisaReview ? '' : 'disabled' }}>
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    {{-- Lampiran bukti yang relevan (informasi; dipakai sebagai bukti portofolio metode VP) --}}
                                    @php
                                        $relevanTerisi = collect($p['bukti_relevan'] ?? [])->filter(fn ($b) => $b['file'] || $b['link'])->values();
                                    @endphp
                                    @if ($relevanTerisi->isNotEmpty())
                                        <h6 class="mt-3">3. Lampiran Bukti yang relevan</h6>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width:40px;">No</th>
                                                        <th>Bukti</th>
                                                        <th style="width:260px;">Berkas / Link Asesi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($relevanTerisi as $i => $b)
                                                        <tr>
                                                            <td class="text-center">{{ $i + 1 }}</td>
                                                            <td>
                                                                {{ $b['nama'] }}
                                                                @unless ($b['template'])
                                                                    <span class="badge bg-light text-muted border ms-1">Tambahan asesi</span>
                                                                @endunless
                                                            </td>
                                                            <td>
                                                                @if ($b['file'])
                                                                    <a href="{{ route('review-apl01.relevan', [$dokumen, $b['id']]) }}" target="_blank" rel="noopener" class="d-block">
                                                                        <i class="mdi mdi-file-document-outline"></i> {{ \Illuminate\Support\Str::limit($b['file']['nama'] ?? 'Berkas', 30) }}
                                                                    </a>
                                                                @endif
                                                                @if ($b['link'])
                                                                    <a href="{{ $b['link'] }}" target="_blank" rel="noopener noreferrer nofollow" class="d-block text-truncate" style="max-width:250px;" title="{{ $b['link'] }}">
                                                                        <i class="mdi mdi-link-variant"></i> {{ $b['link'] }}
                                                                    </a>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            </div>

                        </div>

                        {{-- Kolom kanan: info + keputusan --}}
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Asesmen</h5>
                                    <table class="table table-borderless table-sm mb-0">
                                        <tbody>
                                            <tr><th style="width:110px;">Asesi</th><td>{{ $pendaftaran->asesi->name ?? '-' }}</td></tr>
                                            <tr><th>Asesor</th><td>{{ $jadwal?->asesor?->nama ?? '-' }}</td></tr>
                                            <tr><th>TUK</th><td>{{ $jadwal?->tuk?->title ?? '-' }}</td></tr>
                                            <tr><th>Tanggal</th><td>{{ $jadwal?->tanggal_asesmen?->format('d M Y') ?? '-' }}</td></tr>
                                            <tr><th>Dikirim</th><td>{{ $dokumen->submitted_at?->format('d M Y, H:i') ?? '-' }}</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-2">Tanda Tangan Pemohon</h5>
                                    @if ($dokumen->path_tanda_tangan_asesi)
                                        <img src="{{ route('review-apl01.tanda-tangan', [$dokumen, 'asesi']) }}" alt="Tanda tangan asesi"
                                            class="img-fluid border rounded bg-white" style="max-height:110px;">
                                        <div class="text-muted small mt-1">{{ $pribadi['nama_lengkap'] ?? '' }} · {{ $dokumen->tanggal_tanda_tangan_asesi?->format('d M Y') }}</div>
                                    @else
                                        <span class="text-muted small">Belum ditandatangani.</span>
                                    @endif
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Rekomendasi (diisi oleh LSP)</h5>

                                    @php $hasil = old('hasil', $dokumen->hasil_rekomendasi_admin); @endphp
                                    <p class="small text-muted mb-2">Berdasarkan ketentuan persyaratan dasar, maka pemohon:</p>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="hasil" id="hasil-diterima" value="diterima"
                                                {{ $hasil === 'diterima' ? 'checked' : '' }} {{ $bisaReview ? '' : 'disabled' }}>
                                            <label class="form-check-label text-success fw-semibold" for="hasil-diterima">Diterima sebagai peserta sertifikasi</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="hasil" id="hasil-ditolak" value="ditolak"
                                                {{ $hasil === 'ditolak' ? 'checked' : '' }} {{ $bisaReview ? '' : 'disabled' }}>
                                            <label class="form-check-label text-danger fw-semibold" for="hasil-ditolak">Tidak diterima (kembalikan ke asesi untuk revisi)</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="catatan">Catatan <span class="text-muted small" id="catatan-wajib" style="display:none;">(wajib bila tidak diterima)</span></label>
                                        <textarea class="form-control" id="catatan" name="catatan" rows="4" maxlength="2000"
                                            {{ $bisaReview ? '' : 'disabled' }}>{{ old('catatan', $dokumen->catatan_admin) }}</textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Admin LSP</label>
                                        <div>{{ $dokumen->direviewOlehUser->name ?? auth()->user()->name }}</div>
                                    </div>

                                    <div class="mb-3" id="blok-ttd">
                                        <label class="form-label">Tanda Tangan/Tanggal</label>
                                        @if ($bisaReview)
                                            <div class="border rounded bg-white" style="touch-action:none;">
                                                <canvas id="ttd-canvas" style="width:100%; height:160px; display:block; cursor:crosshair;"
                                                    aria-label="Area tanda tangan admin"></canvas>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1">
                                                <span class="text-muted small">Wajib bila diterima.</span>
                                                <button type="button" class="btn btn-link btn-sm p-0" id="ttd-hapus">Hapus goresan</button>
                                            </div>
                                            <input type="hidden" name="tanda_tangan" id="ttd-input">
                                        @elseif ($dokumen->path_tanda_tangan_admin)
                                            <img src="{{ route('review-apl01.tanda-tangan', [$dokumen, 'admin']) }}" alt="Tanda tangan admin"
                                                class="img-fluid border rounded bg-white" style="max-height:110px;">
                                            <div class="text-muted small mt-1">{{ $dokumen->tanggal_tanda_tangan_admin?->format('d M Y') }}</div>
                                        @else
                                            <div class="text-muted small">-</div>
                                        @endif
                                    </div>

                                    @if ($bisaReview)
                                        <button type="submit" class="btn btn-primary w-100" id="btn-simpan-review">
                                            Simpan Review
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Riwayat</h5>
                                    <ul class="list-unstyled small mb-0">
                                        @forelse ($dokumen->riwayat as $r)
                                            <li class="mb-2">
                                                <strong>{{ $aksiLabel[$r->aksi] ?? $r->aksi }}</strong>
                                                <div class="text-muted">{{ $r->created_at?->format('d M Y, H:i') }}
                                                    @if ($r->pelaku) · {{ $r->pelaku->name ?? $r->pelaku->nama ?? '' }} @endif
                                                </div>
                                                @if ($r->catatan)
                                                    <div>{{ $r->catatan }}</div>
                                                @endif
                                            </li>
                                        @empty
                                            <li class="text-muted">Belum ada riwayat.</li>
                                        @endforelse
                                    </ul>
                                </div>
                            </div>

                            <a href="{{ route('review-apl01.index') }}" class="btn btn-light w-100">&larr; Kembali ke Daftar</a>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
@endsection

@if ($bisaReview)
    @push('scripts')
        <script>
            // Kanvas tanda tangan sederhana (tanpa library): mouse, stylus, sentuh.
            (function () {
                const canvas = document.getElementById('ttd-canvas');
                const input = document.getElementById('ttd-input');
                const form = document.getElementById('form-review');
                const catatanWajib = document.getElementById('catatan-wajib');
                const ctx = canvas.getContext('2d');
                let menggambar = false, kosong = true;

                function ukuran() {
                    const rasio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * rasio;
                    canvas.height = canvas.offsetHeight * rasio;
                    ctx.setTransform(rasio, 0, 0, rasio, 0, 0);
                    ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
                    kosong = true;
                }
                function titik(e) {
                    const r = canvas.getBoundingClientRect();
                    return { x: e.clientX - r.left, y: e.clientY - r.top };
                }
                canvas.addEventListener('pointerdown', (e) => {
                    menggambar = true; canvas.setPointerCapture(e.pointerId);
                    const t = titik(e); ctx.beginPath(); ctx.moveTo(t.x, t.y);
                });
                canvas.addEventListener('pointermove', (e) => {
                    if (!menggambar) return;
                    const t = titik(e); ctx.lineTo(t.x, t.y); ctx.stroke(); kosong = false;
                });
                ['pointerup', 'pointercancel', 'pointerleave'].forEach((ev) => canvas.addEventListener(ev, () => { menggambar = false; }));
                document.getElementById('ttd-hapus').addEventListener('click', ukuran);

                document.querySelectorAll('input[name="hasil"]').forEach((r) => r.addEventListener('change', () => {
                    catatanWajib.style.display = r.value === 'ditolak' && r.checked ? 'inline' : 'none';
                }));

                form.addEventListener('submit', (e) => {
                    const hasil = form.querySelector('input[name="hasil"]:checked')?.value;
                    if (hasil === 'diterima' && kosong) {
                        e.preventDefault();
                        alert('Tanda tangan admin wajib diisi untuk menerima FR.APL.01.');
                        return;
                    }
                    if (hasil === 'ditolak' && !document.getElementById('catatan').value.trim()) {
                        e.preventDefault();
                        alert('Tuliskan catatan alasan FR.APL.01 dikembalikan.');
                        return;
                    }
                    const pesan = hasil === 'diterima'
                        ? 'Terima FR.APL.01 dan tandatangani? Dokumen akan terkunci.'
                        : 'Kembalikan FR.APL.01 ke asesi untuk direvisi?';
                    if (hasil && !confirm(pesan)) { e.preventDefault(); return; }
                    input.value = hasil === 'diterima' && !kosong ? canvas.toDataURL('image/png') : '';
                    document.getElementById('btn-simpan-review').disabled = true;
                });

                window.addEventListener('resize', ukuran);
                ukuran();
            })();
        </script>
    @endpush
@endif

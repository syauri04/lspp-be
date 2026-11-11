<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\PenerbitanSertifikasi;
use App\Models\Sertifikat;
use App\Services\Sertifikasi\PenerbitanSertifikasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penerbitan sertifikat setelah asesmen MUK selesai (CMS).
 * Semua user CMS dapat melihat; mengubah (unggah SK / e-sertifikat, selesaikan, ubah status)
 * hanya super admin (Gate "kelola-sertifikasi"). Logika & transisi di PenerbitanSertifikasiService.
 */
class PenerbitanSertifikatController extends Controller
{
    public const FILTER = [
        PenerbitanSertifikasi::MENUNGGU_DOKUMEN => 'Menunggu Dokumen',
        PenerbitanSertifikasi::SIAP_DISELESAIKAN => 'Siap Diselesaikan',
        PenerbitanSertifikasi::SELESAI => 'Selesai',
        'semua' => 'Semua',
    ];

    public function __construct(private PenerbitanSertifikasiService $service) {}

    public function index(Request $request): View
    {
        $filter = array_key_exists((string) $request->query('status'), self::FILTER) ? $request->query('status') : PenerbitanSertifikasi::MENUNGGU_DOKUMEN;
        $cari = trim((string) $request->query('q', ''));
        $cari = mb_substr($cari, 0, 100);

        $data = PenerbitanSertifikasi::query()
            ->with(['pendaftaran.asesi:id,name,email', 'pendaftaran.skemaSertifikasi:id,title', 'sertifikat:id,penerbitan_sertifikasi_id,no_sertifikat,no_blanko'])
            ->withCount('dokumen')
            ->when($filter !== 'semua', fn ($q) => $q->where('status', $filter))
            ->when($cari !== '', function ($q) use ($cari) {
                $normal = Sertifikat::normalisasi($cari);
                $q->where(fn ($w) => $w
                    ->whereHas('pendaftaran', fn ($p) => $p->where('kode_pendaftaran', $cari)
                        ->orWhereHas('asesi', fn ($a) => $a->where('name', 'like', '%'.addcslashes($cari, '%_\\').'%')))
                    ->orWhereHas('sertifikat', fn ($s) => $s->where('no_sertifikat_normal', $normal)->orWhere('no_blanko_normal', $normal)));
            })
            ->orderByRaw('status = ? DESC', [PenerbitanSertifikasi::SIAP_DISELESAIKAN])
            ->oldest('id')
            ->paginate(20)
            ->withQueryString();

        return view('cms.penerbitan-sertifikat.index', [
            'data' => $data,
            'filter' => $filter,
            'cari' => $cari,
            'jumlahJenis' => count(PenerbitanSertifikasi::jenisDokumen()),
        ]);
    }

    public function show(PenerbitanSertifikasi $penerbitan): View
    {
        $penerbitan->load([
            'pendaftaran.asesi', 'pendaftaran.skemaSertifikasi', 'penilaianMuk.jadwalAsesmen.asesor',
            'dokumen.pengunggah:id,name', 'sertifikat', 'riwayat.user:id,name', 'diselesaikanOleh:id,name',
        ]);

        return view('cms.penerbitan-sertifikat.show', [
            'p' => $penerbitan,
            'jenisDokumen' => PenerbitanSertifikasi::jenisDokumen(),
            'dokumen' => $penerbitan->dokumen->keyBy('jenis'),
            'kekurangan' => $this->service->kekurangan($penerbitan),
            'bisaKelola' => Gate::allows('kelola-sertifikasi'),
            'namaAwal' => $penerbitan->sertifikat ? null : $this->service->namaPemegangAwal($penerbitan),
            'masaBerlaku' => (int) config('sertifikasi.masa_berlaku_tahun'),
            'maxKb' => (int) config('sertifikasi.max_kb'),
        ]);
    }

    public function unggahDokumen(Request $request, PenerbitanSertifikasi $penerbitan, string $jenis): RedirectResponse
    {
        Gate::authorize('kelola-sertifikasi');
        abort_unless(array_key_exists($jenis, PenerbitanSertifikasi::jenisDokumen()), 404);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.config('sertifikasi.max_kb')],
            'alasan' => ['nullable', 'string', 'max:1000'],
        ], $this->pesanBerkas());

        $row = $this->service->unggahDokumen($penerbitan, $jenis, $validated['file'], $request->user(), $validated['alasan'] ?? null);

        return redirect()->route('penerbitan-sertifikat.show', $penerbitan)->with('success', $row->label().' tersimpan.');
    }

    public function simpanSertifikat(Request $request, PenerbitanSertifikasi $penerbitan): RedirectResponse
    {
        Gate::authorize('kelola-sertifikasi');

        $validated = $request->validate([
            'nama_pemegang' => ['required', 'string', 'max:255'],
            'no_blanko' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN.\/\- ]+$/u'],
            'no_sertifikat' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN.\/\- ]+$/u'],
            'tanggal_aktif' => ['required', 'date_format:Y-m-d'],
            'tanggal_expired' => ['required', 'date_format:Y-m-d', 'after:tanggal_aktif'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.config('sertifikasi.max_kb')],
            'alasan' => ['nullable', 'string', 'max:1000'],
        ], [
            ...$this->pesanBerkas(),
            'no_blanko.regex' => 'Nomor blanko hanya boleh berisi huruf, angka, titik, garis miring, dan tanda hubung.',
            'no_sertifikat.regex' => 'Nomor sertifikat hanya boleh berisi huruf, angka, titik, garis miring, dan tanda hubung.',
            'tanggal_expired.after' => 'Tanggal kedaluwarsa harus setelah tanggal aktif.',
        ]);

        $this->service->simpanSertifikat($penerbitan, $validated, $validated['file'] ?? null, $request->user(), $validated['alasan'] ?? null);

        return redirect()->route('penerbitan-sertifikat.show', $penerbitan)->with('success', 'E-Sertifikat tersimpan.');
    }

    public function ubahStatusSertifikat(Request $request, PenerbitanSertifikasi $penerbitan): RedirectResponse
    {
        Gate::authorize('kelola-sertifikasi');

        $validated = $request->validate([
            'status' => ['required', Rule::in([Sertifikat::BERLAKU, Sertifikat::TIDAK_BERLAKU])],
            'alasan' => ['required', 'string', 'max:1000'],
        ], ['alasan.required' => 'Tuliskan alasan perubahan status sertifikat.']);

        $this->service->ubahStatusSertifikat($penerbitan, $validated['status'], $validated['alasan'], $request->user());

        return redirect()->route('penerbitan-sertifikat.show', $penerbitan)->with('success', 'Status sertifikat diperbarui.');
    }

    public function selesaikan(Request $request, PenerbitanSertifikasi $penerbitan): RedirectResponse
    {
        Gate::authorize('kelola-sertifikasi');

        $this->service->selesaikan($penerbitan, $request->user());

        return redirect()->route('penerbitan-sertifikat.show', $penerbitan)
            ->with('success', 'Proses sertifikasi diselesaikan. Asesi telah dikabari dan dapat mengunduh dokumennya di profil.');
    }

    /** Lihat berkas SK (semua user CMS). */
    public function berkasDokumen(PenerbitanSertifikasi $penerbitan, string $jenis): StreamedResponse
    {
        $row = $penerbitan->dokumen()->where('jenis', $jenis)->first();

        return self::alirkan($row?->path_file, $row?->nama_file_asli);
    }

    /** Lihat berkas e-sertifikat (semua user CMS). */
    public function berkasSertifikat(PenerbitanSertifikasi $penerbitan): StreamedResponse
    {
        $row = $penerbitan->sertifikat()->first();

        return self::alirkan($row?->path_file, $row?->nama_file_asli);
    }

    /** Alirkan PDF dari disk privat tanpa cache; isi sudah divalidasi PDF saat unggah. */
    public static function alirkan(?string $path, ?string $nama, string $disposisi = 'inline'): StreamedResponse
    {
        $disk = Storage::disk(PenerbitanSertifikasiService::disk());
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, $nama ?: 'dokumen.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposisi);
    }

    private function pesanBerkas(): array
    {
        $mb = (int) (config('sertifikasi.max_kb') / 1024);

        return [
            'file.required' => 'Pilih berkas PDF.',
            'file.mimes' => 'Berkas harus berformat PDF.',
            'file.mimetypes' => 'Berkas harus berformat PDF.',
            'file.max' => "Ukuran berkas maksimal {$mb} MB.",
        ];
    }
}

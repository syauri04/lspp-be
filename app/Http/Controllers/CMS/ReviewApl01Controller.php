<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\DokumenPenilaian;
use App\Models\JenisDokumenMuk;
use App\Services\Muk\Dokumen\Apl01Handler;
use App\Services\Muk\DokumenWorkflow;
use App\Services\Muk\TandaTanganService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Review FR.APL.01 oleh admin LSP (CMS): verifikasi bukti, rekomendasi
 * diterima / dikembalikan, catatan, dan tanda tangan admin.
 * Transisi status lewat DokumenWorkflow::reviewAdmin.
 */
class ReviewApl01Controller extends Controller
{
    public const FILTER = ['menunggu_review', 'selesai', 'dikembalikan', 'semua'];

    public function __construct(
        private Apl01Handler $handler,
        private DokumenWorkflow $workflow,
    ) {}

    public function index(Request $request): View
    {
        $filter = in_array($request->query('status'), self::FILTER, true) ? $request->query('status') : 'menunggu_review';

        $dokumens = DokumenPenilaian::query()
            ->kode(JenisDokumenMuk::APL_01)
            ->with(['penilaianMuk.pendaftaran.asesi', 'penilaianMuk.pendaftaran.skemaSertifikasi', 'penilaianMuk.jadwalAsesmen.asesor'])
            ->when($filter === 'menunggu_review', fn ($q) => $q->where('status', DokumenPenilaian::STATUS_MENUNGGU_REVIEW))
            ->when($filter === 'selesai', fn ($q) => $q->where('status', DokumenPenilaian::STATUS_SELESAI))
            ->when($filter === 'dikembalikan', fn ($q) => $q->where('status', DokumenPenilaian::STATUS_DRAFT)->where('hasil_rekomendasi_admin', 'ditolak'))
            ->when($filter === 'semua', fn ($q) => $q->whereIn('status', DokumenPenilaian::STATUS_TERKIRIM)->orWhere(fn ($w) => $w->kode(JenisDokumenMuk::APL_01)->where('hasil_rekomendasi_admin', 'ditolak')))
            ->orderByRaw('submitted_at IS NULL, submitted_at ASC')
            ->paginate(20)
            ->withQueryString();

        return view('cms.review-apl01.index', compact('dokumens', 'filter'));
    }

    public function show(DokumenPenilaian $dokumen): View
    {
        $this->pastikanApl01($dokumen);
        $dokumen->load(['penilaianMuk.pendaftaran.asesi', 'penilaianMuk.pendaftaran.dokumen', 'penilaianMuk.jadwalAsesmen.asesor', 'penilaianMuk.jadwalAsesmen.tuk', 'riwayat.pelaku']);

        $payload = $this->handler->payload($dokumen, 'admin');
        $berkasPendaftaran = $dokumen->penilaianMuk->pendaftaran->dokumen->keyBy('jenis_dokumen');

        return view('cms.review-apl01.show', [
            'dokumen' => $dokumen,
            'p' => $payload,
            'berkasPendaftaran' => $berkasPendaftaran,
            'bisaReview' => $dokumen->status === DokumenPenilaian::STATUS_MENUNGGU_REVIEW,
            'tujuanLabel' => Apl01Handler::TUJUAN_LABEL,
        ]);
    }

    public function update(Request $request, DokumenPenilaian $dokumen): RedirectResponse
    {
        $this->pastikanApl01($dokumen);
        [$idBukti, $jenisAdministratif] = $this->handler->butirVerifikasi($dokumen);

        $validated = $request->validate([
            'hasil' => ['required', Rule::in(['diterima', 'ditolak'])],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'tanda_tangan' => ['nullable', 'string', 'max:'.(int) (config('muk.tanda_tangan.max_bytes') * 1.4)],
            'persyaratan' => [count($idBukti) ? 'required' : 'nullable', 'array:'.implode(',', $idBukti)],
            'persyaratan.*' => ['required', Rule::in(Apl01Handler::VERIFIKASI)],
            'administratif' => [count($jenisAdministratif) ? 'required' : 'nullable', 'array:'.implode(',', $jenisAdministratif)],
            'administratif.*' => ['required', Rule::in(Apl01Handler::VERIFIKASI)],
        ], [
            'persyaratan.required' => 'Verifikasi semua bukti persyaratan dasar.',
            'administratif.required' => 'Verifikasi semua bukti administratif.',
            'hasil.required' => 'Pilih rekomendasi Diterima atau Tidak diterima.',
        ]);

        // Semua butir wajib diverifikasi.
        $kurang = collect($idBukti)->diff(array_keys($validated['persyaratan'] ?? []))->isNotEmpty()
            || collect($jenisAdministratif)->diff(array_keys($validated['administratif'] ?? []))->isNotEmpty();
        if ($kurang) {
            return back()->withInput()->withErrors(['persyaratan' => 'Verifikasi semua bukti persyaratan dasar dan bukti administratif.']);
        }

        $this->workflow->reviewAdmin(
            $dokumen->loadMissing('jenisDokumen'),
            $this->handler,
            $validated['hasil'],
            $validated['catatan'] ?? null,
            $validated['tanda_tangan'] ?? null,
            fn (DokumenPenilaian $d) => $this->handler->simpanVerifikasiAdmin($d, $validated['persyaratan'] ?? [], $validated['administratif'] ?? []),
            $request->user(),
        );

        return redirect()
            ->route('review-apl01.show', $dokumen)
            ->with('success', $validated['hasil'] === 'diterima'
                ? 'FR.APL.01 diterima dan ditandatangani.'
                : 'FR.APL.01 dikembalikan ke asesi untuk direvisi.');
    }

    /** Berkas bukti persyaratan dasar yang diunggah asesi. */
    public function bukti(DokumenPenilaian $dokumen, int $bukti): StreamedResponse
    {
        $this->pastikanApl01($dokumen);
        $row = $dokumen->verifikasiPersyaratan()->where('bukti_persyaratan_dasar_id', $bukti)->first();
        abort_unless($row?->path_file && Storage::disk(Apl01Handler::disk())->exists($row->path_file), 404);

        return Storage::disk(Apl01Handler::disk())->response($row->path_file, $row->nama_file_asli, [
            'Content-Type' => $row->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            // Berkas unggahan: sandbox untuk gambar. PDF dikecualikan karena penampil PDF
            // Chrome menolak dokumen ber-CSP sandbox; tipe berkas sudah divalidasi dari isinya.
            ...($row->mime_type === 'application/pdf' ? [] : ['Content-Security-Policy' => 'sandbox']),
        ], 'inline');
    }

    /** Gambar tanda tangan (asesi/admin) untuk ditampilkan di halaman review. */
    public function tandaTangan(DokumenPenilaian $dokumen, string $pihak, TandaTanganService $ttd): StreamedResponse
    {
        $this->pastikanApl01($dokumen);
        abort_unless(in_array($pihak, ['asesi', 'admin'], true), 404);

        $path = $dokumen->{DokumenWorkflow::kolomTtd($pihak)[0]};
        abort_unless($ttd->ada($path), 404);

        return Storage::disk($ttd->disk())->response($path, "ttd-{$pihak}.png", [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function pastikanApl01(DokumenPenilaian $dokumen): void
    {
        abort_unless($dokumen->jenisDokumen?->kode === JenisDokumenMuk::APL_01, 404);
    }
}

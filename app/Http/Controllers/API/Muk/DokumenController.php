<?php

namespace App\Http\Controllers\API\Muk;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureMukRole;
use App\Http\Resources\Muk\DokumenPenilaianResource;
use App\Models\DokumenPenilaian;
use App\Services\Muk\AksesDokumen;
use App\Services\Muk\Dokumen\DokumenHandler;
use App\Services\Muk\Dokumen\MapaHandler;
use App\Services\Muk\DokumenResolver;
use App\Services\Muk\DokumenWorkflow;
use App\Services\Muk\TandaTanganService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Endpoint generik dokumen MUK: /api/muk/penilaian/{penilaian}/dokumen/{slug}
 * (slug = "apl-01", nanti "apl-02", "mapa-01", ...). Logika isi ada di handler,
 * transisi status di DokumenWorkflow, otorisasi di DokumenPenilaianPolicy.
 */
class DokumenController extends Controller
{
    public function __construct(
        private DokumenResolver $resolver,
        private DokumenWorkflow $workflow,
    ) {}

    public function show(Request $request, int $penilaian, string $slug): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->resolver->temukan($request->user(), $penilaian, $slug);
        Gate::authorize('view', $dokumen);

        return self::respons($request, $dokumen, $handler);
    }

    public function update(Request $request, int $penilaian, string $slug): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->resolver->temukan($request->user(), $penilaian, $slug);
        Gate::authorize('isi', $dokumen);

        $validated = $request->validate($handler->rulesSimpan($dokumen));
        $dokumen = $this->workflow->simpan($dokumen, $handler, $validated, $request->user());

        return self::respons($request, $dokumen, $handler);
    }

    public function tandaTangan(Request $request, int $penilaian, string $slug): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->resolver->temukan($request->user(), $penilaian, $slug);
        Gate::authorize('isi', $dokumen);
        abort_unless($handler->butuhTandaTangan(), 404);

        $request->validate(['tanda_tangan' => ['required', 'string', 'max:'.(int) (config('muk.tanda_tangan.max_bytes') * 1.4)]]);
        $dokumen = $this->workflow->tandaTanganPengisi($dokumen, $handler, (string) $request->input('tanda_tangan'), $request->user());

        return self::respons($request, $dokumen, $handler);
    }

    public function submit(Request $request, int $penilaian, string $slug): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->resolver->temukan($request->user(), $penilaian, $slug);
        Gate::authorize('isi', $dokumen);

        $dokumen = $this->workflow->submit($dokumen, $handler, $request->user());

        return self::respons($request, $dokumen, $handler);
    }

    /**
     * Tinjauan asesor (mis. FR.APL.02): rekomendasi, catatan, tanda tangan.
     * Positif -> selesai; negatif -> dikembalikan ke asesi untuk revisi.
     */
    public function review(Request $request, int $penilaian, string $slug, AksesDokumen $akses): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->resolver->temukan($request->user(), $penilaian, $slug);
        Gate::authorize('review', $dokumen);

        $kolom = DokumenWorkflow::PENINJAU[$handler->peninjau()];
        $validated = $request->validate([
            'hasil' => ['required', Rule::in([$kolom['positif'], $kolom['negatif']])],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'tanda_tangan' => ['nullable', 'string', 'max:'.(int) (config('muk.tanda_tangan.max_bytes') * 1.4)],
        ]);

        $dokumen = $this->workflow->tinjau(
            $dokumen,
            $handler,
            $handler->peninjau(),
            $validated['hasil'],
            $validated['catatan'] ?? null,
            $validated['tanda_tangan'] ?? null,
            $request->user(),
            cekPrasyarat: function (DokumenPenilaian $d) use ($akses, $handler, $request) {
                // Cek ulang di dalam lock (mis. APL.01 sudah diterima admin).
                $alasan = $akses->alasanTidakBisaDireview(
                    $d,
                    $d->penilaianMuk->dokumenPenilaian()->with('jenisDokumen')->get(),
                    EnsureMukRole::peranDari($request->user()),
                    $handler->peninjau(),
                );
                if ($alasan) {
                    throw new ConflictHttpException($alasan);
                }
            },
        );

        return self::respons($request, $dokumen, $handler);
    }

    /** Gambar tanda tangan, hanya untuk user yang boleh melihat dokumen. Tidak di-cache. */
    public function gambarTandaTangan(Request $request, int $penilaian, string $slug, string $pihak, TandaTanganService $ttd): StreamedResponse
    {
        validator(['pihak' => $pihak], ['pihak' => [Rule::in(TandaTanganService::PIHAK)]])->validate();

        [$dokumen] = $this->resolver->temukan($request->user(), $penilaian, $slug);
        Gate::authorize('view', $dokumen);

        $path = $dokumen->{DokumenWorkflow::kolomTtd($pihak)[0]};
        abort_unless($ttd->ada($path), 404);

        return Storage::disk($ttd->disk())->response($path, "tanda-tangan-{$pihak}.png", [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Gambar tanda tangan penandatangan template MAPA (konfirmasi / penyusun & validator).
     * Setelah dokumen selesai, yang dikirim adalah salinan di snapshot.
     */
    public function gambarPenandatangan(Request $request, int $penilaian, string $slug, int $penandatangan, TandaTanganService $ttd): StreamedResponse
    {
        [$dokumen, $handler] = $this->resolver->temukan($request->user(), $penilaian, $slug);
        Gate::authorize('view', $dokumen);
        abort_unless($handler instanceof MapaHandler, 404);

        $path = $handler->pathTtdPenandatangan($dokumen, $penandatangan);
        abort_unless($ttd->ada($path), 404);

        return Storage::disk($ttd->disk())->response($path, "tanda-tangan-{$penandatangan}.png", [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function respons(Request $request, DokumenPenilaian $dokumen, DokumenHandler $handler): DokumenPenilaianResource
    {
        return new DokumenPenilaianResource(
            $dokumen->fresh(['jenisDokumen', 'penilaianMuk']),
            $handler,
            EnsureMukRole::peranDari($request->user()),
        );
    }
}

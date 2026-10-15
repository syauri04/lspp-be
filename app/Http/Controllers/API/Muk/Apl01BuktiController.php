<?php

namespace App\Http\Controllers\API\Muk;

use App\Http\Controllers\Controller;
use App\Http\Resources\Muk\DokumenPenilaianResource;
use App\Models\DokumenPenilaian;
use App\Services\Muk\Dokumen\Apl01Handler;
use App\Services\Muk\DokumenResolver;
use App\Services\Muk\DokumenWorkflow;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Berkas bukti persyaratan dasar FR.APL.01:
 * /api/muk/penilaian/{penilaian}/dokumen/apl-01/bukti/{bukti}
 */
class Apl01BuktiController extends Controller
{
    private const SLUG = 'apl-01';

    public function __construct(
        private DokumenResolver $resolver,
        private DokumenWorkflow $workflow,
    ) {}

    public function store(Request $request, int $penilaian, int $bukti): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->dokumen($request, $penilaian, $bukti);
        Gate::authorize('isi', $dokumen);

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', Apl01Handler::BUKTI_MIMES), 'max:'.Apl01Handler::BUKTI_MAX_KB],
        ], [
            'file.mimes' => 'Berkas harus berformat PDF, JPG, atau PNG.',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $path = $handler->tulisBerkasBukti($dokumen, $bukti, $file);

        try {
            $dokumen = $this->workflow->ubahIsi(
                $dokumen,
                $handler,
                fn (DokumenPenilaian $d, array &$hapus) => $handler->simpanBukti($d, $bukti, $path, $file, $hapus),
                $request->user(),
                'unggah_bukti',
                "Bukti persyaratan #{$bukti}",
            );
        } catch (\Throwable $e) {
            Storage::disk(Apl01Handler::disk())->delete($path);
            throw $e;
        }

        return DokumenController::respons($request, $dokumen, $handler);
    }

    public function destroy(Request $request, int $penilaian, int $bukti): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->dokumen($request, $penilaian, $bukti);
        Gate::authorize('isi', $dokumen);

        $dokumen = $this->workflow->ubahIsi(
            $dokumen,
            $handler,
            fn (DokumenPenilaian $d, array &$hapus) => $handler->hapusBukti($d, $bukti, $hapus),
            $request->user(),
            'hapus_bukti',
            "Bukti persyaratan #{$bukti}",
        );

        return DokumenController::respons($request, $dokumen, $handler);
    }

    /** Lihat berkas: hanya user yang boleh melihat dokumen; tidak di-cache. */
    public function show(Request $request, int $penilaian, int $bukti): StreamedResponse
    {
        [$dokumen] = $this->dokumen($request, $penilaian, $bukti);
        Gate::authorize('view', $dokumen);

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

    /** @return array{0: DokumenPenilaian, 1: Apl01Handler} */
    private function dokumen(Request $request, int $penilaian, int $bukti): array
    {
        [$dokumen, $handler] = $this->resolver->temukan($request->user(), $penilaian, self::SLUG);
        abort_unless($handler instanceof Apl01Handler && $handler->buktiMilikSkema($dokumen, $bukti), 404);

        return [$dokumen, $handler];
    }
}

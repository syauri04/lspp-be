<?php

namespace App\Http\Controllers\API\Muk;

use App\Http\Controllers\Controller;
use App\Http\Resources\Muk\DokumenPenilaianResource;
use App\Models\DokumenPenilaian;
use App\Services\Muk\Dokumen\Apl02Handler;
use App\Services\Muk\DokumenResolver;
use App\Services\Muk\DokumenWorkflow;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Berkas "bukti yang relevan" FR.APL.02:
 *  POST   /penilaian/{p}/dokumen/apl-02/elemen/{elemen}/bukti   (file, bukti_id? = ganti berkas baris tsb)
 *  DELETE /penilaian/{p}/dokumen/apl-02/bukti/{bukti}/file
 *  GET    /penilaian/{p}/dokumen/apl-02/bukti/{bukti}/file
 * Link bukti disimpan lewat PATCH dokumen bersama jawaban K/BK.
 */
class Apl02BuktiController extends Controller
{
    private const SLUG = 'apl-02';

    public function __construct(
        private DokumenResolver $resolver,
        private DokumenWorkflow $workflow,
    ) {}

    public function store(Request $request, int $penilaian, int $elemen): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->dokumen($request, $penilaian);
        abort_unless($handler->elemenMilikSkema($dokumen, $elemen), 404);
        Gate::authorize('isi', $dokumen);

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', Apl02Handler::BUKTI_MIMES), 'max:'.Apl02Handler::BUKTI_MAX_KB],
            'bukti_id' => ['nullable', 'integer'],
        ], [
            'file.mimes' => 'Berkas harus berformat PDF, JPG, atau PNG.',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $buktiId = $request->integer('bukti_id') ?: null;
        $path = $handler->tulisBerkas($dokumen, $elemen, $file);

        try {
            $dokumen = $this->workflow->ubahIsi(
                $dokumen,
                $handler,
                fn (DokumenPenilaian $d, array &$hapus) => $handler->simpanBerkas($d, $elemen, $buktiId, $path, $file, $hapus),
                $request->user(),
                'unggah_bukti',
                "Elemen #{$elemen}",
            );
        } catch (\Throwable $e) {
            Storage::disk(Apl02Handler::disk())->delete($path);
            throw $e;
        }

        return DokumenController::respons($request, $dokumen, $handler);
    }

    public function destroy(Request $request, int $penilaian, int $bukti): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->dokumen($request, $penilaian);
        abort_unless($handler->buktiMilikDokumen($dokumen, $bukti), 404);
        Gate::authorize('isi', $dokumen);

        $dokumen = $this->workflow->ubahIsi(
            $dokumen,
            $handler,
            fn (DokumenPenilaian $d, array &$hapus) => $handler->hapusBerkas($d, $bukti, $hapus),
            $request->user(),
            'hapus_bukti',
            "Bukti #{$bukti}",
        );

        return DokumenController::respons($request, $dokumen, $handler);
    }

    /** Lihat berkas: hanya user yang boleh melihat dokumen; tidak di-cache. */
    public function show(Request $request, int $penilaian, int $bukti): StreamedResponse
    {
        [$dokumen, $handler] = $this->dokumen($request, $penilaian);
        Gate::authorize('view', $dokumen);

        $row = $handler->buktiMilikDokumen($dokumen, $bukti);
        abort_unless($row?->path_file && Storage::disk(Apl02Handler::disk())->exists($row->path_file), 404);

        return Storage::disk(Apl02Handler::disk())->response($row->path_file, $row->nama_file_asli, [
            'Content-Type' => $row->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            // Berkas unggahan: sandbox untuk gambar. PDF dikecualikan karena penampil PDF
            // Chrome menolak dokumen ber-CSP sandbox; tipe berkas sudah divalidasi dari isinya.
            ...($row->mime_type === 'application/pdf' ? [] : ['Content-Security-Policy' => 'sandbox']),
        ], 'inline');
    }

    /** @return array{0: DokumenPenilaian, 1: Apl02Handler} */
    private function dokumen(Request $request, int $penilaian): array
    {
        [$dokumen, $handler] = $this->resolver->temukan($request->user(), $penilaian, self::SLUG);
        abort_unless($handler instanceof Apl02Handler, 404);

        return [$dokumen, $handler];
    }
}

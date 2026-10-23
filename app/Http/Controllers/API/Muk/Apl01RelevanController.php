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
 * Lampiran "bukti yang relevan" FR.APL.01 (asesi; berkas dapat dilihat asesor untuk FR.IA.08):
 *  POST   /penilaian/{p}/dokumen/apl-01/relevan                 { nama }        tambah baris asesi
 *  PATCH  /penilaian/{p}/dokumen/apl-01/relevan/{id}            { nama?, link? }
 *  DELETE /penilaian/{p}/dokumen/apl-01/relevan/{id}            hapus baris asesi (bukan butir template)
 *  POST   /penilaian/{p}/dokumen/apl-01/relevan/{id}/file       { file }        unggah / ganti berkas
 *  DELETE /penilaian/{p}/dokumen/apl-01/relevan/{id}/file
 *  GET    /penilaian/{p}/dokumen/apl-01/relevan/{id}/file
 */
class Apl01RelevanController extends Controller
{
    private const SLUG = 'apl-01';

    public function __construct(
        private DokumenResolver $resolver,
        private DokumenWorkflow $workflow,
    ) {}

    public function store(Request $request, int $penilaian): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->dokumen($request, $penilaian);
        Gate::authorize('isi', $dokumen);
        $data = $request->validate(['nama' => ['required', 'string', 'max:500']]);

        return $this->ubah($request, $dokumen, $handler, fn (DokumenPenilaian $d) => $handler->tambahRelevan($d, $data['nama']), 'tambah_bukti_relevan', $data['nama']);
    }

    public function update(Request $request, int $penilaian, int $relevan): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->dokumen($request, $penilaian, $relevan);
        Gate::authorize('isi', $dokumen);
        $data = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:500'],
            'link' => ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https'],
        ], ['link.url' => 'Link harus diawali http:// atau https://']);

        return $this->ubah($request, $dokumen, $handler, fn (DokumenPenilaian $d) => $handler->ubahRelevan($d, $relevan, $data), 'ubah_bukti_relevan', "Bukti relevan #{$relevan}");
    }

    public function destroy(Request $request, int $penilaian, int $relevan): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->dokumen($request, $penilaian, $relevan);
        Gate::authorize('isi', $dokumen);

        return $this->ubah($request, $dokumen, $handler, fn (DokumenPenilaian $d, array &$hapus) => $handler->hapusRelevan($d, $relevan, $hapus), 'hapus_bukti_relevan', "Bukti relevan #{$relevan}");
    }

    public function unggah(Request $request, int $penilaian, int $relevan): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->dokumen($request, $penilaian, $relevan);
        Gate::authorize('isi', $dokumen);
        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', Apl01Handler::BUKTI_MIMES), 'max:'.Apl01Handler::BUKTI_MAX_KB],
        ], [
            'file.mimes' => 'Berkas harus berformat PDF, JPG, atau PNG.',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $path = $handler->tulisBerkasRelevan($dokumen, $file);

        try {
            return $this->ubah(
                $request,
                $dokumen,
                $handler,
                fn (DokumenPenilaian $d, array &$hapus) => $handler->simpanBerkasRelevan($d, $relevan, $path, $file, $hapus),
                'unggah_bukti_relevan',
                "Bukti relevan #{$relevan}",
            );
        } catch (\Throwable $e) {
            Storage::disk(Apl01Handler::disk())->delete($path);
            throw $e;
        }
    }

    public function hapusBerkas(Request $request, int $penilaian, int $relevan): DokumenPenilaianResource
    {
        [$dokumen, $handler] = $this->dokumen($request, $penilaian, $relevan);
        Gate::authorize('isi', $dokumen);

        return $this->ubah($request, $dokumen, $handler, fn (DokumenPenilaian $d, array &$hapus) => $handler->hapusBerkasRelevan($d, $relevan, $hapus), 'hapus_berkas_relevan', "Bukti relevan #{$relevan}");
    }

    /** Lihat berkas: user yang boleh melihat FR.APL.01 (asesi pemilik, asesor setelah dikirim); tidak di-cache. */
    public function show(Request $request, int $penilaian, int $relevan): StreamedResponse
    {
        [$dokumen, $handler] = $this->dokumen($request, $penilaian, $relevan);
        Gate::authorize('view', $dokumen);

        $row = $handler->relevanMilikDokumen($dokumen, $relevan);
        abort_unless($row?->path_file && Storage::disk(Apl01Handler::disk())->exists($row->path_file), 404);

        return Storage::disk(Apl01Handler::disk())->response($row->path_file, $row->nama_file_asli, [
            'Content-Type' => $row->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            // Sandbox untuk gambar; PDF dikecualikan (penampil PDF Chrome menolak CSP sandbox).
            ...($row->mime_type === 'application/pdf' ? [] : ['Content-Security-Policy' => 'sandbox']),
        ], 'inline');
    }

    private function ubah(Request $request, DokumenPenilaian $dokumen, Apl01Handler $handler, \Closure $ubah, string $aksi, string $catatan): DokumenPenilaianResource
    {
        $dokumen = $this->workflow->ubahIsi($dokumen, $handler, $ubah, $request->user(), $aksi, mb_substr($catatan, 0, 200));

        return DokumenController::respons($request, $dokumen, $handler);
    }

    /** @return array{0: DokumenPenilaian, 1: Apl01Handler} */
    private function dokumen(Request $request, int $penilaian, ?int $relevan = null): array
    {
        [$dokumen, $handler] = $this->resolver->temukan($request->user(), $penilaian, self::SLUG);
        abort_unless($handler instanceof Apl01Handler, 404);
        abort_if($relevan !== null && ! $handler->relevanMilikDokumen($dokumen, $relevan), 404);

        return [$dokumen, $handler];
    }
}

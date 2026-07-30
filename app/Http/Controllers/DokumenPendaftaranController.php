<?php

namespace App\Http\Controllers;

use App\Models\Asesi;
use App\Models\DokumenPendaftaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DokumenPendaftaranController extends Controller
{
    /**
     * GET /dokumen/{dokumen}/preview
     * Middleware 'signed' di route sudah pastikan URL tidak dimodifikasi & belum expired.
     * Di sini kita cek tambahan: yang minta memang pemilik dokumen (Asesi) atau admin (User role admin).
     */
    public function preview(Request $request, DokumenPendaftaran $dokumen): StreamedResponse
    {
        $pendaftaran = $dokumen->pendaftaranSertifikasi;
        $user = $request->user();

        $pemilikYangSah = $user instanceof Asesi && $user->id === $pendaftaran->asesi_id;
        $adminYangReview = $user instanceof \App\Models\User && $user->hasRole(['admin', 'super-admin']);

        abort_unless($pemilikYangSah || $adminYangReview, 403, 'Anda tidak memiliki akses ke dokumen ini.');

        abort_unless(
            Storage::disk('local')->exists($dokumen->path_file),
            404,
            'File tidak ditemukan.'
        );

        return Storage::disk('local')->response(
            $dokumen->path_file,
            $dokumen->nama_file_asli,
            ['Content-Type' => $dokumen->mime_type]
        );
    }
}

<?php

namespace App\Http\Controllers\API\Pendaftaran;

use App\Http\Controllers\CMS\PenerbitanSertifikatController;
use App\Http\Controllers\Controller;
use App\Models\Asesi;
use App\Models\PendaftaranSertifikasi;
use App\Models\PenerbitanSertifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/pendaftaran-sertifikasi/{kode}/sertifikasi/{berkas}
 * Unduh dokumen hasil sertifikasi milik asesi sendiri, setelah proses diselesaikan super admin.
 * {berkas}: "e-sertifikat" atau jenis SK yang ditandai 'asesi' => true di config/sertifikasi.php.
 * SK internal (komite teknis dll.) tidak punya jalur di sini (404).
 */
class SertifikasiAsesiController extends Controller
{
    public const E_SERTIFIKAT = 'e-sertifikat';

    public function unduh(Request $request, PendaftaranSertifikasi $pendaftaran, string $berkas): StreamedResponse
    {
        $asesi = $request->user();
        abort_unless($asesi instanceof Asesi && $pendaftaran->asesi_id === $asesi->id, 403, 'Anda tidak memiliki akses ke pendaftaran ini.');

        $penerbitan = $pendaftaran->penerbitanSertifikasi()->first();
        abort_unless($penerbitan?->selesai(), 404);

        $kode = Str::upper(Str::substr($pendaftaran->kode_pendaftaran, 0, 8));

        if ($berkas === self::E_SERTIFIKAT) {
            $s = $penerbitan->sertifikat()->first();

            return PenerbitanSertifikatController::alirkan($s?->path_file, "E-Sertifikat-{$kode}.pdf", 'attachment');
        }

        abort_unless(in_array($berkas, PenerbitanSertifikasi::jenisUntukAsesi(), true), 404);
        $row = $penerbitan->dokumen()->where('jenis', $berkas)->first();

        return PenerbitanSertifikatController::alirkan($row?->path_file, Str::studly($berkas)."-{$kode}.pdf", 'attachment');
    }
}

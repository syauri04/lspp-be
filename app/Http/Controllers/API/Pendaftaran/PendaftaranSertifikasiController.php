<?php

namespace App\Http\Controllers\API\Pendaftaran;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pendaftaran\StorePendaftaranSertifikasiRequest;
use App\Models\Asesi;
use App\Models\PendaftaranSertifikasi;
use App\Models\SkemaSertifikasi;
use App\Models\User;
use App\Notifications\PendaftaranMasukNotifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PendaftaranSertifikasiController extends Controller
{
    /**
     * GET /api/pendaftaran-sertifikasi
     * List pendaftaran milik asesi yang sedang login (untuk halaman Profil).
     */
    public function index(Request $request): JsonResponse
    {
        $asesi = $this->asesiAtauTolak($request);

        $pendaftaran = PendaftaranSertifikasi::with('skemaSertifikasi:id,title')
            ->where('asesi_id', $asesi->id)
            ->latest()
            ->paginate(10);

        return response()->json($pendaftaran);
    }

    /**
     * GET /api/pendaftaran-sertifikasi/{kode_pendaftaran}
     * Detail + timeline status. Route model binding pakai kode_pendaftaran.
     */
    public function show(Request $request, PendaftaranSertifikasi $pendaftaran): JsonResponse
    {
        $asesi = $this->asesiAtauTolak($request);

        abort_unless($pendaftaran->asesi_id === $asesi->id, 403, 'Anda tidak memiliki akses ke pendaftaran ini.');

        $pendaftaran->load([
            'skemaSertifikasi:id,title',
            'riwayatStatus' => fn($q) => $q->orderBy('created_at'),
            'pembayaran',
        ]);

        return response()->json($pendaftaran);
    }

    /**
     * POST /api/pendaftaran-sertifikasi
     * Submit pendaftaran baru beserta 5 dokumen wajib.
     */
    public function store(StorePendaftaranSertifikasiRequest $request): JsonResponse
    {
        $asesi = $this->asesiAtauTolak($request);
        $skema = SkemaSertifikasi::findOrFail($request->skema_sertifikasi_id);

        $pendaftaran = DB::transaction(function () use ($request, $asesi, $skema) {
            $pendaftaran = PendaftaranSertifikasi::create([
                'kode_pendaftaran' => (string) Str::uuid(),
                'asesi_id' => $asesi->id,
                'skema_sertifikasi_id' => $skema->id,
                'status' => 'submitted',
                'harga_snapshot' => $skema->amount,
                'submitted_at' => now(),
            ]);

            foreach (['ktp', 'ijazah', 'portfolio', 'pas_foto', 'cv'] as $jenis) {
                $file = $request->file($jenis);

                // Path per kode_pendaftaran, bukan per asesi, supaya dokumen
                // pendaftaran sebelumnya tidak tertimpa saat asesi daftar skema lain.
                $path = $file->store("dokumen/{$pendaftaran->kode_pendaftaran}", 'local');

                $pendaftaran->dokumen()->create([
                    'jenis_dokumen' => $jenis,
                    'nama_file_asli' => $file->getClientOriginalName(),
                    'path_file' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'ukuran_file' => intdiv($file->getSize(), 1024),
                ]);
            }

            $pendaftaran->riwayatStatus()->create([
                'status_dari' => null,
                'status_ke' => 'submitted',
                'keterangan' => 'Pendaftaran diajukan oleh asesi.',
            ]);

            return $pendaftaran;
        });

        // Notifikasi ke seluruh admin (email + dashboard) lewat channel bawaan Laravel.
        User::role(['admin', 'super-admin'])->get()->each(
            fn(User $admin) => $admin->notify(new PendaftaranMasukNotifikasi($pendaftaran))
        );

        return response()->json([
            'message' => 'Terimakasih, Pengajuan sertifikasi Anda telah berhasil dikirim. '
                . 'Anda dapat memantau status pengajuan melalui email dan halaman Profil Anda.',
            'data' => [
                'kode_pendaftaran' => $pendaftaran->kode_pendaftaran,
                'status' => $pendaftaran->status,
            ],
        ], 201);
    }

    /**
     * Karena guard 'sanctum' bisa dipakai Asesi maupun User (admin) sekaligus,
     * pastikan token yang dipakai memang milik Asesi sebelum lanjut ke logic asesi-only.
     */
    private function asesiAtauTolak(Request $request): Asesi
    {
        $user = $request->user();

        abort_unless($user instanceof Asesi, 403, 'Endpoint ini hanya untuk akun asesi.');

        return $user;
    }
}

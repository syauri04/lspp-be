<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\KontakLembaga;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/kontak — kontak lembaga untuk footer & tombol WhatsApp website.
 * Publik (tanpa token): isinya informasi publik, dan website tidak boleh ikut mati bila token bermasalah.
 * Hanya field publik yang dikirim (tanpa info siapa yang mengubah); di-cache browser/CDN 5 menit.
 */
class KontakWebsiteController extends Controller
{
    public function show(): JsonResponse
    {
        $k = KontakLembaga::ambil();

        return response()->json([
            'success' => true,
            'data' => [
                'nama' => $k['nama'],
                'email' => $k['email'],
                'alamat' => $k['alamat'],
                'whatsapp' => [
                    'nomor' => $k['whatsapp'],
                    'tampil' => KontakLembaga::formatWhatsapp($k['whatsapp']),
                    'url' => KontakLembaga::urlWhatsapp($k),
                ],
            ],
        ])->header('Cache-Control', 'public, max-age=300');
    }
}

<?php

namespace App\Http\Controllers\API\Profil;

use App\Http\Controllers\Controller;
use App\Http\Resources\Profil\NotifikasiAsesiResource;
use App\Models\Asesi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Notifikasi asesi (lspp-fe: halaman Profil & Notifikasi). Hanya notifikasi milik asesi login.
 *  GET  /api/notifikasi                 ?belum_dibaca=1&per_page=10  (+ meta.belum_dibaca)
 *  POST /api/notifikasi/{id}/baca
 *  POST /api/notifikasi/baca-semua
 */
class NotifikasiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $asesi = $this->asesi($request);
        $validated = $request->validate([
            'belum_dibaca' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = $asesi->notifications();
        if ($request->boolean('belum_dibaca')) {
            $query->whereNull('read_at');
        }

        return NotifikasiAsesiResource::collection(
            $query->paginate($validated['per_page'] ?? 10)->withQueryString()
        )->additional(['meta' => ['belum_dibaca' => $asesi->unreadNotifications()->count()]]);
    }

    public function baca(Request $request, string $id): NotifikasiAsesiResource
    {
        $notifikasi = $this->asesi($request)->notifications()->whereKey($id)->firstOrFail();
        $notifikasi->markAsRead();

        return new NotifikasiAsesiResource($notifikasi);
    }

    public function bacaSemua(Request $request): JsonResponse
    {
        $asesi = $this->asesi($request);
        $jumlah = $asesi->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.', 'data' => ['jumlah' => $jumlah]]);
    }

    private function asesi(Request $request): Asesi
    {
        // Dijamin middleware asesi.website; tetap dicek agar aman bila route dipindah.
        $user = $request->user();
        abort_unless($user instanceof Asesi, 403, 'Endpoint ini hanya untuk akun asesi.');

        return $user;
    }
}

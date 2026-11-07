<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Support\Cms\NotifikasiAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notifikasi lonceng CMS. Setiap admin hanya bisa melihat & menandai notifikasinya sendiri.
 */
class NotifikasiController extends Controller
{
    public function index(Request $request): View
    {
        $notifikasi = $request->user()->notifications()
            ->when($request->query('filter') === 'belum', fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('cms.notifikasi.index', [
            'notifikasi' => $notifikasi,
            'daftar' => $notifikasi->getCollection()->map(fn ($n) => NotifikasiAdmin::tampil($n)),
            'filter' => $request->query('filter') === 'belum' ? 'belum' : 'semua',
        ]);
    }

    /** Buka notifikasi: tandai dibaca lalu menuju halaman terkait (hanya URL internal). */
    public function buka(Request $request, string $id): RedirectResponse
    {
        $notif = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notif->markAsRead();

        $url = NotifikasiAdmin::tampil($notif)['url'];

        return $url ? redirect()->to($url) : redirect()->route('notifikasi-admin.index');
    }

    public function bacaSemua(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}

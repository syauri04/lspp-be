<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\BuktiPembayaran;
use App\Services\Pembayaran\PembayaranManual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Verifikasi bukti transfer manual (CMS). Disetujui -> tagihan lunas (bisa dijadwalkan asesmen);
 * ditolak -> asesi dikabari alasan & dapat mengunggah ulang.
 */
class VerifikasiPembayaranController extends Controller
{
    private const FILTER = ['menunggu', 'disetujui', 'ditolak', 'semua'];

    public function __construct(private PembayaranManual $manual) {}

    public function index(Request $request): View
    {
        $filter = in_array($request->query('status'), self::FILTER, true) ? $request->query('status') : 'menunggu';

        $buktis = BuktiPembayaran::query()
            ->with(['pembayaran.pendaftaranSertifikasi.asesi:id,name,email', 'pembayaran.pendaftaranSertifikasi.skemaSertifikasi:id,title'])
            ->when($filter === 'menunggu', fn ($q) => $q->where('status', BuktiPembayaran::MENUNGGU)->oldest())
            ->when($filter === 'disetujui', fn ($q) => $q->where('status', BuktiPembayaran::DISETUJUI)->latest('diverifikasi_at'))
            ->when($filter === 'ditolak', fn ($q) => $q->whereIn('status', [BuktiPembayaran::DITOLAK, BuktiPembayaran::DIBATALKAN])->latest('updated_at'))
            ->when($filter === 'semua', fn ($q) => $q->latest())
            ->paginate(20)
            ->withQueryString();

        return view('cms.verifikasi-pembayaran.index', compact('buktis', 'filter'));
    }

    public function show(BuktiPembayaran $bukti): View
    {
        $bukti->load([
            'pembayaran.pendaftaranSertifikasi.asesi',
            'pembayaran.pendaftaranSertifikasi.skemaSertifikasi',
            'pembayaran.buktiPembayaran.verifikator:id,name',
            'verifikator:id,name',
        ]);

        return view('cms.verifikasi-pembayaran.show', compact('bukti'));
    }

    public function setujui(Request $request, BuktiPembayaran $bukti): RedirectResponse
    {
        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->manual->setujui($bukti, $request->user(), $data['catatan'] ?? null);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('verifikasi-pembayaran.index')
            ->with('success', 'Pembayaran disetujui. Pendaftaran kini dapat dijadwalkan asesmen.');
    }

    public function tolak(Request $request, BuktiPembayaran $bukti): RedirectResponse
    {
        $data = $request->validate(
            ['alasan' => ['required', 'string', 'min:5', 'max:1000']],
            ['alasan.required' => 'Alasan penolakan wajib diisi (dikirim ke asesi).'],
        );

        try {
            $this->manual->tolak($bukti, $request->user(), $data['alasan']);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('verifikasi-pembayaran.index')
            ->with('success', 'Bukti transfer ditolak. Asesi telah diberi tahu untuk mengunggah ulang.');
    }

    /** Berkas bukti transfer (hanya admin login; tidak di-cache). */
    public function berkas(BuktiPembayaran $bukti): StreamedResponse
    {
        $disk = Storage::disk(BuktiPembayaran::disk());
        abort_unless($disk->exists($bukti->path_file), 404);

        return $disk->response($bukti->path_file, $bukti->nama_file_asli, [
            'Content-Type' => $bukti->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            // Sandbox untuk gambar; PDF dikecualikan (penampil PDF Chrome menolak CSP sandbox).
            ...($bukti->mime_type === 'application/pdf' ? [] : ['Content-Security-Policy' => 'sandbox']),
        ], 'inline');
    }
}

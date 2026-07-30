<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pendaftaran\ReviewPendaftaranRequest;
use App\Models\PendaftaranSertifikasi;
use App\Models\Pembayaran;
use App\Notifications\PendaftaranDisetujuiNotifikasi;
use App\Notifications\PendaftaranDitolakNotifikasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PendaftaranSertifikasiController extends Controller
{
    public function index(Request $request): View
    {
        $pendaftaran = PendaftaranSertifikasi::with(['asesi:id,name', 'skemaSertifikasi:id,title'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return view('cms.pendaftaran.index', compact('pendaftaran'));
    }

    public function show(PendaftaranSertifikasi $pendaftaran): View
    {
        $pendaftaran->load([
            'dokumen',
            'riwayatStatus.changedBy:id,name',
            'asesi',
            'skemaSertifikasi',
            'pembayaran',
        ]);

        return view('cms.pendaftaran.show', compact('pendaftaran'));
    }

    /**
     * Approve: dokumen sesuai -> buat record pembayaran -> kirim link checkout ke asesi.
     */
    public function approve(ReviewPendaftaranRequest $request, PendaftaranSertifikasi $pendaftaran): RedirectResponse
    {
        abort_unless(
            $pendaftaran->status === 'submitted',
            422,
            'Pendaftaran ini tidak dalam status yang bisa direview.'
        );

        DB::transaction(function () use ($request, $pendaftaran) {
            $pendaftaran->update([
                'status' => 'awaiting_payment',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'catatan_admin' => $request->catatan,
            ]);

            $pendaftaran->riwayatStatus()->create([
                'status_dari' => 'submitted',
                'status_ke' => 'awaiting_payment',
                'keterangan' => $request->catatan ?? 'Dokumen disetujui admin, menunggu pembayaran.',
                'changed_by' => $request->user()->id,
            ]);

            Pembayaran::create([
                'kode_transaksi' => 'LSPP-' . strtoupper(substr($pendaftaran->kode_pendaftaran, 0, 8)) . '-' . now()->timestamp,
                'pendaftaran_sertifikasi_id' => $pendaftaran->id,
                'jumlah' => $pendaftaran->harga_snapshot,
                'status' => 'pending',
            ]);
        });

        $pendaftaran->asesi->notify(new PendaftaranDisetujuiNotifikasi($pendaftaran));

        return back()->with('success', 'Pendaftaran disetujui. Link pembayaran telah dikirim ke asesi.');
    }

    /**
     * Reject: kirim notif alasan penolakan, tidak ada record pembayaran yang dibuat.
     */
    public function reject(ReviewPendaftaranRequest $request, PendaftaranSertifikasi $pendaftaran): RedirectResponse
    {
        abort_unless(
            $pendaftaran->status === 'submitted',
            422,
            'Pendaftaran ini tidak dalam status yang bisa direview.'
        );

        DB::transaction(function () use ($request, $pendaftaran) {
            $pendaftaran->update([
                'status' => 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'catatan_admin' => $request->catatan,
            ]);

            $pendaftaran->riwayatStatus()->create([
                'status_dari' => 'submitted',
                'status_ke' => 'rejected',
                'keterangan' => $request->catatan,
                'changed_by' => $request->user()->id,
            ]);
        });

        $pendaftaran->asesi->notify(new PendaftaranDitolakNotifikasi($pendaftaran));

        return back()->with('success', 'Pendaftaran ditolak. Notifikasi telah dikirim ke asesi.');
    }
}

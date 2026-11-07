<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pendaftaran\ReviewPendaftaranRequest;
use App\Models\Pembayaran;
use App\Models\PendaftaranSertifikasi;
use App\Notifications\PendaftaranDisetujuiNotifikasi;
use App\Notifications\PendaftaranDitolakNotifikasi;
use App\Services\Pembayaran\ResetSesiDoku;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PendaftaranSertifikasiController extends Controller
{
    public function index(Request $request): View
    {
        $pendaftaran = PendaftaranSertifikasi::with(['asesi:id,name', 'skemaSertifikasi:id,title'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
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
            'pembayaran.buktiPembayaran',
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
                'kode_transaksi' => 'LSPP-'.strtoupper(substr($pendaftaran->kode_pendaftaran, 0, 8)).'-'.now()->timestamp,
                'pendaftaran_sertifikasi_id' => $pendaftaran->id,
                'jumlah' => $pendaftaran->harga_snapshot,
                'jumlah_awal' => $pendaftaran->harga_snapshot, // harga sebelum potongan voucher
                'status' => 'pending',
            ]);
        });

        $pendaftaran->asesi->notify(new PendaftaranDisetujuiNotifikasi($pendaftaran));

        return back()->with('success', 'Pendaftaran disetujui. Link pembayaran telah dikirim ke asesi.');
    }

    /**
     * Reject: kirim notif alasan penolakan, tidak ada record pembayaran yang dibuat.
     */
    /**
     * Reset sesi pembayaran DOKU: asesi bisa memasang/melepas voucher lalu membuat sesi DOKU baru.
     * Link DOKU lama tetap dikenali webhook (pembayaran lewat link lama tetap tercatat).
     */
    public function resetSesiPembayaran(Request $request, PendaftaranSertifikasi $pendaftaran, ResetSesiDoku $reset): RedirectResponse
    {
        abort_unless($pendaftaran->pembayaran, 404, 'Tagihan tidak ditemukan.');

        try {
            $tagihan = $reset->reset($pendaftaran->pembayaran, $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "Sesi pembayaran direset. Kode transaksi baru: {$tagihan->kode_transaksi}. "
            .'Asesi dapat memasang voucher lalu klik Bayar Sekarang untuk membuat sesi pembayaran baru.');
    }

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

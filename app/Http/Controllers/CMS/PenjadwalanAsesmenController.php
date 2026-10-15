<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pendaftaran\StoreJadwalAsesmenRequest;
use App\Models\Asesor;
use App\Models\JadwalAsesmen;
use App\Models\PendaftaranSertifikasi;
use App\Models\TUK;
use App\Notifications\JadwalAsesmenDitentukanNotifikasi;
use App\Notifications\JadwalAsesmenUntukAsesorNotifikasi;
use App\Services\PenilaianMukService;
use Illuminate\Support\Facades\DB;

class PenjadwalanAsesmenController extends Controller
{
    public function index()
    {
        $pendaftarans = PendaftaranSertifikasi::with(['asesi', 'skemaSertifikasi'])
            ->where('status', 'paid')
            ->whereDoesntHave('jadwalAsesmen')
            ->latest('submitted_at')
            ->paginate(10);

        return view('cms.penjadwalan-asesmen.index', compact('pendaftarans'));
    }

    public function create(string $kodePendaftaran)
    {
        $pendaftaran = PendaftaranSertifikasi::with(['asesi', 'skemaSertifikasi'])
            ->where('kode_pendaftaran', $kodePendaftaran)
            ->where('status', 'paid')
            ->firstOrFail();

        $asesors = Asesor::where('status', 'aktif')->orderBy('nama')->get();
        $tuks = TUK::where('is_active', true)->orderBy('title')->get();

        return view('cms.penjadwalan-asesmen.create', compact('pendaftaran', 'asesors', 'tuks'));
    }

    public function store(StoreJadwalAsesmenRequest $request, string $kodePendaftaran)
    {
        $pendaftaran = PendaftaranSertifikasi::where('kode_pendaftaran', $kodePendaftaran)
            ->where('status', 'paid')
            ->firstOrFail();

        $jadwal = DB::transaction(function () use ($request, $pendaftaran) {
            $jadwal = JadwalAsesmen::create([
                'pendaftaran_sertifikasi_id' => $pendaftaran->id,
                'asesor_id' => $request->asesor_id,
                'tuk_id' => $request->tuk_id,
                'tanggal_asesmen' => $request->tanggal_asesmen,
                'waktu_mulai' => $request->waktu_mulai,
                'waktu_selesai' => $request->waktu_selesai,
                'catatan' => $request->catatan,
                'dijadwalkan_by' => $request->user()->id,
                'status' => 'terjadwal',
            ]);

            app(PenilaianMukService::class)->mulaiDariJadwal($jadwal);

            $statusSebelumnya = $pendaftaran->status;

            $pendaftaran->update(['status' => 'dijadwalkan']);

            $pendaftaran->riwayatStatus()->create([
                'status_dari' => $statusSebelumnya,
                'status_ke' => 'dijadwalkan',
                'keterangan' => 'Asesor dan jadwal asesmen telah ditentukan oleh admin.',
                'changed_by' => $request->user()->id,
            ]);

            return $jadwal;
        });

        $jadwal->load(['pendaftaran.asesi', 'asesor']);
        $jadwal->pendaftaran->asesi->notify(new JadwalAsesmenDitentukanNotifikasi($jadwal));
        $jadwal->asesor->notify(new JadwalAsesmenUntukAsesorNotifikasi($jadwal));

        return redirect()
            ->route('penjadwalan-asesmen.index')
            ->with('success', 'Asesor dan jadwal berhasil ditentukan.');
    }
}

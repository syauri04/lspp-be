<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\MapaTemplate;
use App\Models\MapaTemplatePenandatangan;
use App\Models\MapaTemplateRencana;
use App\Models\PenilaianMuk;
use App\Models\SkemaSertifikasi;
use App\Services\Muk\MapaTemplateService;
use App\Services\Muk\TandaTanganService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Template FR.MAPA.01/02 per skema + metode (admin LSP):
 * /sertifikat/skema-sertifikasi/{skema}/mapa/{metode}
 */
class MapaTemplateController extends Controller
{
    public function __construct(private MapaTemplateService $service) {}

    public function edit(SkemaSertifikasi $skema_sertifikasi, string $metode): View
    {
        abort_unless(in_array($metode, PenilaianMuk::METODE, true), 404);

        $skema = $skema_sertifikasi->load('unitKompetensis');
        $template = $this->service->untuk($skema, $metode);

        return view('cms.mapa-template.edit', [
            'skema' => $skema,
            'metode' => $metode,
            'metodeLabel' => MapaTemplateService::METODE_LABEL,
            'template' => $template,
            'rencana' => $template->rencana->keyBy('unit_kompetensi_id'),
            'penandatangan' => $template->penandatangan->groupBy('bagian'),
            'kolomMetode' => MapaTemplateRencana::KOLOM_METODE,
        ]);
    }

    public function update(Request $request, SkemaSertifikasi $skema_sertifikasi, string $metode): RedirectResponse
    {
        abort_unless(in_array($metode, PenilaianMuk::METODE, true), 404);
        $teks = ['nullable', 'string', 'max:100'];

        $validated = $request->validate([
            'rencana' => ['nullable', 'array'],
            'rencana.*' => ['array:bukti,jenis_l,jenis_tl,jenis_t,'.implode(',', MapaTemplateRencana::KOLOM_METODE)],
            'rencana.*.bukti' => ['nullable', 'string', 'max:2000'],
            'rencana.*.jenis_l' => ['nullable', 'boolean'],
            'rencana.*.jenis_tl' => ['nullable', 'boolean'],
            'rencana.*.jenis_t' => ['nullable', 'boolean'],
            ...collect(MapaTemplateRencana::KOLOM_METODE)->mapWithKeys(fn ($k) => ["rencana.*.{$k}" => $teks])->all(),

            'penandatangan' => ['nullable', 'array:'.implode(',', MapaTemplate::BAGIAN)],
            'penandatangan.konfirmasi.*.peran' => ['required', Rule::in(MapaTemplate::PERAN_KONFIRMASI)],
            'penandatangan.penyusun_mapa01.*.peran' => ['required', Rule::in(['penyusun', 'validator'])],
            'penandatangan.penyusun_mapa02.*.peran' => ['required', Rule::in(['penyusun', 'validator'])],
            'penandatangan.penyusun_mapa01.*.nama' => ['required', 'string', 'max:255'],
            'penandatangan.penyusun_mapa02.*.nama' => ['required', 'string', 'max:255'],
            'penandatangan.*.*.id' => ['nullable', 'integer'],
            'penandatangan.*.*.nama' => ['nullable', 'string', 'max:255'],
            'penandatangan.*.*.no_reg' => ['nullable', 'string', 'max:100'],
            'penandatangan.*.*.tanggal' => ['nullable', 'date'],
            'penandatangan.*.*.hapus_ttd' => ['nullable', 'boolean'],
            'penandatangan.*.*.ttd' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:1024'],
        ], [
            'penandatangan.*.*.ttd.mimes' => 'Tanda tangan harus berupa gambar PNG atau JPG.',
            'penandatangan.*.*.ttd.max' => 'Ukuran gambar tanda tangan maksimal 1 MB.',
            'penandatangan.*.*.nama.required' => 'Nama penyusun/validator wajib diisi.',
        ]);

        $template = $this->service->untuk($skema_sertifikasi, $metode);

        // Simpan indeks asli tiap baris agar berkas ttd (input file) tetap terpasang ke baris yang benar.
        $penandatangan = collect($validated['penandatangan'] ?? [])
            ->map(fn ($baris) => collect($baris)->map(fn ($b, $i) => [...$b, '_index' => $i])->all())
            ->all();
        // Bagian yang dikosongkan seluruhnya di form (semua baris dihapus) tetap disinkronkan.
        foreach (['penyusun_mapa01', 'penyusun_mapa02'] as $bagian) {
            if ($request->boolean("ada_{$bagian}") && ! isset($penandatangan[$bagian])) {
                $penandatangan[$bagian] = [];
            }
        }

        $berkas = [];
        foreach ($request->file('penandatangan', []) as $bagian => $perBaris) {
            foreach ($perBaris as $i => $kolom) {
                if (! empty($kolom['ttd'])) {
                    $berkas[$bagian][$i] = $kolom['ttd'];
                }
            }
        }

        $this->service->simpan($template, $validated['rencana'] ?? [], $penandatangan, $berkas);

        return redirect()
            ->route('skema-sertifikasi.mapa.edit', [$skema_sertifikasi, $metode])
            ->with('success', 'Template MAPA '.strtoupper($metode).' berhasil disimpan.');
    }

    /** Gambar tanda tangan penyusun/validator/manajer (CMS, admin login). */
    public function ttd(MapaTemplatePenandatangan $penandatangan, TandaTanganService $ttd): StreamedResponse
    {
        abort_unless($penandatangan->path_ttd && Storage::disk($ttd->disk())->exists($penandatangan->path_ttd), 404);

        return Storage::disk($ttd->disk())->response($penandatangan->path_ttd, 'ttd.png', [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

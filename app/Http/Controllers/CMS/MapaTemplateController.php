<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Models\KriteriaUnjukKerja;
use App\Models\MapaTemplate;
use App\Models\MapaTemplatePenandatangan;
use App\Models\MapaTemplateRencana;
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
        abort_unless(in_array($metode, MapaTemplate::METODE, true), 404);

        $skema = $skema_sertifikasi->load('unitKompetensis.elemenKompetensi.kriteriaUnjukKerja');
        $template = $this->service->untuk($skema, $metode);

        return view('cms.mapa-template.edit', [
            'skema' => $skema,
            'metode' => $metode,
            'metodeLabel' => MapaTemplateService::METODE_LABEL,
            'template' => $template,
            'rencana' => $template->rencana->keyBy('unit_kompetensi_id'),
            'penandatangan' => $template->penandatangan->groupBy('bagian'),
            'bagianPenyusun' => $template->bagianPenyusun(),
            'kolomMetode' => MapaTemplateRencana::KOLOM_METODE,
        ]);
    }

    public function update(Request $request, SkemaSertifikasi $skema_sertifikasi, string $metode): RedirectResponse
    {
        abort_unless(in_array($metode, MapaTemplate::METODE, true), 404);
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
            'penandatangan.penyusun_ia02.*.peran' => ['required', Rule::in(['penyusun', 'validator'])],
            'penandatangan.penyusun_mapa01.*.nama' => ['required', 'string', 'max:255'],
            'penandatangan.penyusun_mapa02.*.nama' => ['required', 'string', 'max:255'],
            'penandatangan.penyusun_ia02.*.nama' => ['required', 'string', 'max:255'],
            'penandatangan.penyusun_ia06b.*.peran' => ['required', Rule::in(['penyusun', 'validator'])],
            'penandatangan.penyusun_ia06b.*.nama' => ['required', 'string', 'max:255'],
            'penandatangan.penyusun_ia09.*.peran' => ['required', Rule::in(['penyusun', 'validator'])],
            'penandatangan.penyusun_ia09.*.nama' => ['required', 'string', 'max:255'],
            'penandatangan.penyusun_ia04a.*.peran' => ['required', Rule::in(['penyusun', 'validator'])],
            'penandatangan.penyusun_ia04a.*.nama' => ['required', 'string', 'max:255'],
            'penandatangan.penyusun_ia04b.*.peran' => ['required', Rule::in(['penyusun', 'validator'])],
            'penandatangan.penyusun_ia04b.*.nama' => ['required', 'string', 'max:255'],

            // Instrumen IA (hanya metode TPD saat ini): FR.IA.02 skenario & waktu, FR.IA.03 pertanyaan.
            'instrumen' => ['nullable', 'array:ia02,ia03,ia04a,ia04b,ia06,ia08,ia09'],
            // FR.IA.04A/B (template KT): instruksi proyek & aspek penilaian.
            'instrumen.ia04a' => ['array:hal_disiapkan,hal_didemonstrasikan,waktu_pengerjaan,waktu_tanya_jawab,total_waktu'],
            'instrumen.ia04a.*' => ['nullable', 'string', 'max:15000'],
            'instrumen.ia04a.waktu_pengerjaan' => ['nullable', 'string', 'max:1000'],
            'instrumen.ia04a.waktu_tanya_jawab' => ['nullable', 'string', 'max:1000'],
            'instrumen.ia04a.total_waktu' => ['nullable', 'string', 'max:1000'],
            'instrumen.ia04b' => ['array:aspek'],
            'instrumen.ia04b.aspek' => ['nullable', 'array', 'max:30'],
            'instrumen.ia04b.aspek.*' => ['array:lingkup,pertanyaan,kesesuaian'],
            'instrumen.ia04b.aspek.*.lingkup' => ['nullable', 'string', 'max:500'],
            'instrumen.ia04b.aspek.*.pertanyaan' => ['nullable', 'string', 'max:5000'],
            'instrumen.ia04b.aspek.*.kesesuaian' => ['nullable', 'string', 'max:500'],
            // FR.IA.08 (template VP): daftar bukti portofolio -> lampiran bukti relevan FR.APL.01.
            'instrumen.ia08' => ['array:bukti,materi'],
            'instrumen.ia08.bukti' => ['nullable', 'array', 'max:30'],
            'instrumen.ia08.bukti.*' => ['nullable', 'string', 'max:500'],
            // Materi/substansi wawancara IA.08: id KUK skema (dropdown per unit/elemen).
            'instrumen.ia08.materi' => ['nullable', 'array', 'max:500'],
            'instrumen.ia08.materi.*' => ['nullable', 'integer'],
            // FR.IA.09 (template VP): pertanyaan wawancara.
            'instrumen.ia09' => ['array:pertanyaan'],
            'instrumen.ia09.pertanyaan' => ['nullable', 'array', 'max:50'],
            'instrumen.ia09.pertanyaan.*' => ['nullable', 'string', 'max:3000'],
            // FR.IA.06 (template umum): waktu, soal esai & kunci jawaban.
            'instrumen.ia06' => ['array:waktu,soal'],
            'instrumen.ia06.waktu' => ['nullable', 'string', 'max:100'],
            'instrumen.ia06.soal' => ['nullable', 'array', 'max:50'],
            'instrumen.ia06.soal.*' => ['array:pertanyaan,kunci'],
            'instrumen.ia06.soal.*.pertanyaan' => ['nullable', 'string', 'max:5000'],
            'instrumen.ia06.soal.*.kunci' => ['nullable', 'string', 'max:10000'],
            'instrumen.ia02' => ['array:skenario,waktu'],
            'instrumen.ia02.skenario' => ['nullable', 'string', 'max:10000'],
            'instrumen.ia02.waktu' => ['nullable', 'string', 'max:100'],
            'instrumen.ia03' => ['array:pertanyaan'],
            'instrumen.ia03.pertanyaan' => ['nullable', 'array', 'max:50'],
            'instrumen.ia03.pertanyaan.*' => ['nullable', 'string', 'max:2000'],
            'penandatangan.*.*.id' => ['nullable', 'integer'],
            'penandatangan.*.*.nama' => ['nullable', 'string', 'max:255'],
            'penandatangan.*.*.no_reg' => ['nullable', 'string', 'max:100'],
            'penandatangan.*.*.hapus_ttd' => ['nullable', 'boolean'],
            'penandatangan.*.*.ttd' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:1024'],
        ], [
            'penandatangan.*.*.ttd.mimes' => 'Tanda tangan harus berupa gambar PNG atau JPG.',
            'penandatangan.*.*.ttd.max' => 'Ukuran gambar tanda tangan maksimal 1 MB.',
            'penandatangan.*.*.nama.required' => 'Nama penyusun/validator wajib diisi.',
        ]);

        $template = $this->service->untuk($skema_sertifikasi, $metode);
        // Bagian penyusun yang tidak berlaku untuk metode ini diabaikan.
        $bagianSah = $metode === MapaTemplate::UMUM ? $template->bagianPenyusun() : ['konfirmasi', ...$template->bagianPenyusun()];
        $validated['penandatangan'] = array_intersect_key($validated['penandatangan'] ?? [], array_flip($bagianSah));

        // Simpan indeks asli tiap baris agar berkas ttd (input file) tetap terpasang ke baris yang benar.
        $penandatangan = collect($validated['penandatangan'] ?? [])
            ->map(fn ($baris) => collect($baris)->map(fn ($b, $i) => [...$b, '_index' => $i])->all())
            ->all();
        // Bagian yang dikosongkan seluruhnya di form (semua baris dihapus) tetap disinkronkan.
        foreach ($template->bagianPenyusun() as $bagian) {
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
        // Berkas untuk bagian yang tidak berlaku diabaikan (tidak ditulis ke disk).
        $berkas = array_intersect_key($berkas, $penandatangan);

        // Instrumen IA hanya untuk metode TPD (form kunci ia02/ia03 -> kode dokumen).
        $instrumen = null;
        if ($metode === 'tpd' && ($request->has('instrumen.ia02') || $request->has('instrumen.ia03'))) {
            $instrumen = [
                MapaTemplate::IA_02 => $validated['instrumen']['ia02'] ?? [],
                MapaTemplate::IA_03 => $validated['instrumen']['ia03'] ?? [],
            ];
        } elseif ($metode === 'kt' && ($request->has('instrumen.ia04a') || $request->has('instrumen.ia04b'))) {
            $instrumen = array_filter([
                MapaTemplate::IA_04A => $request->has('instrumen.ia04a') ? ($validated['instrumen']['ia04a'] ?? []) : null,
                MapaTemplate::IA_04B => $request->has('instrumen.ia04b') ? ($validated['instrumen']['ia04b'] ?? []) : null,
            ], fn ($v) => $v !== null);
        } elseif ($metode === 'vp' && ($request->has('instrumen.ia08') || $request->has('instrumen.ia09'))) {
            $instrumen = array_filter([
                MapaTemplate::IA_08 => $request->has('instrumen.ia08') ? $this->ia08SahSkema($skema_sertifikasi, $validated['instrumen']['ia08'] ?? []) : null,
                MapaTemplate::IA_09 => $request->has('instrumen.ia09') ? ($validated['instrumen']['ia09'] ?? []) : null,
            ], fn ($v) => $v !== null);
        } elseif ($metode === MapaTemplate::UMUM && $request->has('instrumen.ia06')) {
            $instrumen = [MapaTemplate::IA_06 => $validated['instrumen']['ia06'] ?? []];
        }
        // Template umum tidak punya rencana MAPA.
        if ($metode === MapaTemplate::UMUM) {
            $validated['rencana'] = [];
        }

        $this->service->simpan($template, $validated['rencana'] ?? [], $penandatangan, $berkas, $instrumen);

        return redirect()
            ->route('skema-sertifikasi.mapa.edit', [$skema_sertifikasi, $metode])
            ->with('success', 'Template perangkat asesmen '.strtoupper($metode).' berhasil disimpan.');
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

    /** Materi IA.08 hanya id KUK milik skema ini (id lain dibuang), urut sesuai struktur skema. */
    private function ia08SahSkema(SkemaSertifikasi $skema, array $ia08): array
    {
        if (array_key_exists('materi', $ia08)) {
            $kukSkema = KriteriaUnjukKerja::query()
                ->whereHas('elemenKompetensi.unitKompetensi', fn ($q) => $q->where('skema_sertifikasi_id', $skema->id))
                ->pluck('id')->all();
            $dipilih = array_map('intval', (array) ($ia08['materi'] ?? []));
            $ia08['materi'] = array_values(array_intersect($kukSkema, $dipilih));
        }

        return $ia08;
    }
}

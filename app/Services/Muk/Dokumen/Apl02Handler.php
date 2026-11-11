<?php

namespace App\Services\Muk\Dokumen;

use App\Models\Apl01BuktiRelevan;
use App\Models\Apl02BuktiRelevan;
use App\Models\Apl02Jawaban;
use App\Models\DokumenPenilaian;
use App\Models\ElemenKompetensi;
use App\Models\JenisDokumenMuk;
use App\Models\SkemaSertifikasi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * FR.APL.02 Asesmen Mandiri (diisi asesi, ditinjau asesor).
 *
 * - Jawaban per ELEMEN di apl02_jawabans: kompeten (K/BK). Baris jawaban TIDAK dibuat di muka;
 *   GET menggabungkan master unit->elemen->KUK dengan jawaban.
 * - "Bukti yang relevan" per elemen di apl02_bukti_relevans: tiap baris berisi berkas unggahan,
 *   link (http/https), dan/atau rujukan "Lampiran bukti yang relevan" FR.APL.01 (bagian 3) milik
 *   penilaian yang sama (apl01_bukti_relevan_id; hanya lampiran yang berisi berkas/link).
 *   Elemen K wajib punya minimal satu bukti terisi.
 * - Struktur unit/elemen/KUK dibekukan ke snapshot saat submit.
 * - Bagian rekomendasi (asesor) read-only di sini; diisi lewat review asesor.
 */
class Apl02Handler implements DokumenHandler
{
    public const MAKS_BUKTI_PER_ELEMEN = 20;

    public const BUKTI_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];

    public const BUKTI_MAX_KB = 5120;

    public function kode(): string
    {
        return JenisDokumenMuk::APL_02;
    }

    public function pengisi(): string
    {
        return 'asesi';
    }

    public function peninjau(): ?string
    {
        return 'asesor';
    }

    public function penyetuju(): ?string
    {
        return null;
    }

    public function butuhTandaTangan(): bool
    {
        return true;
    }

    public function payload(DokumenPenilaian $dokumen, string $peran): array
    {
        $dokumen->loadMissing([
            'penilaianMuk.pendaftaran.asesi',
            'penilaianMuk.jadwalAsesmen.asesor',
            'ditinjauOlehAsesor',
        ]);

        $penilaian = $dokumen->penilaianMuk;
        $master = $dokumen->snapshot ?? $this->master($this->skema($dokumen));
        $jawaban = $dokumen->apl02Jawaban()->with('bukti.lampiranApl01')->get()->keyBy('elemen_kompetensi_id');
        $asesor = $dokumen->ditinjauOlehAsesor ?? $penilaian->jadwalAsesmen?->asesor;

        $units = collect($master['unit_kompetensi'])->map(fn (array $u) => [
            ...$u,
            'elemen' => collect($u['elemen'])->map(function (array $e) use ($jawaban) {
                $j = $jawaban->get($e['id']);

                return [
                    ...$e,
                    'jawaban' => [
                        'kompeten' => $j?->kompeten,
                        'bukti' => ($j?->bukti ?? collect())->map(fn (Apl02BuktiRelevan $b) => [
                            'id' => $b->id,
                            'link' => $b->link,
                            'lampiran' => $b->lampiranApl01 ? Apl01Handler::barisRelevan($b->lampiranApl01) : null,
                            'file' => $b->path_file ? [
                                'nama' => $b->nama_file_asli,
                                'mime' => $b->mime_type,
                                'ukuran_kb' => $b->ukuran_file,
                            ] : null,
                        ])->values()->all(),
                    ],
                ];
            })->all(),
        ])->all();

        $semuaElemen = collect($units)->flatMap(fn ($u) => $u['elemen']);

        return [
            'skema' => $master['skema'],
            'unit_kompetensi' => $units,
            'progres' => [
                'terisi' => $semuaElemen->filter(fn ($e) => $this->elemenLengkap($e['jawaban']['kompeten'], count($e['jawaban']['bukti'])))->count(),
                'total' => $semuaElemen->count(),
            ],
            'asesi' => ['nama' => $this->namaAsesi($dokumen)],
            'peninjau' => [
                'nama' => $asesor?->nama,
                'nomor_registrasi' => $asesor?->nomor_registrasi,
                'rekomendasi' => $dokumen->hasil_rekomendasi_asesor,
                'catatan' => $dokumen->catatan_asesor,
            ],
            // Pilihan dropdown "Pilih dari Lampiran FR.APL.01" (kosong bila asesi belum melampirkan apa pun).
            'lampiran_apl01' => $this->lampiranApl01($dokumen)->map(fn (Apl01BuktiRelevan $b) => Apl01Handler::barisRelevan($b))->values()->all(),
            'batas' => [
                'maks_bukti_per_elemen' => self::MAKS_BUKTI_PER_ELEMEN,
                'mimes' => self::BUKTI_MIMES,
                'max_kb' => self::BUKTI_MAX_KB,
            ],
        ];
    }

    public function rulesSimpan(DokumenPenilaian $dokumen): array
    {
        return [
            'jawaban' => ['required', 'array', 'max:1000'],
            'jawaban.*' => ['array:elemen_id,kompeten,bukti'],
            // Hanya elemen milik skema penilaian ini.
            'jawaban.*.elemen_id' => ['required', 'integer', 'distinct', Rule::in($this->idElemen($dokumen)->all())],
            'jawaban.*.kompeten' => ['present', 'nullable', Rule::in(['K', 'BK'])],
            'jawaban.*.bukti' => ['present', 'array', 'max:'.self::MAKS_BUKTI_PER_ELEMEN],
            'jawaban.*.bukti.*' => ['array:id,link,lampiran_id'],
            'jawaban.*.bukti.*.id' => ['nullable', 'integer'],
            // Kepemilikan lampiran (penilaian yang sama, berisi) dicek di simpan().
            'jawaban.*.bukti.*.lampiran_id' => ['nullable', 'integer'],
            // Hanya http/https: mencegah link "javascript:" dsb.
            'jawaban.*.bukti.*.link' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    /**
     * Sinkronisasi per elemen: kompeten + daftar bukti (berdasarkan id baris bukti).
     * Baris yang tidak dikirim dihapus (berkasnya dihapus setelah commit); baris baru tanpa
     * link diabaikan (berkas diunggah lewat endpoint tersendiri).
     */
    public function simpan(DokumenPenilaian $dokumen, array $validated, array &$hapusNanti): bool
    {
        $semua = $dokumen->apl02Jawaban()->with('bukti')->get()->keyBy('elemen_kompetensi_id');
        $berubah = false;
        $lampiranSah = $this->lampiranApl01($dokumen)->pluck('id')->all();

        foreach ($validated['jawaban'] as $item) {
            $kompeten = $item['kompeten'] ?? null;
            $jawaban = $semua->get($item['elemen_id']);
            $buktiLama = $jawaban?->bukti->keyBy('id') ?? collect();
            $buktiKirim = collect($item['bukti'] ?? []);

            $idTakDikenal = $buktiKirim->pluck('id')->filter()->diff($buktiLama->keys());
            if ($idTakDikenal->isNotEmpty()) {
                throw ValidationException::withMessages(['jawaban' => ['Data bukti tidak valid. Muat ulang halaman lalu coba lagi.']]);
            }

            // Lampiran FR.APL.01: harus milik penilaian ini & berisi; satu lampiran sekali per elemen.
            $lampiranKirim = $buktiKirim->pluck('lampiran_id')->filter()->map(fn ($id) => (int) $id);
            if ($lampiranKirim->diff($lampiranSah)->isNotEmpty()) {
                throw ValidationException::withMessages(['jawaban' => ['Lampiran FR.APL.01 yang dipilih tidak tersedia. Muat ulang halaman lalu coba lagi.']]);
            }
            if ($lampiranKirim->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['jawaban' => ['Lampiran FR.APL.01 yang sama dipilih lebih dari sekali pada satu elemen.']]);
            }

            $adaBuktiBaru = $buktiKirim->contains(fn ($b) => empty($b['id']) && (filled($b['link'] ?? null) || ! empty($b['lampiran_id'])));
            if (! $jawaban && $kompeten === null && ! $adaBuktiBaru) {
                continue; // tidak perlu membuat baris kosong
            }

            if (! $jawaban) {
                $jawaban = Apl02Jawaban::create([
                    'dokumen_penilaian_id' => $dokumen->id,
                    'elemen_kompetensi_id' => $item['elemen_id'],
                    'kompeten' => $kompeten,
                ]);
                $berubah = true;
            } elseif ($jawaban->kompeten !== $kompeten) {
                $jawaban->update(['kompeten' => $kompeten]);
                $berubah = true;
            }

            $idDipertahankan = [];
            foreach ($buktiKirim->values() as $urutan => $b) {
                $link = filled($b['link'] ?? null) ? trim($b['link']) : null;
                $lampiranId = ! empty($b['lampiran_id']) ? (int) $b['lampiran_id'] : null;
                $row = ! empty($b['id']) ? $buktiLama->get($b['id']) : null;

                if (! $row) {
                    if ($link === null && $lampiranId === null) {
                        continue;
                    }
                    $jawaban->bukti()->create(['link' => $link, 'apl01_bukti_relevan_id' => $lampiranId, 'urutan' => $urutan]);
                    $berubah = true;

                    continue;
                }

                $idDipertahankan[] = $row->id;

                // Baris tanpa link, berkas, dan lampiran = kosong -> dihapus.
                if ($link === null && $lampiranId === null && ! $row->path_file) {
                    $row->delete();
                    $berubah = true;

                    continue;
                }

                if ($row->link !== $link || $row->apl01_bukti_relevan_id !== $lampiranId || $row->urutan !== $urutan) {
                    $row->update(['link' => $link, 'apl01_bukti_relevan_id' => $lampiranId, 'urutan' => $urutan]);
                    $berubah = $berubah || $row->wasChanged(['link', 'apl01_bukti_relevan_id']);
                }
            }

            foreach ($buktiLama->except($idDipertahankan) as $row) {
                if ($row->path_file) {
                    $hapusNanti[] = [self::disk(), $row->path_file];
                }
                $row->delete();
                $berubah = true;
            }
        }

        return $berubah;
    }

    /* ---------------------------- berkas bukti ---------------------------- */

    /** Simpan berkas ke disk privat SEBELUM transaksi; path dikembalikan untuk dicatat di DB. */
    public function tulisBerkas(DokumenPenilaian $dokumen, int $elemenId, UploadedFile $file): string
    {
        $ext = $file->guessExtension() ?: 'bin';

        return $file->storeAs(
            "apl02/{$dokumen->penilaian_muk_id}",
            "elemen-{$elemenId}-".Str::uuid().".{$ext}",
            self::disk(),
        );
    }

    /**
     * Unggah berkas ke baris bukti yang ada ($buktiId) atau ke baris baru.
     * Berkas lama pada baris tsb didaftarkan untuk dihapus setelah commit.
     */
    public function simpanBerkas(DokumenPenilaian $dokumen, int $elemenId, ?int $buktiId, string $path, UploadedFile $file, array &$hapusNanti): bool
    {
        $jawaban = Apl02Jawaban::firstOrCreate(
            ['dokumen_penilaian_id' => $dokumen->id, 'elemen_kompetensi_id' => $elemenId],
            ['kompeten' => null],
        );

        if ($buktiId) {
            $row = $jawaban->bukti()->whereKey($buktiId)->first();
            if (! $row) {
                throw ValidationException::withMessages(['bukti_id' => ['Baris bukti tidak ditemukan.']]);
            }
            if ($row->path_file) {
                $hapusNanti[] = [self::disk(), $row->path_file];
            }
        } else {
            if ($jawaban->bukti()->count() >= self::MAKS_BUKTI_PER_ELEMEN) {
                throw ValidationException::withMessages(['file' => ['Maksimal '.self::MAKS_BUKTI_PER_ELEMEN.' bukti per elemen.']]);
            }
            $row = new Apl02BuktiRelevan([
                'apl02_jawaban_id' => $jawaban->id,
                'urutan' => (int) $jawaban->bukti()->max('urutan') + 1,
            ]);
        }

        $row->fill([
            'path_file' => $path,
            'nama_file_asli' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime_type' => $file->getMimeType(),
            'ukuran_file' => (int) ceil($file->getSize() / 1024),
        ])->save();

        return true;
    }

    /** Hapus berkas dari baris bukti; baris tanpa link ikut dihapus. */
    public function hapusBerkas(DokumenPenilaian $dokumen, int $buktiId, array &$hapusNanti): bool
    {
        $row = $this->buktiMilikDokumen($dokumen, $buktiId);
        if (! $row?->path_file) {
            return false;
        }

        $hapusNanti[] = [self::disk(), $row->path_file];

        if (filled($row->link) || $row->apl01_bukti_relevan_id !== null) {
            $row->update(['path_file' => null, 'nama_file_asli' => null, 'mime_type' => null, 'ukuran_file' => null]);
        } else {
            $row->delete();
        }

        return true;
    }

    public function buktiMilikDokumen(DokumenPenilaian $dokumen, int $buktiId): ?Apl02BuktiRelevan
    {
        return Apl02BuktiRelevan::query()
            ->whereKey($buktiId)
            ->whereHas('jawaban', fn ($q) => $q->where('dokumen_penilaian_id', $dokumen->id))
            ->first();
    }

    public function elemenMilikSkema(DokumenPenilaian $dokumen, int $elemenId): bool
    {
        return $this->idElemen($dokumen)->contains($elemenId);
    }

    /**
     * Lampiran bukti yang relevan FR.APL.01 penilaian ini yang berisi berkas/link
     * (urut template lalu tambahan asesi). Kosong bila tidak ada / APL.01 belum dibuat.
     *
     * @return Collection<int, Apl01BuktiRelevan>
     */
    public function lampiranApl01(DokumenPenilaian $dokumen): Collection
    {
        $apl01 = $dokumen->penilaianMuk->dokumenPenilaian()->kode(JenisDokumenMuk::APL_01)->first();

        return $apl01
            ? $apl01->buktiRelevan()->get()->filter(fn (Apl01BuktiRelevan $b) => $b->terisi())->values()
            : collect();
    }

    public static function disk(): string
    {
        return config('muk.tanda_tangan.disk');
    }

    /* ------------------------------- submit ------------------------------- */

    public function kesalahanSubmit(DokumenPenilaian $dokumen): array
    {
        $jawaban = $dokumen->apl02Jawaban()->with('bukti.lampiranApl01')->get()->keyBy('elemen_kompetensi_id');
        $errors = [];

        foreach ($this->idElemen($dokumen) as $id) {
            $row = $jawaban->get($id);
            if (! $row?->kompeten) {
                $errors["jawaban.{$id}"] = 'Pilih K atau BK.';
            } elseif ($row->kompeten === 'K' && $row->bukti->filter->terisi()->isEmpty()) {
                $errors["jawaban.{$id}"] = 'Unggah dokumen, sisipkan link, atau pilih lampiran FR.APL.01 minimal satu bukti.';
            }
        }

        if ($errors !== []) {
            $errors = ['jawaban' => count($errors).' elemen belum lengkap.'] + $errors;
        }

        return $errors;
    }

    public function snapshot(DokumenPenilaian $dokumen): array
    {
        return $this->master($this->skema($dokumen));
    }

    private function elemenLengkap(?string $kompeten, int $jumlahBukti): bool
    {
        return $kompeten === 'BK' || ($kompeten === 'K' && $jumlahBukti > 0);
    }

    private function skema(DokumenPenilaian $dokumen): SkemaSertifikasi
    {
        return SkemaSertifikasi::findOrFail($dokumen->penilaianMuk->pendaftaran->skema_sertifikasi_id);
    }

    /** @return Collection<int, int> */
    private function idElemen(DokumenPenilaian $dokumen): Collection
    {
        return ElemenKompetensi::query()
            ->whereHas('unitKompetensi', fn ($q) => $q->where('skema_sertifikasi_id', $dokumen->penilaianMuk->pendaftaran->skema_sertifikasi_id))
            ->pluck('id');
    }

    private function namaAsesi(DokumenPenilaian $dokumen): ?string
    {
        // Nama sesuai isian FR.APL.01 bila ada, selain itu nama akun asesi.
        $apl01 = $dokumen->penilaianMuk->dokumenPenilaian()->kode(JenisDokumenMuk::APL_01)->value('data');
        $data = is_string($apl01) ? json_decode($apl01, true) : $apl01;

        return $data['pribadi']['nama_lengkap'] ?? $dokumen->penilaianMuk->pendaftaran->asesi?->name;
    }

    /** Master unit -> elemen -> KUK (satu query eager load, tanpa N+1). */
    private function master(SkemaSertifikasi $skema): array
    {
        $skema->loadMissing('unitKompetensis.elemenKompetensi.kriteriaUnjukKerja');

        return [
            'skema' => [
                'id' => $skema->id,
                'judul' => $skema->title['id'] ?? null,
                'nomor' => $skema->nomor_skema,
                'jenis' => $skema->jenis_skema,
            ],
            'unit_kompetensi' => $skema->unitKompetensis->map(fn ($u) => [
                'id' => $u->id,
                'kode_unit' => $u->kode_unit,
                'judul_unit' => $u->judul_unit,
                'elemen' => $u->elemenKompetensi->map(fn ($e) => [
                    'id' => $e->id,
                    'nomor' => $e->nomor_elemen,
                    'nama' => $e->nama_elemen,
                    'kuk' => $e->kriteriaUnjukKerja->map(fn ($k) => [
                        'id' => $k->id,
                        'nomor' => $k->nomor_kuk,
                        'deskripsi' => $k->deskripsi,
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}

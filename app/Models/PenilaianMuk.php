<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class PenilaianMuk extends Model
{
    public const STATUS_MENUNGGU_PENGISIAN_APL = 'menunggu_pengisian_apl';

    public const STATUS_MENUNGGU_REVIEW = 'menunggu_review';

    public const STATUS_BERLANGSUNG = 'berlangsung';

    public const STATUS_SELESAI_KOMPETEN = 'selesai_kompeten';

    public const STATUS_SELESAI_BELUM_KOMPETEN = 'selesai_belum_kompeten';

    public const STATUS = [
        self::STATUS_MENUNGGU_PENGISIAN_APL,
        self::STATUS_MENUNGGU_REVIEW,
        self::STATUS_BERLANGSUNG,
        self::STATUS_SELESAI_KOMPETEN,
        self::STATUS_SELESAI_BELUM_KOMPETEN,
    ];

    public const METODE = ['kt', 'tpd', 'vp'];

    public const STATUS_SELESAI = [self::STATUS_SELESAI_KOMPETEN, self::STATUS_SELESAI_BELUM_KOMPETEN];

    protected $fillable = [
        'pendaftaran_sertifikasi_id',
        'jadwal_asesmen_id',
        'ditentukan_oleh_asesor_id',
        'metode_asesmen',
        'status',
        'metode_ditentukan_at',
        'selesai_at',
    ];

    protected $casts = [
        'metode_ditentukan_at' => 'datetime',
        'selesai_at' => 'datetime',
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PendaftaranSertifikasi::class, 'pendaftaran_sertifikasi_id');
    }

    public function jadwalAsesmen(): BelongsTo
    {
        return $this->belongsTo(JadwalAsesmen::class);
    }

    public function ditentukanOlehAsesor(): BelongsTo
    {
        return $this->belongsTo(Asesor::class, 'ditentukan_oleh_asesor_id');
    }

    public function dokumenPenilaian(): HasMany
    {
        return $this->hasMany(DokumenPenilaian::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scope kepemilikan (API MUK)
    |--------------------------------------------------------------------------
    | Semua query penilaian dari API MUK WAJIB lewat forUser(), bukan find($id)
    | lalu dicek. Penilaian milik orang lain otomatis tidak ditemukan (404).
    | Jadwal 'dibatalkan' disembunyikan dari asesi & asesor.
    */

    public function scopeForAsesi(Builder $query, Asesi $asesi): Builder
    {
        return $query
            ->whereHas('pendaftaran', fn (Builder $q) => $q->where('asesi_id', $asesi->id))
            ->whereHas('jadwalAsesmen', fn (Builder $q) => $q->where('status', '!=', 'dibatalkan'));
    }

    public function scopeForAsesor(Builder $query, Asesor $asesor): Builder
    {
        // Pakai asesor di jadwal (bukan ditentukan_oleh_asesor_id): kalau admin mengganti
        // asesor di jadwal, akses asesor lama otomatis hilang.
        return $query->whereHas('jadwalAsesmen', fn (Builder $q) => $q
            ->where('asesor_id', $asesor->id)
            ->where('status', '!=', 'dibatalkan'));
    }

    public function scopeForUser(Builder $query, mixed $user): Builder
    {
        return match (true) {
            $user instanceof Asesi => $query->forAsesi($user),
            $user instanceof Asesor => $query->forAsesor($user),
            default => throw new InvalidArgumentException('User bukan asesi/asesor.'),
        };
    }

    /**
     * Penilaian yang menunggu aksi dari peran tsb. Satu-satunya definisi "perlu tindakan",
     * dipakai untuk filter dashboard dan flag di respons.
     */
    public function scopePerluTindakan(Builder $query, string $peran): Builder
    {
        $query->whereNotIn('status', self::STATUS_SELESAI);

        if ($peran === 'asesi') {
            // Asesi: APL yang belum dikirim, atau dokumen asesor yang menunggu persetujuannya (AK.07/AK.01).
            return $query->whereHas('dokumenPenilaian', fn (Builder $d) => $d
                ->where(fn (Builder $w) => $w
                    ->where(fn (Builder $apl) => $apl
                        ->whereHas('jenisDokumen', fn (Builder $j) => $j->whereIn('kode', JenisDokumenMuk::APL))
                        ->whereIn('status', [DokumenPenilaian::STATUS_BELUM_DIISI, DokumenPenilaian::STATUS_DRAFT]))
                    ->orWhere('status', DokumenPenilaian::STATUS_MENUNGGU_PERSETUJUAN)
                    // Dokumen yang sudah dibuka asesor untuk dijawab (mis. FR.IA.03) tapi belum dikirim.
                    ->orWhere(fn (Builder $jawab) => $jawab
                        ->whereIn('status', [DokumenPenilaian::STATUS_BELUM_DIISI, DokumenPenilaian::STATUS_DRAFT])
                        ->whereNotNull('data_review->persiapan->dibuka_at'))));
        }

        // Asesor: ada APL.02 yang menunggu review (dan APL.01 sudah diterima admin), atau
        // APL.02 sudah direkomendasikan "dapat dilanjutkan" tapi metode asesmen belum dipilih, atau
        // jawaban asesi pada dokumen asesmen (mis. FR.IA.03) menunggu dinilai.
        return $query->where(fn (Builder $w) => $w
            ->whereHas('dokumenPenilaian', fn (Builder $d) => $d
                ->where('status', DokumenPenilaian::STATUS_MENUNGGU_REVIEW)
                ->whereHas('jenisDokumen', fn (Builder $j) => $j->whereNotIn('kode', JenisDokumenMuk::APL)))
            ->orWhere(fn (Builder $r) => $r
                ->whereHas('dokumenPenilaian', fn (Builder $d) => $d
                    ->kode(JenisDokumenMuk::APL_02)
                    ->where('status', DokumenPenilaian::STATUS_MENUNGGU_REVIEW))
                ->whereHas('dokumenPenilaian', fn (Builder $d) => $d
                    ->kode(JenisDokumenMuk::APL_01)
                    ->where('status', DokumenPenilaian::STATUS_SELESAI)
                    ->where('hasil_rekomendasi_admin', 'diterima')))
            ->orWhere(fn (Builder $x) => $x
                ->whereNull('metode_ditentukan_at')
                ->whereHas('dokumenPenilaian', fn (Builder $d) => $d
                    ->kode(JenisDokumenMuk::APL_02)
                    ->where('status', DokumenPenilaian::STATUS_SELESAI)
                    ->where('hasil_rekomendasi_asesor', 'dapat_dilanjutkan'))));
    }

    // shortcut, dipakai buat akses skema tanpa nulis rantai panjang tiap kali
    public function getSkemaAttribute()
    {
        return $this->pendaftaran?->skemaSertifikasi;
    }
}

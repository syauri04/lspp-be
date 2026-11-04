<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class PendaftaranSertifikasi extends Model
{
    use Notifiable, SoftDeletes;

    /** Label status untuk asesi (halaman profil lspp-fe). */
    public const STATUS_LABEL = [
        'submitted' => 'Diajukan',
        'approved' => 'Disetujui',
        'awaiting_payment' => 'Menunggu pembayaran',
        'paid' => 'Pembayaran diterima',
        'dijadwalkan' => 'Terjadwal',
        'asesmen_selesai' => 'Asesmen selesai',
        'completed' => 'Selesai',
        'rejected' => 'Ditolak',
        'expired' => 'Kedaluwarsa',
        'cancelled' => 'Dibatalkan',
    ];

    /** Kelompok tab filter di profil -> status yang termasuk. */
    public const KELOMPOK = [
        'diajukan' => ['submitted'],
        'diproses' => ['approved', 'awaiting_payment', 'paid', 'dijadwalkan'],
        'selesai' => ['asesmen_selesai', 'completed'],
        'ditolak' => ['rejected', 'expired', 'cancelled'],
    ];

    public static function kelompokDari(?string $status): ?string
    {
        foreach (self::KELOMPOK as $kelompok => $daftar) {
            if (in_array($status, $daftar, true)) {
                return $kelompok;
            }
        }

        return null;
    }

    protected $fillable = [
        'kode_pendaftaran',
        'asesi_id',
        'skema_sertifikasi_id',
        'status',
        'catatan_admin',
        'harga_snapshot',
        'reviewed_by',
        'reviewed_at',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'harga_snapshot' => 'decimal:2',
    ];

    // Route model binding pakai kode_pendaftaran (UUID), bukan id numerik,
    // supaya URL publik tidak bisa ditebak.
    public function getRouteKeyName(): string
    {
        return 'kode_pendaftaran';
    }

    public function asesi(): BelongsTo
    {
        return $this->belongsTo(Asesi::class);
    }

    public function skemaSertifikasi(): BelongsTo
    {
        return $this->belongsTo(SkemaSertifikasi::class);
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenPendaftaran::class);
    }

    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(RiwayatStatusPendaftaran::class);
    }

    public function pembayaran(): HasOne
    {
        return $this->hasOne(Pembayaran::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function jadwalAsesmen()
    {
        return $this->hasOne(JadwalAsesmen::class, 'pendaftaran_sertifikasi_id');
    }

    public function penilaianMuk()
    {
        return $this->hasOne(PenilaianMuk::class, 'pendaftaran_sertifikasi_id');
    }
}

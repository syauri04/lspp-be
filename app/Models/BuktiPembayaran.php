<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bukti transfer manual unggahan asesi. Satu tagihan (pembayaran) bisa punya beberapa bukti
 * (riwayat bila ditolak lalu diunggah ulang), tetapi maksimal satu berstatus "menunggu".
 */
class BuktiPembayaran extends Model
{
    public const MENUNGGU = 'menunggu';

    public const DISETUJUI = 'disetujui';

    public const DITOLAK = 'ditolak';

    /** Tagihan lunas lewat jalur lain (mis. DOKU) saat bukti masih menunggu. */
    public const DIBATALKAN = 'dibatalkan';

    public const STATUS_LABEL = [
        self::MENUNGGU => 'Menunggu verifikasi',
        self::DISETUJUI => 'Disetujui',
        self::DITOLAK => 'Ditolak',
        self::DIBATALKAN => 'Dibatalkan',
    ];

    /** Metode pembayaran yang dicatat di pembayarans.metode_pembayaran saat disetujui. */
    public const METODE = 'TRANSFER_MANUAL';

    public const MIMES = ['jpg', 'jpeg', 'png', 'pdf'];

    public const MAKS_KB = 5120;

    protected $fillable = [
        'pembayaran_id', 'rekening_pembayaran_id', 'rekening_tujuan',
        'nama_pengirim', 'bank_pengirim', 'tanggal_transfer', 'jumlah_transfer', 'catatan_asesi',
        'path_file', 'nama_file_asli', 'mime_type', 'ukuran_file',
        'status', 'catatan_admin', 'diverifikasi_oleh', 'diverifikasi_at',
    ];

    protected $hidden = ['path_file'];

    protected $casts = [
        'rekening_tujuan' => 'array',
        'tanggal_transfer' => 'date',
        'jumlah_transfer' => 'decimal:2',
        'diverifikasi_at' => 'datetime',
    ];

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }

    public function rekening(): BelongsTo
    {
        return $this->belongsTo(RekeningPembayaran::class, 'rekening_pembayaran_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public static function disk(): string
    {
        // Disk privat yang sama dengan berkas MUK (storage/app/private).
        return config('muk.tanda_tangan.disk');
    }

    /** Jumlah transfer berbeda dengan tagihan (ditandai untuk admin). */
    public function jumlahBerbeda(): bool
    {
        // Bandingkan dalam sen (integer) agar bebas galat floating point.
        return (int) round((float) $this->jumlah_transfer * 100) !== (int) round((float) $this->pembayaran->jumlah * 100);
    }
}

<?php

namespace App\Services\Muk;

use App\Models\DokumenPenilaian;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tanda tangan digital (PNG dari canvas). Generik untuk semua dokumen & pihak.
 *
 * Keamanan:
 * - hanya data URL "data:image/png;base64,", base64 strict, batas ukuran & dimensi,
 * - diverifikasi benar-benar PNG (getimagesizefromstring), lalu DI-ENCODE ULANG lewat GD
 *   sehingga byte asli (metadata / payload polyglot) tidak pernah tersimpan,
 * - disimpan di disk privat; DB hanya menyimpan path. Akses lewat endpoint terotorisasi.
 */
class TandaTanganService
{
    public const PIHAK = ['asesi', 'asesor', 'admin'];

    private const PREFIX = 'data:image/png;base64,';

    /** Validasi + sanitasi; kembalikan byte PNG bersih. */
    public function bersihkan(string $dataUrl, string $field = 'tanda_tangan'): string
    {
        $cfg = config('muk.tanda_tangan');
        $gagal = fn (string $pesan) => throw ValidationException::withMessages([$field => [$pesan]]);

        if (! str_starts_with($dataUrl, self::PREFIX)) {
            $gagal('Format tanda tangan harus PNG.');
        }

        $base64 = substr($dataUrl, strlen(self::PREFIX));

        // Cek ukuran sebelum decode (base64 ~ 4/3 ukuran asli).
        if (strlen($base64) > (int) ceil($cfg['max_bytes'] * 4 / 3) + 4) {
            $gagal('Ukuran tanda tangan terlalu besar.');
        }

        $biner = base64_decode($base64, true);
        if ($biner === false || $biner === '') {
            $gagal('Data tanda tangan tidak valid.');
        }

        $info = @getimagesizefromstring($biner);
        if ($info === false || $info[2] !== IMAGETYPE_PNG) {
            $gagal('Tanda tangan bukan gambar PNG yang valid.');
        }

        [$w, $h] = $info;
        if ($w > $cfg['max_width'] || $h > $cfg['max_height'] || $w < $cfg['min_width'] || $h < $cfg['min_height']) {
            $gagal('Dimensi tanda tangan tidak valid.');
        }

        $img = @imagecreatefromstring($biner);
        if ($img === false) {
            $gagal('Tanda tangan tidak dapat diproses.');
        }

        if ($this->kosong($img, $w, $h)) {
            imagedestroy($img);
            $gagal('Tanda tangan masih kosong.');
        }

        imagesavealpha($img, true);
        ob_start();
        imagepng($img, null, 9);
        $bersih = (string) ob_get_clean();
        imagedestroy($img);

        return $bersih;
    }

    /**
     * Tanda tangan dari berkas gambar unggahan (CMS: penyusun/validator/manajer).
     * Divalidasi sebagai gambar lalu di-encode ulang ke PNG (byte asli tidak disimpan).
     */
    public function bersihkanBerkas(UploadedFile $file, string $field = 'ttd'): string
    {
        $gagal = fn (string $pesan) => throw ValidationException::withMessages([$field => [$pesan]]);

        $biner = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($biner);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            $gagal('Tanda tangan harus berupa gambar PNG atau JPG.');
        }
        if ($info[0] > 4000 || $info[1] > 4000) {
            $gagal('Dimensi gambar tanda tangan terlalu besar.');
        }

        $img = @imagecreatefromstring($biner);
        if ($img === false) {
            $gagal('Gambar tanda tangan tidak dapat diproses.');
        }

        imagesavealpha($img, true);
        ob_start();
        imagepng($img, null, 9);
        $bersih = (string) ob_get_clean();
        imagedestroy($img);

        return $bersih;
    }

    /** Simpan PNG bersih untuk pihak tertentu; kembalikan path relatif di disk privat. */
    public function simpan(DokumenPenilaian $dokumen, string $pihak, string $png): string
    {
        $slug = str($dokumen->jenisDokumen->kode)->after('FR.')->lower()->replace('.', '-');
        $path = "signatures/{$dokumen->penilaian_muk_id}/{$slug}/{$pihak}-".Str::uuid().'.png';

        Storage::disk($this->disk())->put($path, $png);

        return $path;
    }

    public function hapus(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    public function ada(?string $path): bool
    {
        return $path !== null && Storage::disk($this->disk())->exists($path);
    }

    public function disk(): string
    {
        return config('muk.tanda_tangan.disk');
    }

    /** Canvas kosong = semua piksel transparan / satu warna. Sampling agar murah. */
    private function kosong(\GdImage $img, int $w, int $h): bool
    {
        $pertama = null;
        $langkahX = max(1, intdiv($w, 400));
        $langkahY = max(1, intdiv($h, 200));

        for ($y = 0; $y < $h; $y += $langkahY) {
            for ($x = 0; $x < $w; $x += $langkahX) {
                $warna = imagecolorat($img, $x, $y);
                $alpha = ($warna >> 24) & 0x7F;
                if ($alpha === 127) {
                    $warna = 'transparan';
                }
                $pertama ??= $warna;
                if ($warna !== $pertama) {
                    return false;
                }
            }
        }

        return true;
    }
}

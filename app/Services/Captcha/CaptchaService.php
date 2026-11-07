<?php

namespace App\Services\Captcha;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Captcha gambar sederhana tanpa layanan pihak ketiga.
 *
 * - Kode acak per permintaan (random_int = CSPRNG), dirender ke PNG dengan GD
 *   (huruf diputar, warna acak, garis & titik pengganggu) sehingga teksnya
 *   tidak ada di HTML/DOM.
 * - Yang disimpan di cache hanya HMAC jawaban (bukan kode asli), berumur pendek.
 * - Sekali pakai: kunci langsung dihapus saat diverifikasi, benar atau salah,
 *   sehingga satu gambar tidak bisa ditebak berulang kali.
 * - Stateless di sisi aplikasi (cache bersama), aman untuk banyak server.
 */
class CaptchaService
{
    private const PREFIX = 'captcha:';

    /** @return array{id: string, gambar: string, kedaluwarsa_detik: int} */
    public function buat(): array
    {
        $karakter = config('captcha.karakter');
        $kode = '';
        for ($i = 0; $i < config('captcha.panjang'); $i++) {
            $kode .= $karakter[random_int(0, strlen($karakter) - 1)];
        }

        $id = (string) Str::uuid();
        $ttl = (int) config('captcha.ttl');
        Cache::put(self::PREFIX.$id, $this->hash($kode), $ttl);

        return [
            'id' => $id,
            'gambar' => 'data:image/png;base64,'.base64_encode($this->render($kode)),
            'kedaluwarsa_detik' => $ttl,
        ];
    }

    public function verifikasi(?string $id, ?string $jawaban): bool
    {
        if (! is_string($id) || ! Str::isUuid($id) || ! is_string($jawaban)) {
            return false;
        }

        // pull = ambil + hapus (sekali pakai).
        $hash = Cache::pull(self::PREFIX.$id);
        if (! is_string($hash)) {
            return false;
        }

        $normal = strtoupper(preg_replace('/\s+/', '', $jawaban));

        return hash_equals($hash, $this->hash($normal));
    }

    private function hash(string $kode): string
    {
        return hash_hmac('sha256', $kode, (string) config('app.key'));
    }

    /** PNG 180x56: tiap huruf digambar dengan font bawaan GD, diperbesar, diputar acak. */
    private function render(string $kode): string
    {
        $lebar = 180;
        $tinggi = 56;
        $img = imagecreatetruecolor($lebar, $tinggi);
        imagefill($img, 0, 0, imagecolorallocate($img, 249, 251, 248));

        // Garis & titik pengganggu di belakang huruf.
        for ($i = 0; $i < 6; $i++) {
            $warna = imagecolorallocate($img, random_int(160, 220), random_int(160, 220), random_int(160, 220));
            imagesetthickness($img, random_int(1, 2));
            imageline($img, random_int(0, $lebar), random_int(0, $tinggi), random_int(0, $lebar), random_int(0, $tinggi), $warna);
        }
        for ($i = 0; $i < 250; $i++) {
            $warna = imagecolorallocate($img, random_int(150, 230), random_int(150, 230), random_int(150, 230));
            imagesetpixel($img, random_int(0, $lebar - 1), random_int(0, $tinggi - 1), $warna);
        }

        // Palet sesuai desain: abu tua, hijau LSPP, merah muda.
        $palet = [[75, 85, 99], [130, 177, 36], [248, 113, 113], [55, 65, 81], [101, 140, 25]];
        $font = 5;
        $fw = imagefontwidth($font);
        $fh = imagefontheight($font);
        $skala = 3;
        $slot = (int) floor(($lebar - 16) / strlen($kode));

        foreach (str_split($kode) as $i => $huruf) {
            $kecil = imagecreatetruecolor($fw, $fh);
            $transparan = imagecolorallocatealpha($kecil, 0, 0, 0, 127);
            imagealphablending($kecil, false);
            imagefill($kecil, 0, 0, $transparan);
            imagesavealpha($kecil, true);
            [$r, $g, $b] = $palet[random_int(0, count($palet) - 1)];
            imagestring($kecil, $font, 0, 0, $huruf, imagecolorallocate($kecil, $r, $g, $b));

            $besar = imagecreatetruecolor($fw * $skala, $fh * $skala);
            imagealphablending($besar, false);
            imagefill($besar, 0, 0, imagecolorallocatealpha($besar, 0, 0, 0, 127));
            imagesavealpha($besar, true);
            imagecopyresized($besar, $kecil, 0, 0, 0, 0, $fw * $skala, $fh * $skala, $fw, $fh);

            $putar = imagerotate($besar, random_int(-25, 25), imagecolorallocatealpha($besar, 0, 0, 0, 127));
            imagealphablending($img, true);
            $x = 8 + $i * $slot + random_int(-2, 2);
            $y = (int) (($tinggi - imagesy($putar)) / 2) + random_int(-4, 4);
            imagecopy($img, $putar, $x, $y, 0, 0, imagesx($putar), imagesy($putar));

            imagedestroy($kecil);
            imagedestroy($besar);
            imagedestroy($putar);
        }

        // Satu garis melintang di atas huruf agar OCR sederhana lebih sulit.
        imagesetthickness($img, 2);
        imageline($img, 0, random_int(18, 38), $lebar, random_int(18, 38), imagecolorallocate($img, 130, 177, 36));

        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}

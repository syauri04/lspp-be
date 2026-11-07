<?php

/*
| Captcha gambar untuk login & register asesi di lspp-fe.
| Kode dibuat & dicek di server; jawaban disimpan di cache (hash) sehingga aman
| dipakai di beberapa server sekaligus selama CACHE_STORE bersama (database/redis).
*/

return [
    // Matikan hanya untuk keperluan test/darurat. Bawaan: aktif.
    'aktif' => (bool) env('CAPTCHA_AKTIF', true),

    'panjang' => 6,

    // Masa berlaku kode (detik). Setiap kode hanya bisa dipakai sekali.
    'ttl' => 300,

    // Tanpa karakter yang mirip (0/O, 1/I/L) agar mudah dibaca manusia.
    'karakter' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
];

# API MUK Paperless (`/api/muk`)

Backend untuk web sertifikasi paperless (`muk/`, domain `sertifikasi.lspp306.com`).
Dokumen ini untuk developer BE/FE. Semua respons JSON; header wajib `Accept: application/json`.

---

## 1. Autentikasi

Login MUK **terpisah** dari login website utama (lspp-fe). Token lspp-fe ditolak di `/api/muk/*`, dan token MUK ditolak di endpoint akun lspp-fe.

| Method | Path | Keterangan |
|---|---|---|
| POST | `/muk/auth/asesi/login` | `{email, password}` → token berability `muk:asesi` |
| POST | `/muk/auth/asesor/login` | `{email, password}` → token berability `muk:asesor` |
| GET | `/muk/auth/me` | Profil + `role` |
| POST | `/muk/auth/logout` | Mencabut token yang dipakai |

Respons login:
```json
{ "message": "Login berhasil.",
  "data": { "token": "1|...", "token_type": "Bearer", "expires_at": "2026-10-14T18:00:00+07:00",
            "user": { "id": 1, "role": "asesi", "name": "...", "email": "...", "phone": null, "avatar_url": null } } }
```

- Token berlaku `MUK_TOKEN_TTL_MINUTES` (default 480 menit). Token kedaluwarsa dibersihkan harian (`sanctum:prune-expired`).
- Rate limit: 5 percobaan **gagal** per email+IP per menit, dan 20 request login per IP per menit.
- Pesan gagal generik ("Email atau kata sandi salah."). Asesi Google tanpa password harus membuat password di lspp-fe.
- Asesor `nonaktif` / dihapus: login ditolak dan semua tokennya dicabut.

Kirim token sebagai `Authorization: Bearer <token>`.

## 2. Kepemilikan & kode status

- Semua query penilaian dibatasi ke pemilik: asesi lewat `pendaftaran.asesi_id`, asesor lewat `jadwal_asesmens.asesor_id`. Jadwal `dibatalkan` disembunyikan.
- Bukan milik user / tidak ada → **404** `{"message":"Data tidak ditemukan."}` (tanpa nama model).
- Peran salah / dokumen terkunci → **403** dengan `message` alasan.
- Status tidak memungkinkan aksi (mis. sudah dikirim, sedang tidak menunggu review) → **409**.
- Validasi → **422** standar Laravel (`errors.{field}`).

## 3. Penilaian

| Method | Path | Peran | Keterangan |
|---|---|---|---|
| GET | `/muk/penilaian` | asesi, asesor | `?kelompok=perlu_tindakan\|berlangsung\|selesai`, `?status=`, `?per_page=` (≤50), `?page=` |
| GET | `/muk/penilaian/{id}` | asesi, asesor | Detail + daftar dokumen (`terkunci`, `alasan_terkunci`, catatan). Asesor: `aksi_metode {bisa, alasan}` |
| POST | `/muk/penilaian/{id}/metode` | asesor | `{metode: kt\|tpd\|vp}`. Lihat §6 |

Status penilaian: `menunggu_pengisian_apl` → `menunggu_review` (kedua APL dikirim) → `berlangsung` (metode ditetapkan) → `selesai_kompeten` / `selesai_belum_kompeten`. Bila salah satu APL dikembalikan, status kembali ke `menunggu_pengisian_apl`.

## 4. Dokumen (generik per slug)

`{slug}` = `apl-01`, `apl-02` (dokumen berikutnya cukup menambah handler di `config/muk.php → dokumen_handlers`).

| Method | Path | Peran | Keterangan |
|---|---|---|---|
| GET | `/muk/penilaian/{id}/dokumen/{slug}` | pengisi & peninjau | Payload dokumen (meta generik + isi handler) |
| PATCH | `/muk/penilaian/{id}/dokumen/{slug}` | asesi | Simpan isian (rate limit 120/menit) |
| POST | `/muk/penilaian/{id}/dokumen/{slug}/tanda-tangan` | asesi | `{tanda_tangan: "data:image/png;base64,..."}` |
| POST | `/muk/penilaian/{id}/dokumen/{slug}/submit` | asesi | Validasi kelengkapan + tanda tangan → `menunggu_review` |
| POST | `/muk/penilaian/{id}/dokumen/{slug}/review` | asesor | `{hasil, catatan?, tanda_tangan?}` (lihat §5) |
| GET | `/muk/penilaian/{id}/dokumen/{slug}/tanda-tangan/{asesi\|asesor\|admin}` | yang boleh melihat dokumen | PNG, `Cache-Control: private, no-store` |

Bagian `dokumen` (generik) pada payload:
```json
{ "id": 1, "penilaian_id": 1, "kode": "FR.APL.02", "slug": "apl-02", "status": "menunggu_review",
  "pengisi": "asesi", "peninjau": "asesor", "dapat_diubah": false,
  "review": { "bisa": true, "alasan": null },
  "tanda_tangan": { "asesi": {"ada": true, "tanggal": "2026-10-14"}, "asesor": {...}, "admin": {...} },
  "hasil_rekomendasi_admin": null, "catatan_admin": null,
  "hasil_rekomendasi_asesor": null, "catatan_asesor": null }
```
`review` hanya muncul bila user adalah peninjau dokumen tsb. Path file tidak pernah dikirim.

Status dokumen: `belum_diisi` → `draft` → `menunggu_review` → `selesai` (atau kembali ke `draft` bila dikembalikan peninjau).

### FR.APL.01 (`apl-01`)
- PATCH body: `{ "isian": { "pribadi": {...}, "pekerjaan": {...}, "tujuan_asesmen": "sertifikasi|sertifikasi_ulang|pkt|rpl|lainnya", "tujuan_lainnya": "..." } }`. Key di luar daftar → 422.
- Bukti persyaratan dasar (berkas, PDF/JPG/PNG ≤ 5 MB):
  - `POST /muk/penilaian/{id}/dokumen/apl-01/bukti/{buktiId}` (multipart `file`) — unggah/ganti
  - `DELETE` path yang sama — hapus
  - `GET` path yang sama — lihat berkas
- Submit: data pribadi wajib (kecuali kode pos & telp rumah/kantor), tujuan asesmen, **minimal satu** berkas bukti persyaratan dasar, tanda tangan.
- Peninjau: **admin LSP** di CMS (`/review-apl01`).

### FR.APL.02 (`apl-02`)
- Terkunci bagi asesi sampai APL.01 dikirim (atau dikembalikan admin untuk revisi).
- PATCH body: `{ "jawaban": [ { "elemen_id": 1, "kompeten": "K|BK|null", "bukti": [ { "id": 5|null, "link": "https://..."|null } ] } ] }`
  - Baris bukti yang tidak dikirim dihapus (berkasnya ikut dihapus). Link hanya `http/https`.
- Berkas bukti per elemen:
  - `POST /muk/penilaian/{id}/dokumen/apl-02/elemen/{elemenId}/bukti` (multipart `file`, `bukti_id?` untuk mengganti berkas baris tsb)
  - `DELETE /muk/penilaian/{id}/dokumen/apl-02/bukti/{buktiId}/file`
  - `GET /muk/penilaian/{id}/dokumen/apl-02/bukti/{buktiId}/file`
- Submit: semua elemen dijawab; elemen **K** wajib ≥ 1 bukti (link atau berkas); tanda tangan.
- Peninjau: **asesor**, setelah APL.01 **diterima** admin.

### FR.MAPA.01 (`mapa-01`) & FR.MAPA.02 (`mapa-02`)
- Diisi **asesor**, tanpa peninjau & tanpa tanda tangan asesor (`POST .../tanda-tangan` → 404). Asesi: 403.
- Template (§2 rencana per unit, konfirmasi orang relevan, penyusun & validator) diambil dari CMS per **skema + metode**.
- MAPA.01 PATCH body: `{ "isian": { asesi[], tujuan[], lingkungan[], peluang[], siapa[], konfirmasi[], standar[], hubungan: { bukti|aktivitas|pembelajaran: {checked, emoji: senang|netral|sedih} }, modifikasi: { karakteristik|kontekstualisasi|saran|penyesuaian|peluang_terintegrasi: {ada, catatan} } } }`
  - Grup daftar diganti utuh; `hubungan`/`modifikasi` digabung per butir.
  - Isian awal otomatis (`prefill: true`): tujuan dari APL.01, LSP, manajer sertifikasi, standar kompetensi, 3.4 berisi daftar unit skema.
  - Selesaikan: grup §1 wajib (kecuali standar), emotikon wajib bila hubungan dicentang, §3 Ada/tidak ada wajib (keterangan wajib bila Ada), template rencana lengkap untuk semua unit (`errors.rencana`).
- MAPA.02 PATCH body: `{ "isian": { "potensi": { "ia01": [1,3], "ia04a": [], ... } } }`; selesaikan bila minimal satu potensi dicentang.
- `POST .../submit` → status langsung `selesai`; template + **salinan** gambar ttd dibekukan ke snapshot (perubahan template sesudahnya tidak berpengaruh).
- Gambar ttd penandatangan: `GET /muk/penilaian/{id}/dokumen/{mapa-01|mapa-02}/penandatangan/{penandatanganId}`.
- Dokumen AK/IA terbuka setelah kedua MAPA `selesai`.

## 5. Tinjauan (review)

| Peninjau | Dokumen | Positif | Negatif |
|---|---|---|---|
| admin LSP (CMS) | FR.APL.01 | `diterima` → `selesai` (tanda tangan admin wajib) | `ditolak` → kembali `draft` (catatan wajib) |
| asesor (API) | FR.APL.02 | `dapat_dilanjutkan` → `selesai` (tanda tangan asesor wajib) | `tidak_dapat_dilanjutkan` → kembali `draft` (catatan wajib) |

Saat dikembalikan: tanda tangan pengisi & snapshot dihapus; pengisi merevisi lalu mengirim ulang. Saat dikirim ulang, keputusan peninjau putaran sebelumnya dikosongkan (tetap ada di riwayat).

## 6. Metode asesmen

`POST /muk/penilaian/{id}/metode` `{metode}` — syarat: APL.01 diterima, APL.02 `dapat_dilanjutkan`, penilaian belum selesai. Mengubah status penilaian ke `berlangsung`. Masih bisa diubah selama belum ada dokumen turunan (selain APL). Tercatat di riwayat FR.APL.02.

## 7. Tanda tangan & berkas

- Tanda tangan: hanya `data:image/png;base64,`, ≤ 300 KB, dimensi wajar, bukan kanvas kosong; **di-encode ulang lewat GD**; disimpan di disk privat `storage/app/private/signatures/{penilaian}/{slug}/{pihak}-{uuid}.png`.
- Berkas bukti: tipe divalidasi dari isi (PDF/JPG/PNG), disk privat `storage/app/private/apl01|apl02/...`, diakses hanya lewat endpoint terotorisasi; gambar dikirim dengan `Content-Security-Policy: sandbox`, semua dengan `nosniff` & `no-store`.
- Isi dokumen/tanda tangan berubah setelah ditandatangani → tanda tangan pengisi otomatis dihapus.

## 8. Notifikasi email (queue)

| Kejadian | Penerima | Isi |
|---|---|---|
| Jadwal ditentukan | asesi & asesor | Tombol deep link `MUK_URL/penilaian/{id}` |
| APL.01 dikirim | admin & super-admin (email + dashboard) | Link ke `/review-apl01/{id}` |
| APL.02 dikirim (APL.01 sudah diterima) / APL.01 diterima saat APL.02 menunggu | asesor | Link ke MUK |
| Dokumen diterima / dikembalikan | asesi | Hasil + catatan + link ke MUK |

Deep link tidak memberi akses; penerima tetap harus login.

## 9. Audit trail

Tabel `riwayat_dokumen_penilaians`: setiap aksi (`mulai_diisi`, `tanda_tangan_*`, `unggah_bukti`, `hapus_bukti`, `submit`, `review_admin_*`, `review_asesor_*`, `metode_ditentukan`, `metode_diubah`, `tanda_tangan_dihapus`) beserta pelaku, status dari/ke, catatan, dan meta.

## 10. Checklist deploy produksi

- [ ] `APP_DEBUG=false` (agar detail SQL/stack trace tidak tampil ke pengguna)
- [ ] `MUK_URL=https://sertifikasi.lspp306.com`
- [ ] `CORS_ALLOWED_ORIGINS=https://lspp306.com,https://www.lspp306.com,https://sertifikasi.lspp306.com` (tambahkan origin lain yang memanggil API)
- [ ] `php artisan migrate` (semua migration bersifat menambah, aman untuk data lama)
- [ ] Queue worker berjalan (`php artisan queue:work` via supervisor) — notifikasi memakai queue
- [ ] Scheduler aktif (`* * * * * php artisan schedule:run`) — prune token
- [ ] PHP: `upload_max_filesize ≥ 6M`, `post_max_size ≥ 8M` (berkas bukti 5 MB)
- [ ] `storage/app/private` dapat ditulis web server dan **tidak** ter-expose publik
- [ ] FE `muk/`: `NEXT_PUBLIC_API_URL` & `NEXT_PUBLIC_MAIN_SITE_URL` (nilai API ikut CSP `connect-src`)
- [ ] Role `admin` / `super-admin` ada (penerima notifikasi APL.01)

## 11. Test

```
php artisan config:clear
php artisan test tests/Feature/Muk tests/Feature/Cms
```
Test memakai MySQL `lspp-306_test` (lihat `phpunit.xml`); `TestCase` menolak berjalan di DB yang namanya tidak berakhiran `_test`.

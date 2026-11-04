# Catatan Perbaikan: Halaman Profil Asesi & Notifikasi

Halaman: `lspp-fe/app/[lang]/profile/page.tsx` (website utama, `http://localhost:3000/id/profile`).
Data: `lspp-be` (API `/api/...`, guard Sanctum asesi).
Status: analisis 2026-11-04 — belum dikerjakan. Dikerjakan bertahap (lihat bagian 4).

---

## 1. Layout profil vs data backend

| Bagian di halaman | Sekarang | Data yang tersedia di BE | Kekurangan |
|---|---|---|---|
| Header (foto, nama, telepon, email, edit profil & password) | **Sudah data asli** (`/api/auth/me`, `/api/auth/profile`, `/api/auth/change-password`) | — | Tidak ada |
| "Skema yang diikuti" (kartu + berlaku sampai + Unduh Sertifikat) | Dummy (`skemaData`) | Pendaftaran berstatus `asesmen_selesai` + hasil `penilaian_muks.status` (`selesai_kompeten` / `selesai_belum_kompeten`), judul & gambar skema | **Belum ada data e-sertifikat** (nomor, masa berlaku, berkas) → tampilkan "Sertifikat sedang diproses", tombol unduh nonaktif. Ditunda (lihat Tahap 4) |
| Notifikasi (titik merah = belum dibaca, "Selengkapnya") | Dummy (`notifikasiData`) | Tabel `notifications` (channel database). Untuk asesi baru terisi oleh `PendaftaranDisetujuiNotifikasi` (11 baris di DB dev) | **Belum ada API** daftar/tandai dibaca. Banyak event belum disimpan ke database (lihat bagian 3). Payload lama tidak memuat judul skema |
| Daftar sertifikasi + filter + detail (Batch, TUK, timeline) | Dummy (`sertifikasiData`) | `GET /api/pendaftaran-sertifikasi` (list, judul skema) & `GET /api/pendaftaran-sertifikasi/{kode}` (riwayat status + pembayaran) | List belum memuat gambar skema, jadwal (tanggal, TUK, asesor), status MUK & hasil. **"Batch" tidak ada di BE** → ganti dengan Kode Pendaftaran / Tanggal Asesmen. Filter belum mencakup status Ditolak/Kedaluwarsa |

## 2. Pemetaan status pendaftaran → tampilan profil

Status di `pendaftaran_sertifikasis.status`:

| Status BE | Label di profil | Tab filter | Aksi di kartu |
|---|---|---|---|
| `submitted` | Diajukan | Diajukan | — |
| `approved`, `awaiting_payment` | Disetujui — menunggu pembayaran | Diproses | **Bayar** (ke halaman checkout) |
| `paid` | Pembayaran diterima — menunggu jadwal | Diproses | — |
| `dijadwalkan` | Terjadwal (tanggal, TUK, asesor) | Diproses | **Mulai Asesmen** (ke MUK `sertifikasi.lspp306.com`) |
| `asesmen_selesai` | Selesai — Kompeten / Belum Kompeten (dari MUK) | Selesai | Lihat hasil; sertifikat (Tahap 4) |
| `rejected`, `expired`, `cancelled` | Ditolak / Kedaluwarsa / Dibatalkan | **Tab baru: Ditolak/Batal** | Daftar ulang |

Timeline detail = `riwayat_status_pendaftarans` (status_ke, keterangan, tanggal) + jadwal + hasil MUK.

## 3. Audit notifikasi ke asesi

Email = terkirim lewat email. Profil = tersimpan di tabel `notifications` (channel `database`) sehingga bisa tampil di halaman profil.

| Event | Notifikasi | Email | Profil | Catatan |
|---|---|---|---|---|
| Pendaftaran diajukan (`submitted`) | — (hanya admin yang dikirimi) | ✗ | ✗ | Tambah konfirmasi "Pendaftaran diterima, menunggu verifikasi" |
| Pendaftaran disetujui (`awaiting_payment`) | `PendaftaranDisetujuiNotifikasi` | ✓ | ✓ | Payload belum memuat judul skema & URL |
| Pendaftaran ditolak (`rejected`) | `PendaftaranDitolakNotifikasi` | ✓ | ✓ | Payload belum memuat judul skema |
| Pembayaran berhasil (`paid`, webhook DOKU/Midtrans) | — | ✗ | ✗ | **Belum ada** |
| Pembayaran kedaluwarsa (`expired`) | — | ✗ | ✗ | **Belum ada** |
| Jadwal asesmen ditentukan (`dijadwalkan`) | `JadwalAsesmenDitentukanNotifikasi` | ✓ | ✗ | Tambah channel `database` |
| Pengingat asesmen H-3 / H-1 | — | ✗ | ✗ | **Belum ada** (perlu perintah terjadwal + penanda sudah terkirim) |
| Dokumen MUK perlu persetujuan/jawaban asesi (AK.07, AK.01, IA, AK.02, dst) | `Muk\DokumenPersetujuanNotifikasi` | ✓ | ✗ | Tambah channel `database` (tautan ke MUK) |
| APL.01/APL.02 ditinjau admin/asesor | `Muk\DokumenDitinjauNotifikasi` | ✓ | ✗ | Tambah channel `database` |
| Hasil asesmen ditetapkan (Kompeten / Belum Kompeten) | — | ✗ | ✗ | **Belum ada** (picu dari `SinkronStatusAsesmen::selesai`) |
| E-sertifikat terbit / akan kedaluwarsa | — | ✗ | ✗ | Ditunda — modul sertifikat belum ada |

## 3b. Temuan: zona waktu riwayat status (perlu diputuskan)

`riwayat_status_pendaftarans.created_at` diisi MySQL (`useCurrent()`, zona waktu server MySQL = WIB),
sedangkan kolom lain (mis. `submitted_at`, `paid_at`) diisi Laravel dalam UTC (`config/app.php` timezone `UTC`).
Contoh DB dev: pengajuan `submitted_at` 02:21 UTC, riwayat "submitted" 09:21 (sebenarnya WIB) — API menandai
keduanya `+00:00`, sehingga FE yang mengonversi ke WIB akan menampilkan riwayat **7 jam lebih maju**.

Usulan perbaikan (terpisah, perlu dicek dampaknya ke tampilan riwayat di CMS):
1. Model `RiwayatStatusPendaftaran` mengisi `created_at = now()` (UTC) saat `creating`.
2. Migrasi data: konversi `created_at` lama WIB → UTC (−7 jam), hanya bila server MySQL memang WIB (cek `SELECT @@global.time_zone, @@system_time_zone` di dev & staging).
3. CMS menampilkan waktu dengan `->timezone('Asia/Jakarta')`.

Sementara itu, di FE (Tahap 2) timeline menampilkan **tanggal saja** (tanpa jam) agar tidak salah tampil.

## 4. Rencana bertahap

### Tahap 1 — API profil (lspp-be) ✅ selesai 2026-11-04
Hasil: `GET /api/pendaftaran-sertifikasi` (`?kelompok=`, `meta.jumlah`), `GET /api/pendaftaran-sertifikasi/{kode}`
(+ `timeline`, `pembayaran`), `GET /api/notifikasi` (`?belum_dibaca=1`, `meta.belum_dibaca`),
`POST /api/notifikasi/{id}/baca`, `POST /api/notifikasi/baca-semua`. Semua memakai middleware `asesi.website`
(token MUK ditolak). Test: `tests/Feature/ProfilAsesiTest.php`.

Rincian awal:
- Perluas `GET /api/pendaftaran-sertifikasi`: gambar & slug skema, jadwal (tanggal, waktu, TUK, asesor), status MUK & hasil (kompeten/belum), `aksi` (bayar / mulai asesmen + URL), filter `?kelompok=diajukan|diproses|selesai|ditolak`.
- Perluas detail `/{kode}`: timeline gabungan (riwayat status + jadwal + hasil MUK).
- API notifikasi asesi: `GET /api/notifikasi` (paginasi, jumlah belum dibaca), `POST /api/notifikasi/{id}/baca`, `POST /api/notifikasi/baca-semua`. Hanya milik asesi login.
- Standarkan payload `toArray()`: `{ judul, pesan, skema, kode_pendaftaran, url, kategori }` (data lama tetap terbaca lewat fallback `message`).
- Test Pest untuk kepemilikan data & format respons.

### Tahap 2 — Halaman profil (lspp-fe) ✅ selesai 2026-11-04
Hasil: `lspp-fe/service/profil.service.ts` (tipe + fungsi API + helper tanggal/URL);
`app/[lang]/profile/page.tsx` (3 bagian dummy → data API, layout sama; "Batch" → Jadwal Asesmen, + Asesor, Hasil,
Catatan Admin; tab Diajukan/Diproses/Selesai/Ditolak dengan jumlah; tombol Bayar Sekarang / Mulai Asesmen);
`app/[lang]/notifikasi/page.tsx` (dummy → data API: tandai dibaca saat diklik, "Tandai semua dibaca", muat lebih banyak).
Titik merah lonceng: dikerjakan di Tahap 3.
Notifikasi "disetujui" lama tetap menautkan ke checkout walau pendaftaran sudah lewat tahap bayar (data lama).

Rincian awal:
- Ganti `skemaData`, `notifikasiData`, `sertifikasiData` dengan data API; pertahankan layout & style yang ada.
- Notifikasi: titik merah untuk belum dibaca, klik = tandai dibaca + buka tautan, "Selengkapnya" = halaman berikutnya.
- Kartu sertifikasi: tab Diajukan / Diproses / Selesai / Ditolak; tombol Bayar & Mulai Asesmen sesuai status; detail TUK, jadwal, timeline.
- "Skema yang diikuti": pendaftaran selesai + hasil; unduh sertifikat nonaktif ("Sertifikat sedang diproses").

### Tahap 3 — Melengkapi notifikasi ✅ selesai 2026-11-04
Hasil:
- `StatusPendaftaranNotifikasi` (email + database) dipicu `RiwayatStatusPendaftaranObserver` setiap riwayat status
  `submitted` / `paid` / `expired` / `asesmen_selesai` — mencakup semua jalur (website, CMS, webhook DOKU & Midtrans, MUK).
  Kedaluwarsa dari halaman checkout kini ikut dicatat di riwayat.
- Channel `database` untuk asesi: jadwal ditentukan, dokumen MUK (menunggu persetujuan / siap dijawab / dinilai),
  hasil tinjauan APL. Tautan langsung ke MUK `/dashboard/penilaian/{id}[/dokumen/{slug}]` (sebelumnya `/penilaian/...`
  diredirect ke dashboard sehingga dokumen tidak langsung terbuka).
- `asesmen:kirim-pengingat` (H-3 & H-1, terjadwal 07:00 WIB; sekali per jadwal per hari via cek tabel notifications + kunci cache).
- lspp-fe: titik merah lonceng hanya tampil bila ada notifikasi belum dibaca (`hooks/use-notifikasi-belum-dibaca.ts`),
  diperbarui saat notifikasi ditandai dibaca & saat jendela kembali fokus.
- Test: `tests/Feature/NotifikasiAsesiTest.php`.
- Deploy: cron `schedule:run` & worker antrean wajib aktif (pengingat & email memakai keduanya).
Belum dicakup: pembayaran yang lewat batas tanpa webhook DOKU & tanpa asesi membuka checkout tidak otomatis
ditandai kedaluwarsa (perlu perintah terjadwal terpisah bila diinginkan).

Rincian awal:
- Channel `database` untuk: jadwal ditentukan, dokumen MUK (persetujuan & hasil tinjauan).
- Notifikasi baru: pendaftaran diajukan (konfirmasi), pembayaran berhasil, pembayaran kedaluwarsa, hasil asesmen.
- Pengingat asesmen H-3 & H-1 via scheduler (idempoten; cron `schedule:run` sudah diperlukan).

### Tahap 4 — E-sertifikat (setelah modul sertifikat dibuat)
- Data sertifikat (nomor, masa berlaku, berkas), tombol unduh, notifikasi terbit & pengingat kedaluwarsa.

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
- Lampiran bukti yang relevan (payload `bukti_relevan: [{id, nama, template, link, file}]`): butir dari Template VP (CMS, `instrumen.FR.IA.08.bukti`) disinkronkan otomatis selama belum dikirim (`template: true`, tidak dapat dihapus/diganti nama); asesi dapat menambah baris sendiri (maks 20). Setiap baris berkas (PDF/JPG/PNG ≤ 5 MB) dan/atau link http(s):
  - `POST /muk/penilaian/{id}/dokumen/apl-01/relevan` `{ nama }` — tambah baris asesi
  - `PATCH .../relevan/{relevanId}` `{ nama?, link? }` — nama hanya baris asesi
  - `DELETE .../relevan/{relevanId}` — hapus baris asesi (butir template → 422)
  - `POST .../relevan/{relevanId}/file` (multipart `file`) — unggah/ganti; `DELETE` sama — hapus berkas
  - `GET .../relevan/{relevanId}/file` — lihat berkas (asesi; asesor setelah APL.01 dikirim, untuk IA.08)
  - Perubahan menghapus tanda tangan asesi (sama seperti isian lain). Opsional saat submit.
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
- Dokumen AK terbuka setelah kedua MAPA `selesai`; dokumen IA terbuka setelah FR.AK.07 & FR.AK.01 `selesai` (disetujui asesi). AK.07 & AK.01 boleh diisi dengan urutan bebas.

### FR.AK.07 (`ak-07`) & FR.AK.01 (`ak-01`) — asesor isi, asesi setujui
- Alur: asesor PATCH isian → `POST .../tanda-tangan` (ttd asesor) → `POST .../submit` → status **`menunggu_persetujuan`** (email ke asesi) → asesi `POST .../setujui` `{tanda_tangan}` → **`selesai`** (email ke asesor).
- `POST .../tarik` (asesor): kembali `draft` selama asesi belum menyetujui. Isi berubah setelah ditandatangani → ttd asesor dihapus.
- Asesi baru bisa membuka dokumen setelah dikirim asesor (sebelumnya 403 "Dokumen sedang disiapkan asesor."). Asesi tidak bisa PATCH.
- Payload meta tambahan: `penyetuju` ("asesi"), `persetujuan` `{bisa, alasan}` (khusus penyetuju), `dapat_ditarik`.
- AK.07 PATCH: `{ "isian": { tuk: sewaktu|tempat_kerja|mandiri, tanggal: Y-m-d, potensi: ["1".."5"], karakter: { k1..k8: { penyesuaian: ya|tidak, pilihan: [indeks], lainnya: [{teks, checked}] } }, hasil: { acuan, metode, instrumen } } }`
  - Isian awal: tanggal (jadwal), potensi (MAPA.01 §1.1), acuan (standar kompetensi), metode, instrumen IA sesuai metode.
  - Kirim: TUK, tanggal, potensi ≥ 1, setiap baris Ya/Tidak (Ya → ≥ 1 keterangan), hasil 1–3 terisi, ttd asesor.
- AK.01 PATCH: `{ "isian": { tuk, bukti: [portofolio|observasi|lisan|lainnya|reviu_produk|kegiatan_terstruktur|tertulis|wawancara], bukti_lainnya, pelaksanaan: { tanggal, waktu, tuk } } }`
  - Isian awal: TUK (dari AK.07), bukti (rencana MAPA.01: kolom metode + kode DPL/DPT/PW), pelaksanaan (jadwal & TUK).
  - Kirim: TUK, bukti ≥ 1 (Lainnya wajib ditulis), pelaksanaan lengkap, ttd asesor.

### FR.IA.01 / IA.02 / IA.03 (`ia-01`, `ia-02`, `ia-03`): metode TPD
- Alur sama dengan AK.07/AK.01: asesor isi + ttd → kirim → asesi `setujui` dengan ttd → selesai (bisa `tarik`).
- Terbuka setelah AK.07 & AK.01 selesai. Isian awal: TUK (AK.01/AK.07), tanggal (jadwal).
- IA.01 PATCH: `{ isian: { tuk, tanggal, elemen: { "e{elemenId}": { standar, pencapaian: ya|tidak, lanjut } }, umpan_balik, rekomendasi: kompeten|lanjutan, lanjutan: { kelompok, unit, elemen, kuk } } }`. Struktur unit/elemen/KUK dari skema (`struktur`, dibekukan di snapshot). Kirim: semua elemen dinilai, rekomendasi "kompeten" ditolak bila ada elemen "tidak", lanjutan wajib menyebut unit, umpan balik wajib.
- IA.02 PATCH: `{ isian: { tuk, tanggal } }`. Skenario, waktu & penyusun/validator dari Template TPD (CMS), dibekukan + salinan ttd saat dikirim; gambar ttd via `.../penandatangan/{id}`. Kirim ditolak bila skenario belum diisi di CMS (`errors.instrumen`).
- IA.03 (asesor isi + ttd → asesi setujui, seperti IA.09): `{ isian: { tuk, tanggal, jawaban: [{tanggapan, pencapaian}], tambahan: [{teks, tanggapan, pencapaian}] (maks 10), umpan_balik } }`. Tanggapan asesi **dicatat asesor**. Pertanyaan dari Template TPD (`instrumen.FR.IA.03`) disalin ke `isian.pertanyaan` saat pertama disimpan (tidak bisa dikirim klien). Kirim: TUK, tanggal, ≥ 1 pertanyaan, setiap pertanyaan bertanggapan & Ya/Tidak, umpan balik. Asesi melihat setelah dikirim.
  - Migrasi `2026_10_27_100000_fr_ia_03_tanggapan_diisi_asesor`: data alur sebelumnya (persiapan/penilaian di `data_review`) dikonversi; dokumen selesai tetap selesai, yang berjalan menjadi draft asesor (tanggapan dipertahankan, ttd asesi dikosongkan). Data lama diarsip di `data_review.arsip_alur_asesi` (dipulihkan oleh `down()`).
- CMS Template TPD (`/sertifikat/skema-sertifikasi/{skema}/mapa/tpd`): skenario & waktu IA.02, penyusun/validator IA.02, daftar pertanyaan IA.03 (kolom `mapa_templates.instrumen`).

### FR.IA.06A / 06B / 06C (`ia-06a`, `ia-06b`, `ia-06c`): semua metode
- Soal esai, kunci jawaban, waktu & penyusun/validator IA.06B diisi admin di CMS: **Template Umum** (`/sertifikat/skema-sertifikasi/{skema}/mapa/umum`), satu kali per skema untuk semua metode.
- IA.06A (asesor siapkan → asesi jawab): `PATCH .../ia-06a/persiapan` `{ persiapan: { tuk, tanggal, waktu } }` → `POST .../ia-06a/buka` (soal + kunci dibekukan di data_review; email ke asesi) → asesi `PATCH .../ia-06a` `{ isian: { jawaban: [teks] } }` → `submit` (tanpa ttd, semua jawaban wajib) → `selesai` (email ke asesor). **Kunci jawaban tidak pernah ada di payload IA.06A.**
- IA.06B (hanya asesor; asesi 403 "Dokumen ini hanya untuk asesor."): soal + kunci (mengikuti IA.06A), header, penyusun/validator. Tanpa isian (PATCH ditolak); `POST .../ia-06b/submit` langsung dari `belum_diisi` → `selesai`, isi + salinan ttd penyusun dibekukan (tanggal = tanggal diselesaikan).
- IA.06C (pola AK: asesor isi + ttd → asesi setujui): terbuka setelah IA.06A `selesai` ("Menunggu asesi mengirim jawaban FR.IA.06A."). PATCH `{ isian: { pencapaian: [ya|tidak], umpan_balik } }`; payload `jawaban_asesi: [{pertanyaan, jawaban}]` tanpa kunci. Kirim: semua pencapaian & umpan balik wajib.

### Penutup: FR.AK.02 / 03 / 05 / 06 (`ak-02`, `ak-03`, `ak-05`, `ak-06`)
- **AK.02** Rekaman Asesmen (pola AK: asesor isi + ttd → asesi setujui). Terbuka setelah **semua dokumen IA selesai**. PATCH `{ isian: { tuk, tanggal_mulai, tanggal_selesai, bukti: { "u{unitId}": [observasi|portofolio|pihak_ketiga|wawancara|lisan|tertulis|proyek_kerja|lainnya] }, rekomendasi: kompeten|belum_kompeten, tindak_lanjut, komentar } }`.
  - Isian awal: bukti per unit dari dokumen IA metode; payload `indikasi` = hasil "belum tercapai" di IA.01/IA.03/IA.06C.
  - Kirim: TUK, tanggal (selesai ≥ mulai), setiap unit ≥ 1 bukti, rekomendasi; BK → tindak lanjut wajib; K padahal ada indikasi → komentar (alasan) wajib.
- **AK.03** Umpan Balik — diisi **asesi** setelah AK.02 selesai, tanpa ttd: `{ isian: { komponen: [{hasil: ya|tidak, catatan}] x9, catatan_lainnya } }` → submit → selesai. Asesor membaca setelah dikirim.
- **AK.05** Laporan & **AK.06** Tinjauan Proses — **hanya asesor** (asesi 403), setelah AK.02 selesai; ttd asesor → submit → selesai (tanpa persetujuan asesi). AK.05: rekomendasi mengikuti AK.02 (read-only), keterangan wajib bila BK. AK.06: prinsip per aspek ("fleksibel" tidak berlaku untuk keputusan & umpan balik), dimensi (wajib, isian awal = instrumen IA), nama peninjau.
- **Status akhir penilaian**: begitu semua dokumen asesmen selesai **kecuali dokumen pasca-keputusan asesi** (`JenisDokumenMuk::PASCA_KEPUTUSAN`: AK.03 Umpan Balik, AK.04 Banding) → `selesai_kompeten` / `selesai_belum_kompeten` sesuai AK.02, `selesai_at` terisi, riwayat `penilaian_selesai`, jadwal & pendaftaran ikut selesai. Setelah itu semua dokumen terkunci, kecuali AK.03/AK.04 yang tetap dapat diisi asesi.
  - Penilaian lama yang tertahan (mis. menunggu AK.03 dengan aturan lama): `php artisan muk:sinkron-penyelesaian` (`--dry-run` untuk melihat dulu). Aman dijalankan berulang.

### FR.IA.08 / 09 / 10 (`ia-08`, `ia-09`, `ia-10`): metode VP
- Terbuka setelah AK.07 & AK.01 selesai; asesi melihat setelah asesor mengirim.
- **IA.08** (asesor isi + ttd → asesi setujui): `{ isian: { tuk, tanggal, portofolio: [{bukti, relevan_id, valid, asli, terkini, memadai}], klarifikasi: ["u{unitId}"], materi: ["k{kukId}"], bukti_tambahan, rekomendasi: kompeten|lanjutan, lanjutan: { jenis: observasi|kegiatan_terstruktur, unit: ["u{unitId}"], kuk: ["k{kukId}"] } } }`. Isian awal portofolio = lampiran bukti relevan APL.01 yang berisi berkas/link (`relevan_id` menunjuk barisnya; id milik penilaian lain dibuang); payload `lampiran` (berkas/link untuk dibuka asesor) & `kuk`. `materi` = KUK yang tampil di tabel klarifikasi (Materi/Substansi Wawancara): awal dari Template VP (`instrumen.FR.IA.08.materi`, id KUK), dapat ditambah/hapus asesor; kunci di luar skema dibuang. Kirim: ≥ 1 bukti, setiap bukti 4 aturan terisi, rekomendasi (lanjutan → `jenis` & ≥ 1 unit wajib; KUK opsional, kosong = seluruh KUK unit; KUK di luar unit terpilih dibuang).
  - **Asesmen lanjutan (BNSP):** saat IA.08 **selesai** (disetujui asesi) dengan rekomendasi lanjutan, dokumen dibuat otomatis: `observasi` → FR.IA.01/02/03 (TPD), `kegiatan_terstruktur` → FR.IA.04A/04B (KT). Metode utama tetap VP. Dokumen lanjutan hanya memuat unit (IA.01: juga KUK) dalam lingkup; template IA.02/03 dari Template TPD, IA.04A/B dari Template KT. AK.02: indikasi FR.IA.08 & bukti observasi/proyek hanya pada unit lingkup. Penilaian selesai setelah dokumen lanjutan juga selesai.
- **IA.09** (asesor isi + ttd → asesi setujui): `{ isian: { tuk, tanggal, bukti: [teks], jawaban: [{kesimpulan, pencapaian}], tambahan: [{teks, kesimpulan, pencapaian}] } }`. Pertanyaan dari Template VP (CMS, `instrumen.FR.IA.09`) disalin saat pertama disimpan (tidak bisa dikirim klien); bukti awal = lampiran bukti relevan APL.01 (berisi berkas/link). Pertanyaan tambahan di FE berisi awal "Sesuai dengan bukti No. … Yang anda ajukan, ceritakan pengalaman anda …". Penyusun/validator `penyusun_ia09` dibekukan + salinan ttd + tanggal kirim. Kirim: setiap pertanyaan berkesimpulan & berpencapaian.
- **IA.10** (asesor isi + ttd → Selesaikan, tanpa persetujuan asesi): `{ isian: { tuk, tanggal, pengawas: {nama, tempat_kerja, alamat, telepon}, jawaban: [ya|tidak] x6, hubungan, lama_bekerja, kedekatan, pengalaman, keyakinan, kebutuhan_pelatihan, komentar } }`. Isian awal tempat kerja/alamat/telepon dari APL.01. Kirim: nama pengawas, 6 jawaban, keyakinan.
- CMS Template VP: daftar bukti portofolio IA.08 (→ APL.01 lampiran bukti relevan), materi/substansi wawancara IA.08 (dropdown KUK per unit/elemen), pertanyaan wawancara IA.09 & penyusun/validator IA.09.

### FR.IA.04A / 04B (`ia-04a`, `ia-04b`): metode KT
- Terbuka setelah AK.07 & AK.01 selesai; asesi melihat setelah asesor mengirim.
- **IA.04A** (asesor isi + ttd → asesi setujui): `{ isian: { tuk, umpan_balik, supervisor } }`. Payload `instrumen: {hal_disiapkan, hal_didemonstrasikan, waktu_pengerjaan, waktu_tanya_jawab, total_waktu}` (waktu opsional) dari Template KT (CMS, `instrumen.FR.IA.04A`), tidak bisa diubah klien; `unit_kompetensi`; `penyusun` (`penyusun_ia04a`). Supervisor hanya nama (opsional), umpan balik opsional. Kirim: TUK wajib; ditolak (`instrumen`) bila instruksi CMS kosong. Instruksi, penyusun + salinan ttd + tanggal kirim dibekukan.
- **IA.04B** (asesor isi + ttd → asesi setujui): `{ isian: { tuk, tanggal, jawaban: [{tanggapan, pencapaian, kesesuaian}], tambahan: [{lingkup, pertanyaan, kesesuaian, tanggapan, pencapaian}] (maks 10), rekomendasi_teks, rekomendasi: kompeten|belum_kompeten } }`. `isian.aspek` (lingkup/pertanyaan/kesesuaian) dari Template KT disalin saat pertama disimpan dan tidak bisa dikirim klien; `jawaban` sejajar `aspek`; `jawaban.*.kesesuaian` awal dari template dan dapat diubah asesor. Tanggapan asesi dicatat asesor. Kirim: TUK, tanggal, ≥ 1 aspek/tambahan, setiap baris Ya/Tidak, rekomendasi; `kompeten` ditolak bila ada aspek "Tidak". Hasil "Tidak"/Belum Kompeten tampil sebagai indikasi di AK.02.
- CMS Template KT: instruksi IA.04A, daftar aspek IA.04B, penyusun/validator IA.04A & IA.04B.

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

Semua notifikasi berjalan di queue (`queue:work` wajib aktif). Penerima **asesi** juga mendapat salinan
di tabel `notifications` (channel database) yang tampil di halaman Profil/Notifikasi lspp-fe (`GET /api/notifikasi`).
Email hanya dikirim saat giliran berpindah ke pihak lain (bukan saat simpan draf / tanda tangan).

| Kejadian | Penerima | Isi |
|---|---|---|
| Jadwal ditentukan | asesi (+database) & asesor | Tombol ke `MUK_URL/dashboard/penilaian/{id}` |
| Pengingat H-3 & H-1 (`asesmen:kirim-pengingat`, 07:00 WIB) | asesi (+database) | Tanggal, jam, TUK, asesor |
| APL.01 dikirim | admin & super-admin (email + dashboard) | Link ke `/review-apl01/{id}` |
| APL.02 dikirim (APL.01 sudah diterima) / APL.01 diterima saat APL.02 menunggu | asesor | Link ke MUK |
| APL.01/APL.02 diterima / dikembalikan | asesi (+database) | Hasil + catatan + link dokumen |
| Dokumen diisi asesor menunggu persetujuan (AK.07, AK.01, IA.01–04B, IA.06C, IA.08, IA.09, AK.02) | asesi (+database) | Link langsung `/dashboard/penilaian/{id}/dokumen/{slug}` |
| IA.06A dibuka untuk dijawab | asesi (+database) | Link dokumen |
| Jawaban IA.06A dikirim | asesor | Link dokumen |
| Asesi menyetujui dokumen asesor | asesor | **Nonaktif default** (asesmen tatap muka). Aktifkan: `MUK_EMAIL_ASESOR_DISETUJUI=true` |
| Hasil asesmen (status pendaftaran `asesmen_selesai`) | asesi (+database) | Kompeten / Belum Kompeten |

Tidak mengirim email: MAPA.01/02, IA.06B, IA.10, AK.03, AK.05, AK.06.
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

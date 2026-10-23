# Checklist Tes Manual MUK (APL.01 → APL.02 → Metode)

Persiapan: 1 asesi (password sudah dibuat di lspp-fe, email terverifikasi), 1 asesor aktif, 1 admin CMS, skema dengan unit/elemen/KUK/bukti persyaratan. Jalankan `php artisan queue:listen` agar email terkirim (atau set `MAIL_MAILER=log`).

## A. Jadwal & login
- [ ] CMS: jadwalkan pendaftaran `paid` → email asesi & asesor memuat tombol ke `/penilaian/{id}`
- [ ] Buka link di incognito → diarahkan ke login MUK → setelah login kembali ke penilaian tsb
- [ ] Login asesi dengan password salah 6x → pesan "Terlalu banyak percobaan"
- [ ] Token lspp-fe tidak bisa dipakai di MUK (403); asesor tidak bisa login di lspp-fe

## B. FR.APL.01 (asesi)
- [ ] Data prefill dari profil; Ubah → isi → Simpan → "Tersimpan"
- [ ] Unggah bukti persyaratan (PDF/JPG/PNG ≤ 5 MB); berkas lain ditolak; klik nama berkas → terbuka
- [ ] Tanda tangan di kotak Pemohon; ubah isian setelahnya → tanda tangan terhapus
- [ ] Kirim → read-only; FR.APL.02 terbuka; admin menerima email "FR.APL.01 Menunggu Tinjauan Anda"

## C. Review APL.01 (admin, CMS → Review FR.APL.01)
- [ ] Tidak diterima + catatan → asesi menerima email "Perlu Diperbaiki", melihat pesan merah, revisi, kirim ulang
- [ ] Diterima + tanda tangan → asesi melihat verifikasi, "Tidak diterima" dicoret, tanda tangan admin

## D. FR.APL.02 (asesi)
- [ ] K/BK per elemen; bukti = link dan/atau berkas; ikon hapus berkas; X hapus baris
- [ ] Elemen K tanpa bukti → Kirim nonaktif / ditandai merah
- [ ] Kirim → asesor menerima email (hanya bila APL.01 sudah diterima; bila belum, email terkirim saat admin menerima APL.01)

## E. Review APL.02 & metode (asesor)
- [ ] Sebelum APL.01 diterima: pesan "Menunggu FR.APL.01 diterima admin LSP"
- [ ] Tidak dapat dilanjutkan + catatan → asesi menerima email & merevisi
- [ ] Dapat dilanjutkan + tanda tangan → "Tentukan Metode →"; asesi menerima email
- [ ] Tentukan Metode: pilih → Tetapkan → status "Berlangsung", badge metode di sidebar; masih bisa diubah

## E2. FR.MAPA.01 & FR.MAPA.02 (asesor, setelah metode ditetapkan)
- [ ] CMS: Skema → Template VP/KT/TPD → isi rencana §2 semua unit, konfirmasi, penyusun MAPA.01/02 + ttd
- [ ] MAPA.01: isian awal (tujuan dari APL.01, LSP, manajer, standar, 3.4 daftar unit) → Ubah → lengkapi §1 & §3 → Simpan → Selesaikan
- [ ] Hubungan dicentang tanpa emotikon / §3 belum dipilih → ditandai merah saat Selesaikan
- [ ] Template rencana belum lengkap → peringatan kuning, Selesaikan nonaktif
- [ ] MAPA.02: centang potensi → Simpan → Selesaikan; penyusun/validator & ttd tampil
- [ ] Setelah keduanya selesai: dokumen AK/IA terbuka; ubah template di CMS → MAPA tetap tampil versi lama
- [ ] Login asesi → MAPA: "Dokumen ini diisi oleh asesor."

## E3. FR.AK.07 & FR.AK.01 (asesor isi → asesi setujui)
- [ ] Asesi membuka AK.07 sebelum dikirim → "Dokumen sedang disiapkan asesor."
- [ ] Asesor AK.07: isian awal (tanggal, potensi dari MAPA.01, acuan/metode/instrumen) → Ubah → pilih TUK, Ya/Tidak per baris, keterangan → Simpan
- [ ] Kirim tanpa lengkap → baris ditandai merah; tanda tangan asesor → Kirim ke Asesi → status "Menunggu persetujuan asesi"
- [ ] Tarik Kembali → draft, bisa diubah; ubah isi → ttd asesor terhapus, tanda tangani ulang → kirim lagi
- [ ] Asesi (email masuk): baca → klik kotak Tanda Tangan Asesi → Setujui → selesai; asesor menerima email
- [ ] AK.01: TUK terisi dari AK.07, bukti dari rencana MAPA.01, hari/tanggal/waktu/TUK dari jadwal → alur sama seperti AK.07
- [ ] Dokumen IA terkunci ("Menunggu FR.AK.07 dan FR.AK.01 ...") sampai kedua AK disetujui asesi, lalu terbuka

## E4. FR.IA.01 / IA.02 / IA.03 (metode TPD)
- [ ] CMS: Skema → Template TPD → isi skenario (tombol "Isi contoh skenario") & waktu IA.02, penyusun/validator IA.02 + ttd, pertanyaan IA.03 → Simpan
- [ ] IA.01: unit/elemen/KUK tampil; Ya/Tidak per elemen, umpan balik, rekomendasi; "Kompeten" saat ada elemen Tidak → ditolak; ttd → Kirim → asesi Setujui
- [ ] IA.02: skenario, waktu & penyusun dari CMS (tidak bisa diubah); ttd → Kirim → asesi Setujui; ubah template setelahnya → dokumen tetap versi lama
- [ ] IA.03: asesor Ubah → pertanyaan dari CMS + Tambah Pertanyaan; catat tanggapan asesi + Ya/Tidak + umpan balik → ttd → Kirim ke Asesi → asesi Setujui; asesi tidak bisa membuka sebelum dikirim
- [ ] Asesi membuka IA sebelum dikirim → "Dokumen sedang disiapkan asesor."

## E5. FR.IA.06A / 06B / 06C (semua metode)
- [ ] CMS: Skema → **Template Umum (IA.06)** → waktu, Tambah Soal (pertanyaan + kunci), penyusun/validator IA.06B + ttd → Simpan
- [ ] IA.06A asesor: TUK/tanggal/waktu → Buka untuk Asesi; asesi (sebelum dibuka → "Dokumen sedang disiapkan asesor.") tulis jawaban → Kirim
- [ ] IA.06B: asesi → "Dokumen ini hanya untuk asesor."; asesor melihat kunci + penyusun → Selesaikan
- [ ] IA.06C: sebelum IA.06A dikirim → terkunci; asesor nilai Ya/Tidak + umpan balik → ttd → Kirim ke Asesi → asesi Setujui
- [ ] Cek di DevTools (Network) asesi: respons IA.06A/IA.06C tidak memuat teks kunci jawaban

## E6. Penutup: FR.AK.02 / 03 / 05 / 06 & status akhir
- [ ] AK.02 terkunci sampai semua IA selesai; setelah itu bukti per unit terisi otomatis, kotak kuning "hasil belum tercapai" bila ada
- [ ] Pilih Kompeten padahal ada indikasi → wajib komentar; Belum Kompeten → wajib tindak lanjut; ttd → Kirim ke Asesi → asesi Setujui
- [ ] AK.03 (asesi): Ya/Tidak 9 komponen + catatan → Kirim; asesor dapat membaca setelah dikirim
- [ ] AK.05 & AK.06: asesi → "Dokumen ini hanya untuk asesor."; asesor isi → ttd → Selesaikan
- [ ] Setelah AK.02, AK.05 & AK.06 selesai (AK.03 asesi belum diisi pun) → status penilaian "Kompeten"/"Belum kompeten"; dokumen asesor tidak bisa diubah, AK.03 masih bisa diisi & dikirim asesi

## E7. FR.IA.08 / 09 / 10 (metode VP)
- [ ] CMS: Skema → Template VP → Bukti Portofolio IA.08 (Tambah) + Materi/Substansi Wawancara IA.08 (Tambah KUK per unit, dropdown per elemen, hapus x) + pertanyaan IA.09 (Tambah) + penyusun/validator IA.09 + ttd → Simpan
- [ ] APL.01 (asesi, Ubah): bagian "3. Lampiran Bukti yang relevan" berisi butir dari CMS (tanpa tombol hapus); Unggah Dokumen / Sisipkan Link; Tambah Bukti → nama → Simpan Bukti → unggah/link; hapus baris tambahan; ttd terhapus bila lampiran berubah
- [ ] IA.08: bukti portofolio terisi dari lampiran bukti relevan APL.01 (hanya yang berisi berkas/link); klik nama berkas/Buka link untuk review; Ya/Tidak 4 aturan bukti; tabel klarifikasi hanya menampilkan KUK dari CMS, asesor tambah (dropdown) / hapus (x) materi; centang unit untuk klarifikasi; rekomendasi; ttd → Kirim ke Asesi → asesi Setujui
- [ ] IA.08 lanjutan: pilih "Belum memenuhi" → Observasi Langsung / Kegiatan Terstruktur, centang unit, tambah KUK (dropdown per elemen, hapus x) → Kirim → asesi Setujui → dokumen IA.01–03 (atau IA.04A/B) muncul hanya berisi unit/KUK terpilih; IA.02 memakai skenario Template TPD; AK.02 menampilkan indikasi FR.IA.08
- [ ] IA.09: bukti dari lampiran relevan APL.01, pertanyaan dari CMS + Tambah Pertanyaan (berisi template "Sesuai dengan bukti No. …"); kesimpulan + Ya/Tidak; ttd → Kirim ke Asesi → asesi Setujui; penyusun & ttd tampil
- [ ] IA.10: tempat kerja/alamat/telepon dari APL.01; nama pengawas, 6 pertanyaan, uraian; ttd → Selesaikan
- [ ] AK.02 (VP): bukti per unit terisi Portofolio, Wawancara, Pihak Ketiga, Tertulis

## E8. FR.IA.04A / 04B (metode KT)
- [ ] CMS: Skema → Template KT → isi instruksi IA.04A (2 kolom + waktu poin 1 & 2 + total waktu), aspek IA.04B (Tambah Aspek), penyusun/validator IA.04A & IA.04B + ttd → Simpan
- [ ] IA.04A: TUK terisi dari AK.01; instruksi & penyusun dari CMS (tidak bisa diubah); umpan balik + nama supervisor; ttd → Kirim ke Asesi → asesi Setujui; tanggal ttd penyusun = tanggal kirim
- [ ] IA.04A tanpa instruksi CMS: muncul peringatan dan Kirim ditolak
- [ ] IA.04B: aspek dari CMS + Tambah Pertanyaan; asesor isi tanggapan + Ya/Tidak; pilih Kompeten saat ada "Tidak" → ditolak; Belum Kompeten → ttd → Kirim → asesi Setujui
- [ ] AK.02 (KT): bukti per unit terisi Proyek Kerja; indikasi IA.04B tampil bila ada aspek belum tercapai

## F. Keamanan cepat
- [ ] Ganti `{id}` di URL ke penilaian orang lain → "Penilaian tidak ditemukan"
- [ ] Buka URL berkas/tanda tangan API tanpa token → 401
- [ ] Nonaktifkan asesor di CMS saat masih login → request berikutnya kembali ke login

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

## F. Keamanan cepat
- [ ] Ganti `{id}` di URL ke penilaian orang lain → "Penilaian tidak ditemukan"
- [ ] Buka URL berkas/tanda tangan API tanpa token → 401
- [ ] Nonaktifkan asesor di CMS saat masih login → request berikutnya kembali ke login

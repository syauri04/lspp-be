# Pembayaran Manual (Transfer Bank)

Opsi pembayaran di samping DOKU: asesi transfer ke rekening LSP, mengunggah bukti, admin memverifikasi di CMS.
Setelah disetujui, tagihan lunas lewat jalur yang sama dengan DOKU (`KonfirmasiPembayaran::lunas`), sehingga
penjadwalan asesmen (MUK), notifikasi "Pembayaran berhasil", dan timeline profil berjalan tanpa perubahan.

## Alur

1. Admin menyetujui dokumen pendaftaran → tagihan (`pembayarans`, status `pending`) dibuat (tidak berubah).
2. Asesi membuka checkout (`/checkout/{kode}` di lspp-fe) → memilih **Bayar Online (DOKU)** atau **Transfer Manual**.
3. Transfer manual: asesi memilih rekening, transfer, lalu mengunggah bukti + data pengirim.
   - Bukti tersimpan `menunggu`; pendaftaran tetap `awaiting_payment` (label profil: "Menunggu verifikasi pembayaran").
   - Admin & super-admin dikabari (email + database).
   - Selama menunggu: unggah ulang & bayar DOKU ditolak (409) — mencegah pembayaran ganda.
4. Admin di CMS **Verifikasi Pembayaran**:
   - **Setujui** → tagihan `paid` (metode `TRANSFER_MANUAL`), riwayat status, asesi dikabari; muncul di Penjadwalan Asesmen.
   - **Tolak** (alasan wajib) → bukti `ditolak`, asesi dikabari alasan & dapat mengunggah ulang.
5. Bila tagihan lunas lewat DOKU saat bukti masih menunggu, bukti otomatis `dibatalkan`.

## Tabel (migrasi `2026_10_28_100000_create_pembayaran_manual_tables`, hanya menambah tabel)

| Tabel | Isi |
|---|---|
| `pengaturans` | key-value; `pembayaran.doku_aktif`, `pembayaran.manual_aktif` (bawaan: aktif) |
| `rekening_pembayarans` | bank, nomor, atas nama, keterangan, aktif, urutan |
| `bukti_pembayarans` | FK tagihan, snapshot rekening tujuan, pengirim, tanggal, jumlah, berkas (path privat), status `menunggu/disetujui/ditolak/dibatalkan`, catatan & verifikator admin |

## API (lspp-fe, token website asesi; middleware `auth:sanctum` + `asesi.website`)

- `GET /api/checkout/{kode}` → tambahan: `metode {doku, manual}`, `rekening[]` (aktif), `bukti_terakhir`, `unggah {mimes, maks_kb}`.
  Transfer manual hanya ditawarkan bila saklarnya aktif **dan** ada rekening aktif.
- `POST /api/checkout/{kode}/bukti-transfer` (multipart, maks 5×/menit): `rekening_pembayaran_id`, `nama_pengirim`,
  `bank_pengirim`, `tanggal_transfer` (≤ hari ini WIB, ≥ tanggal tagihan), `jumlah_transfer`, `catatan?`, `file` (JPG/PNG/PDF ≤ 5 MB).
- `POST /api/checkout/{kode}/pay` (DOKU) → 409 bila DOKU dimatikan atau bukti transfer masih diverifikasi.

## CMS

- **Verifikasi Pembayaran** (`/verifikasi-pembayaran`): daftar per status (badge jumlah menunggu di sidebar), detail
  dengan pratinjau bukti, penanda jumlah tidak sesuai tagihan, riwayat bukti per tagihan, aksi setujui/tolak.
- **Pengaturan Pembayaran** (`/pengaturan-pembayaran`): saklar DOKU / transfer manual (minimal satu aktif; manual butuh
  rekening aktif) dan kelola rekening. Rekening aktif terakhir tidak bisa dinonaktifkan/dihapus selama transfer manual aktif.
- Detail pendaftaran menampilkan metode bayar & daftar bukti transfer.

## Keamanan

- Berkas di disk privat (`storage/app/private/bukti-pembayaran/{kode}/{uuid}.{ext}`), nama acak; hanya admin login
  yang bisa membuka (`no-store`, `nosniff`, CSP sandbox untuk gambar). Tipe berkas divalidasi dari isinya.
- Kepemilikan tagihan dicek di API; token MUK ditolak; rate limit unggah 5×/menit (penghitung terpisah).
- Verifikasi di dalam transaksi + penguncian baris (aman dari klik ganda / dua admin bersamaan).
- Rekening tujuan disalin ke bukti (snapshot) sehingga perubahan/penghapusan rekening tidak mengubah bukti lama.

## Deploy

```
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan queue:restart
```
Lalu di CMS → Pengaturan Pembayaran: tambah rekening LSP. Transfer manual baru muncul di checkout setelah ada rekening aktif.
Test: `php artisan test tests/Feature/PembayaranManualTest.php`.

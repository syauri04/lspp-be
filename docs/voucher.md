# Voucher Pembayaran

Potongan biaya sertifikasi yang dipakai asesi di halaman checkout lspp-fe. Dikelola admin di CMS → **Voucher**.

## Aturan

- Tipe: **nominal** (Rp) atau **persentase** (0,01–100%, opsional *maks. potongan* Rp). Potongan tidak pernah melebihi harga.
- Berlaku antara `mulai_at`–`berakhir_at` (input WIB, disimpan UTC) dan hanya bila **aktif**.
- **Kuota** total (kosong = tanpa batas) dan **maks. per asesi** (bawaan 1x, kosong = tanpa batas).
  Yang dihitung hanya pemakaian yang masih terpasang pada tagihan *pending/lunas* — tagihan kedaluwarsa
  atau voucher yang dilepas otomatis mengembalikan kuota.
- Satu tagihan hanya satu voucher; memasang voucher lain mengganti yang lama.
- Voucher **terkunci** (tidak bisa dipasang/dilepas) bila: sesi DOKU sudah dibuat (klik *Bayar Sekarang*),
  bukti transfer manual sedang diverifikasi, atau tagihan tidak lagi pending/sudah lewat batas bayar.
- Potongan 100% → tagihan Rp 0 langsung **lunas** (metode `VOUCHER`), alur MUK/notifikasi berjalan seperti biasa.
- Kode yang sudah dipakai tidak bisa diubah; voucher dihapus (soft delete) → kode tidak bisa dipakai ulang,
  riwayat & potongan tagihan tetap.

## Reset sesi pembayaran DOKU (CMS)

Bila asesi sudah klik *Bayar Sekarang* lalu ingin memakai voucher, admin membuka **Pendaftaran → detail →
Info Pembayaran → Reset Sesi Pembayaran**. Efeknya:

- Kode transaksi (invoice DOKU) diganti baru, link DOKU tersimpan dihapus → voucher bisa dipasang/dilepas
  dan asesi klik *Bayar Sekarang* lagi untuk membuat sesi DOKU baru dengan nominal terbaru.
- Kode lama, URL, nominal, waktu & admin disimpan di `pembayarans.sesi_doku_lama`.
- Link lama di DOKU masih bisa dibayar: webhook tetap mengenali invoice lama dan menyatakan lunas bila
  nominalnya ≥ tagihan; notifikasi expired/pending dari sesi lama diabaikan.
- Hanya untuk tagihan pending yang punya sesi DOKU dan tidak sedang ada bukti transfer menunggu verifikasi.

## Data

| Tabel | Isi |
|---|---|
| `vouchers` | master voucher |
| `voucher_pemakaians` | riwayat per tagihan: voucher, asesi, skema, kode, harga awal, potongan, jumlah akhir, aktif/dilepas |
| `pembayarans` (+kolom) | `jumlah_awal` (harga sebelum potongan), `potongan`, `voucher_id`, `voucher_kode`; `jumlah` = yang harus dibayar |

## API (lspp-fe, token website asesi)

- `GET /api/checkout/{kode}` → tambahan `harga_awal`, `potongan`, `voucher {kode,nama,keterangan}`, `voucher_terkunci` (alasan / null).
- `POST /api/checkout/{kode}/voucher` `{kode}` → pasang/ganti; 422 `errors.kode` bila tidak valid/kuota habis/terkunci.
- `DELETE /api/checkout/{kode}/voucher` → lepas.
- Keduanya dibatasi 10×/menit (anti tebak kode).

## Keamanan

- Perhitungan hanya di server, rupiah bilangan bulat; FE hanya mengirim kode.
- Transaksi + penguncian baris (tagihan → voucher): aman dari klik ganda & kuota balapan.
- Webhook DOKU menolak menandai lunas bila `order.amount` < tagihan (log `critical`, cek manual).

## Deploy

```
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache
```
Test: `php artisan test tests/Feature/VoucherTest.php`

# Dthree Ecommerce

Website e-commerce Dthree dengan Laravel 13, Blade, Bootstrap 5.3.8, dan MySQL. Target hosting PHP 8.4 atau lebih baru. Repositori resmi: https://github.com/akmaldthree/websitedthree.

**Status: internal alpha.** Fondasi katalog dan transaksi tersedia untuk pengujian. Seluruh PRD v1.0 belum selesai dan website belum dinyatakan siap transaksi publik. Lihat [status PRD](docs/STATUS_PRD.md) untuk batas fitur serta pekerjaan berikutnya.

## Yang tersedia

- Storefront Bahasa Indonesia dengan beranda, katalog, filter, detail series, SKU manual, keranjang, checkout tamu dua langkah, dan tautan pesanan bertanda tangan.
- Series tanpa jenis produk (Abaya) atau dengan jenis produk (Sarimbit), arsip varian, upload foto, harga dan berat per SKU.
- Reservasi stok / kuota pre-order, snapshot pesanan, verifikasi transfer privat, COD bersyarat, expiry otomatis, picking / packing / resi, dan rekonsiliasi COD.
- Dashboard Admin, Management, Keuangan, Gudang, dan CS; hak akses per tindakan, lifecycle staf, inventori dengan alasan perubahan, audit, rekening, tarif manual POS/JNE, voucher, halaman kebijakan, import CSV dengan preview, serta laporan penjualan/produk/pelanggan/pengiriman.
- Akun pelanggan, reset password melalui konfigurasi email Laravel, alamat utama, riwayat pesanan, invoice, retur/garansi per item, pemeriksaan barang kembali, dan refund manual yang dapat diaudit.
- Outbox email transaksi dengan deduplikasi, retry terbatas, histori status, serta fallback tindak lanjut manual oleh Customer Service.
- Program mitra reseller, dropshipper, dan grosir dengan persetujuan admin, grup harga privat, minimum pesanan, tier harga per SKU, dan snapshot harga pada pesanan.
- Produk ukuran custom dengan template field dinamis, validasi dan persetujuan ukuran, biaya tetap atau penawaran admin, snapshot checkout, serta milestone produksi sampai siap kirim.
- Bundle SKU dengan komposisi yang dikelola admin, ketersediaan dan berat turunan, reservasi stok komponen atomik, serta snapshot isi bundle pada pesanan.
- Wishlist akun dan ulasan hanya dari item yang benar-benar diterima, hingga tiga foto, antrean moderasi admin, serta label pembelian terverifikasi pada storefront.
- Loyalty berbasis ledger dengan aturan nilai/perolehan/masa berlaku dari admin, redeem atomik saat checkout, pemberian poin idempoten setelah pesanan selesai, pemulihan saat batal, dan penyesuaian proporsional saat refund.
- CMS terjadwal untuk banner, section beranda, menu, FAQ, journal, dan campaign; mendukung gambar, urutan, periode tayang, metadata SEO, dan preview admin untuk konten belum terbit.
- Promosi bertarget kategori, series, atau SKU dengan segmentasi pelanggan/B2B, batas per pengguna, prioritas, stacking terkendali, ledger pemakaian, dan snapshot voucher pada pesanan.
- Akun pelanggan dapat masuk memakai email atau telepon, menyimpan keranjang lintas perangkat, mengklaim pesanan tamu melalui tautan pesanan bertanda tangan, pesan ulang, dan mengatur persetujuan promo per saluran.
- B2B mendukung masa aktif/suspend, diskon dasar grup, tier per SKU, minimum pembelian per kategori/series/SKU, dan identitas pengirim dropship pada dokumen paket.
- Import katalog menerima CSV/XLSX dengan pratinjau atomik, mode buat/upsert beserta ledger stok, dan generator kombinasi jenis produk, warna, serta ukuran.

## Instalasi hosting

Ikuti [panduan hosting](docs/HOSTING.md). Aset Bootstrap disimpan dalam `public/vendor/bootstrap`, sehingga hosting tidak membutuhkan Node.js atau npm build. Dependensi PHP dikunci dalam `composer.lock`.

```bash
composer install
cp .env.example .env
# Isi konfigurasi database MySQL dan APP_URL pada .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan dthree:admin email-admin-anda@example.com
php artisan serve
```

Password admin diminta secara tersembunyi. Tidak ada akun atau password bawaan. Login `/masuk`, dashboard `/admin`. Seeder normal tidak mengisi produk palsu, rekening, atau membuka checkout; pengaturan yang sudah diubah admin tidak ditimpa ketika seeder dijalankan kembali.

## Data demonstrasi lokal

Gunakan database development terpisah, `APP_ENV=local`. Jalankan `php artisan db:seed --class=DemoSeeder` jika memerlukan contoh Raffah/Arcelia. Semua SKU diawali `DEMO-`; ilustrasi bukan foto produk Dthree. Seeder demo tidak membuka checkout atau membuat akun admin. Jangan menjalankannya pada database penjualan.

## Pembaruan

Perubahan diuji, di-commit, dan di-push ke `main`. Pemilik melakukan pembaruan dari terminal hosting:

```bash
bash scripts/deploy.sh
```

Script melakukan `git pull --ff-only`, Composer install, migration, cache, dan restart queue. `.env`, database, serta upload tidak masuk Git. Jangan mengedit kode langsung di hosting. Backup dilakukan oleh hosting/operator sebelum deployment.

## Pengujian

```bash
php artisan test --compact --fail-on-risky
php artisan view:cache
```

CI menjalankan tes pada SQLite dan MySQL 8. Tes lokal memakai database SQLite in-memory; database development tidak dihapus. Simulasi konkurensi MySQL nyata, beban, email eksternal, dan restore backup tetap menjadi pekerjaan UAT staging.

## Referensi

- PRD Dthree Ecommerce v1.0, 21 Oktober 2026, dokumen yang diberikan pemilik.
- [Laravel 13 deployment](https://laravel.com/docs/13.x/deployment).
- [Bootstrap 5.3](https://getbootstrap.com/docs/5.3/getting-started/introduction/).

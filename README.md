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
- Pusat konfigurasi admin mengelola identitas brand, logo/favicon/hero, seluruh copy utama beranda dan footer, SEO default, kontak/media sosial, serta pilihan mode manual/API untuk ongkir, tracking, dan WhatsApp dengan token terenkripsi.
- Galeri produk mendukung banyak foto dan pengaitan warna/SKU; kategori dan series memiliki metadata SEO, canonical, structured data, serta tautan berbagi varian.
- CMS menyediakan media library, editor blok terstruktur yang aman, template halaman, tema serta header/footer global, preview, jadwal tayang, workflow draft–review–approve, riwayat revisi, dan pemulihan versi lama.
- Akun staf dapat mengaktifkan verifikasi dua langkah melalui kode email dari menu Keamanan akun.
- Analytics internal anonim merekam funnel storefront tanpa kata pencarian atau data pribadi, dapat dimatikan admin, dan tampil pada laporan Management.
- Staf pengiriman dapat mengubah ongkir sebelum bukti pembayaran masuk; nilai awal, alasan, total baru, timeline, dan audit tersimpan.
- Daftar pesanan mendukung tindakan massal berizin untuk packing, penerimaan, dan pembatalan dengan hasil per pesanan; pengiriman tetap meminta resi unik per paket.
- Ledger komisi B2B mendukung pencatatan payout, rekening penerima, referensi transfer, bukti privat, dan audit.
- Admin dapat mewajibkan 2FA email bagi seluruh staf serta memantau heartbeat scheduler, queue, notifikasi gagal, database, storage, dan mode debug.

## Instalasi hosting

Untuk domain `asfara.co.id`, gunakan [panduan deploy websiteasfara](docs/DEPLOY_ASFARA_CO_ID.md).
Panduan tersebut memakai repository `https://github.com/akmaldthree/websiteasfara`
dan mencakup instalasi pertama serta pembaruan berikutnya dari branch `main`.

Ikuti [panduan deploy store.dthree.co.id](docs/DEPLOY_STORE_SUBDOMAIN.md). Panduan [domain utama](docs/HOSTING.md) dan [public_html](docs/DEPLOY_PUBLIC_HTML.md) tetap tersedia sebagai referensi. Aset Bootstrap disimpan dalam `public/vendor/bootstrap`, sehingga hosting tidak membutuhkan Node.js atau npm build. Dependensi PHP dikunci dalam `composer.lock`.

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

Gunakan database development terpisah, `APP_ENV=local`. Jalankan `php artisan db:seed --class=DemoSeeder` untuk mengisi contoh lengkap pada seluruh area CMS: kategori, 10 series, 169 varian, galeri, bundling, ukuran custom, harga B2B, stok, pelanggan, tujuh kondisi pesanan, retur, notifikasi, ulasan, pertanyaan produk, newsletter, voucher, tarif, lokasi toko, banner, Shop the Look, journal, lookbook, campaign, menu, dan halaman kebijakan. Semua SKU diawali `DEMO-`; gambar generatif bukan foto produk Dthree. Aset mengikuti rasio slot CMS: hero 2:1, produk 4:5, promo 3:4, editorial 3:2, dan Shop the Look 4:5. Seeder aman dijalankan ulang dan tidak membuat akun admin. Seeder membuka checkout serta memasang rekening contoh, jadi gunakan hanya untuk pratinjau atau staging dan ganti semua data contoh sebelum menerima transaksi nyata.

## Pembaruan

Perubahan diuji, di-commit, dan di-push ke `main`. Pemilik melakukan pembaruan dari terminal hosting:

```bash
bash scripts/deploy.sh
```

Script membuat backup MySQL dan `storage/app`, melakukan `git pull --ff-only`, Composer install, migration, cache, restart queue, serta pemeriksaan konfigurasi produksi. `.env`, database, upload, dan hasil backup tidak masuk Git. Jangan mengedit kode langsung di hosting; salin backup ke penyimpanan eksternal dan uji restore secara berkala.

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

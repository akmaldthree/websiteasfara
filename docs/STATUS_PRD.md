# Status implementasi PRD Dthree v1.0

Baseline: PRD Dthree Ecommerce v1.0 tanggal 21 Oktober 2026. Rilis ini adalah **internal alpha** untuk fondasi dan alur transaksi inti, bukan pemenuhan seluruh kriteria go-live. PHP 8.4+ dan MySQL dikonfirmasi oleh pemilik. Produksi dan stok e-commerce dipisahkan dari aplikasi workspace lain.

## Tersedia pada alpha

| Area | Implementasi | Batas saat ini |
| --- | --- | --- |
| Katalog | Kategori, series, jenis opsional, warna, ukuran, SKU manual unik tanpa beda kapital, harga, berat, arsip | Satu foto per series; warna/ukuran berupa nilai per SKU |
| Storefront | Beranda, pencarian nama/jenis/SKU, filter, sorting, pagination, detail, selector SKU, panduan ukuran | Logo tipografi dan ilustrasi contoh; belum aset brand/foto asli |
| Stok | Saldo/kuota dan reservasi, transaksi DB, lock, conditional update, movement ledger, expiry | Satu gudang; pre-order memakai kuota yang dikelola manual; concurrency staging tetap wajib |
| Checkout | Tamu, alamat Indonesia, pilihan tarif dan metode, review akhir, kalkulasi server, idempotensi, snapshot | Alamat berupa input teks, belum kode wilayah resmi; tidak ada penggabungan cart antarperangkat |
| Pembayaran | Rekening admin, bukti JPG/PNG/PDF privat, setujui/tolak/upload ulang, audit | Verifikasi manual; tidak ada payment gateway atau mutasi bank otomatis |
| COD | Aktif/nonaktif, wilayah tarif, ready stock, min/max subtotal, biaya tetap/persen/cap, konfirmasi CS | Belum blacklist pelanggan dan aturan kelompok/kategori/kurir lanjutan |
| Ongkir | Tarif manual POS/JNE menurut provinsi/kota, berat kg pertama/tambahan, estimasi, resi | Belum adapter tarif dan tracking otomatis; belum override ongkir order |
| Promosi | Satu voucher nominal/persen/subsidi ongkir, periode, minimum, maksimum, kuota | Belum segmentasi kategori/series/grup, stacking, simulasi, approval campaign |
| Akun | Registrasi email, login, reset password, buku alamat, riwayat akun | Belum login telepon, alamat utama, konversi tamu terverifikasi, pesan ulang |
| Admin | Lima peran, permission tindakan, konfigurasi toko, daftar pesanan, inventori, audit | Belum 2FA, lifecycle nonaktif/ubah peran pengguna melalui UI, tindakan massal |
| Konten | Teks beranda dan halaman teks admin | Belum section reorder/schedule, banner upload, menu dinamis, journal, preview konten |
| Import | CSV template, preview, validasi seluruh baris, konfirmasi atomic, audit | Maks. 200 baris; create-only, tanpa XLSX/upsert/generator kombinasi |
| Laporan | Rentang tanggal, transaksi dan nilai dibayar, export CSV tanpa data pribadi | Belum analisis produk/customer/ongkir/promo/margin/cohort dan grafik |
| Layanan | Kasus retur/garansi/komplain, nomor kasus, status, catatan, WhatsApp kontekstual | Belum penentuan kelayakan, bukti kasus, retur item, refund ledger dan restock retur |
| Deployment | Repo GitHub, lockfile, tes, CI SQLite/MySQL, script pull-hosting, panduan | Hosting aktual, domain, SMTP, backup/restore dan load test belum diverifikasi |

## Belum diimplementasikan

1. Notifikasi transaksi email/WhatsApp, outbox retry dan monitoring kegagalan. Reset password memakai email Laravel yang harus dikonfigurasi.
2. Adapter penyedia tarif POS/JNE dan tracking, retry/cache/fallback otomatis. Tarif manual tetap dapat digunakan.
3. Refund parsial/penuh, retur per item, kebijakan garansi dan perhitungan stok sesuai hasil kasus.
4. Fitur pertumbuhan: reseller/dropshipper/grosir, harga kelompok rahasia, minimum/tier pricing, custom size/quotation/produksi, bundling komponen, wishlist, review terverifikasi, loyalty.
5. CMS lanjutan, galeri dan foto per warna, SEO metadata per halaman/produk, structured data, analytics events dan persetujuan pemasaran.
6. Hardening produksi: 2FA staf, audit sensitif dengan redaksi berdasarkan peran, SLA bukti transfer, retensi data, monitoring, simulasi koneksi eksternal, beban serta konkurensi MySQL dan backup restore.
7. Integrasi sumber stok/dashboard produksi dua arah. Tidak ada data aplikasi lain yang disalin ke e-commerce.

## Verifikasi dan penerimaan

Tes otomatis menguji snapshot harga, replay checkout, stok tidak cukup, persetujuan transfer berulang, expiry dan bukti pending, upload ulang, COD tidak layak, batas subsidi/kuota voucher, batas akses role, SKU/kombinasi, import preview, alamat milik pengguna, registrasi yang tidak bisa menaikkan role, perubahan harga setelah review, dan rendering halaman.

Browser diperiksa pada desktop dan lebar ponsel 390px; pemilihan SKU dan tambah keranjang diuji. Tes SQLite bukan bukti uji konkurensi MySQL. CI disiapkan untuk menjalankan suite yang sama pada MySQL; uji paralel dua proses tetap diperlukan sebelum go-live.

## Langkah penerapan berikutnya

Lengkapi data produk/foto/logo asli, kebijakan dan rekening melalui admin. Tentukan penyedia ongkir dan WhatsApp. Jalankan alpha di staging PHP 8.4/MySQL, lanjutkan fitur Wajib yang masih parsial, lalu simulasikan UAT 01–16 pada PRD bersama Admin/Keuangan/Gudang/CS. Checkout publik baru dibuka setelah data, integrasi, keamanan, backup dan rekonsiliasi siap.

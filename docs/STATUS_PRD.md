# Status implementasi PRD Dthree v1.0

Baseline: PRD Dthree Ecommerce v1.0 tanggal 21 Oktober 2026. Rilis ini adalah **internal alpha** untuk fondasi dan alur transaksi inti, bukan pemenuhan seluruh kriteria go-live. PHP 8.4+ dan MySQL dikonfirmasi oleh pemilik. Produksi dan stok e-commerce dipisahkan dari aplikasi workspace lain.

## Tersedia pada alpha

| Area | Implementasi | Batas saat ini |
| --- | --- | --- |
| Katalog | Kategori, series, jenis opsional, warna, ukuran, SKU manual unik tanpa beda kapital, harga, berat, arsip, galeri series, dan foto khusus warna/SKU | Warna/ukuran berupa nilai per SKU; aset produk asli perlu diunggah pemilik |
| Storefront | Beranda, pencarian nama/jenis/SKU, filter, sorting, pagination, detail, selector SKU, panduan ukuran, tautan berbagi varian; logo, favicon, hero, copy beranda/footer, kontak, sosial, SEO kategori/produk/konten, canonical, dan structured data dikelola admin | Aset brand/foto asli perlu diunggah pemilik |
| Stok | Saldo/kuota dan reservasi, transaksi DB, lock, conditional update, movement ledger, expiry | Satu gudang; pre-order memakai kuota yang dikelola manual; concurrency staging tetap wajib |
| Checkout | Tamu, alamat Indonesia, pilihan tarif dan metode, review akhir, kalkulasi server, idempotensi, snapshot, keranjang akun lintas perangkat dan merge setelah login | Alamat berupa input teks, belum kode wilayah resmi |
| Pembayaran | Rekening admin, bukti JPG/PNG/PDF privat, setujui/tolak/upload ulang, refund parsial/penuh dengan referensi dan bukti privat, audit | Verifikasi dan transfer refund tetap manual; tidak ada payment gateway atau mutasi bank otomatis |
| COD | Aktif/nonaktif, wilayah tarif, ready stock, min/max subtotal, biaya tetap/persen/cap, konfirmasi CS, kelompok pelanggan, blacklist nomor, pengecualian SKU/kategori | Belum penilaian risiko otomatis berbasis riwayat transaksi |
| Ongkir | Tarif manual POS/JNE menurut provinsi/kota, berat kg pertama/tambahan, estimasi, resi; override ongkir sebelum pembayaran dengan alasan/audit; mode/provider/URL/token API dapat disiapkan dari admin dengan fallback manual | Belum adapter tarif dan tracking otomatis |
| Promosi | Voucher nominal/persen/subsidi ongkir, periode, minimum, maksimum, kuota, target kategori/series/SKU, segmentasi pelanggan/grup B2B, prioritas, stacking terkendali, batas per pengguna, ledger dan snapshot | Belum simulasi serta approval campaign berjenjang |
| Produk custom | Template ukuran dinamis dengan unit/bantuan/batas, biaya tetap atau quotation admin, persetujuan pelanggan, snapshot order, milestone produksi dan gate sebelum packing | Belum upload referensi desain, revisi ukuran dua arah, dan notifikasi khusus per milestone |
| Bundling | SKU bundle, komposisi dan jumlah per komponen, ketersediaan dari komponen terlangka, berat turunan, reservasi/pengurangan/pembatalan stok komponen, snapshot order | Belum builder bundle di storefront; komposisi ditetapkan admin |
| Engagement | Wishlist akun; ulasan berbintang hanya untuk item diterima, hingga tiga foto, moderasi approve/reject, label pembelian terverifikasi, voting membantu, pertanyaan produk dan jawaban termoderasi | Belum notifikasi otomatis saat pertanyaan dijawab |
| Loyalty | Ledger poin, konfigurasi earn/value/minimum/expiry, saldo akun, redeem checkout, earn setelah selesai, restore pembatalan, adjustment refund proporsional, level member, dan kampanye pengali poin bertanggal | Belum benefit khusus per level dan bonus bertarget produk/segmen |
| B2B | Pengajuan reseller/dropshipper/grosir, dokumen legal privat, peninjauan admin, masa aktif/suspend, grup harga privat, minimum global dan bertarget kategori/series/SKU, tier per SKU, snapshot checkout, identitas pengirim dropship, penanggung jawab sales, konfigurasi termin/limit kredit dan komisi | Termin kredit dan komisi belum memengaruhi penagihan; belum portal pesanan massal |
| Akun | Registrasi, login email/telepon, reset password, buku alamat, riwayat, invoice, klaim pesanan tamu melalui tautan bertanda tangan, pesan ulang, dan persetujuan promo email/WhatsApp | Verifikasi klaim bergantung pada kepemilikan tautan pesanan dan kecocokan email/telepon |
| Admin | Lima peran, permission tindakan, aktif/nonaktif dan perubahan peran staf, konfigurasi toko, daftar pesanan, inventori, audit | Belum 2FA dan tindakan massal |
| Konten | Halaman, banner, section beranda, menu, FAQ, journal, campaign; upload gambar, editor blok aman, urutan, jadwal tayang, metadata SEO, preview admin, riwayat versi, dan restore yang dapat dibatalkan | Menggunakan section/template terkontrol sesuai batas PRD; bukan page builder bebas |
| Import | CSV/XLSX, template, preview, validasi seluruh baris, create/upsert atomik, audit dan ledger perubahan stok, generator kombinasi jenis/warna/ukuran | Maks. 200 baris; XLSX membaca worksheet pertama sesuai kolom template |
| Notifikasi | Outbox email transaksi, histori, deduplikasi event, retry terbatas, status tidak pasti setelah worker terhenti, tindak lanjut manual, serta konfigurasi provider WhatsApp/token terenkripsi dari admin | SMTP harus diuji di staging; adapter WhatsApp otomatis belum tersedia |
| Fulfillment | Status packing/kirim/terima, resi, riwayat pelanggan, picking list, packing slip, label, invoice | Tracking kurir tetap manual |
| Laporan | Omzet bruto/bersih, refund, AOV, pelanggan berulang, produk, ongkir, stok rendah, export CSV, serta funnel analytics internal anonim yang dapat dinonaktifkan admin | Belum margin, cohort, grafik, serta segmentasi promo lanjutan |
| Layanan | Aturan retur/garansi, batas waktu, alasan, pengecualian SKU, bukti privat, item kasus, keputusan CS, pemeriksaan Gudang, restock terpisah, refund ledger | Belum ongkir balik otomatis, loyalty adjustment, dan SLA lanjutan |
| Deployment | Repo GitHub, lockfile, tes, CI SQLite/MySQL, script pull-hosting, panduan | Hosting aktual, domain, SMTP, backup/restore dan load test belum diverifikasi |

## Belum diimplementasikan

1. WhatsApp otomatis dan integrasi penyedia pesan. Email transaksi sudah memakai outbox, tetapi SMTP nyata, reputasi domain, dan alert eksternal harus diuji di staging.
2. Adapter penyedia tarif POS/JNE dan tracking, retry/cache/fallback otomatis. Tarif manual tetap dapat digunakan.
3. Otomasi ongkir retur, approval refund berjenjang, dan rekonsiliasi bank. Adjustment loyalty proporsional saat refund sudah tersedia; transfer refund tetap dilakukan manual.
4. Fitur pertumbuhan lanjutan: benefit loyalty khusus per level dan bonus bertarget, penagihan kredit serta ledger komisi B2B, dan portal pesanan massal.
5. Hardening produksi: 2FA staf, audit sensitif dengan redaksi berdasarkan peran, SLA bukti transfer, retensi data, monitoring, simulasi koneksi eksternal, beban serta konkurensi MySQL dan backup restore.
6. Integrasi sumber stok/dashboard produksi dua arah. Tidak ada data aplikasi lain yang disalin ke e-commerce.

## Verifikasi dan penerimaan

Tes otomatis menguji snapshot harga retail/B2B/custom, validasi ukuran, quotation dan penerimaan pelanggan, gate produksi sebelum packing, ketersediaan/berat/reservasi/commit/release komponen bundle, kerahasiaan tier, minimum grup, perubahan tier setelah review, pengajuan/persetujuan mitra, replay checkout, stok tidak cukup, persetujuan transfer berulang, expiry dan bukti pending, upload ulang, COD tidak layak, batas subsidi/kuota voucher, batas akses role, SKU/kombinasi, import preview, alamat milik pengguna, registrasi yang tidak bisa menaikkan role, perubahan harga setelah review, dan rendering halaman.

Browser diperiksa pada desktop dan lebar ponsel 390px; pemilihan SKU dan tambah keranjang diuji. Tes SQLite bukan bukti uji konkurensi MySQL. CI disiapkan untuk menjalankan suite yang sama pada MySQL; uji paralel dua proses tetap diperlukan sebelum go-live.

## Langkah penerapan berikutnya

Lengkapi data produk/foto/logo asli, kebijakan dan rekening melalui admin. Tentukan penyedia ongkir dan WhatsApp. Jalankan alpha di staging PHP 8.4/MySQL, lanjutkan fitur Wajib yang masih parsial, lalu simulasikan UAT 01–16 pada PRD bersama Admin/Keuangan/Gudang/CS. Checkout publik baru dibuka setelah data, integrasi, keamanan, backup dan rekonsiliasi siap.

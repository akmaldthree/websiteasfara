# Status implementasi PRD Dthree v1.0

Baseline: PRD Dthree Ecommerce v1.0 tanggal 21 Oktober 2026. Rilis ini adalah **internal alpha** untuk fondasi dan alur transaksi inti, bukan pemenuhan seluruh kriteria go-live. PHP 8.4+ dan MySQL dikonfirmasi oleh pemilik. Produksi dan stok e-commerce dipisahkan dari aplikasi workspace lain.

## Tersedia pada alpha

| Area | Implementasi | Batas saat ini |
| --- | --- | --- |
| Katalog | Kategori, series, jenis opsional, warna, ukuran, SKU manual unik tanpa beda kapital, harga, berat, arsip | Satu foto per series; warna/ukuran berupa nilai per SKU |
| Storefront | Beranda, pencarian nama/jenis/SKU, filter, sorting, pagination, detail, selector SKU, panduan ukuran | Logo tipografi dan ilustrasi contoh; belum aset brand/foto asli |
| Stok | Saldo/kuota dan reservasi, transaksi DB, lock, conditional update, movement ledger, expiry | Satu gudang; pre-order memakai kuota yang dikelola manual; concurrency staging tetap wajib |
| Checkout | Tamu, alamat Indonesia, pilihan tarif dan metode, review akhir, kalkulasi server, idempotensi, snapshot | Alamat berupa input teks, belum kode wilayah resmi; tidak ada penggabungan cart antarperangkat |
| Pembayaran | Rekening admin, bukti JPG/PNG/PDF privat, setujui/tolak/upload ulang, refund parsial/penuh dengan referensi dan bukti privat, audit | Verifikasi dan transfer refund tetap manual; tidak ada payment gateway atau mutasi bank otomatis |
| COD | Aktif/nonaktif, wilayah tarif, ready stock, min/max subtotal, biaya tetap/persen/cap, konfirmasi CS | Belum blacklist pelanggan dan aturan kelompok/kategori/kurir lanjutan |
| Ongkir | Tarif manual POS/JNE menurut provinsi/kota, berat kg pertama/tambahan, estimasi, resi | Belum adapter tarif dan tracking otomatis; belum override ongkir order |
| Promosi | Satu voucher nominal/persen/subsidi ongkir, periode, minimum, maksimum, kuota | Belum segmentasi kategori/series/grup, stacking, simulasi, approval campaign |
| Produk custom | Template ukuran dinamis dengan unit/bantuan/batas, biaya tetap atau quotation admin, persetujuan pelanggan, snapshot order, milestone produksi dan gate sebelum packing | Belum upload referensi desain, revisi ukuran dua arah, dan notifikasi khusus per milestone |
| Bundling | SKU bundle, komposisi dan jumlah per komponen, ketersediaan dari komponen terlangka, berat turunan, reservasi/pengurangan/pembatalan stok komponen, snapshot order | Belum builder bundle di storefront; komposisi ditetapkan admin |
| Engagement | Wishlist akun; ulasan berbintang hanya untuk item diterima, hingga tiga foto, moderasi approve/reject, label pembelian terverifikasi | Belum pertanyaan produk dan helpful voting |
| Loyalty | Ledger poin, konfigurasi earn/value/minimum/expiry, saldo akun, redeem checkout, earn setelah selesai, restore pembatalan, adjustment refund proporsional | Belum tier/member level dan kampanye bonus poin |
| B2B | Pengajuan reseller/dropshipper/grosir, peninjauan admin, grup harga privat, minimum pesanan, tier harga per SKU, snapshot checkout | Belum dokumen legal, termin kredit, salesman, komisi, dan portal pesanan massal |
| Akun | Registrasi email, login, reset password, buku alamat dengan alamat utama, riwayat akun, invoice cetak | Belum login telepon, konversi tamu terverifikasi, pesan ulang |
| Admin | Lima peran, permission tindakan, aktif/nonaktif dan perubahan peran staf, konfigurasi toko, daftar pesanan, inventori, audit | Belum 2FA dan tindakan massal |
| Konten | Teks beranda dan halaman teks admin | Belum section reorder/schedule, banner upload, menu dinamis, journal, preview konten |
| Import | CSV template, preview, validasi seluruh baris, konfirmasi atomic, audit | Maks. 200 baris; create-only, tanpa XLSX/upsert/generator kombinasi |
| Notifikasi | Outbox email transaksi, histori, deduplikasi event, retry terbatas, status tidak pasti setelah worker terhenti, dan tindak lanjut manual | SMTP harus dikonfigurasi dan diuji di staging; WhatsApp otomatis belum tersedia |
| Fulfillment | Status packing/kirim/terima, resi, riwayat pelanggan, picking list, packing slip, label, invoice | Tracking kurir tetap manual |
| Laporan | Omzet bruto/bersih, refund, AOV, pelanggan berulang, produk, ongkir, stok rendah, dan export CSV | Belum margin, cohort, grafik, serta segmentasi promo lanjutan |
| Layanan | Aturan retur/garansi, batas waktu, alasan, pengecualian SKU, bukti privat, item kasus, keputusan CS, pemeriksaan Gudang, restock terpisah, refund ledger | Belum ongkir balik otomatis, loyalty adjustment, dan SLA lanjutan |
| Deployment | Repo GitHub, lockfile, tes, CI SQLite/MySQL, script pull-hosting, panduan | Hosting aktual, domain, SMTP, backup/restore dan load test belum diverifikasi |

## Belum diimplementasikan

1. WhatsApp otomatis dan integrasi penyedia pesan. Email transaksi sudah memakai outbox, tetapi SMTP nyata, reputasi domain, dan alert eksternal harus diuji di staging.
2. Adapter penyedia tarif POS/JNE dan tracking, retry/cache/fallback otomatis. Tarif manual tetap dapat digunakan.
3. Otomasi ongkir retur, approval refund berjenjang, adjustment loyalty, dan rekonsiliasi bank. Refund saat ini dicatat setelah Keuangan benar-benar mengirim dana.
4. Fitur pertumbuhan lanjutan: tier/member loyalty, kampanye bonus poin, pertanyaan produk, helpful voting, serta kelanjutan B2B berupa dokumen legal, termin kredit, salesman, komisi, dan portal pesanan massal.
5. CMS lanjutan, galeri dan foto per warna, SEO metadata per halaman/produk, structured data, analytics events dan persetujuan pemasaran.
6. Hardening produksi: 2FA staf, audit sensitif dengan redaksi berdasarkan peran, SLA bukti transfer, retensi data, monitoring, simulasi koneksi eksternal, beban serta konkurensi MySQL dan backup restore.
7. Integrasi sumber stok/dashboard produksi dua arah. Tidak ada data aplikasi lain yang disalin ke e-commerce.

## Verifikasi dan penerimaan

Tes otomatis menguji snapshot harga retail/B2B/custom, validasi ukuran, quotation dan penerimaan pelanggan, gate produksi sebelum packing, ketersediaan/berat/reservasi/commit/release komponen bundle, kerahasiaan tier, minimum grup, perubahan tier setelah review, pengajuan/persetujuan mitra, replay checkout, stok tidak cukup, persetujuan transfer berulang, expiry dan bukti pending, upload ulang, COD tidak layak, batas subsidi/kuota voucher, batas akses role, SKU/kombinasi, import preview, alamat milik pengguna, registrasi yang tidak bisa menaikkan role, perubahan harga setelah review, dan rendering halaman.

Browser diperiksa pada desktop dan lebar ponsel 390px; pemilihan SKU dan tambah keranjang diuji. Tes SQLite bukan bukti uji konkurensi MySQL. CI disiapkan untuk menjalankan suite yang sama pada MySQL; uji paralel dua proses tetap diperlukan sebelum go-live.

## Langkah penerapan berikutnya

Lengkapi data produk/foto/logo asli, kebijakan dan rekening melalui admin. Tentukan penyedia ongkir dan WhatsApp. Jalankan alpha di staging PHP 8.4/MySQL, lanjutkan fitur Wajib yang masih parsial, lalu simulasikan UAT 01–16 pada PRD bersama Admin/Keuangan/Gudang/CS. Checkout publik baru dibuka setelah data, integrasi, keamanan, backup dan rekonsiliasi siap.

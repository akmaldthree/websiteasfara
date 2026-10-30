# Panduan Deploy Dthree di store dthree co id

Panduan ini memasang website e-commerce pada `https://store.dthree.co.id` tanpa mengubah website utama `https://dthree.co.id`. Repository Laravel berada langsung di folder subdomain, sedangkan document root subdomain diarahkan ke folder `public` Laravel.

## 1. Lokasi aplikasi

Gunakan struktur berikut:

```text
/home/dthreeco/store.dthree.co.id/        aplikasi Laravel
/home/dthreeco/store.dthree.co.id/public  document root store.dthree.co.id
```

Jika repository belum berhasil di-clone, pasang SSH deploy key terlebih dahulu, lalu jalankan:

```bash
cd ~/store.dthree.co.id
git clone git@github-dthree:akmaldthree/websitedthree.git .
git branch --show-current
```

Branch harus `main`. Pastikan lokasi absolutnya:

```bash
realpath .
realpath public
```

Gunakan hasil `realpath public` sebagai document root subdomain.

## 2. Buat subdomain di cPanel

1. Buka **cPanel → Domains**.
2. Klik **Create A New Domain**.
3. Isi domain dengan `store.dthree.co.id`.
4. Nonaktifkan **Share document root** jika pilihan tersebut tampil.
5. Isi document root dengan path relatif berikut:

   ```text
   store.dthree.co.id/public
   ```

6. Pastikan hasil akhirnya menunjuk ke:

   ```text
   /home/dthreeco/store.dthree.co.id/public
   ```

7. Klik **Submit**.

Jika cPanel membatasi document root agar selalu berada di bawah `public_html`, hubungi dukungan hosting dan minta `store.dthree.co.id` diarahkan ke hasil perintah `realpath ~/store.dthree.co.id/public`. Jangan arahkan document root ke root repository.

## 3. DNS subdomain

Jika nameserver domain menggunakan Jagoan Hosting, cPanel biasanya membuat record DNS secara otomatis. Periksa melalui **Zone Editor** bahwa record `store` tersedia.

Jika DNS dikelola di tempat lain, buat record:

```text
Type: A
Name: store
Value: IP hosting dthree.co.id
```

Gunakan IP resmi yang terlihat pada panel hosting. Jangan menyalin placeholder sebagai nilai DNS.

## 4. Buat database MySQL

Dari cPanel:

1. Buat database khusus website Dthree.
2. Buat pengguna database dengan password kuat.
3. Hubungkan pengguna ke database dan berikan hak yang dibutuhkan.
4. Catat host, port, nama database, username, dan password.

Gunakan database kosong. Jangan menjalankan `migrate:fresh`, `db:wipe`, atau `migrate:rollback` pada produksi.

## 5. Instal aplikasi

```bash
cd ~/store.dthree.co.id

php -v
composer --version

composer install \
  --no-dev \
  --prefer-dist \
  --no-interaction \
  --optimize-autoloader

cp .env.example .env
nano .env
```

PHP harus versi 8.4 atau lebih baru. Isi `.env` produksi:

```dotenv
APP_NAME=Dthree
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://store.dthree.co.id
APP_LOCALE=id

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=<HOST_DATABASE>
DB_PORT=3306
DB_DATABASE=<NAMA_DATABASE>
DB_USERNAME=<USER_DATABASE>
DB_PASSWORD="<PASSWORD_DATABASE>"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_COOKIE=dthree_store_session
SESSION_DOMAIN=store.dthree.co.id

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
PUBLIC_FILESYSTEM_ROOT=/home/dthreeco/store.dthree.co.id/public/storage

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=<HOST_SMTP>
MAIL_PORT=587
MAIL_USERNAME=<USER_SMTP>
MAIL_PASSWORD="<PASSWORD_SMTP>"
MAIL_FROM_ADDRESS=noreply@dthree.co.id
MAIL_FROM_NAME=Dthree

TRANSACTION_EMAIL_ENABLED=false
```

Jangan mengirim `.env`, password, token, atau private key melalui chat dan jangan commit file tersebut ke Git.

## 6. Inisialisasi pertama

```bash
cd ~/store.dthree.co.id

php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
chmod -R u+rwX storage bootstrap/cache
mkdir -p public/storage
chmod -R u+rwX public/storage
php artisan optimize
```

`PUBLIC_FILESYSTEM_ROOT` menyimpan media publik langsung di `public/storage`. Konfigurasi ini ditujukan untuk shared hosting yang menonaktifkan fungsi PHP `symlink()` dan `exec()`, sehingga `php artisan storage:link` tidak diperlukan.

`APP_KEY` hanya dibuat satu kali. Jangan menggantinya setelah website mulai digunakan.

## 7. Buat akun admin CMS

```bash
php artisan dthree:admin admin@dthree.co.id --name="Admin Dthree"
```

Masukkan password ketika diminta. Setelah domain aktif:

- Login: `https://store.dthree.co.id/masuk`
- Dashboard: `https://store.dthree.co.id/admin`
- Monitoring: `https://store.dthree.co.id/admin/monitoring`

## 8. Atur PHP 8.4 dan SSL

1. Buka **cPanel → MultiPHP Manager**.
2. Pilih `store.dthree.co.id`.
3. Gunakan PHP 8.4 atau lebih baru.
4. Buka **SSL/TLS Status** dan jalankan AutoSSL jika sertifikat belum tersedia.
5. Aktifkan **Force HTTPS Redirect** setelah sertifikat berhasil diterbitkan.

Uji:

```bash
curl -I https://store.dthree.co.id/up
curl -I https://store.dthree.co.id/masuk
```

Endpoint `/up` harus menghasilkan HTTP 200.

## 9. Pasang cron

Cari binary PHP yang digunakan terminal:

```bash
which php
php -v
```

Gunakan path hasil `which php` pada Cron Jobs. Contoh jika hasilnya `/usr/bin/php`:

```cron
* * * * * cd /home/dthreeco/store.dthree.co.id && /usr/bin/php artisan schedule:run >> /home/dthreeco/store.dthree.co.id/storage/logs/scheduler.log 2>&1
```

Untuk shared hosting tanpa process manager, tambahkan queue worker pendek:

```cron
* * * * * cd /home/dthreeco/store.dthree.co.id && /usr/bin/php artisan queue:work --stop-when-empty --max-time=50 --tries=3 >> /home/dthreeco/store.dthree.co.id/storage/logs/queue.log 2>&1
```

Verifikasi:

```bash
cd ~/store.dthree.co.id
php artisan schedule:list
php artisan schedule:run
php artisan system:heartbeat
php artisan system:production-check
```

Kemudian buka **Admin → Monitoring sistem**.

## 10. Uji email sebelum mewajibkan 2FA

Biarkan `TRANSACTION_EMAIL_ENABLED=false` sampai SMTP selesai diuji. Uji fitur lupa password dan 2FA pada satu akun staf. Setelah email diterima dengan benar, ubah:

```dotenv
TRANSACTION_EMAIL_ENABLED=true
```

Lalu jalankan:

```bash
php artisan optimize:clear
php artisan optimize
```

Aktifkan kebijakan wajib 2FA seluruh staf melalui CMS hanya setelah pengiriman email stabil.

## 11. Lengkapi CMS dan lakukan UAT

Sebelum membuka checkout:

1. Upload logo, favicon, hero, foto produk, dan konten asli.
2. Isi kategori, series, SKU, harga, berat, dan stok.
3. Isi rekening dan tarif manual POS/JNE.
4. Terbitkan syarat, privasi, pengiriman, retur, dan garansi.
5. Uji transfer, COD, email, packing, resi, penerimaan, retur, refund, B2B, loyalty, dan payout komisi.
6. Pastikan `APP_DEBUG=false` dan backup tersedia.

Untuk memasang katalog demonstrasi lengkap beserta gambar pada tahap pratinjau, buat backup database lalu jalankan satu kali:

```bash
cd ~/store.dthree.co.id
php artisan optimize:clear
ALLOW_DEMO_SEEDING=true php artisan db:seed --class=DemoSeeder --force
php artisan optimize
```

Seeder aman dijalankan ulang: katalog, produk, varian, galeri, bundling, ukuran custom, harga B2B, pelanggan, contoh pesanan, retur, konten, voucher, dan tarif yang sama akan diperbarui tanpa digandakan. Aset gambar bawaannya sudah mengikuti rasio hero 2:1, produk 4:5, promo 3:4, editorial 3:2, dan Shop the Look 4:5. Nilai `ALLOW_DEMO_SEEDING` hanya diberikan pada perintah tersebut sehingga tidak perlu disimpan di `.env`. Ganti seluruh informasi contoh, rekening demo, kontak, harga, stok, pelanggan, pesanan, serta gambar dari CMS sebelum toko menerima transaksi nyata.

## 12. Update setelah perubahan GitHub

Script berikut membuat backup database dan `storage/app` secara otomatis sebelum deployment:

```bash
cd ~/store.dthree.co.id
bash scripts/deploy.sh
```

Karena document root subdomain langsung menunjuk ke folder `public` repository, tidak diperlukan proses salin atau `rsync` aset setelah update.

Jika binary server berbeda:

```bash
PHP_BIN=<PATH_PHP_84> COMPOSER_BIN=<PATH_COMPOSER> bash scripts/deploy.sh
```

Backup memerlukan `mysqldump`, `gzip`, dan `tar`. Hasil privat disimpan di `storage/app/backups` selama 14 hari dan statusnya tampil pada **Admin → Monitoring sistem**. Tetap salin backup ke penyimpanan eksternal terenkripsi.

## 13. Pemeriksaan akhir

```bash
cd ~/store.dthree.co.id

php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan system:production-check
git status --short
git log -1 --oneline
curl -I https://store.dthree.co.id/up
```

Pastikan seluruh migration berstatus `Ran`, Git working tree kosong, SSL aktif, cron memperbarui heartbeat, dan file `.env` tidak dapat diakses melalui web.

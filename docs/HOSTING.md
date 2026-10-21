# Instalasi dan pembaruan hosting Dthree

Gunakan PHP 8.4+, Composer 2, MySQL 8/InnoDB, Git, HTTPS, akses terminal, serta cron. Aktifkan ekstensi PHP sesuai Laravel: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, pdo, pdo_mysql, session, tokenizer, xml. Web server harus mengarah ke folder `public`, bukan root proyek.

## Instalasi pertama

1. Buat database dan pengguna database khusus Dthree dari panel hosting. Jangan menggunakan database aplikasi lain.
2. Beri hosting akses baca ke repositori privat melalui SSH deploy key atau autentikasi GitHub hosting. Jangan menaruh token di URL remote, kode, atau file yang di-commit.
3. Clone di direktori aplikasi di luar web root umum. Contoh:

```bash
git clone git@github.com:akmaldthree/websitedthree.git websitedthree
cd websitedthree
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
cp .env.example .env
```

4. Edit `.env` di hosting. Contoh berikut adalah placeholder, bukan kredensial aktif:

```dotenv
APP_NAME=Dthree
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.id
APP_LOCALE=id
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database_hosting
DB_USERNAME=nama_user_database
DB_PASSWORD="isi_password_database"
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_COOKIE=dthree_session
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=smtp
MAIL_HOST=host-smtp-anda
MAIL_PORT=587
MAIL_USERNAME=akun-smtp-anda
MAIL_PASSWORD="isi_password_smtp"
MAIL_FROM_ADDRESS=alamat-email-toko-anda
MAIL_FROM_NAME=Dthree
```

Sesuaikan SMTP dengan penyedia email. Jangan mengandalkan mailer `log` untuk mengirim reset password. Jika memakai proxy/CDN, konfigurasi trusted proxy sesuai host; jangan mempercayai semua proxy secara otomatis.

5. Siapkan aplikasi:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan dthree:admin email-admin-anda@example.com
php artisan optimize
```

Jalankan `key:generate` hanya pada instalasi pertama. Mengganti `APP_KEY` akan membatalkan tautan pesanan bertanda tangan dan sesi yang ada. Seeder tidak membuat akun default. Password admin tidak ditulis ke Git atau output terminal.

6. Atur document root domain ke `/path/websitedthree/public`. Pastikan PHP web server dan PHP terminal sama-sama 8.4+. Folder `storage` dan `bootstrap/cache` harus dapat ditulis oleh pengguna aplikasi; gunakan ownership yang benar, bukan izin `777`.
7. Tambahkan cron setiap menit (sesuaikan lokasi PHP dan proyek):

```cron
* * * * * cd /path/websitedthree && /usr/bin/php artisan schedule:run >> /path/websitedthree/storage/logs/scheduler.log 2>&1
```

Perintah `orders:expire` melepaskan reservasi transfer/COD yang melewati batas. Bukti berstatus menunggu verifikasi menahan reservasi sampai keputusan Keuangan. Pantau umur antrean tersebut setiap hari.

8. Jika memakai job queue, jalankan worker melalui Supervisor/fitur daemon hosting: `php artisan queue:work --tries=3 --timeout=90`. Implementasi alpha belum memiliki notifikasi transaksi otomatis; konfigurasi worker disiapkan untuk pengembangan lanjut. Reset password Laravel saat ini dikirim melalui mailer secara langsung.
9. Buka `/masuk`, lalu lengkapi kategori, series, SKU, stok, foto asli, rekening, tarif per wilayah, WhatsApp, dan kebijakan. Pastikan halaman `syarat-ketentuan` dan `privasi` diterbitkan. Uji di staging sebelum membuka checkout. Tarif `*` berlaku untuk seluruh wilayah; gunakan hanya bila tarif tersebut benar-benar telah ditetapkan oleh operasional.

## Memperbarui setelah push GitHub

Backup database dan seluruh `storage/app` terlebih dahulu, terutama bukti pembayaran privat. Dari root aplikasi:

```bash
bash scripts/deploy.sh
```

Script memeriksa branch `main` dan perubahan lokal, lalu mengambil commit terbaru dengan fast-forward. Script tidak mengubah `.env`, membuat ulang APP_KEY, menghapus database, menjalankan demo seeder, atau menimpa upload.

Untuk hosting dengan nama binary berbeda:

```bash
PHP_BIN=/path/php84 COMPOSER_BIN=/path/composer bash scripts/deploy.sh
```

Jika Composer berupa PHAR, gunakan launcher executable pada hosting yang menjalankan PHAR dengan PHP 8.4. Pastikan Composer menggunakan versi PHP yang sama dengan aplikasi.

Alternatif manual:

```bash
php artisan down --retry=60
git pull --ff-only origin main
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

Jika langkah mana pun gagal, berhenti dan periksa `storage/logs/laravel.log`. Script membiarkan website dalam maintenance untuk mencegah pengunjung menggunakan versi yang belum lengkap. Perbaiki masalah lalu jalankan ulang. Jangan langsung menjalankan `migrate:fresh`, `db:wipe`, `reset --hard`, atau mengganti APP_KEY.

## Pemulihan dan pemeriksaan

Simpan backup database, storage privat/publik, dan `.env` secara terpisah dan terenkripsi. Uji pemulihan pada staging. Untuk rollback, gunakan commit yang telah diketahui baik serta rencana migration yang sesuai; jangan menjalankan `migrate:rollback` tanpa mengecek dampak terhadap data. Setelah update, periksa `/up`, login, katalog, simulasi checkout staging, cron, dan antrean bukti pembayaran.

Koneksi hosting/domain, deploy key, backup/restore, SMTP nyata, serta UAT setiap role belum diverifikasi dalam workspace lokal ini.

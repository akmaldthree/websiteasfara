# Panduan Deploy Dthree dari Terminal public html

Panduan ini ditujukan untuk terminal hosting dengan prompt seperti `[dthreeco@inspire public_html]$`. Aplikasi Laravel disimpan di `/home/dthreeco/apps/websitedthree`. Folder `public_html` hanya boleh memuat isi folder `public` Laravel atau diarahkan langsung ke folder tersebut. Struktur ini mencegah `.env`, source code, bukti pembayaran, dokumen B2B, dan file privat dapat diakses melalui web.

## 1. Periksa server

Dari terminal hosting jalankan:

```bash
pwd
cd ~
pwd
php -v
php -m
composer --version
git --version
which php
which composer
```

PHP web, terminal, dan cron harus menggunakan PHP 8.4 atau lebih baru. Simpan hasil `which php` karena path tersebut dipakai pada cron. Jika versi terminal berbeda dari versi domain, pilih PHP 8.4 melalui panel hosting atau gunakan path binary PHP 8.4 yang diberikan hosting.

## 2. Buat database

Dari panel hosting:

1. Buat database MySQL khusus Dthree.
2. Buat pengguna database dengan password acak yang kuat.
3. Berikan seluruh hak yang diperlukan pengguna tersebut pada database Dthree.
4. Catat nama database, username, password, host, dan port.

Gunakan database kosong. Jangan menjalankan `migrate:fresh`, `db:wipe`, atau `migrate:rollback` pada produksi.

## 3. Clone aplikasi di luar public html

Jangan menjalankan `git clone` langsung di dalam `public_html`. Dari home akun hosting:

```bash
cd ~
mkdir -p apps
cd apps
git clone https://github.com/akmaldthree/websitedthree.git
cd websitedthree
git branch --show-current
```

Branch yang tampil harus `main`. Lokasi aplikasi selanjutnya adalah:

```text
/home/dthreeco/apps/websitedthree
```

Jika repository GitHub berubah menjadi privat, gunakan SSH deploy key read-only sebagaimana dijelaskan dalam `docs/HOSTING.md`.

## 4. Instal dependensi dan buat konfigurasi

Dari root aplikasi:

```bash
cd ~/apps/websitedthree
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
cp .env.example .env
nano .env
```

Isi nilai produksi berikut. Ganti setiap nilai dalam tanda `<...>` dengan data panel hosting:

```dotenv
APP_NAME=Dthree
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://dthree.co.id
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
SESSION_COOKIE=dthree_session
SESSION_DOMAIN=.dthree.co.id

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

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

Simpan dengan `Ctrl+O`, tekan Enter, lalu keluar dengan `Ctrl+X`. Jangan mengirim atau menyimpan isi `.env` di GitHub.

## 5. Inisialisasi Laravel

Masih dari `~/apps/websitedthree`:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
chmod -R u+rwX storage bootstrap/cache
php artisan optimize
```

`APP_KEY` hanya dibuat satu kali. Jangan menjalankan `key:generate` lagi setelah website digunakan.

## 6. Hubungkan domain ke folder public Laravel

Gunakan salah satu pilihan berikut. Pilihan A lebih sederhana dan lebih mudah dirawat.

### Pilihan A mengubah document root domain

Dari panel hosting, ubah document root `dthree.co.id` menjadi:

```text
/home/dthreeco/apps/websitedthree/public
```

Kemudian jalankan:

```bash
cd ~/apps/websitedthree
php artisan storage:link
```

Folder `public_html` lama tidak dipakai oleh domain setelah document root berhasil diubah. Simpan backupnya sampai website baru selesai diverifikasi.

### Pilihan B jika document root wajib public html

Gunakan pilihan ini hanya jika panel tidak mengizinkan perubahan document root. Backup isi `public_html` melalui File Manager hosting, lalu kosongkan file website lama setelah backup dipastikan tersedia.

Salin file publik Laravel:

```bash
cp -a ~/apps/websitedthree/public/. ~/public_html/
```

Buka bootstrap publik:

```bash
nano ~/public_html/index.php
```

Ubah tiga path aplikasi menjadi:

```php
if (file_exists($maintenance = __DIR__.'/../apps/websitedthree/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../apps/websitedthree/vendor/autoload.php';
$app = require_once __DIR__.'/../apps/websitedthree/bootstrap/app.php';
```

Hapus blok maintenance lama yang masih menunjuk ke `__DIR__.'/../storage/framework/maintenance.php'`, sehingga hanya tersisa blok dengan path `apps/websitedthree` di atas.

Simpan file. Lalu buat link upload publik:

```bash
ln -s ~/apps/websitedthree/storage/app/public ~/public_html/storage
```

Jika `public_html/storage` sudah ada, periksa dahulu dengan `ls -la ~/public_html/storage`. Jangan menimpa folder yang berisi file tanpa backup.

Setiap update yang mengubah CSS, JavaScript, favicon, atau aset publik perlu disalin kembali tanpa menimpa `index.php` khusus dan link `storage`:

```bash
rsync -a --exclude=index.php --exclude=storage ~/apps/websitedthree/public/ ~/public_html/
```

## 7. Buat akun admin CMS

Dari root aplikasi:

```bash
cd ~/apps/websitedthree
php artisan dthree:admin admin@dthree.co.id --name="Admin Dthree"
```

Masukkan password ketika diminta. Password tidak tampil di terminal dan harus memiliki minimal 12 karakter, huruf, serta angka.

Setelah domain aktif:

- Login CMS: `https://dthree.co.id/masuk`
- Dashboard CMS: `https://dthree.co.id/admin`
- Monitoring: `https://dthree.co.id/admin/monitoring`

## 8. Pasang cron scheduler dan queue

Buka menu Cron Jobs pada panel hosting. Gunakan path PHP dari hasil `which php`. Contoh berikut memakai `/usr/bin/php`; ganti jika hasil server berbeda.

Scheduler setiap menit:

```cron
* * * * * cd /home/dthreeco/apps/websitedthree && /usr/bin/php artisan schedule:run >> /home/dthreeco/apps/websitedthree/storage/logs/scheduler.log 2>&1
```

Queue untuk shared hosting tanpa process manager:

```cron
* * * * * cd /home/dthreeco/apps/websitedthree && /usr/bin/php artisan queue:work --stop-when-empty --max-time=50 --tries=3 >> /home/dthreeco/apps/websitedthree/storage/logs/queue.log 2>&1
```

Verifikasi manual:

```bash
cd ~/apps/websitedthree
php artisan schedule:list
php artisan schedule:run
php artisan system:heartbeat
```

Setelah beberapa menit, buka menu **Admin → Monitoring sistem**. Database, Scheduler, Queue, dan Storage seharusnya berstatus baik. Mode aplikasi harus menunjukkan debug nonaktif.

## 9. Aktifkan SSL dan email

Aktifkan AutoSSL atau Let's Encrypt untuk:

- `dthree.co.id`
- `www.dthree.co.id`

Arahkan `www` ke `https://dthree.co.id`. Setelah sertifikat aktif, uji:

```bash
curl -I https://dthree.co.id/up
curl -I https://dthree.co.id/masuk
```

`/up` harus menghasilkan status 200. Uji lupa password dan 2FA admin. Setelah email benar-benar diterima, ubah `.env`:

```dotenv
TRANSACTION_EMAIL_ENABLED=true
```

Lalu muat ulang konfigurasi:

```bash
cd ~/apps/websitedthree
php artisan optimize:clear
php artisan optimize
```

Aktifkan kebijakan wajib 2FA seluruh staf hanya setelah SMTP berhasil diuji.

## 10. Isi data sebelum membuka checkout

Masuk ke CMS dan lengkapi:

1. Logo, favicon, hero, kontak, dan media sosial.
2. Kategori, series, SKU, foto, harga, berat, serta stok.
3. Rekening bank dan tarif manual POS/JNE.
4. Halaman syarat, privasi, pengiriman, retur, dan garansi.
5. Pengaturan COD, loyalty, B2B, role, dan email.
6. Satu transaksi uji sampai pembayaran, packing, pengiriman, penerimaan, refund, dan notifikasi.

Jangan menjalankan `DemoSeeder` pada database produksi. Aktifkan **Buka checkout** setelah data dan transaksi uji selesai.

## 11. Update website berikutnya

Setelah perubahan dipush ke GitHub, backup database dan `storage/app`, kemudian:

```bash
cd ~/apps/websitedthree
bash scripts/deploy.sh
```

Jika memakai Pilihan B, sinkronkan aset publik setelah deploy:

```bash
rsync -a --exclude=index.php --exclude=storage ~/apps/websitedthree/public/ ~/public_html/
```

Jika binary hosting tidak bernama `php` atau `composer`:

```bash
PHP_BIN=<PATH_PHP_84> COMPOSER_BIN=<PATH_COMPOSER> bash scripts/deploy.sh
```

## 12. Pemeriksaan akhir

```bash
cd ~/apps/websitedthree
php artisan about
php artisan migrate:status
php artisan schedule:list
git status --short
git log -1 --oneline
curl -I https://dthree.co.id/up
```

Pastikan:

- `APP_ENV=production` dan `APP_DEBUG=false`.
- Seluruh migration berstatus `Ran`.
- Git working tree kosong.
- `.env`, `.git`, `vendor`, source aplikasi, dan `storage/app` tidak dapat diakses melalui web.
- Upload gambar publik dan file privat berhasil.
- Cron memperbarui heartbeat scheduler.
- Backup database, `storage/app`, dan `.env` tersedia di lokasi terpisah.

Jika muncul error 500, lihat log tanpa membagikan rahasia:

```bash
cd ~/apps/websitedthree
tail -n 100 storage/logs/laravel.log
```

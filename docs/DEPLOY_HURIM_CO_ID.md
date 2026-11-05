# Panduan deploy `websiteasfara` ke `hurim.co.id`

Panduan ini digunakan untuk instalasi pertama dari repository
`https://github.com/akmaldthree/websiteasfara` ke domain utama
`https://hurim.co.id`. Aplikasi menggunakan Laravel 13, PHP 8.4+, MySQL,
Composer, queue database, dan scheduler Laravel. Aset frontend sudah tersedia
di repository sehingga server tidak memerlukan Node.js atau npm.

## 1. Persyaratan hosting

Pastikan paket hosting menyediakan:

- PHP 8.4 atau lebih baru untuk website, terminal, dan cron.
- MySQL 8/InnoDB, Composer 2, Git, terminal/SSH, cron, dan SSL.
- Ekstensi PHP `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`,
  `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `session`, `tokenizer`,
  dan `xml`.
- Document root domain yang dapat diarahkan ke folder `public` Laravel. Jika
  tidak tersedia, gunakan alternatif `public_html` pada bagian 7.

Periksa dari terminal hosting:

```bash
php -v
php -m
composer --version
git --version
which php
which composer
```

Jangan melanjutkan jika PHP terminal masih di bawah 8.4. Path hasil `which php`
akan digunakan pada konfigurasi cron.

## 2. Arahkan DNS dan aktifkan SSL

Isi DNS menggunakan alamat resmi yang diberikan penyedia hosting:

| Tipe | Nama | Nilai |
| --- | --- | --- |
| A | `@` | `<IP_HOSTING>` |
| CNAME | `www` | `hurim.co.id` |

Jangan memasukkan teks `<IP_HOSTING>` secara literal. Jika penyedia hosting
memberikan CNAME atau nameserver khusus, ikuti nilai dari penyedia tersebut.

Setelah DNS aktif:

1. Tambahkan `hurim.co.id` dan `www.hurim.co.id` pada panel hosting.
2. Aktifkan AutoSSL atau Let's Encrypt untuk keduanya.
3. Jadikan `https://hurim.co.id` sebagai alamat utama.
4. Redirect permanen `www.hurim.co.id` ke `https://hurim.co.id`.

Jangan memaksa HTTPS sebelum sertifikat berhasil diterbitkan.

## 3. Buat database produksi

Dari panel hosting:

1. Buat database MySQL baru, misalnya `USERNAME_hurim`.
2. Buat user database dengan password acak yang kuat.
3. Berikan hak akses user tersebut ke database.
4. Catat nama database, username, password, host, dan port.

Gunakan database kosong. Jangan menjalankan `migrate:fresh`, `db:wipe`, atau
`migrate:rollback` pada produksi.

## 4. Clone repository di luar web root

Repository sebaiknya disimpan di luar `public_html`:

```bash
cd ~
mkdir -p apps
cd apps
git clone https://github.com/akmaldthree/websiteasfara.git
cd websiteasfara
git branch --show-current
git remote -v
```

Branch harus `main` dan remote harus menunjuk ke
`akmaldthree/websiteasfara.git`. Struktur yang disarankan:

```text
/home/USERNAME/apps/websiteasfara/        # root aplikasi Laravel
/home/USERNAME/apps/websiteasfara/public  # document root hurim.co.id
```

Repository saat ini public sehingga clone HTTPS tidak memerlukan token. Jika
nanti diubah menjadi private, gunakan SSH deploy key read-only; jangan menaruh
token GitHub di URL remote, `.env`, atau shell history.

## 5. Instal dependency dan siapkan `.env`

```bash
cd ~/apps/websiteasfara
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
cp .env.example .env
nano .env
```

Isi konfigurasi produksi berikut dan ganti seluruh placeholder `<...>`:

```dotenv
APP_NAME=Hurim
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://hurim.co.id
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
SESSION_COOKIE=hurim_session
SESSION_DOMAIN=.hurim.co.id

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=<HOST_SMTP>
MAIL_PORT=587
MAIL_USERNAME=<USER_SMTP>
MAIL_PASSWORD="<PASSWORD_SMTP>"
MAIL_FROM_ADDRESS=noreply@hurim.co.id
MAIL_FROM_NAME=Hurim

TRANSACTION_EMAIL_ENABLED=false
ALLOW_DEMO_SEEDING=false
```

Catatan penting:

- `.env` tidak boleh di-commit atau disimpan di dalam folder publik.
- `APP_KEY` hanya dibuat sekali. Menggantinya setelah website digunakan akan
  merusak sesi dan data terenkripsi.
- Biarkan `TRANSACTION_EMAIL_ENABLED=false` sampai SMTP, reset password, dan
  2FA staf berhasil diuji.
- Jangan memasukkan data demonstrasi ke database produksi.

## 6. Inisialisasi Laravel

Jalankan dari root aplikasi:

```bash
cd ~/apps/websiteasfara
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
chmod -R u+rwX storage bootstrap/cache
php artisan optimize
```

Seeder produksi tidak membuat akun admin atau password bawaan. Jangan memakai
permission `777`.

## 7. Hubungkan domain ke folder `public`

### Pilihan A — document root dapat diubah (direkomendasikan)

Atur document root `hurim.co.id` di panel hosting menjadi:

```text
/home/USERNAME/apps/websiteasfara/public
```

Kemudian jalankan:

```bash
cd ~/apps/websiteasfara
php artisan storage:link
```

Jangan arahkan domain ke root repository. Jika dilakukan, `.env`, source code,
dan dokumen privat berisiko terekspos.

### Pilihan B — hosting mewajibkan `public_html`

Gunakan pilihan ini hanya jika panel tidak dapat mengubah document root. Backup
isi `public_html` terlebih dahulu, lalu salin file publik Laravel:

```bash
cp -a ~/apps/websiteasfara/public/. ~/public_html/
nano ~/public_html/index.php
```

Ubah path bootstrap di `public_html/index.php` agar menunjuk ke aplikasi:

```php
if (file_exists($maintenance = __DIR__.'/../apps/websiteasfara/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../apps/websiteasfara/vendor/autoload.php';
$app = require_once __DIR__.'/../apps/websiteasfara/bootstrap/app.php';
```

Pastikan blok maintenance lama yang menunjuk ke `../storage` sudah diganti,
bukan diduplikasi. Lalu buat link media publik:

```bash
ln -s ~/apps/websiteasfara/storage/app/public ~/public_html/storage
```

Jika hosting melarang symlink, tambahkan ini ke `.env`:

```dotenv
PUBLIC_FILESYSTEM_ROOT=/home/USERNAME/public_html/storage
```

Lalu buat foldernya:

```bash
mkdir -p ~/public_html/storage
```

Pada pilihan B, setiap update aset publik harus diikuti:

```bash
rsync -a --exclude=index.php --exclude=storage ~/apps/websiteasfara/public/ ~/public_html/
```

## 8. Buat akun administrator

```bash
cd ~/apps/websiteasfara
php artisan dthree:admin admin@hurim.co.id --name="Administrator Hurim"
```

Masukkan password kuat saat diminta. Password tidak akan ditampilkan di
terminal. Setelah domain aktif:

- Login: `https://hurim.co.id/masuk`
- Dashboard: `https://hurim.co.id/admin`
- Monitoring: `https://hurim.co.id/admin/monitoring`

## 9. Pasang scheduler dan queue

Ganti `USERNAME` dan `/usr/bin/php` sesuai hasil pemeriksaan server.

Scheduler setiap menit:

```cron
* * * * * cd /home/USERNAME/apps/websiteasfara && /usr/bin/php artisan schedule:run >> /home/USERNAME/apps/websiteasfara/storage/logs/scheduler.log 2>&1
```

Untuk shared hosting tanpa Supervisor, tambahkan queue worker setiap menit:

```cron
* * * * * cd /home/USERNAME/apps/websiteasfara && /usr/bin/php artisan queue:work --stop-when-empty --max-time=50 --tries=3 >> /home/USERNAME/apps/websiteasfara/storage/logs/queue.log 2>&1
```

Untuk VPS, gunakan Supervisor atau systemd agar `php artisan queue:work`
berjalan terus-menerus, lalu jalankan `php artisan queue:restart` setelah setiap
deployment.

Verifikasi:

```bash
cd ~/apps/websiteasfara
php artisan schedule:list
php artisan schedule:run
php artisan system:heartbeat
```

## 10. Verifikasi sebelum dibuka ke publik

```bash
cd ~/apps/websiteasfara
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan system:production-check
git status --short
curl -I https://hurim.co.id/up
curl -I https://www.hurim.co.id/up
```

Pastikan:

- `/up` menghasilkan HTTP 200 dan `www` mengarah ke domain utama.
- `APP_ENV=production`, `APP_DEBUG=false`, dan URL memakai HTTPS.
- Seluruh migration berstatus `Ran`.
- `.env`, `.git`, source, dan `storage/app` tidak dapat diakses melalui web.
- Upload gambar, reset password, email, cron, queue, serta transaksi uji berhasil.
- Data rekening, ongkir, kebijakan, produk, harga, dan stok sudah benar.
- Backup database, `storage/app`, dan `.env` tersimpan di lokasi terpisah.

Setelah SMTP benar-benar teruji, ubah:

```dotenv
TRANSACTION_EMAIL_ENABLED=true
```

Kemudian muat ulang konfigurasi:

```bash
php artisan optimize:clear
php artisan optimize
```

Jangan membuka checkout sebelum transaksi uji dari awal hingga selesai berhasil.

## 11. Update setelah perubahan dipush dari Codex

Setelah perubahan masuk ke branch `main` di GitHub, jalankan dari server:

```bash
cd ~/apps/websiteasfara
bash scripts/deploy.sh
```

Script akan memeriksa branch dan working tree, membuat backup database serta
`storage/app`, menjalankan `git pull --ff-only`, memasang dependency produksi,
menjalankan migration, membuat cache, me-restart queue, dan melakukan
pemeriksaan konfigurasi produksi.

Jika binary hosting berbeda:

```bash
PHP_BIN=/path/php84 COMPOSER_BIN=/path/composer bash scripts/deploy.sh
```

Jika memakai Pilihan B (`public_html`), sinkronkan aset publik setelah deploy:

```bash
rsync -a --exclude=index.php --exclude=storage ~/apps/websiteasfara/public/ ~/public_html/
```

Jangan mengedit kode langsung di hosting. Perubahan kode dilakukan di
repository, di-push ke GitHub, lalu ditarik menggunakan script deployment.

## 12. Checklist singkat go-live

- [ ] DNS `@` dan `www` sudah benar.
- [ ] SSL aktif untuk domain utama dan `www`.
- [ ] PHP web, terminal, dan cron menggunakan PHP 8.4+.
- [ ] Document root hanya mengarah ke folder `public` Laravel.
- [ ] Database produksi terpisah dan migration berhasil.
- [ ] Akun admin dapat login dan tidak ada akun bawaan.
- [ ] Scheduler dan queue berjalan tanpa error berulang.
- [ ] Upload publik serta dokumen privat sudah diuji.
- [ ] SMTP, reset password, email transaksi, dan 2FA sudah diuji.
- [ ] Checkout dan alur operasional sudah diuji lengkap.
- [ ] Backup database/storage berhasil dibuat dan diuji restore di staging.
- [ ] `APP_DEBUG=false` dan tidak ada rahasia di GitHub.

Kredensial hosting, database, SMTP, deploy key, dan isi `.env` tidak boleh
dikirim melalui chat atau disimpan di repository.

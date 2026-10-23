# Panduan deploy Dthree ke `dthree.co.id`

Panduan ini digunakan untuk instalasi pertama dan pembaruan website dari `https://github.com/akmaldthree/websitedthree`. Target server: PHP 8.4+, Composer 2, MySQL 8/InnoDB, Git, HTTPS, terminal/SSH, dan cron. Jangan memakai database atau folder upload milik aplikasi lain.

## 1. Persyaratan hosting

Pastikan hosting menyediakan:

- PHP 8.4+ untuk website, terminal, dan cron.
- MySQL 8/InnoDB, Composer 2, Git, SSH/terminal, cron setiap menit, dan SSL.
- Ekstensi PHP `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, dan `xml`.
- Document root domain yang dapat diarahkan ke folder Laravel `public`.

Verifikasi:

```bash
php -v
php -m
composer --version
git --version
```

Jika terminal memakai binary berbeda, ambil path PHP 8.4 dari panel hosting. Contoh path dalam dokumen ini tidak boleh disalin tanpa memeriksa server.

## 2. DNS, domain, dan SSL

Di pengelola DNS `dthree.co.id`, gunakan nilai resmi dari penyedia hosting:

| Tipe | Nama | Nilai |
| --- | --- | --- |
| A | `@` | `<IP_HOSTING>` |
| CNAME | `www` | `dthree.co.id` |

Jangan mengisi `<IP_HOSTING>` secara literal. Jika hosting memberikan CNAME, ikuti instruksi hosting. Setelah DNS aktif:

1. Pasang Let’s Encrypt/AutoSSL untuk `dthree.co.id` dan `www.dthree.co.id`.
2. Gunakan `https://dthree.co.id` sebagai URL utama.
3. Redirect permanen `www.dthree.co.id` ke `https://dthree.co.id` melalui panel/web server.
4. Jangan memaksa HTTPS sebelum sertifikat terbit.

Verifikasi:

```bash
curl -I https://dthree.co.id/up
curl -I https://www.dthree.co.id/up
```

`/up` harus sukses dan URL `www` harus mengarah ke domain utama.

## 3. Struktur folder yang aman

Simpan repository di luar web root umum:

```text
/home/USERNAME/apps/websitedthree/       # repository Laravel
/home/USERNAME/apps/websitedthree/public # document root dthree.co.id
```

Atur document root `dthree.co.id` ke:

```text
/home/USERNAME/apps/websitedthree/public
```

Jangan arahkan domain ke root repository karena `.env`, source, dan file privat dapat terekspos. Jika shared hosting tidak mengizinkan document root tersebut, minta hosting mengubahnya atau gunakan addon domain dengan document root yang dapat dipilih. Jangan menyalin `.env` ke `public_html`.

## 4. Database MySQL

Dari panel hosting:

1. Buat database khusus, misalnya `USERNAME_dthree`.
2. Buat user database dengan password acak yang kuat.
3. Hubungkan user tersebut ke database.
4. Catat host database resmi; sering berupa `localhost`, tetapi tidak selalu.

Gunakan database kosong saat instalasi pertama. Jangan pernah menjalankan `migrate:fresh` atau `db:wipe` pada staging/produksi.

## 5. Akses repository GitHub

Untuk repository privat, buat SSH deploy key read-only:

```bash
ssh-keygen -t ed25519 -C "deploy-dthree.co.id" -f ~/.ssh/dthree_deploy
cat ~/.ssh/dthree_deploy.pub
```

Tambahkan public key di GitHub repository **Settings → Deploy keys** tanpa write access. Tambahkan konfigurasi `~/.ssh/config`:

```sshconfig
Host github-dthree
    HostName github.com
    User git
    IdentityFile ~/.ssh/dthree_deploy
    IdentitiesOnly yes
```

Batasi permission file SSH:

```bash
chmod 700 ~/.ssh
chmod 600 ~/.ssh/config ~/.ssh/dthree_deploy
chmod 644 ~/.ssh/dthree_deploy.pub
```

Clone aplikasi:

```bash
ssh -T git@github-dthree
mkdir -p ~/apps
cd ~/apps
git clone git@github-dthree:akmaldthree/websitedthree.git
cd websitedthree
git branch --show-current
```

Branch harus `main`. Jangan menyimpan token GitHub di remote URL, `.env`, atau shell history.

## 6. Composer dan `.env`

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
cp .env.example .env
```

Isi `.env` berikut dan ganti seluruh placeholder:

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

Catatan:

- `APP_KEY` dibuat satu kali pada instalasi pertama. Menggantinya merusak sesi, data terenkripsi, dan tautan bertanda tangan.
- Aktifkan SPF dan DKIM dari penyedia email. Terapkan DMARC setelah pengiriman teruji.
- Biarkan `TRANSACTION_EMAIL_ENABLED=false` sampai SMTP, reset password, dan 2FA staf berhasil diuji.
- Jangan commit `.env`.

## 7. Inisialisasi pertama

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan dthree:admin admin@dthree.co.id --name="Administrator Dthree"
php artisan optimize
```

Perintah admin meminta password secara tersembunyi. Seeder tidak membuat password atau akun admin default.

Pastikan folder runtime dapat ditulis oleh user aplikasi:

```bash
chmod -R u+rwX storage bootstrap/cache
```

Gunakan ownership user hosting yang benar. Jangan gunakan permission `777`.

## 8. Cron dan pengiriman email

Tambahkan cron setiap menit. Ganti path sesuai server:

```cron
* * * * * cd /home/USERNAME/apps/websitedthree && /usr/bin/php artisan schedule:run >> /home/USERNAME/apps/websitedthree/storage/logs/scheduler.log 2>&1
```

Scheduler melepaskan reservasi kedaluwarsa dan mengirim outbox email dengan retry terbatas. Uji:

```bash
php artisan schedule:list
php artisan schedule:run
php artisan notifications:send --limit=5
```

Setelah SMTP berhasil, ubah `.env`:

```dotenv
TRANSACTION_EMAIL_ENABLED=true
```

Lalu jalankan:

```bash
php artisan optimize:clear
php artisan optimize
```

Periksa menu **Notifikasi transaksi** untuk pesan gagal. Reset password dan 2FA dikirim langsung melalui email, jadi uji keduanya sebelum mengaktifkan 2FA pada administrator utama.

## 9. Verifikasi instalasi

```bash
php artisan about
php artisan migrate:status
php artisan route:list
php artisan schedule:list
curl -I https://dthree.co.id/up
```

Masuk di `https://dthree.co.id/masuk`, kemudian lengkapi:

- Logo, favicon, hero, kontak, dan sosial.
- Kategori, series, SKU, foto, harga, berat, stok/kuota.
- Rekening bank dan tarif manual POS/JNE.
- Syarat, privasi, pengiriman, retur, dan garansi.
- COD, loyalty, B2B, email, serta role tim.

Lakukan transaksi uji lengkap sebelum checkout dibuka. Jangan menjalankan `DemoSeeder` pada produksi.

## 10. Update setelah push GitHub

Backup database dan seluruh `storage/app`, lalu dari root aplikasi jalankan:

```bash
bash scripts/deploy.sh
```

Jika binary berbeda:

```bash
PHP_BIN=/path/php84 COMPOSER_BIN=/path/composer bash scripts/deploy.sh
```

Script memastikan branch `main` dan working tree bersih, menarik fast-forward, mengaktifkan maintenance, memasang dependency production, menjalankan migration/cache, restart queue, lalu membuka website.

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

Jika gagal, website sengaja tetap maintenance. Periksa `storage/logs/laravel.log`, perbaiki, jalankan ulang, lalu gunakan `php artisan up` hanya setelah semua langkah berhasil.

## 11. Backup dan rollback

Backup minimum:

- Database MySQL.
- `storage/app` untuk bukti pembayaran, dokumen bisnis, dan referensi desain privat.
- `storage/app/public` untuk gambar produk dan branding.
- `.env` secara terenkripsi.

Jadwalkan backup harian dan uji restore di staging. Untuk rollback kode, gunakan commit baik yang diketahui dan rencana migration yang ditinjau. Jangan menjalankan `migrate:rollback`, `reset --hard`, `migrate:fresh`, atau mengganti `APP_KEY` tanpa backup dan analisis data.

## 12. Checklist penerimaan `dthree.co.id`

- [ ] `https://dthree.co.id/up` sukses dan `www` redirect ke domain utama.
- [ ] `.env`, `.git`, source, dan `storage/app` tidak dapat diakses melalui web.
- [ ] PHP web, terminal, dan cron memakai PHP 8.4+.
- [ ] Migration/seeder berhasil dan admin dapat login.
- [ ] Upload gambar publik dan dokumen privat berhasil.
- [ ] Reset password, email transaksi, dan 2FA diterima.
- [ ] Cron berjalan setiap menit tanpa error berulang.
- [ ] Checkout transfer, COD, dan B2B diuji.
- [ ] Fulfillment, retur, refund, dan notifikasi diuji.
- [ ] Desktop serta ponsel diperiksa.
- [ ] Backup database/storage berhasil dipulihkan di staging.
- [ ] `APP_DEBUG=false`; checkout dibuka setelah UAT disetujui.

Kredensial hosting, database, SMTP, dan deploy key tidak boleh dikirim melalui chat atau disimpan dalam repository.

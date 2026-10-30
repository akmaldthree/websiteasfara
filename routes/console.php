<?php

use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audit;
use App\Services\DeliveryService;
use App\Services\OrderWorkflow;
use App\Services\SystemHealthService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Validation\ValidationException;

Artisan::command('dthree:admin {email} {--name=Administrator}', function () {
    $email = strtolower(trim($this->argument('email')));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Email tidak valid.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('Email sudah digunakan; akun tidak diubah.');

        return 1;
    }
    $password = $this->secret('Kata sandi admin (min. 12 karakter, huruf dan angka)');
    if (strlen($password ?? '') < 12 || ! preg_match('/[a-z]/i', $password) || ! preg_match('/[0-9]/', $password)) {
        $this->error('Kata sandi tidak memenuhi persyaratan.');

        return 1;
    }
    $u = User::create(['name' => $this->option('name'), 'email' => $email, 'password' => $password]);
    $u->forceFill(['role' => 'admin'])->save();
    Audit::record('admin.created', 'user:'.$u->id, null, ['email' => $email]);
    $this->info('Admin dibuat. Masuk melalui /masuk.');
})->purpose('Membuat admin tanpa kata sandi default');
Artisan::command('orders:expire', function (OrderWorkflow $workflow) {
    $count = 0;
    Order::where('status', 'waiting')->where('payment_status', '!=', 'review')->where('expires_at', '<', now())->chunkById(100, function ($orders) use ($workflow, &$count) {
        foreach ($orders as $order) {
            try {
                $workflow->act($order, 'expire', 'Melewati batas waktu');
                $count++;
            } catch (ValidationException $e) { /* Another worker completed this order. */
            }
        }
    });
    $this->info("{$count} pesanan diperiksa dan kedaluwarsa.");
});
Schedule::command('orders:expire')->everyMinute()->withoutOverlapping();

Artisan::command('notifications:send {--limit=50}', function (DeliveryService $service) {
    $service->recoverStale();
    if (! config('commerce.email_notifications')) {
        $this->info('Email transaksi dijeda. Konfigurasikan SMTP dan TRANSACTION_EMAIL_ENABLED.');

        return;
    }
    $ids = NotificationDelivery::where('channel', 'email')->whereIn('status', ['pending', 'failed'])
        ->where('attempts', '<', 5)->where('available_at', '<=', now())->orderBy('id')
        ->limit(max(1, min(100, (int) $this->option('limit'))))->pluck('id');
    $sent = 0;
    foreach ($ids as $id) {
        if ($service->send($id)) {
            $sent++;
        }
    }
    $this->info("{$sent} notifikasi dikirim.");
})->purpose('Mengirim outbox email transaksi dengan retry terbatas');
Schedule::command('notifications:send')->everyMinute()->withoutOverlapping(15);

Artisan::command('system:heartbeat', function () {
    Setting::put('system_scheduler_last_seen', now()->toIso8601String());
    $this->info('Heartbeat scheduler dicatat.');
})->purpose('Mencatat heartbeat untuk monitoring scheduler');
Schedule::command('system:heartbeat')->everyMinute()->withoutOverlapping();

Artisan::command('system:backup-record {file}', function () {
    $file = basename((string) $this->argument('file'));
    if ($file === '' || $file === '.' || $file === '..') {
        $this->error('Nama backup tidak valid.');

        return 1;
    }

    Setting::put('system_backup_last_seen', ['at' => now()->toIso8601String(), 'file' => $file]);
    $this->info('Backup dicatat untuk monitoring.');

    return 0;
})->purpose('Mencatat backup database dan storage yang berhasil dibuat');

Artisan::command('system:production-check', function (SystemHealthService $health) {
    $blockingChecks = ['Database', 'Storage', 'Environment', 'Mode aplikasi', 'HTTPS', 'Cookie sesi', 'Kunci aplikasi', 'Email transaksi', 'Ruang disk'];
    $failed = false;

    foreach ($health->checks() as $check) {
        $this->line(sprintf('[%s] %s — %s', strtoupper($check['status']), $check['name'], $check['detail']));
        if (in_array($check['name'], $blockingChecks, true) && $check['status'] === 'error') {
            $failed = true;
        }
    }

    if ($failed) {
        $this->error('Pemeriksaan produksi gagal. Perbaiki item ERROR sebelum website dibuka.');

        return 1;
    }

    $this->info('Pemeriksaan wajib produksi lulus. Tindak lanjuti item WARNING melalui CMS.');

    return 0;
})->purpose('Memvalidasi konfigurasi wajib sebelum website produksi dibuka');

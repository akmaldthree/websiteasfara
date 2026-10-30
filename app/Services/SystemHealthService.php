<?php

namespace App\Services;

use App\Models\NotificationDelivery;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemHealthService
{
    /** @return array<int, array{name: string, status: string, detail: string}> */
    public function checks(): array
    {
        $checks = [];
        try {
            DB::select('select 1');
            $checks[] = $this->check('Database', 'ok', 'Koneksi database aktif.');
        } catch (Throwable) {
            $checks[] = $this->check('Database', 'error', 'Koneksi database gagal.');
        }

        $heartbeat = Setting::get('system_scheduler_last_seen');
        $heartbeatAt = $heartbeat ? Carbon::parse($heartbeat) : null;
        $schedulerHealthy = $heartbeatAt?->greaterThan(now()->subMinutes(5)) ?? false;
        $checks[] = $this->check('Scheduler', $schedulerHealthy ? 'ok' : 'error', $heartbeatAt ? 'Heartbeat terakhir '.$heartbeatAt->diffForHumans().'.' : 'Heartbeat belum pernah diterima.');

        $queuedJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $checks[] = $this->check('Queue', $failedJobs > 0 ? 'error' : ($queuedJobs > 100 ? 'warning' : 'ok'), "{$queuedJobs} job menunggu, {$failedJobs} job gagal.");

        $failedNotifications = Schema::hasTable('notification_deliveries')
            ? NotificationDelivery::whereIn('status', ['failed', 'uncertain'])->count()
            : 0;
        $checks[] = $this->check('Notifikasi', $failedNotifications > 0 ? 'warning' : 'ok', "{$failedNotifications} pengiriman perlu tindak lanjut.");
        $checks[] = $this->check('Storage', is_writable(storage_path()) ? 'ok' : 'error', is_writable(storage_path()) ? 'Folder storage dapat ditulis.' : 'Folder storage tidak dapat ditulis.');
        $production = app()->environment('production');
        $checks[] = $this->check('Environment', $production ? 'ok' : 'error', $production ? 'APP_ENV production.' : 'APP_ENV bukan production.');
        $checks[] = $this->check('Mode aplikasi', config('app.debug') ? 'error' : 'ok', config('app.debug') ? 'APP_DEBUG masih aktif.' : 'Debug dinonaktifkan.');

        $appUrl = (string) config('app.url');
        $https = str_starts_with($appUrl, 'https://');
        $checks[] = $this->check('HTTPS', $https ? 'ok' : 'error', $https ? 'APP_URL menggunakan HTTPS.' : 'APP_URL belum menggunakan HTTPS.');
        $secureCookie = (bool) config('session.secure');
        $checks[] = $this->check('Cookie sesi', $secureCookie ? 'ok' : 'error', $secureCookie ? 'Cookie sesi hanya dikirim melalui HTTPS.' : 'SESSION_SECURE_COOKIE belum aktif.');
        $hasKey = filled(config('app.key'));
        $checks[] = $this->check('Kunci aplikasi', $hasKey ? 'ok' : 'error', $hasKey ? 'APP_KEY tersedia.' : 'APP_KEY belum diatur.');

        $emailEnabled = (bool) config('commerce.email_notifications');
        $mailDriver = (string) config('mail.default');
        $emailReady = ! $emailEnabled || ! in_array($mailDriver, ['log', 'array'], true);
        $checks[] = $this->check('Email transaksi', $emailReady ? ($emailEnabled ? 'ok' : 'warning') : 'error', $emailEnabled ? ($emailReady ? "Aktif melalui mailer {$mailDriver}." : "Aktif tetapi mailer {$mailDriver} tidak mengirim email eksternal.") : 'Dinonaktifkan sampai SMTP selesai diuji.');

        $backup = Setting::get('system_backup_last_seen');
        $backupAt = is_array($backup) && isset($backup['at']) ? Carbon::parse($backup['at']) : null;
        $backupHealthy = $backupAt?->greaterThan(now()->subHours(26)) ?? false;
        $checks[] = $this->check('Backup', $backupHealthy ? 'ok' : 'warning', $backupAt ? 'Backup terakhir '.$backupAt->diffForHumans().'.' : 'Backup aplikasi belum pernah dicatat.');

        $freeBytes = @disk_free_space(storage_path());
        if (is_float($freeBytes)) {
            $freeGb = round($freeBytes / 1024 / 1024 / 1024, 1);
            $checks[] = $this->check('Ruang disk', $freeGb < 1 ? 'error' : ($freeGb < 3 ? 'warning' : 'ok'), "Sisa ruang {$freeGb} GB.");
        }

        return $checks;
    }

    /** @return array{name: string, status: string, detail: string} */
    private function check(string $name, string $status, string $detail): array
    {
        return compact('name', 'status', 'detail');
    }
}

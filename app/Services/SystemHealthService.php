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
        $encryptedSession = (bool) config('session.encrypt');
        $checks[] = $this->check('Enkripsi sesi', $encryptedSession ? 'ok' : 'error', $encryptedSession ? 'Data sesi terenkripsi.' : 'SESSION_ENCRYPT belum aktif.');
        $httpOnlySession = (bool) config('session.http_only');
        $checks[] = $this->check('Akses cookie', $httpOnlySession ? 'ok' : 'error', $httpOnlySession ? 'Cookie sesi tidak dapat dibaca JavaScript.' : 'SESSION_HTTP_ONLY harus aktif.');
        $sameSite = (string) config('session.same_site');
        $sameSiteSafe = in_array($sameSite, ['lax', 'strict'], true);
        $checks[] = $this->check('SameSite cookie', $sameSiteSafe ? 'ok' : 'error', $sameSiteSafe ? "Kebijakan SameSite {$sameSite}." : 'SESSION_SAME_SITE harus lax atau strict.');
        $hasKey = filled(config('app.key'));
        $checks[] = $this->check('Kunci aplikasi', $hasKey ? 'ok' : 'error', $hasKey ? 'APP_KEY tersedia.' : 'APP_KEY belum diatur.');

        $cacheDriver = (string) config('cache.default');
        $persistentCache = ! in_array($cacheDriver, ['array', 'null'], true);
        $checks[] = $this->check('Cache produksi', $persistentCache ? 'ok' : 'error', $persistentCache ? "Cache menggunakan {$cacheDriver}." : "Driver cache {$cacheDriver} tidak persisten.");
        $queueDriver = (string) config('queue.default');
        $persistentQueue = ! in_array($queueDriver, ['sync', 'null'], true);
        $checks[] = $this->check('Queue produksi', $persistentQueue ? 'ok' : 'error', $persistentQueue ? "Queue menggunakan {$queueDriver}." : "Driver queue {$queueDriver} tidak menjalankan worker asinkron.");

        $requiredTables = collect([
            config('session.driver') === 'database' ? (string) config('session.table', 'sessions') : null,
            $cacheDriver === 'database' ? (string) config('cache.stores.database.table', 'cache') : null,
            $queueDriver === 'database' ? (string) config('queue.connections.database.table', 'jobs') : null,
            $queueDriver === 'database' ? 'failed_jobs' : null,
        ])->filter()->unique();
        $missingTables = $requiredTables->reject(fn (string $table): bool => Schema::hasTable($table))->values();
        $checks[] = $this->check('Tabel runtime', $missingTables->isEmpty() ? 'ok' : 'error', $missingTables->isEmpty() ? 'Tabel sesi, cache, dan queue tersedia.' : 'Tabel belum tersedia: '.$missingTables->implode(', ').'. Jalankan migrasi.');

        $publicRoot = (string) config('filesystems.disks.public.root');
        $publicStorageReady = is_dir($publicRoot) && is_writable($publicRoot);
        $checks[] = $this->check('Storage publik', $publicStorageReady ? 'ok' : 'error', $publicStorageReady ? 'Penyimpanan gambar publik tersedia dan dapat ditulis.' : 'Root disk public belum tersedia atau tidak dapat ditulis.');

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

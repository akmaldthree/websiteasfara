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
        $checks[] = $this->check('Mode aplikasi', config('app.debug') ? 'warning' : 'ok', config('app.debug') ? 'APP_DEBUG masih aktif.' : 'Debug dinonaktifkan.');

        return $checks;
    }

    /** @return array{name: string, status: string, detail: string} */
    private function check(string $name, string $status, string $detail): array
    {
        return compact('name', 'status', 'detail');
    }
}

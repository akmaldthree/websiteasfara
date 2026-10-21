<?php

use App\Models\Order;
use App\Models\User;
use App\Services\Audit;
use App\Services\OrderWorkflow;
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

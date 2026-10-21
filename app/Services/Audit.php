<?php

namespace App\Services;

use App\Models\AuditLog;

class Audit
{
    public static function record(string $action, string $subject, mixed $before = null, mixed $after = null): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject' => $subject, 'before' => $before, 'after' => $after, 'ip' => app()->runningInConsole() ? null : request()->ip()]);
    }
}

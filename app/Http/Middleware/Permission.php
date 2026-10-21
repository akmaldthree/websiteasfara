<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Permission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless($request->user()?->allowed($permission), 403, 'Akses tidak diizinkan untuk peran Anda.');

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserCanRedeem
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->status === 'active' && in_array($request->user()?->role, ['admin', 'staff'], true), 403);
        return $next($request);
    }
}

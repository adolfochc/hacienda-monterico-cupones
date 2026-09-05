<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->status !== 'active') {
            auth('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                abort(403, 'Tu acceso se encuentra bloqueado.');
            }
            return redirect()->route('login')->withErrors(['email' => 'Tu acceso se encuentra bloqueado.']);
        }
        if ($request->user()?->must_change_password && !$request->routeIs('password.first.*', 'logout')) {
            return redirect()->route('password.first.edit');
        }
        return $next($request);
    }
}

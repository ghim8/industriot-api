<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Super admin ou non authentifié — pas de filtre
        if (!$user || $user->role === 'super_admin') {
            return $next($request);
        }

        // Injecter via attributes (fiable)
        $request->attributes->set('_entreprise_id', $user->entreprise_id);

        return $next($request);
    }
}
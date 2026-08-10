<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if (tenancy()->initialized && tenant('is_active') === false) {
            abort(403, 'This tenant has been deactivated.');
        }

        return $next($request);
    }
}

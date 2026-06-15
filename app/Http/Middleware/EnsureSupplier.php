<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupplier
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isSupplier()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Supplier access required.');
        }

        return $next($request);
    }
}

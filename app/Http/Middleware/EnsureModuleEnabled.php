<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! config("dropflow.modules.{$module}", false)) {
            return redirect()
                ->route('dashboard')
                ->with('info', 'This module is not enabled yet. See the roadmap on the dashboard.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJson
{
    /**
     * Force API requests to be treated as JSON-producing requests.
     */
    public function handle(Request $request, Closure $next)
    {
        // If no explicit Accept header, tell the app we accept JSON
        if (! $request->headers->has('Accept')) {
            $request->headers->set('Accept', 'application/json');
        }

        // Mark as AJAX to help some middleware that checks X-Requested-With
        if (! $request->headers->has('X-Requested-With')) {
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }

        return $next($request);
    }
}

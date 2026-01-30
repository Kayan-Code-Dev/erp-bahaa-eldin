<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to log all API requests
 * 
 * Logs API requests to ActivityLog for comprehensive audit trail.
 * Can be disabled via config for performance if needed.
 */
class LogApiRequests
{
    /**
     * Routes to exclude from logging
     */
    protected array $excludedRoutes = [
        '/api/health',
        '/api/v1/health',
        '/up',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if logging is enabled (can be disabled via env)
        if (!env('LOG_API_REQUESTS', true)) {
            return $next($request);
        }

        // Skip excluded routes
        if ($this->shouldExcludeRoute($request)) {
            return $next($request);
        }

        // Only log API routes
        if (!$request->is('api/*')) {
            return $next($request);
        }

        $startTime = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

        // Log the API request
        $this->logRequest($request, $response, $duration);

        return $response;
    }

    /**
     * Check if route should be excluded from logging
     */
    protected function shouldExcludeRoute(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->excludedRoutes as $excluded) {
            if (str_contains($path, $excluded)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log the API request
     */
    protected function logRequest(Request $request, Response $response, float $duration): void
    {
        try {
            $user = auth()->user();

            ActivityLog::create([
                'user_id' => $user?->id,
                'action' => ActivityLog::ACTION_API_REQUEST,
                'entity_type' => null,
                'entity_id' => null,
                'description' => "API {$request->method()} {$request->path()} - {$response->getStatusCode()}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'branch_id' => $user?->employee?->primaryBranch()?->id,
                'metadata' => [
                    'path' => $request->path(),
                    'status_code' => $response->getStatusCode(),
                    'duration_ms' => $duration,
                    'query_params' => $request->query->all(),
                    'has_body' => $request->hasContent(),
                ],
            ]);
        } catch (\Exception $e) {
            // Don't break the request if logging fails
            // Log to Laravel's default logger instead
            \Log::warning('Failed to log API request', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
            ]);
        }
    }
}


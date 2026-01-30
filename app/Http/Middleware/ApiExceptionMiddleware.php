<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class ApiExceptionMiddleware
{
    /**
     * Handle an incoming request and return JSON errors for API endpoints.
     */
    public function handle(Request $request, Closure $next)
    {
        // Force JSON responses for API routes so auth/exception handlers
        // return JSON instead of redirecting to a web login route.
        if (! $request->expectsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        try {
            $response = $next($request);

            // If downstream returned a redirect to the login route (common when
            // auth middleware handles unauthenticated web requests), convert it
            // to a JSON 401 for API routes.
            if ($response instanceof RedirectResponse) {
                $location = $response->headers->get('Location');
                // If it's a redirect to a login page (by name or path), return JSON
                if ($location && str_contains($location, '/login')) {
                    return response()->json([
                        'message' => 'Unauthenticated.',
                        'status' => 401,
                        'timestamp' => now()->toISOString(),
                    ], 401);
                }
            }

            return $response;
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
                'status' => 422,
                'timestamp' => now()->toISOString(),
            ], 422);
        } catch (AuthenticationException $e) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'status' => 401,
                'timestamp' => now()->toISOString(),
            ], 401);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Resource not found.',
                'status' => 404,
                'timestamp' => now()->toISOString(),
            ], 404);
        } catch (HttpException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'HTTP Error',
                'status' => $e->getStatusCode(),
                'timestamp' => now()->toISOString(),
            ], $e->getStatusCode());
        } catch (Throwable $e) {
            // In production you may want to hide the exception message
            $message = app()->environment('production') ? 'Server Error' : $e->getMessage();
            return response()->json([
                'message' => $message,
                'status' => 500,
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }
}

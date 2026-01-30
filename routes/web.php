<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/**
 * @OpenApi\Annotations\Info(
 *   title="Bahaa-Eldin API",
 *   version="1.0.0",
 *   description="Auto-generated OpenAPI documentation"
 * )
 *
 * @OA\Get(
 *   path="/api/health",
 *   tags={"Health"},
 *   summary="Health check",
 *   @OA\Response(response=200, description="OK")
 * )
 */
Route::get('/', function () {
    return view('welcome');
});

// Provide a named `login` route so unauthenticated redirects (if any) resolve.
// For API callers we return JSON; for browsers we redirect to home.
Route::get('/login', function (Request $request) {
    // For this application we prefer API-style JSON responses for auth failures.
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

use App\Http\Controllers\AuthController;

// Include l5-swagger routes
require __DIR__.'/../vendor/darkaonline/l5-swagger/src/routes.php';

// NOTE: login route moved to `routes/api.php` under versioned path `/api/v1` to avoid CSRF (419)
// errors for API requests. If you need a web (session) login form, add a route that includes
// a CSRF token in the form.

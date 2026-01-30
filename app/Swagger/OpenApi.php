<?php

namespace App\Swagger;

/**
 * @OA\Server(
 *   url="https://kayancode.com",
 *   description="Production server"
 * )
 * @OA\SecurityScheme(
 *   securityScheme="sanctum",
 *   type="http",
 *   scheme="bearer",
 *   bearerFormat="JWT",
 *   description="Laravel Sanctum bearer token. Format: Bearer {token}"
 * )
 */
class OpenApi
{
    // nothing to execute in this file; it's only for annotations
}

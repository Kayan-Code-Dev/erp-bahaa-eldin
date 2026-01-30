<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\ActivityLog;

/**
 * AuthController
 *
 * Controller for v1 authentication endpoints (login/logout).
 */
class AuthController extends Controller
    {
        /**
         * Login using email and password. Returns a Sanctum token on success.
         *
         * @OA\Post(
         *   path="/api/v1/login",
         *   summary="Login",
         *   tags={"Auth"},
         *   @OA\RequestBody(
         *     required=true,
         *     @OA\JsonContent(
         *       required={"email","password"},
         *       @OA\Property(property="email", type="string", format="email"),
         *       @OA\Property(property="password", type="string", format="password")
         *     )
         *   ),
         *   @OA\Response(
         *     response=200,
         *     description="Successful login",
         *     @OA\JsonContent(
         *       @OA\Property(property="user", type="object"),
         *       @OA\Property(property="token", type="string")
         *     )
         *   ),
         *   @OA\Response(
         *     response=401,
         *     description="Invalid credentials",
         *     @OA\JsonContent(
         *       type="object",
         *       @OA\Property(property="message", type="string"),
         *       @OA\Property(property="status", type="integer"),
         *       @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-02 23:33:25", description="MySQL datetime format: Y-m-d H:i:s")
         *     )
         *   ),
         *   @OA\Response(
         *     response=422,
         *     description="Validation error",
         *     @OA\JsonContent(
         *       type="object",
         *       @OA\Property(property="message", type="string"),
         *       @OA\Property(property="errors", type="object"),
         *       @OA\Property(property="status", type="integer"),
         *       @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-02 23:33:25", description="MySQL datetime format: Y-m-d H:i:s")
         *     )
         *   ),
         *   @OA\Response(
         *     response=500,
         *     description="Server error",
         *     @OA\JsonContent(
         *       type="object",
         *       @OA\Property(property="message", type="string"),
         *       @OA\Property(property="status", type="integer"),
         *       @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-02 23:33:25", description="MySQL datetime format: Y-m-d H:i:s")
         *     )
         *   )
         * )
         */
        public function login(Request $request)
        {
            $data = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $data['email'])->first();
            if (! $user || ! Hash::check($data['password'], $user->password)) {
                // Log failed login attempt
                ActivityLog::logLoginFailed($data['email']);
                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            // create token
            $token = $user->createToken('api-token')->plainTextToken;

            // Log successful login
            ActivityLog::logLogin($user);

            return response()->json([
                'user' => $user->only(['id','name','email']),
                'token' => $token,
            ]);
        }

        /**
         * Logout and revoke the current token.
         *
         * @OA\Post(
         *   path="/api/v1/logout",
         *   summary="Logout",
         *   tags={"Auth"},
         *   security={{"sanctum":{}}},
         *   @OA\Response(
         *     response=200,
         *     description="Logged out",
         *     @OA\JsonContent(
         *       type="object",
         *       @OA\Property(property="message", type="string"),
         *       @OA\Property(property="status", type="integer"),
         *       @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-02 23:33:25", description="MySQL datetime format: Y-m-d H:i:s")
         *     )
         *   ),
         *   @OA\Response(
         *     response=401,
         *     description="Unauthenticated",
         *     @OA\JsonContent(
         *       type="object",
         *       @OA\Property(property="message", type="string"),
         *       @OA\Property(property="status", type="integer"),
         *       @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-02 23:33:25", description="MySQL datetime format: Y-m-d H:i:s")
         *     )
         *   ),
         *   @OA\Response(
         *     response=422,
         *     description="Validation error",
         *     @OA\JsonContent(
         *       type="object",
         *       @OA\Property(property="message", type="string"),
         *       @OA\Property(property="errors", type="object"),
         *       @OA\Property(property="status", type="integer"),
         *       @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-02 23:33:25", description="MySQL datetime format: Y-m-d H:i:s")
         *     )
         *   ),
         *   @OA\Response(
         *     response=500,
         *     description="Server error",
         *     @OA\JsonContent(
         *       type="object",
         *       @OA\Property(property="message", type="string"),
         *       @OA\Property(property="status", type="integer"),
         *       @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-02 23:33:25", description="MySQL datetime format: Y-m-d H:i:s")
         *     )
         *   )
         * )
         */
        public function logout(Request $request)
        {
            $user = $request->user();
            if ($user) {
                // Log logout before revoking token
                ActivityLog::logLogout($user);

                // Revoke current access token
                if (method_exists($user, 'currentAccessToken') && $request->user()->currentAccessToken()) {
                    $request->user()->currentAccessToken()->delete();
                } else {
                    // Fallback: delete all tokens
                    if (method_exists($user, 'tokens')) {
                        $user->tokens()->delete();
                    }
                }
            }

            return response()->json(['message' => 'Logged out']);
        }
    }


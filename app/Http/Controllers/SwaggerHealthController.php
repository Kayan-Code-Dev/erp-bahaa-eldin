<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SwaggerHealthController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/health",
     *   tags={"Health"},
     *   summary="Health endpoint",
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function health(Request $request)
    {
        return response()->json(['status' => 'ok']);
    }
}

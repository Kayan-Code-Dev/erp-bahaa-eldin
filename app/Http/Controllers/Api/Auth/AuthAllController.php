<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AuthAllController extends Controller
{

    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }


    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guard' => 'required|string|in:admin-api,branchManager-api,branch-api,employee-api',
            'emOrMb'    => 'required|string',
            'password'  => 'required|string',
            'fcm_token' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status'  => false, 'message' => $validator->errors()->first(),], Response::HTTP_BAD_REQUEST);
        }
        $data   = $validator->validated();
        $guard = $request->input('guard');
        $result = $this->authService->login($data, $guard);
        return response()->json(
            $result['status']
                ? $result
                : ['status' => false, 'message' => $result['message'] ?? 'Login failed'],
            $result['status'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
        );
    }


    public function logout(Request $request)
    {
        try {
            $result = $this->authService->logout($request);
            if ($result['status']) {
                return response()->json($result, Response::HTTP_OK);
            }
            return response()->json($result, Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'فشل تسجيل الخروج، حاول مرة أخرى لاحقًا'
            ], Response::HTTP_BAD_REQUEST);
        }
    }


    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guard' => 'required|string|in:admin-api,branchManager-api,branch-api,employee-api',
            'emOrMb' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status'  => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data   = $validator->validated();
        $result =  $this->authService->forgotPassword($data, $data['guard']);
        $statusCode = $result['status'] ? Response::HTTP_OK  : Response::HTTP_BAD_REQUEST;
        return response()->json($result, $statusCode);
    }


    public function sendCodeForgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guard' => 'required|string|in:admin-api,branchManager-api,branch-api,employee-api',
            'emOrMb' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data   = $validator->validated();
        $result = $this->authService->sendCodeForgotPassword($data, $data['guard']);
        $statusCode = $result['status'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;
        return response()->json($result, $statusCode);
    }


    public function checkForgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guard' => 'required|string|in:admin-api,branchManager-api,branch-api,employee-api,employee-api',
            'emOrMb' => 'required|string',
            'code'  => 'required|string|min:6|max:6',
        ]);
        if ($validator->fails()) {
            return response()->json(['status'  => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data   = $validator->validated();
        $result = $this->authService->checkForgotPassword($data, $data['guard']);
        $statusCode = $result['status'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;
        return response()->json($result, $statusCode);
    }


    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guard' => 'required|string|in:admin-api,branchManager-api,branch-api,employee-api',
            'emOrMb' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['status'  => false,  'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data   = $validator->validated();
        $result = $this->authService->resetPassword($data, $data['guard']);
        $statusCode = $result['status'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;
        return response()->json($result, $statusCode);
    }


    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);
        if ($validator->fails()) {
            return response()->json(['status'  => false, 'message' => $validator->errors()->first(),], Response::HTTP_BAD_REQUEST);
        }
        $user = $request->user();
        $result = $this->authService->updatePassword($user,  $request->input('current_password'), $request->input('new_password'));
        $statusCode = $result['status'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;
        return response()->json($result, $statusCode);
    }
}

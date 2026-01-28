<?php

namespace App\Services\Auth;

use App\Helpers\EmailHelper;
use App\Helpers\OtpGenerator;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\BranchManager;
use App\Models\EmployeeLogin;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthService
{


    public function login(array $data, string $guard)
    {
        $user = $this->getUser($data, $guard);
        if (!$user) {
            return ['status' => false, 'message' => 'الحساب المدخل غير مسجل'];
        }
        if (!Hash::check($data['password'], $user->password)) {
            return ['status' => false, 'message' => 'كملة المرور غير صحيحة'];
        }
        if ($user->status === 'inactive') {
            return ['status' => false, 'message' => 'الحساب غير مفعل، يرجى تفعيله أولاً'];
        }
        if ($user->blocked) {
            return ['status' => false, 'message' => 'لقد تم حظر حسابك، يرجى الاتصال بالدعم'];
        }
        if (!empty($data['fcm_token'])) {
            $this->saveFcmToken($user, $data['fcm_token']);
        }
        $this->saveLastLogin($user);
        return $this->grantPGCT($data, $guard);
    }


    public function logout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return ['status'  => false, 'message' => 'لم يتم تسجيل الدخول'];
        }
        //الخروج من جميع الاجهزة
        $user->tokens()->delete();
        //الخروج من الجهاز الحالي
        // $request->user()->currentAccessToken()->delete();
        $user->fcm_token = null;
        $user->last_logout = now();
        $user->save();
        return ['status'  => true, 'message' => 'تم تسجيل الخروج بنجاح'];
    }


    public function forgotPassword(array $data, string $guard): array
    {

        $user = $this->getUser($data, $guard);
        if (!$user) {
            return ['status' => false, 'message' => 'الحساب المدخل غير مسجل'];
        }
        if ($user->is_active === false) {
            return ['status' => false, 'message' => 'الحساب غير مفعل, يرجى تفعيله أولاً'];
        }
        if ($user->blocked) {
            return ['status' => false, 'message' => 'لقد تم حظر حسابك، يرجى الاتصال بالدعم'];
        }
        if ($user->otp_code && $user->code_expires_at && $user->code_expires_at > now()) {
            return ['status' => false, 'message' => 'رمز التفعيل لم تنتهي صلاحيته بعد، يرجى الانتظار قبل طلب رمز جديد'];
        }
        $otp_code = OtpGenerator::generateNumeric(6);
        $user->otp_code = Hash::make($otp_code);
        $user->code_expires_at = now()->addMinutes(3);
        $isSavedCode = $user->save();

        if ($isSavedCode) {
            $message = sprintf('رمز التحقق: %s. يرجى استخدام هذا الرمز لإتمام العملية.', $otp_code);
            if (filter_var($data['emOrMb'], FILTER_VALIDATE_EMAIL)) {
                $lang = $data['lang'] ?? 'en';
                EmailHelper::sendTemporaryPassword($user->email, $otp_code, false, $lang, $user->name);
            } else {
                // SmsService::sendMessage($admin->phone, $message);
            }
        }

        return ['status' => true, 'message' => 'تم إرسال رمز الإستعادة بنجاح'];
    }

    public function sendCodeForgotPassword(array $data, string $guard): array
    {
        $user = $this->getUser($data, $guard);
        if (!$user) {
            return ['status' => false, 'message' => 'الحساب المدخل غير مسجل!'];
        }
        if ($user->status === 'inactive') {
            return ['status' => false,  'message' => 'يحب تفعيل الحساب لتمكين من استعاد كلمة المرور'];
        }
        if ($user->code_expires_at && $user->code_expires_at > now()->subSeconds(30)) {
            return ['status' => false, 'message' => 'رمز التفعيل لم تنتهي صلاحيته بعد، يرجى الانتظار قبل طلب رمز جديد'];
        }

        // Generate OTP code
        $otpCode = OtpGenerator::generateNumeric(6);
        $user->otp_code = Hash::make($otpCode);
        $user->code_expires_at = now()->addMinutes(3);
        if ($user->save()) {
            $message = sprintf('رمز التحقق: %s. يرجى استخدام هذا الرمز لإتمام العملية.', $otpCode);
            if (filter_var($data['emOrMb'], FILTER_VALIDATE_EMAIL)) {
                $lang = $data['lang'] ?? 'en';
                EmailHelper::sendTemporaryPassword($user->email, $otpCode, false, $lang, $user->name);
            } else {
                // SmsService::sendMessage($admin->phone, $message);
            }
        }
        return ['status' => true, 'message' => 'تم إعادة إرسال رمز التفعيل بنجاح'];
    }


    public function checkForgotPassword(array $data, string $guard): array
    {
        $user = $this->getUser($data, $guard);
        if (!$user) {
            return ['status'  => false, 'message' => 'الحساب المدخل غير مسجل'];
        }
        if ($user->status === 'inactive') {
            return ['status'  => false,  'message' => 'الحساب غير مفعل، يرجى تفعيله أولاً'];
        }
        if (!$user->otp_code || !$user->code_expires_at) {
            return ['status'  => false, 'message' => 'لم يتم إرسال رمز التفعيل، يرجى المحاولة مرة أخرى'];
        }
        if ($user->code_expires_at < now()) {
            return ['status'  => false,  'message' => 'رمز التفعيل منتهي الصلاحية، يرجى طلب رمز جديد'];
        }
        if (!Hash::check($data['code'], $user->otp_code)) {
            return ['status'  => false, 'message' => 'رمز التفعيل غير صحيح, حاول مرة أخرى'];
        }
        $user->otp_code = null;
        $user->save();
        return ['status'  => true,  'message' => 'تم التحقق من الرمز بنجاح'];
    }



    public function resetPassword(array $data, string $guard): array
    {
        $user = $this->getUser($data, $guard);

        if (!$user) {
            return ['status'  => false, 'message' => 'الحساب المدخل غير مسجل!'];
        }

        if ($user->status === 'inactive') {
            return ['status'  => false,  'message' => 'الحساب غير مفعل، يرجى تفعيله أولاً'];
        }
        // شرط: يجب أن يكون المستخدم قد تحقق من الكود
        if ($user->otp_code !== null) {
            return ['status'  => false, 'message' => 'غير مسموح بإعادة تعيين كلمة المرور إلا بعد التحقق من الكود'];
        }

        // شرط: يجب أن يكون هناك رمز جديد صالح
        if (!$user->code_expires_at || $user->code_expires_at < now()) {
            return ['status'  => false, 'message' => 'غير مسموح بإعادة تعيين كلمة المرور إلا عند طلب رمز تحقق جديد'];
        }
        $user->password = Hash::make($data['password']);
        $user->code_expires_at = null;
        $user->save();
        return ['status'  => true, 'message' => 'تم إستعادة كلمة المرور بنجاح'];
    }


    public function updatePassword($user, string $currentPassword, string $newPassword): array
    {
        if (! $user || ! Hash::check($currentPassword, $user->password)) {
            return ['status'  => false, 'message' => 'كلمة المرور الحالية غير صحيحة',];
        }
        $user->password = Hash::make($newPassword);
        $user->save();
        return ['status'  => true, 'message' => 'تم تحديث كلمة المرور بنجاح'];
    }


    private function grantPGCT($data, string $guard)
    {
        try {
            $response = Http::asForm()->post(env('URL_API_TOKEN'), [
                'grant_type' => 'password',
                'client_id' => env('USER_CLIENT_ID'),
                'client_secret' => env('USER_CLIENT_SECRET'),
                'username' => $data['emOrMb'],
                'password' => $data['password'],
                'scope' => '*'
            ]);
            $admin = $this->getUser($data, $guard);
            $token = $admin->createToken($guard);
            $admin->setAttribute('token', $token->accessToken);
            $admin->setAttribute('token', $token);
            return ['status' => true, 'message' => 'تم تسجيل الدخول بنجاح', 'data' => new AdminResource($admin, $guard)];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'تعذر تسجيل الدخول, حاول مرة أخرى'];
        }
    }


    private function getUser(array $data, string $guard)
    {
        $user = null;
        $emOrMb = $data['emOrMb'];

        switch ($guard) {
            case 'admin-api':
                $query = Admin::query();
                break;

            case 'branchManager-api':
                $query = BranchManager::query();
                break;

            case 'branch-api':
                $query = Branch::query();
                break;

            case 'employee-api':
                $query = EmployeeLogin::query();
                break;

            default:
                return null;
        }

        if (filter_var($emOrMb, FILTER_VALIDATE_EMAIL)) {
            $user = $query->where('email', $emOrMb)->first();
        } elseif (is_numeric($emOrMb)) {
            $lastDigits = substr($emOrMb, -9);
            $user = $query->where('phone', 'LIKE', '%' . $lastDigits)->first();
        }

        return $user;
    }


    private function saveFcmToken($user, string $fcmToken): void
    {
        $user->fcm_token = $fcmToken;
        $user->save();
    }

    private function saveLastLogin($user): void
    {
        $user->last_login = now();
        $user->save();
    }
}

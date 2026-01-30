<?php

namespace App\Services\Admins;

use App\Helpers\EmailHelper;
use App\Helpers\OtpGenerator;
use App\Http\Resources\AdminResource;
use App\Mail\AdminPasswordMail;
use App\Mail\WelcomeAdminMail;
use App\Models\Admin;
use App\Models\City;
use App\Models\Country;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

class AdminService
{

    public function getAllAdmins($perPage = 10)
    {
        $admins = Admin::with('city')->paginate($perPage);
        $mapped = $admins->getCollection()->map(fn($admin) => $this->formatAdmin($admin));
        return [
            'data' => $mapped,
            'current_page' => $admins->currentPage(),
            'next_page_url' => $admins->nextPageUrl(),
            'prev_page_url' => $admins->previousPageUrl(),
            'total' => $admins->total(),
        ];
    }

    public function getRoleAdmin()
    {
        $roles = Role::where('guard_name', '=', 'admin-api')->get()->map(function ($role) {
            return [
                'id' => $role->id ?? '',
                'name' => $role->name ?? '',
            ];
        });
        return $roles;
    }

    public function getCountry()
    {
        $countries =  Country::where('active', '=', true)->get()->map(function ($country) {
            return [
                'id' => $country->id ?? '',
                'name' => $country->name ?? '',
            ];
        });
        return $countries;
    }

    public function getCities(Country $country)
    {
        $cities =  City::where('active', '=', true)->where('country_id', '=', $country->id)->get()->map(function ($city) {
            return [
                'id' => $city->id ?? '',
                'name' => $city->name ?? '',
            ];
        });
        return $cities;
    }


    public function createAdmin(array $data): Admin
    {
        $data = $this->handleImageUpload($data);
        $admin = Admin::create($data);
        $admin->ip_address = request()->ip();
        $otp = OtpGenerator::generateNumeric(6);
        $admin->otp_code = Hash::make($otp);
        $admin->code_expires_at = now()->addMinutes(1);
        $admin->save();
        $admin->assignRole(Role::findOrFail($data['role_id']));
        $this->sendWelcomeMessage($admin, $otp);
        return $admin;
    }


    public function updateAdmin(array $data, Admin $admin): Admin
    {
        $data = $this->handleImageUpload($data, $admin);
        $admin->fill($data);
        $admin->save();
        $admin->syncRoles(Role::findOrFail($data['role_id']));
        return $admin;
    }


    public function verifyEmail(array $data, Admin $admin): void
    {
        if ($admin->code_expires_at && now()->greaterThan($admin->code_expires_at)) {
            throw new \Exception('رمز التفعيل منتهي الصلاحية.');
        }

        if (! Hash::check($data['otp'], $admin->otp_code)) {
            throw new \Exception('رمز التفعيل غير صحيح.');
        }
        $admin->status = 'active';
        $admin->otp_code = null;
        $admin->code_expires_at = null;
        $password = OtpGenerator::generateAlphanumeric(6);
        $admin->password = Hash::make($password);
        $admin->save();
        try {
            $loginUrl = env('APP_URL_LOGIN');
            Mail::to($admin->email)->send(new AdminPasswordMail($admin, $password, $loginUrl));
        } catch (\Exception $e) {
            Log::error("خطأ عند إرسال البريد للمشرف {$admin->id}: " . $e->getMessage());
        }
    }


    protected function handleImageUpload(array $data, Admin $admin = null): array
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            if ($admin && $admin->image) {
                Storage::disk('public')->delete($admin->image);
            }
            $imageName = time() . '_' . str_replace(' ', '', $data['first_name'] ?? $admin->first_name) . '.' . $data['image']->extension();
            $data['image']->storePubliclyAs('Admins', $imageName, ['disk' => 'public']);
            $data['image'] = 'Admins/' . $imageName;
        }
        return $data;
    }

    public function formatAdmin(Admin $admin): array
    {
        return [
            'uuid'        => $admin->uuid,
            'name'      => $admin->first_name . ' ' . $admin->last_name,
            'email'     => $admin->email,
            'phone'     => $admin->phone,
            'id_number' => $admin->id_number,
            'country'   => $admin->city->country->name ?? '',
            'city'      => $admin->city->name ?? '',
            'image'     => $admin->image ? asset('storage/' . $admin->image) : asset('Image/mohammad.jpeg'),
            'status'    => $admin->status,
            'blocked'   => $admin->blocked,
            'created_at' => $admin->created_at?->format('d-m-Y'),
        ];
    }


    protected function sendWelcomeMessage(Admin $admin, string $otp): void
    {
        $activationUrl = url("/cms/admin/activate/{$admin->uuid}?otp={$otp}");
        dispatch(function () use ($admin, $otp, $activationUrl) {
            try {
                Mail::to($admin->email)->send(new WelcomeAdminMail($admin, $otp, $activationUrl));
            } catch (\Exception $e) {
                Log::error('Failed to send welcome email: ' . $e->getMessage());
            }
        })->onQueue('emails');
        Mail::to($admin->email)->send(new WelcomeAdminMail($admin, $otp, $activationUrl));
    }


    public function deleteAdmin(Admin $admin): bool
    {
        return $admin->delete();
    }


    public function getDeletedAdmins($perPage = 10)
    {
        $admins = Admin::onlyTrashed()->with('city')->paginate($perPage);

        $mapped = $admins->getCollection()->map(function ($admin) {
            return $this->formatAdmin($admin);
        });

        $admins->setCollection($mapped);

        return [
            'data' => $admins->items(), // العناصر بعد الفورمات
            'current_page' => $admins->currentPage(),
            'next_page_url' => $admins->nextPageUrl(),
            'prev_page_url' => $admins->previousPageUrl(),
            'total' => $admins->total(),
        ];
    }


    public function restoreAdmin(string $uuid): Admin|bool
    {
        $admin = Admin::onlyTrashed()->where('uuid', $uuid)->first();
        if (! $admin) {
            return false;
        }
        $admin->restore();
        return $admin;
    }


    public function forceDeleteAdmin(string $uuid): bool
    {
        $admin = Admin::withTrashed()->where('uuid', $uuid)->first();
        if (! $admin) {
            return false;
        }
        if ($admin->image) {
            Storage::disk('public')->delete($admin->image);
        }
        $admin->forceDelete();
        return true;
    }


    public function toggleBlockAdmin(string $uuid): ?Admin
    {
        $admin = Admin::where('uuid', $uuid)->first();

        if (! $admin) {
            return null;
        }
        $admin->blocked = $admin->blocked ? 0 : 1;
        $admin->status  = $admin->blocked ? 'suspended' : 'active';
        $admin->save();
        return $admin;
    }
}

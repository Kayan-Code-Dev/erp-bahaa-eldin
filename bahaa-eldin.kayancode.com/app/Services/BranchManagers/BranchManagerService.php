<?php

namespace App\Services\BranchManagers;

use App\Helpers\EmailHelper;
use App\Helpers\OtpGenerator;
use App\Http\Resources\BranchManagerResource;
use App\Mail\BranchManagerPasswordMail;
use App\Mail\WelcomeBranchManagerMail;
use App\Models\Branch;
use App\Models\BranchManager;
use App\Models\City;
use App\Models\Country;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class BranchManagerService
{
    /**
     * جلب جميع مديري الفروع مع pagination
     */
    public function getAllBranchManagers($perPage = 10)
    {
        $managers = BranchManager::with('city')->paginate($perPage);
        // $mapped = $managers->getCollection()->map(fn($manager) => $this->formatBranchManager($manager));
        $mapped = $managers->getCollection()->map(function ($manager) {
            $managerArray = $manager->toArray(); // نرجع كل الحقول كما هي
            $managerArray['role_id'] = $manager->roles->pluck('id')->first() ?? null; // نضيف role_id
            $managerArray['country_id'] = $manager->city->country_id; // نضيف country_id
            $managerArray['city_id'] = $manager->city_id; // نضيف country_id
            $managerArray['image_url'] = $manager->image_url; // نضيف country_id
            return $managerArray;
        });
        return [
            'data' => $mapped,
            'current_page' => $managers->currentPage(),
            'next_page_url' => $managers->nextPageUrl(),
            'prev_page_url' => $managers->previousPageUrl(),
            'total' => $managers->total(),
        ];
    }

    public function getRoleBranchManagerService()
    {
        $roles = Role::where('guard_name', '=', 'branchManager-api')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
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

    /**
     * إنشاء مدير فرع جديد
     */
    public function createBranchManager(array $data): BranchManager
    {
        return DB::transaction(function () use ($data) {
            $data = $this->handleImageUpload($data);
            $manager = BranchManager::create($data);
            $manager->ip_address = request()->ip();
            $otp = OtpGenerator::generateNumeric(6);
            $manager->otp_code = Hash::make($otp);
            $manager->code_expires_at = now()->addMinutes(3);
            $manager->save();
            $manager->assignRole(Role::findOrFail($data['role_id']));
            $this->sendWelcomeMessage($manager, $otp);
            return $manager;
        });
    }

    /**
     * تحديث بيانات مدير فرع
     */
    public function updateBranchManager(array $data, BranchManager $manager): BranchManager
    {
        $data = $this->handleImageUpload($data, $manager);
        $manager->fill($data);
        $manager->save();
        return $manager;
    }

    /**
     * حذف منطقي
     */
    public function deleteBranchManager(BranchManager $manager): bool
    {
        return $manager->delete();
    }

    /**
     * جلب المحذوفين فقط
     */
    public function getDeletedBranchManagers($perPage = 10)
    {
        $managers = BranchManager::onlyTrashed()->with('city')->paginate($perPage);
        // $mapped = $managers->getCollection()->map(fn($manager) => $this->formatBranchManager($manager));
        // $managers->setCollection($mapped);
        $mapped = $managers->getCollection()->map(function ($manager) {
            $managerArray = $manager->toArray(); // نرجع كل الحقول كما هي
            $managerArray['role_id'] = $manager->roles->pluck('id')->first() ?? null; // نضيف role_id
            $managerArray['country_id'] = $manager->city->country_id; // نضيف country_id
            $managerArray['city_id'] = $manager->city_id; // نضيف country_id
            $managerArray['image_url'] = $manager->image_url; // نضيف country_id
            return $managerArray;
        });
        return [
            'data' => $mapped,
            'current_page' => $managers->currentPage(),
            'next_page_url' => $managers->nextPageUrl(),
            'prev_page_url' => $managers->previousPageUrl(),
            'total' => $managers->total(),
        ];
    }

    /**
     * استعادة مدير فرع محذوف
     */
    public function restoreBranchManager(string $uuid): BranchManager|bool
    {
        $manager = BranchManager::onlyTrashed()->where('uuid', $uuid)->first();
        if (!$manager) return false;
        $manager->restore();
        return $manager;
    }

    /**
     * حذف نهائي
     */
    public function forceDeleteBranchManager(string $uuid): bool
    {
        $manager = BranchManager::withTrashed()->where('uuid', $uuid)->first();
        if (!$manager) return false;

        if ($manager->image) {
            Storage::disk('public')->delete($manager->image);
        }
        $manager->forceDelete();
        return true;
    }

    /**
     * حظر أو رفع الحظر
     */
    public function toggleBlockBranchManager(string $uuid): ?BranchManager
    {
        $manager = BranchManager::where('uuid', $uuid)->first();
        if (!$manager) return null;
        $manager->blocked = $manager->blocked ? 0 : 1;
        $manager->status  = $manager->blocked ? 'suspended' : 'active';
        $manager->save();

        return $manager;
    }

    /**
     * رفع الصورة
     */
    protected function handleImageUpload(array $data, BranchManager $manager = null): array
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            if ($manager && $manager->image) {
                Storage::disk('public')->delete($manager->image);
            }
            $imageName = time() . '_' . str_replace(' ', '', $data['first_name'] ?? $manager->first_name) . '.' . $data['image']->extension();
            $data['image']->storePubliclyAs('BranchManagers', $imageName, ['disk' => 'public']);
            $data['image'] = 'BranchManagers/' . $imageName;
        }
        return $data;
    }

    /**
     * فورمات بيانات المدير
     */
    public function formatBranchManager(BranchManager $manager): array
    {
        return [
            'uuid' => $manager->uuid,
            'branch_name' => $manager->branch_name,
            'location' => $manager->location,
            'total_revenue' => 2000.000,
            'number_of_active_requests' => 20,
            'stock_status' => 'جيدة',
            'created_at' => $manager->created_at?->format('d-m-Y'),
        ];
    }

    /**
     * إرسال رسالة الترحيب + رمز التفعيل
     */
    protected function sendWelcomeMessage(BranchManager $manager, string $otp): void
    {
        // لو عندك Mail Class جاهز مثل AdminService
        $activationUrl = url("/cms/branchManager/activate/{$manager->uuid}?otp={$otp}");
        Mail::to($manager->email)->send(new WelcomeBranchManagerMail($manager, $otp, $activationUrl));
    }


    public function verifyEmail(array $data, BranchManager $manager): void
    {
        if ($manager->code_expires_at && now()->greaterThan($manager->code_expires_at)) {
            throw new \Exception('رمز التفعيل منتهي الصلاحية.');
        }

        if (! Hash::check($data['otp'], $manager->otp_code)) {
            throw new \Exception('رمز التفعيل غير صحيح.');
        }
        $manager->status = 'active';
        $manager->otp_code = null;
        $manager->code_expires_at = null;
        $password = OtpGenerator::generateAlphanumeric(6);
        $manager->password = Hash::make($password);
        $manager->save();
        try {
            $loginUrl = env('APP_URL_LOGIN');
            Mail::to($manager->email)->send(new BranchManagerPasswordMail($manager, $password, $loginUrl));
        } catch (\Exception $e) {
            Log::error("خطأ عند إرسال البريد لمدير الفرع {$manager->id}: " . $e->getMessage());
        }
    }
    //********************************************************************************* */


    // public function getMyBranches(BranchManager $branchManager)
    // {
    //     $branches = Branch::query()->where('branch_manager_id', $branchManager->id)->get();
    //     return $branches->map(function ($branch) {
    //         return $this->formatBranch($branch);
    //     });
    // }

    public function getMyBranches($perPage = 10, BranchManager $branchManager)
    {
        $branches = Branch::query()
            ->where('branch_manager_id', $branchManager->id)
            ->where('blocked', false)
            ->paginate($perPage);

        // خليه يطبق الدالة مباشرة على items بدون فقدان خصائص الـ paginator
        $branches->getCollection()->transform(function ($branch) {
            return $this->formatBranch($branch);
        });

        return [
            'data' => $branches->items(),
            'current_page' => $branches->currentPage(),
            'next_page_url' => $branches->nextPageUrl(),
            'prev_page_url' => $branches->previousPageUrl(),
            'total' => $branches->total(),
        ];
    }




    public function formatBranch(Branch $branch): array
    {
        return [
            'uuid' => $branch->uuid,
            'branch_name' => $branch->name,
            'email' => $branch->email,
            'phone' => $branch->phone,
            'location' => $branch->location,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'status' => $branch->status,
            'blocked' => $branch->blocked,
            'created_at' => $branch->created_at?->format('d-m-Y'),
        ];
    }
}

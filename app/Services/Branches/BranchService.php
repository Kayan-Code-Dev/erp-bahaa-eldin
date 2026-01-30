<?php

namespace App\Services\Branches;

use App\Helpers\OtpGenerator;
use App\Mail\BranchPasswordMail;
use App\Mail\WelcomeBranchMail;
use App\Models\Branch;
use App\Models\BranchManager;
use App\Models\WorkShop;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class BranchService
{
    /**
     * جلب كل الفروع مع pagination
     */
    public function getAllBranches($perPage = 10)
    {
        $branches = Branch::with('manager')->paginate($perPage);
        $mapped = $branches->getCollection()->map(fn($branch) => $this->formatBranch($branch));
        return [
            'data' => $mapped,
            'current_page' => $branches->currentPage(),
            'next_page_url' => $branches->nextPageUrl(),
            'prev_page_url' => $branches->previousPageUrl(),
            'total' => $branches->total(),
        ];
    }

    public function getAllBranchManagers()
    {
        $branchManagers = BranchManager::where('blocked', false)->where('status', 'active')->orderBy('first_name')->get()->map(function ($branchManager) {
            return [
                'id' => $branchManager->id,
                'name' => $branchManager->full_name,
            ];
        });

        return $branchManagers;
    }

    /**
     * إنشاء فرع جديد
     */
    public function createBranch(array $data): Branch
    {
        return DB::transaction(function () use ($data) {
            $data = $this->handleImageUpload($data);
            $branch = Branch::create($data);
            $branch->ip_address = request()->ip();
            $otp = OtpGenerator::generateNumeric(6);
            $branch->otp_code = Hash::make($otp);
            $branch->code_expires_at = now()->addMinutes(3);
            $branch->save();
            $this->createBranchWorkShop($branch, $data);
            $this->createBranchRole($branch, $data);
            $this->sendWelcomeMessage($branch, $otp);
            return $branch;
        });
    }

    /**
     * إنشاء وظيفة لمدير الفرع جديد
     */
    private function createBranchRole($branch, $data)
    {
        $existingRole = Role::where('name', $branch->name)
            ->where('guard_name', 'branch-api')
            ->where('branch_id', $branch->id)
            ->first();
        if (!$existingRole) {
            $brancMangerName = BranchManager::where('id', '=', $data['branch_manager_id'])->first();
            $role = Role::create([
                'name' => $brancMangerName->branch_name . '-' . $branch->name,
                'guard_name' => 'branch-api',
                'branch_id' => $branch->id,
            ]);
        } else {
            $role = $existingRole;
        }
        $branch->assignRole($role);
    }

    private function createBranchWorkShop(Branch $branch)
    {
        $existing = WorkShop::where('branch_id', $branch->id)->first();
        if ($existing) {
            return $existing;
        }
        WorkShop::create([
            'name' => $branch->name . ' - ' . ' ورشة',
            'branch_id' => $branch->id,
            'location' => $branch->location ?? null,
        ]);
    }

    /**
     * تحديث بيانات الفرع
     */
    public function updateBranch(array $data, Branch $branch): Branch
    {
        $branch->fill($data);
        $branch->save();
        return $branch;
    }

    /**
     * حذف منطقي
     */
    public function deleteBranch(Branch $branch): bool
    {
        return $branch->delete();
    }

    /**
     * جلب المحذوفين فقط
     */
    public function getDeletedBranches($perPage = 10)
    {
        $branches = Branch::onlyTrashed()->with('manager')->paginate($perPage);
        $mapped = $branches->getCollection()->map(fn($branch) => $this->formatBranch($branch));
        $branches->setCollection($mapped);
        return [
            'data' => $branches->items(),
            'current_page' => $branches->currentPage(),
            'next_page_url' => $branches->nextPageUrl(),
            'prev_page_url' => $branches->previousPageUrl(),
            'total' => $branches->total(),
        ];
    }

    /**
     * استرجاع فرع محذوف
     */
    public function restoreBranch(string $uuid): Branch|bool
    {
        $branch = Branch::onlyTrashed()->where('uuid', $uuid)->first();
        if (!$branch) return false;
        $branch->restore();
        return $branch;
    }

    /**
     * حذف نهائي
     */
    public function forceDeleteBranch(string $uuid): bool
    {
        $branch = Branch::withTrashed()->where('uuid', $uuid)->first();
        if (!$branch) return false;

        if ($branch->image) {
            Storage::disk('public')->delete($branch->image);
        }

        $branch->forceDelete();
        return true;
    }

    /**
     * حظر أو رفع الحظر
     */
    public function toggleBlockBranch(string $uuid, bool $block = true): ?Branch
    {
        $branch = Branch::where('uuid', $uuid)->first();
        if (!$branch) return null;

        $branch->blocked = $branch->blocked ? 0 : 1;
        $branch->status  = $branch->blocked ? 'suspended' : 'active';
        $branch->save();

        return $branch;
    }

    /**
     * رفع الصورة
     */
    protected function handleImageUpload(array $data, Branch $branch = null): array
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            if ($branch && $branch->image) {
                Storage::disk('public')->delete($branch->image);
            }
            $imageName = time() . '_' . str_replace(' ', '', $data['name'] ?? ($branch->name ?? 'branch')) . '.' . $data['image']->extension();
            $data['image']->storePubliclyAs('Branches', $imageName, ['disk' => 'public']);
            $data['image'] = 'Branches/' . $imageName;
        }
        return $data;
    }

    /**
     * فورمات بيانات الفرع
     */
    public function formatBranch(Branch $branch): array
    {
        return [
            'uuid' => $branch->uuid ?? '',
            'branch_manager_id' => $branch->manager->id ?? '',
            'branch_manager' => $branch->manager->first_name ?? '',
            'name' => $branch->name ?? '',
            'email' => $branch->email ?? '',
            'phone' => $branch->phone ?? '',
            'location' => $branch->location ?? '',
            'latitude' => $branch->latitude ?? '',
            'longitude' => $branch->longitude ?? '',
            'status' => $branch->status ?? '',
            'blocked' => (bool) $branch->blocked ?? '',
            'created_at' => $branch->created_at?->format('d-m-Y') ?? '',
        ];
    }

    protected function sendWelcomeMessage(Branch $branch, string $otp): void
    {
        // لو عندك Mail Class جاهز مثل AdminService
        $activationUrl = url("/cms/branch/activate/{$branch->uuid}?otp={$otp}");
        Mail::to($branch->email)->send(new WelcomeBranchMail($branch, $otp, $activationUrl));
    }

    public function verifyEmail(array $data, Branch $branch): void
    {
        if ($branch->code_expires_at && now()->greaterThan($branch->code_expires_at)) {
            throw new \Exception('رمز التفعيل منتهي الصلاحية.');
        }

        if (! Hash::check($data['otp'], $branch->otp_code)) {
            throw new \Exception('رمز التفعيل غير صحيح.');
        }
        $branch->status = 'active';
        $branch->otp_code = null;
        $branch->code_expires_at = null;
        $password = OtpGenerator::generateAlphanumeric(6);
        $branch->password = Hash::make($password);
        $branch->save();
        try {
            $loginUrl = env('APP_URL_LOGIN');
            Mail::to($branch->email)->send(new BranchPasswordMail($branch, $password, $loginUrl));
        } catch (\Exception $e) {
            Log::error("خطأ عند إرسال البريد لمدير الفرع {$branch->id}: " . $e->getMessage());
        }
    }

    public function getDeletedMyBranches($perPage = 10)
    {
        $manger = auth('branchManager-api')->user();
        $branches = Branch::onlyTrashed()->with('manager')->where('branch_manager_id', '=', $manger->id)->paginate($perPage);
        $mapped = $branches->getCollection()->map(fn($branch) => $this->formatBranch($branch));
        $branches->setCollection($mapped);
        return [
            'data' => $branches->items(),
            'current_page' => $branches->currentPage(),
            'next_page_url' => $branches->nextPageUrl(),
            'prev_page_url' => $branches->previousPageUrl(),
            'total' => $branches->total(),
        ];
    }
}

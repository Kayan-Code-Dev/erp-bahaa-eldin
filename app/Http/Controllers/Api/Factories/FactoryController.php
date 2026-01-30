<?php

namespace App\Http\Controllers\Api\Factories;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\EmployeeLogin;
use App\Services\Factories\FactoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class FactoryController extends Controller
{
    protected $factoryService;
    protected ?Branch $branch;
    protected ?EmployeeLogin $employeeLogin;

    public function __construct(FactoryService $factoryService)
    {
        $this->factoryService = $factoryService;
        $this->branch = auth('branch-api')->user(); // فرع عنده صلاحيات
        $this->employeeLogin = auth('employee-api')->user(); // موظف عنده صلاحيات
    }

    private function checkPermission(string $permission)
    {
        if ($this->branch instanceof Branch) {
            if (!$this->branch->can($permission)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
            }
        } elseif ($this->employeeLogin instanceof EmployeeLogin) {
            if (!$this->employeeLogin->can($permission)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
            }
        } else {
            abort(Response::HTTP_UNAUTHORIZED, 'يجب تسجيل الدخول');
        }
    }

    public function indexOrder($perPage = 10)
    {
        $this->checkPermission('Factory-Management');
        $orders = $this->factoryService->index($perPage);
        return response()->json(['status' => true,   'message' => 'تم جلب الطلبات بنجاح', 'data' => $orders], Response::HTTP_OK);
    }

    public function indexDetailsOrder($uuid)
    {
        $this->checkPermission('Factory-Management');
        $orders = $this->factoryService->indexDetails($uuid);
        if ($orders == null) {
            return response()->json(['status' => false,   'message' => 'لم يتم العثور على الطلب',], Response::HTTP_BAD_REQUEST);
        }
        return response()->json(['status' => true,   'message' => 'تم جلب تفاصيل الطلب بنجاح', 'data' => $orders], Response::HTTP_OK);
    }

    public function acceptOrder($uuid)
    {
        $this->checkPermission('Factory-Management');
        $orders = $this->factoryService->acceptOrder($uuid);
        if ($orders == null) {
            return response()->json(['status' => false,   'message' => 'عذرا تم قبلو الطلب من قبل',], Response::HTTP_BAD_REQUEST);
        }
        return response()->json(['status' => true,   'message' => 'تم قبول الطلب بنجاح', 'data' => $orders], Response::HTTP_OK);
    }

    public function startProduction(Request $request, $uuid)
    {
        $this->checkPermission('Factory-Management');
        $validator = Validator::make($request->all(), [
            'expected_finish_date' => 'required|date|after_or_equal:today',
            'production_line' => 'required|string|max:255',
            'notes' => 'required|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $orders = $this->factoryService->startProduction($data, $uuid);
        if ($orders == null) {
            return response()->json(['status' => false,   'عذرا، لم يتم قبول الطلب بعد. الرجاء قبول الطلب قبل بدء العملية.'], Response::HTTP_BAD_REQUEST);
        }
        return response()->json(['status' => true,   'message' => 'تم بدا العملية بنجاح', 'data' => $orders], Response::HTTP_OK);
    }

    public function updateStatusOrder(Request $request, $uuid)
    {
        $this->checkPermission('Factory-Management');
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:in_progress,paused,completed,canceled',
            'production_line' => 'required|string|max:255',
            'quantity' => 'required|integer|min:0',
            'notes' => 'required|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        try {
            $orders = $this->factoryService->updateStatusOrder($data, $uuid);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => false, 'message' => 'عذراً، لم يتم قبول الطلب بعد. الرجاء قبول الطلب قبل بدء العملية.'], Response::HTTP_BAD_REQUEST);
        }
        return response()->json(['status' => true,  'message' => 'تم تحديث العملية بنجاح', 'data' => $orders], Response::HTTP_OK);
    }
}

<?php

namespace App\Http\Controllers\Api\Clients;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use App\Models\EmployeeLogin;
use App\Services\Clients\ClientService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ClientController extends Controller
{
    protected ClientService $clientService;
    protected ?Branch $branch;
    protected ?EmployeeLogin $employeeLogin;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
        $this->branch = auth('branch-api')->user(); // فرع عنده صلاحيات
        $this->employeeLogin = auth('employee-api')->user(); // موظف عنده صلاحيات
    }

    private function checkPermission(string $permission)
    {
        $user = $this->clientService->getCurrentUser();
        /** @var \Spatie\Permission\Traits\HasPermissions|Authenticatable $user */
        if (!$user || !method_exists($user, 'can') || !$user->can($permission)) {
            throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
        }
    }
    /**
     * Display a listing of the resource.
     */
    public function index($perPage = 10)
    {
        //
        // $this->checkPermission('Read-Clients');
        $clients = $this->clientService->index($perPage);
        return response()->json(['status' => true, 'message' => 'تم جلب العملاء بنجاح', 'data' => $clients], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // $this->checkPermission('Create-Client');
        $validator = Validator::make($request->all(), [
            'client_name'            => 'required|string|max:100',
            'client_phone_primary'   => 'required|string|max:20|unique:clients,phone_primary',
            'client_phone_secondary' => 'nullable|string|max:20',
            'client_address'         => 'required|string|max:255',
            'visit_date'             => 'required|date|after_or_equal:today',
            'source'                 => 'nullable|string|max:100',
            'event_date'             => 'required|date|after_or_equal:visit_date',
            'notes'                  => 'nullable|string|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $client = $this->clientService->create($data);
        return response()->json(['status' => true, 'message' => 'تم إنشاء الموظف بنجاح', 'data' => $client], Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        //
    }
}

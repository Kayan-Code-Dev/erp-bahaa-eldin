<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\PhoneController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\WorkshopController;
use App\Http\Controllers\FactoryController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\ClothController;
use App\Http\Controllers\ClothTypeController;
use App\Http\Controllers\ClothTraceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CustodyController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\CashboxController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\RentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\JobTitleController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EmployeeCustodyController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\DeductionController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FactoryOrderController;
use App\Http\Controllers\FactoryDashboardController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierOrderController;

// Versioned API (v1). Mounts v1 endpoints under /api/v1
// Apply API error middleware to all v1 routes so we return consistent JSON errors
Route::prefix('v1')->middleware(['force.json','api.error'])->group(function () {
	Route::post('/login', [AuthController::class, 'login']);

	// Public signed route for custody photos (no authentication required, signature validates access)
	Route::get('/custody-photos/{path}', [CustodyController::class, 'showPhoto'])
		->where('path', '.*')
		->name('custody.photos.show');

	// Protected endpoints (require Sanctum)
	Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

		// ==================== Clients ====================
		Route::get('/clients', [ClientController::class, 'index'])->middleware('permission:clients.view');
		Route::get('/clients/export', [ClientController::class, 'export'])->middleware('permission:clients.export');
		Route::post('/clients', [ClientController::class, 'store'])->middleware('permission:clients.create');
		Route::get('/clients/{id}', [ClientController::class, 'show'])->middleware('permission:clients.view');
		Route::put('/clients/{id}', [ClientController::class, 'update'])->middleware('permission:clients.update');
		Route::delete('/clients/{id}', [ClientController::class, 'destroy'])->middleware('permission:clients.delete');
		Route::get('/clients/{id}/measurements', [ClientController::class, 'getMeasurements'])->middleware('permission:clients.measurements.view');
		Route::put('/clients/{id}/measurements', [ClientController::class, 'updateMeasurements'])->middleware('permission:clients.measurements.update');

		// ==================== Phones ====================
		Route::get('/phones', [PhoneController::class, 'index'])->middleware('permission:phones.view');
		Route::get('/phones/export', [PhoneController::class, 'export'])->middleware('permission:phones.export');
		Route::post('/phones', [PhoneController::class, 'store'])->middleware('permission:phones.create');
		Route::get('/phones/{id}', [PhoneController::class, 'show'])->middleware('permission:phones.view');
		Route::put('/phones/{id}', [PhoneController::class, 'update'])->middleware('permission:phones.update');
		Route::delete('/phones/{id}', [PhoneController::class, 'destroy'])->middleware('permission:phones.delete');

		// ==================== Countries ====================
		Route::get('/countries', [CountryController::class, 'index'])->middleware('permission:countries.view');
		Route::get('/countries/export', [CountryController::class, 'export'])->middleware('permission:countries.export');
		Route::post('/countries', [CountryController::class, 'store'])->middleware('permission:countries.create');
		Route::get('/countries/{id}', [CountryController::class, 'show'])->middleware('permission:countries.view');
		Route::put('/countries/{id}', [CountryController::class, 'update'])->middleware('permission:countries.update');
		Route::delete('/countries/{id}', [CountryController::class, 'destroy'])->middleware('permission:countries.delete');

		// ==================== Cities ====================
		Route::get('/cities', [CityController::class, 'index'])->middleware('permission:cities.view');
		Route::get('/cities/export', [CityController::class, 'export'])->middleware('permission:cities.export');
		Route::post('/cities', [CityController::class, 'store'])->middleware('permission:cities.create');
		Route::get('/cities/{id}', [CityController::class, 'show'])->middleware('permission:cities.view');
		Route::put('/cities/{id}', [CityController::class, 'update'])->middleware('permission:cities.update');
		Route::delete('/cities/{id}', [CityController::class, 'destroy'])->middleware('permission:cities.delete');

		// ==================== Addresses ====================
		Route::get('/addresses', [AddressController::class, 'index'])->middleware('permission:addresses.view');
		Route::get('/addresses/export', [AddressController::class, 'export'])->middleware('permission:addresses.export');
		Route::post('/addresses', [AddressController::class, 'store'])->middleware('permission:addresses.create');
		Route::get('/addresses/{id}', [AddressController::class, 'show'])->middleware('permission:addresses.view');
		Route::put('/addresses/{id}', [AddressController::class, 'update'])->middleware('permission:addresses.update');
		Route::delete('/addresses/{id}', [AddressController::class, 'destroy'])->middleware('permission:addresses.delete');

		// ==================== Suppliers ====================
		Route::prefix('suppliers')->group(function () {
			Route::get('/', [SupplierController::class, 'index'])->middleware('permission:suppliers.view')->name('suppliers.index');
			Route::get('/export', [SupplierController::class, 'export'])->middleware('permission:suppliers.export')->name('suppliers.export');
			Route::post('/store', [SupplierController::class, 'store'])->middleware('permission:suppliers.create')->name('suppliers.store');
			Route::get('/show/{id}', [SupplierController::class, 'show'])->middleware('permission:suppliers.view')->name('suppliers.show');
			Route::put('/update/{id}', [SupplierController::class, 'update'])->middleware('permission:suppliers.update')->name('suppliers.update');
			Route::delete('/delete/{id}', [SupplierController::class, 'destroy'])->middleware('permission:suppliers.delete')->name('suppliers.destroy');
		});

		// ==================== Supplier Orders ====================
		Route::prefix('supplier-orders')->group(function () {
			Route::get('/', [SupplierOrderController::class, 'index'])->middleware('permission:supplier-orders.view')->name('supplier-orders.index');
			Route::get('/export', [SupplierOrderController::class, 'export'])->middleware('permission:supplier-orders.export')->name('supplier-orders.export');
			Route::get('/generate-number', [SupplierOrderController::class, 'generateNumber'])->middleware('permission:supplier-orders.create')->name('supplier-orders.generate-number');
			Route::post('/store', [SupplierOrderController::class, 'store'])->middleware('permission:supplier-orders.create')->name('supplier-orders.store');
			Route::get('/show/{id}', [SupplierOrderController::class, 'show'])->middleware('permission:supplier-orders.view')->name('supplier-orders.show');
			Route::get('clothes/{id}', [SupplierOrderController::class, 'getClothes'])->middleware('permission:supplier-orders.view')->name('supplier-orders.clothes');
			Route::put('/update/{id}', [SupplierOrderController::class, 'update'])->middleware('permission:supplier-orders.update')->name('supplier-orders.update');
			Route::delete('/delete/{id}', [SupplierOrderController::class, 'destroy'])->middleware('permission:supplier-orders.delete')->name('supplier-orders.destroy');
		});

		// ==================== Inventories ====================
		Route::get('/inventories', [InventoryController::class, 'index'])->middleware('permission:inventories.view');
		Route::get('/inventories/export', [InventoryController::class, 'export'])->middleware('permission:inventories.export');
		Route::post('/inventories', [InventoryController::class, 'store'])->middleware('permission:inventories.create');
		Route::get('/inventories/{id}', [InventoryController::class, 'show'])->middleware('permission:inventories.view');
		Route::get('/inventories/{id}/clothes', [InventoryController::class, 'getClothes'])->middleware('permission:inventories.view');
		Route::put('/inventories/{id}', [InventoryController::class, 'update'])->middleware('permission:inventories.update');
		Route::delete('/inventories/{id}', [InventoryController::class, 'destroy'])->middleware('permission:inventories.delete');

		// ==================== Branches ====================
		Route::get('/branches', [BranchController::class, 'index'])->middleware('permission:branches.view');
		Route::get('/branches/export', [BranchController::class, 'export'])->middleware('permission:branches.export');
		Route::post('/branches', [BranchController::class, 'store'])->middleware('permission:branches.create');
		Route::get('/branches/{id}', [BranchController::class, 'show'])->middleware('permission:branches.view');
		Route::put('/branches/{id}', [BranchController::class, 'update'])->middleware('permission:branches.update');
		Route::delete('/branches/{id}', [BranchController::class, 'destroy'])->middleware('permission:branches.delete');

		// ==================== Workshops ====================
		Route::get('/workshops', [WorkshopController::class, 'index'])->middleware('permission:workshops.view');
		Route::get('/workshops/export', [WorkshopController::class, 'export'])->middleware('permission:workshops.export');
		Route::get('/workshops/statuses', [WorkshopController::class, 'statuses'])->middleware('permission:workshops.view');
		Route::get('/workshops/actions', [WorkshopController::class, 'actions'])->middleware('permission:workshops.view');
		Route::post('/workshops', [WorkshopController::class, 'store'])->middleware('permission:workshops.create');
		Route::get('/workshops/{id}', [WorkshopController::class, 'show'])->middleware('permission:workshops.view');
		Route::put('/workshops/{id}', [WorkshopController::class, 'update'])->middleware('permission:workshops.update');
		Route::delete('/workshops/{id}', [WorkshopController::class, 'destroy'])->middleware('permission:workshops.delete');

		// Workshop Cloth Management
		Route::get('/workshops/{id}/clothes', [WorkshopController::class, 'clothes'])->middleware('permission:workshops.manage-clothes');
		Route::get('/workshops/{id}/pending-transfers', [WorkshopController::class, 'pendingTransfers'])->middleware('permission:workshops.approve-transfers');
		Route::post('/workshops/{id}/approve-transfer/{transfer_id}', [WorkshopController::class, 'approveTransfer'])->middleware('permission:workshops.approve-transfers');
		Route::post('/workshops/{id}/update-cloth-status', [WorkshopController::class, 'updateClothStatus'])->middleware('permission:workshops.update-status');
		Route::post('/workshops/{id}/return-cloth', [WorkshopController::class, 'returnCloth'])->middleware('permission:workshops.return-cloth');
		Route::get('/workshops/{id}/logs', [WorkshopController::class, 'logs'])->middleware('permission:workshops.view-logs');
		Route::get('/workshops/{id}/cloth-history/{cloth_id}', [WorkshopController::class, 'clothHistory'])->middleware('permission:workshops.view-logs');

		// ==================== Factories ====================
		// Specific routes must come BEFORE {id} routes
		Route::get('/factories', [FactoryController::class, 'index'])->middleware('permission:factories.view');
		Route::get('/factories/export', [FactoryController::class, 'export'])->middleware('permission:factories.export');
		Route::get('/factories/statistics', [FactoryController::class, 'statistics'])->middleware('permission:factories.view');
		Route::get('/factories/ranking', [FactoryController::class, 'ranking'])->middleware('permission:factories.view');
		Route::get('/factories/workload', [FactoryController::class, 'workload'])->middleware('permission:factories.view');
		Route::get('/factories/recommend', [FactoryController::class, 'recommend'])->middleware('permission:factories.view');
		Route::post('/factories', [FactoryController::class, 'store'])->middleware('permission:factories.create');
		Route::get('/factories/{id}', [FactoryController::class, 'show'])->middleware('permission:factories.view');
		Route::get('/factories/{id}/summary', [FactoryController::class, 'summary'])->middleware('permission:factories.view');
		Route::get('/factories/{id}/trends', [FactoryController::class, 'trends'])->middleware('permission:factories.view');
		Route::get('/factories/{id}/orders', [FactoryController::class, 'orders'])->middleware('permission:factories.view');
		Route::get('/factories/{id}/evaluations', [FactoryController::class, 'evaluations'])->middleware('permission:evaluations.view');
		Route::post('/factories/{id}/recalculate', [FactoryController::class, 'recalculate'])->middleware('permission:factories.manage');
		Route::post('/factories/{id}/evaluations', [FactoryController::class, 'createEvaluation'])->middleware('permission:evaluations.create');
		Route::get('/factories/{id}/users', [FactoryController::class, 'users'])->middleware('permission:factories.manage');
		Route::post('/factories/{id}/users/{userId}', [FactoryController::class, 'assignUser'])->middleware('permission:factories.manage');
		Route::delete('/factories/{id}/users/{userId}', [FactoryController::class, 'removeUser'])->middleware('permission:factories.manage');
		Route::put('/factories/{id}', [FactoryController::class, 'update'])->middleware('permission:factories.update');
		Route::delete('/factories/{id}', [FactoryController::class, 'destroy'])->middleware('permission:factories.delete');
		Route::get('/evaluations/{id}', [FactoryController::class, 'showEvaluation'])->middleware('permission:evaluations.view');

		// ==================== Factory User Routes ====================
		Route::prefix('factory')->middleware('permission:factories.orders.view')->group(function () {
			Route::get('/dashboard', [FactoryDashboardController::class, 'index']);
			Route::get('/statistics', [FactoryDashboardController::class, 'statistics']);
			Route::get('/orders', [FactoryOrderController::class, 'index']);
			Route::get('/orders/{id}', [FactoryOrderController::class, 'show']);
			Route::post('/orders/{orderId}/items/{itemId}/accept', [FactoryOrderController::class, 'acceptItem'])->middleware('permission:factories.orders.accept');
			Route::post('/orders/{orderId}/items/{itemId}/reject', [FactoryOrderController::class, 'rejectItem'])->middleware('permission:factories.orders.reject');
			Route::put('/orders/{orderId}/items/{itemId}/status', [FactoryOrderController::class, 'updateItemStatus'])->middleware('permission:factories.orders.update-status');
			Route::put('/orders/{orderId}/items/{itemId}/notes', [FactoryOrderController::class, 'updateItemNotes'])->middleware('permission:factories.orders.add-notes');
			Route::put('/orders/{orderId}/items/{itemId}/delivery-date', [FactoryOrderController::class, 'setDeliveryDate'])->middleware('permission:factories.orders.set-delivery-date');
			Route::post('/orders/{orderId}/items/{itemId}/deliver', [FactoryOrderController::class, 'deliverItem'])->middleware('permission:factories.orders.deliver');
			Route::get('/orders/{orderId}/items/{itemId}/history', [FactoryOrderController::class, 'getItemStatusHistory']);
		});

		// ==================== Transfers ====================
		Route::get('/transfers', [TransferController::class, 'index'])->middleware('permission:transfers.view');
		Route::get('/transfers/export', [TransferController::class, 'export'])->middleware('permission:transfers.export');
		Route::post('/transfers', [TransferController::class, 'store'])->middleware('permission:transfers.create');
		Route::get('/transfers/{id}', [TransferController::class, 'show'])->middleware('permission:transfers.view');
		Route::put('/transfers/{id}', [TransferController::class, 'update'])->middleware('permission:transfers.update');
		Route::delete('/transfers/{id}', [TransferController::class, 'destroy'])->middleware('permission:transfers.delete');
		Route::post('/transfers/{id}/approve', [TransferController::class, 'approve'])->middleware('permission:transfers.approve');
		Route::post('/transfers/{id}/approve-items', [TransferController::class, 'approveItems'])->middleware('permission:transfers.approve');
		Route::post('/transfers/{id}/reject', [TransferController::class, 'reject'])->middleware('permission:transfers.reject');
		Route::post('/transfers/{id}/reject-items', [TransferController::class, 'rejectItems'])->middleware('permission:transfers.reject');

		// ==================== Cloth Types ====================
		Route::get('/cloth-types', [ClothTypeController::class, 'index'])->middleware('permission:cloth-types.view');
		Route::get('/cloth-types/export', [ClothTypeController::class, 'export'])->middleware('permission:cloth-types.export');
		Route::post('/cloth-types', [ClothTypeController::class, 'store'])->middleware('permission:cloth-types.create');
		Route::get('/cloth-types/{id}', [ClothTypeController::class, 'show'])->middleware('permission:cloth-types.view');
		Route::put('/cloth-types/{id}', [ClothTypeController::class, 'update'])->middleware('permission:cloth-types.update');
		Route::delete('/cloth-types/{id}', [ClothTypeController::class, 'destroy'])->middleware('permission:cloth-types.delete');

		// ==================== Clothes ====================
		// Custom routes must come BEFORE resource-style routes to avoid conflicts
		Route::get('/clothes/available-for-date', [ClothController::class, 'availableForDate'])->middleware('permission:clothes.view');
		Route::get('/clothes/unavailable-days', [ClothController::class, 'bulkUnavailableDays'])->middleware('permission:clothes.view');
		Route::get('/clothes', [ClothController::class, 'index'])->middleware('permission:clothes.view');
		Route::get('/clothes/export', [ClothController::class, 'export'])->middleware('permission:clothes.export');
		Route::post('/clothes', [ClothController::class, 'store'])->middleware('permission:clothes.create');
		Route::get('/clothes/{id}', [ClothController::class, 'show'])->middleware('permission:clothes.view');
		Route::get('/clothes/{id}/unavailable-days', [ClothController::class, 'unavailableDays'])->middleware('permission:clothes.view');
		Route::get('/clothes/{id}/trace', [ClothTraceController::class, 'trace'])->middleware('permission:clothes.view');
		Route::put('/clothes/{id}', [ClothController::class, 'update'])->middleware('permission:clothes.update');
		Route::delete('/clothes/{id}', [ClothController::class, 'destroy'])->middleware('permission:clothes.delete');

		// ==================== Categories ====================
		Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:categories.view');
		Route::get('/categories/export', [CategoryController::class, 'export'])->middleware('permission:categories.export');
		Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:categories.create');
		Route::get('/categories/{id}', [CategoryController::class, 'show'])->middleware('permission:categories.view');
		Route::put('/categories/{id}', [CategoryController::class, 'update'])->middleware('permission:categories.update');
		Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');

		// ==================== Subcategories ====================
		Route::get('/subcategories', [SubcategoryController::class, 'index'])->middleware('permission:subcategories.view');
		Route::get('/subcategories/export', [SubcategoryController::class, 'export'])->middleware('permission:subcategories.export');
		Route::post('/subcategories', [SubcategoryController::class, 'store'])->middleware('permission:subcategories.create');
		Route::get('/subcategories/{id}', [SubcategoryController::class, 'show'])->middleware('permission:subcategories.view');
		Route::put('/subcategories/{id}', [SubcategoryController::class, 'update'])->middleware('permission:subcategories.update');
		Route::delete('/subcategories/{id}', [SubcategoryController::class, 'destroy'])->middleware('permission:subcategories.delete');

		// ==================== Orders ====================
		// Specific routes must come BEFORE {id} routes
		Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:orders.view');
		Route::get('/orders/export', [OrderController::class, 'export'])->middleware('permission:orders.export');
		Route::get('/orders/tailoring/stages', [OrderController::class, 'tailoringStages'])->middleware('permission:orders.view');
		Route::get('/orders/tailoring', [OrderController::class, 'tailoringOrders'])->middleware('permission:tailoring.view');
		Route::get('/orders/tailoring/overdue', [OrderController::class, 'overdueOrders'])->middleware('permission:tailoring.view');
		Route::get('/orders/tailoring/pending-pickup', [OrderController::class, 'pendingPickup'])->middleware('permission:tailoring.view');
		Route::get('/orders/tailoring/ready-for-customer', [OrderController::class, 'readyForCustomer'])->middleware('permission:tailoring.view');
		Route::post('/orders', [OrderController::class, 'store'])->middleware('permission:orders.create');
		Route::get('/orders/{id}', [OrderController::class, 'show'])->middleware('permission:orders.view');
		Route::put('/orders/{id}', [OrderController::class, 'update'])->middleware('permission:orders.update');
		Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->middleware('permission:orders.delete');
		Route::post('/orders/{id}/add-payment', [OrderController::class, 'addPayment'])->middleware('permission:payments.create');
		Route::post('/orders/{orderId}/payments/{paymentId}/pay', [OrderController::class, 'payPayment'])->middleware('permission:payments.pay');
		Route::post('/orders/{orderId}/payments/{paymentId}/cancel', [OrderController::class, 'cancelPayment'])->middleware('permission:payments.cancel');
		Route::post('/orders/{id}/deliver', [OrderController::class, 'deliver'])->middleware('permission:orders.deliver');
		Route::post('/orders/{id}/finish', [OrderController::class, 'finish'])->middleware('permission:orders.finish');
		Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->middleware('permission:orders.cancel');
		Route::post('/orders/{id}/return', [OrderController::class, 'returnItems'])->middleware('permission:orders.return');
		Route::post('/orders/{order_id}/items/{cloth_id}/return', [OrderController::class, 'returnCloth'])->middleware('permission:orders.return');
		Route::post('/orders/{id}/tailoring-stage', [OrderController::class, 'updateTailoringStage'])->middleware('permission:tailoring.manage');
		Route::post('/orders/{id}/assign-factory', [OrderController::class, 'assignFactory'])->middleware('permission:tailoring.manage');
		Route::get('/orders/{id}/stage-history', [OrderController::class, 'stageHistory'])->middleware('permission:tailoring.view');

		// ==================== Custody ====================
		Route::get('/custody', [CustodyController::class, 'index'])->middleware('permission:custody.view');
		Route::get('/custody/export', [CustodyController::class, 'export'])->middleware('permission:custody.export');
		Route::get('/custody/{id}', [CustodyController::class, 'show'])->middleware('permission:custody.view');
		Route::get('/orders/{id}/custody', [CustodyController::class, 'index'])->middleware('permission:custody.view');
		Route::post('/orders/{id}/custody', [CustodyController::class, 'store'])->middleware('permission:custody.create');
		Route::put('/custody/{id}', [CustodyController::class, 'update'])->middleware('permission:custody.update');
		Route::post('/custody/{id}/return', [CustodyController::class, 'returnCustody'])->middleware('permission:custody.return');

		// ==================== Payments ====================
		Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:payments.view');
		Route::get('/payments/export', [PaymentController::class, 'export'])->middleware('permission:payments.export');
		Route::post('/payments', [PaymentController::class, 'store'])->middleware('permission:payments.create');
		Route::get('/payments/{id}', [PaymentController::class, 'show'])->middleware('permission:payments.view');
		Route::post('/payments/{id}/pay', [PaymentController::class, 'pay'])->middleware('permission:payments.pay');
		Route::post('/payments/{id}/cancel', [PaymentController::class, 'cancel'])->middleware('permission:payments.cancel');

		// ==================== Roles ====================
		Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
		Route::get('/roles/export', [RoleController::class, 'export'])->middleware('permission:roles.export');
		Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
		Route::get('/roles/{id}', [RoleController::class, 'show'])->middleware('permission:roles.view');
		Route::put('/roles/{id}', [RoleController::class, 'update'])->middleware('permission:roles.update');
		Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');

		// Role entity type restrictions
		Route::get('/roles/{id}/entity-types', [RoleController::class, 'getEntityTypes'])->middleware('permission:roles.view');
		Route::post('/roles/{id}/entity-types', [RoleController::class, 'setEntityTypes'])->middleware('permission:roles.update');
		Route::delete('/roles/{id}/entity-types', [RoleController::class, 'clearEntityTypes'])->middleware('permission:roles.update');

		// ==================== Users ====================
		Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view');
		Route::get('/users/export', [UserController::class, 'export'])->middleware('permission:users.export');
		Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create');
		Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:users.view');
		Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:users.update');
		Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
		Route::get('/users/{id}/permissions', [PermissionController::class, 'userPermissions'])->middleware('permission:users.view');

		// ==================== Permissions ====================
		Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:roles.view');
		Route::get('/permissions/{id}', [PermissionController::class, 'show'])->middleware('permission:roles.view');
		Route::get('/roles/{id}/permissions', [PermissionController::class, 'rolePermissions'])->middleware('permission:roles.view');
		Route::put('/roles/{id}/permissions', [PermissionController::class, 'assignPermissions'])->middleware('permission:roles.assign-permissions');
		Route::post('/roles/{id}/permissions/toggle', [PermissionController::class, 'togglePermission'])->middleware('permission:roles.assign-permissions');
		Route::get('/me/permissions', [PermissionController::class, 'myPermissions']); // No permission check - users can see their own permissions

		// ==================== Cashboxes ====================
		Route::get('/cashboxes', [CashboxController::class, 'index'])->middleware('permission:cashbox.view');
		Route::get('/cashboxes/{id}', [CashboxController::class, 'show'])->middleware('permission:cashbox.view');
		Route::put('/cashboxes/{id}', [CashboxController::class, 'update'])->middleware('permission:cashbox.manage');
		Route::get('/cashboxes/{id}/daily-summary', [CashboxController::class, 'dailySummary'])->middleware('permission:cashbox.view');
		Route::post('/cashboxes/{id}/recalculate', [CashboxController::class, 'recalculate'])->middleware('permission:cashbox.manage');
		Route::get('/branches/{id}/cashbox', [CashboxController::class, 'branchCashbox'])->middleware('permission:cashbox.view');

		// ==================== Transactions ====================
		Route::get('/transactions', [TransactionController::class, 'index'])->middleware('permission:transactions.view');
		Route::get('/transactions/categories', [TransactionController::class, 'categories'])->middleware('permission:transactions.view');
		Route::get('/transactions/{id}', [TransactionController::class, 'show'])->middleware('permission:transactions.view');
		Route::post('/transactions/{id}/reverse', [TransactionController::class, 'reverse'])->middleware('permission:cashbox.manage');
		Route::get('/cashboxes/{cashbox_id}/transactions', [TransactionController::class, 'forCashbox'])->middleware('permission:transactions.view');

		// ==================== Expenses ====================
		Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:expenses.view');
		Route::get('/expenses/categories', [ExpenseController::class, 'categories'])->middleware('permission:expenses.view');
		Route::get('/expenses/summary', [ExpenseController::class, 'summary'])->middleware('permission:expenses.view');
		Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:expenses.create');
		Route::get('/expenses/{id}', [ExpenseController::class, 'show'])->middleware('permission:expenses.view');
		Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->middleware('permission:expenses.update');
		Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy'])->middleware('permission:expenses.delete');
		Route::post('/expenses/{id}/approve', [ExpenseController::class, 'approve'])->middleware('permission:expenses.approve');
		Route::post('/expenses/{id}/pay', [ExpenseController::class, 'pay'])->middleware('permission:expenses.pay');
		Route::post('/expenses/{id}/cancel', [ExpenseController::class, 'cancel'])->middleware('permission:expenses.delete');

		// ==================== Receivables ====================
		Route::get('/receivables', [ReceivableController::class, 'index'])->middleware('permission:receivables.view');
		Route::get('/receivables/summary', [ReceivableController::class, 'summary'])->middleware('permission:receivables.view');
		Route::post('/receivables', [ReceivableController::class, 'store'])->middleware('permission:receivables.manage');
		Route::get('/receivables/{id}', [ReceivableController::class, 'show'])->middleware('permission:receivables.view');
		Route::put('/receivables/{id}', [ReceivableController::class, 'update'])->middleware('permission:receivables.manage');
		Route::delete('/receivables/{id}', [ReceivableController::class, 'destroy'])->middleware('permission:receivables.manage');
		Route::post('/receivables/{id}/record-payment', [ReceivableController::class, 'recordPayment'])->middleware('permission:receivables.manage');
		Route::post('/receivables/{id}/write-off', [ReceivableController::class, 'writeOff'])->middleware('permission:receivables.manage');
		Route::get('/clients/{client_id}/receivables', [ReceivableController::class, 'forClient'])->middleware('permission:receivables.view');

		// ==================== Appointments (Rents) ====================
		Route::get('/appointments', [RentController::class, 'index'])->middleware('permission:appointments.view');
		Route::get('/appointments/types', [RentController::class, 'types'])->middleware('permission:appointments.view');
		Route::get('/appointments/statuses', [RentController::class, 'statuses'])->middleware('permission:appointments.view');
		Route::get('/appointments/calendar', [RentController::class, 'calendar'])->middleware('permission:appointments.view');
		Route::get('/appointments/today', [RentController::class, 'today'])->middleware('permission:appointments.view');
		Route::get('/appointments/upcoming', [RentController::class, 'upcoming'])->middleware('permission:appointments.view');
		Route::get('/appointments/overdue', [RentController::class, 'overdue'])->middleware('permission:appointments.view');
		Route::post('/appointments', [RentController::class, 'store'])->middleware('permission:appointments.create');
		Route::get('/appointments/{id}', [RentController::class, 'show'])->middleware('permission:appointments.view');
		Route::put('/appointments/{id}', [RentController::class, 'update'])->middleware('permission:appointments.update');
		Route::delete('/appointments/{id}', [RentController::class, 'destroy'])->middleware('permission:appointments.delete');
		Route::post('/appointments/{id}/confirm', [RentController::class, 'confirm'])->middleware('permission:appointments.manage');
		Route::post('/appointments/{id}/start', [RentController::class, 'start'])->middleware('permission:appointments.manage');
		Route::post('/appointments/{id}/complete', [RentController::class, 'complete'])->middleware('permission:appointments.manage');
		Route::post('/appointments/{id}/cancel', [RentController::class, 'cancel'])->middleware('permission:appointments.manage');
		Route::post('/appointments/{id}/no-show', [RentController::class, 'noShow'])->middleware('permission:appointments.manage');
		Route::post('/appointments/{id}/reschedule', [RentController::class, 'reschedule'])->middleware('permission:appointments.manage');
		Route::get('/clients/{client_id}/appointments', [RentController::class, 'forClient'])->middleware('permission:appointments.view');
		Route::get('/clothes/{cloth_id}/availability', [RentController::class, 'checkClothAvailability'])->middleware('permission:appointments.view');

		// ==================== Reports ====================
		Route::get('/reports/available-dresses', [ReportController::class, 'availableDresses'])->middleware('permission:reports.view');
		Route::get('/reports/out-of-branch', [ReportController::class, 'outOfBranch'])->middleware('permission:reports.view');
		Route::get('/reports/overdue-returns', [ReportController::class, 'overdueReturns'])->middleware('permission:reports.view');
		Route::get('/reports/most-rented', [ReportController::class, 'mostRented'])->middleware('permission:reports.view');
		Route::get('/reports/most-sold', [ReportController::class, 'mostSold'])->middleware('permission:reports.view');
		Route::get('/reports/rental-profits', [ReportController::class, 'rentalProfits'])->middleware('permission:reports.financial');
		Route::get('/reports/tailoring-profits', [ReportController::class, 'tailoringProfits'])->middleware('permission:reports.financial');
		Route::get('/reports/factory-evaluations', [ReportController::class, 'factoryEvaluations'])->middleware('permission:reports.performance');
		Route::get('/reports/employee-orders', [ReportController::class, 'employeeOrders'])->middleware('permission:reports.performance');
		Route::get('/reports/daily-cashbox', [ReportController::class, 'dailyCashbox'])->middleware('permission:reports.financial');
		Route::get('/reports/monthly-financial', [ReportController::class, 'monthlyFinancial'])->middleware('permission:reports.financial');
		Route::get('/reports/expenses', [ReportController::class, 'expenses'])->middleware('permission:reports.financial');
		Route::get('/reports/deposits', [ReportController::class, 'deposits'])->middleware('permission:reports.financial');
		Route::get('/reports/debts', [ReportController::class, 'debts'])->middleware('permission:reports.financial');

		// ==================== Notifications ====================
		Route::get('/notifications', [NotificationController::class, 'index']); // Users see their own
		Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
		Route::get('/notifications/types', [NotificationController::class, 'types']);
		Route::get('/notifications/all', [NotificationController::class, 'all'])->middleware('permission:notifications.manage');
		Route::post('/notifications/broadcast', [NotificationController::class, 'broadcast'])->middleware('permission:notifications.manage');
		Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
		Route::post('/notifications/dismiss-all', [NotificationController::class, 'dismissAll']);
		Route::get('/notifications/{id}', [NotificationController::class, 'show']);
		Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
		Route::post('/notifications/{id}/unread', [NotificationController::class, 'markAsUnread']);
		Route::post('/notifications/{id}/dismiss', [NotificationController::class, 'dismiss']);
		Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

		// ==================== HR Module: Departments ====================
		Route::get('/departments', [DepartmentController::class, 'index'])->middleware('permission:hr.departments.view');
		Route::get('/departments/tree', [DepartmentController::class, 'tree'])->middleware('permission:hr.departments.view');
		Route::post('/departments', [DepartmentController::class, 'store'])->middleware('permission:hr.departments.manage');
		Route::get('/departments/{id}', [DepartmentController::class, 'show'])->middleware('permission:hr.departments.view');
		Route::put('/departments/{id}', [DepartmentController::class, 'update'])->middleware('permission:hr.departments.manage');
		Route::delete('/departments/{id}', [DepartmentController::class, 'destroy'])->middleware('permission:hr.departments.manage');

		// ==================== HR Module: Job Titles ====================
		Route::get('/job-titles', [JobTitleController::class, 'index'])->middleware('permission:hr.job-titles.view');
		Route::get('/job-titles/levels', [JobTitleController::class, 'levels'])->middleware('permission:hr.job-titles.view');
		Route::post('/job-titles', [JobTitleController::class, 'store'])->middleware('permission:hr.job-titles.manage');
		Route::get('/job-titles/{id}', [JobTitleController::class, 'show'])->middleware('permission:hr.job-titles.view');
		Route::put('/job-titles/{id}', [JobTitleController::class, 'update'])->middleware('permission:hr.job-titles.manage');
		Route::delete('/job-titles/{id}', [JobTitleController::class, 'destroy'])->middleware('permission:hr.job-titles.manage');
		Route::get('/job-titles/{id}/roles', [JobTitleController::class, 'roles'])->middleware('permission:hr.job-titles.view');
		Route::post('/job-titles/{id}/roles', [JobTitleController::class, 'assignRoles'])->middleware('permission:hr.job-titles.manage');
		Route::delete('/job-titles/{id}/roles', [JobTitleController::class, 'removeRoles'])->middleware('permission:hr.job-titles.manage');
		Route::put('/job-titles/{id}/roles/sync', [JobTitleController::class, 'syncRoles'])->middleware('permission:hr.job-titles.manage');

		// ==================== HR Module: Employees ====================
		Route::get('/employees', [EmployeeController::class, 'index'])->middleware('permission:hr.employees.view');
		Route::get('/employees/me', [EmployeeController::class, 'me']);
		Route::get('/employees/employment-types', [EmployeeController::class, 'employmentTypes'])->middleware('permission:hr.employees.view');
		Route::get('/employees/employment-statuses', [EmployeeController::class, 'employmentStatuses'])->middleware('permission:hr.employees.view');
		Route::post('/employees', [EmployeeController::class, 'store'])->middleware('permission:hr.employees.create');
		Route::get('/employees/{id}', [EmployeeController::class, 'show'])->middleware('permission:hr.employees.view');
		Route::put('/employees/{id}', [EmployeeController::class, 'update'])->middleware('permission:hr.employees.update');
		Route::delete('/employees/{id}', [EmployeeController::class, 'destroy'])->middleware('permission:hr.employees.delete');
		Route::post('/employees/{id}/assign-branches', [EmployeeController::class, 'assignBranches'])->middleware('permission:hr.employees.manage-branches');
		Route::post('/employees/{id}/terminate', [EmployeeController::class, 'terminate'])->middleware('permission:hr.employees.terminate');

		// Entity assignments (polymorphic)
		Route::get('/employees/{id}/entities', [EmployeeController::class, 'getEntities'])->middleware('permission:hr.employees.view');
		Route::post('/employees/{id}/entities', [EmployeeController::class, 'assignEntity'])->middleware('permission:hr.employees.manage-entities');
		Route::delete('/employees/{id}/entities/{entityType}/{entityId}', [EmployeeController::class, 'unassignEntity'])->middleware('permission:hr.employees.manage-entities');
		Route::put('/employees/{id}/entities/sync', [EmployeeController::class, 'syncEntities'])->middleware('permission:hr.employees.manage-entities');

		// ==================== HR Module: Attendance ====================
		Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('permission:hr.attendance.view');
		Route::get('/attendance/my', [AttendanceController::class, 'myAttendance'])->middleware('permission:hr.attendance.check-in');
		Route::get('/attendance/today', [AttendanceController::class, 'today'])->middleware('permission:hr.attendance.check-in');
		Route::get('/attendance/statuses', [AttendanceController::class, 'statuses'])->middleware('permission:hr.attendance.view');
		Route::get('/attendance/report', [AttendanceController::class, 'report'])->middleware('permission:hr.attendance.reports');
		Route::get('/attendance/summary/{employee_id}/{period}', [AttendanceController::class, 'summary'])->middleware('permission:hr.attendance.view');
		Route::post('/attendance', [AttendanceController::class, 'store'])->middleware('permission:hr.attendance.manage');
		Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->middleware('permission:hr.attendance.check-in');
		Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->middleware('permission:hr.attendance.check-in');
		Route::post('/attendance/bulk', [AttendanceController::class, 'bulkStore'])->middleware('permission:hr.attendance.manage');
		Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->middleware('permission:hr.attendance.view');
		Route::put('/attendance/{id}', [AttendanceController::class, 'update'])->middleware('permission:hr.attendance.manage');
		Route::delete('/attendance/{id}', [AttendanceController::class, 'destroy'])->middleware('permission:hr.attendance.manage');

		// ==================== HR Module: Employee Custody (Equipment) ====================
		Route::get('/employee-custodies', [EmployeeCustodyController::class, 'index'])->middleware('permission:hr.custody.view');
		Route::get('/employee-custodies/my', [EmployeeCustodyController::class, 'myCustodies'])->middleware('permission:hr.attendance.check-in');
		Route::get('/employee-custodies/types', [EmployeeCustodyController::class, 'types'])->middleware('permission:hr.custody.view');
		Route::get('/employee-custodies/statuses', [EmployeeCustodyController::class, 'statuses'])->middleware('permission:hr.custody.view');
		Route::get('/employee-custodies/overdue', [EmployeeCustodyController::class, 'overdue'])->middleware('permission:hr.custody.view');
		Route::post('/employee-custodies', [EmployeeCustodyController::class, 'store'])->middleware('permission:hr.custody.assign');
		Route::get('/employee-custodies/{id}', [EmployeeCustodyController::class, 'show'])->middleware('permission:hr.custody.view');
		Route::put('/employee-custodies/{id}', [EmployeeCustodyController::class, 'update'])->middleware('permission:hr.custody.assign');
		Route::delete('/employee-custodies/{id}', [EmployeeCustodyController::class, 'destroy'])->middleware('permission:hr.custody.assign');
		Route::post('/employee-custodies/{id}/return', [EmployeeCustodyController::class, 'return'])->middleware('permission:hr.custody.return');
		Route::post('/employee-custodies/{id}/mark-damaged', [EmployeeCustodyController::class, 'markDamaged'])->middleware('permission:hr.custody.return');
		Route::post('/employee-custodies/{id}/mark-lost', [EmployeeCustodyController::class, 'markLost'])->middleware('permission:hr.custody.return');

		// ==================== HR Module: Employee Documents ====================
		Route::get('/employee-documents', [EmployeeDocumentController::class, 'index'])->middleware('permission:hr.documents.view');
		Route::get('/employee-documents/my', [EmployeeDocumentController::class, 'myDocuments'])->middleware('permission:hr.attendance.check-in');
		Route::get('/employee-documents/types', [EmployeeDocumentController::class, 'types'])->middleware('permission:hr.documents.view');
		Route::get('/employee-documents/expiring', [EmployeeDocumentController::class, 'expiring'])->middleware('permission:hr.documents.view');
		Route::get('/employee-documents/expired', [EmployeeDocumentController::class, 'expired'])->middleware('permission:hr.documents.view');
		Route::post('/employee-documents', [EmployeeDocumentController::class, 'store'])->middleware('permission:hr.documents.upload');
		Route::get('/employee-documents/{id}', [EmployeeDocumentController::class, 'show'])->middleware('permission:hr.documents.view');
		Route::put('/employee-documents/{id}', [EmployeeDocumentController::class, 'update'])->middleware('permission:hr.documents.upload');
		Route::delete('/employee-documents/{id}', [EmployeeDocumentController::class, 'destroy'])->middleware('permission:hr.documents.delete');
		Route::get('/employee-documents/{id}/download', [EmployeeDocumentController::class, 'download'])->middleware('permission:hr.documents.view');
		Route::post('/employee-documents/{id}/verify', [EmployeeDocumentController::class, 'verify'])->middleware('permission:hr.documents.verify');
		Route::post('/employee-documents/{id}/unverify', [EmployeeDocumentController::class, 'unverify'])->middleware('permission:hr.documents.verify');

		// ==================== HR Module: Deductions ====================
		Route::get('/deductions', [DeductionController::class, 'index'])->middleware('permission:hr.deductions.view');
		Route::get('/deductions/types', [DeductionController::class, 'types'])->middleware('permission:hr.deductions.view');
		Route::get('/deductions/summary/{employee_id}/{period}', [DeductionController::class, 'summary'])->middleware('permission:hr.deductions.view');
		Route::post('/deductions', [DeductionController::class, 'store'])->middleware('permission:hr.deductions.create');
		Route::post('/deductions/create-absence', [DeductionController::class, 'createAbsence'])->middleware('permission:hr.deductions.create');
		Route::post('/deductions/create-late', [DeductionController::class, 'createLate'])->middleware('permission:hr.deductions.create');
		Route::get('/deductions/{id}', [DeductionController::class, 'show'])->middleware('permission:hr.deductions.view');
		Route::put('/deductions/{id}', [DeductionController::class, 'update'])->middleware('permission:hr.deductions.create');
		Route::delete('/deductions/{id}', [DeductionController::class, 'destroy'])->middleware('permission:hr.deductions.create');
		Route::post('/deductions/{id}/approve', [DeductionController::class, 'approve'])->middleware('permission:hr.deductions.approve');

		// ==================== HR Module: Payroll ====================
		Route::get('/payrolls', [PayrollController::class, 'index'])->middleware('permission:hr.payroll.view');
		Route::get('/payrolls/my', [PayrollController::class, 'myPayrolls'])->middleware('permission:hr.attendance.check-in');
		Route::get('/payrolls/statuses', [PayrollController::class, 'statuses'])->middleware('permission:hr.payroll.view');
		Route::get('/payrolls/summary/{period}', [PayrollController::class, 'summary'])->middleware('permission:hr.payroll.view');
		Route::post('/payrolls/generate', [PayrollController::class, 'generate'])->middleware('permission:hr.payroll.generate');
		Route::post('/payrolls/generate-bulk', [PayrollController::class, 'generateBulk'])->middleware('permission:hr.payroll.generate');
		Route::get('/payrolls/{id}', [PayrollController::class, 'show'])->middleware('permission:hr.payroll.view');
		Route::put('/payrolls/{id}', [PayrollController::class, 'update'])->middleware('permission:hr.payroll.generate');
		Route::delete('/payrolls/{id}', [PayrollController::class, 'destroy'])->middleware('permission:hr.payroll.generate');
		Route::post('/payrolls/{id}/recalculate', [PayrollController::class, 'recalculate'])->middleware('permission:hr.payroll.generate');
		Route::post('/payrolls/{id}/submit', [PayrollController::class, 'submit'])->middleware('permission:hr.payroll.generate');
		Route::post('/payrolls/{id}/approve', [PayrollController::class, 'approve'])->middleware('permission:hr.payroll.approve');
		Route::post('/payrolls/{id}/reject', [PayrollController::class, 'reject'])->middleware('permission:hr.payroll.approve');
		Route::post('/payrolls/{id}/pay', [PayrollController::class, 'pay'])->middleware('permission:hr.payroll.pay');
		Route::post('/payrolls/{id}/cancel', [PayrollController::class, 'cancel'])->middleware('permission:hr.payroll.generate');

		// ==================== HR Module: Activity Logs ====================
		Route::get('/activity-logs', [ActivityLogController::class, 'index'])->middleware('permission:hr.activity-log.view');
		Route::get('/activity-logs/my', [ActivityLogController::class, 'myActivity']);
		Route::get('/activity-logs/today', [ActivityLogController::class, 'today'])->middleware('permission:hr.activity-log.view');
		Route::get('/activity-logs/actions', [ActivityLogController::class, 'actions'])->middleware('permission:hr.activity-log.view');
		Route::get('/activity-logs/statistics', [ActivityLogController::class, 'statistics'])->middleware('permission:hr.activity-log.view');
		Route::get('/activity-logs/deletions', [ActivityLogController::class, 'deletions'])->middleware('permission:hr.activity-log.view');
		Route::get('/activity-logs/login-attempts', [ActivityLogController::class, 'loginAttempts'])->middleware('permission:hr.activity-log.view');
		Route::get('/activity-logs/entity/{entity_type}/{entity_id}', [ActivityLogController::class, 'forEntity'])->middleware('permission:hr.activity-log.view');
		Route::get('/activity-logs/user/{user_id}', [ActivityLogController::class, 'forUser'])->middleware('permission:hr.activity-log.view');
		Route::get('/activity-logs/{id}', [ActivityLogController::class, 'show'])->middleware('permission:hr.activity-log.view');

		// ==================== Dashboard ====================
		Route::get('/dashboard/overview', [DashboardController::class, 'overview'])->middleware('permission:dashboard.view');
		Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->middleware('permission:dashboard.view');

		// Activity Analytics
		Route::get('/dashboard/activity/summary', [DashboardController::class, 'activitySummary'])->middleware('permission:dashboard.activity.view');
		Route::get('/dashboard/activity/trends', [DashboardController::class, 'activityTrends'])->middleware('permission:dashboard.activity.view');
		Route::get('/dashboard/activity/users', [DashboardController::class, 'userActivity'])->middleware('permission:dashboard.activity.view');
		Route::get('/dashboard/activity/entities', [DashboardController::class, 'entityActivity'])->middleware('permission:dashboard.activity.view');
		Route::get('/dashboard/activity/top-users', [DashboardController::class, 'topUsers'])->middleware('permission:dashboard.activity.view');
		Route::get('/dashboard/activity/top-entities', [DashboardController::class, 'topEntities'])->middleware('permission:dashboard.activity.view');
		Route::get('/dashboard/activity/timeline', [DashboardController::class, 'activityTimeline'])->middleware('permission:dashboard.activity.view');

		// Business Metrics
		Route::get('/dashboard/business/sales', [DashboardController::class, 'salesMetrics'])->middleware('permission:dashboard.business.view');
		Route::get('/dashboard/business/clients', [DashboardController::class, 'clientMetrics'])->middleware('permission:dashboard.business.view');
		Route::get('/dashboard/business/payments', [DashboardController::class, 'paymentMetrics'])->middleware('permission:dashboard.business.view');
		Route::get('/dashboard/business/inventory', [DashboardController::class, 'inventoryMetrics'])->middleware('permission:dashboard.business.view');
		Route::get('/dashboard/business/financial', [DashboardController::class, 'financialMetrics'])->middleware('permission:dashboard.business.view');

		// HR Metrics
		Route::get('/dashboard/hr/attendance', [DashboardController::class, 'attendanceMetrics'])->middleware('permission:dashboard.hr.view');
		Route::get('/dashboard/hr/payroll', [DashboardController::class, 'payrollMetrics'])->middleware('permission:dashboard.hr.view');
		Route::get('/dashboard/hr/employees', [DashboardController::class, 'employeeActivityMetrics'])->middleware('permission:dashboard.hr.view');
		Route::get('/dashboard/hr/trends', [DashboardController::class, 'hrTrends'])->middleware('permission:dashboard.hr.view');
	});
});

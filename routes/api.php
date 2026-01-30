<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\Auth\AuthAllController;
use App\Http\Controllers\Api\Branch\BranchController;
use App\Http\Controllers\Api\BranchManagers\BranchManagerController;
use App\Http\Controllers\Api\BranchManagers\RoleAndPermissionController;
use App\Http\Controllers\Api\Categories\CategoryController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\Clients\ClientController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\Department\DepartmentController;
use App\Http\Controllers\Api\Employees\EmployeeController;
use App\Http\Controllers\Api\Factories\FactoryController;
use App\Http\Controllers\Api\Inventory\InventoryController;
use App\Http\Controllers\Api\InventoryTransfer\InventoryTransferController;
use App\Http\Controllers\Api\Job\JobController;
use App\Http\Controllers\Api\Orders\OrderController;
use App\Http\Controllers\Api\RoleAndPermission\RolePermissionController;
use App\Http\Controllers\Api\SubCategory\SubCategoryController;
use App\Http\Controllers\Api\WorkShop\WorkShopController;
use Illuminate\Support\Facades\Route;

/*
 * |--------------------------------------------------------------------------
 * | API Routes
 * |--------------------------------------------------------------------------
 * | This file contains all API routes for admins.
 * | Structure:
 * | - v1/web/admins
 */

/*
 * |--------------------------------------------------------------------------
 * | Fallback Route
 * |--------------------------------------------------------------------------
 */

Route::fallback(function () {
    return response()->json([
        'message' => 'عذرًا، لم يتم العثور على الصفحة المطلوبة. يُرجى التحقق من الرابط.'
    ], 404);
});


/*
 * |--------------------------------------------------------------------------
 * |    Api Web Admins && BranchesManger && Branch  Routes
 * |--------------------------------------------------------------------------
 */

Route::prefix('v1/web/')->group(function () {


    /*
 * |--------------------------------------------------------------------------
 * |Api  Auth Admin && BranchesManger && Branch  Routes
 * |--------------------------------------------------------------------------
 */
    Route::prefix('auth/')->group(function () {

        Route::middleware('guest:admin-api,branchManager-api,branch-api,employee-api')->group(function () {

            Route::controller(AuthAllController::class)->group(function () {
                Route::post('login', 'login');
                Route::post('forgot-password', 'forgotPassword');
                Route::post('send-code-forgot-password', 'sendCodeForgotPassword');
                Route::post('check-forgot-password', 'checkForgotPassword');
                Route::post('reset-password', 'resetPassword');
            });
        });



        Route::middleware(['auth:admin-api,branchManager-api,branch-api,employee-api'])->group(function () {

            Route::controller(RolePermissionController::class)->group(function () {
                Route::get('get_my_permissions', 'getMyPermissions');
            });

            Route::controller(AuthAllController::class)->group(function () {
                Route::post('update-password', 'updatePassword');
                Route::get('logout', 'logout');
            });
        });
    });



    /*
 * |--------------------------------------------------------------------------
 * |Api  Admin  Routes
 * |--------------------------------------------------------------------------
 */
    Route::prefix('admins/')->group(function () {
        Route::middleware(['auth:admin-api'])->group(function () {

            Route::prefix('roles')->controller(RolePermissionController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::put('/{role}', 'update');
                Route::delete('/{id}', 'destroy');
                Route::get('/{role}', 'show');
            });

            Route::prefix('permissions')->controller(RolePermissionController::class)->group(function () {
                Route::get('/', 'indexPermissions');
                Route::post('/role', 'togglePermission');
            });


            Route::prefix('countries')->controller(CountryController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::put('/{country}', 'update');
                Route::delete('/{country}', 'destroy');
            });

            Route::prefix('cities')->controller(CityController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/{country}', 'getCityByCountry');
                Route::post('/', 'store');
                Route::put('/{city}', 'update');
                Route::delete('/{city}', 'destroy');
            });

            Route::prefix('admins')->controller(AdminController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/get_role_admin', 'getRoleAdmin');
                Route::get('/get_countries', 'getCountry');
                Route::get('/get_cities/{country}', 'getCity');
                Route::post('/', 'store');
                Route::put('/{admin}', 'update');
                Route::delete('/{admin}', 'destroy');
                Route::get('/get_deleted_admins', 'getDeletedAdmins');
                Route::get('/restore_admin/{admin}', 'restore');
                Route::delete('/force_delete_admin/{admin}', 'forceDelete');
                Route::post('/block_admin/{admin}', 'block');
            });


            Route::prefix('branch-managers')->controller(BranchManagerController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/get_role_branch_manager', 'getRoleBranchManager');
                Route::get('/get_countries', 'getCountry');
                Route::get('/get_cities/{country}', 'getCity');

                Route::post('/', 'store');
                Route::put('/{branchManager}', 'update');
                Route::delete('/{branchManager}', 'destroy');
                Route::get('/get_deleted_branchManagers', 'getDeletedBranchManagers');
                Route::get('/restore_branchManager/{branchManager}', 'restore');
                Route::delete('/force_delete_branchManager/{branchManager}', 'forceDelete');
                Route::post('/block_branchManager/{branchManager}', 'block');
            });

            Route::prefix('branches')->controller(BranchController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/branch_Manger', 'getBranchManagers');
                Route::post('/', 'store');
                Route::put('/{branch}', 'update');
                Route::delete('/{branch}', 'destroy');
                Route::get('/get_deleted_branches', 'getDeletedBranches');
                Route::get('/restore_branches/{branch}', 'restore');
                Route::delete('/force_delete_branches/{branch}', 'forceDelete');
                Route::post('/block_branches/{branch}', 'block');
            });
        });
    });


    /*
 * |--------------------------------------------------------------------------
 * |Api  Branch Manager  Routes
 * |--------------------------------------------------------------------------
 */
    Route::prefix('branch_managers/')->group(function () {
        Route::middleware(['auth:branchManager-api'])->group(function () {

            Route::controller(RoleAndPermissionController::class)->group(function () {
                Route::get('my_roles_branch_managers', 'getMyRoles');
                Route::get('role/{role}', 'show');
                Route::get('my_permissions_branch_managers', 'getPermissionsBranchManager');
                Route::post('/togglePermission', 'togglePermission');
            });


            //CREATE MY BRANCHES
            Route::controller(BranchManagerController::class)->group(function () {
                Route::get('get_my_branches', 'getMyBranches');
                Route::post('create_branches', 'createBranches');
                Route::put('update_branches/{branch}', 'updateBranches');
                Route::delete('delete_branches/{branch}', 'deleteBranches');

                Route::get('/get_deleted_my_branches', 'getDeletedMyBranches');
                Route::get('/restore_my_branches/{branch}', 'restoreMyBranch');
                Route::delete('/force_delete_my_branches/{branch}', 'forceDeleteeMyBranch');
                Route::post('/block_my_branches/{branch}', 'blockMyBranch');
            });

            Route::prefix('employees')->controller(EmployeeController::class)->group(function () {
                Route::get('get_employees', 'getEmployees');
                Route::get('get_employee/{uuid}', 'getEmployee');
                Route::get('get_branches', 'getBranches');
                Route::get('get_branches_department/{branch}', 'getBranchesDepartment');
                Route::get('get_branches_job/{department}', 'getBranchesJob');
                Route::get('get_countries/', 'getCountries');
                Route::get('get_cities_by_country/{country}', 'getCitiesByCountry');
                Route::get('get_role_branch/{branch}', 'getRoleBranch');
                Route::post('create_employee', 'createEmployees');
                Route::put('update_employees/{uuid}', 'updateEmployee');
                Route::delete('delete_employee/{uuid}', 'deleteEmployee');
                Route::get('/get_deleted_employees', 'getDeletedEmployees');
                Route::get('/restore_employees/{uuid}', 'restoreEmployee');
                Route::delete('/force_delete_employees/{uuid}', 'forceDeleteEmployee');
                Route::post('/block_employees/{uuid}', 'blockEmployee');
            });


            Route::prefix('inventories')->controller(InventoryController::class)->group(function () {
                Route::get('/', 'indexBranchManager');
            });

            Route::prefix('inventory_transfers')->controller(InventoryTransferController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('get_branches', 'getBranches');
                Route::get('ge_category/{branch}', 'getCategories');
                Route::get('get_sub_category_by_categories/{category}', 'getSubCategoriesByCategory');
                Route::post('/', 'store');
                Route::post('/{InventoryTransfer:uuid}/approve', 'approve');
                Route::post('/{InventoryTransfer:uuid}/reject', 'reject');
            });
        });
    });



    /*
 * |--------------------------------------------------------------------------
 * |Api  Branch  Routes
 * |--------------------------------------------------------------------------
 */
    Route::prefix('branches/')->group(function () {
        Route::middleware(['auth:branch-api'])->group(function () {

            Route::controller(RoleAndPermissionController::class)->group(function () {
                Route::get('my_roles_branches', 'getMyRolesEmployees');
                Route::get('role/{role}', 'show');
                Route::post('create_roles_branch', 'createRoleEmployeeBranch');
                Route::get('my_permissions_branches', 'getPermissionsEmployeeBranch');
                Route::post('/togglePermission', 'togglePermission');
            });

            Route::prefix('departments')->controller(DepartmentController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::put('/{department}', 'update');
                Route::delete('/{department}', 'destroy');
            });

            Route::prefix('jobs')->controller(JobController::class)->group(function () {
                Route::get('/get_department', 'getDepartment');
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::put('/{job}', 'update');
                Route::delete('/{job}', 'destroy');
            });

            Route::prefix('employees')->controller(EmployeeController::class)->group(function () {
                Route::get('get_my_employees', 'getMyEmployees');
                Route::get('get_employee/{uuid}', 'getEmployee');
                Route::get('get_my_branches', 'getMyBranches');
                Route::get('get_my_branches_department', 'getMyBranchesDepartment');
                Route::get('get_my_branches_job/{department}', 'getMyBranchesJob');
                Route::get('get_countries/', 'getCountries');
                Route::get('get_cities_by_country/{country}', 'getCitiesByCountry');
                Route::get('get_my_role_branch', 'getMyRoleBranch');
                Route::post('create_my_employee', 'createMyEmployees');
                Route::put('update_my_employees/{uuid}', 'updateMyEmployee');
                Route::delete('delete_my_employee/{uuid}', 'deleteMyEmployee');
                Route::get('/get_deleted_my_employees', 'getDeletedMyEmployees');
                Route::get('/restore_my_employees/{uuid}', 'restoreMyEmployee');
                Route::delete('/force_delete_my_employees/{uuid}', 'forceDeleteMyEmployee');
                Route::post('/block_my_employees/{uuid}', 'blockMyEmployee');
            });

            Route::prefix('categories')->controller(CategoryController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::put('/{category}', 'update');
                Route::delete('/{category}', 'destroy');
            });

            Route::prefix('sub_categories')->controller(SubCategoryController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/get_my_categories', 'indexMyCategories');
                Route::post('/', 'store');
                Route::put('/{subCategory}', 'update');
                Route::delete('/{subCategory}', 'destroy');
            });


            Route::prefix('inventories')->controller(InventoryController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/ge_category', 'getCategories');
                Route::get('/get_sub_category_by_categories/{category}', 'getSubCategoriesByCategory');
                Route::get('/get_branches', 'getBranches');
                Route::post('/', 'store');
                Route::put('/{inventory}', 'update');
                Route::delete('/{inventory}', 'destroy');
            });


            Route::prefix('inventory_transfers')->controller(InventoryTransferController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::post('/{transfer}/approve', 'approve');
                Route::post('/{transfer}/reject', 'reject');
            });

            Route::prefix('orders')->controller(OrderController::class)->group(function () {
                Route::get('get/{orderType}', 'indexOrders'); // مثال: /orders/tailoring/10
                Route::get('/get_categories', 'getCategories');
                Route::get('/sub-categories/{category}', 'getSubCategoriesByCategory');
                Route::post('create/{orderType}', 'storeOrder'); // مثال: /orders/tailoring
                Route::get('/{uuid}/show', 'show');
                Route::post('/{uuid}/status', 'updateStatus');
            });
        });
    });


    /*
 * |--------------------------------------------------------------------------
 * |Api  Branch  Routes
 * |--------------------------------------------------------------------------
 */
    Route::prefix('employees/')->group(function () {
        Route::middleware(['auth:employee-api'])->group(function () {

            Route::prefix('departments')->controller(DepartmentController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::put('/{department}', 'update');
                Route::delete('/{department}', 'destroy');
            });

            Route::prefix('jobs')->controller(JobController::class)->group(function () {
                Route::get('/get_department', 'getDepartment');
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::put('/{job}', 'update');
                Route::delete('/{job}', 'destroy');
            });


            Route::prefix('categories')->controller(CategoryController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::put('/{category}', 'update');
                Route::delete('/{category}', 'destroy');
            });


            Route::prefix('sub_categories')->controller(SubCategoryController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/get_my_categories', 'indexMyCategories');
                Route::post('/', 'store');
                Route::put('/{subCategory}', 'update');
                Route::delete('/{subCategory}', 'destroy');
            });


            Route::prefix('inventories')->controller(InventoryController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/ge_category', 'getCategories');
                Route::get('/get_sub_category_by_categories/{category}', 'getSubCategoriesByCategory');
                Route::get('/get_branches', 'getBranches');
                Route::post('/', 'store');
                Route::put('/{inventory}', 'update');
                Route::delete('/{inventory}', 'destroy');
            });

            Route::prefix('inventory_transfers')->controller(InventoryTransferController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::post('/{InventoryTransfer:uuid}/approve', 'approve');
                Route::post('/{InventoryTransfer:uuid}/reject', 'reject');
            });

            Route::prefix('orders')->controller(OrderController::class)->group(function () {
                Route::get('get/{orderType}', 'indexOrders'); // مثال: /orders/tailoring/10
                Route::get('/categories', 'getCategories');
                Route::get('/sub-categories/{category}', 'getSubCategoriesByCategory');
                Route::post('/{orderType}', 'storeOrder'); // مثال: /orders/tailoring
                Route::get('/{uuid}/show', 'show');
                Route::post('/{uuid}/status', 'updateStatus');
            });


            Route::prefix('clients')->controller(ClientController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
            });


            Route::prefix('factories/')->controller(FactoryController::class)->group(function () {
                Route::get('orders', 'indexOrder');
                Route::get('orders_details/{uuid}', 'indexDetailsOrder');
                Route::get('acceptOrder/{uuid}', 'acceptOrder');
                Route::post('start_orders/{uuid}', 'startProduction');
                Route::post('update_status_orders/{uuid}', 'updateStatusOrder');
            });

            Route::prefix('workshops')->controller(WorkShopController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/index_details/{uuid}', 'indexDetails');
                Route::post('/accept_order/{uuid}', 'acceptOrder');
                Route::post('/create_invoice/{uuid}', 'createInvoice');
            });
        });
    });
});

<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\Branch\BranchController;
use App\Http\Controllers\Api\BranchManagers\BranchManagerController;
use App\Http\Controllers\Api\Employees\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::controller(AdminController::class)->prefix('cms/admin/activate/{uuid}')->group(function () {
    Route::get('/', 'showVerifyEmail')->name('admin.activate.show');
    Route::post('/', 'verifyEmail')->name('admin.activate.verify');
});


Route::controller(BranchManagerController::class)->prefix('cms/branchManager/activate/{uuid}')->group(function () {
    Route::get('/', 'showVerifyEmail')->name('branchManager.activate.show');
    Route::post('/', 'verifyEmail')->name('branchManager.activate.verify');
});


Route::controller(BranchController::class)->prefix('cms/branch/activate/{uuid}')->group(function () {
    Route::get('/', 'showVerifyEmail')->name('branch.activate.show');
    Route::post('/', 'verifyEmail')->name('branch.activate.verify');
});


Route::controller(EmployeeController::class)->prefix('cms/employees/activate/{uuid}')->group(function () {
    Route::get('/', 'showVerifyEmail')->name('employee.activate.show');
    Route::post('/', 'verifyEmail')->name('employee.activate.verify');
});

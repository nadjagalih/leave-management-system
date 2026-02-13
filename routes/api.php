<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\ProfileController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Generic OAuth Routes
Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectToProvider']);
Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load('role');
    });

    // Route for Employee
    Route::middleware('role:Employee')->group(function () {
        Route::get('/leave-requests', [LeaveRequestController::class, 'indexForEmployee']);
        Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'showForEmployee']);
        Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
        Route::post('/leave-requests/{leaveRequest}/withdraw', [LeaveRequestController::class, 'withdraw']);
    });

    // Profile Management
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);

    // Admin Routes
    Route::middleware('role:Admin')->prefix('admin')->group(function () {
        Route::get('/leave-requests', [LeaveRequestController::class, 'indexForAdmin']);
        Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'showForAdmin']);
        Route::patch('/leave-requests/{leaveRequest}/status', [LeaveRequestController::class, 'updateStatus']);
    });
});

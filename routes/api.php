<?php

use App\Http\Controllers\Api\Apotik\RegistrationController as ApotikRegistrationController;
use App\Http\Controllers\Api\Apotik\ProductController as ApotikProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\Doctor\RegistrationController as DoctorRegistrationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('doctor')->group(function () {
    Route::post('/register', [DoctorRegistrationController::class, 'store']);
    Route::post('/login', [AuthController::class, 'loginDoctor']);
});

Route::prefix('apotik')->group(function () {
    Route::post('/register', [ApotikRegistrationController::class, 'store']);
    Route::post('/login', [AuthController::class, 'loginApotik']);
});

Route::prefix('patient')->group(function () {
    Route::post('/login', [AuthController::class, 'loginPatient']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::get('/products/global', [ProductController::class, 'global']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    Route::prefix('apotik')->group(function () {
        Route::get('/products', [ApotikProductController::class, 'index']);
        Route::post('/products', [ApotikProductController::class, 'store']);
        Route::get('/products/{product}', [ApotikProductController::class, 'show']);
        Route::patch('/products/{product}', [ApotikProductController::class, 'update']);
        Route::patch('/products/{product}/stock', [ApotikProductController::class, 'updateStock']);
        Route::delete('/products/{product}', [ApotikProductController::class, 'destroy']);
    });

    Route::get('/consultations', [ConsultationController::class, 'index']);
    Route::post('/consultations', [ConsultationController::class, 'store']);
    Route::get('/consultations/{consultation}', [ConsultationController::class, 'show']);
    Route::patch('/consultations/{consultation}/status', [ConsultationController::class, 'updateStatus']);
    Route::post('/consultations/{consultation}/messages', [ConsultationController::class, 'addMessage']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show']);
    Route::patch('/shipments/{shipment}/assign-courier', [ShipmentController::class, 'assignCourier']);
    Route::patch('/shipments/{shipment}/status', [ShipmentController::class, 'updateStatus']);
});

<?php

use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{user}', [UserController::class, 'show']);

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::patch('/products/{product}', [ProductController::class, 'update']);
Route::patch('/products/{product}/stock', [ProductController::class, 'updateStock']);

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

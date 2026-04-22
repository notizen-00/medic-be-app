<?php

use App\Http\Controllers\Api\Apotik\RegistrationController as ApotikRegistrationController;
use App\Http\Controllers\Api\Apotik\ProductController as ApotikProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\Doctor\RegistrationController as DoctorRegistrationController;
use App\Http\Controllers\Api\Mitra\RegistrationController as MitraRegistrationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PartnerServiceController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ServiceBookingController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('patient')->controller(AuthController::class)->group(function () {
    Route::post('/login', 'loginPatient');
});

Route::prefix('mitra')->group(function () {
    Route::post('/register', [MitraRegistrationController::class, 'store']);
    Route::post('/login', [AuthController::class, 'loginMitra']);

    Route::prefix('doctor')->controller(AuthController::class)->group(function () {
        Route::post('/register', [DoctorRegistrationController::class, 'store']);
        Route::post('/login', 'loginDoctor');
    });

    Route::prefix('apotik')->controller(AuthController::class)->group(function () {
        Route::post('/login', 'loginApotik');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Shared Authenticated Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('shared')->group(function () {
        Route::controller(AuthController::class)->group(function () {
            Route::get('/me', 'me');
            Route::post('/logout', 'logout');
        });

        Route::prefix('users')->controller(UserController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{user}', 'show');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Patient Mobile App
    |--------------------------------------------------------------------------
    */
    Route::prefix('patient')->group(function () {
        Route::get('/apotiks', [UserController::class, 'apotiks']);

        Route::prefix('services')->controller(ServiceController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{service}', 'show');
        });

        Route::prefix('service-bookings')->controller(ServiceBookingController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{serviceBooking}', 'show');
            Route::patch('/{serviceBooking}/status', 'updateStatus');
        });

        Route::prefix('products')->controller(ProductController::class)->group(function () {
            Route::get('/global', 'global');
            Route::get('/', 'index');
            Route::get('/{product}', 'show');
        });

        Route::prefix('consultations')->controller(ConsultationController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{consultation}', 'show');
            Route::patch('/{consultation}/status', 'updateStatus');
            Route::post('/{consultation}/messages', 'addMessage');
        });

        Route::prefix('orders')->controller(OrderController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{order}', 'show');
            Route::patch('/{order}/status', 'updateStatus');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Healthcare Partner Mobile App
    |--------------------------------------------------------------------------
    */
    Route::prefix('mitra')->group(function () {
        Route::prefix('service-applications')->controller(PartnerServiceController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::patch('/{partnerService}', 'update');
        });

        Route::prefix('service-bookings')->controller(ServiceBookingController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{serviceBooking}', 'show');
            Route::patch('/{serviceBooking}/status', 'updateStatus');
        });

        Route::prefix('apotik')->group(function () {
            Route::post('/register', [ApotikRegistrationController::class, 'store']);

            Route::prefix('products')->controller(ApotikProductController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{product}', 'show');
                Route::patch('/{product}', 'update');
                Route::patch('/{product}/stock', 'updateStock');
                Route::delete('/{product}', 'destroy');
            });
        });

        Route::prefix('shipments')->controller(ShipmentController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{shipment}', 'show');
            Route::patch('/{shipment}/assign-courier', 'assignCourier');
            Route::patch('/{shipment}/status', 'updateStatus');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Internal Admin Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->controller(UserController::class)->group(function () {
        Route::get('/apotiks', 'adminApotiks');
    });

    Route::prefix('admin/services')->controller(ServiceController::class)->group(function () {
        Route::post('/', 'store');
        Route::patch('/{service}', 'update');
    });

    Route::prefix('admin/service-applications')->controller(PartnerServiceController::class)->group(function () {
        Route::patch('/{partnerService}/verify', 'verify');
    });
});

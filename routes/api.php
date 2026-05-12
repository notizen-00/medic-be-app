<?php

use App\Http\Controllers\Api\Admin\BalanceController as AdminBalanceController;
use App\Http\Controllers\Api\Admin\ConsultationsController as AdminConsultationsController;
use App\Http\Controllers\Api\Admin\OrdersController as AdminOrdersController;
use App\Http\Controllers\Api\Admin\PartnerServicesController as AdminPartnerServicesController;
use App\Http\Controllers\Api\Admin\PartnersController as AdminPartnersController;
use App\Http\Controllers\Api\Admin\PatientController as AdminPatientController;
use App\Http\Controllers\Api\Admin\PaymentsController as AdminPaymentsController;
use App\Http\Controllers\Api\Admin\PharmaciesController as AdminPharmaciesController;
use App\Http\Controllers\Api\Admin\RegistrationsController as AdminRegistrationsController;
use App\Http\Controllers\Api\Admin\ServiceBookingsController as AdminServiceBookingsController;
use App\Http\Controllers\Api\Admin\ShipmentsController as AdminShipmentsController;
use App\Http\Controllers\Api\Admin\TransactionsController as AdminTransactionsController;
use App\Http\Controllers\Api\Patient\BalanceController as PatientBalanceController;
use App\Http\Controllers\Api\Apotik\ProductController as ApotikProductController;
use App\Http\Controllers\Api\Auth\AdminAuthController;
use App\Http\Controllers\Api\Auth\ApotikAuthController;
use App\Http\Controllers\Api\Auth\ApotikRegistrationController;
use App\Http\Controllers\Api\Auth\DoctorAuthController;
use App\Http\Controllers\Api\Auth\MitraAuthController;
use App\Http\Controllers\Api\Auth\NurseAuthController;
use App\Http\Controllers\Api\Auth\PatientAuthController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\MidtransCallbackController;
use App\Http\Controllers\Api\NurseController;
use App\Http\Controllers\Api\Patient\ConsultationController as PatientConsultationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PartnerServiceController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ServiceBookingController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Mitra\ProfileController as MitraProfileController;
use App\Http\Controllers\Api\Mitra\ConsultationsController as MitraConsultationsController;
use App\Http\Controllers\Api\Shared\PartnerDocumentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/

Route::prefix('patient')->group(function () {
    Route::post('/register', [PatientAuthController::class, 'register']);
    Route::post('/login', [PatientAuthController::class, 'login']);
});

Route::prefix('mitra')->group(function () {
    Route::post('/register', [MitraAuthController::class, 'register']);
    Route::post('/login', [MitraAuthController::class, 'login']);

    Route::prefix('doctor')->controller(DoctorAuthController::class)->group(function () {
        Route::post('/register', 'register');
        Route::post('/login', 'login');
    });

    Route::prefix('nurse')->controller(NurseAuthController::class)->group(function () {
        Route::post('/register', 'register');
        Route::post('/login', 'login');
    });

    Route::prefix('apotik')->controller(ApotikAuthController::class)->group(function () {
        Route::post('/login', 'login');
    });
});

Route::prefix('admin')->controller(AdminAuthController::class)->group(function () {
    Route::post('/login', 'login');
});

Route::post('/midtrans/callback', MidtransCallbackController::class);

Route::middleware('auth:sanctum')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Shared Authenticated Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('shared')->group(function () {
        Route::controller(SessionController::class)->group(function () {
            Route::get('/me', 'me');
            Route::post('/logout', 'logout');
        });

        Route::prefix('users')->controller(UserController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{user}', 'show');
        });

        Route::get('/secure-image/partners/{user}/documents/{type}', [PartnerDocumentController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | Patient Mobile App
    |--------------------------------------------------------------------------
    */
    Route::prefix('patient')->group(function () {
        Route::post('/logout', [SessionController::class, 'logout']);
        Route::get('/doctors', [DoctorController::class, 'index']);
        Route::get('/nurses', [NurseController::class, 'index']);
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

        Route::prefix('consultations')->group(function () {
            Route::get('/', [PatientConsultationController::class, 'index']);
            Route::post('/', [PatientConsultationController::class, 'store']);
            Route::get('/{consultation}', [PatientConsultationController::class, 'show']);
            Route::patch('/{consultation}/pay', [PatientConsultationController::class, 'pay']);
            Route::patch('/{consultation}/status', [PatientConsultationController::class, 'updateStatus']);
            Route::post('/{consultation}/messages', [PatientConsultationController::class, 'addMessage']);
        });

        Route::prefix('orders')->controller(OrderController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{order}', 'show');
            Route::patch('/{order}/status', 'updateStatus');
        });

        Route::prefix('balance')->group(function () {
            Route::get('/', [PatientBalanceController::class, 'show']);
            Route::get('/history', [PatientBalanceController::class, 'history']);
            Route::post('/topup', [PatientBalanceController::class, 'topup']);
            Route::patch('/topup/confirm', [PatientBalanceController::class, 'confirmTopup']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Healthcare Partner Mobile App
    |--------------------------------------------------------------------------
    */
    Route::prefix('mitra')->group(function () {
        Route::prefix('profile')->controller(MitraProfileController::class)->group(function () {
            Route::get('/', 'show');
            Route::patch('/', 'update');
        });

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

        Route::prefix('consultations')->group(function () {
            Route::get('/', [MitraConsultationsController::class, 'index']);
            Route::get('/{consultation}', [MitraConsultationsController::class, 'show']);
            Route::patch('/{consultation}/status', [MitraConsultationsController::class, 'updateStatus']);
            Route::post('/{consultation}/messages', [MitraConsultationsController::class, 'addMessage']);
        });

        Route::prefix('apotik')->group(function () {
            Route::post('/register', [ApotikRegistrationController::class, 'register']);

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
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::prefix('orders')->controller(AdminOrdersController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{order}', 'show');
        });

        Route::prefix('consultations')->controller(AdminConsultationsController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{consultation}', 'show');
        });

        Route::prefix('patients')->controller(AdminPatientController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{user}', 'show');
        });

        Route::prefix('registrations')->controller(AdminRegistrationsController::class)->group(function () {
            Route::get('/mitra', 'partnerRegistrations');
        });

        Route::controller(AdminPartnersController::class)->group(function () {
            Route::get('/partners', 'index');
            Route::get('/doctors', 'doctors');
            Route::get('/nurses', 'nurses');
            Route::get('/midwives', 'midwives');
            Route::patch('/partners/{user}/verify', 'verify');
        });

        Route::get('/apotiks', [AdminPharmaciesController::class, 'index']);
        Route::get('/payments', [AdminPaymentsController::class, 'index']);
        Route::get('/transactions', [AdminTransactionsController::class, 'index']);
        Route::get('/service-bookings', [AdminServiceBookingsController::class, 'index']);
        Route::get('/partner-services', [AdminPartnerServicesController::class, 'index']);
        Route::get('/shipments', [AdminShipmentsController::class, 'index']);

        // Balance routes untuk admin
        Route::prefix('balance')->group(function () {
            Route::get('/', [AdminBalanceController::class, 'index']);
            Route::get('/transactions', [AdminBalanceController::class, 'allTransactions']);
            Route::get('/users/{user}', [AdminBalanceController::class, 'show']);
            Route::get('/users/{user}/history', [AdminBalanceController::class, 'history']);
            Route::post('/users/{user}/refund', [AdminBalanceController::class, 'refund']);
            Route::post('/users/{user}/adjust', [AdminBalanceController::class, 'adjust']);
        });
    });

    Route::prefix('admin/services')->controller(ServiceController::class)->group(function () {
        Route::post('/', 'store');
        Route::patch('/{service}', 'update');
    });

    Route::prefix('admin/service-applications')->controller(PartnerServiceController::class)->group(function () {
        Route::patch('/{partnerService}/verify', 'verify');
    });
});

<?php

use App\Http\Controllers\Api\Admin\BalanceController as AdminBalanceController;
use App\Http\Controllers\Api\Admin\ConsultationsController as AdminConsultationsController;
use App\Http\Controllers\Api\Admin\OrdersController as AdminOrdersController;
use App\Http\Controllers\Api\Admin\PartnerServicesController as AdminPartnerServicesController;
use App\Http\Controllers\Api\Admin\PartnersController as AdminPartnersController;
use App\Http\Controllers\Api\Admin\PatientController as AdminPatientController;
use App\Http\Controllers\Api\Admin\PaymentsController as AdminPaymentsController;
use App\Http\Controllers\Api\Admin\PharmaciesController as AdminPharmaciesController;
use App\Http\Controllers\Api\Admin\PromoCodeController as AdminPromoCodeController;
use App\Http\Controllers\Api\Admin\ProductCatalogController as AdminProductCatalogController;
use App\Http\Controllers\Api\Admin\RegistrationsController as AdminRegistrationsController;
use App\Http\Controllers\Api\Admin\ReportsController as AdminReportsController;
use App\Http\Controllers\Api\Admin\ServiceBookingsController as AdminServiceBookingsController;
use App\Http\Controllers\Api\Admin\ServiceBookingFeeSettingController as AdminServiceBookingFeeSettingController;
use App\Http\Controllers\Api\Admin\ServiceCategoriesController as AdminServiceCategoriesController;
use App\Http\Controllers\Api\Admin\ServiceMarkupController as AdminServiceMarkupController;
use App\Http\Controllers\Api\Admin\ServicesController as AdminServicesController;
use App\Http\Controllers\Api\Admin\ShipmentsController as AdminShipmentsController;
use App\Http\Controllers\Api\Admin\TransactionsController as AdminTransactionsController;
use App\Http\Controllers\Api\Admin\JournalsController as AdminJournalsController;
use App\Http\Controllers\Api\Patient\BalanceController as PatientBalanceController;
use App\Http\Controllers\Api\Patient\PatientMemberController;
use App\Http\Controllers\Api\Patient\ServiceBookingController as PatientServiceBookingController;
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
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Mitra\ProfileController as MitraProfileController;
use App\Http\Controllers\Api\Mitra\ConsultationsController as MitraConsultationsController;
use App\Http\Controllers\Api\Mitra\ServiceBookingController as MitraServiceBookingController;
use App\Http\Controllers\Api\Mitra\BalanceController as MitraBalanceController;
use App\Http\Controllers\Api\Shared\PartnerDocumentController;
use App\Http\Controllers\Api\Shared\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
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
    Route::post('/broadcasting/auth', function (Request $request) {
        return Broadcast::auth($request);
    });

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

        Route::prefix('notifications')->controller(NotificationController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/unread-count', 'unreadCount');
            Route::patch('/read-all', 'markAllAsRead');
            Route::patch('/{notification}/read', 'markAsRead');
            Route::delete('/{notification}', 'destroy');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Patient Mobile App
    |--------------------------------------------------------------------------
    */
    Route::prefix('patient')->middleware('role:pasien')->group(function () {
        Route::post('/logout', [SessionController::class, 'logout']);
        Route::get('/doctors', [DoctorController::class, 'index']);
        Route::get('/nurses', [NurseController::class, 'index']);
        Route::get('/apotiks', [UserController::class, 'apotiks']);

        Route::prefix('members')->controller(PatientMemberController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{patientMember}', 'show');
            Route::patch('/{patientMember}', 'update');
            Route::patch('/{patientMember}/primary', 'setPrimary');
            Route::delete('/{patientMember}', 'destroy');
        });

        Route::prefix('services')->controller(ServiceController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{service}', 'show');
        });

        // Service booking dengan discount, markup, pembayaran, dan catalog layanan.
        // Route statis harus berada sebelum /{serviceBooking} agar "services" tidak dianggap ID booking.
        Route::prefix('service-bookings')->group(function () {
            Route::controller(PatientServiceBookingController::class)->group(function () {
                Route::get('/services', 'index');
                Route::get('/services/{service}', 'show');
                Route::post('/check-promo-code', 'checkPromoCode');
                Route::post('/', 'store');
                Route::get('/', 'indexBookings');
                Route::patch('/{serviceBooking}/pay', 'pay');
                Route::patch('/{serviceBooking}/cancel', 'cancel');
                Route::patch('/{serviceBooking}/confirm-completion', 'confirmCompletion');
                Route::get('/{serviceBooking}/tracking', 'tracking');
                Route::get('/{serviceBooking}', 'showBooking');
            });
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

        // Promo codes yang tersedia untuk patient
        Route::prefix('promo-codes')->group(function () {
            Route::get('/available', [AdminPromoCodeController::class, 'availableCodes']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Healthcare Partner Mobile App
    |--------------------------------------------------------------------------
    */
    Route::prefix('mitra')->middleware('role:mitra')->group(function () {
        Route::prefix('profile')->controller(MitraProfileController::class)->group(function () {
            Route::get('/', 'show');
            Route::patch('/', 'update');
            Route::patch('/availability', 'toggleAvailability');
        });

        Route::prefix('service-applications')->controller(PartnerServiceController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::patch('/{partnerService}', 'update');
        });

        Route::prefix('service-bookings')->controller(MitraServiceBookingController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{serviceBooking}', 'show');
            Route::patch('/{serviceBooking}/accept', 'accept');
            Route::patch('/{serviceBooking}/reject', 'reject');
            Route::patch('/{serviceBooking}/start-journey', 'startJourney');
            Route::patch('/{serviceBooking}/location', 'updateLocation');
            Route::post('/{serviceBooking}/histories', 'addTreatmentHistory');
            Route::patch('/{serviceBooking}/complete', 'complete');
            Route::patch('/{serviceBooking}/status', 'updateStatus');
        });

        Route::prefix('balance')->group(function () {
            Route::get('/', [MitraBalanceController::class, 'show']);
            Route::get('/history', [MitraBalanceController::class, 'history']);
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
        Route::patch('/products/{product}/admin-price', [AdminProductCatalogController::class, 'updateAdminPrice']);
        Route::get('/service-bookings', [AdminServiceBookingsController::class, 'index']);
        Route::get('/service-booking-fees', [AdminServiceBookingFeeSettingController::class, 'show']);
        Route::put('/service-booking-fees', [AdminServiceBookingFeeSettingController::class, 'update']);
        Route::get('/partner-services', [AdminPartnerServicesController::class, 'index']);
        Route::get('/shipments', [AdminShipmentsController::class, 'index']);

        Route::prefix('service-categories')->controller(AdminServiceCategoriesController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{serviceCategory}', 'show');
            Route::patch('/{serviceCategory}', 'update');
            Route::delete('/{serviceCategory}', 'destroy');
        });

        Route::prefix('services')->controller(AdminServicesController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{service}', 'show');
            // Gunakan POST untuk update multipart agar PHP mengisi UploadedFile dengan benar.
            Route::post('/{service}', 'update');
            Route::patch('/{service}', 'update');
            Route::delete('/{service}', 'destroy');
        });

        // Balance routes untuk admin
        Route::prefix('balance')->group(function () {
            Route::get('/', [AdminBalanceController::class, 'index']);
            Route::get('/transactions', [AdminBalanceController::class, 'allTransactions']);
            Route::get('/users/{user}', [AdminBalanceController::class, 'show']);
            Route::get('/users/{user}/history', [AdminBalanceController::class, 'history']);
            Route::post('/users/{user}/refund', [AdminBalanceController::class, 'refund']);
            Route::post('/users/{user}/adjust', [AdminBalanceController::class, 'adjust']);
        });

        // Service markup settings untuk admin
        Route::prefix('service-markup')->controller(AdminServiceMarkupController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{serviceMarkupSetting}', 'show');
            Route::post('/', 'store');
            Route::patch('/{serviceMarkupSetting}', 'update');
            Route::delete('/{serviceMarkupSetting}', 'destroy');
            Route::patch('/{serviceMarkupSetting}/toggle-status', 'toggleStatus');
        });

        // Promo codes untuk admin
        Route::prefix('promo-codes')->controller(AdminPromoCodeController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{promoCode}', 'show');
            Route::post('/', 'store');
            Route::patch('/{promoCode}', 'update');
            Route::delete('/{promoCode}', 'destroy');
            Route::patch('/{promoCode}/toggle-status', 'toggleStatus');
        });

        // Reports untuk admin
        Route::prefix('reports')->controller(AdminReportsController::class)->group(function () {
            Route::get('/orders', 'orders');
            Route::get('/customers', 'customers');
            Route::get('/profit-loss', 'profitLoss');
        });

        // Journals (operasional keuangan) untuk admin
        Route::prefix('journals')->controller(AdminJournalsController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{journalEntry}', 'show');
            Route::post('/{journalEntry}/post', 'post');
        });
    });

    Route::prefix('admin/service-applications')->controller(PartnerServiceController::class)->group(function () {
        Route::patch('/{partnerService}/verify', 'verify');
    });
});

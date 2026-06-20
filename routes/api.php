<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerMarketerController;
use App\Http\Controllers\Api\CustomerMarketerDashboardController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\FinancialController;
use App\Http\Controllers\Api\InstitutionController;
use App\Http\Controllers\Api\InstitutionMarketerController;
use App\Http\Controllers\Api\InstitutionMarketerDashboardController;
use App\Http\Controllers\Api\InstitutionTypeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\ActivityLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ============================================================================
// SECTION 1: Public Routes (No Authentication Required)
// ============================================================================

// 1.1 Auth Routes
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
});

// 1.2 Public Endpoints
Route::get('/customer/qr/{membership_number}', [CustomerController::class, 'generateQR'])->name('customer.qr');
Route::get('/institutions/nearby', [InstitutionController::class, 'nearby'])->name('institutions.nearby');
Route::get('/institutions/types', [InstitutionTypeController::class, 'index'])->name('institution-types.index');
Route::get('/institutions/{institution}', [InstitutionController::class, 'show'])->name('institutions.show');
Route::get('/customer/verify/{membership_number}', [VerificationController::class, 'checkMembership'])->name('customer.verify');

// ============================================================================
// SECTION 2: Protected Routes (Authentication Required)
// ============================================================================

Route::middleware(['auth:sanctum', 'check.status'])->group(function () {
    
    // ------------------------------------------------------------------------
    // 2.1 Authentication Routes
    // ------------------------------------------------------------------------
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/change-password', [AuthController::class, 'changePassword'])->name('change-password');
        Route::post('/refresh-token', [AuthController::class, 'refreshToken'])->name('refresh-token');
    });
    
    // ------------------------------------------------------------------------
    // 2.2 Profile Routes (All Authenticated Users)
    // ------------------------------------------------------------------------
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/me', [ProfileController::class, 'getUserProfile'])->name('me');
        Route::get('/customer', [ProfileController::class, 'getCustomerProfile'])->name('customer');
        Route::put('/update', [ProfileController::class, 'updateProfile'])->name('update');
        Route::put('/change-password', [ProfileController::class, 'changePassword'])->name('change-password');
        Route::post('/fingerprint', [ProfileController::class, 'storeFingerprint'])->name('fingerprint.store');
        Route::delete('/fingerprint', [ProfileController::class, 'deleteFingerprint'])->name('fingerprint.delete');
        Route::get('/stats', [ProfileController::class, 'getCustomerStats'])->name('stats');
    });
    
    // ------------------------------------------------------------------------
    // 2.3 File Upload Routes
    // ------------------------------------------------------------------------
    Route::prefix('upload')->name('upload.')->group(function () {
        Route::post('/identity', [FileUploadController::class, 'uploadIdentityImage'])->name('identity');
        Route::post('/personal', [FileUploadController::class, 'uploadPersonalImage'])->name('personal');
        Route::post('/contract', [FileUploadController::class, 'uploadContract'])->name('contract');
    });
    
    // ------------------------------------------------------------------------
    // 2.4 Notification Routes
    // ------------------------------------------------------------------------
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/institution', [NotificationController::class, 'institutionNotifications'])->name('institution');
        Route::get('/verification', [NotificationController::class, 'verificationNotifications'])->name('verification');
        Route::get('/by-type/{type}', [NotificationController::class, 'getByType'])->name('by-type');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::get('/unread-count/by-type/{type}', [NotificationController::class, 'unreadCountByType'])->name('unread-count-by-type');
        Route::get('/user/{userId}', [NotificationController::class, 'getUserNotifications'])->name('user');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('mark-as-read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('mark-all-as-read');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
        Route::delete('/clear-all', [NotificationController::class, 'clearAll'])->name('clear-all');
        Route::post('/send', [NotificationController::class, 'send'])->name('send');
        Route::post('/send-to-customer', [NotificationController::class, 'sendToCustomer'])->name('send-to-customer');
        Route::get('/types', [NotificationController::class, 'types'])->name('types');
    });
    
    // ------------------------------------------------------------------------
    // 2.5 Admin Only Routes
    // ------------------------------------------------------------------------
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        
        // Institution Types Management
        Route::apiResource('institution-types', InstitutionTypeController::class)->names('institution-types');
        Route::post('/institution-types/{institution_type}/toggle', [InstitutionTypeController::class, 'toggleStatus'])->name('institution-types.toggle');
        
        // Activity Logs
        Route::prefix('activity-logs')->name('activity-logs.')->group(function () {
            Route::get('/', [ActivityLogController::class, 'index'])->name('index');
            Route::get('/user/{user}', [ActivityLogController::class, 'getUserActivities'])->name('user');
            Route::get('/module/{module}', [ActivityLogController::class, 'getModuleActivities'])->name('module');
            Route::delete('/old', [ActivityLogController::class, 'deleteOldLogs'])->name('delete-old');
        });
        
        // Admin Dashboard
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('/', [AdminDashboardController::class, 'index'])->name('index');
            Route::get('/recent-activities', [AdminDashboardController::class, 'recentActivities'])->name('recent-activities');
            Route::get('/top-marketers', [AdminDashboardController::class, 'topMarketers'])->name('top-marketers');
            Route::get('/new-institutions', [AdminDashboardController::class, 'newInstitutions'])->name('new-institutions');
            Route::get('/monthly-stats', [AdminDashboardController::class, 'monthlyStats'])->name('monthly-stats');
            Route::get('/stats-summary', [AdminDashboardController::class, 'statsSummary'])->name('stats-summary');
        });
        
        // Institution Marketers Management
        Route::prefix('institution-marketers')->name('institution-marketers.')->group(function () {
            Route::get('/', [InstitutionMarketerController::class, 'index'])->name('index');
            Route::post('/', [InstitutionMarketerController::class, 'store'])->name('store');
            Route::get('/{id}', [InstitutionMarketerController::class, 'show'])->name('show');
            Route::put('/{id}', [InstitutionMarketerController::class, 'update'])->name('update');
            Route::patch('/{id}', [InstitutionMarketerController::class, 'update'])->name('update-patch');
            Route::delete('/{id}', [InstitutionMarketerController::class, 'destroy'])->name('destroy');
            Route::put('/{id}/status', [InstitutionMarketerController::class, 'updateStatus'])->name('update-status');
            Route::get('/stats', [InstitutionMarketerController::class, 'stats'])->name('stats');
        });
        
        // Customer Marketers Management
        Route::prefix('customer-marketers')->name('customer-marketers.')->group(function () {
            Route::get('/', [CustomerMarketerController::class, 'index'])->name('index');
            Route::post('/', [CustomerMarketerController::class, 'store'])->name('store');
            Route::get('/{id}', [CustomerMarketerController::class, 'show'])->name('show');
            Route::put('/{id}', [CustomerMarketerController::class, 'update'])->name('update');
            Route::patch('/{id}', [CustomerMarketerController::class, 'update'])->name('update-patch');
            Route::delete('/{id}', [CustomerMarketerController::class, 'destroy'])->name('destroy');
            Route::put('/{id}/status', [CustomerMarketerController::class, 'updateStatus'])->name('update-status');
            Route::get('/stats', [CustomerMarketerController::class, 'stats'])->name('stats');
        });
        
        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/revenue', [ReportController::class, 'revenueReport'])->name('revenue');
            Route::get('/customers', [ReportController::class, 'customersReport'])->name('customers');
            Route::get('/institutions', [ReportController::class, 'institutionsReport'])->name('institutions');
            Route::get('/commissions', [ReportController::class, 'commissionsReport'])->name('commissions');
            Route::get('/top-performers', [ReportController::class, 'topPerformers'])->name('top-performers');
            Route::get('/chart-data', [ReportController::class, 'chartData'])->name('chart-data');
            Route::get('/export', [ReportController::class, 'exportReport'])->name('export');
        });
        
        // Financial Routes
        Route::prefix('financial')->name('financial.')->group(function () {
            Route::get('/stats', [FinancialController::class, 'stats'])->name('stats');
            Route::get('/chart-data', [FinancialController::class, 'chartData'])->name('chart-data');
            Route::get('/revenue-distribution', [FinancialController::class, 'revenueDistribution'])->name('revenue-distribution');
            Route::get('/top-marketers', [FinancialController::class, 'topMarketers'])->name('top-marketers');
            Route::get('/recent-transactions', [FinancialController::class, 'recentTransactions'])->name('recent-transactions');
            Route::get('/summary', [FinancialController::class, 'summary'])->name('summary');
            Route::get('/commission-breakdown', [FinancialController::class, 'commissionBreakdown'])->name('commission-breakdown');
        });
    });
    
    // ------------------------------------------------------------------------
    // 2.6 Customer Marketer Routes (Customer Marketer & Admin)
    // ------------------------------------------------------------------------
    Route::middleware(['role:admin,customer_marketer'])->prefix('customer-marketers')->name('customer-marketers.')->group(function () {
        
        // Customer Management
        Route::prefix('customers')->name('customers.')->group(function () {
            Route::get('/', [CustomerMarketerController::class, 'getCustomers'])->name('index');
            Route::post('/', [CustomerController::class, 'store'])->name('store');
            Route::get('/{id}', [CustomerMarketerController::class, 'getCustomer'])->name('show');
            Route::put('/{id}', [CustomerMarketerController::class, 'updateCustomer'])->name('update');
            Route::delete('/{id}', [CustomerMarketerController::class, 'deleteCustomer'])->name('destroy');
        });
        
        // Marketer Commissions
        Route::get('/{id}/commissions', [CustomerMarketerController::class, 'getMarketerCommissions'])->name('commissions');
        
        // Current Marketer Info
        Route::get('/me', [CustomerMarketerController::class, 'me'])->name('me');
        
        // Dashboard Stats
        Route::get('/dashboard-stats', [CustomerMarketerController::class, 'dashboardStats'])->name('dashboard-stats');
    });
    
    // ------------------------------------------------------------------------
    // 2.7 Institution Marketer Routes (Institution Marketer & Admin)
    // ------------------------------------------------------------------------
    Route::middleware(['role:admin,institution_marketer'])->prefix('institution-marketers')->name('institution-marketers.')->group(function () {
        
        // Institution Management for Marketers
        Route::prefix('institutions')->name('institutions.')->group(function () {
            Route::get('/', [InstitutionMarketerController::class, 'getInstitutions'])->name('index');
            Route::post('/', [InstitutionMarketerController::class, 'storeInstitution'])->name('store');
            Route::get('/{id}', [InstitutionMarketerController::class, 'getInstitution'])->name('show');
            Route::put('/{id}', [InstitutionMarketerController::class, 'updateInstitution'])->name('update');
            Route::delete('/{id}', [InstitutionMarketerController::class, 'deleteInstitution'])->name('destroy');
        });
        
        // Marketer Commissions
        Route::get('/{id}/commissions', [InstitutionMarketerController::class, 'getMarketerCommissions'])->name('commissions');
        
        // Current Marketer Info
        Route::get('/me', [InstitutionMarketerController::class, 'me'])->name('me');
        
        // Dashboard Stats
        Route::get('/dashboard-stats', [InstitutionMarketerController::class, 'dashboardStats'])->name('dashboard-stats');
    });
    
    // ------------------------------------------------------------------------
    // 2.8 Institution Owner Routes
    // ------------------------------------------------------------------------
    Route::middleware(['role:admin,institution_owner'])->prefix('institution-owner')->name('institution-owner.')->group(function () {
        
        // My Institution
        Route::get('/my-institution', [InstitutionController::class, 'myInstitution'])->name('my-institution');
        
        // Verification Routes
        Route::post('/verify-by-phone', [VerificationController::class, 'verifyByPhone'])->name('verify-by-phone');
        Route::post('/verify-fingerprint', [VerificationController::class, 'verifyFingerprint'])->name('verify-fingerprint');
        Route::post('/verify-customer', [VerificationController::class, 'verifyCustomer'])->name('verify-customer');
        Route::post('/verify-by-qr', [VerificationController::class, 'verifyByQR'])->name('verify-by-qr');
        Route::post('/discounts/approve', [VerificationController::class, 'approveDiscount'])->name('discounts.approve');
        Route::get('/customer/{id}', [VerificationController::class, 'getCustomerDetails'])->name('customer.details');
    });
    
    // ------------------------------------------------------------------------
    // 2.9 Customer Routes (Customer Role Only)
    // ------------------------------------------------------------------------
    Route::middleware(['role:customer'])->prefix('customer')->name('customer.')->group(function () {
        
        // Profile
        Route::get('/me', [CustomerController::class, 'me'])->name('me');
        
        // Profile Management
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [CustomerController::class, 'profile'])->name('show');
            Route::put('/', [CustomerController::class, 'updateProfile'])->name('update');
        });
        
        // Membership
        Route::prefix('membership')->name('membership.')->group(function () {
            Route::get('/', [CustomerController::class, 'membershipInfo'])->name('info');
            Route::get('/qr-code', [CustomerController::class, 'getQRCode'])->name('qr-code');
        });
        
        // Transactions
        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::get('/', [CustomerController::class, 'customerTransactions'])->name('index');
            Route::get('/{transaction}', [CustomerController::class, 'transactionDetails'])->name('show');
        });
        
        // Savings Summary
        Route::get('/savings-summary', [CustomerController::class, 'savingsSummary'])->name('savings-summary');
        
        // Fingerprint
        Route::prefix('fingerprint')->name('fingerprint.')->group(function () {
            Route::post('/', [CustomerController::class, 'storeFingerprint'])->name('store');
            Route::delete('/', [CustomerController::class, 'deleteFingerprint'])->name('delete');
        });
        
        // View Institutions
        Route::get('/institutions/nearby', [InstitutionController::class, 'nearby'])->name('institutions.nearby');
        Route::get('/institutions/{institution}/discount', [InstitutionController::class, 'institutionDiscount'])->name('institutions.discount');
        Route::get('/institutions', [InstitutionController::class, 'index'])->name('institutions.index');
    });
    
    // ------------------------------------------------------------------------
    // 2.10 Institution Routes (All Authenticated Users)
    // ------------------------------------------------------------------------
    Route::get('/institutions', [InstitutionController::class, 'index'])->name('institutions.index');
    Route::post('/institutions', [InstitutionController::class, 'store'])->name('institutions.store');
    Route::get('/institutions/{institution}', [InstitutionController::class, 'show'])->name('institutions.show');
    Route::put('/institutions/{institution}', [InstitutionController::class, 'update'])->name('institutions.update');
    Route::delete('/institutions/{institution}', [InstitutionController::class, 'destroy'])->name('institutions.destroy');
    Route::post('/institutions/{institution}/renew-agreement', [InstitutionController::class, 'renewAgreement'])->name('institutions.renew-agreement');
    Route::post('/institutions/{institution}/update-discount', [InstitutionController::class, 'updateDiscount'])->name('institutions.update-discount');
    Route::get('/institutions/stats', [InstitutionController::class, 'stats'])->name('institutions.stats');
    Route::post('/institutions/{institution}/add-owner', [InstitutionController::class, 'addOwner'])->name('institutions.add-owner');
    Route::delete('/institutions/{institution}/remove-owner/{user}', [InstitutionController::class, 'removeOwner'])->name('institutions.remove-owner');
    
    // ------------------------------------------------------------------------
    // 2.11 Customer Routes (All Authenticated Users)
    // ------------------------------------------------------------------------
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/stats', [CustomerController::class, 'stats'])->name('stats');
        Route::get('/{id}', [CustomerController::class, 'show'])->name('show');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::put('/{id}', [CustomerController::class, 'update'])->name('update');
        Route::delete('/{id}', [CustomerController::class, 'destroy'])->name('destroy');
        Route::put('/{id}/status', [CustomerController::class, 'updateStatus'])->name('update-status');
        Route::post('/{id}/renew', [CustomerController::class, 'renewMembership'])->name('renew');
        Route::get('/{id}/transactions', [CustomerController::class, 'transactions'])->name('transactions');
        Route::delete('/{id}/fingerprint', [CustomerController::class, 'deleteFingerprint'])->name('delete-fingerprint');
        Route::get('/export/excel', [CustomerController::class, 'exportExcel'])->name('export');
        Route::get('/me', [ProfileController::class, 'getCustomerProfile'])->name('me');
    });
    
    // ------------------------------------------------------------------------
    // 2.12 Marketer Dashboard Routes
    // ------------------------------------------------------------------------
    Route::middleware(['role:admin,customer_marketer,institution_marketer'])->prefix('marketer')->name('marketer.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'marketerDashboard'])->name('dashboard');
        Route::get('/statistics', [DashboardController::class, 'marketerStatistics'])->name('statistics');
    });
    
    // ------------------------------------------------------------------------
    // 2.13 Commissions Routes (All Authenticated Users)
    // ------------------------------------------------------------------------
    Route::prefix('commissions')->name('commissions.')->group(function () {
        Route::get('/', [CommissionController::class, 'index'])->name('index');
        Route::get('/{id}', [CommissionController::class, 'show'])->name('show');
        Route::put('/{id}/pay', [CommissionController::class, 'pay'])->name('pay');
        Route::get('/stats', [CommissionController::class, 'stats'])->name('stats');
        Route::get('/revenue-transactions', [CommissionController::class, 'revenueTransactions'])->name('revenue-transactions');
        Route::get('/monthly-report', [CommissionController::class, 'monthlyReport'])->name('monthly-report');
        Route::get('/marketer/{id}/stats', [CommissionController::class, 'marketerStats'])->name('marketer-stats');
        Route::get('/marketers/top', [CommissionController::class, 'topMarketers'])->name('top-marketers');
    });
    
    // ------------------------------------------------------------------------
    // 2.14 Common Authenticated Routes (All Roles)
    // ------------------------------------------------------------------------
    Route::get('/statistics/summary', [DashboardController::class, 'summary'])->name('statistics.summary');
});

// ============================================================================
// SECTION 3: Webhook Routes (No CSRF Protection)
// ============================================================================

// Route::prefix('webhooks')->name('webhooks.')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])->group(function () {
//     Route::post('/payment/callback', [WebhookController::class, 'paymentCallback'])->name('payment');
//     Route::post('/sms/callback', [WebhookController::class, 'smsCallback'])->name('sms');
// });


// ============================================================
// Customer Marketer Dashboard Routes
// ============================================================
Route::middleware(['auth:sanctum', 'check.status'])->prefix('customer-marketer/dashboard')->name('customer-marketer.dashboard.')->group(function () {
    
    // Dashboard endpoints
    Route::get('/stats', [CustomerMarketerDashboardController::class, 'dashboardStats'])->name('stats');
    Route::get('/me', [CustomerMarketerDashboardController::class, 'me'])->name('me');
    Route::get('/summary', [CustomerMarketerDashboardController::class, 'getSummary'])->name('summary');
    Route::get('/recent-activities', [CustomerMarketerDashboardController::class, 'getRecentActivities'])->name('recent-activities');
    
    // Customer management
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerMarketerDashboardController::class, 'getCustomers'])->name('index');
        Route::get('/{id}', [CustomerMarketerDashboardController::class, 'getCustomer'])->name('show');
        Route::put('/{id}', [CustomerMarketerDashboardController::class, 'updateCustomer'])->name('update');
        Route::delete('/{id}', [CustomerMarketerDashboardController::class, 'deleteCustomer'])->name('destroy');
    });
    
    // Commission management
    Route::prefix('commissions')->name('commissions.')->group(function () {
        Route::get('/', [CustomerMarketerDashboardController::class, 'getCommissions'])->name('index');
        Route::get('/stats', [CustomerMarketerDashboardController::class, 'getCommissionStats'])->name('stats');
    });
});

// ============================================================
// Institution Marketer Dashboard Routes
// ============================================================
Route::middleware(['auth:sanctum', 'check.status'])->prefix('institution-marketer/dashboard')->name('institution-marketer.dashboard.')->group(function () {
    
    // Dashboard endpoints
    Route::get('/stats', [InstitutionMarketerDashboardController::class, 'dashboardStats'])->name('stats');
    Route::get('/me', [InstitutionMarketerDashboardController::class, 'me'])->name('me');
    Route::get('/summary', [InstitutionMarketerDashboardController::class, 'getSummary'])->name('summary');
    Route::get('/chart-data', [InstitutionMarketerDashboardController::class, 'getChartData'])->name('chart-data');
    
    // Institution management
    Route::get('/institutions', [InstitutionMarketerDashboardController::class, 'getInstitutions'])->name('institutions');
    
    // Commission management
    Route::get('/commission-stats', [InstitutionMarketerDashboardController::class, 'getCommissionStats'])->name('commission-stats');
});
// ============================================================================
// SECTION 4: Fallback Route for 404 Not Found
// ============================================================================

Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Route not found',
        'errors' => ['The requested route does not exist']
    ], 404);
})->name('fallback');
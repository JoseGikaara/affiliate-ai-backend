<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GenerateController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\CreditTransactionController;
use App\Http\Controllers\Api\TopUpController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AffiliateController;
use App\Http\Controllers\Api\ConversionController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\AdminPayoutController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\LandingPageController;
use App\Http\Controllers\Api\AffiliateNetworkController;
use App\Http\Controllers\Api\AdminNetworkController;
use App\Http\Controllers\Api\CreditPackageController;
use App\Http\Controllers\Api\CpaLockerController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TrainingController;
use App\Http\Controllers\Api\AdminTrainingModuleController;

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toIso8601String(),
        'environment' => app()->environment(),
    ]);
});

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);
Route::post('/password/reset/confirm', [AuthController::class, 'confirmPasswordReset']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'credits' => $user->credits,
            'free_credits' => $user->free_credits ?? 0,
            'is_admin' => $user->is_admin,
            'status' => $user->status ?? 'active',
        ]);
    });

    Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);

    Route::post('/logout', [AuthController::class, 'logout']);

    // Projects
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    // Credit Transactions
    Route::get('/credit_transactions', [CreditTransactionController::class, 'index']);

    // Credit Packages
    Route::get('/credit-packages', [CreditPackageController::class, 'index']);

    // Top-ups
    Route::get('/topups', [TopUpController::class, 'index']);
    Route::post('/topups', [TopUpController::class, 'store']);
    Route::post('/topups/{topup}/approve', [TopUpController::class, 'approve']);
    Route::post('/topups/{topup}/reject', [TopUpController::class, 'reject']);

    // Payments
    Route::post('/payments/mpesa', [PaymentController::class, 'mpesa']);

    // AI Generation
    Route::post('/generate', [GenerateController::class, 'generate']);

    // Offers (public for authenticated users)
    Route::get('/offers', [AffiliateController::class, 'getOffers']);
    
    // Affiliate routes
    Route::get('/my-affiliate', [AffiliateController::class, 'getMyAffiliate']);
    Route::get('/my-affiliate/dashboard', [AffiliateController::class, 'getMyDashboardStats']);
    Route::prefix('affiliates')->group(function () {
        Route::post('/{id}/generate-link', [AffiliateController::class, 'generateLink']);
        Route::get('/{id}/links', [AffiliateController::class, 'getLinks']);
        Route::get('/{id}/dashboard', [AffiliateController::class, 'getDashboardStats']);
    });

    // Conversion tracking (public, no auth required for clicks)
    Route::post('/conversion', [ConversionController::class, 'track']);
    Route::get('/track/{trackingId}', [ConversionController::class, 'trackClick']);

    // Payout routes (affiliate)
    Route::prefix('payouts')->group(function () {
        Route::get('/balance', [PayoutController::class, 'getBalance']);
        Route::get('/requests', [PayoutController::class, 'index']);
        Route::post('/requests', [PayoutController::class, 'store']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{notificationId}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{notificationId}', [NotificationController::class, 'destroy']);
    });

    // Affiliate Networks
    Route::prefix('networks')->group(function () {
        Route::get('/', [AffiliateNetworkController::class, 'index']);
        Route::get('/{network}', [AffiliateNetworkController::class, 'show']);
        Route::post('/{network}/learn-more', [AffiliateNetworkController::class, 'learnMore']);
    });

    // CPA Lockers
    Route::prefix('cpa')->group(function () {
        Route::get('/lockers', [CpaLockerController::class, 'index']);
        Route::post('/unlock/{locker}', [CpaLockerController::class, 'unlock']);
        Route::get('/download/{locker}', [CpaLockerController::class, 'download']);
    });

    // Trainings
    Route::prefix('trainings')->group(function () {
        Route::get('/', [TrainingController::class, 'index']);
        Route::get('/{id}', [TrainingController::class, 'show']);
        Route::post('/unlock/{id}', [TrainingController::class, 'unlock']);
        Route::post('/unlock', [TrainingController::class, 'unlockByBody']);
        Route::post('/complete', [TrainingController::class, 'complete']);
    });

    // Landing Pages
    Route::prefix('landing-pages')->group(function () {
        Route::get('/', [LandingPageController::class, 'index']);
        Route::post('/', [LandingPageController::class, 'store']);
        Route::post('/generate', [LandingPageController::class, 'generateLandingPage'])->middleware('rate_limit_ai');
        Route::get('/networks', function () {
            return response()->json([
                'networks' => \App\Models\LandingPage::getSupportedNetworks(),
                'categories' => \App\Models\LandingPage::getNetworksByCategory(),
            ]);
        });
        Route::get('/networks/{network}/pricing', function (string $network) {
            $pricing = \App\Models\LandingPage::getNetworkPricing($network);
            return response()->json($pricing);
        });
        Route::get('/{landingPage}', [LandingPageController::class, 'show']);
        Route::put('/{landingPage}', [LandingPageController::class, 'update']);
        Route::delete('/{landingPage}', [LandingPageController::class, 'destroy']);
        Route::post('/{landingPage}/publish', [LandingPageController::class, 'publish']);
        Route::post('/{landingPage}/unpublish', [LandingPageController::class, 'unpublish']);
        Route::post('/{landingPage}/renew', [LandingPageController::class, 'renew']);
        Route::post('/{landingPage}/toggle-auto-renew', [LandingPageController::class, 'toggleAutoRenew']);
        Route::get('/{landingPage}/analytics', [LandingPageController::class, 'showAnalytics']);
        Route::get('/{landingPage}/assets', [LandingPageController::class, 'downloadAssets']);
        Route::get('/leaderboard', [LandingPageController::class, 'leaderboard']);
    });

    // Public tracking endpoints (no auth required)
    Route::post('/landing-pages/{landingPage}/track-view', [LandingPageController::class, 'trackView']);
    Route::post('/landing-pages/{landingPage}/track-conversion', [LandingPageController::class, 'trackConversion']);

    // Admin routes (protected with admin middleware)
    Route::prefix('admin')->middleware(['admin'])->group(function () {
        // User Management
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
        Route::post('/users/{user}/credits', [AdminController::class, 'updateCredits']);

        // Affiliate Management
        Route::get('/affiliates', [AdminController::class, 'affiliates']);
        Route::put('/affiliates/{affiliate}', [AdminController::class, 'updateAffiliate']);
        Route::patch('/affiliates/{affiliate}/status', [AdminController::class, 'updateAffiliateStatus']);
        Route::delete('/affiliates/{affiliate}', [AdminController::class, 'deleteAffiliate']);

        // Offer Management
        Route::get('/offers', [AdminController::class, 'offers']);
        Route::post('/offers', [AdminController::class, 'createOffer']);
        Route::put('/offers/{offer}', [AdminController::class, 'updateOffer']);
        Route::patch('/offers/{offer}/status', [AdminController::class, 'updateOfferStatus']);
        Route::delete('/offers/{offer}', [AdminController::class, 'deleteOffer']);

        // Payout Management (legacy - keeping for compatibility)
        Route::get('/payments', [AdminController::class, 'payments']);
        Route::post('/payments', [AdminController::class, 'createPayment']);
        Route::patch('/payments/{payoutRequest}', [AdminController::class, 'updatePayment']);

        // New Payout Management
        Route::prefix('payouts')->group(function () {
            Route::get('/requests', [AdminPayoutController::class, 'index']);
            Route::get('/requests/{payoutRequest}', [AdminPayoutController::class, 'show']);
            Route::post('/requests/{payoutRequest}/approve', [AdminPayoutController::class, 'approve']);
            Route::post('/requests/{payoutRequest}/reject', [AdminPayoutController::class, 'reject']);
            Route::post('/requests/{payoutRequest}/process', [AdminPayoutController::class, 'process']);
            Route::get('/history', [AdminPayoutController::class, 'payouts']);
        });

        // Analytics
        Route::get('/dashboard/stats', [AdminController::class, 'dashboardStats']);
        Route::get('/analytics', [AdminController::class, 'analytics']);

        // Settings
        Route::get('/settings', [AdminController::class, 'getSettings']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);
        Route::put('/settings/password', [AdminController::class, 'updatePassword']);

        // Network Management
        Route::prefix('networks')->group(function () {
            Route::get('/', [AdminNetworkController::class, 'index']);
            Route::post('/', [AdminNetworkController::class, 'store']);
            Route::put('/{network}', [AdminNetworkController::class, 'update']);
            Route::delete('/{network}', [AdminNetworkController::class, 'destroy']);
        });

        // Credit Rules
        Route::get('/credit-rules', [AdminNetworkController::class, 'getCreditRules']);
        Route::put('/credit-rules', [AdminNetworkController::class, 'updateCreditRules']);

        // CPA Lockers Management
        Route::prefix('cpa')->group(function () {
            Route::get('/lockers', [CpaLockerController::class, 'adminIndex']);
            Route::post('/lockers', [CpaLockerController::class, 'store']);
            Route::put('/lockers/{locker}', [CpaLockerController::class, 'update']);
            Route::delete('/lockers/{locker}', [CpaLockerController::class, 'destroy']);
        });

        // Landing Pages Management
        Route::get('/landing-pages', [AdminController::class, 'landingPages']);
        Route::get('/landing-pages/stats', [AdminController::class, 'landingPagesStats']);
        Route::get('/landing-pages/leaderboard', [AdminController::class, 'landingPagesLeaderboard']);
        Route::put('/landing-pages/{landingPage}/credit-cost', [AdminController::class, 'updateLandingPageCreditCost']);
        Route::delete('/landing-pages/{landingPage}', [AdminController::class, 'deleteLandingPage']);

        // Billing Management
        Route::get('/billing-logs', [AdminController::class, 'billingLogs']);
        Route::get('/billing-stats', [AdminController::class, 'billingStats']);
        Route::post('/billing-logs/{billingLog}/retry', [AdminController::class, 'retryFailedRenewal']);
        // Training Modules Management
        Route::prefix('trainings')->group(function () {
            Route::get('/', [AdminTrainingModuleController::class, 'index']);
            Route::post('/', [AdminTrainingModuleController::class, 'store']);
            Route::put('/{module}', [AdminTrainingModuleController::class, 'update']);
            Route::delete('/{module}', [AdminTrainingModuleController::class, 'destroy']);
            Route::post('/generate/{networkId}', [AdminTrainingModuleController::class, 'generate']);
        });
    });
});

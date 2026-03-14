<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\EligibilityController;
use App\Http\Controllers\Api\V1\FollowupController;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\OfficeHourController;
use App\Http\Controllers\Api\V1\OnboardController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PayerController;
use App\Http\Controllers\Api\V1\ProviderController;
use App\Http\Controllers\Api\V1\ProxyController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\StrategyProfileController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TestimonialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

/*
|--------------------------------------------------------------------------
| Public Reference Data (no auth)
|--------------------------------------------------------------------------
*/
// Temporary: run EnnHealth seeder via API (remove after use)
Route::get('/run-seed', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\EnnHealthDataSeeder', '--force' => true]);
        return response()->json(['success' => true, 'output' => \Illuminate\Support\Facades\Artisan::output()]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
    }
});

Route::prefix('reference')->group(function () {
    Route::get('/states', [ReferenceController::class, 'states']);
    Route::get('/telehealth-policies', [ReferenceController::class, 'telehealthPolicies']);
    Route::get('/taxonomy-codes', [ReferenceController::class, 'taxonomyCodes']);
    Route::get('/payers', [ReferenceController::class, 'payers']);
});

/*
|--------------------------------------------------------------------------
| Public Onboarding (token-based, no auth)
|--------------------------------------------------------------------------
*/
Route::prefix('onboard')->group(function () {
    Route::get('/{token}', [OnboardController::class, 'validate_token']);
    Route::post('/{token}', [OnboardController::class, 'submit']);
});

/*
|--------------------------------------------------------------------------
| Public Agency Routes (by slug, no auth)
|--------------------------------------------------------------------------
*/
Route::prefix('public/{slug}')->group(function () {
    Route::get('/availability', [BookingController::class, 'availability']);
    Route::get('/office-hours', [OfficeHourController::class, 'publicIndex']);
    Route::post('/book', [BookingController::class, 'book']);
    Route::get('/testimonials', [TestimonialController::class, 'publicIndex']);
    Route::post('/eligibility', [EligibilityController::class, 'publicCheck']);
});

Route::prefix('public/testimonial')->group(function () {
    Route::get('/{token}', [TestimonialController::class, 'showByToken']);
    Route::post('/{token}', [TestimonialController::class, 'submitByToken']);
});

/*
|--------------------------------------------------------------------------
| Public NPPES Proxy (no auth, no CORS issues server-side)
|--------------------------------------------------------------------------
*/
Route::prefix('proxy/nppes')->group(function () {
    Route::get('/lookup/{npi}', [ProxyController::class, 'nppesLookup']);
    Route::get('/search', [ProxyController::class, 'nppesSearch']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Agency management
    Route::get('/agency', [AgencyController::class, 'show']);
    Route::put('/agency', [AgencyController::class, 'update']);
    Route::get('/agency/config', [AgencyController::class, 'getConfig']);
    Route::put('/agency/config', [AgencyController::class, 'updateConfig']);

    // Agency user management (admin only)
    Route::prefix('agency/users')->group(function () {
        Route::get('/', [AgencyController::class, 'listUsers']);
        Route::post('/', [AgencyController::class, 'inviteUser']);
        Route::put('/{id}', [AgencyController::class, 'updateUser']);
        Route::delete('/{id}', [AgencyController::class, 'deleteUser']);
    });

    // Onboarding token management
    Route::prefix('onboard/tokens')->group(function () {
        Route::get('/', [OnboardController::class, 'index']);
        Route::post('/', [OnboardController::class, 'store']);
        Route::delete('/{id}', [OnboardController::class, 'destroy']);
    });

    // Credentialing CRUD
    Route::apiResource('organizations', OrganizationController::class);
    Route::apiResource('providers', ProviderController::class);
    Route::apiResource('licenses', LicenseController::class);

    Route::apiResource('applications', ApplicationController::class);
    Route::post('/applications/{id}/transition', [ApplicationController::class, 'transition']);
    Route::get('/applications-stats', [ApplicationController::class, 'stats']);

    Route::apiResource('followups', FollowupController::class);
    Route::post('/followups/{id}/complete', [FollowupController::class, 'complete']);
    Route::get('/followups-overdue', [FollowupController::class, 'overdue']);
    Route::get('/followups-upcoming', [FollowupController::class, 'upcoming']);

    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::post('/activity-logs', [ActivityLogController::class, 'store']);

    Route::apiResource('tasks', TaskController::class);
    Route::post('/tasks/{id}/complete', [TaskController::class, 'complete']);

    Route::apiResource('strategies', StrategyProfileController::class);

    // Payers (global catalog + agency plans)
    Route::get('/payers', [PayerController::class, 'index']);
    Route::get('/payers/{id}', [PayerController::class, 'show']);
    Route::get('/payers/{id}/plans', [PayerController::class, 'plans']);
    Route::post('/payer-plans', [PayerController::class, 'storePlan']);
    Route::put('/payer-plans/{id}', [PayerController::class, 'updatePlan']);
    Route::delete('/payer-plans/{id}', [PayerController::class, 'destroyPlan']);

    // Proxy services
    Route::post('/proxy/stedi/eligibility', [ProxyController::class, 'stediEligibility']);
    Route::post('/proxy/caqh/{action}', [ProxyController::class, 'caqh']);

    // Eligibility check history
    Route::get('/eligibility-checks', [EligibilityController::class, 'index']);

    // Bookings management
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::put('/bookings/{id}', [BookingController::class, 'update']);

    // Testimonials management
    Route::get('/testimonials', [TestimonialController::class, 'index']);
    Route::put('/testimonials/{id}', [TestimonialController::class, 'update']);
    Route::post('/testimonials/generate-link', [TestimonialController::class, 'generateLink']);

    // Office hours management
    Route::get('/office-hours', [OfficeHourController::class, 'index']);
    Route::put('/office-hours', [OfficeHourController::class, 'upsert']);
});

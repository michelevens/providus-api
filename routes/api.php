<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\EligibilityController;
use App\Http\Controllers\Api\V1\ExclusionController;
use App\Http\Controllers\Api\V1\FacilityController;
use App\Http\Controllers\Api\V1\FaqController;
use App\Http\Controllers\Api\V1\FollowupController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\OfficeHourController;
use App\Http\Controllers\Api\V1\OnboardController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PayerController;
use App\Http\Controllers\Api\V1\ProviderController;
use App\Http\Controllers\Api\V1\ProviderProfileController;
use App\Http\Controllers\Api\V1\ProxyController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\ReportController;
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
    Route::get('/{token}/organizations', [OnboardController::class, 'organizations']);
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

    // Agency info (all authenticated users can read)
    Route::get('/agency', [AgencyController::class, 'show']);
    Route::get('/agency/config', [AgencyController::class, 'getConfig']);

    // Agency management (agency+ roles only)
    Route::middleware('role:agency')->group(function () {
        Route::put('/agency', [AgencyController::class, 'update']);
        Route::put('/agency/config', [AgencyController::class, 'updateConfig']);

        // User management (agency+ only)
        Route::prefix('agency/users')->group(function () {
            Route::get('/', [AgencyController::class, 'listUsers']);
            Route::post('/', [AgencyController::class, 'inviteUser']);
            Route::put('/{id}', [AgencyController::class, 'updateUser']);
            Route::delete('/{id}', [AgencyController::class, 'deleteUser']);
        });
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

    // ── Exclusion Screening ──
    Route::get('/exclusions', [ExclusionController::class, 'index']);
    Route::get('/exclusions/summary', [ExclusionController::class, 'summary']);
    Route::post('/exclusions/screen/{providerId}', [ExclusionController::class, 'screen']);
    Route::post('/exclusions/screen-all', [ExclusionController::class, 'screenAll']);

    // ── Facilities ──
    Route::apiResource('facilities', FacilityController::class);
    Route::post('/facilities/from-npi', [FacilityController::class, 'createFromNpi']);

    // ── Billing & Invoicing ──
    Route::get('/billing/stats', [InvoiceController::class, 'stats']);
    Route::get('/billing/services', [InvoiceController::class, 'services']);
    Route::post('/billing/services', [InvoiceController::class, 'storeService']);
    Route::put('/billing/services/{id}', [InvoiceController::class, 'updateService']);
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('/invoices/{id}/payments', [InvoiceController::class, 'addPayment']);

    // ── Provider Profile (education, malpractice, boards, work history, CME, references, documents) ──
    Route::prefix('providers/{providerId}')->group(function () {
        Route::get('/profile', [ProviderProfileController::class, 'fullProfile']);

        Route::get('/malpractice', [ProviderProfileController::class, 'malpractice']);
        Route::post('/malpractice', [ProviderProfileController::class, 'storeMalpractice']);
        Route::put('/malpractice/{id}', [ProviderProfileController::class, 'updateMalpractice']);
        Route::delete('/malpractice/{id}', [ProviderProfileController::class, 'destroyMalpractice']);

        Route::get('/education', [ProviderProfileController::class, 'education']);
        Route::post('/education', [ProviderProfileController::class, 'storeEducation']);
        Route::put('/education/{id}', [ProviderProfileController::class, 'updateEducation']);
        Route::delete('/education/{id}', [ProviderProfileController::class, 'destroyEducation']);

        Route::get('/boards', [ProviderProfileController::class, 'boards']);
        Route::post('/boards', [ProviderProfileController::class, 'storeBoard']);
        Route::put('/boards/{id}', [ProviderProfileController::class, 'updateBoard']);
        Route::delete('/boards/{id}', [ProviderProfileController::class, 'destroyBoard']);

        Route::get('/work-history', [ProviderProfileController::class, 'workHistory']);
        Route::post('/work-history', [ProviderProfileController::class, 'storeWorkHistory']);
        Route::put('/work-history/{id}', [ProviderProfileController::class, 'updateWorkHistory']);
        Route::delete('/work-history/{id}', [ProviderProfileController::class, 'destroyWorkHistory']);

        Route::get('/cme', [ProviderProfileController::class, 'cme']);
        Route::post('/cme', [ProviderProfileController::class, 'storeCme']);
        Route::put('/cme/{id}', [ProviderProfileController::class, 'updateCme']);
        Route::delete('/cme/{id}', [ProviderProfileController::class, 'destroyCme']);

        Route::get('/references', [ProviderProfileController::class, 'references']);
        Route::post('/references', [ProviderProfileController::class, 'storeReference']);
        Route::put('/references/{id}', [ProviderProfileController::class, 'updateReference']);
        Route::delete('/references/{id}', [ProviderProfileController::class, 'destroyReference']);

        Route::get('/documents', [ProviderProfileController::class, 'documents']);
        Route::post('/documents', [ProviderProfileController::class, 'storeDocument']);
        Route::put('/documents/{id}', [ProviderProfileController::class, 'updateDocument']);
        Route::delete('/documents/{id}', [ProviderProfileController::class, 'destroyDocument']);
    });

    // ── Bulk Import ──
    Route::get('/imports', [ImportController::class, 'index']);
    Route::post('/imports/preview', [ImportController::class, 'preview']);
    Route::post('/imports/execute', [ImportController::class, 'execute']);

    // ── Reports & Export ──
    Route::get('/reports/provider/{providerId}', [ReportController::class, 'providerPacket']);
    Route::get('/reports/compliance', [ReportController::class, 'complianceReport']);
    Route::get('/reports/export', [ReportController::class, 'export']);

    // ── FAQ / Knowledge Base ──
    Route::get('/faqs', [FaqController::class, 'index']);
    Route::post('/faqs', [FaqController::class, 'store']);
    Route::put('/faqs/{id}', [FaqController::class, 'update']);
    Route::delete('/faqs/{id}', [FaqController::class, 'destroy']);
    Route::post('/faqs/{id}/helpful', [FaqController::class, 'helpful']);

    // ── Licensing Boards (reference) ──
    Route::get('/licensing-boards', [FaqController::class, 'licensingBoards']);
});

/*
|--------------------------------------------------------------------------
| SuperAdmin Routes (future cross-agency operations)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:superadmin'])->prefix('admin')->group(function () {
    Route::get('/agencies', function () {
        $agencies = \App\Models\Agency::withCount(['users', 'organizations', 'providers', 'applications'])
            ->with('config:id,agency_id,notification_email')
            ->orderBy('name')
            ->get();
        return response()->json(['success' => true, 'data' => $agencies]);
    });

    Route::get('/agencies/{id}', function (int $id) {
        $agency = \App\Models\Agency::withCount(['users', 'organizations', 'providers', 'applications', 'licenses', 'tasks'])
            ->with(['config', 'users:id,agency_id,first_name,last_name,email,role,is_active'])
            ->findOrFail($id);
        return response()->json(['success' => true, 'data' => $agency]);
    });
});

// TEMPORARY: promote account to superadmin — REMOVE after use
Route::get('/promote-superadmin', function () {
    $user = \App\Models\User::where('email', 'admin@ennhealth.com')->first();
    if (!$user) return response()->json(['error' => 'User not found'], 404);
    $user->update(['role' => 'superadmin']);
    return response()->json(['success' => true, 'message' => 'Promoted to superadmin', 'role' => $user->role]);
});

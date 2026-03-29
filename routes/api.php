<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CommunicationLogController;
use App\Http\Controllers\Api\V1\EligibilityController;
use App\Http\Controllers\Api\V1\ExclusionController;
use App\Http\Controllers\Api\V1\FacilityController;
use App\Http\Controllers\Api\V1\FaqController;
use App\Http\Controllers\Api\V1\FollowupController;
use App\Http\Controllers\Api\V1\FundingController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\MasterDataController;
use App\Http\Controllers\Api\V1\NotificationController;
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
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\BillingServiceController;
use App\Http\Controllers\Api\V1\RcmController;
use App\Http\Controllers\Api\V1\RcmPhase2Controller;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\OrganizationContactController;
use App\Http\Controllers\Api\V1\RevenueIntelligenceController;
use App\Http\Controllers\Api\V1\TestimonialController;
use App\Http\Controllers\Api\V1\TwoFactorController;
use App\Http\Controllers\Api\V1\ShareLinkController;
use App\Http\Controllers\Api\V1\NotificationSendController;
use App\Http\Controllers\Api\V1\ApiKeyController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/demo-login', [AuthController::class, 'demoLogin'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::post('/accept-invite', [AuthController::class, 'acceptInvite'])->middleware('throttle:5,1');
    Route::post('/verify-2fa', [TwoFactorController::class, 'verifyLogin'])->middleware('throttle:5,1');

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
    Route::get('/{token}', [OnboardController::class, 'validate_token'])->where('token', '[A-Za-z0-9]{20,}');
    Route::get('/{token}/organizations', [OnboardController::class, 'organizations'])->where('token', '[A-Za-z0-9]{20,}');
    Route::post('/{token}', [OnboardController::class, 'submit'])->where('token', '[A-Za-z0-9]{20,}');
});

/*
|--------------------------------------------------------------------------
| Public Share Link (no auth)
|--------------------------------------------------------------------------
*/
Route::get('/share/{token}', [ShareLinkController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Public Agency Routes (by slug, no auth)
|--------------------------------------------------------------------------
*/
Route::prefix('public/{slug}')->middleware('embed.cors')->group(function () {
    Route::get('/embed-config', [BookingController::class, 'embedConfig']);
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
| Public Contract Viewing (token-based, no auth)
|--------------------------------------------------------------------------
*/
Route::prefix('contracts/view')->group(function () {
    Route::get('/{token}', [ContractController::class, 'showByToken']);
    Route::post('/{token}/accept', [ContractController::class, 'acceptByToken']);
});

/*
|--------------------------------------------------------------------------
| Stripe Webhook (no auth — verified by signature)
|--------------------------------------------------------------------------
*/
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

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
            Route::post('/{id}/reset-password', [AgencyController::class, 'resetUserPassword']);
            Route::put('/{id}/change-email', [AgencyController::class, 'changeUserEmail']);
        });

        // Audit logs (agency-scoped)
        Route::get('/audit-logs', [AdminController::class, 'auditLogs']);
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

    // ── Organization Contacts ──
    Route::prefix('organizations/{organizationId}/contacts')->group(function () {
        Route::get('/', [OrganizationContactController::class, 'index']);
        Route::post('/', [OrganizationContactController::class, 'store']);
        Route::put('/{id}', [OrganizationContactController::class, 'update']);
        Route::delete('/{id}', [OrganizationContactController::class, 'destroy']);
    });

    // ── License Monitoring ──
    Route::get('/licenses-monitoring/summary', [LicenseController::class, 'monitoringSummary']);
    Route::get('/licenses-monitoring/expiring', [LicenseController::class, 'expiring']);
    Route::get('/licenses-monitoring/verifications', [LicenseController::class, 'verifications']);
    Route::post('/licenses/{id}/verify', [LicenseController::class, 'verify']);
    Route::post('/licenses-monitoring/verify-all', [LicenseController::class, 'verifyAll']);

    // ── DEA Registrations ──
    Route::get('/dea-registrations', [LicenseController::class, 'deaIndex']);
    Route::post('/dea-registrations', [LicenseController::class, 'deaStore']);
    Route::put('/dea-registrations/{id}', [LicenseController::class, 'deaUpdate']);
    Route::delete('/dea-registrations/{id}', [LicenseController::class, 'deaDestroy']);

    Route::apiResource('applications', ApplicationController::class);
    Route::post('/applications/{id}/transition', [ApplicationController::class, 'transition']);
    Route::get('/applications-stats', [ApplicationController::class, 'stats']);
    // Alternate create route — workaround for CDN caching 503 on POST /applications
    Route::post('/app-create', [ApplicationController::class, 'store']);

    Route::apiResource('followups', FollowupController::class);
    Route::post('/followups/{id}/complete', [FollowupController::class, 'complete']);
    Route::get('/followups-overdue', [FollowupController::class, 'overdue']);
    Route::get('/followups-upcoming', [FollowupController::class, 'upcoming']);

    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::post('/activity-logs', [ActivityLogController::class, 'store']);

    // ── Notifications ──
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // ── Communication Logs ──
    Route::get('/communication-logs', [CommunicationLogController::class, 'index']);
    Route::post('/communication-logs', [CommunicationLogController::class, 'store']);
    Route::get('/communication-logs/{id}', [CommunicationLogController::class, 'show']);
    Route::delete('/communication-logs/{id}', [CommunicationLogController::class, 'destroy']);

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

    // ── Share Links ──
    Route::post('/providers/{providerId}/share', [ShareLinkController::class, 'store']);

    // ── Notification Sending (Resend) ──
    Route::post('/notifications/send', [NotificationSendController::class, 'send']);
    Route::post('/notifications/test', [NotificationSendController::class, 'test']);
    Route::get('/notifications/log', [NotificationSendController::class, 'index']);
    Route::get('/notifications/preferences', [NotificationSendController::class, 'preferences']);
    Route::put('/notifications/preferences', [NotificationSendController::class, 'updatePreferences']);

    // ── Agency Branding (moved to /agency/branding to avoid conflict with organizations apiResource) ──
    Route::get('/agency/branding', [AgencyController::class, 'branding']);
    Route::put('/agency/branding', [AgencyController::class, 'updateBranding']);

    // ── API Keys & Webhooks (agency+ role) ──
    Route::apiResource('api-keys', ApiKeyController::class)->only(['index', 'store', 'destroy']);
    Route::apiResource('webhooks', WebhookController::class);
    Route::post('/webhooks/{id}/test', [WebhookController::class, 'test']);

    // ── Billing & Invoicing ──
    Route::get('/billing/stats', [InvoiceController::class, 'stats']);
    Route::get('/billing/services', [InvoiceController::class, 'services']);
    Route::post('/billing/services', [InvoiceController::class, 'storeService']);
    Route::put('/billing/services/{id}', [InvoiceController::class, 'updateService']);
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('/invoices/{id}/payments', [InvoiceController::class, 'addPayment']);
    Route::post('/invoices/{id}/send-reminder', [InvoiceController::class, 'sendReminder']);

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

        Route::get('/documents', [DocumentController::class, 'index']);
        Route::post('/documents', [ProviderProfileController::class, 'storeDocument']);
        Route::put('/documents/{id}', [ProviderProfileController::class, 'updateDocument']);
        Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

        // File upload/download
        Route::post('/documents/upload', [DocumentController::class, 'upload']);
        Route::post('/documents/{id}/replace', [DocumentController::class, 'replace']);
        Route::get('/documents/{id}/download', [DocumentController::class, 'download']);
    });

    // ── Bulk Import ──
    Route::get('/imports', [ImportController::class, 'index']);
    Route::post('/imports/preview', [ImportController::class, 'preview']);
    Route::post('/imports/execute', [ImportController::class, 'execute']);

    // ── Reports & Export ──
    Route::get('/reports/provider/{providerId}', [ReportController::class, 'providerPacket']);
    Route::get('/reports/provider/{providerId}/pdf', [ReportController::class, 'providerPacketPdf']);
    Route::get('/reports/compliance', [ReportController::class, 'complianceReport']);
    Route::get('/reports/export', [ReportController::class, 'export']);

    // ── AI Features ──
    Route::post('/ai/extract-document/{documentId}', [AiController::class, 'extractDocument']);
    Route::post('/ai/draft-email/{applicationId}', [AiController::class, 'draftEmail']);
    Route::get('/ai/anomalies/{providerId}', [AiController::class, 'detectAnomalies']);
    Route::get('/ai/predict-timeline/{applicationId}', [AiController::class, 'predictTimeline']);

    // ── FAQ / Knowledge Base ──
    Route::get('/faqs', [FaqController::class, 'index']);
    Route::post('/faqs', [FaqController::class, 'store']);
    Route::put('/faqs/{id}', [FaqController::class, 'update']);
    Route::delete('/faqs/{id}', [FaqController::class, 'destroy']);
    Route::post('/faqs/{id}/helpful', [FaqController::class, 'helpful']);

    // ── Licensing Boards (reference) ──
    Route::get('/licensing-boards', [FaqController::class, 'licensingBoards']);

    // ── Contracts & Agreements ──
    Route::get('/contracts/stats', [ContractController::class, 'stats']);
    Route::apiResource('contracts', ContractController::class);
    Route::post('/contracts/{id}/send', [ContractController::class, 'send']);
    Route::post('/contracts/{id}/terminate', [ContractController::class, 'terminate']);
    Route::post('/contracts/{id}/generate-invoice', [ContractController::class, 'generateInvoice']);

    // ── Billing Services Management ──
    Route::get('/billing-clients/stats', [BillingServiceController::class, 'clientStats']);
    Route::get('/billing-clients', [BillingServiceController::class, 'clients']);
    Route::get('/billing-clients/{id}', [BillingServiceController::class, 'showClient']);
    Route::post('/billing-clients', [BillingServiceController::class, 'storeClient']);
    Route::put('/billing-clients/{id}', [BillingServiceController::class, 'updateClient']);
    Route::delete('/billing-clients/{id}', [BillingServiceController::class, 'destroyClient']);
    Route::post('/billing-clients/{id}/generate-ledger', [BillingServiceController::class, 'generateLedger']);
    Route::get('/billing-clients/{id}/ledger', [BillingServiceController::class, 'getLedger']);
    Route::put('/billing-ledger/{id}/remittance', [BillingServiceController::class, 'recordRemittance']);

    Route::get('/billing-tasks', [BillingServiceController::class, 'tasks']);
    Route::post('/billing-tasks/generate', [BillingServiceController::class, 'generateTasks']);
    Route::post('/billing-tasks', [BillingServiceController::class, 'storeTask']);
    Route::post('/billing-tasks/{id}/dismiss', [BillingServiceController::class, 'dismissTask']);
    Route::put('/billing-tasks/{id}', [BillingServiceController::class, 'updateTask']);
    Route::delete('/billing-tasks/{id}', [BillingServiceController::class, 'destroyTask']);

    Route::get('/billing-activities', [BillingServiceController::class, 'activities']);
    Route::post('/billing-activities', [BillingServiceController::class, 'storeActivity']);
    Route::put('/billing-activities/{id}', [BillingServiceController::class, 'updateActivity']);
    Route::delete('/billing-activities/{id}', [BillingServiceController::class, 'destroyActivity']);

    Route::get('/billing-financials', [BillingServiceController::class, 'financials']);
    Route::post('/billing-financials', [BillingServiceController::class, 'storeFinancial']);
    Route::put('/billing-financials/{id}', [BillingServiceController::class, 'updateFinancial']);

    // ── RCM: Claims, Denials, Payments, Charges, AR ──
    Route::get('/rcm/claims/stats', [RcmController::class, 'claimStats']);
    Route::get('/rcm/claims', [RcmController::class, 'claims']);
    Route::get('/rcm/claims/{id}', [RcmController::class, 'showClaim']);
    Route::post('/rcm/claims', [RcmController::class, 'storeClaim']);
    Route::put('/rcm/claims/{id}', [RcmController::class, 'updateClaim']);
    Route::delete('/rcm/claims/{id}', [RcmController::class, 'destroyClaim']);
    Route::post('/rcm/claims/bulk-import', [RcmController::class, 'bulkImportClaims']);
    Route::post('/rcm/claims/purge', [RcmController::class, 'purgeAllClaims']);

    Route::get('/rcm/denials/stats', [RcmController::class, 'denialStats']);
    Route::get('/rcm/denials', [RcmController::class, 'denials']);
    Route::post('/rcm/denials', [RcmController::class, 'storeDenial']);
    Route::put('/rcm/denials/{id}', [RcmController::class, 'updateDenial']);
    Route::delete('/rcm/denials/{id}', [RcmController::class, 'destroyDenial']);

    Route::get('/rcm/payments', [RcmController::class, 'payments']);
    Route::post('/rcm/payments', [RcmController::class, 'storePayment']);
    Route::post('/rcm/payments/bulk-match', [RcmController::class, 'bulkMatchPayments']);
    Route::put('/rcm/payments/{id}', [RcmController::class, 'updatePayment']);
    Route::delete('/rcm/payments/{id}', [RcmController::class, 'destroyPayment']);

    Route::get('/rcm/charges', [RcmController::class, 'charges']);
    Route::post('/rcm/charges', [RcmController::class, 'storeCharge']);
    Route::put('/rcm/charges/{id}', [RcmController::class, 'updateCharge']);
    Route::delete('/rcm/charges/{id}', [RcmController::class, 'destroyCharge']);
    Route::post('/rcm/charges/bulk-import', [RcmController::class, 'bulkImportCharges']);

    Route::get('/rcm/ar-aging', [RcmController::class, 'arAging']);

    // ── RCM Phase 2: Advanced Features ──
    Route::get('/rcm/fee-schedules', [RcmPhase2Controller::class, 'feeSchedules']);
    Route::post('/rcm/fee-schedules', [RcmPhase2Controller::class, 'storeFeeSchedule']);
    Route::put('/rcm/fee-schedules/{id}', [RcmPhase2Controller::class, 'updateFeeSchedule']);
    Route::delete('/rcm/fee-schedules/{id}', [RcmPhase2Controller::class, 'destroyFeeSchedule']);
    Route::post('/rcm/fee-schedules/bulk-import', [RcmPhase2Controller::class, 'bulkImportFeeSchedules']);

    Route::get('/rcm/work-queues', [RcmPhase2Controller::class, 'workQueues']);

    Route::get('/rcm/appeal-templates', [RcmPhase2Controller::class, 'appealTemplates']);
    Route::post('/rcm/appeal-templates', [RcmPhase2Controller::class, 'storeAppealTemplate']);
    Route::put('/rcm/appeal-templates/{id}', [RcmPhase2Controller::class, 'updateAppealTemplate']);
    Route::delete('/rcm/appeal-templates/{id}', [RcmPhase2Controller::class, 'destroyAppealTemplate']);
    Route::post('/rcm/denials/generate-appeal', [RcmPhase2Controller::class, 'generateAppealLetter']);
    Route::post('/rcm/denials/escalate', [RcmPhase2Controller::class, 'escalateDenials']);

    Route::post('/rcm/payments/batch-allocate', [RcmPhase2Controller::class, 'batchAllocatePayment']);

    Route::get('/rcm/followups', [RcmPhase2Controller::class, 'followups']);
    Route::post('/rcm/followups', [RcmPhase2Controller::class, 'storeFollowup']);
    Route::put('/rcm/followups/{id}', [RcmPhase2Controller::class, 'updateFollowup']);
    Route::delete('/rcm/followups/{id}', [RcmPhase2Controller::class, 'destroyFollowup']);

    Route::post('/rcm/underpayments/detect', [RcmPhase2Controller::class, 'detectUnderpayments']);
    Route::get('/rcm/underpayments', [RcmPhase2Controller::class, 'underpayments']);
    Route::put('/rcm/underpayments/{id}', [RcmPhase2Controller::class, 'updateUnderpayment']);

    Route::get('/rcm/export/claims', [RcmPhase2Controller::class, 'exportClaims']);
    Route::get('/rcm/export/denials', [RcmPhase2Controller::class, 'exportDenials']);

    Route::post('/rcm/client-reports/generate', [RcmPhase2Controller::class, 'generateClientReport']);
    Route::get('/rcm/client-reports', [RcmPhase2Controller::class, 'clientReports']);

    Route::get('/rcm/patient-statements', [RcmPhase2Controller::class, 'patientStatements']);
    Route::post('/rcm/patient-statements', [RcmPhase2Controller::class, 'storePatientStatement']);
    Route::put('/rcm/patient-statements/{id}', [RcmPhase2Controller::class, 'updatePatientStatement']);
    Route::post('/rcm/patient-statements/generate', [RcmPhase2Controller::class, 'generatePatientStatements']);

    Route::get('/rcm/eligibility', [RcmPhase2Controller::class, 'eligibilityChecks']);
    Route::post('/rcm/eligibility/check', [RcmPhase2Controller::class, 'checkEligibility']);
    Route::put('/rcm/eligibility/{id}', [RcmPhase2Controller::class, 'updateEligibilityCheck']);

    Route::post('/rcm/era/parse', [RcmPhase2Controller::class, 'parseEra']);
    Route::post('/rcm/837/parse', [RcmPhase2Controller::class, 'parse837']);
    Route::post('/rcm/837/import', [RcmPhase2Controller::class, 'import837']);

    Route::get('/rcm/denial-risk', [RcmPhase2Controller::class, 'denialRiskAnalysis']);
    Route::post('/rcm/pre-submission-check', [RcmPhase2Controller::class, 'preSubmissionCheck']);

    // Payer Intelligence Hub
    Route::get('/rcm/payer-rules', [RcmPhase2Controller::class, 'payerRules']);
    Route::get('/rcm/payer-rules/{payerName}', [RcmPhase2Controller::class, 'showPayerRule']);
    Route::post('/rcm/payer-rules', [RcmPhase2Controller::class, 'storePayerRule']);
    Route::put('/rcm/payer-rules/{id}', [RcmPhase2Controller::class, 'updatePayerRule']);
    Route::delete('/rcm/payer-rules/{id}', [RcmPhase2Controller::class, 'destroyPayerRule']);
    Route::post('/rcm/payer-rules/check', [RcmPhase2Controller::class, 'checkPayerRules']);
    Route::post('/rcm/payer-rules/extract-policy', [RcmPhase2Controller::class, 'extractPayerPolicy']);

    // Reconciliation
    Route::post('/rcm/reconcile', [RcmPhase2Controller::class, 'autoReconcile']);
    Route::post('/rcm/sync-charge-statuses', [RcmPhase2Controller::class, 'syncChargeStatuses']);
    Route::get('/rcm/reconciliation-report', [RcmPhase2Controller::class, 'reconciliationReport']);

    // Duplicate Detection
    Route::get('/rcm/duplicates', [RcmPhase2Controller::class, 'detectDuplicates']);

    // Provider Feedback
    Route::get('/rcm/provider-feedback', [RcmPhase2Controller::class, 'providerFeedback']);
    Route::post('/rcm/provider-feedback', [RcmPhase2Controller::class, 'storeProviderFeedback']);
    Route::put('/rcm/provider-feedback/{id}', [RcmPhase2Controller::class, 'updateProviderFeedback']);
    Route::post('/rcm/provider-feedback/auto-generate', [RcmPhase2Controller::class, 'autoGenerateFeedback']);

    // Real-time Eligibility (Stedi)
    Route::post('/rcm/eligibility/realtime', [RcmPhase2Controller::class, 'realTimeEligibility']);

    // ── Funding Hub ──
    Route::prefix('funding')->group(function () {
        Route::get('/opportunities', [FundingController::class, 'opportunities']);
        Route::get('/opportunities/{fundingOpportunity}', [FundingController::class, 'show']);
        Route::get('/summary', [FundingController::class, 'summary']);
        Route::get('/intelligence', [FundingController::class, 'intelligence']);
        Route::post('/scrape', [FundingController::class, 'scrape'])->middleware('throttle:5,1');
        Route::get('/applications', [FundingController::class, 'applications']);
        Route::post('/applications', [FundingController::class, 'storeApplication']);
        Route::put('/applications/{fundingApplication}', [FundingController::class, 'updateApplication']);
        Route::delete('/applications/{fundingApplication}', [FundingController::class, 'destroyApplication']);
    });

    // ── Revenue Intelligence ──
    Route::prefix('revenue')->group(function () {
        Route::get('/dashboard', [RevenueIntelligenceController::class, 'dashboard']);
        Route::get('/by-provider', [RevenueIntelligenceController::class, 'byProvider']);
        Route::get('/by-payer', [RevenueIntelligenceController::class, 'byPayer']);
        Route::get('/by-state', [RevenueIntelligenceController::class, 'byState']);
        Route::get('/time-to-credential', [RevenueIntelligenceController::class, 'timeToCredential']);
    });

    // ── Two-Factor Authentication ──
    Route::prefix('2fa')->group(function () {
        Route::get('/status', [TwoFactorController::class, 'status']);
        Route::post('/enable', [TwoFactorController::class, 'enable']);
        Route::post('/verify', [TwoFactorController::class, 'verify']);
        Route::post('/disable', [TwoFactorController::class, 'disable']);
        Route::get('/recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
        Route::post('/regenerate-recovery', [TwoFactorController::class, 'regenerateRecoveryCodes']);
    });

    // ── Subscription & Billing (Stripe) ──
    Route::prefix('subscription')->group(function () {
        Route::get('/status', [SubscriptionController::class, 'status']);
        Route::get('/plans', [SubscriptionController::class, 'plans']);
        Route::post('/checkout', [SubscriptionController::class, 'checkout']);
        Route::post('/portal', [SubscriptionController::class, 'portal']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/resume', [SubscriptionController::class, 'resume']);
    });
});

/*
|--------------------------------------------------------------------------
| SuperAdmin Routes — Platform Administration
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:superadmin'])->prefix('admin')->group(function () {

    // Platform overview
    Route::get('/stats', [AdminController::class, 'stats']);

    // Demo user seeding (superadmin only)
    Route::post('/seed-demo', [AuthController::class, 'seedDemoUsers'])->middleware('throttle:3,1');

    // Agency management
    Route::get('/agencies', [AdminController::class, 'agencies']);
    Route::get('/agencies/{id}', [AdminController::class, 'agencyShow']);
    Route::put('/agencies/{id}', [AdminController::class, 'agencyUpdate']);
    Route::get('/agencies/{id}/users', [AdminController::class, 'agencyUsers']);
    Route::post('/agencies/{id}/impersonate', [AdminController::class, 'impersonate']);

    // Platform-wide user management
    Route::get('/users', [AdminController::class, 'users']);
    Route::put('/users/{id}', [AdminController::class, 'userUpdate']);

    // Audit log (activity log)
    Route::get('/audit-log', [AdminController::class, 'auditLog']);
    // Audit logs (model-level change tracking)
    Route::get('/audit-logs', [AdminController::class, 'auditLogs']);

    // Master Data CRUD
    Route::prefix('master-data')->group(function () {
        Route::get('/status', [MasterDataController::class, 'seedStatus']);

        // Payers
        Route::get('/payers', [MasterDataController::class, 'payers']);
        Route::post('/payers', [MasterDataController::class, 'storePayer']);
        Route::put('/payers/{id}', [MasterDataController::class, 'updatePayer']);
        Route::delete('/payers/{id}', [MasterDataController::class, 'destroyPayer']);

        // Telehealth Policies
        Route::get('/telehealth-policies', [MasterDataController::class, 'telehealthPolicies']);
        Route::post('/telehealth-policies', [MasterDataController::class, 'storeTelehealthPolicy']);
        Route::put('/telehealth-policies/{id}', [MasterDataController::class, 'updateTelehealthPolicy']);
        Route::delete('/telehealth-policies/{id}', [MasterDataController::class, 'destroyTelehealthPolicy']);

        // Strategy Templates
        Route::get('/strategy-templates', [MasterDataController::class, 'strategyTemplates']);
        Route::post('/strategy-templates', [MasterDataController::class, 'storeStrategyTemplate']);
        Route::put('/strategy-templates/{id}', [MasterDataController::class, 'updateStrategyTemplate']);
        Route::delete('/strategy-templates/{id}', [MasterDataController::class, 'destroyStrategyTemplate']);

        // Taxonomy Codes
        Route::get('/taxonomy-codes', [MasterDataController::class, 'taxonomyCodes']);
        Route::post('/taxonomy-codes', [MasterDataController::class, 'storeTaxonomyCode']);
        Route::put('/taxonomy-codes/{id}', [MasterDataController::class, 'updateTaxonomyCode']);
        Route::delete('/taxonomy-codes/{id}', [MasterDataController::class, 'destroyTaxonomyCode']);
    });
});


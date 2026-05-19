<?php

declare(strict_types=1);

use App\Controllers\AcceptInviteController;
use App\Controllers\AppointmentController;
use App\Controllers\BillingController;
use App\Controllers\LabController;
use App\Controllers\LabReportController;
use App\Controllers\PharmacyController;
use App\Controllers\AuthController;
use App\Controllers\ClinicSettingsController;
use App\Controllers\QueueController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\OnboardingController;
use App\Controllers\PatientController;
use App\Controllers\PortalController;
use App\Controllers\QrController;
use App\Controllers\SettingsController;
use App\Controllers\VisitController;
use App\Controllers\AnalyticsController;
use App\Controllers\BookController;
use App\Controllers\CrmController;
use App\Controllers\IncentiveController;
use App\Controllers\SchedulingController;
use App\Controllers\StaffController;
use App\Controllers\ApiV1Controller;
use App\Controllers\DirectoryController;
use App\Controllers\DocsController;
use App\Controllers\ImpersonateController;
use App\Controllers\SuperAdminController;
use App\Controllers\WebhookController;
use App\Core\GroupedRouteRegistrar;
use App\Core\RouteRegistrar;

return static function (RouteRegistrar $router): void {
    $router->get('/health', [HealthController::class, 'index'], 'health');

    $router->get('/auth/google', [AuthController::class, 'googleRedirect']);
    $router->get('/auth/google/callback', [AuthController::class, 'googleCallback']);
    $router->get('/api/refresh-token', [AuthController::class, 'refreshToken']);

    $router->group(['middleware' => ['csrf', 'rate']], static function (GroupedRouteRegistrar $auth): void {
        $auth->get('/register', [AuthController::class, 'showRegister']);
        $auth->post('/register', [AuthController::class, 'register']);
        $auth->get('/login', [AuthController::class, 'showLogin']);
        $auth->post('/login', [AuthController::class, 'login']);
        $auth->post('/logout', [AuthController::class, 'logout']);
        $auth->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
        $auth->post('/forgot-password', [AuthController::class, 'forgotPassword']);
        $auth->get('/reset-password/{token}', [AuthController::class, 'showResetPassword']);
        $auth->post('/reset-password/{token}', [AuthController::class, 'resetPassword']);
        $auth->get('/accept-invite/{token}', [AcceptInviteController::class, 'show']);
        $auth->post('/accept-invite/{token}', [AcceptInviteController::class, 'accept']);
    });

    $router->get('/api/check-slug', [AuthController::class, 'checkSlug']);

    $router->post('/webhooks/stripe', [WebhookController::class, 'stripe']);
    $router->post('/webhooks/razorpay', [WebhookController::class, 'razorpay']);
    $router->post('/webhooks/photo-published', [WebhookController::class, 'photoPublished']);

    $router->group(['middleware' => ['refresh', 'tenant', 'auth', 'csrf', 'rate']], static function (GroupedRouteRegistrar $app): void {
        $app->get('/dashboard', [DashboardController::class, 'index']);
        $app->post('/dashboard/checklist/dismiss', [DashboardController::class, 'dismissChecklist']);

        $app->get('/settings/leaves', static fn () => \App\Http\Response::redirect('/settings?tab=leaves'));
        $app->get('/settings', [ClinicSettingsController::class, 'index']);
        $app->post('/settings/general', [ClinicSettingsController::class, 'saveGeneral']);
        $app->post('/settings/hours', [ClinicSettingsController::class, 'saveHours']);
        $app->post('/settings/specialty', [ClinicSettingsController::class, 'saveSpecialty']);
        $app->post('/settings/notifications', [ClinicSettingsController::class, 'saveNotifications']);
        $app->post('/settings/test-whatsapp', [ClinicSettingsController::class, 'testWhatsApp']);
        $app->post('/settings/test-razorpay', [ClinicSettingsController::class, 'testRazorpay']);
        $app->post('/settings/leaves', [ClinicSettingsController::class, 'saveLeave']);
        $app->post('/settings/leaves/{id}/remove', [ClinicSettingsController::class, 'removeLeave']);
        $app->post('/settings/team/invite', [ClinicSettingsController::class, 'inviteStaff']);
        $app->post('/settings/team/invites/{id}/revoke', [ClinicSettingsController::class, 'revokeInvite']);
        $app->post('/settings/team/{id}', [ClinicSettingsController::class, 'updateStaff']);
        $app->post('/settings/consent-forms', [ClinicSettingsController::class, 'saveConsentForm']);
        $app->post('/settings/api/keys', [ClinicSettingsController::class, 'createApiKey']);
        $app->post('/settings/api/keys/{id}/revoke', [ClinicSettingsController::class, 'revokeApiKey']);
        $app->post('/settings/branding', [ClinicSettingsController::class, 'saveBranding']);
        $app->post('/settings/branding/domain', [ClinicSettingsController::class, 'startDomainVerify']);
        $app->post('/settings/branding/domain/check', [ClinicSettingsController::class, 'checkDomainVerify']);
        $app->post('/impersonate/exit', [ImpersonateController::class, 'exit']);

        $app->get('/lab', [LabController::class, 'index']);
        $app->get('/lab/catalog', [LabController::class, 'catalog']);
        $app->get('/lab/orders', [LabController::class, 'orders']);
        $app->get('/lab/orders/{id}', [LabController::class, 'showOrder']);
        $app->post('/lab/orders', [LabController::class, 'orderFromVisit']);
        $app->post('/lab/orders/{id}/collect', [LabController::class, 'collectSample']);
        $app->post('/lab/orders/{id}/results', [LabController::class, 'enterResults']);
        $app->post('/lab/orders/{id}/finalize', [LabController::class, 'finalize']);
        $app->get('/lab/orders/{id}/barcode', [LabController::class, 'barcodePdf']);

        $app->get('/pharmacy/pos', [PharmacyController::class, 'pos']);
        $app->get('/pharmacy/inventory', [PharmacyController::class, 'inventory']);
        $app->get('/pharmacy/narcotic', [PharmacyController::class, 'narcotic']);
        $app->post('/pharmacy/inventory', [PharmacyController::class, 'addBatch']);
        $app->post('/pharmacy/pos/checkout', [PharmacyController::class, 'checkout']);

        $app->get('/analytics', [AnalyticsController::class, 'index']);
        $app->post('/analytics/expenses', [AnalyticsController::class, 'storeExpense']);
        $app->get('/analytics/export/excel', [AnalyticsController::class, 'exportExcel']);
        $app->get('/analytics/export/tally', [AnalyticsController::class, 'exportTally']);

        $app->get('/crm', [CrmController::class, 'index']);
        $app->get('/crm/new', [CrmController::class, 'create']);
        $app->get('/crm/{id}/edit', [CrmController::class, 'edit']);
        $app->post('/crm/save', [CrmController::class, 'store']);
        $app->post('/crm/{id}/convert', [CrmController::class, 'convert']);

        $app->get('/staff/attendance', [StaffController::class, 'attendance']);
        $app->post('/staff/attendance/clock-in', [StaffController::class, 'clockIn']);
        $app->post('/staff/attendance/clock-out', [StaffController::class, 'clockOut']);
        $app->get('/staff/leaves', [StaffController::class, 'leaves']);
        $app->post('/staff/leaves', [StaffController::class, 'requestLeave']);
        $app->post('/staff/leaves/{id}/approve', [StaffController::class, 'approveLeave']);
        $app->post('/staff/leaves/{id}/reject', [StaffController::class, 'rejectLeave']);

        $app->get('/scheduling', [SchedulingController::class, 'index']);
        $app->post('/scheduling/schedules', [SchedulingController::class, 'saveSchedule']);
        $app->post('/scheduling/sync-hours', [SchedulingController::class, 'syncFromHours']);

        $app->get('/billing/incentives', [IncentiveController::class, 'index']);
        $app->post('/billing/incentives/config', [IncentiveController::class, 'saveConfig']);
        $app->post('/billing/incentives/calculate', [IncentiveController::class, 'calculate']);
        $app->get('/billing/incentives/{id}/payslip', [IncentiveController::class, 'payslip']);

        $app->get('/billing', [BillingController::class, 'index']);
        $app->get('/billing/export/excel', [BillingController::class, 'exportExcel']);
        $app->get('/billing/export/tally', [BillingController::class, 'exportTally']);
        $app->get('/billing/{id}', [BillingController::class, 'show']);
        $app->post('/billing/{id}', [BillingController::class, 'update']);
        $app->post('/billing/{id}/pay-cash', [BillingController::class, 'payCash']);

        $app->get('/appointments', [AppointmentController::class, 'index']);
        $app->get('/appointments/new', [AppointmentController::class, 'create']);
        $app->post('/appointments/new', [AppointmentController::class, 'store']);
        $app->get('/appointments/{id}/edit', [AppointmentController::class, 'edit']);
        $app->post('/appointments/{id}', [AppointmentController::class, 'update']);
        $app->post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
        $app->get('/appointments/{id}/slip', [AppointmentController::class, 'slip']);

        $app->get('/queue', [QueueController::class, 'index']);
        $app->post('/queue/{id}/status', [QueueController::class, 'updateStatus']);

        $app->get('/visits', [VisitController::class, 'index']);
        $app->get('/visits/new', [VisitController::class, 'start']);
        $app->get('/visits/{id}', [VisitController::class, 'show']);
        $app->post('/visits/{id}/complete', [VisitController::class, 'complete']);
        $app->post('/visits/{id}/unlock', [VisitController::class, 'unlock']);
        $app->post('/visits/{id}/consent', [VisitController::class, 'signConsent']);
        $app->post('/visits/{id}/discharge', [VisitController::class, 'saveDischarge']);
        $app->post('/visits/{id}/discharge/finalize', [VisitController::class, 'finalizeDischarge']);
        $app->post('/visits/{id}/diet', [VisitController::class, 'saveDiet']);
        $app->post('/visits/{id}/diet/share', [VisitController::class, 'shareDiet']);
        $app->post('/visits/{id}/photos', [VisitController::class, 'uploadPhoto']);

        $app->get('/settings/password', [SettingsController::class, 'showPassword']);
        $app->post('/settings/password', [SettingsController::class, 'updatePassword']);
        $app->get('/settings/sessions', [SettingsController::class, 'showSessions']);
        $app->post('/settings/sessions/revoke-all', [SettingsController::class, 'revokeOtherSessions']);
        $app->post('/settings/sessions/revoke/{id}', [SettingsController::class, 'revokeSession']);

        $app->get('/onboarding/plan-selection', [OnboardingController::class, 'planSelection']);
        $app->post('/onboarding/plan-selection', [OnboardingController::class, 'selectPlan']);
        $app->get('/onboarding/billing/success', [OnboardingController::class, 'billingSuccess']);
        $app->get('/onboarding/clinic-setup', [OnboardingController::class, 'clinicSetup']);
        $app->post('/onboarding/clinic-setup', [OnboardingController::class, 'saveClinicSetup']);
        $app->get('/onboarding/specialty-config', [OnboardingController::class, 'specialtyConfig']);
        $app->post('/onboarding/specialty-config', [OnboardingController::class, 'saveSpecialtyConfig']);
        $app->get('/onboarding/notifications', [OnboardingController::class, 'notifications']);
        $app->post('/onboarding/notifications', [OnboardingController::class, 'saveNotifications']);
        $app->get('/onboarding/complete', [OnboardingController::class, 'complete']);
        $app->post('/onboarding/complete', [OnboardingController::class, 'complete']);

        $app->get('/patients', [PatientController::class, 'index']);
        $app->get('/patients/new', [PatientController::class, 'create']);
        $app->post('/patients/new', [PatientController::class, 'store']);
        $app->get('/patients/{id}', [PatientController::class, 'show']);
        $app->get('/patients/{id}/edit', [PatientController::class, 'edit']);
        $app->post('/patients/{id}', [PatientController::class, 'update']);
        $app->post('/patients/{id}/regenerate-qr', [PatientController::class, 'regenerateQr']);
        $app->get('/patients/{id}/qr-card', [PatientController::class, 'qrCard']);
        $app->post('/patients/{id}/advance', [PatientController::class, 'recordAdvance']);
        $app->get('/patients/{id}/gdpr/export', [PatientController::class, 'exportGdpr']);
        $app->post('/patients/{id}/gdpr/anonymize', [PatientController::class, 'anonymizeGdpr']);
    });

    $router->group([
        'prefix' => '/api/v1',
        'middleware' => ['refresh', 'tenant', 'auth', 'rbac', 'module', 'rate'],
    ], static function (GroupedRouteRegistrar $api): void {
        $api->get('/ping', static fn () => \App\Http\Response::json(['pong' => true]));
        $api->get('/dashboard/queue', [DashboardController::class, 'queueApi']);
        $api->get('/patients/search', [PatientController::class, 'searchApi']);
        $api->get('/patients/check-phone', [PatientController::class, 'checkPhoneApi']);
        $api->get('/slots', [AppointmentController::class, 'slotsApi']);
        $api->get('/appointments/calendar', [AppointmentController::class, 'calendarApi']);
        $api->get('/queue', [QueueController::class, 'api']);
        $api->post('/visits/{id}/autosave', [VisitController::class, 'autosaveApi']);
        $api->get('/visits/{id}/tab/{tab}', [VisitController::class, 'tabApi']);
        $api->get('/drugs/search', [VisitController::class, 'drugsApi']);
        $api->get('/remedies/search', [VisitController::class, 'remediesApi']);
        $api->get('/icd10/search', [VisitController::class, 'icd10Api']);
        $api->get('/patients/{id}/vitals-chart', [VisitController::class, 'vitalsChartApi']);
        $api->get('/billing/{id}/razorpay-order', [BillingController::class, 'razorpayOrderApi']);
        $api->get('/billing/{id}/check-payment', [BillingController::class, 'checkPaymentApi']);
        $api->post('/billing/{id}/simulate-pay', [BillingController::class, 'simulatePayApi']);
        $api->get('/pharmacy/search', [PharmacyController::class, 'searchApi']);
    });

    $router->group(['middleware' => ['tenant', 'csrf', 'rate']], static function (GroupedRouteRegistrar $publicBook): void {
        $publicBook->get('/book/{slug}', [BookController::class, 'show']);
        $publicBook->post('/book/{slug}', [BookController::class, 'book']);
    });

    $router->group(['middleware' => ['tenant', 'rate']], static function (GroupedRouteRegistrar $publicApi): void {
        $publicApi->get('/book/{slug}/slots', [BookController::class, 'slotsApi']);
    });

    $router->group(['middleware' => ['tenant', 'rate']], static function (GroupedRouteRegistrar $publicQueue): void {
        $publicQueue->get('/queue/display', [QueueController::class, 'display']);
        $publicQueue->get('/lab/report/{token}', [LabReportController::class, 'show']);
    });

    $router->group([
        'prefix' => '/portal',
        'middleware' => ['tenant', 'csrf', 'rate'],
    ], static function (GroupedRouteRegistrar $portal): void {
        $portal->get('/', [PortalController::class, 'home']);
        $portal->get('/login', [PortalController::class, 'login']);
        $portal->post('/login/send-otp', [PortalController::class, 'sendOtp']);
        $portal->post('/login/verify', [PortalController::class, 'verifyOtp']);
        $portal->post('/logout', [PortalController::class, 'logout']);
        $portal->get('/dashboard', [PortalController::class, 'dashboard']);
        $portal->get('/download/{token}', [PortalController::class, 'download']);
        $portal->get('/discharge/{token}', [PortalController::class, 'discharge']);
    });

    $router->get('/impersonate/{token}', [ImpersonateController::class, 'enter']);

    $router->group(['middleware' => ['rate']], static function (GroupedRouteRegistrar $docs): void {
        $docs->get('/docs', [DocsController::class, 'index']);
        $docs->get('/docs/openapi.json', [DocsController::class, 'openapi']);
    });

    $router->group(['middleware' => ['rate']], static function (GroupedRouteRegistrar $directory): void {
        $directory->get('/doctors', [DirectoryController::class, 'index']);
        $directory->get('/doctors/{city}/{specialty}', [DirectoryController::class, 'citySpecialty']);
        $directory->get('/doctors/profile/{slug}', [DirectoryController::class, 'profile']);
    });

    $router->group([
        'prefix' => '/api/v1/rest',
        'middleware' => ['api_bearer', 'rate'],
    ], static function (GroupedRouteRegistrar $rest): void {
        $rest->get('/patients', [ApiV1Controller::class, 'patients']);
        $rest->get('/patients/{id}', [ApiV1Controller::class, 'patient']);
        $rest->post('/patients', [ApiV1Controller::class, 'createPatient']);
        $rest->get('/appointments', [ApiV1Controller::class, 'appointments']);
        $rest->get('/appointments/{id}', [ApiV1Controller::class, 'appointment']);
        $rest->post('/appointments', [ApiV1Controller::class, 'createAppointment']);
        $rest->get('/visits', [ApiV1Controller::class, 'visits']);
        $rest->get('/visits/{id}', [ApiV1Controller::class, 'visit']);
        $rest->get('/invoices', [ApiV1Controller::class, 'invoices']);
        $rest->get('/invoices/{id}', [ApiV1Controller::class, 'invoice']);
    });

    $router->group([
        'prefix' => '/admin',
        'middleware' => ['rate', 'csrf'],
    ], static function (GroupedRouteRegistrar $admin): void {
        $admin->get('/login', [SuperAdminController::class, 'showLogin']);
        $admin->post('/login', [SuperAdminController::class, 'login']);
        $admin->get('/', static fn () => \App\Http\Response::redirect('/admin/dashboard'));
    });

    $router->group([
        'prefix' => '/admin',
        'middleware' => ['rate', 'csrf', 'superadmin'],
    ], static function (GroupedRouteRegistrar $admin): void {
        $admin->get('/dashboard', [SuperAdminController::class, 'dashboard']);
        $admin->get('/clinics', [SuperAdminController::class, 'clinics']);
        $admin->post('/impersonate', [SuperAdminController::class, 'impersonate']);
        $admin->post('/logout', [SuperAdminController::class, 'logout']);
        $admin->get('/reviews', [SuperAdminController::class, 'reviews']);
        $admin->post('/reviews/approve', [SuperAdminController::class, 'approveReview']);
        $admin->post('/reviews/reject', [SuperAdminController::class, 'rejectReview']);
        $admin->post('/churn/run', [SuperAdminController::class, 'runChurn']);
    });

    $router->group([
        'prefix' => '/qr',
        'middleware' => ['tenant', 'rate'],
    ], static function (GroupedRouteRegistrar $qr): void {
        $qr->get('/{token}', [QrController::class, 'resolve']);
    });
};

<?php

declare(strict_types=1);

use App\Controllers\AcceptInviteController;
use App\Controllers\AppointmentController;
use App\Controllers\BillingController;
use App\Controllers\AuthController;
use App\Controllers\ClinicSettingsController;
use App\Controllers\QueueController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\PatientAuthController;
use App\Controllers\SubscriptionController;
use App\Controllers\PatientController;
use App\Controllers\PortalController;
use App\Controllers\PrescriptionController;
use App\Controllers\SettingsController;
use App\Controllers\VisitController;
use App\Controllers\AnalyticsController;
use App\Controllers\ReportController;
use App\Controllers\StaffController;
use App\Controllers\ApiV1Controller;
use App\Controllers\DirectoryController;
use App\Controllers\DocsController;
use App\Controllers\ImpersonateController;
use App\Controllers\SuperAdminController;
use App\Controllers\PartnerAdminController;
use App\Controllers\PartnerAuthController;
use App\Controllers\PartnerDashboardController;
use App\Controllers\TeamSettingsController;
use App\Controllers\SymptomsController;
use App\Controllers\FollowUpController;
use App\Controllers\DietTemplateController;
use App\Controllers\DoctorScheduleController;
use App\Controllers\HelpController;
use App\Controllers\MessagingAdminController;
use App\Controllers\MiscAdminController;
use App\Controllers\RecaptchaAdminController;
use App\Controllers\OnboardingController;
use App\Controllers\EmailTemplateAdminController;
use App\Controllers\SpecialtyAdminController;
use App\Controllers\LocationAdminController;
use App\Controllers\VitalsController;
use App\Controllers\WebhookController;
use App\Controllers\WordPressAdminController;
use App\Controllers\BlogController;
use App\Controllers\StoreAdminController;
use App\Controllers\StoreCatalogAdminController;
use App\Controllers\VendorProductController;
use App\Controllers\VendorAuthController;
use App\Controllers\VendorPortalController;
use App\Core\GroupedRouteRegistrar;
use App\Core\RouteRegistrar;

return static function (RouteRegistrar $router): void {
    $router->get('/health', [HealthController::class, 'index'], 'health');

    // Public landing page on the bare app domain (app.eclinicpro.com/).
    // If the visitor already has a session, send them straight to /dashboard.
    $router->get('/', [\App\Controllers\LandingController::class, 'index']);

    $router->get('/auth/google', [AuthController::class, 'googleRedirect']);
    $router->get('/auth/google/callback', [AuthController::class, 'googleCallback']);
    $router->get('/api/refresh-token', [AuthController::class, 'refreshToken']);

    $router->group(['middleware' => ['csrf', 'rate']], static function (GroupedRouteRegistrar $auth): void {
        $auth->get('/register', [AuthController::class, 'showRegister']);
        $auth->post('/register', [AuthController::class, 'register']);
        $auth->post('/register/send-otp', [AuthController::class, 'sendRegisterOtp']);
        $auth->post('/register/verify-otp', [AuthController::class, 'verifyRegisterOtp']);
        $auth->get('/login', [AuthController::class, 'showLogin']);
        $auth->post('/login', [AuthController::class, 'login']);
        $auth->post('/logout', [AuthController::class, 'logout']);
        $auth->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
        $auth->post('/forgot-password/send-otp', [AuthController::class, 'sendForgotPasswordOtp']);
        $auth->post('/forgot-password/verify-otp', [AuthController::class, 'verifyForgotPasswordOtp']);
        $auth->post('/forgot-password/reset', [AuthController::class, 'resetPasswordViaPhone']);
        $auth->get('/forgot-username', [AuthController::class, 'showForgotUsername']);
        $auth->post('/forgot-username', [AuthController::class, 'forgotUsername']);
        $auth->get('/reset-password/{token}', [AuthController::class, 'showResetPassword']);
        $auth->post('/reset-password/{token}', [AuthController::class, 'resetPassword']);
        $auth->get('/accept-invite/{token}', [AcceptInviteController::class, 'show']);
        $auth->post('/accept-invite/{token}', [AcceptInviteController::class, 'accept']);

        // Passwordless OTP login for doctors approved via the claim queue.
        $auth->get('/doctor/login',          [\App\Controllers\DoctorOtpLoginController::class, 'show']);
        $auth->post('/doctor/login/send',    [\App\Controllers\DoctorOtpLoginController::class, 'sendOtp']);
        $auth->post('/doctor/login/verify',  [\App\Controllers\DoctorOtpLoginController::class, 'verifyOtp']);
    });

    $router->get('/api/check-slug', [AuthController::class, 'checkSlug']);
    $router->get('/api/check-username', [AuthController::class, 'checkUsername']);

    $router->post('/webhooks/razorpay', [WebhookController::class, 'razorpay']);
    // Shiprocket tracking. The URL avoids the words "shiprocket"/"sr", which Shiprocket rejects in webhook URLs (VERIFY).
    $router->post('/webhooks/store-tracking', [WebhookController::class, 'storeTracking']);
    // Meta WhatsApp: GET = verify handshake, POST = delivery/inbound events.
    $router->get('/webhooks/whatsapp', [WebhookController::class, 'whatsapp']);
    $router->post('/webhooks/whatsapp', [WebhookController::class, 'whatsapp']);

    $router->group(['middleware' => ['refresh', 'tenant', 'auth', 'subscription', 'csrf', 'rbac', 'rate']], static function (GroupedRouteRegistrar $app): void {
        $app->get('/subscription-expired', [SubscriptionController::class, 'expired']);
        $app->get('/dashboard', [DashboardController::class, 'index']);
        $app->post('/dashboard/checklist/dismiss', [DashboardController::class, 'dismissChecklist']);

        $app->get('/prescriptions', [PrescriptionController::class, 'index']);
        $app->get('/prescriptions/{visitId}/pdf', [PrescriptionController::class, 'downloadPdf']);
        $app->get('/vitals', [VitalsController::class, 'index']);

        // Phase 4: follow-ups page + help page
        $app->get('/follow-ups', [FollowUpController::class, 'index']);
        $app->get('/help', [HelpController::class, 'index']);

        $app->get('/blogs', [BlogController::class, 'index']);
        $app->get('/blogs/new', [BlogController::class, 'create']);
        $app->get('/blogs/{id}/edit', [BlogController::class, 'edit']);
        $app->post('/blogs/save', [BlogController::class, 'save']);
        $app->post('/blogs/{id}/delete', [BlogController::class, 'delete']);
        $app->post('/blogs/{id}/publish', [BlogController::class, 'publish']);

        $app->get('/settings/leaves', static fn () => \App\Http\Response::redirect('/leaves'));
        $app->get('/settings', [ClinicSettingsController::class, 'index']);

        // Promoted out of Settings into their own left-menu pages.
        // NOTE: '/billing' is patient billing (BillingController). The clinic's
        // own subscription/billing lives at '/subscription' to avoid the clash.
        $app->get('/leaves',       [ClinicSettingsController::class, 'leaves']);
        $app->get('/subscription', [ClinicSettingsController::class, 'billing']);
        // Backwards-compat for old tab deep-links.
        $app->get('/settings/subscription', static fn () => \App\Http\Response::redirect('/subscription'));
        $app->post('/settings/general', [ClinicSettingsController::class, 'saveGeneral']);
        $app->post('/settings/general/phone/send-otp', [ClinicSettingsController::class, 'sendGeneralPhoneOtp']);
        $app->post('/settings/general/phone/verify-otp', [ClinicSettingsController::class, 'verifyGeneralPhoneOtp']);
        $app->post('/settings/services', [ClinicSettingsController::class, 'saveServices']);
        $app->post('/settings/hours', [ClinicSettingsController::class, 'saveHours']);
        $app->post('/settings/specialty', [ClinicSettingsController::class, 'saveSpecialty']);
        $app->post('/settings/notifications', [ClinicSettingsController::class, 'saveNotifications']);
        $app->post('/settings/test-whatsapp', [ClinicSettingsController::class, 'testWhatsApp']);
        $app->post('/settings/test-razorpay', [ClinicSettingsController::class, 'testRazorpay']);
        $app->post('/settings/leaves', [ClinicSettingsController::class, 'saveLeave']);
        $app->post('/settings/leaves/{id}/remove', [ClinicSettingsController::class, 'removeLeave']);
        $app->get('/settings/team', [TeamSettingsController::class, 'index']);
        $app->post('/settings/team/invite', [TeamSettingsController::class, 'inviteStaff']);
        $app->post('/settings/team/create', [TeamSettingsController::class, 'createStaffAccount']);
        $app->post('/settings/team/invites/{id}/revoke', [TeamSettingsController::class, 'revokeInvite']);
        $app->post('/settings/team/{id}/reset-password', [TeamSettingsController::class, 'resetStaffPassword']);
        $app->post('/settings/team/{id}', [TeamSettingsController::class, 'updateStaff']);
        $app->get('/change-password', [AuthController::class, 'showChangePassword']);
        $app->post('/change-password', [AuthController::class, 'changePassword']);
        $app->post('/settings/api/keys', [ClinicSettingsController::class, 'createApiKey']);
        $app->post('/settings/api/keys/{id}/revoke', [ClinicSettingsController::class, 'revokeApiKey']);
        $app->post('/settings/branding', [ClinicSettingsController::class, 'saveBranding']);
        $app->get('/settings/invoices/{id}/pdf', [ClinicSettingsController::class, 'downloadSaasInvoice']);
        $app->post('/settings/branding/domain', [ClinicSettingsController::class, 'startDomainVerify']);
        $app->post('/settings/branding/domain/check', [ClinicSettingsController::class, 'checkDomainVerify']);
        $app->post('/impersonate/exit', [ImpersonateController::class, 'exit']);

        // Income / GST report — available wherever Patient Bills is.
        $app->get('/reports/income', [ReportController::class, 'income']);
        $app->get('/reports/income/export', [ReportController::class, 'incomeCsv']);

        $app->get('/analytics', [AnalyticsController::class, 'index']);
        $app->post('/analytics/expenses', [AnalyticsController::class, 'storeExpense']);
        $app->get('/analytics/export/excel', [AnalyticsController::class, 'exportExcel']);
        $app->get('/analytics/export/tally', [AnalyticsController::class, 'exportTally']);

        $app->get('/staff/attendance', [StaffController::class, 'attendance']);
        $app->post('/staff/attendance/clock-in', [StaffController::class, 'clockIn']);
        $app->post('/staff/attendance/clock-out', [StaffController::class, 'clockOut']);
        $app->get('/staff/leaves', [StaffController::class, 'leaves']);
        $app->post('/staff/leaves', [StaffController::class, 'requestLeave']);
        $app->post('/staff/leaves/{id}/approve', [StaffController::class, 'approveLeave']);
        $app->post('/staff/leaves/{id}/reject', [StaffController::class, 'rejectLeave']);

        $app->get('/billing', [BillingController::class, 'index']);
        $app->get('/billing/export/excel', [BillingController::class, 'exportExcel']);
        $app->get('/billing/export/tally', [BillingController::class, 'exportTally']);
        $app->get('/billing/{id}', [BillingController::class, 'show']);
        $app->get('/billing/{id}/pdf', [BillingController::class, 'downloadPdf']);
        $app->post('/billing/{id}', [BillingController::class, 'update']);
        $app->post('/billing/{id}/pay-cash', [BillingController::class, 'payCash']);
        $app->post('/billing/{id}/payment', [BillingController::class, 'recordPayment']);

        $app->get('/appointments', [AppointmentController::class, 'index']);
        $app->get('/appointments/new', [AppointmentController::class, 'create']);
        $app->post('/appointments/new', [AppointmentController::class, 'store']);
        // Must precede /appointments/{id} so "calendar" isn't captured as an id.
        $app->get('/appointments/calendar', [AppointmentController::class, 'calendar']);
        $app->get('/appointments/{id}', [AppointmentController::class, 'edit']);
        $app->get('/appointments/{id}/edit', [AppointmentController::class, 'edit']);
        $app->post('/appointments/{id}', [AppointmentController::class, 'update']);
        $app->post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
        $app->get('/appointments/{id}/slip', [AppointmentController::class, 'slip']);

        $app->get('/doctor/schedule', [DoctorScheduleController::class, 'index']);
        $app->post('/doctor/schedule/hours', [DoctorScheduleController::class, 'saveHours']);
        $app->post('/doctor/schedule/leaves', [DoctorScheduleController::class, 'saveLeave']);
        $app->post('/doctor/schedule/leaves/{id}/remove', [DoctorScheduleController::class, 'removeLeave']);

        $app->get('/queue', [QueueController::class, 'index']);
        $app->post('/queue/{id}/status', [QueueController::class, 'updateStatus']);
        $app->post('/queue/call-next', [QueueController::class, 'callNext']);

        $app->get('/visits', [VisitController::class, 'index']);
        $app->get('/visits/new', [VisitController::class, 'start']);
        $app->get('/visits/{id}', [VisitController::class, 'show']);
        $app->post('/visits/{id}/complete', [VisitController::class, 'complete']);
        $app->get('/visits/{id}/unlock', [VisitController::class, 'unlockGet']);
        $app->post('/visits/{id}/unlock', [VisitController::class, 'unlock']);
        $app->post('/visits/{id}/diet', [VisitController::class, 'saveDiet']);
        $app->post('/visits/{id}/diet/share', [VisitController::class, 'shareDiet']);
        $app->post('/visits/{id}/send-rx', [VisitController::class, 'sendRxWhatsApp']);

        $app->get('/settings/profile', [SettingsController::class, 'showProfile']);
        $app->post('/settings/profile', [SettingsController::class, 'updateProfile']);
        $app->get('/settings/password', [SettingsController::class, 'showPassword']);
        $app->post('/settings/password', [SettingsController::class, 'updatePassword']);
        $app->get('/settings/sessions', [SettingsController::class, 'showSessions']);
        $app->post('/settings/sessions/revoke-all', [SettingsController::class, 'revokeOtherSessions']);
        $app->post('/settings/sessions/revoke/{id}', [SettingsController::class, 'revokeSession']);

        // Registration steps 4–5 (Checkout → Payment). Needs the new account's
        // session, so it lives here rather than in the guest /register group.
        $app->get('/register/checkout', [\App\Controllers\SignupCheckoutController::class, 'show']);
        $app->post('/register/checkout/code', [\App\Controllers\SignupCheckoutController::class, 'code']);
        $app->post('/register/payment', [\App\Controllers\SignupCheckoutController::class, 'pay']);
        $app->get('/register/payment', static fn () => \App\Http\Response::redirect('/register/checkout'));

        $app->get('/onboarding/plan-selection', [OnboardingController::class, 'planSelection']);
        $app->post('/onboarding/plan-selection', [OnboardingController::class, 'selectPlan']);
        // Real gateway checkout (Razorpay) — used by onboarding + Settings.
        $app->post('/subscription/checkout', [SubscriptionController::class, 'checkout']);
        $app->get('/onboarding/billing/success', [OnboardingController::class, 'billingSuccess']);
        $app->get('/onboarding/billing/razorpay-return', [OnboardingController::class, 'razorpayReturn']);
        $app->get('/onboarding/clinic-setup', [OnboardingController::class, 'clinicSetup']);
        $app->post('/onboarding/clinic-setup', [OnboardingController::class, 'saveClinicSetup']);
        $app->post('/onboarding/clinic-setup/draft', [OnboardingController::class, 'draftClinicSetup']);
        $app->get('/onboarding/specialty-config', [OnboardingController::class, 'specialtyConfig']);
        $app->post('/onboarding/specialty-config', [OnboardingController::class, 'saveSpecialtyConfig']);
        $app->post('/onboarding/specialty-config/draft', [OnboardingController::class, 'draftSpecialtyConfig']);
        $app->get('/onboarding/notifications', [OnboardingController::class, 'notifications']);
        $app->post('/onboarding/notifications', [OnboardingController::class, 'saveNotifications']);
        $app->post('/onboarding/notifications/draft', [OnboardingController::class, 'draftNotifications']);
        $app->get('/onboarding/complete', [OnboardingController::class, 'complete']);
        $app->post('/onboarding/complete', [OnboardingController::class, 'complete']);

        // "Listed on eClinicPro" — single page for the whole directory
        // lifecycle: apply, status/reason, re-apply, and edit the live profile.
        $app->get('/listing',        [\App\Controllers\GetListedController::class, 'show']);
        $app->post('/listing/apply', [\App\Controllers\GetListedController::class, 'submit']);
        $app->post('/listing/save',  [\App\Controllers\GetListedController::class, 'save']);

        // Backwards-compat: old links / onboarding point at get-listed.
        $app->get('/onboarding/get-listed',  static fn () => \App\Http\Response::redirect('/listing'));
        $app->post('/onboarding/get-listed', [\App\Controllers\GetListedController::class, 'submit']);

        $app->get('/patients', [PatientController::class, 'index']);
        $app->get('/patients/new', [PatientController::class, 'create']);
        $app->post('/patients/new', [PatientController::class, 'store']);
        $app->get('/patients/{id}', [PatientController::class, 'show']);
        $app->get('/patients/{id}/edit', [PatientController::class, 'edit']);
        $app->post('/patients/{id}', [PatientController::class, 'update']);
        $app->post('/patients/{id}/advance', [PatientController::class, 'recordAdvance']);
        $app->get('/patients/{id}/gdpr/export', [PatientController::class, 'exportGdpr']);
        $app->post('/patients/{id}/gdpr/anonymize', [PatientController::class, 'anonymizeGdpr']);
    });

    $router->group([
        'prefix' => '/api/v1',
        // csrf: these run on session cookies, so cross-site POSTs were possible
        // (autosave, templates, billing). Token arrives via X-CSRF-Token header,
        // injected globally by the base layout's fetch() hook.
        'middleware' => ['refresh', 'tenant', 'auth', 'csrf', 'rbac', 'module', 'rate'],
    ], static function (GroupedRouteRegistrar $api): void {
        $api->get('/ping', static fn () => \App\Http\Response::json(['pong' => true]));
        $api->get('/dashboard/queue', [DashboardController::class, 'queueApi']);
        $api->get('/patients/search', [PatientController::class, 'searchApi']);
        $api->get('/patients/check-phone', [PatientController::class, 'checkPhoneApi']);
        $api->get('/slots', [AppointmentController::class, 'slotsApi']);
        $api->get('/appointments/list', [AppointmentController::class, 'listApi']);
        $api->get('/appointments/calendar', [AppointmentController::class, 'calendarApi']);
        $api->post('/appointments/{id}/status', [AppointmentController::class, 'statusApi']);
        $api->post('/appointments/book', [AppointmentController::class, 'bookApi']);
        $api->get('/queue', [QueueController::class, 'api']);
        $api->post('/visits/{id}/autosave', [VisitController::class, 'autosaveApi']);
        $api->post('/visits/{id}/immunizations/given', [VisitController::class, 'markImmunizationGiven']);
        $api->get('/patients/{id}/immunizations', [PatientController::class, 'immunizationsApi']);
        $api->post('/patients/{id}/immunizations', [PatientController::class, 'immunizationsApi']);
        $api->get('/visits/{id}/summary', [VisitController::class, 'summaryApi']);
        $api->post('/visits/{id}/charges', [VisitController::class, 'saveCharges']);
        $api->get('/visits/{id}/tab/{tab}', [VisitController::class, 'tabApi']);
        // Phase 2: "Same as last visit" — GET previews, POST applies.
        $api->get('/visits/{id}/last-visit', [VisitController::class, 'cloneLastVisit']);
        $api->post('/visits/{id}/clone-last', [VisitController::class, 'cloneLastVisit']);
        // Phase 2: visible_modules toggle + section state memory.
        $api->post('/clinic-settings/modules/{moduleKey}', [ClinicSettingsController::class, 'toggleModule']);
        $api->post('/clinic-settings/section-state', [ClinicSettingsController::class, 'recordSectionState']);

        // Phase 3: Symptoms autocomplete + visit symptoms CRUD
        $api->get('/symptoms/search', [SymptomsController::class, 'searchApi']);
        $api->get('/symptoms/by-category', [SymptomsController::class, 'byCategory']);
        $api->get('/visits/{id}/symptoms', [SymptomsController::class, 'listForVisit']);
        $api->post('/visits/{id}/symptoms', [SymptomsController::class, 'saveForVisit']);

        // Phase 3: Prescription templates + drug/remedy autocomplete (canonical homes)
        $api->get('/prescriptions/templates', [PrescriptionController::class, 'templatesIndex']);
        $api->get('/prescriptions/templates/{id}', [PrescriptionController::class, 'templateShow']);
        $api->post('/prescriptions/templates', [PrescriptionController::class, 'templateCreate']);
        $api->post('/prescriptions/templates/{id}/apply/{visitId}', [PrescriptionController::class, 'templateApply']);
        $api->post('/prescriptions/templates/{id}/activate', [PrescriptionController::class, 'templateActivate']);
        $api->post('/prescriptions/templates/{id}/delete', [PrescriptionController::class, 'templateDelete']);

        // Phase 4: follow-ups
        $api->post('/visits/{id}/follow-up', [FollowUpController::class, 'saveForVisit']);
        $api->get('/follow-ups/dashboard', [FollowUpController::class, 'dashboardApi']);
        $api->post('/follow-ups/{id}/complete', [FollowUpController::class, 'complete']);
        $api->post('/follow-ups/{id}/reschedule', [FollowUpController::class, 'reschedule']);
        $api->post('/follow-ups/{id}/cancel', [FollowUpController::class, 'cancel']);

        // Phase 4: diet templates
        $api->get('/diet-templates', [DietTemplateController::class, 'index']);
        $api->get('/diet-templates/{id}', [DietTemplateController::class, 'show']);
        $api->post('/diet-templates', [DietTemplateController::class, 'create']);
        $api->post('/visits/{visitId}/apply-diet/{id}', [DietTemplateController::class, 'applyToVisit']);
        $api->post('/diet-templates/{id}/delete', [DietTemplateController::class, 'delete']);

        $api->get('/drugs/search', [VisitController::class, 'drugsApi']);
        $api->get('/remedies/search', [VisitController::class, 'remediesApi']);
        $api->get('/icd10/search', [VisitController::class, 'icd10Api']);
        $api->get('/patients/{id}/vitals-chart', [VisitController::class, 'vitalsChartApi']);
        $api->get('/billing/{id}/razorpay-order', [BillingController::class, 'razorpayOrderApi']);
        $api->get('/billing/{id}/check-payment', [BillingController::class, 'checkPaymentApi']);
        $api->post('/billing/{id}/simulate-pay', [BillingController::class, 'simulatePayApi']);
    });

    $router->group(['middleware' => ['tenant', 'rate']], static function (GroupedRouteRegistrar $publicApi): void {
        // Patient identity OTP — shared with marketing site (ecp_pid cookie).
        $publicApi->get('/api/patient-auth/me', [PatientAuthController::class, 'me']);
        $publicApi->post('/api/patient-auth/send-otp', [PatientAuthController::class, 'sendOtp']);
        $publicApi->post('/api/patient-auth/verify-otp', [PatientAuthController::class, 'verifyOtp']);
        $publicApi->post('/api/patient-auth/logout', [PatientAuthController::class, 'logout']);
    });

    $router->group(['middleware' => ['tenant', 'rate']], static function (GroupedRouteRegistrar $publicQueue): void {
        $publicQueue->get('/queue/display', [QueueController::class, 'display']);
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
    });

    $router->get('/impersonate/exit', [ImpersonateController::class, 'exit']);
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
        $admin->get('/patients', [SuperAdminController::class, 'patients']);
        $admin->get('/patients/{id}', [SuperAdminController::class, 'patientDetail']);
        $admin->get('/signups', [SuperAdminController::class, 'signups']);
        $admin->get('/signups/{id}', [SuperAdminController::class, 'signupDetail']);
        $admin->post('/impersonate', [SuperAdminController::class, 'impersonate']);
        $admin->post('/logout', [SuperAdminController::class, 'logout']);
        $admin->get('/reviews', [SuperAdminController::class, 'reviews']);
        $admin->post('/reviews/approve', [SuperAdminController::class, 'approveReview']);
        $admin->post('/reviews/reject', [SuperAdminController::class, 'rejectReview']);
        $admin->post('/churn/run', [SuperAdminController::class, 'runChurn']);

        // Phase 1: tenant detail page + trial / addon controls
        $admin->get('/clinics/{id}', [SuperAdminController::class, 'clinicDetail']);
        $admin->post('/clinics/{id}/extend-trial', [SuperAdminController::class, 'extendTrial']);
        $admin->post('/clinics/{id}/plan', [SuperAdminController::class, 'setPlan']);
        $admin->post('/clinics/{id}/addon', [SuperAdminController::class, 'toggleAddon']);
        $admin->post('/clinics/{id}/delete', [SuperAdminController::class, 'deleteClinic']);

        // Phase 1: feature flag management
        $admin->get('/feature-flags', [SuperAdminController::class, 'featureFlags']);
        $admin->post('/feature-flags/{key}', [SuperAdminController::class, 'updateFeatureFlag']);

        // Read-only subscription payment gateway status (keys live in .env)
        $admin->get('/payment-gateway', [SuperAdminController::class, 'paymentGateway']);
        $admin->get('/email', [SuperAdminController::class, 'email']);
        $admin->post('/email/test', [SuperAdminController::class, 'testEmail']);

        // Admin-editable email template content
        $admin->get('/email-templates', [EmailTemplateAdminController::class, 'index']);
        $admin->post('/email-templates/test', [EmailTemplateAdminController::class, 'test']);
        $admin->post('/email-templates/{key}/reset', [EmailTemplateAdminController::class, 'reset']);
        $admin->post('/email-templates/{key}', [EmailTemplateAdminController::class, 'save']);

        // Phase 3: symptom promotions queue
        $admin->get('/symptom-promotions', [SymptomsController::class, 'promotionsIndex']);
        $admin->post('/symptom-promotions/promote', [SymptomsController::class, 'promote']);
        $admin->post('/symptom-promotions/ignore', [SymptomsController::class, 'ignore']);

        // Phase 3/4: cron triggers (call from system cron via authenticated POST)
        $admin->post('/cron/template-discovery', [SuperAdminController::class, 'runTemplateDiscovery']);
        $admin->post('/cron/followup-reminders', [SuperAdminController::class, 'runFollowUpReminders']);
        $admin->post('/cron/followup-mark-missed', [SuperAdminController::class, 'runFollowUpMarkMissed']);

        // Auth captcha control
        $admin->get('/recaptcha', [RecaptchaAdminController::class, 'index']);
        $admin->post('/recaptcha', [RecaptchaAdminController::class, 'save']);
        $admin->get('/misc', [MiscAdminController::class, 'index']);
        $admin->post('/misc', [MiscAdminController::class, 'save']);

        // Patient mobile app update check (api/mobile/v1/app_version.php)
        $admin->get('/app-versions', [\App\Controllers\AppVersionAdminController::class, 'index']);
        $admin->post('/app-versions', [\App\Controllers\AppVersionAdminController::class, 'save']);

        // WhatsApp/SMS messaging control centre
        $admin->get('/messaging', [MessagingAdminController::class, 'index']);
        $admin->post('/messaging/connection', [MessagingAdminController::class, 'saveConnection']);
        $admin->post('/messaging/template/{id}', [MessagingAdminController::class, 'saveTemplate']);
        $admin->post('/messaging/rule/{id}', [MessagingAdminController::class, 'saveRule']);
        $admin->post('/messaging/test', [MessagingAdminController::class, 'sendTest']);

        // Specialty catalog (single source of truth for portal + directory)
        $admin->get('/specialties', [SpecialtyAdminController::class, 'index']);
        $admin->post('/specialties', [SpecialtyAdminController::class, 'save']);
        $admin->post('/specialties/{id}/toggle', [SpecialtyAdminController::class, 'toggle']);

        // Lab test catalog (Thyrocare-sourced products, categories, coupons)
        $admin->get('/lab/products', [\App\Controllers\LabAdminController::class, 'products']);
        // Static segments must be declared BEFORE /lab/products/{id}, otherwise
        // the id route matches "cleanup"/"bulk-delete" and swallows them.
        $admin->get('/lab/products/cleanup', [\App\Controllers\LabAdminController::class, 'cleanup']);
        $admin->post('/lab/products/cleanup', [\App\Controllers\LabAdminController::class, 'cleanupRun']);
        $admin->post('/lab/products/bulk-delete', [\App\Controllers\LabAdminController::class, 'bulkDeleteProducts']);
        $admin->get('/lab/products/{id}', [\App\Controllers\LabAdminController::class, 'productDetail']);
        $admin->post('/lab/products/{id}', [\App\Controllers\LabAdminController::class, 'saveProduct']);
        $admin->post('/lab/products/{id}/toggle', [\App\Controllers\LabAdminController::class, 'toggleProduct']);
        $admin->post('/lab/products/{id}/feature', [\App\Controllers\LabAdminController::class, 'toggleFeatured']);
        $admin->post('/lab/products/{id}/delete', [\App\Controllers\LabAdminController::class, 'deleteProduct']);
        // Lab bookings (ops worklist). As with /lab/products above, the static
        // "export" segment MUST be declared before /lab/orders/{id} or the id
        // route matches "export" and swallows it.
        $admin->get('/lab/orders', [\App\Controllers\LabAdminController::class, 'orders']);
        $admin->get('/lab/orders/export', [\App\Controllers\LabAdminController::class, 'exportOrders']);
        $admin->get('/lab/orders/{id}', [\App\Controllers\LabAdminController::class, 'orderDetail']);
        $admin->post('/lab/orders/{id}', [\App\Controllers\LabAdminController::class, 'saveOrder']);
        $admin->get('/lab/categories', [\App\Controllers\LabAdminController::class, 'categories']);
        $admin->post('/lab/categories', [\App\Controllers\LabAdminController::class, 'saveCategory']);
        $admin->post('/lab/categories/{id}/toggle', [\App\Controllers\LabAdminController::class, 'toggleCategory']);
        $admin->get('/lab/coupons', [\App\Controllers\LabAdminController::class, 'coupons']);
        $admin->post('/lab/coupons', [\App\Controllers\LabAdminController::class, 'saveCoupon']);
        $admin->post('/lab/coupons/{id}/toggle', [\App\Controllers\LabAdminController::class, 'toggleCoupon']);

        // States & cities catalog (Listed on eClinicPro pickers)
        $admin->get('/locations', [LocationAdminController::class, 'index']);
        $admin->post('/locations/states', [LocationAdminController::class, 'saveState']);
        $admin->post('/locations/states/{id}/toggle', [LocationAdminController::class, 'toggleState']);
        $admin->post('/locations/cities', [LocationAdminController::class, 'saveCity']);
        $admin->post('/locations/cities/{id}/toggle', [LocationAdminController::class, 'toggleCity']);

        // Plan catalog (source of truth for pricing / onboarding / checkout)
        $admin->get('/plans', [\App\Controllers\PlanAdminController::class, 'index']);
        $admin->post('/plans', [\App\Controllers\PlanAdminController::class, 'save']);
        $admin->post('/plans/{id}/toggle', [\App\Controllers\PlanAdminController::class, 'toggle']);
        $admin->post('/plans/{id}/delete', [\App\Controllers\PlanAdminController::class, 'delete']);

        // Plan discount codes (applied on the signup checkout)
        $admin->get('/discounts', [\App\Controllers\DiscountAdminController::class, 'index']);
        $admin->post('/discounts', [\App\Controllers\DiscountAdminController::class, 'save']);
        $admin->post('/discounts/{id}/toggle', [\App\Controllers\DiscountAdminController::class, 'toggle']);
        $admin->post('/discounts/{id}/delete', [\App\Controllers\DiscountAdminController::class, 'delete']);

        // Subscription (plan) payments — who paid, Razorpay refs, renewals.
        $admin->get('/payments', [\App\Controllers\SubscriptionPaymentAdminController::class, 'index']);
        $admin->get('/payments/export', [\App\Controllers\SubscriptionPaymentAdminController::class, 'export']);
        $admin->get('/payments/{id}/pdf', [\App\Controllers\SubscriptionPaymentAdminController::class, 'pdf']);
        $admin->post('/payments/{id}/recheck', [\App\Controllers\SubscriptionPaymentAdminController::class, 'recheck']);

        // Master prescription templates (system-provided per specialty)
        $admin->get('/rx-templates', [\App\Controllers\MasterTemplateAdminController::class, 'index']);
        $admin->get('/rx-templates/new', [\App\Controllers\MasterTemplateAdminController::class, 'create']);
        $admin->get('/rx-templates/{id}', [\App\Controllers\MasterTemplateAdminController::class, 'edit']);
        $admin->post('/rx-templates/save', [\App\Controllers\MasterTemplateAdminController::class, 'save']);
        $admin->post('/rx-templates/{id}/toggle', [\App\Controllers\MasterTemplateAdminController::class, 'toggle']);
        $admin->post('/rx-templates/{id}/delete', [\App\Controllers\MasterTemplateAdminController::class, 'delete']);

        // Messaging crons
        $admin->post('/cron/notifications-process', [MessagingAdminController::class, 'runProcess']);
        $admin->post('/cron/leads-nudges', [MessagingAdminController::class, 'runLeadNudges']);
        $admin->post('/cron/leads-expire', [MessagingAdminController::class, 'runLeadExpire']);

        // Doctor claim + new-listing review queue
        $admin->get('/claims', [\App\Controllers\DoctorClaimController::class, 'index']);
        $admin->get('/claims/{id}', [\App\Controllers\DoctorClaimController::class, 'show']);
        $admin->post('/claims/approve', [\App\Controllers\DoctorClaimController::class, 'approve']);
        $admin->post('/claims/reject', [\App\Controllers\DoctorClaimController::class, 'reject']);
        $admin->post('/claims/duplicate', [\App\Controllers\DoctorClaimController::class, 'markDuplicate']);

        // Lead analytics (doctor acquisition funnel)
        $admin->get('/leads', [\App\Controllers\LeadAdminController::class, 'index']);
        $admin->get('/lead-settings', [\App\Controllers\LeadSettingsController::class, 'index']);
        $admin->post('/lead-settings', [\App\Controllers\LeadSettingsController::class, 'save']);
        $admin->post('/lead-settings/doctor-quota', [\App\Controllers\LeadSettingsController::class, 'saveDoctorQuota']);

        // Outreach worklist (convert non-joined clinics receiving leads)
        $admin->get('/outreach', [\App\Controllers\OutreachAdminController::class, 'index']);
        $admin->get('/outreach/export', [\App\Controllers\OutreachAdminController::class, 'exportCsv']);
        $admin->post('/outreach/status', [\App\Controllers\OutreachAdminController::class, 'saveStatus']);

        // WordPress blog access for doctors
        $admin->get('/wordpress-settings', [WordPressAdminController::class, 'settings']);
        $admin->post('/wordpress-settings/save', [WordPressAdminController::class, 'saveSettings']);
        $admin->post('/wordpress-settings/test', [WordPressAdminController::class, 'testConnection']);
        $admin->get('/wordpress-doctors', [WordPressAdminController::class, 'index']);
        $admin->post('/wordpress-doctors/grant', [WordPressAdminController::class, 'grantAccess']);
        $admin->post('/wordpress-doctors/revoke', [WordPressAdminController::class, 'revokeAccess']);

        // Partner / affiliate program management
        $admin->get('/partners', [PartnerAdminController::class, 'index']);
        $admin->post('/partner-settings', [PartnerAdminController::class, 'saveSettings']);
        $admin->get('/partner-payouts', [PartnerAdminController::class, 'payouts']);
        $admin->post('/partner-payouts/{id}/process', [PartnerAdminController::class, 'processPayout']);
        $admin->get('/partners/{id}', [PartnerAdminController::class, 'show']);
        $admin->post('/partners/{id}/approve', [PartnerAdminController::class, 'approve']);
        $admin->post('/partners/{id}/status', [PartnerAdminController::class, 'setStatus']);
        $admin->post('/partners/{id}/override', [PartnerAdminController::class, 'setOverride']);
        $admin->post('/partners/{id}/document', [PartnerAdminController::class, 'reviewDocument']);

        // Store marketplace — sellers + settings (chunk 1). Static segments
        // before {id} routes so "settings" etc. never match an id.
        $admin->get('/store', static fn () => \App\Http\Response::redirect('/admin/store/dashboard'));
        $admin->get('/store/dashboard', [\App\Controllers\StoreMerchAdminController::class, 'dashboard']);
        $admin->get('/store/coupons', [\App\Controllers\StoreMerchAdminController::class, 'coupons']);
        $admin->post('/store/coupons', [\App\Controllers\StoreMerchAdminController::class, 'saveCoupon']);
        $admin->get('/store/rewards', [\App\Controllers\StoreRewardsAdminController::class, 'index']);
        $admin->post('/store/rewards/settings', [\App\Controllers\StoreRewardsAdminController::class, 'saveSettings']);
        $admin->post('/store/rewards/adjust', [\App\Controllers\StoreRewardsAdminController::class, 'adjust']);
        $admin->post('/store/rewards/referral/{id}', [\App\Controllers\StoreRewardsAdminController::class, 'rejectReferral']);
        $admin->get('/store/reviews', [\App\Controllers\StoreMerchAdminController::class, 'reviews']);
        $admin->post('/store/reviews/{id}', [\App\Controllers\StoreMerchAdminController::class, 'moderateReview']);
        $admin->get('/store/banners', [\App\Controllers\StoreMerchAdminController::class, 'banners']);
        $admin->post('/store/banners', [\App\Controllers\StoreMerchAdminController::class, 'saveBanner']);
        $admin->get('/store/email', [\App\Controllers\StoreEmailAdminController::class, 'settings']);
        $admin->post('/store/email', [\App\Controllers\StoreEmailAdminController::class, 'saveSettings']);
        $admin->get('/store/email-templates', [\App\Controllers\StoreEmailAdminController::class, 'templates']);
        $admin->get('/store/email-templates/{key}/preview', [\App\Controllers\StoreEmailAdminController::class, 'preview']);
        $admin->post('/store/email-templates/{key}/reset', [\App\Controllers\StoreEmailAdminController::class, 'resetTemplate']);
        $admin->post('/store/email-templates/{key}', [\App\Controllers\StoreEmailAdminController::class, 'saveTemplate']);
        $admin->get('/store/settings', [StoreAdminController::class, 'settings']);
        $admin->post('/store/settings', [StoreAdminController::class, 'saveSettings']);
        $admin->get('/store/vendors', [StoreAdminController::class, 'vendors']);
        $admin->get('/store/vendors/{id}', [StoreAdminController::class, 'vendorDetail']);
        $admin->get('/store/vendors/{id}/terms-certificate', [\App\Controllers\StorePolicyController::class, 'adminCertificate']);
        $admin->post('/store/vendors/{id}/status', [StoreAdminController::class, 'vendorStatus']);
        $admin->post('/store/vendors/{id}/reset-password', [StoreAdminController::class, 'resetPassword']);
        $admin->post('/store/vendors/{id}/feature', [StoreAdminController::class, 'toggleFeatured']);
        $admin->post('/store/vendors/{id}/commission', [StoreAdminController::class, 'vendorCommission']);
        $admin->get('/store/documents/{id}/file', [StoreAdminController::class, 'documentFile']);
        $admin->post('/store/documents/{id}/review', [StoreAdminController::class, 'reviewDocument']);
        $admin->post('/store/bank/{id}/verify', [StoreAdminController::class, 'verifyBank']);
        $admin->post('/store/bank/{id}/reveal', [StoreAdminController::class, 'revealBank']);

        // Store catalog moderation (chunk 2)
        $admin->get('/store/products', [StoreCatalogAdminController::class, 'products']);
        $admin->get('/store/products/{id}', [StoreCatalogAdminController::class, 'productDetail']);
        $admin->post('/store/products/{id}/decision', [StoreCatalogAdminController::class, 'productDecision']);
        $admin->post('/store/products/{id}/feature', [StoreCatalogAdminController::class, 'productFeature']);
        $admin->get('/store/categories', [StoreCatalogAdminController::class, 'categories']);
        $admin->post('/store/categories/{id}', [StoreCatalogAdminController::class, 'saveCategory']);
        $admin->get('/store/brands', [StoreCatalogAdminController::class, 'brands']);
        $admin->post('/store/brands', [StoreCatalogAdminController::class, 'saveBrand']);

        // Store orders (P5)
        $admin->get('/store/orders', [\App\Controllers\StoreOrderAdminController::class, 'index']);
        $admin->get('/store/orders/{id}', [\App\Controllers\StoreOrderAdminController::class, 'show']);
        $admin->post('/store/orders/{id}/cancel', [\App\Controllers\StoreOrderAdminController::class, 'cancel']);
        $admin->post('/store/orders/{id}/recheck', [\App\Controllers\StoreOrderAdminController::class, 'recheck']);
        $admin->post('/store/orders/{id}/cancel-items', [\App\Controllers\StoreOrderAdminController::class, 'cancelItems']);
        $admin->post('/store/orders/{id}/packages/{voId}/ship', [\App\Controllers\StoreOrderAdminController::class, 'shipBook']);
        $admin->post('/store/orders/{id}/packages/{voId}/mark-shipped', [\App\Controllers\StoreOrderAdminController::class, 'markShipped']);
        $admin->post('/store/orders/{id}/packages/{voId}/mark-delivered', [\App\Controllers\StoreOrderAdminController::class, 'markDelivered']);
        $admin->post('/store/orders/{id}/packages/{voId}/undelivered', [\App\Controllers\StoreOrderAdminController::class, 'refundUndelivered']);
        $admin->post('/store/orders/{id}/packages/{voId}/invoice', [\App\Controllers\StoreOrderAdminController::class, 'issueInvoice']);
        $admin->get('/store/orders/{id}/packages/{voId}/parcel-photo', [\App\Controllers\StoreOrderAdminController::class, 'parcelPhoto']);
        $admin->post('/store/orders/{id}/packages/{voId}/charge', [\App\Controllers\StoreOrderAdminController::class, 'charge']);
        $admin->post('/store/orders/{id}/packages/{voId}/charges/{ledgerId}/reverse', [\App\Controllers\StoreOrderAdminController::class, 'reverseCharge']);
        $admin->get('/store/disputes', [\App\Controllers\StoreOrderAdminController::class, 'disputes']);
        $admin->post('/store/disputes/{id}/{action}', [\App\Controllers\StoreOrderAdminController::class, 'resolveDispute']);
        $admin->post('/store/orders/{id}/packages/{voId}/courier-charge', [\App\Controllers\StoreOrderAdminController::class, 'courierCharge']);
        // GST documents + seller policies (P11b)
        $admin->get('/store/hsn', [StoreCatalogAdminController::class, 'hsn']);
        $admin->post('/store/hsn', [StoreCatalogAdminController::class, 'saveHsn']);
        $admin->get('/store/gst', [\App\Controllers\StoreTaxController::class, 'adminRegister']);
        $admin->get('/store/gst/export', [\App\Controllers\StoreTaxController::class, 'adminCsv']);
        $admin->get('/store/gst/documents/{id}', [\App\Controllers\StoreTaxController::class, 'adminDocument']);
        $admin->get('/store/accounts', [\App\Controllers\StoreAccountsController::class, 'index']);
        $admin->get('/store/accounts/export', [\App\Controllers\StoreAccountsController::class, 'export']);
        $admin->get('/store/accounts/ledger', [\App\Controllers\StoreAccountsController::class, 'ledgerExport']);
        $admin->get('/store/accounts/invoices/export', [\App\Controllers\StoreAccountsController::class, 'invoicesExport']);
        $admin->post('/store/accounts/invoices', [\App\Controllers\StoreAccountsController::class, 'issueInvoices']);
        $admin->get('/store/seller-invoices/{id}', [\App\Controllers\StoreAccountsController::class, 'adminInvoice']);
        $admin->get('/store/policies/{slug}', [\App\Controllers\StorePolicyController::class, 'adminEdit']);
        $admin->post('/store/policies/{slug}', [\App\Controllers\StorePolicyController::class, 'adminSave']);

        // Payouts & reports (P9)
        $admin->get('/store/payouts', [\App\Controllers\StorePayoutAdminController::class, 'index']);
        $admin->post('/store/payouts/batch', [\App\Controllers\StorePayoutAdminController::class, 'createBatch']);
        $admin->post('/store/payouts/adjust', [\App\Controllers\StorePayoutAdminController::class, 'adjust']);
        $admin->get('/store/payouts/{id}', [\App\Controllers\StorePayoutAdminController::class, 'show']);
        $admin->get('/store/payouts/{id}/statement', [\App\Controllers\StorePayoutAdminController::class, 'statement']);
        $admin->post('/store/payouts/{id}/{action}', [\App\Controllers\StorePayoutAdminController::class, 'transition']);
        $admin->get('/store/reports', [\App\Controllers\StorePayoutAdminController::class, 'report']);

        // Returns (P10)
        $admin->get('/store/returns', [\App\Controllers\StoreReturnController::class, 'adminIndex']);
        $admin->get('/store/returns/{id}', [\App\Controllers\StoreReturnController::class, 'adminShow']);
        $admin->get('/store/returns/{id}/photo/{n}', [\App\Controllers\StoreReturnController::class, 'adminPhoto']);
        $admin->post('/store/returns/{id}/{action}', [\App\Controllers\StoreReturnController::class, 'adminAction']);
        $admin->post('/store/orders/{id}/shipments/{shipmentId}/cancel', [\App\Controllers\StoreOrderAdminController::class, 'shipCancel']);
        $admin->post('/store/orders/{id}/shipments/{shipmentId}/refresh', [\App\Controllers\StoreOrderAdminController::class, 'shipRefresh']);
    });

    // Store marketplace — seller portal. Own guard (mc_vendor_token, path /vendor),
    // separate from clinic users, admins and partners.
    $router->get('/vendor', static fn () => \App\Http\Response::redirect('/vendor/dashboard'));
    $router->group([
        'prefix' => '/vendor',
        'middleware' => ['rate', 'csrf', 'vendor'],
    ], static function (GroupedRouteRegistrar $vendor): void {
        // Public (VendorAuthMiddleware lets exactly these two paths through)
        $vendor->get('/login', [VendorAuthController::class, 'showLogin']);
        $vendor->post('/login', [VendorAuthController::class, 'login']);
        $vendor->get('/register', [VendorAuthController::class, 'showRegister']);
        $vendor->post('/register', [VendorAuthController::class, 'register']);
        $vendor->get('/forgot-password', [VendorAuthController::class, 'showForgot']);
        $vendor->post('/forgot-password', [VendorAuthController::class, 'forgot']);
        $vendor->get('/reset-password', [VendorAuthController::class, 'showReset']);
        $vendor->post('/reset-password', [VendorAuthController::class, 'reset']);
        $vendor->post('/logout', [VendorAuthController::class, 'logout']);

        // Authenticated onboarding
        $vendor->get('/dashboard', [VendorPortalController::class, 'dashboard']);
        $vendor->post('/submit', [VendorPortalController::class, 'submit']);
        $vendor->get('/profile', [VendorPortalController::class, 'profile']);
        $vendor->post('/profile', [VendorPortalController::class, 'saveProfile']);
        $vendor->get('/addresses', [VendorPortalController::class, 'addresses']);
        $vendor->post('/addresses', [VendorPortalController::class, 'addAddress']);
        $vendor->post('/addresses/{id}/remove', [VendorPortalController::class, 'removeAddress']);
        $vendor->get('/bank', [VendorPortalController::class, 'bank']);
        $vendor->post('/bank', [VendorPortalController::class, 'saveBank']);
        $vendor->get('/documents', [VendorPortalController::class, 'documents']);
        $vendor->post('/documents', [VendorPortalController::class, 'uploadDocument']);
        $vendor->get('/documents/{id}/file', [VendorPortalController::class, 'documentFile']);

        // Products (chunk 2). "new" is declared before {id}.
        $vendor->get('/products', [VendorProductController::class, 'index']);
        $vendor->get('/products/new', [VendorProductController::class, 'create']);
        $vendor->post('/products', [VendorProductController::class, 'store']);
        $vendor->get('/products/{id}', [VendorProductController::class, 'edit']);
        $vendor->post('/products/{id}', [VendorProductController::class, 'update']);
        $vendor->post('/products/{id}/images', [VendorProductController::class, 'uploadImages']);
        $vendor->post('/products/{id}/images/{imageId}/delete', [VendorProductController::class, 'deleteImage']);
        $vendor->post('/products/{id}/images/{imageId}/cover', [VendorProductController::class, 'coverImage']);
        $vendor->post('/products/{id}/submit', [VendorProductController::class, 'submit']);
        $vendor->post('/products/{id}/archive', [VendorProductController::class, 'archive']);

        // Orders (read-only in P6; actions in P7)
        $vendor->get('/orders', [\App\Controllers\VendorOrderController::class, 'index']);
        $vendor->get('/orders/{id}', [\App\Controllers\VendorOrderController::class, 'show']);
        $vendor->post('/orders/{id}/accept', [\App\Controllers\VendorOrderController::class, 'accept']);
        $vendor->post('/orders/{id}/packed', [\App\Controllers\VendorOrderController::class, 'packed']);
        $vendor->post('/orders/{id}/cancel', [\App\Controllers\VendorOrderController::class, 'cancelItems']);
        $vendor->post('/orders/{id}/book', [\App\Controllers\VendorOrderController::class, 'book']);
        $vendor->get('/orders/{id}/parcel-photo', [\App\Controllers\VendorOrderController::class, 'parcelPhoto']);
        $vendor->post('/orders/{id}/charges/{ledgerId}/dispute', [\App\Controllers\VendorOrderController::class, 'dispute']);
        $vendor->post('/orders/{id}/invoice', [\App\Controllers\StoreTaxController::class, 'vendorIssueInvoice']);
        $vendor->get('/gst', [\App\Controllers\StoreTaxController::class, 'vendorRegister']);
        $vendor->get('/gst/export', [\App\Controllers\StoreTaxController::class, 'vendorCsv']);
        $vendor->get('/gst/documents/{id}', [\App\Controllers\StoreTaxController::class, 'vendorDocument']);
        $vendor->get('/gst/eclinicpro-invoices/{id}', [\App\Controllers\StoreAccountsController::class, 'vendorInvoice']);
        $vendor->get('/terms', [\App\Controllers\StorePolicyController::class, 'vendorShow']);
        $vendor->post('/terms/accept', [\App\Controllers\StorePolicyController::class, 'vendorAccept']);
        $vendor->get('/terms/certificate', [\App\Controllers\StorePolicyController::class, 'vendorCertificate']);

        // Reviews (P11)
        $vendor->get('/reviews', [VendorPortalController::class, 'reviews']);
        $vendor->post('/reviews/{id}/reply', [VendorPortalController::class, 'replyReview']);

        // Returns (P10)
        $vendor->get('/returns', [\App\Controllers\StoreReturnController::class, 'vendorIndex']);
        $vendor->get('/returns/{id}', [\App\Controllers\StoreReturnController::class, 'vendorShow']);
        $vendor->get('/returns/{id}/photo/{n}', [\App\Controllers\StoreReturnController::class, 'vendorPhoto']);
        $vendor->post('/returns/{id}/{action}', [\App\Controllers\StoreReturnController::class, 'vendorAction']);

        // Earnings & payouts (P9)
        $vendor->get('/payouts', [\App\Controllers\VendorPayoutController::class, 'index']);
        $vendor->post('/payouts/request', [\App\Controllers\VendorPayoutController::class, 'request']);
        $vendor->get('/payouts/{id}/statement', [\App\Controllers\VendorPayoutController::class, 'statement']);
    });

    // Partner program — public auth pages + guarded partner dashboard.
    // Separate guard (mc_partner_token) from clinic users and platform admins.
    $router->group([
        'prefix' => '/partner',
        'middleware' => ['rate', 'csrf', 'partner'],
    ], static function (GroupedRouteRegistrar $partner): void {
        // Public (the partner middleware lets /partner/login & /partner/register through)
        $partner->get('/login', [PartnerAuthController::class, 'showLogin']);
        $partner->post('/login', [PartnerAuthController::class, 'login']);
        $partner->get('/register', [PartnerAuthController::class, 'showRegister']);
        $partner->post('/register', [PartnerAuthController::class, 'register']);
        $partner->post('/logout', [PartnerAuthController::class, 'logout']);

        // Authenticated dashboard
        $partner->get('/dashboard', [PartnerDashboardController::class, 'dashboard']);
        $partner->get('/referrals', [PartnerDashboardController::class, 'referrals']);
        $partner->get('/earnings', [PartnerDashboardController::class, 'earnings']);
        $partner->post('/payout-request', [PartnerDashboardController::class, 'requestPayout']);
        $partner->post('/payout-details', [PartnerDashboardController::class, 'savePayoutDetails']);
        $partner->get('/documents', [PartnerDashboardController::class, 'documents']);
        $partner->post('/documents/upload', [PartnerDashboardController::class, 'uploadDocument']);
    });
};

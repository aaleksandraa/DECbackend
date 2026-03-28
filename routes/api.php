<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\Admin\ImportController;
use App\Http\Controllers\Api\Admin\HomepageCategoryController as AdminHomepageCategoryController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarFeedController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\HomepageCategoryController;
use App\Http\Controllers\Api\JobAdController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SalonController;
use App\Http\Controllers\Api\SalonSettingsController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceImageController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// API v1 routes
Route::prefix('v1')->group(function () {
    // Public auth routes - more reasonable rate limiting for login
    // Using web middleware to ensure session is available for Sanctum
    Route::middleware(['web'])->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:60,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:60,1');
    });

    // Email verification routes (public - user clicks link from email)
    // Using higher rate limit as browsers may prefetch links
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:60,1'])
        ->name('verification.verify');

    // Resend verification email (public - for users who can't login)
    // Note: This route MUST be accessible without authentication
    Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
        ->middleware(['throttle:6,1'])
        ->withoutMiddleware(['auth:sanctum']); // Explicitly allow unauthenticated access

    // Password reset routes (public)
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:6,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:6,1');

    // =============================================
    // CHATBOT API
    // - Direct Meta webhook (backend-first)
    // - Legacy n8n endpoint (backward compatibility)
    // =============================================
    Route::prefix('chatbot')->group(function () {
        Route::get('/webhook', [ChatbotController::class, 'verifyWebhook']);
        Route::post('/webhook', [ChatbotController::class, 'handleWebhook'])
            ->middleware('throttle:120,1');
        Route::post('/message', [ChatbotController::class, 'processMessage'])
            ->middleware('throttle:60,1');
    });

    // =============================================
    // PUBLIC ROUTES - No authentication required
    // =============================================

    // Widget API (public, with CORS for external domains)
    Route::prefix('widget')->middleware(['widget.cors'])->group(function () {
        // OPTIONS preflight handler
        Route::options('/{any}', fn() => response('', 204))->where('any', '.*');

        Route::middleware('throttle.redis:60,1')->group(function () {
            Route::get('/{salonSlug}', [\App\Http\Controllers\Api\WidgetController::class, 'show']);
            Route::post('/slots/available', [\App\Http\Controllers\Api\WidgetController::class, 'availableSlots']);
            Route::post('/dates/available', [\App\Http\Controllers\Api\WidgetController::class, 'availableDates']);
            Route::post('/debug/appointments', [\App\Http\Controllers\Api\WidgetController::class, 'debugAppointments']);
            Route::post('/book', [\App\Http\Controllers\Api\WidgetController::class, 'book']);
        });
    });

    Route::middleware('throttle.redis:120,1')->group(function () {
        // Existing salon public routes
        Route::get('/salons', [SalonController::class, 'index']);
        Route::get('/salons/nearest', [SalonController::class, 'nearest']);
        Route::get('/salons/{salon}', [SalonController::class, 'show']);
        Route::get('/salons/{salon}/services', [ServiceController::class, 'index']);
        Route::get('/salons/{salon}/services/by-category', [ServiceController::class, 'byCategory']);
        Route::get('/salons/{salon}/staff', [StaffController::class, 'index']);
        Route::get('/salons/{salon}/reviews', [ReviewController::class, 'index']);

        // NEW: SEO-friendly public routes
        Route::prefix('public')->group(function () {
            // City pages for SEO (e.g., /frizer-doboj)
            Route::get('/cities', [PublicController::class, 'cities']);
            Route::get('/cities/{citySlug}', [PublicController::class, 'salonsByCity']);

            // Salon by slug (e.g., /salon/studio-ana-doboj)
            Route::get('/salon/{slug}', [PublicController::class, 'salonBySlug']);

            // Public search
            Route::get('/search', [PublicController::class, 'search']);

            // Popular services for search suggestions
            Route::get('/services', [PublicController::class, 'popularServices']);

            // Public capacity endpoint for guest booking
            Route::get('/salons/{salon}/capacity/month', [AppointmentController::class, 'getPublicMonthCapacity']);

            // Available time slots (public)
            Route::get('/available-slots', [PublicController::class, 'availableSlots']);

            // Available time slots for multiple services (public)
            Route::post('/available-slots-multi', [PublicController::class, 'availableSlotsMulti']);

            // Available dates for a month (public - optimized single call)
            Route::post('/available-dates-month', [PublicController::class, 'availableDatesMonth']);

            // Guest booking (no auth required)
            Route::post('/book', [PublicController::class, 'storeGuestAppointment']);

            // Public ICS download (no auth required)
            Route::get('/appointments/{appointment}/ics', [AppointmentController::class, 'downloadIcs']);
            Route::get('/calendar/staff/{token}.ics', [CalendarFeedController::class, 'staffFeed']);

            // Sitemap for SEO
            Route::get('/sitemap', [PublicController::class, 'sitemap']);

            // Public analytics settings (for GA injection)
            Route::get('/analytics-settings', [SettingsController::class, 'getAnalytics']);

            // Public appearance settings
            Route::get('/appearance-settings', [SettingsController::class, 'getAppearance']);

            // Public featured salon
            Route::get('/featured-salon', [SettingsController::class, 'getFeaturedSalon']);

            // Public registration settings
            Route::get('/registration-settings', [SettingsController::class, 'getRegistrationSettings']);

            // Contact form
            Route::post('/contact', [\App\Http\Controllers\Api\ContactController::class, 'send'])
                ->middleware('throttle:5,1'); // 5 requests per minute

            // Locations (for salon registration dropdown)
            Route::get('/locations', [LocationController::class, 'index']);
            Route::get('/locations/grouped', [LocationController::class, 'grouped']);
            Route::get('/locations/cantons', [LocationController::class, 'cantons']);

            // Staff public profiles
            Route::get('/staff/{slug}', [\App\Http\Controllers\Api\PublicStaffController::class, 'show']);
            Route::get('/staff/{slug}/portfolio', [\App\Http\Controllers\Api\PublicStaffController::class, 'portfolio']);
            Route::get('/staff/{slug}/reviews', [\App\Http\Controllers\Api\PublicStaffController::class, 'reviews']);
            Route::get('/staff/{slug}/availability', [\App\Http\Controllers\Api\PublicStaffController::class, 'availability']);

            // Job ads (public)
            Route::get('/job-ads', [JobAdController::class, 'index']);
            Route::get('/job-ads/{id}', [JobAdController::class, 'show']);

            // Homepage categories (public)
            Route::get('/homepage-categories', [HomepageCategoryController::class, 'index']);

            // SEO meta tags (public)
            Route::get('/seo-meta', [PublicController::class, 'getSeoMeta']);
        });
    });

    // XML Sitemap (no throttling)
    Route::get('/sitemap.xml', [PublicController::class, 'sitemapXml']);



    // Protected routes with standard rate limiting (120 per minute)
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        // Auth routes
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/user/profile', [AuthController::class, 'updateProfile']);
        Route::put('/user/password', [AuthController::class, 'changePassword']);
        Route::post('/user/avatar', [AuthController::class, 'uploadAvatar']);
        // Note: /email/resend is defined as public route above (line 43)
        Route::get('/user/favorites', [FavoriteController::class, 'index']);
        Route::post('/user/favorites/{salon}', [FavoriteController::class, 'store']);
        Route::delete('/user/favorites/{salon}', [FavoriteController::class, 'destroy']);
        Route::get('/user/favorites/{salon}/check', [FavoriteController::class, 'check']);
        Route::get('/user/appointments', [AppointmentController::class, 'index']);

        // Dashboard routes (optimized with caching)
        Route::prefix('dashboard')->group(function () {
            Route::get('/salon/stats', [DashboardController::class, 'salonStats']);
            Route::get('/salon/today', [DashboardController::class, 'todayAppointments']);
            Route::get('/salon/analytics', [DashboardController::class, 'salonAnalytics']);
            Route::get('/staff/stats', [DashboardController::class, 'staffStats']);
            Route::post('/clear-cache', [DashboardController::class, 'clearCache']);
        });

        // Salon settings routes (daily reports, etc.)
        Route::prefix('salon/settings')->group(function () {
            Route::get('/', [SalonSettingsController::class, 'show']);
            Route::put('/', [SalonSettingsController::class, 'update']);
            Route::post('/test-report', [SalonSettingsController::class, 'sendTestReport']);
            Route::get('/preview-report', [SalonSettingsController::class, 'previewReport']);
            Route::get('/monthly-report', [SalonSettingsController::class, 'getMonthlyReport']);
            Route::post('/send-monthly-report', [SalonSettingsController::class, 'sendMonthlyReport']);
        });
        Route::post('/user/appointments', [AppointmentController::class, 'store']);
        Route::get('/user/appointments/{appointment}', [AppointmentController::class, 'show']);
        Route::put('/user/appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::delete('/user/appointments/{appointment}', [AppointmentController::class, 'destroy']);
        Route::put('/user/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::put('/salon/profile', [SalonController::class, 'updateProfile']);

        // Salon routes
        Route::post('/salons', [SalonController::class, 'store']);
        Route::put('/salons/{salon}', [SalonController::class, 'update']);
        Route::delete('/salons/{salon}', [SalonController::class, 'destroy']);
        Route::post('/salons/{salon}/images', [SalonController::class, 'uploadImages']);
        Route::delete('/salons/{salon}/images/{image}', [SalonController::class, 'deleteImage']);
        Route::put('/salons/{salon}/images/{image}/primary', [SalonController::class, 'setPrimaryImage']);
        Route::get('/salons/{salon}/available-slots', [SalonController::class, 'availableSlots']);
        Route::post('/salons/{salon}/available-slots-multi', [SalonController::class, 'availableSlotsMulti']);

        // Staff routes - reorder must come BEFORE {staff} parameter routes
        Route::post('/salons/{salon}/staff', [StaffController::class, 'store']);
        Route::post('/salons/{salon}/staff/reorder', [StaffController::class, 'reorder']);
        Route::get('/salons/{salon}/staff/{staff}', [StaffController::class, 'show']);
        Route::put('/salons/{salon}/staff/{staff}', [StaffController::class, 'update']);
        Route::delete('/salons/{salon}/staff/{staff}', [StaffController::class, 'destroy']);
        Route::post('/salons/{salon}/staff/{staff}/avatar', [StaffController::class, 'uploadAvatar']);
        Route::get('/salons/{salon}/staff/{staff}/schedule', [StaffController::class, 'schedule']);
        Route::get('/salons/{salon}/staff/{staff}/appointments', [StaffController::class, 'appointments']);

        // Staff self-update route
        Route::put('/staff/me/settings', [StaffController::class, 'updateOwnSettings']);
        Route::get('/staff/me/calendar-links', [CalendarFeedController::class, 'myLinks']);
        Route::post('/staff/me/calendar-links/regenerate', [CalendarFeedController::class, 'regenerate']);

        // Service routes - reorder must come BEFORE {service} parameter routes
        Route::post('/salons/{salon}/services', [ServiceController::class, 'store']);
        Route::post('/salons/{salon}/services/reorder', [ServiceController::class, 'reorder']);
        Route::get('/salons/{salon}/services/{service}', [ServiceController::class, 'show']);
        Route::put('/salons/{salon}/services/{service}', [ServiceController::class, 'update']);
        Route::delete('/salons/{salon}/services/{service}', [ServiceController::class, 'destroy']);

        // Service image routes
        Route::post('/services/{service}/images', [ServiceImageController::class, 'store']);
        Route::put('/services/{service}/images/{image}', [ServiceImageController::class, 'update']);
        Route::put('/services/{service}/images/reorder', [ServiceImageController::class, 'reorder']);
        Route::delete('/services/{service}/images/{image}', [ServiceImageController::class, 'destroy']);

        // Appointment routes
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/appointments/capacity/month', [AppointmentController::class, 'getMonthCapacity']);
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
        Route::get('/appointments/{appointment}/ics', [AppointmentController::class, 'downloadIcs']);
        Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
        Route::put('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::put('/appointments/{appointment}/no-show', [AppointmentController::class, 'markNoShow']);
        Route::put('/appointments/{appointment}/complete', [AppointmentController::class, 'markCompleted']);

        // Review routes
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::get('/reviews/{review}', [ReviewController::class, 'show']);
        Route::put('/reviews/{review}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
        Route::post('/reviews/{review}/response', [ReviewController::class, 'addResponse']);

        // Schedule routes
        Route::get('/salons/{salon}/breaks', [ScheduleController::class, 'getSalonBreaks']);
        Route::post('/salons/{salon}/breaks', [ScheduleController::class, 'storeSalonBreak']);
        Route::put('/salons/{salon}/breaks/{break}', [ScheduleController::class, 'updateSalonBreak']);
        Route::delete('/salons/{salon}/breaks/{break}', [ScheduleController::class, 'deleteSalonBreak']);

        Route::get('/salons/{salon}/vacations', [ScheduleController::class, 'getSalonVacations']);
        Route::post('/salons/{salon}/vacations', [ScheduleController::class, 'storeSalonVacation']);
        Route::put('/salons/{salon}/vacations/{vacation}', [ScheduleController::class, 'updateSalonVacation']);
        Route::delete('/salons/{salon}/vacations/{vacation}', [ScheduleController::class, 'deleteSalonVacation']);

        Route::get('/staff/{staff}/breaks', [ScheduleController::class, 'getStaffBreaks']);
        Route::post('/staff/{staff}/breaks', [ScheduleController::class, 'storeStaffBreak']);
        Route::put('/staff/{staff}/breaks/{break}', [ScheduleController::class, 'updateStaffBreak']);
        Route::delete('/staff/{staff}/breaks/{break}', [ScheduleController::class, 'deleteStaffBreak']);

        Route::get('/staff/{staff}/vacations', [ScheduleController::class, 'getStaffVacations']);
        Route::post('/staff/{staff}/vacations', [ScheduleController::class, 'storeStaffVacation']);
        Route::put('/staff/{staff}/vacations/{vacation}', [ScheduleController::class, 'updateStaffVacation']);
        Route::delete('/staff/{staff}/vacations/{vacation}', [ScheduleController::class, 'deleteStaffVacation']);

        // Favorite routes
        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/favorites/{salon}', [FavoriteController::class, 'store']);
        Route::delete('/favorites/{salon}', [FavoriteController::class, 'destroy']);
        Route::get('/favorites/{salon}/check', [FavoriteController::class, 'check']);

        // Notification routes
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

        // Job ads for salon owners
        Route::prefix('owner/job-ads')->group(function () {
            Route::get('/', [JobAdController::class, 'ownerIndex']);
            Route::post('/', [JobAdController::class, 'ownerStore']);
            Route::put('/{id}', [JobAdController::class, 'ownerUpdate']);
            Route::delete('/{id}', [JobAdController::class, 'ownerDestroy']);
        });

        // Clients management for salon owners and staff
        Route::get('/clients', [ClientController::class, 'index']);
        Route::post('/clients/send-email', [ClientController::class, 'sendEmail']);
        Route::get('/clients/{clientId}', [ClientController::class, 'show']);

        // Social Integrations (Instagram/Facebook chatbot)
        Route::prefix('admin/social-integrations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Admin\SocialIntegrationController::class, 'index']);
            Route::get('/connect', [\App\Http\Controllers\Api\Admin\SocialIntegrationController::class, 'connect']);
            Route::get('/callback', [\App\Http\Controllers\Api\Admin\SocialIntegrationController::class, 'callback']);
            Route::get('/pending-pages', [\App\Http\Controllers\Api\Admin\SocialIntegrationController::class, 'pendingPages']);
            Route::post('/select-page', [\App\Http\Controllers\Api\Admin\SocialIntegrationController::class, 'selectPage']);
            Route::patch('/{id}', [\App\Http\Controllers\Api\Admin\SocialIntegrationController::class, 'update']);
            Route::post('/disconnect', [\App\Http\Controllers\Api\Admin\SocialIntegrationController::class, 'disconnect']);
        });

        // Widget management (admin only)
        Route::middleware('admin')->prefix('admin/widget')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\AdminWidgetController::class, 'index']);
            Route::get('/{salonId}', [\App\Http\Controllers\Api\AdminWidgetController::class, 'show']);
            Route::post('/{salonId}/generate', [\App\Http\Controllers\Api\AdminWidgetController::class, 'generateApiKey']);
            Route::put('/{salonId}/settings', [\App\Http\Controllers\Api\AdminWidgetController::class, 'updateSettings']);
            Route::delete('/{salonId}', [\App\Http\Controllers\Api\AdminWidgetController::class, 'destroy']);
            Route::get('/{salonId}/analytics', [\App\Http\Controllers\Api\AdminWidgetController::class, 'analytics']);
        });

        // Admin routes with extra security
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboardStats']);
            Route::get('/users', [AdminController::class, 'users']);
            Route::post('/users', [AdminController::class, 'createUser']);
            Route::put('/users/{user}', [AdminController::class, 'updateUser']);
            Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
            Route::post('/users/{user}/reset-password', [AdminController::class, 'resetUserPassword']);
            Route::post('/users/{user}/message', [AdminController::class, 'sendMessageToUser']);
            Route::get('/salons', [AdminController::class, 'salons']);
            Route::put('/salons/{salon}', [AdminController::class, 'updateSalon']);
            Route::put('/salons/{salon}/approve', [AdminController::class, 'approveSalon']);
            Route::put('/salons/{salon}/suspend', [AdminController::class, 'suspendSalon']);
            Route::get('/analytics', [AdminController::class, 'analytics']);
            Route::get('/social-integrations/health', [\App\Http\Controllers\Api\Admin\SocialIntegrationController::class, 'healthCheck']);

            // GDPR Consents management
            Route::get('/consents', [AdminController::class, 'consents']);
            Route::get('/consents/export', [AdminController::class, 'exportConsents']);
            Route::get('/users/{user}/consents', [AdminController::class, 'userConsents']);

            // System settings
            Route::get('/settings', [SettingsController::class, 'index']);
            Route::get('/settings/{group}', [SettingsController::class, 'getByGroup']);
            Route::put('/settings', [SettingsController::class, 'update']);

            // Gradient/Appearance settings
            Route::get('/gradient-presets', [SettingsController::class, 'getGradientPresets']);
            Route::put('/gradient', [SettingsController::class, 'updateGradient']);
            Route::put('/navbar-gradient', [SettingsController::class, 'updateNavbarGradient']);
            Route::put('/sticky-navbar', [SettingsController::class, 'updateStickyNavbar']);
            Route::put('/search-version', [SettingsController::class, 'updateSearchVersion']);

            // Bulk Import routes
            Route::prefix('import')->group(function () {
                Route::post('/upload', [ImportController::class, 'upload']);
                Route::get('/{importId}/preview', [ImportController::class, 'preview']);
                Route::post('/{importId}/validate', [ImportController::class, 'validate']);
                Route::post('/{importId}/process', [ImportController::class, 'process']);
                Route::get('/batch/{importBatchId}/status', [ImportController::class, 'status']);
                Route::get('/history', [ImportController::class, 'history']);
                Route::get('/batch/{importBatchId}/errors', [ImportController::class, 'downloadErrors']);
                Route::get('/template/{format}', [ImportController::class, 'downloadTemplate']);
            });

            // Salon profile layout settings
            Route::get('/salon-profile-layout', [SettingsController::class, 'getSalonProfileLayout']);
            Route::get('/salon-profile-layouts', [SettingsController::class, 'getSalonProfileLayoutOptions']);
            Route::put('/salon-profile-layout', [SettingsController::class, 'updateSalonProfileLayout']);

            // Featured salon settings
            Route::get('/featured-salon', [SettingsController::class, 'getFeaturedSalonAdmin']);
            Route::put('/featured-salon', [SettingsController::class, 'updateFeaturedSalon']);

            // Registration settings
            Route::get('/registration-settings', [SettingsController::class, 'getRegistrationSettings']);
            Route::put('/registration-settings', [SettingsController::class, 'updateRegistrationSettings']);

            // Analytics settings
            Route::put('/analytics-settings', [SettingsController::class, 'updateAnalytics']);

            // Locations management
            Route::get('/locations', [LocationController::class, 'adminIndex']);
            Route::post('/locations', [LocationController::class, 'store']);
            Route::get('/locations/{location}', [LocationController::class, 'show']);
            Route::put('/locations/{location}', [LocationController::class, 'update']);
            Route::delete('/locations/{location}', [LocationController::class, 'destroy']);

            // Job ads management
            Route::get('/job-ads', [JobAdController::class, 'adminIndex']);
            Route::post('/job-ads', [JobAdController::class, 'store']);
            Route::put('/job-ads/owner-posting-setting', [JobAdController::class, 'updateOwnerPostingSetting']);
            Route::put('/job-ads/{id}', [JobAdController::class, 'update']);
            Route::delete('/job-ads/{id}', [JobAdController::class, 'destroy']);
            Route::put('/job-ads/{id}/toggle-active', [JobAdController::class, 'toggleActive']);

            // Homepage categories management
            Route::prefix('homepage-categories')->group(function () {
                Route::get('/', [AdminHomepageCategoryController::class, 'index']);
                Route::post('/', [AdminHomepageCategoryController::class, 'store']);
                Route::put('/{id}', [AdminHomepageCategoryController::class, 'update']);
                Route::delete('/{id}', [AdminHomepageCategoryController::class, 'destroy']);
                Route::post('/{id}/image', [AdminHomepageCategoryController::class, 'uploadImage']);
                Route::post('/reorder', [AdminHomepageCategoryController::class, 'reorder']);
                Route::put('/settings', [AdminHomepageCategoryController::class, 'updateSettings']);
            });

            // Import appointments
            Route::prefix('import')->group(function () {
                Route::post('/upload', [ImportController::class, 'upload']);
                Route::get('/{importId}/preview', [ImportController::class, 'preview']);
                Route::post('/{importId}/validate', [ImportController::class, 'validate']);
                Route::post('/{importId}/process', [ImportController::class, 'process']);
                Route::get('/batch/{importBatchId}/status', [ImportController::class, 'status']);
                Route::get('/history', [ImportController::class, 'history']);
                Route::get('/batch/{importBatchId}/errors', [ImportController::class, 'downloadErrors']);
                Route::get('/template/{format}', [ImportController::class, 'downloadTemplate']);
            });
        });
    });
});

// Legacy routes (without v1 prefix) - for backward compatibility
// These will be deprecated in future versions
// Using web middleware to ensure session is available for Sanctum
Route::middleware(['web'])->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:60,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:60,1');
});

Route::middleware('throttle:120,1')->group(function () {
    Route::get('/salons', [SalonController::class, 'index']);
    Route::get('/salons/nearest', [SalonController::class, 'nearest']);
    Route::get('/salons/{salon}', [SalonController::class, 'show']);
    Route::get('/salons/{salon}/services', [ServiceController::class, 'index']);
    Route::get('/salons/{salon}/services/by-category', [ServiceController::class, 'byCategory']);
    Route::get('/salons/{salon}/staff', [StaffController::class, 'index']);
    Route::get('/salons/{salon}/reviews', [ReviewController::class, 'index']);

    // Public settings (analytics, appearance, etc.)
    Route::get('/public/analytics-settings', [SettingsController::class, 'getAnalytics']);
    Route::get('/public/appearance-settings', [SettingsController::class, 'getAppearance']);
    Route::get('/public/featured-salon', [SettingsController::class, 'getFeaturedSalon']);

    // Job ads (public)
    Route::get('/public/job-ads', [JobAdController::class, 'index']);
    Route::get('/public/job-ads/{id}', [JobAdController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);
    Route::post('/user/avatar', [AuthController::class, 'uploadAvatar']);
    Route::get('/user/favorites', [FavoriteController::class, 'index']);
    Route::post('/user/favorites/{salon}', [FavoriteController::class, 'store']);
    Route::delete('/user/favorites/{salon}', [FavoriteController::class, 'destroy']);
    Route::get('/user/favorites/{salon}/check', [FavoriteController::class, 'check']);
    Route::get('/user/appointments', [AppointmentController::class, 'index']);
    Route::post('/user/appointments', [AppointmentController::class, 'store']);
    Route::get('/user/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::put('/user/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/user/appointments/{appointment}', [AppointmentController::class, 'destroy']);
    Route::put('/user/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::put('/salon/profile', [SalonController::class, 'updateProfile']);

    Route::post('/salons', [SalonController::class, 'store']);
    Route::put('/salons/{salon}', [SalonController::class, 'update']);
    Route::delete('/salons/{salon}', [SalonController::class, 'destroy']);
    Route::post('/salons/{salon}/images', [SalonController::class, 'uploadImages']);
    Route::delete('/salons/{salon}/images/{image}', [SalonController::class, 'deleteImage']);
    Route::put('/salons/{salon}/images/{image}/primary', [SalonController::class, 'setPrimaryImage']);
    Route::get('/salons/{salon}/available-slots', [SalonController::class, 'availableSlots']);

    Route::post('/salons/{salon}/staff', [StaffController::class, 'store']);
    Route::get('/salons/{salon}/staff/{staff}', [StaffController::class, 'show']);
    Route::put('/salons/{salon}/staff/{staff}', [StaffController::class, 'update']);
    Route::delete('/salons/{salon}/staff/{staff}', [StaffController::class, 'destroy']);
    Route::post('/salons/{salon}/staff/{staff}/avatar', [StaffController::class, 'uploadAvatar']);
    Route::get('/salons/{salon}/staff/{staff}/schedule', [StaffController::class, 'schedule']);
    Route::get('/salons/{salon}/staff/{staff}/appointments', [StaffController::class, 'appointments']);

    Route::put('/staff/me/settings', [StaffController::class, 'updateOwnSettings']);

    Route::post('/salons/{salon}/services', [ServiceController::class, 'store']);
    Route::get('/salons/{salon}/services/{service}', [ServiceController::class, 'show']);
    Route::put('/salons/{salon}/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/salons/{salon}/services/{service}', [ServiceController::class, 'destroy']);

    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
    Route::put('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::put('/appointments/{appointment}/no-show', [AppointmentController::class, 'markNoShow']);
    Route::put('/appointments/{appointment}/complete', [AppointmentController::class, 'markCompleted']);

    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/reviews/{review}', [ReviewController::class, 'show']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    Route::post('/reviews/{review}/response', [ReviewController::class, 'addResponse']);

    Route::get('/salons/{salon}/breaks', [ScheduleController::class, 'getSalonBreaks']);
    Route::post('/salons/{salon}/breaks', [ScheduleController::class, 'storeSalonBreak']);
    Route::put('/salons/{salon}/breaks/{break}', [ScheduleController::class, 'updateSalonBreak']);
    Route::delete('/salons/{salon}/breaks/{break}', [ScheduleController::class, 'deleteSalonBreak']);

    Route::get('/salons/{salon}/vacations', [ScheduleController::class, 'getSalonVacations']);
    Route::post('/salons/{salon}/vacations', [ScheduleController::class, 'storeSalonVacation']);
    Route::put('/salons/{salon}/vacations/{vacation}', [ScheduleController::class, 'updateSalonVacation']);
    Route::delete('/salons/{salon}/vacations/{vacation}', [ScheduleController::class, 'deleteSalonVacation']);

    Route::get('/staff/{staff}/breaks', [ScheduleController::class, 'getStaffBreaks']);
    Route::post('/staff/{staff}/breaks', [ScheduleController::class, 'storeStaffBreak']);
    Route::put('/staff/{staff}/breaks/{break}', [ScheduleController::class, 'updateStaffBreak']);
    Route::delete('/staff/{staff}/breaks/{break}', [ScheduleController::class, 'deleteStaffBreak']);

    Route::get('/staff/{staff}/vacations', [ScheduleController::class, 'getStaffVacations']);
    Route::post('/staff/{staff}/vacations', [ScheduleController::class, 'storeStaffVacation']);
    Route::put('/staff/{staff}/vacations/{vacation}', [ScheduleController::class, 'updateStaffVacation']);
    Route::delete('/staff/{staff}/vacations/{vacation}', [ScheduleController::class, 'deleteStaffVacation']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{salon}', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{salon}', [FavoriteController::class, 'destroy']);
    Route::get('/favorites/{salon}/check', [FavoriteController::class, 'check']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboardStats']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
        Route::post('/users/{user}/reset-password', [AdminController::class, 'resetUserPassword']);
        Route::post('/users/{user}/message', [AdminController::class, 'sendMessageToUser']);
        Route::get('/salons', [AdminController::class, 'salons']);
        Route::put('/salons/{salon}', [AdminController::class, 'updateSalon']);
        Route::put('/salons/{salon}/approve', [AdminController::class, 'approveSalon']);
        Route::put('/salons/{salon}/suspend', [AdminController::class, 'suspendSalon']);
        Route::get('/analytics', [AdminController::class, 'analytics']);

        // System settings
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::get('/settings/{group}', [SettingsController::class, 'getByGroup']);
        Route::put('/settings', [SettingsController::class, 'update']);

        // Gradient/Appearance settings
        Route::get('/gradient-presets', [SettingsController::class, 'getGradientPresets']);
        Route::put('/gradient', [SettingsController::class, 'updateGradient']);
        Route::put('/navbar-gradient', [SettingsController::class, 'updateNavbarGradient']);
        Route::put('/sticky-navbar', [SettingsController::class, 'updateStickyNavbar']);

        // Salon profile layout settings
        Route::get('/salon-profile-layout', [SettingsController::class, 'getSalonProfileLayout']);
        Route::get('/salon-profile-layouts', [SettingsController::class, 'getSalonProfileLayoutOptions']);
        Route::put('/salon-profile-layout', [SettingsController::class, 'updateSalonProfileLayout']);

        // Featured salon settings
        Route::get('/featured-salon', [SettingsController::class, 'getFeaturedSalonAdmin']);
        Route::put('/featured-salon', [SettingsController::class, 'updateFeaturedSalon']);

        // Analytics settings
        Route::put('/analytics-settings', [SettingsController::class, 'updateAnalytics']);

        // Locations management
        Route::get('/locations', [LocationController::class, 'adminIndex']);
        Route::post('/locations', [LocationController::class, 'store']);
        Route::get('/locations/{location}', [LocationController::class, 'show']);
        Route::put('/locations/{location}', [LocationController::class, 'update']);
        Route::delete('/locations/{location}', [LocationController::class, 'destroy']);

        // Bot Protection
        Route::prefix('bot-protection')->group(function () {
            Route::get('/blocked-ips', [\App\Http\Controllers\Api\Admin\BotProtectionController::class, 'getBlockedIps']);
            Route::get('/stats', [\App\Http\Controllers\Api\Admin\BotProtectionController::class, 'getBotStats']);
            Route::get('/recent-requests', [\App\Http\Controllers\Api\Admin\BotProtectionController::class, 'getRecentRequests']);
            Route::post('/block-ip', [\App\Http\Controllers\Api\Admin\BotProtectionController::class, 'blockIp']);
            Route::post('/unblock-ip', [\App\Http\Controllers\Api\Admin\BotProtectionController::class, 'unblockIp']);
            Route::delete('/clear-old-logs', [\App\Http\Controllers\Api\Admin\BotProtectionController::class, 'clearOldLogs']);
            Route::get('/settings', [\App\Http\Controllers\Api\Admin\BotProtectionController::class, 'getSettings']);
            Route::put('/settings', [\App\Http\Controllers\Api\Admin\BotProtectionController::class, 'updateSettings']);
        });
    });
});

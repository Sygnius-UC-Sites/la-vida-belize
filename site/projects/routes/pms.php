<?php

/**
 * [PMS-MODULE] — All routes for the PMS module. 100% additive.
 *
 * Loaded by PmsServiceProvider::register() BEFORE any provider boots, so
 * these unique-URI routes always precede the site's own routes and can never
 * be shadowed by a site catch-all (e.g. GET /{seo_url}).
 *
 * Every route name here is new (pms.* or names that only the PMS module
 * defines) so `php artisan route:cache` never sees a duplicate name.
 */

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Controllers\PmsPageController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Payment\PmsStripeController;
use App\Http\Controllers\Payment\PmsReceiptController;
use App\Http\Controllers\Payment\StripeWebhookController;
use App\Http\Controllers\Admin\PmsPropertyController;
use App\Http\Controllers\Admin\PmsRoomController;
use App\Http\Controllers\Admin\PmsBookingController;
use App\Http\Controllers\Admin\PmsToolsController;
use App\Http\Controllers\Admin\ScheduledPaymentsController;
use App\Http\Controllers\Admin\OperationsController;

/*
|--------------------------------------------------------------------------
| Webhooks — no CSRF (authenticity verified by signature/token middleware)
|--------------------------------------------------------------------------
*/
Route::group(['excluded_middleware' => [VerifyCsrfToken::class]], function () {

    // Stripe — signature verified inside the controller via the Stripe SDK.
    Route::post('stripe/webhook', [StripeWebhookController::class, 'handle']);

    // Lodgify — Route::any() is intentional (Lodgify probes with GET).
    Route::group(['middleware' => ['lodgify.webhook']], function () {
        Route::any('lodgify/new_property',     [WebhookController::class, 'property_event']);
        Route::any('lodgify/updated_property', [WebhookController::class, 'property_event']);
        Route::any('lodgify/rate_change',         [WebhookController::class, 'rate_change']);
        Route::any('lodgify/availability_change', [WebhookController::class, 'availability_change']);
        Route::any('lodgify/booking_new_any_status',          [WebhookController::class, 'booking_new_any_status']);
        Route::any('lodgify/booking_new_status_booked',       [WebhookController::class, 'booking_new_status_booked']);
        Route::any('lodgify/booking_change',                  [WebhookController::class, 'booking_change']);
        Route::any('lodgify/booking_status_change_booked',    [WebhookController::class, 'booking_status_change_booked']);
        Route::any('lodgify/booking_status_change_tentative', [WebhookController::class, 'booking_status_change_tentative']);
        Route::any('lodgify/booking_status_change_open',      [WebhookController::class, 'booking_status_change_open']);
        Route::any('lodgify/booking_status_change_declined',  [WebhookController::class, 'booking_status_change_declined']);
    });

    // Guesty — Svix/HMAC signatures with a path-token fallback.
    Route::any('webhooks/guesty', [WebhookController::class, 'guesty_webhook'])->middleware('guesty.webhook');
    Route::any('webhooks/guesty/{webhookToken}', [WebhookController::class, 'guesty_webhook'])->middleware('guesty.webhook');

    // Hostaway / Hostfully — secret token in URL (?token=...)
    Route::any('webhooks/hostaway',  [WebhookController::class, 'hostaway_webhook'])->middleware('hostaway.webhook');
    Route::any('webhooks/hostfully', [WebhookController::class, 'hostfully_webhook'])->middleware('hostfully.webhook');
});

/*
|--------------------------------------------------------------------------
| Front — property page intercept + live quote/availability
|--------------------------------------------------------------------------
*/

// Intercept the site's property-detail URL. PMS room → single-pms view.
// Anything else → delegated to the site's own PageController (unchanged).
// NOTE: the properties/detail/{seo_url} intercept is NOT registered here.
// PmsServiceProvider::interceptPropertyDetailRoute() re-points the site's
// existing route at PmsPageController after all site routes have loaded.

// Real-time quote + availability for the property-page widget.
// CSRF-exempt: read-only price/availability calculations, no state change.
Route::post('pms-quote', [QuoteController::class, 'get'])->name('pms-quote')
    ->withoutMiddleware([VerifyCsrfToken::class]);
Route::get('pms-availability', [AvailabilityController::class, 'get'])->name('pms-availability');

/*
|--------------------------------------------------------------------------
| Front — booking funnel (parallel to the old flow; old flow is untouched)
|--------------------------------------------------------------------------
*/
Route::get('pms/get-quote', [PmsPageController::class, 'getQuote'])->name('pms.get-quote');
Route::post('pms/save-booking-data', [PmsPageController::class, 'saveBookingData'])->name('pms.save-booking-data');
Route::get('pms/booking/preview/{id}', [PmsPageController::class, 'previewBooking'])->name('pms.booking.preview');

// Stripe payment (PaymentIntents). Throttled against card-testing.
Route::group(['middleware' => ['throttle:10,1']], function () {
    Route::get('pms/booking/payment/{id}',  [PmsStripeController::class, 'index'])->name('pms.stripe_payment');
    Route::post('pms/booking/payment/{id}', [PmsStripeController::class, 'indexPostRedirect'])->name('pms.stripe.post');
    Route::get('pms/booking/payment/{id}/return', [PmsStripeController::class, 'confirm'])->name('pms.stripe.return');
    Route::post('pms/booking/payment/{id}/accept-price', [PmsStripeController::class, 'acceptPrice'])->name('pms.stripe.accept-price');
});
Route::get('pms/payment/success/{id}', [PmsReceiptController::class, 'success'])->name('pms.payment.success');

// One-time browser setup (migrate + pms:install without a terminal).
// Only active while storage/app/pms-setup.token exists; token-guarded;
// self-disables after a successful run.
Route::get('pms-setup', [\App\Http\Controllers\PmsSetupController::class, 'run'])->name('pms.setup');
Route::get('pms-setup/diag', [\App\Http\Controllers\PmsSetupController::class, 'diag'])->name('pms.setup.diag');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['auth']], function () {

    Route::resource('pms_properties', PmsPropertyController::class);
    // Publish a synced room to the public site (creates the linked core property).
    Route::post('pms_rooms/{id}/publish', [PmsRoomController::class, 'publishToSite'])->name('pms_rooms.publish');
    Route::resource('pms_rooms', PmsRoomController::class);

    // Booking actions — registered BEFORE the resource so 'confirmed' is not
    // swallowed by the {pms_booking} show route. Names match the packaged views.
    Route::get('pms-bookings/confirmed/{id}', [PmsBookingController::class, 'confirmed'])->name('pms-booking-confirm');
    Route::post('pms-bookings/{id}/deposit/capture', [PmsBookingController::class, 'captureDeposit'])->name('booking.deposit.capture');
    Route::post('pms-bookings/{id}/deposit/release', [PmsBookingController::class, 'releaseDeposit'])->name('booking.deposit.release');
    Route::post('pms-bookings/{id}/refund', [PmsBookingController::class, 'refundPayment'])->name('booking.refund');
    Route::post('pms-bookings/{id}/retry-pms', [PmsBookingController::class, 'retryPms'])->name('booking.retry-pms');
    Route::resource('pms-bookings', PmsBookingController::class);

    // Scheduled payments (installments)
    Route::get('scheduled-payments', [ScheduledPaymentsController::class, 'index'])->name('scheduled-payments.index');
    Route::post('scheduled-payments/{id}/mark-charged', [ScheduledPaymentsController::class, 'markCharged'])->name('scheduled-payments.mark-charged');
    Route::post('scheduled-payments/{id}/reset', [ScheduledPaymentsController::class, 'resetToPending'])->name('scheduled-payments.reset');

    // PMS settings + manual tools (URLs match the packaged settings view)
    Route::get('pms-settings', [PmsToolsController::class, 'settings'])->name('pms-settings');
    Route::get('operations', [OperationsController::class, 'index'])->name('operations.index');
    Route::post('operations/go-live-bootstrap', [OperationsController::class, 'goLiveBootstrap'])->name('operations.bootstrap');
    Route::post('operations/register-webhooks', [OperationsController::class, 'registerWebhooks'])->name('operations.webhooks');
    Route::post('operations/queue-probe', [OperationsController::class, 'queueProbe'])->name('operations.queue-probe');
    // Archived provider snapshots (created when an API key is replaced)
    Route::get('pms-archives/download/{file}', function (string $file) {
        $path = storage_path('app/pms-archives/' . basename($file));
        abort_unless(is_file($path) && str_ends_with($path, '.json'), 404);
        return response()->download($path);
    })->name('pms-archives.download');
    Route::get('pms-archives/delete/{file}', function (string $file) {
        $path = storage_path('app/pms-archives/' . basename($file));
        if (is_file($path) && str_ends_with($path, '.json')) @unlink($path);
        return redirect()->to('admin/pms-settings')->with('success', 'Archive deleted.');
    })->name('pms-archives.delete');
    Route::get('pms-archives/restore/{file}', function (string $file) {
        try {
            $msg = app(\App\Services\PmsArchiveService::class)->restore($file);
            return redirect()->to('admin/pms-settings')->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->to('admin/pms-settings')->with('danger', 'Restore failed: ' . $e->getMessage());
        }
    })->name('pms-archives.restore');
    Route::get('setting/register-pms-webhooks', [PmsToolsController::class, 'registerPmsWebhooks'])
        ->name('pms.webhooks.register');
    Route::get('setting/sync-pms-data/{provider}', [PmsToolsController::class, 'syncPmsData'])
        ->name('pms.sync.provider');
    Route::get('setting/sync-pms-reviews', [PmsToolsController::class, 'syncPmsReviews'])
        ->name('pms.sync.reviews');
});

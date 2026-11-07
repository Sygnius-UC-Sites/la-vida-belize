<?php

use Illuminate\Support\Facades\Route;

Route::get("fullcalendar/{id}", function () {
    return view("welcome");
});
Route::get('/reload-captcha',  'PageController@reloadCaptcha');
Route::get("fullcalendar-demo/{id}", function () {
    return view("welcome-demo");
});
Route::get("fullcalendar-demo-admin/{id}", function () {
    return view("welcome-demo-admin");
});
Route::post("fullcalendar-demo-post", function () {
    return view("common-dates");
});
Route::get("robots.txt", function () {
    return response(view('front.robots'), 200, ['Content-Type' => 'text/plain']);
});
Route::post("getEMailBody", "PageController@getEMailBody")->name("getEMailBody");

Route::group(["prefix" => "admin", "namespace" => "Admin", "middleware" => ["auth", "feature.gate"]], function () {
    // ── Hidden developer panel — feature flags (no menu link; non-devs 404) ──
    Route::get("dev/features",  "DevFeatureController@index")->name("admin.dev-features");
    Route::post("dev/features", "DevFeatureController@update")->name("admin.dev-features.update");
    Route::get("/", 'DashboardController@index');
    // Command palette / global search backing the top-bar search dropdown.
    Route::get("api/search", "SearchController@index")->name("admin.search");
    Route::get("api/notifications", "NotificationController@index")->name("admin.notifications");
    // Support: optional per-install external help URL. Kept server-side so the
    // admin route resolves cleanly even if the operator bookmarks /admin/help.
    Route::get("help", function () {
        $supportUrl = trim((string) \DB::table('basic_settings')->where('name', 'support_url')->value('value'));
        if ($supportUrl !== '' && preg_match('#^https?://#i', $supportUrl)) {
            return redirect()->away($supportUrl);
        }

        return redirect('/admin')->with('warning', 'No support URL is configured for this site.');
    })->name("admin.help");
    Route::post("get-checkin-checkout-data-gaurav", "BookingRequestController@getCheckinCheckoutDataGaurav")->name("get-checkin-checkout-data-gaurav");
    Route::get("exportData/{dynamic}", "DashboardController@exportData")->name("exportData");
    Route::get("/media-center", "DashboardController@mediaCenter");
    Route::post("/new-file-uploads", "DashboardController@newFileUploads")->name("new-file-uploads");
    Route::post("/je-upload", "DashboardController@jeUpload")->name("je-upload"); // inline image upload for JSON-editor image fields (returns {url})
    Route::get("change-password", "DashboardController@changePassword")->name("admin-change-password");
    Route::post("change-password", "DashboardController@changePasswordPost")->name("change-password-admin");
    Route::get("setting", "DashboardController@setting");
    Route::post("setting", "DashboardController@settingPost");
    Route::get("settings/payment", "PaymentSettingsController@index")->name("admin.settings.payment");
    Route::post("settings/payment", "PaymentSettingsController@update")->name("admin.settings.payment.update");
    Route::get("settings/rental-agreement", "RentalAgreementSettingsController@edit")->name("admin.rental-agreement.edit");
    Route::put("settings/rental-agreement", "RentalAgreementSettingsController@update")->name("admin.rental-agreement.update");
    // Phase 2 — Word doc → HTML conversion endpoint. Used by both the global
    // settings page and per-property edit (context query param disambiguates).
    Route::post("settings/rental-agreement/convert-doc", "RentalAgreementSettingsController@convertDoc")->name("admin.rental-agreement.convert-doc");
    Route::get("settings/stay-communications", "StayCommunicationsSettingsController@edit")->name("admin.stay-communications.edit");
    Route::put("settings/stay-communications", "StayCommunicationsSettingsController@update")->name("admin.stay-communications.update");
    Route::get("settings/payment-gateways", "PaymentGatewaysSettingsController@edit")->name("admin.payment-gateways.edit");
    Route::put("settings/payment-gateways", "PaymentGatewaysSettingsController@update")->name("admin.payment-gateways.update");
    Route::get("settings/spam-protection", "SpamProtectionSettingsController@edit")->name("admin.spam-protection.edit");
    Route::put("settings/spam-protection", "SpamProtectionSettingsController@update")->name("admin.spam-protection.update");
    Route::get("settings/mail", "MailSettingsController@edit")->name("admin.mail.edit");
    Route::put("settings/mail", "MailSettingsController@update")->name("admin.mail.update");
    Route::post("settings/mail/test", "MailSettingsController@sendTest")->name("admin.mail.test");
    Route::post("settings/mail/pause", "MailSettingsController@pauseSending")->name("admin.mail.pause");
    Route::post("settings/mail/resume", "MailSettingsController@resumeSending")->name("admin.mail.resume");
    Route::post("settings/mail/suppression/remove", "MailSettingsController@removeSuppression")->name("admin.mail.suppression.remove");
    Route::get("scrape-sources", "ScrapeSourceController@index")->name("admin.scrape-sources.index");
    Route::post("scrape-sources", "ScrapeSourceController@store")->name("admin.scrape-sources.store");
    Route::delete("scrape-sources/{id}", "ScrapeSourceController@destroy")->name("admin.scrape-sources.destroy");
    Route::post("scrape-sources/{id}/scrape", "ScrapeSourceController@scrape")->name("admin.scrape-sources.scrape");
    Route::post("scrape-sources/{id}/scrape-reviews", "ScrapeSourceController@scrapeReviews")->name("admin.scrape-sources.scrape-reviews");
    Route::post("scrape-sources/{id}/check-status", "ScrapeSourceController@checkStatus")->name("admin.scrape-sources.check-status");
    Route::get("scrape-sources/{id}/raw", "ScrapeSourceController@viewRaw")->name("admin.scrape-sources.raw");
    Route::post("scrape-sources/{id}/remap", "ScrapeSourceController@remap")->name("admin.scrape-sources.remap");
    Route::get("scrape-sources/{id}/reviews", "ScrapeSourceController@reviews")->name("admin.scrape-sources.reviews");
    Route::post("scrape-sources/{id}/reviews/bulk", "ScrapeSourceController@reviewsBulk")->name("admin.scrape-sources.reviews.bulk");
    Route::put("scrape-sources/settings", "ScrapeSourceController@settingsUpdate")->name("admin.scrape-sources.settings.update");
    Route::post("scrape-sources/test-connection", "ScrapeSourceController@testConnection")->name("admin.scrape-sources.test-connection");
    Route::get("booking-enquiries/{id}/agreement.pdf", "BookingRequestController@downloadAgreement")->name("admin.booking.agreement-download");
    Route::delete("medias-destroy", "DashboardController@mediasDelete")->name("medias-destroy");
    Route::get('ckeditor', 'CkeditorController@index');
    Route::post('ckeditor/upload', 'CkeditorController@upload')->name('ckeditor.upload');
    Route::get("properties/copydata/{id}", "PropertyController@copyData")->name("properties.copyData");
    Route::post("image-delete-asset", "PropertyController@imageDeleteAsset")->name("image-delete-asset");
    Route::post("properties/{property_id}/gallery/{image_id}/set-cover", "PropertyController@setFeatureImage")->name('property-gallery.set-cover');
    Route::post("properties/{property_id}/gallery/bulk-delete", "PropertyController@galleryBulkDelete")->name('property-gallery.bulk-delete');
    Route::post("properties/{property_id}/gallery/bulk-update-category", "PropertyController@galleryBulkUpdateCategory")->name('property-gallery.bulk-update-category');
    Route::put("properties/{property_id}/gallery/{image_id}", "PropertyController@galleryUpdateOne")->name('property-gallery.update-one');
    Route::get("delete-property-space-single", "PropertyController@deletePropertySpace")->name("delete-property-space-single");
    Route::get("faqs/copydata/{id}", "FaqController@copyData")->name("faqs.copyData");
    Route::get("testimonials/copydata/{id}", "TestimonialController@copyData")->name("testimonials.copyData");
    Route::post("multiple-delete/{models}", "DashboardController@multipleDelete")->name("multipleDelete");
    Route::get("properties/active/{id}", "PropertyController@active")->name("properties.active");
    Route::get("properties/toggle-home/{id}", "PropertyController@toggleHome")->name("properties.toggle-home");
    Route::get("properties/deactive/{id}", "PropertyController@deactive")->name("properties.deactive");
    // Phase 7 — bulk actions on the index page
    Route::post("properties/bulk-activate",   "PropertyController@bulkActivate")->name("properties.bulk-activate");
    Route::post("properties/bulk-deactivate", "PropertyController@bulkDeactivate")->name("properties.bulk-deactivate");
    Route::post("properties/bulk-delete",     "PropertyController@bulkDelete")->name("properties.bulk-delete");
    // Phase 7 — drag-reorder for the 3 small repeaters in the edit form
    Route::post("properties/{property_id}/highlights/reorder",         "PropertyController@reorderHighlights")->name("properties-highlights.reorder");
    Route::post("properties/{property_id}/sleep-cards/reorder",        "PropertyController@reorderSleepCards")->name("properties-sleep-cards.reorder");
    Route::post("properties/{property_id}/amenity-highlights/reorder", "PropertyController@reorderAmenityHighlights")->name("properties-amenity-highlights.reorder");
    // Phase 11 — Inline-create endpoints for taxonomy modals on Property + Attraction edit forms.
    Route::post("locations/quick-create",             "LocationController@quickCreate")->name("admin.locations.quick-create");
    Route::post("attraction-categories/quick-create", "AttractionCategoryController@quickCreate")->name("admin.attraction-categories.quick-create");
    Route::get("properties/{property_id}/group-amenities", "PropertyAmenityGroupController@index")->name("properties-group-amenities");
    Route::get("properties/{property_id}/group-amenities/create", "PropertyAmenityGroupController@create")->name("properties-group-amenities.create");
    Route::post("properties/{property_id}/group-amenities/create", "PropertyAmenityGroupController@store")->name("properties-group-amenities.store");
    Route::get("properties/{property_id}/group-amenities/{id}/edit", "PropertyAmenityGroupController@edit")->name("properties-group-amenities.edit");
    Route::put("properties/{property_id}/group-amenities/{id}/update", "PropertyAmenityGroupController@update")->name("properties-group-amenities.update");
    Route::get("properties/{property_id}/group-amenities/{id}/delete", "PropertyAmenityGroupController@destroy")->name("properties-group-amenities.destroy");
    Route::get("properties/{property_id}/group-amenities/{id}/active", "PropertyAmenityGroupController@active")->name("properties-group-amenities.active");
    Route::get("properties/{property_id}/group-amenities/{id}/deactive", "PropertyAmenityGroupController@deactive")->name("properties-group-amenities.deactive");

  
    // Phase 6 — Calendar API (replaces properties-calendar.* and properties-rates.* CRUD trees)
    Route::prefix("properties/{property_id}/calendar")->name("admin.calendar.")->group(function () {
        Route::post("defaults", "PropertyCalendarApiController@updateDefaults")->name("defaults.update");
        Route::get("days", "PropertyCalendarApiController@days")->name("days");
        Route::get("overrides-count", "PropertyCalendarApiController@overridesCount")->name("overrides-count");
        Route::post("days/bulk-update", "PropertyCalendarApiController@bulkUpdateDays")->name("days.bulk-update");
        Route::delete("days", "PropertyCalendarApiController@clearDayOverrides")->name("days.clear");
        Route::get("reservations", "PropertyCalendarApiController@reservations")->name("reservations");
        Route::get("pricing-rules", "PropertyCalendarApiController@listPricingRules")->name("pricing-rules.list");
        Route::post("pricing-rule", "PropertyCalendarApiController@storePricingRule")->name("pricing-rule.store");
        Route::put("pricing-rule/{id}", "PropertyCalendarApiController@updatePricingRule")->name("pricing-rule.update");
        Route::delete("pricing-rule/{id}", "PropertyCalendarApiController@destroyPricingRule")->name("pricing-rule.destroy");
        Route::get("ical-feeds", "PropertyCalendarApiController@listIcalFeeds")->name("ical-feeds.list");
        Route::post("ical-feeds", "PropertyCalendarApiController@storeIcalFeed")->name("ical-feeds.store");
        Route::post("ical-feeds/{id}/refresh", "PropertyCalendarApiController@refreshIcalFeed")->name("ical-feeds.refresh");
        Route::delete("ical-feeds/{id}", "PropertyCalendarApiController@destroyIcalFeed")->name("ical-feeds.destroy");
        Route::post("regenerate-export", "PropertyCalendarApiController@regenerateExport")->name("regenerate-export");
        Route::post("import-prices", "PropertyCalendarApiController@importExcelPrices")->name("import-prices");
        Route::delete("import-prices/{fileid}", "PropertyCalendarApiController@deleteImportedFile")->name("import-prices.destroy");
    });

    Route::get("properties/{property_id}/rooms", "PropertyRoomController@index")->name("properties-group-rooms");
    Route::get("properties/{property_id}/rooms/create", "PropertyRoomController@create")->name("properties-group-rooms.create");
    Route::post("properties/{property_id}/rooms/create", "PropertyRoomController@store")->name("properties-group-rooms.store");
    Route::get("properties/{property_id}/rooms/{id}/edit", "PropertyRoomController@edit")->name("properties-group-rooms.edit");
    Route::put("properties/{property_id}/rooms/{id}/update", "PropertyRoomController@update")->name("properties-group-rooms.update");
    Route::delete("properties/{property_id}/rooms/{id}/delete", "PropertyRoomController@destroy")->name("properties-group-rooms.destroy");
    Route::get("properties/{property_id}/rooms/{id}/active", "PropertyRoomController@active")->name("properties-group-rooms.active");
    Route::get("properties/{property_id}/rooms/{id}/deactive", "PropertyRoomController@deactive")->name("properties-group-rooms.deactive");
    Route::get("properties/{property_id}/group-amenities/{group_id}/amenities", "PropertyAmenityController@index")->name("properties-amenities");
    Route::get("properties/{property_id}/group-amenities/{group_id}/amenities/create", "PropertyAmenityController@create")->name("properties-amenities.create");
    Route::post("properties/{property_id}/group-amenities/{group_id}/amenities/store", "PropertyAmenityController@store")->name("properties-amenities.store");
    Route::get("properties/{property_id}/group-amenities/{group_id}/amenities/{id}/edit", "PropertyAmenityController@edit")->name("properties-amenities.edit");
    Route::put("properties/{property_id}/group-amenities/{group_id}/amenities/{id}/update", "PropertyAmenityController@update")->name("properties-amenities.update");
    Route::delete("properties/{property_id}/group-amenities/{group_id}/amenities/{id}/delete", "PropertyAmenityController@destroy")->name("properties-amenities.destroy");
    Route::get("properties/{property_id}/group-amenities/{group_id}/amenities/{id}/active", "PropertyAmenityController@active")->name("properties-amenities.active");
    Route::get("properties/{property_id}/group-amenities/{group_id}/amenities/{id}/deactive", "PropertyAmenityController@deactive")->name("properties-amenities.deactive");
    Route::post("properties/{property_id}/group-amenities/{group_id}/sort", "PropertyAmenityGroupController@sort")->name('properties-group-amenities.sort');
    Route::post("properties/{property_id}/group-amenities/{group_id}/amenities/{amenity_id}/sort", "PropertyAmenityController@sort")->name('properties-amenities.sort');
    Route::post("properties/{property_id}/amenities/sync-library", "PropertyController@syncAmenityLibrary")->name('property-amenities.sync-library');
    Route::post("properties/{property_id}/amenities/custom-add", "PropertyController@customAmenitiesBatchAdd")->name('property-amenities.custom-add');
    Route::post("properties/{property_id}/amenities/custom-delete", "PropertyController@customAmenitiesBatchDelete")->name('property-amenities.custom-delete');
    Route::get("properties/{property_id}/rooms/{group_id}/sub-room", "PropertyRoomItemController@index")->name("properties-sub-room");
    Route::get("properties/{property_id}/rooms/{group_id}/sub-room/create", "PropertyRoomItemController@create")->name("properties-sub-room.create");
    Route::post("properties/{property_id}/rooms/{group_id}/sub-room/store", "PropertyRoomItemController@store")->name("properties-sub-room.store");
    Route::get("properties/{property_id}/rooms/{group_id}/sub-room/{id}/edit", "PropertyRoomItemController@edit")->name("properties-sub-room.edit");
    Route::put("properties/{property_id}/rooms/{group_id}/sub-room/{id}/update", "PropertyRoomItemController@update")->name("properties-sub-room.update");
    Route::delete("properties/{property_id}/rooms/{group_id}/sub-room/{id}/delete", "PropertyRoomItemController@destroy")->name("properties-sub-room.destroy");
    Route::get("properties/{property_id}/rooms/{group_id}/sub-room/{id}/active", "PropertyRoomItemController@active")->name("properties-sub-room.active");
    Route::get("properties/{property_id}/rooms/{group_id}/sub-room/{id}/deactive", "PropertyRoomItemController@deactive")->name("properties-sub-room.deactive");
    Route::get('booking-enquiries/confirmed/{id}', "BookingRequestController@confirmed")->name("booking-enquiry-confirm");
    Route::post('booking-enquiries/decline/{id}',  "BookingRequestController@decline")->name("booking-enquiry-decline");
    // Phase 6: properties-rates.* and properties-calendar.* CRUD trees retired —
    // replaced by admin.calendar.* AJAX endpoints + Calendar tab inside property edit form.
    Route::post("properties/update-property-caption-and-sorting", "PropertyController@updateCaptionSOrt")->name("update-property-caption-and-sorting");
    Route::get("booking-enquiries/properties/{id}", "BookingRequestController@singlePropertyBookoing")->name("singlePropertyBookoing");
    Route::delete("booking-requests/destroy/{id}", "BookingRequestController@destroyData")->name("booking-requests.destroyData");
    // Phase 13G — manually re-fire any lifecycle email from the booking-detail modal.
    Route::post("booking-enquiries/{id}/resend/{type}", "BookingRequestController@resendEmail")
        ->where('type', 'welcome|checkin|checkout|review|cancellation|awaiting-payment|confirmed|enquiry')
        ->name('booking-enquiries.resend');
    // Phase 13L — render any lifecycle email as HTML for inspection (no actual send).
    Route::get("booking-enquiries/{id}/email-preview/{type}", "BookingRequestController@previewEmail")
        ->where('type', 'welcome|checkin|checkout|review|cancellation|awaiting-payment|confirmed|enquiry|installment2|installment3')
        ->name('booking-enquiries.email-preview');
    // Template-only previews — render every lifecycle email with sample data,
    // no specific booking required. Index lists them; type-specific URL
    // returns the full rendered HTML so it can be inspected in an iframe.
    Route::get("email-templates", "BookingRequestController@templatePreviewIndex")->name('email-templates.index');
    Route::get("email-templates/{type}", "BookingRequestController@templatePreviewRender")
        ->where('type', 'welcome|checkin|checkout|review|cancellation|awaiting-payment|confirmed|enquiry|installment2|installment3|receipt|contact-guest|contact-owner|rental-agreement-request|rental-agreement-signed|rental-agreement-signed-admin')
        ->name('email-templates.render');
    // Per-template file attachments — admins upload PDFs / images / docs that
    // get appended to every send of a given email type (welcome package
    // brochure, check-in map, etc.). Multipart upload from the email-templates
    // index page; AJAX delete from the same panel.
    Route::post("email-templates/{type}/attachments", "EmailAttachmentController@store")
        ->where('type', 'welcome|checkin|checkout|review|cancellation|awaiting-payment|confirmed|enquiry|installment2|installment3|receipt|contact-guest|contact-owner|rental-agreement-request|rental-agreement-signed|rental-agreement-signed-admin')
        ->name('email-attachments.store');
    Route::delete("email-templates/attachments/{id}", "EmailAttachmentController@destroy")
        ->name('email-attachments.destroy');
    // Phase 3: typed addon delete routes (deletervhookup / deletevisitor / deletehottub / deletefirewood) removed.
    // Property fees are managed via the routes below.
    Route::post("properties/{property_id}/fees", "PropertyFeeController@store")->name('property-fees.store');
    Route::put("properties/{property_id}/fees/{fee_id}", "PropertyFeeController@update")->name('property-fees.update');
    Route::delete("properties/{property_id}/fees/{fee_id}", "PropertyFeeController@destroy")->name('property-fees.destroy');
    Route::post("properties/{property_id}/fees/{fee_id}/sort", "PropertyFeeController@sort")->name('property-fees.sort');
    Route::post("delete-property-highlight",         "PropertyController@deletePropertyHighlight")->name('delete-property-highlight');
    Route::post("delete-property-sleep-card",        "PropertyController@deletePropertySleepCard")->name('delete-property-sleep-card');
    Route::post("delete-property-amenity-highlight", "PropertyController@deletePropertyAmenityHighlight")->name('delete-property-amenity-highlight');

    Route::get('api/datepicker/blocked-dates', 'DatepickerController@blockedDates')->name('admin.datepicker.blocked-dates');

    // Phase 13A — CMS index modernization
    Route::post("cms/{id}/duplicate", "CmsController@duplicate")->name('cms.duplicate');

    Route::resources([
        'users' => UserController::class,
        'cms' => CmsController::class,
        'locations' => LocationController::class,
        'attractions' => AttractionController::class,
        'attraction-categories' => AttractionCategoryController::class,
        'coupons' => CouponController::class,
        'email-templetes' => EmailTempleteController::class,
        'faqs' => FaqController::class,
        'faq-categories' => FaqCategoryController::class,
        'testimonials' => TestimonialController::class,
        'our-teams' => OurTeamController::class,
        'properties' => PropertyController::class,
        'contact-us-enquiries' => ContactusRequestController::class,
        'booking-enquiries' => BookingRequestController::class,
        'payments' => PaymentController::class,
    ]);
    Route::resource('property-categories', 'PropertyCategoryController');

    // QA pass: bulk-delete payment history (e.g. clearing dummy/test records).
    // Registered alongside the resource so the route fits the existing namespace
    // (App\Http\Controllers\Admin) and CSRF auth middleware.
    Route::post('payments/delete-all', 'PaymentController@deleteAll')->name('payments.deleteAll');
});

Auth::routes();

// GET /logout — convenience route so the admin menu's "Log out" link
// (which does a GET) signs the user out and lands on the login screen.
// (Laravel's stock logout is POST-only.)
Route::get('/logout', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
})->name('logout.get');
Route::post("checkajax-get-quote", "PageController@checkAjaxGetQuoteData")->name("checkajax-get-quote");
// QA 2026-06-11 (taxonomy H): these admin-only quote helpers were registered
// outside the auth group — reachable unauthenticated. Now gated.
Route::group(["middleware" => ["auth"]], function () {
    Route::post("admin-checkajax-get-quote", "PageController@adminCheckAjaxGetQuoteData")->name("admin-checkajax-get-quote");
    Route::put("admin-checkajax-get-quote-edit", "PageController@adminCheckAjaxGetQuoteDataEdit")->name("admin-checkajax-get-quote-edit");
});
Route::get("set-cron-job", "ICalController@setCronJob");
Route::get("send-reminder-email", "ICalController@sendReminderPackage");
Route::get("refresh-calendar-data", "ICalController@refresshCalendar")->name("refresshCalendar");
Route::get("send-welcome-packages", "ICalController@sendWelcomePackage")->name("sendWelcomePackage");
Route::get("send-review-email", "ICalController@sendReviewEmail")->name("sendReviewEmail");
Route::get("ical/{id}", "ICalController@getEventsICalObject");
Route::get("sitemap.xml", "PageController@sitemap");
Route::post("contact-post", "PageController@contactPost")->name("contactPost");
Route::post("review-submit", "PageController@reviewSubmit")->name("reviewSubmit");
Route::get('review/{token}', 'ReviewSubmissionController@show')->name('review.show');
Route::post('review/{token}', 'ReviewSubmissionController@submit')->name('review.submit');
Route::post("save-booking-data", "PageController@saveBookingData")->name("save-booking-data");
Route::post("rental-aggrement-data-save", "PageController@rentalAggrementDataSave")->name("rental-aggrement-data-save");
Route::get("booking/{id}/agreement", "RentalAgreementDownloadController@guestDownload")->name("rental-agreement.guest-download");
// Phase 4 — magic-link to the signing page used in the post-confirmation
// signing flow. The link is signed (URL::temporarySignedRoute) so it
// expires after 14 days and can't be tampered with.
Route::get("booking/{id}/sign-agreement", "PageController@rentalAggrementBooking")->name("rental-agreement.sign-link");
Route::post("booking/payment/{id}", "Payment\StripeController@indexPost")->name("stripe.post");
Route::post("booking/payment/authorize/post/{id}", "Payment\AuthorizeNetController@indexPost")->name("authorize.submit");
Route::get("booking/payment/paypal/post/{id}", "Payment\PaypalController@indexPost")->name("paypal.submit");
Route::post("booking/payment/square/post/{id}", "Payment\SquarePaymentController@indexPost")->name("square.submit");
Route::post("get-quote-changeable-data", "PageController@getQUoteCHangeableData")->name("getQUoteCHangeableData");
Route::post("api/booking/quote", "Api\BookingQuoteController@quote")->name("api.booking.quote");
Route::post("get-states-data", "GauravCommonController@stateDateSelect")->name("stateDateSelect");

// SES bounce/complaint webhook — SNS topic POSTs here. CSRF-excluded in
// VerifyCsrfToken; signature-verified inside the controller.
Route::post("webhooks/mail", "Webhooks\\SesNotificationController@handle")->name("webhooks.mail");

// Apify scrape-run webhook — POSTed by Apify when a run reaches a terminal state.
// Authenticity gated by X-Webhook-Secret header inside the controller.
Route::post("webhooks/listings", "Webhooks\\ApifyNotificationController@handle")->name("webhooks.listings");
Route::group(["middleware" => ["OptimizeMiddleware"]], function () {
    $htmlRedirects = [
        'index.html' => '/',
        'about-us.html' => '/about-us',
        'about-owner.html' => '/about-owner',
        'properties.html' => '/properties',
        'faq.html' => '/faq',
        'attractions.html' => '/attractions',
        'attractions-dining.html' => '/attractions/dining',
        'attractions-adventures.html' => '/attractions/adventures',
        'attractions-taxi.html' => '/attractions/taxi',
        'attractions-golf-cart.html' => '/attractions/golf-cart',
        'attractions-car-rental.html' => '/attractions/car-rental',
        'contact.html' => '/contact',
        'experience.html' => '/experience',
        'groups.html' => '/groups',
        'reviews.html' => '/reviews',
        'privacy.html' => '/privacy',
        'terms.html' => '/terms',
        'explore.html' => '/attractions',
        'stays.html' => '/properties',
        'story.html' => '/about-us',
    ];
    foreach ($htmlRedirects as $from => $to) {
        Route::get($from, fn () => redirect($to, 301));
    }

    $aliasRedirects = [
        'contact-us' => '/contact',
        'our-story' => '/about-us',
        'faqs' => '/faq',
        'experiences' => '/experience',
        'explore' => '/attractions',
        'attractions-dining' => '/attractions/dining',
        'attractions-adventures' => '/attractions/adventures',
        'attractions-taxi' => '/attractions/taxi',
        'attractions-golf-cart' => '/attractions/golf-cart',
        'attractions-car-rental' => '/attractions/car-rental',
    ];
    foreach ($aliasRedirects as $from => $to) {
        Route::get($from, fn () => redirect($to, 301));
    }

    Route::get("payment/success/{id}", "Payment\CommonController@showReceipt");
    Route::get("faq/{property}", "PageController@propetyFaqList");
    Route::get("check-payment/success/{id}", "Payment\CommonController@showReceiptCheck");
    Route::get("venmo-payment/success/{id}", "Payment\CommonController@showReceiptVenmo");
    Route::get("payment/mail-success/{id}", "Payment\SquarePaymentController@showReceiptMail");
    Route::get("booking/payment/square/{id}", "Payment\SquarePaymentController@index")->name("square_payment");
    Route::get("booking/payment/{id}", "Payment\StripeController@index")->name("stripe_payment");
    Route::get("booking/payment/paypal/{id}", "Payment\PaypalController@index")->name("paypal_payment");
    Route::get("booking/payment/authorize/{id}", "Payment\AuthorizeNetController@index")->name("authorize_payment");
    Route::get("booking/preview/{id}", "PageController@previewBooking");
    Route::get("booking/rental-aggrement/{id}", "PageController@rentalAggrementBooking");
    Route::get("conatct-us/{id}", "PageController@contactUsThankyouPage")->name('contactus.thankyou');
    Route::get("properties/location/{seo_url}", "PageController@propertyLocation");
    Route::get("attractions/detail/{seo_url}", "PageController@attractionSingle");
    Route::get("attractions/location/{seo_url}", "PageController@attractionLocation");
    Route::get("attractions/category/{seo_url}", "PageController@attractionCategory");
    $attractionChildren = [
        'dining' => 'attractions-dining',
        'adventures' => 'attractions-adventures',
        'taxi' => 'attractions-taxi',
        'golf-cart' => 'attractions-golf-cart',
        'car-rental' => 'attractions-car-rental',
    ];
    foreach ($attractionChildren as $slug => $template) {
        Route::get("attractions/{$slug}", function () use ($slug, $template) {
            $data = \App\Models\Cms::where('seo_url', 'attractions-' . $slug)->first()
                ?: \App\Models\Cms::firstOrCreate(
                    ['seo_url' => 'attractions-' . $slug],
                    ['name' => \Illuminate\Support\Str::headline($slug), 'templete' => $template, 'publish' => 'published']
                );
            return view('front.static.' . $template, compact('data'));
        });
    }
    Route::get("properties/detail/{seo_url}", "PageController@propertyDetail");
    Route::get('/', "PageController@index")->name("front-home");
    Route::get("condos/{seo_url}", "PageController@condoDetail");
    Route::get("houses/{seo_url}", "PageController@houseDetail");
    Route::get("stays", "PageController@booking")->name('stays');
    // Type-less property detail — for clients without a condo/house division
    // (single-collection clients where every retreat is just a "stay").
    Route::get("stay/{seo_url}", "PageController@stayDetail")->name('stay.detail');
    // Site-wide /gallery page (aggregated PropertyGallery photos).
    Route::get("gallery", "PageController@gallery")->name('gallery');

    // Readable La Vida guest pages. firstOrCreate so they work before/without
    // the seed migration, still appear in /admin CMS, and keep FeatureFlags.
    $bradPages = [
        'about-us' => 'about',
        'about-owner' => 'about-owner',
        'properties' => 'property-list',
        'faq' => 'faq',
        'attractions' => 'attractions',
        'contact' => 'contact',
        'experience' => 'experiences',
        'groups' => 'groups',
        'reviews' => 'reviews',
        'privacy' => 'privacy',
        'terms' => 'terms',
    ];
    foreach ($bradPages as $seo => $template) {
        Route::get($seo, function () use ($seo, $template) {
            if (!\App\Helper\FeatureFlags::frontPageEnabled($template)
                || !\App\Helper\FeatureFlags::frontPageEnabled($seo)) {
                return abort(404);
            }
            $data = \App\Models\Cms::firstOrCreate(
                ['seo_url' => $seo],
                ['name' => \Illuminate\Support\Str::headline($seo), 'templete' => $template, 'publish' => 'published']
            );
            return view('front.static.' . $template, compact('data'));
        });
    }
    Route::get("booking", function () {
        $qs = request()->getQueryString();
        return redirect('/stays' . ($qs ? '?' . $qs : ''), 301);
    });
    Route::get('booking/checkout/{slug}', 'PageController@checkout')->name('booking.checkout');
    Route::post('booking/request', 'PageController@submitBookingRequest')->name('booking.request.submit');
    Route::get('booking/pay/{id}', 'PageController@payByMagicLink')->name('booking.pay-link');

    // Booking-flow result pages (B1): before the {seo_url} catch-all; firstOrCreate
    // gives each a CMS row on first render -> editable in admin.
    $bookingFlowPage = function (string $view, string $seo) {
        $data = \App\Models\Cms::firstOrCreate(
            ['seo_url' => $seo],
            ['name' => \Illuminate\Support\Str::headline($seo), 'templete' => $seo, 'publish' => 'published']
        );
        return view($view, compact('data'));
    };
    Route::get("booking-confirmed",          fn () => $bookingFlowPage('front.static.booking-confirmed',        'booking-confirmed'));
    Route::get("booking-confirmed-instant",  fn () => $bookingFlowPage('front.static.booking-confirmed',        'booking-confirmed'));
    Route::get("booking-request-received",   fn () => $bookingFlowPage('front.static.booking-request-received', 'booking-request-received'));
    Route::get("payment-success",            fn () => $bookingFlowPage('front.static.payment-success',          'payment-success'));
    Route::get("payment-failed",             fn () => $bookingFlowPage('front.static.payment-failed',           'payment-failed'));

    // Legal pages (QA D-001): the checkout consent box links to these slugs.
    // firstOrCreate gives each an admin-editable CMS row on first render, and
    // the shared legal-page template renders the CMS body. Declared before the
    // {seo_url} catch-all so they resolve instead of 404ing.
    $legalPage = function (string $preferredSeo, string $legacySeo, string $view) {
        $data = \App\Models\Cms::where('seo_url', $preferredSeo)->first()
            ?: \App\Models\Cms::where('seo_url', $legacySeo)->first();
        if (!$data) {
            $data = \App\Models\Cms::firstOrCreate(
                ['seo_url' => $preferredSeo],
                ['name' => \Illuminate\Support\Str::headline($preferredSeo), 'templete' => $preferredSeo, 'publish' => 'published']
            );
        }
        return view($view, compact('data'));
    };
    Route::get("terms-of-service", fn () => $legalPage('terms', 'terms-of-service', 'front.static.terms'))->name('terms-of-service');
    Route::get("privacy-policy",   fn () => $legalPage('privacy', 'privacy-policy', 'front.static.privacy'))->name('privacy-policy');

    Route::get("{seo_url}", "PageController@dynamicDataCategory")->name("services");
});

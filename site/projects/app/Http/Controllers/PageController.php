<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Auth;
use App\Models\Cms; 
use Mail;
use App\Helper\Upload;
use ModelHelper;
use Helper;
use DB;
use MailHelper;
use LiveCart;
use Session;
use Response;
use App\Models\ContactusRequest;
use App\Models\Testimonial;
use App\Support\PropertyUrl;
use App\Models\Property;
use App\Models\Payment;
use App\Models\Location;
use App\Models\Attraction;
use App\Models\AttractionCategory;
use App\Models\BookingRequest;
use App\Models\PropertyGallery;
use App\Models\PropertyAmenityGroup;
use Image;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Charge;
use Validator;
use Illuminate\Support\Facades\Log;

class PageController extends Controller{
    
     function getEMailBody(Request $request){
        $data=Property::find($request->id);
        if($data){
            return $data->welcome_package_description;
        }
        return '';
    }

    function getQUoteCHangeableData(Request $request){ 
        if($request->property_id){
            $property=Property::find($request->property_id);
            if($request->start_date){
                if($request->end_date){
                    $main_data=Helper::getGrossAmountData($property,$request->start_date,$request->end_date);
                    if($main_data['status']=="true"){
                        $main_data['total_guests']=$request->get("adults")+$request->get("child");
                        $main_data['adults']=$request->get("adults");
                        $main_data['child']=$request->get("child");
                        $main_data['start_date']=$request->get("start_date");
                        $main_data['end_date']=$request->get("end_date");
                        $main_data['adults']=$request->get("adults");
                        $main_data['childs']=$request->get("child");
                        $main_data['pet_fee_data_guarav']=$request->get("pet");
                        $main_data['heating_pool_fee']=$request->get("heating_pool_fee");
                        $show_price_div_data= view("front.ajax.show-price-div-data",compact("property","main_data"))->render();
                        $row_data_hidden=view("front.ajax.row_data_fields",compact("property","main_data"))->render();
                        return response()->json(['status'=> "200","show_price_div_data"=>$show_price_div_data,"row_data_hidden"=>$row_data_hidden]);
                    }else if($main_data['status']=="already-booked"){
                        return response()->json(['status'=> "404","message"=>"Some Dates are already booked"]);
                    }else if($main_data['status']=="min-stay-day"){
                        return response()->json(['status'=> "404","message"=>"Minimum stay criteria not met"]);
                    }else if($main_data['status']=="date-price"){
                        return response()->json(['status'=> "404","message"=>'Price is not defined']);
                    }else if($main_data['status']=="checkin-checkout-day"){
                        return response()->json(['status'=> "404","message"=>$main_data['message']]);
                    }else{
                        return response()->json(['status'=> "404","message"=>"Invalid Calling"]);
                    }
                }else{
                    return response()->json(['status'=> "404","message"=>"Invalid Checkout"]);
                }
            }else{
                return response()->json(['status'=> "404","message"=>"Invalid Checkin"]);
            }
        }else{
            return response()->json(['status'=> "404","message"=>"Invalid Property"]);
        }
    }
  
    public function propetyFaqList($seo_url){
      $property = Property::where("seo_url",$seo_url)->first();
       if($property){
            $data = new \stdClass();
            $data->name="Faq Page";
            $data->meta_title="Faq Page";
            $data->meta_keywords="Faq Page";
            $data->meta_description="Faq Page";
        return view('front.faq.faq-list', compact("property","data"));
       }
       return abort(404);
    }

    public function attractionCategory($seo_url){
        $data=AttractionCategory::where("seo_url",$seo_url)->first();
        if($data){
            return view("front.attractions.category",compact("data"));
        }
        return abort(404);
    }

    public function guestAttractionCategory($seo_url)
    {
        if (!\App\Helper\FeatureFlags::frontPageEnabled('attractions')) {
            return abort(404);
        }

        $category = AttractionCategory::where('seo_url', $seo_url)
            ->where('publish', 'published')
            ->first();
        if (!$category) {
            return abort(404);
        }

        $data = Cms::where('seo_url', 'attractions-' . $seo_url)->first() ?: $category;
        $attractions = Attraction::where('category_id', $category->id)
            ->where('publish', 'published')
            ->orderBy('ordering', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return view('front.static.attractions-category', compact('data', 'category', 'attractions'));
    }
  
    public function propertyDetail($seo_url){
        return $this->redirectToCanonicalProperty($seo_url);
    }

    public function condoDetail($seo_url) {
        if (!PropertyUrl::hasMultipleTypes()) {
            return $this->redirectToCanonicalProperty($seo_url);
        }

        return $this->propertyPage($seo_url, 'condo');
    }

    public function houseDetail($seo_url) {
        if (!PropertyUrl::hasMultipleTypes()) {
            return $this->redirectToCanonicalProperty($seo_url);
        }

        return $this->propertyPage($seo_url, 'house');
    }

    /**
     * Type-less property detail — used by clients where
     * the URL has no condos/ or houses/ prefix because they only have one
     * category of stay. Reuses the same data-loading + view as the typed flow.
     */
    public function stayDetail($seo_url) {
        $property = $this->findPublicProperty($seo_url);
        if (!$property) {
            return abort(404);
        }

        if (PropertyUrl::hasMultipleTypes()) {
            $canonical = PropertyUrl::path($property);
            if (request()->path() !== ltrim($canonical, '/')) {
                return $this->redirectToCanonicalProperty($seo_url);
            }
        }

        return $this->propertyPage($seo_url);
    }

    private function redirectToCanonicalProperty(string $seoUrl)
    {
        $property = $this->findPublicProperty($seoUrl);
        if (!$property) {
            return abort(404);
        }

        $query = request()->getQueryString();
        return redirect(PropertyUrl::path($property) . ($query ? '?' . $query : ''), 301);
    }

    private function findPublicProperty(string $seoUrl): ?Property
    {
        return Property::query()
            ->where('seo_url', $seoUrl)
            ->where('status', 'true')
            ->first();
    }

    private function propertyPage(string $seoUrl, ?string $expectedType = null) {
        $data = Property::with(['pmsRoom', 'categories'])
            ->where('seo_url', $seoUrl)
            ->where('status', 'true')
            ->first();
        if (!$data) return abort(404);

        if ($expectedType !== null && strtolower((string) $data->type) !== $expectedType) {
            return $this->redirectToCanonicalProperty($seoUrl);
        }

        $testimonials = Testimonial::where('property_id', $data->id)
            ->where('status', 'true')
            ->get();

        $imported = \DB::table('property_reviews')
            ->where('property_id', $data->id)
            ->where('status', 'published')
            ->get()
            ->map(function ($r) {
                $o = new \stdClass();
                $o->name       = $r->reviewer_name ?: 'Guest';
                $o->message    = $r->comment ?: ($r->comment_translated ?: '');
                $o->rating     = $r->rating;
                $o->score      = (string) ($r->rating ?? '');
                $o->stay_date  = $r->review_date ? \Carbon\Carbon::parse($r->review_date)->format('M Y') : '';
                $o->location   = $r->reviewer_location;
                $o->source     = $r->source;
                $o->photo      = $r->reviewer_photo_local ?: $r->reviewer_photo_url;
                $o->created_at = $r->review_date ?? $r->created_at;
                return $o;
            });

        $reviews = $testimonials->concat($imported)
            ->sortByDesc(function ($r) { return $r->created_at ?? null; })
            ->values();

        return view('front.static.property-detail', compact('data', 'reviews'));
    }

    public function booking(Request $request) {
        $data = Cms::where('seo_url', 'booking')->first();
        $context = \App\Helpers\BookingContext::fromRequest($request);

        $q = Property::with(['pmsRoom', 'categories'])
            ->where('status', 'true');
        if ($context->category) {
            $q->whereHas('categories', function ($categoryQuery) use ($context) {
                $categoryQuery->where('slug', $context->category)
                    ->where('status', true);
            });
        }
        $guestCount = max(1, (int) $context->adults + (int) $context->children);
        if ($guestCount > 1)   $q->where('sleeps',  '>=', $guestCount);
        if ($context->bedrooms)     $q->where('bedroom', '>=', $context->bedrooms);
        if ($context->min_price)    $q->where('price', '>=', $context->min_price);
        if ($context->max_price)    $q->where('price', '<=', $context->max_price);

        $properties = $q->orderBy('ordering')->get();
        if ($context->pets > 0) {
            $properties = $properties->filter(function ($property) use ($context) {
                if ($property->pmsRoom) {
                    return \App\Helper\BookingPartyValidator::validate(
                        $property->pmsRoom,
                        $context->adults,
                        $context->children,
                        $context->pets
                    )['ok'];
                }

                return (bool) $property->accepts_pets
                    && (int) $property->max_pet >= $context->pets;
            })->values();
        }

        $properties = app(\App\Services\ListingAvailabilityService::class)
            ->filter($properties, $context->check_in, $context->check_out);

        $categories = \App\Models\PropertyCategory::where('status', true)
            ->whereHas('properties', fn ($propertyQuery) => $propertyQuery->where('status', 'true'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Back-compat $filters array for the existing Blade template + $context for forward links
        $filters = [
            'check_in'  => $context->check_in,
            'check_out' => $context->check_out,
            'adults'    => $context->adults > 1 ? $context->adults : null,
            'category'  => $context->category,
            'bedrooms'  => $context->bedrooms,
            'min_price' => $context->min_price,
            'max_price' => $context->max_price,
        ];

        return view('front.static.booking', compact('data', 'properties', 'filters', 'context', 'categories'));
    }

    public function checkout($slug, \Illuminate\Http\Request $request)
    {
        $property = \App\Models\Property::where('seo_url', $slug)->first();
        if (!$property) {
            abort(404);
        }
        // O2: firstOrCreate so the page gets a real CMS row on first render → it
        // appears in the admin Pages list and becomes editable (meta/copy).
        $cms = \App\Models\Cms::firstOrCreate(
            ['seo_url' => 'checkout'],
            ['name' => \Illuminate\Support\Str::headline('checkout'), 'templete' => 'checkout', 'publish' => 'published']
        );
        $context = \App\Helpers\BookingContext::fromRequest($request);

        // Phase 3.1: parse selected property_fees IDs from `?fees[]={id}` query params
        $feeIds = $request->query('fees', []);
        if (!is_array($feeIds)) $feeIds = [];
        $feeIds = array_values(array_filter(array_map('intval', $feeIds), fn ($n) => $n > 0));
        $addons = ['fees' => $feeIds];

        $calc = new \App\Services\Booking\QuoteCalculator();
        $input = \App\Services\Booking\QuoteInput::fromArray([
            'property_id' => $property->id,
            'check_in' => $context->check_in,
            'check_out' => $context->check_out,
            'adults' => $context->adults,
            'children' => $context->children,
            'pets' => $context->pets,
            'addons' => $addons,
        ]);
        $quote = $calc->calculate($input);

        $enabledStr = (string) \DB::table('basic_settings')->where('name', 'enabled_payment_gateways')->value('value');
        $enabledGateways = array_values(array_filter(array_map('trim', explode(',', $enabledStr))));

        // Per-customer feature flags: gateways not sold to this customer are
        // never offered to guests, regardless of stored settings.
        $enabledGateways = array_values(array_filter($enabledGateways,
            fn ($gw) => \App\Helper\FeatureFlags::gatewayEnabled($gw)));

        // Phase 4 — split into a single card processor + optional PayPal.
        // Owners pick (Stripe OR Authorize.net OR Square) for cards, plus
        // optionally PayPal as an additional option. A gateway is only
        // *available* to the guest if the owner has actually entered the
        // credentials it needs — otherwise we'd offer a payment method
        // that's guaranteed to fail. Misconfigured gateways are hidden.
        $settings = \DB::table('basic_settings')
            ->whereIn('name', [
                'stripe_publish_key', 'stripe_secret_key',
                'AUTHORIZED_MERCHANT_LOGIN_ID', 'AUTHORIZED_MERCHANT_TRANSACTION_KEY',
                'square_ACCESS_TOKEN', 'square_Application_id',
                'paypal_access_token',
            ])->pluck('value', 'name')->toArray();
        $hasKeys = [
            'stripe'    => !empty($settings['stripe_publish_key'])
                        && !empty($settings['stripe_secret_key']),
            'authorize' => !empty($settings['AUTHORIZED_MERCHANT_LOGIN_ID'])
                        && !empty($settings['AUTHORIZED_MERCHANT_TRANSACTION_KEY']),
            'square'    => !empty($settings['square_ACCESS_TOKEN'])
                        && !empty($settings['square_Application_id']),
            'paypal'    => !empty($settings['paypal_access_token']),
        ];
        $cardGateway = collect(['stripe', 'authorize', 'square'])
            ->first(fn ($gw) => in_array($gw, $enabledGateways, true) && $hasKeys[$gw]);
        $paypalEnabled = in_array('paypal', $enabledGateways, true) && $hasKeys['paypal'];

        $cancelType = $property->cancellation_policy_type ?? 'moderate';
        $cancelText = $property->cancellation_policy ?: \DB::table('basic_settings')
            ->where('name', "cancellation_template_{$cancelType}")->value('value');

        // Phase 3 — checkout right-column live summary needs:
        //   - all PropertyFee rows (the Blade splits mandatory vs optional itself).
        //   - widget-blocked dates so guests can edit dates inline without
        //     bouncing back to the property page.
        $allFees = \App\Models\PropertyFee::active()
            ->where('property_id', $property->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
        $widgetBlocked = (new \App\Helper\LiveCart())->iCalDataCheckInCheckOutCheckinCheckout($property->id);

        $response = response()->view('front.static.checkout', compact(
            'property', 'cms', 'context', 'addons', 'quote',
            'enabledGateways', 'cardGateway', 'paypalEnabled',
            'cancelType', 'cancelText',
            'allFees', 'widgetBlocked'
        ));

        // Hard "no-store" so browsers don't bfcache the form. After a
        // confirmed booking the back button must NOT restore a paid
        // booking's checkout — the JS guard handles SPA-style cases,
        // and these headers handle the bfcache case for browsers that
        // skip script execution on back-restore.
        return $response
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function submitBookingRequest(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $spam = app(\App\Services\Spam\SpamProtection::class)->check($request, 'booking');
        if (!$spam->passed) {
            if ($spam->silent) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => url('booking-request-received'),
                ]);
            }
            return response()->json(['success' => false, 'error_message' => $spam->userMessage], 200);
        }

        $validated = $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'pets' => 'nullable|integer|min:0',
            'addons' => 'nullable|string',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'nullable|string|max:50',
            'message' => 'nullable|string',
            'idempotency_key' => 'required|uuid',
        ]);

        $existing = \App\Models\BookingRequest::where('request_id', $validated['idempotency_key'])->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'redirect_url' => url('booking-request-received') . '?booking_id=' . $existing->id,
            ]);
        }

        $property = \App\Models\Property::find($validated['property_id']);

        // QA 2026-06-11 (taxonomy G): cap stay length — a 400-night request
        // previously sailed through. Uses the property's max_stay when set,
        // otherwise a generous 180-night ceiling.
        $nights = \Carbon\Carbon::parse($validated['check_in'])
            ->diffInDays(\Carbon\Carbon::parse($validated['check_out']));
        $maxNights = (int) ($property->max_stay ?: 0) > 0 ? (int) $property->max_stay : 180;
        if ($nights > $maxNights) {
            return response()->json([
                'success' => false,
                'error_message' => "Stays at this home are limited to {$maxNights} nights. For longer visits, please contact us directly.",
            ], 200);
        }

        $addonsArr = json_decode($validated['addons'] ?? '[]', true) ?: [];
        $calc = new \App\Services\Booking\QuoteCalculator();
        $input = \App\Services\Booking\QuoteInput::fromArray(array_merge($validated, ['addons' => $addonsArr]));
        $quote = $calc->calculate($input);

        if (!$quote->valid) {
            return response()->json(['success' => false, 'error_message' => implode('. ', $quote->errors)], 200);
        }

        $booking = \App\Models\BookingRequest::create([
            'property_id' => $property->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'] ?? null,
            'message' => $validated['message'] ?? null,
            'checkin' => $validated['check_in'],
            'checkout' => $validated['check_out'],
            'total_guests' => $validated['adults'] + ($validated['children'] ?? 0),
            'adults' => $validated['adults'],
            'child' => $validated['children'] ?? 0,
            'total_pets' => $validated['pets'] ?? 0,
            'gross_amount' => $quote->total,
            'sub_amount' => $quote->subtotal,
            'total_amount' => $quote->total,
            'tax' => $quote->tax,
            'total_night' => $quote->nights,
            'booking_status' => 'pending-approval',
            'payment_status' => 'check-booking',
            'request_id' => $validated['idempotency_key'],
            'amount_data' => json_encode($quote->toArray()),
            'ip_address' => $request->ip(),
        ]);

        try {
            // Phase 13D — customer enquiry-confirmation email is admin-editable on
            // /admin/settings/stay-communications (Booking lifecycle tab → Enquiry confirmation).
            $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
            $brand    = \ModelHelper::getDataFromSetting('mailer_admin_name')
                     ?: \ModelHelper::getDataFromSetting('mail_from_name')
                     ?: \ModelHelper::getDataFromSetting('owner_name')
                     ?: 'Notification';
            $footer   = \ModelHelper::getDataFromSetting('mail_footer') ?: '';

            $subject = $resolver->expandMergeFields((string) (\ModelHelper::getDataFromSetting('enquiry_default_subject') ?: 'We received your enquiry about {{property_name}}'));
            $body    = $resolver->expandMergeFields((string) (\ModelHelper::getDataFromSetting('enquiry_default_body_html') ?: '<p>Hi {{guest_name}},</p><p>Thanks for your interest in {{property_name}} — we will be in touch shortly.</p><p>{{brand}}</p>'));

            $customerHtml = view('mail.email-shell', ['body_html_rendered' => $body, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $booking, 'property' => $property])->render();
            \MailHelper::emailSenderByController($customerHtml, $booking->email, $subject);
            \App\Services\Email\SendLog::record((int)$booking->id, 'enquiry', (string)$booking->email, $subject, $customerHtml);

            // Phase 13E — admin alert is admin-editable on the Email Templates page (Owner alerts tab → New booking enquiry).
            $adminSubject = $resolver->expandMergeFields((string) (\ModelHelper::getDataFromSetting('admin_alert_enquiry_subject') ?: 'New booking enquiry — {{property_name}}'));
            $adminBody    = $resolver->expandMergeFields((string) (\ModelHelper::getDataFromSetting('admin_alert_enquiry_body_html') ?: '<p>New booking enquiry: #{{booking_id}}</p><p><a href="{{admin_url}}">View →</a></p>'));
            $adminHtml = view('mail.email-shell', ['body_html_rendered' => $adminBody, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $booking, 'property' => $property])->render();
            $to = \ModelHelper::getDataFromSetting('booking_receiving_mail') ?: \ModelHelper::getDataFromSetting('admin_email_primary') ?: \ModelHelper::getDataFromSetting('contact_us_receiving_mail');
            if ($to) {
                \MailHelper::emailSenderByController($adminHtml, $to, $adminSubject);
            }
        } catch (\Throwable $e) {
            logger()->error('Booking request email failed', ['booking_id' => $booking->id, 'exception' => $e]);
        }

        return response()->json([
            'success' => true,
            'redirect_url' => url('booking-request-received') . '?booking_id=' . $booking->id,
        ]);
    }

    public function payByMagicLink(\Illuminate\Http\Request $request, $id)
    {
        if (!$request->hasValidSignature()) {
            $cms = \App\Models\Cms::firstOrCreate(
                ['seo_url' => 'magic-link-expired'],
                ['name' => \Illuminate\Support\Str::headline('magic-link-expired'), 'templete' => 'magic-link-expired', 'publish' => 'published']
            );
            return response()->view('front.static.magic-link-expired', compact('cms'), 403);
        }

        $booking = \App\Models\BookingRequest::find($id);
        if (!$booking) {
            abort(404);
        }
        if ($booking->payment_status === 'paid') {
            return redirect(url('booking-confirmed-instant') . '?booking_id=' . $booking->id);
        }

        $property = \App\Models\Property::find($booking->property_id);
        $cms = \App\Models\Cms::firstOrCreate(
            ['seo_url' => 'magic-link-payment'],
            ['name' => \Illuminate\Support\Str::headline('magic-link-payment'), 'templete' => 'magic-link-payment', 'publish' => 'published']
        );

        $enabledStr = (string) \DB::table('basic_settings')->where('name', 'enabled_payment_gateways')->value('value');
        $enabledGateways = array_values(array_filter(array_map('trim', explode(',', $enabledStr))));

        // Per-customer feature flags: gateways not sold to this customer are
        // never offered to guests, regardless of stored settings.
        $enabledGateways = array_values(array_filter($enabledGateways,
            fn ($gw) => \App\Helper\FeatureFlags::gatewayEnabled($gw)));

        // Mirror the regular checkout's gateway-credential gating (PageController@checkout).
        // A gateway flagged as "enabled" but missing keys would render a payment option that's
        // guaranteed to fail on submit — hide it. PayPal previously appeared on this page even
        // with no `paypal_access_token` row in basic_settings; this filter is what stops that.
        $settings = \DB::table('basic_settings')
            ->whereIn('name', [
                'stripe_publish_key', 'stripe_secret_key',
                'AUTHORIZED_MERCHANT_LOGIN_ID', 'AUTHORIZED_MERCHANT_TRANSACTION_KEY',
                'square_ACCESS_TOKEN', 'square_Application_id',
                'paypal_access_token',
            ])->pluck('value', 'name')->toArray();
        $hasKeys = [
            'stripe'    => !empty($settings['stripe_publish_key']) && !empty($settings['stripe_secret_key']),
            'authorize' => !empty($settings['AUTHORIZED_MERCHANT_LOGIN_ID']) && !empty($settings['AUTHORIZED_MERCHANT_TRANSACTION_KEY']),
            'square'    => !empty($settings['square_ACCESS_TOKEN']) && !empty($settings['square_Application_id']),
            'paypal'    => !empty($settings['paypal_access_token']),
        ];
        // Property-level Stripe override — same precedence as the regular checkout.
        if ($property && $property->stripe_publish_key && $property->stripe_secret_key) {
            $hasKeys['stripe'] = true;
        }
        $enabledGateways = array_values(array_filter($enabledGateways, fn ($gw) => !empty($hasKeys[$gw] ?? false)));

        return view('front.static.magic-link-payment', compact('booking', 'property', 'cms', 'enabledGateways'));
    }

    public function attractionSingle($seo_url){
        $data=Attraction::where("seo_url",$seo_url)->first();
        if($data){
            return view("front.attractions.single",compact("data"));
        }
        return abort(404);
    }

    public function attractionLocation($seo_url){
        $data=Location::where("seo_url",$seo_url)->first();
        if($data){
            return view("front.attractions.location",compact("data"));
        }
        return abort(404);
    }

    public function propertyLocation($seo_url){
        $data=Location::where("seo_url",$seo_url)->first();
        if($data){
            return view("front.property.location",compact("data"));
        }
        return abort(404);
    }

    public function reviewSubmit(Request $request){
         $validator = Validator::make($request->all(), ['email' => 'required|email','name'=>"required","message"=>"required"]);   
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        Testimonial::create($request->all());
        return redirect()->back()->with("success","Thank you for submitting your review");
    }

    public function reloadCaptcha(){
        return response()->json(['captcha'=> captcha_img()]);
    }

    public function contactPost(Request $request){
        $request->merge([
            'name'      => $request->fullName  ?? $request->name,
            'mobile'    => $request->phone     ?? $request->mobile,
            'check_in'  => $request->checkin   ?? $request->check_in,
            'check_out' => $request->checkout  ?? $request->check_out,
        ]);

        // Both AJAX (fetch with Accept:application/json or X-Requested-With) and
        // legacy POSTs route through here. We branch the response shape on
        // wantsJson() so the AJAX path gets field-level error keys (rendered
        // inline by contact.blade.php) and legacy submits still get the old
        // flash-message redirect-back behaviour.
        $wantsJson = $request->wantsJson() || $request->ajax();

        $spam = app(\App\Services\Spam\SpamProtection::class)->check($request, 'contact');
        if (!$spam->passed) {
            if ($spam->silent) {
                $msg = 'Thank you for submitting your query, we will get in touch shortly';
                if ($wantsJson) return response()->json(['success' => true, 'redirect_url' => url('/'), 'message' => $msg]);
                return redirect()->back()->with('success', $msg);
            }
            if ($wantsJson) return response()->json(['success' => false, 'message' => $spam->userMessage], 422);
            return redirect()->back()->withInput()->with('danger', $spam->userMessage);
        }

        $validator = Validator::make($request->all(),
                                ['email' => 'required|email',
                                 'name'=>"required",
                                 "message"=>"required|min:25"]);
        if($validator->fails()){
            if ($wantsJson) {
                // Map server keys back to the form's actual input names so the
                // JS can highlight the right fields (name → fullName, mobile → phone).
                $errors = $validator->errors()->toArray();
                $remap  = ['name' => 'fullName', 'mobile' => 'phone'];
                $mapped = [];
                foreach ($errors as $k => $msgs) {
                    $mapped[$remap[$k] ?? $k] = is_array($msgs) ? $msgs[0] : $msgs;
                }
                return response()->json([
                    'success' => false,
                    'errors'  => $mapped,
                    'message' => $validator->errors()->first(),
                ], 422);
            }
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $dt= ContactusRequest::create($request->all());

        // Fire two emails: an auto-reply to the guest ("we got your message")
        // and a notification to the owner inbox ("new contact submission").
        // Both templates live in basic_settings under contact_default_* and
        // admin_alert_contact_* and surface in the email-templates gallery.
        try {
            $brand  = \ModelHelper::getDataFromSetting('mailer_admin_name')
                   ?: \ModelHelper::getDataFromSetting('mail_from_name')
                   ?: 'Base Vacation Rentals';
            $footer = (string) \ModelHelper::getDataFromSetting('mail_footer');

            // Build a token map similar to the booking resolver so the templates
            // can use friendly merge tokens. Contact submissions don't have a
            // booking/property attached, so we ship a smaller set.
            $tokens = [
                '{{guest_name}}'      => e((string) $dt->name),
                '{{guest_email}}'     => e((string) $dt->email),
                '{{guest_phone}}'     => e((string) ($dt->mobile ?? '')),
                '{{check_in}}'        => e((string) ($dt->check_in ?? '')),
                '{{check_out}}'       => e((string) ($dt->check_out ?? '')),
                '{{guests_count}}'    => e((string) ($dt->guests ?? '')),
                '{{property_type}}'   => e((string) ($dt->property ?? '')),
                '{{property_slug}}'   => e((string) ($dt->property_slug ?? '')),
                '{{message}}'         => e((string) $dt->message),
                '{{submission_id}}'   => (string) $dt->id,
                '{{brand}}'           => e($brand),
                '{{site_email}}'      => e((string) (\ModelHelper::getDataFromSetting('email') ?: '')),
                '{{site_phone}}'      => e((string) (\ModelHelper::getDataFromSetting('mobile') ?: '')),
                '{{admin_url}}'       => url('admin/contact-us-enquiries/' . $dt->id . '/edit'),
            ];

            // 1) Auto-reply to the guest.
            $guestSubject = strtr((string) (\ModelHelper::getDataFromSetting('contact_default_subject')
                                ?: 'Thanks for reaching out, {{guest_name}}'), $tokens);
            $guestBody    = strtr((string) (\ModelHelper::getDataFromSetting('contact_default_body_html')
                                ?: '<p>Hi {{guest_name}},</p><p>Thanks for your message — we typically reply within 2-4 hours.</p><p>Warmly,<br>{{brand}}</p>'), $tokens);
            $guestHtml    = view('mail.email-shell', ['body_html_rendered' => $guestBody, 'brand' => $brand, 'mail_footer' => $footer])->render();
            \MailHelper::emailSenderByController($guestHtml, $dt->email, $guestSubject);
            \App\Services\Email\SendLog::record(0, 'contact-guest', (string) $dt->email, $guestSubject, $guestHtml);

            // 2) Notification to the owner.
            $ownerTo = \ModelHelper::getDataFromSetting('contact_us_receiving_mail')
                    ?: \ModelHelper::getDataFromSetting('admin_email_primary');
            if ($ownerTo) {
                $adminSubject = strtr((string) (\ModelHelper::getDataFromSetting('admin_alert_contact_subject')
                                    ?: 'New contact form submission — {{guest_name}}'), $tokens);
                $adminBody    = strtr((string) (\ModelHelper::getDataFromSetting('admin_alert_contact_body_html')
                                    ?: '<p>{{guest_name}} ({{guest_email}}) submitted the contact form.</p><p>{{message}}</p><p><a href="{{admin_url}}">View in admin →</a></p>'), $tokens);
                $adminHtml    = view('mail.email-shell', ['body_html_rendered' => $adminBody, 'brand' => $brand, 'mail_footer' => $footer])->render();
                \MailHelper::emailSenderByController($adminHtml, $ownerTo, $adminSubject);
                \App\Services\Email\SendLog::record(0, 'contact-owner', (string) $ownerTo, $adminSubject, $adminHtml);
            }
        } catch (\Throwable $e) {
            logger()->error('Contact-form email failed', ['exception' => $e]);
        }

        $thankUrl = url('conatct-us/'.$dt->id);
        if ($wantsJson) return response()->json(['success' => true, 'redirect_url' => $thankUrl, 'message' => 'Thank you for submitting your query, we will get in touch shortly']);
        return redirect()->to($thankUrl)->with("success","Thank you for submitting your query, we will get in touch shortly");
    }

    public function contactUsThankyouPage($id){
        $data1 = ContactusRequest::find($id);
        if($data1){
            $data = new \stdClass();
            $data->name="Contact US Request";
            $data->meta_title="Contact US Request";
            $data->meta_keywords="Contact US Request";
            $data->meta_description="Contact US Request";
            return view("front.Contactus.thankyou",compact("data1","data"));
        }
        return abort(404);

    }

    public function index(){
        $data = Cms::where("seo_url", 'home')->first()
            ?: Cms::where("templete", 'home')->orderBy('id')->first();
        if (!$data) {
            $data = new Cms();
            $data->name = 'Home';
            $data->seo_url = 'home';
            $data->templete = 'home';
            $data->meta_title = 'La Vida Belize — Your Own Piece of the Caribbean';
            $data->meta_description = 'Private beachfront retreat near Placencia, Belize. Casa, Casita and Studio.';
        }
        $condos       = Property::where('type','condo')->where('status','true')->orderBy('ordering')->get();
        $houses       = Property::where('type','house')->where('status','true')->orderBy('ordering')->get();
        $testimonials = Testimonial::where('status','true')->where('is_home','true')->orderByDesc('id')->get();
        return view('front.static.home', compact("data","condos","houses","testimonials"));
    }

    /**
     * Site-wide gallery page for client themes.
     * Aggregates every active PropertyGallery image; the view handles
     * grouping/Fancybox. A dedicated `gallery` Cms row (templete='gallery')
     * supplies page meta + headings; absent that, sane defaults are used so
     * the route never 500s for a fresh install.
     */
    public function gallery(){
        $data = Cms::where("seo_url", 'gallery')->first();
        if (!$data) {
            $data = new Cms();
            $data->name = 'Gallery';
            $data->seo_url = 'gallery';
            $data->templete = 'gallery';
        }
        return view('front.static.gallery', compact('data'));
    }

    public function adminCheckAjaxGetQuoteData(Request $request){
        if($request->property_id){
            $property=Property::find($request->property_id);
            if($request->checkin){
                if($request->checkout){
                    $main_data=Helper::getGrossAmountData($property,$request->checkin,$request->checkout);
                    if($main_data['status']=="true"){
                        $main_data['start_date']=$request->get("checkin");
                        $main_data['end_date']=$request->get("checkout");
                        $main_data['adults']=$request->get("adults");
                        $main_data['childs']=$request->get("childs");
                        $main_data['pet_fee_data_guarav']=$request->get("pets");
                        $main_data['heating_pool_fee']=$request->heating_pool_fee_data_guarav;
                        $main_data['total_guests']=$request->get("adults")+$request->get("childs");
                        $main_data['adults']=$request->get("adults");
                        $main_data['child']=$request->get("childs");
                        $main_data['start_date']=$request->get("checkin");
                        $main_data['end_date']=$request->get("checkout");
                        $main_data['heating_pool_fee']=$request->get("heating_pool_fee_data_guarav");       
                        $main_data['adults']=$request->get("adults");
                        $main_data['childs']=$request->get("childs");
                        $main_data['pet_fee_data_guarav']=$request->get("pets");
                        $main_data['extra_discount']=$request->get('extra_discount');
                        $data_view= view('admin.common.get-quote',compact("main_data","property"))->render();
                        return response()->json(["message"=>"success","status"=>200,"data_view"=>$data_view]);
                    }else if($main_data['status']=="already-booked"){
                        return response()->json(["message"=>"Already booked some date","status"=>400]);
                    }else if($main_data['status']=="checkin-checkout-day"){
                        return response()->json(["message"=>$main_data['message'],"status"=>400]);
                    }else if($main_data['status']=="min-stay-day"){
                        return response()->json(["message"=>"Minimum stay is not statisfy","status"=>400]);
                    }else if($main_data['status']=="date-price"){
                        return response()->json(["message"=>"Price is not defined","status"=>400]);
                    }else{
                        return response()->json(["message"=>"Invalid Calling","status"=>400,"message1"=>$main_data['status']]);
                    }
                }else{
                    return response()->json(["message"=>"Invalid Checkout","status"=>400]);
                }
            }else{
                return response()->json(["message"=>"Invalid Checkin","status"=>400]);
            }
        }else{
            return response()->json(["message"=>"Property Not select","status"=>400]);
        }
    }
    
    public function adminCheckAjaxGetQuoteDataEdit(Request $request){
        if($request->property_id){
            $property=Property::find($request->property_id);
            if($request->checkin){
                if($request->checkout){
                    $main_data=Helper::getGrossAmountData($property,$request->checkin,$request->checkout);
                    if($main_data['status']=="true"){
                        $main_data['start_date']=$request->get("checkin");
                        $main_data['end_date']=$request->get("checkout");
                        $main_data['adults']=$request->get("adults");
                        $main_data['childs']=$request->get("child");
                        $main_data['pet_fee_data_guarav']=$request->get("total_pets");
                        $main_data['heating_pool_fee']=$request->get("heating_pool_fee_data_guarav");
                        $main_data['total_guests']=$request->get("adults")+$request->get("child");
                        $main_data['adults']=$request->get("adults");
                        $main_data['child']=$request->get("child");
                        $main_data['start_date']=$request->get("checkin");
                        $main_data['end_date']=$request->get("checkout");
                        $main_data['adults']=$request->get("adults");
                        $main_data['childs']=$request->get("child");
                        $main_data['pet_fee_data_guarav']=$request->get("total_pets");
                        $main_data['extra_discount']=$request->get('extra_discount');
                        $main_data['coupon_discount']=$request->get('coupon_discount');
                        $main_data['coupon_discount_code']=$request->get('coupon_discount_code');
                        $data_view= view('admin.common.get-quote-edit',compact("main_data","property"))->render();
                        return response()->json(["message"=>"success","status"=>200,"data_view"=>$data_view]);
                    }else if($main_data['status']=="already-booked"){
                        return response()->json(["message"=>"Already booked some date","status"=>400]);
                    }else if($main_data['status']=="min-stay-day"){
                        return response()->json(["message"=>"Minimum stay is not statisfy","status"=>400]);
                    }else if($main_data['status']=="date-price"){
                        return response()->json(["message"=>"Price is not defined","status"=>400]);
                    }else if($main_data['status']=="checkin-checkout-day"){
                        return response()->json(["message"=>$main_data['message'],"status"=>400]);
                    }else{
                        return response()->json(["message"=>"Invalid Calling","status"=>400]);
                    }
                }else{
                    return response()->json(["message"=>"Invalid Checkout","status"=>400]);
                }
            }else{
                return response()->json(["message"=>"Invalid Checkin","status"=>400]);
            }
        }else{
            return response()->json(["message"=>"Property Not select","status"=>400]);
        }
    }

    public function checkAjaxGetQuoteData(Request $request){
        //dd($request->all());
        if($request->property_id){
            $property=Property::find($request->property_id);
            if($request->start_date){
                if($request->end_date){
                    $main_data=Helper::getGrossAmountData($property,$request->start_date,$request->end_date);
                    //dd($main_data);
                    if($main_data['status']=="true"){
                        $main_data['start_date']=$request->get("start_date");
                        $main_data['end_date']=$request->get("end_date");
                        $main_data['adults']=$request->get("adults");
                        $main_data['childs']=$request->get("childs");
                        $main_data['pet_fee_data_guarav']=$request->get("pet_fee_data_guarav");
                        $main_data['heating_pool_fee_data_guarav']=$request->get("heating_pool_fee_data_guarav");
                        $data_view=view("front.property.ajax-gaurav-data-get-quote",compact("property","main_data"))->render();
                        $modal_day_view=view("front.property.ajax-gaurav-modal-day-get-quote",compact("property","main_data"))->render();
                        $modal_service_view=view("front.property.ajax-gaurav-modal-service-get-quote",compact("property","main_data"))->render();
                        return response()->json(["message"=>"success","status"=>200,"modal_day_view"=>$modal_day_view,"modal_service_view"=>$modal_service_view,"data_view"=>$data_view]);
                    }else if($main_data['status']=="already-booked"){
                        return response()->json(["message"=>"Already booked some date","status"=>400]);
                    }else if($main_data['status']=="min-stay-day"){
                        return response()->json(["message"=>"Minimum stay is not statisfy","status"=>400]);
                    }else if($main_data['status']=="checkin-checkout-day"){
                        return response()->json(["message"=>$main_data['message'],"status"=>400]);
                    }else if($main_data['status']=="date-price"){
                        return response()->json(["message"=>"Price is not defined","status"=>400]);
                    }else{
                        return response()->json(["message"=>"Invalid Calling","status"=>400]);
                    }
                }else{
                    return response()->json(["message"=>"Invalid Checkout","status"=>400]);
                }
            }else{
                return response()->json(["message"=>"Invalid Checkin","status"=>400]);
            }
        }else{
            return response()->json(["message"=>"Property Not select","status"=>400]);
        }
    }

    public function dynamicDataCategory(Request $request,$seo_url){
        if($seo_url=="home"){return redirect("/");}
        // N2: these slugs are transactional/data-driven — they need a booking or quote
        // context that only their real routes supply (booking/checkout/{slug},
        // booking/pay/{id}, the booking-flow result routes). Rendering their template
        // via the bare /{slug} catch-all has no context and 500s, so 404 them here.
        // (review-your-quote is handled with its property_id context further down.)
        if (in_array($seo_url, ['checkout','magic-link-payment','magic-link-expired','payment-success','payment-failed','booking-confirmed','booking-confirmed-instant','booking-request-received'], true)) {
            return abort(404);
        }
        $data=Cms::where("seo_url",$seo_url)->first();
        if($data){
            // Per-customer feature flags: public pages for disabled features
            // (attractions/reviews/gallery/faq) 404 — their nav links are
            // hidden in header/footer, this stops direct URL access.
            if (!\App\Helper\FeatureFlags::frontPageEnabled($data->templete ?? '')
                || !\App\Helper\FeatureFlags::frontPageEnabled($seo_url)) {
                return abort(404);
            }
            $templete="front.static.".$data->templete;
            if($data->templete=="review-your-quote"){
                if($request->property_id){
                    $property=Property::find($request->property_id);
                    if($request->start_date){
                        if($request->end_date){
                            $main_data=Helper::getGrossAmountData($property,$request->start_date,$request->end_date);
                            if($main_data['status']=="true"){
                                $main_data['total_guests']=$request->get("adults")+$request->get("child");
                                $main_data['adults']=$request->get("adults");
                                $main_data['child']=$request->get("child");
                                $main_data['start_date']=$request->get("start_date");
                                $main_data['end_date']=$request->get("end_date");
                                $main_data['adults']=$request->get("adults");
                                $main_data['childs']=$request->get("child");
                                $main_data['pet_fee_data_guarav']=$request->get("pet");
                                $main_data['heating_pool_fee']=$request->get("heating_pool_fee");
                                return view($templete,compact("data","main_data","property"));
                            }else if($main_data['status']=="already-booked"){
                                return redirect(PropertyUrl::url($property))->with("danger","Some Dates are already booked");
                            }else if($main_data['status']=="min-stay-day"){
                                return redirect(PropertyUrl::url($property))->with("danger","Minimum stay criteria not met");
                            }else if($main_data['status']=="date-price"){
                                return redirect(PropertyUrl::url($property))->with("danger","Price is not defined");
                            }else if($main_data['status']=="checkin-checkout-day"){
                                return redirect(PropertyUrl::url($property))->with("danger",$main_data['message']);
                            }else{
                                return redirect(PropertyUrl::url($property))->with("danger","Invalid Calling");
                            }
                        }else{
                            return redirect(PropertyUrl::url($property))->with("danger","Invalid Checkout");
                        }
                    }else{
                        return redirect(PropertyUrl::url($property))->with("danger","Invalid Checkin");
                    }
                }else{
                    return redirect()->back()->with("danger","Invalid Property");
                }
            }else{
               return view($templete,compact("data"));
            }
        }
        $propertyCategory = \App\Models\PropertyCategory::where('slug', $seo_url)
            ->where('status', true)
            ->first();
        if ($propertyCategory) {
            return redirect()->route('stays', ['category' => $propertyCategory->slug]);
        }

        return $this->redirectToCanonicalProperty($seo_url);
    }

public function saveBookingData(Request $request)
{

    /* ==================================
       🔐 LOG REFERENCE (TRACE EVERYTHING)
    ================================== */
    $logRef = 'BOOKING_' . now()->timestamp . '_' . rand(1000, 9999);

    Log::info("[$logRef] Booking process started", $request->except(['stripeToken']));

    // Detect AJAX/JSON callers (v1.1 plan, spec §4.1). The checkout-page JS
    // posts here via fetch() and expects a JSON envelope; legacy form-submit
    // callers (none currently known) still receive a redirect-back-with-flash.
    $wantsJson = $request->wantsJson() || $request->expectsJson();

    /* ==================================
       1️⃣ BASIC VALIDATIONS
    ================================== */

    // Two response-shape zones in this method:
    //   - Early-validation guards (this section + the date check below) use
    //     inline `if ($wantsJson) { return JSON; } return redirect();` blocks.
    //   - Exception-path guards (catch blocks at the bottom) accumulate into
    //     $message + $errorCode and fall through to the unified return at the
    //     end of the method.
    // Both produce the same JSON envelope shape; the split exists because
    // catches cannot return without exiting the method while leaving the catch
    // structure incomplete.
    if (!$request->property_id) {
        Log::warning("[$logRef] Missing property_id");
        if ($wantsJson) {
            return response()->json([
                'success' => false,
                'error_code' => 'validation_failed',
                'error_message' => 'Property is required.',
            ], 200);
        }
        return redirect()->back()->with("danger", "Property is required.");
    }

    $property = Property::find($request->property_id);

    if (!$property) {
        Log::warning("[$logRef] Property not found", [
            'property_id' => $request->property_id
        ]);
        if ($wantsJson) {
            return response()->json([
                'success' => false,
                'error_code' => 'invalid_booking',
                'error_message' => 'Property not found.',
            ], 200);
        }
        return redirect()->back()->with("danger", "Property not found.");
    }

    if (!$request->checkin || !$request->checkout) {
        Log::warning("[$logRef] Invalid checkin / checkout dates");
        if ($wantsJson) {
            return response()->json([
                'success' => false,
                'error_code' => 'validation_failed',
                'error_message' => 'Invalid Checkin / Checkout',
            ], 200);
        }
        return redirect()->back()->with("danger", "Invalid Checkin / Checkout");
    }

    /* ==================================
       2️⃣ DATE & AMOUNT VALIDATION
    ================================== */

    Log::info("[$logRef] Calculating booking amount", [
        'checkin'  => $request->checkin,
        'checkout' => $request->checkout
    ]);

    $main_data = Helper::getGrossAmountData(
        $property,
        $request->checkin,
        $request->checkout
    );

    Log::info("[$logRef] Gross amount result", $main_data);

    if (!isset($main_data['status']) || $main_data['status'] !== true) {
        Log::warning("[$logRef] Booking validation failed", $main_data);
        $message = $main_data['message'] ?? "Booking not allowed";
        if ($wantsJson) {
            return response()->json([
                'success' => false,
                'error_code' => 'invalid_booking',
                'error_message' => $message,
            ], 200);
        }
        return redirect()->back()->with("danger", $message);
    }

    /* ==================================
       3️⃣ CREATE / UPDATE BOOKING
    ================================== */

    $data = $request->except(['_token', 'stripeToken']);
    $data['quote_date'] = now();
    $data['quote_ip'] = Helper::getIp();
    $data['booking_status'] = 'pending-payment';
    $data['rental_aggrement_status'] = 'false';

    $booking = BookingRequest::updateOrCreate(
        ['request_id' => $request->request_id],
        $data
    );

    Log::info("[$logRef] Booking saved", [
        'booking_id' => $booking->id,
        'request_id' => $request->request_id
    ]);

    /* ==================================
       3.5️⃣  NON-STRIPE GATEWAY HAND-OFF
       ==================================
       Until Phase 4 (2026-05-07) this controller ran the Stripe path
       unconditionally regardless of the chosen payment_method, so picking
       PayPal / AuthorizeNet / Square produced a "[Stripe] AuthenticationException"
       (and then `($e->getError()->message ?? $e->getMessage())` crashed because getError() is null
       on wrapped exceptions). Branch off here for any non-Stripe method —
       the booking row is already persisted, just hand off to that gateway's
       redirect/post route. The Stripe code below runs only when method=stripe.
    */
    $paymentMethod = strtolower((string) $request->input('payment_method', 'stripe'));
    if ($paymentMethod !== 'stripe') {
        // Redirect to each gateway's GET *_payment display route — the page
        // that renders the provider's own UI (PayPal SDK button, Authorize
        // hosted form, Square Web Payments SDK). Using the *.submit POST
        // routes here would 302 → GET and miss the handler.
        $routes = [
            'paypal'    => 'paypal_payment',     // GET /booking/payment/paypal/{id}
            'authorize' => 'authorize_payment',  // GET /booking/payment/authorize/{id}
            'square'    => 'square_payment',     // GET /booking/payment/square/{id}
        ];
        if (!isset($routes[$paymentMethod])) {
            Log::warning("[$logRef] Unknown payment method", ['method' => $paymentMethod]);
            return redirect()->back()->with('danger', 'Unsupported payment method.');
        }
        Log::info("[$logRef] Handing off to {$paymentMethod}", [
            'route' => $routes[$paymentMethod],
            'booking_id' => $booking->id,
        ]);
        // Native form submit follows redirect; AJAX paths (Stripe) won't reach here.
        return redirect()->route($routes[$paymentMethod], ['id' => $booking->id]);
    }

    /* ==================================
       4️⃣ STRIPE KEYS (PROPERTY > GLOBAL)
    ================================== */

    $key = [
        'stripe_publish_key' => ModelHelper::getDataFromSetting('stripe_publish_key'),
        'stripe_secret_key'  => ModelHelper::getDataFromSetting('stripe_secret_key'),
    ];

    if ($property->stripe_publish_key && $property->stripe_secret_key) {
        $key = [
            'stripe_publish_key' => $property->stripe_publish_key,
            'stripe_secret_key'  => $property->stripe_secret_key,
        ];
    }

    Log::info("[$logRef] Stripe keys selected", [
        'property_keys' => $property->stripe_publish_key ? 'YES' : 'NO'
    ]);

    /* ==================================
       5️⃣ STRIPE PAYMENT
    ================================== */

    $errorCode = 'gateway_error';

    try {
        Stripe::setApiKey($key['stripe_secret_key']);

        Log::info("[$logRef] Creating Stripe customer");

        $customer = Customer::create([
            "email" => $booking->email,
            "name"  => $booking->name ?? '',
        ]);

        Log::info("[$logRef] Stripe customer created", [
            'customer_id' => $customer->id
        ]);

        Customer::createSource(
            $customer->id,
            ["source" => $request->stripeToken]
        );

        Log::info("[$logRef] Stripe card attached");

        $amount = $request->total_amount;

        Log::info("[$logRef] Charging customer", [
            'amount' => $amount
        ]);

        $charge = Charge::create([
            "customer"    => $customer->id,
            "amount"      => $amount * 100,
            "currency"    => "usd",
            "description" => "Booking Payment for " . $property->name,
        ]);

        $chargeData = $charge->jsonSerialize();

        Log::info("[$logRef] Stripe charge response", $chargeData);

        if (
            $chargeData['paid'] == 1 &&
            $chargeData['captured'] == 1 &&
            empty($chargeData['failure_code'])
        ) {

            /* ==================================
               6️⃣ PAYMENT RECORD (idempotent)
            ================================== */

            // Idempotent insert keyed on Stripe charge id — guards against
            // reload-driven duplicate inserts (matches StripeController::indexPost
            // pattern from Plan C). The threat model is browser reload / back-button
            // after the first response committed; wasRecentlyCreated returns false
            // on the second pass and we skip emails + status update.
            //
            // Known limitation: payments.tran_id has no UNIQUE constraint at the
            // DB layer, so a true race between two simultaneous requests (both
            // SELECT empty before either INSERT commits) could create duplicate
            // rows. This is a pre-existing project-wide pattern (all 4 gateway
            // controllers behave the same). The race window is narrow enough on
            // single-process Apache (XAMPP) deployments to be acceptable for v1.1;
            // v1.2 should add `ALTER TABLE payments ADD UNIQUE INDEX (tran_id(191))`
            // and convert the column from `text` to `varchar(191)`.
            $payment = Payment::firstOrCreate(
                ['tran_id' => $chargeData['id']],
                [
                    'booking_id'          => $booking->id,
                    'receipt_url'         => $chargeData['receipt_url'] ?? null,
                    'customer_id'         => $chargeData['customer'],
                    'balance_transaction' => $chargeData['balance_transaction'],
                    'description'         => json_encode($chargeData),
                    'status'              => 'complete',
                    'type'                => 'stripe',
                    'amount'              => $amount,
                ]
            );

            Log::info("[$logRef] Payment record (firstOrCreate)", [
                'payment_id' => $payment->id,
                'wasRecentlyCreated' => $payment->wasRecentlyCreated,
            ]);

            if ($payment->wasRecentlyCreated) {
                /* ==================================
                   7️⃣ UPDATE BOOKING STATUS (only on first execution)
                ================================== */

                $paidSoFar = (float) Payment::where('booking_id', $booking->id)
                    ->whereIn('status', ['complete', 'completed', 'succeeded', 'paid'])
                    ->sum('amount');
                $paymentStatus = ((float) ($booking->total_amount ?? 0) > 0 && $paidSoFar + 0.01 >= (float) $booking->total_amount)
                    ? 'paid'
                    : 'partially';
                $paymentCount = (int) Payment::where('booking_id', $booking->id)
                    ->whereIn('status', ['complete', 'completed', 'succeeded', 'paid'])
                    ->count();

                BookingRequest::where('id', $booking->id)->update([
                    'booking_status' => 'booked',
                    'payment_status' => $paymentStatus,
                    'how_many_payment_done' => $paymentCount,
                ]);
                $booking->refresh();
                app(\App\Services\Payments\DirectInstallmentScheduler::class)->schedule(
                    $booking,
                    (string) ($chargeData['customer'] ?? ''),
                    null,
                    strtoupper((string) ($chargeData['currency'] ?? 'USD'))
                );

                Log::info("[$logRef] Booking confirmed");

                /* ==================================
                   8️⃣ EMAILS (only on first execution)
                ================================== */

                $files = [];
                if ($property->rental_aggrement_attachment) {
                    $files[] = $property->rental_aggrement_attachment;
                }

                $bookingArr = BookingRequest::find($booking->id)->toArray();

                Log::info("[$logRef] Sending user email", [
                    'email' => $bookingArr['email']
                ]);

                // Phase 13D — confirmed/payment-received email is admin-editable on
                // /admin/settings/stay-communications (Booking lifecycle tab → Booking confirmed).
                $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
                $brand    = ModelHelper::getDataFromSetting('mailer_admin_name')
                         ?: ModelHelper::getDataFromSetting('mail_from_name')
                         ?: ModelHelper::getDataFromSetting('owner_name')
                         ?: 'Notification';
                $footer   = ModelHelper::getDataFromSetting('mail_footer') ?: '';

                $subject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('confirmed_default_subject') ?: 'Booking confirmed: {{property_name}}'));
                $body    = $resolver->expandMergeFieldsWithQuote((string) (ModelHelper::getDataFromSetting('confirmed_default_body_html') ?: '<p>Hi {{guest_name}},</p><p>Your booking is confirmed.</p><p>{{brand}}</p>'));
                $html    = view('mail.email-shell', ['body_html_rendered' => $body, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $booking, 'property' => $property])->render();

                MailHelper::emailSenderByController($html, $bookingArr['email'], $subject, $files);
                \App\Services\Email\SendLog::record((int)$booking->id, 'confirmed', (string)$bookingArr['email'], $subject, $html);

                $adminMail = ModelHelper::getDataFromSetting('booking_receiving_mail') ?: ModelHelper::getDataFromSetting('admin_email_primary');

                Log::info("[$logRef] Sending admin email", ['email' => $adminMail]);

                if ($adminMail) {
                    // Phase 13E — admin alert editable on Email Templates → Owner alerts → Booking confirmed.
                    $adminSubject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('admin_alert_confirmed_subject') ?: 'Booking confirmed — {{property_name}}'));
                    $adminBody    = $resolver->expandMergeFieldsWithQuote((string) (ModelHelper::getDataFromSetting('admin_alert_confirmed_body_html') ?: '<p>Booking #{{booking_id}} confirmed.</p><p><a href="{{admin_url}}">View →</a></p>'));
                    $adminHtml    = view('mail.email-shell', ['body_html_rendered' => $adminBody, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $booking, 'property' => $property])->render();
                    MailHelper::emailSenderByController($adminHtml, $adminMail, $adminSubject, $files);
                }
            } else {
                Log::info("[$logRef] Duplicate execution detected — skipping status update + emails", [
                    'payment_id' => $payment->id,
                    'tran_id' => $chargeData['id'],
                ]);
            }

            /* ==================================
               9️⃣ SUCCESS RESPONSE
            ================================== */

            Log::info("[$logRef] Booking completed successfully");

            if ($wantsJson) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => url('payment/success/' . $payment->id),
                ]);
            }

            return redirect('payment/success/' . $payment->id)
                ->with("success", "Payment successful. Booking confirmed.");
        }

        Log::error("[$logRef] Stripe payment failed", $chargeData);
        $message = "Payment failed";
        $errorCode = 'gateway_error';
        if ($wantsJson) {
            return response()->json([
                'success' => false,
                'error_code' => $errorCode,
                'error_message' => $message,
            ], 200);
        }
        return redirect()->back()->with("danger", $message);

    } catch (\Stripe\Exception\CardException $e) {
        $message = ($e->getError()->message ?? $e->getMessage());
        $errorCode = 'card_declined';
        Log::warning("[$logRef] Stripe card declined", ['message' => $message]);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        $message = ($e->getError()->message ?? $e->getMessage());
        $errorCode = 'validation_failed';
        Log::warning("[$logRef] Stripe invalid request", ['message' => $message]);
    } catch (\Stripe\Exception\RateLimitException $e) {
        $message = ($e->getError()->message ?? $e->getMessage());
        $errorCode = 'gateway_error';
        Log::warning("[$logRef] Stripe rate limit", ['message' => $message]);
    } catch (\Stripe\Exception\AuthenticationException $e) {
        $message = ($e->getError()->message ?? $e->getMessage());
        $errorCode = 'gateway_error';
        Log::error("[$logRef] Stripe authentication failed", ['message' => $message]);
    } catch (\Stripe\Exception\ApiConnectionException $e) {
        $message = ($e->getError()->message ?? $e->getMessage());
        $errorCode = 'gateway_error';
        Log::error("[$logRef] Stripe API connection failed", ['message' => $message]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        $message = ($e->getError()->message ?? $e->getMessage());
        $errorCode = 'gateway_error';
        Log::error("[$logRef] Stripe API error", ['message' => $message]);
    } catch (\Exception $e) {
        $message = $e->getMessage();
        $errorCode = 'gateway_error';
        Log::error("[$logRef] Unexpected exception", [
            'message' => $message,
            'file'    => $e->getFile(),
            'line'    => $e->getLine()
        ]);
    }

    if ($wantsJson) {
        return response()->json([
            'success' => false,
            'error_code' => $errorCode,
            'error_message' => $message ?? 'Payment failed',
        ], 200);
    }
    return redirect()->back()->with("danger", $message ?? 'Payment failed');
}



    public function previewBooking(Request $request , $id){
        $booking=BookingRequest::find($id);
        if($booking){
            $property=Property::find($booking->property_id);
            if($property){
                $data = new \stdClass();
                $data->name="Booking Request";
                $data->meta_title="Booking Request";
                $data->meta_keywords="Booking Request";
                $data->meta_description="Booking Request";
                return view("front.booking.preview",compact("booking","data","property"));
            }
        }
        return abort(404);
    }

    public function rentalAggrementBooking(Request $request , $id){
        $booking=BookingRequest::find($id);
        if($booking){
             if($booking->rental_aggrement_status!="true"){
                $property=app(\App\Services\Booking\RentalAgreementPropertyResolver::class)->resolve($booking);
                if($property){
                    $data = new \stdClass();
                    $data->name="Rental Agreement  ";
                    $data->meta_title="Rental Agreement  ";
                    $data->meta_keywords="Rental Agreement  ";
                    $data->meta_description="Rental Agreement  ";
                    return view("front.booking.rentalAggrementBooking",compact("booking","data","property"));
                }
             }else{
                if ($booking->pms_source) {
                    return redirect()->to('pms/payment/success/'.$booking->id);
                }
                if($booking->operation=="check-booking"){
                  	BookingRequest::where("id", $id)->update(["booking_status" => "booked"]);
                  	$booking=BookingRequest::find($id);
                     return redirect()->to('check-payment/success/'.$booking->id);
                }elseif($booking->operation=="venmo-booking"){
                  	BookingRequest::where("id", $id)->update(["booking_status" => "booked"]);
                  	$booking=BookingRequest::find($id);
                     return redirect()->to('venmo-payment/success/'.$booking->id);
                }else{
                    return redirect()->to('booking/payment/paypal/'.$booking->id)->with("danger","Rental Agreement already submitted"); 
                }
            }
        }
        return abort(404);
    }

    public function rentalAggrementDataSave(Request $request){
        if($request->booking_id){
            $booking=BookingRequest::find($request->booking_id);
            if($booking){
                if($booking->rental_aggrement_status!="true"){
                    $property=app(\App\Services\Booking\RentalAgreementPropertyResolver::class)->resolve($booking);
                    if($property){
                        // Validate consent
                        $consentText = (string) $request->input('consent_text', '');
                        $consentHash = (string) $request->input('consent_hash', '');
                        $expectedHash = hash_hmac('sha256', $consentText, config('app.key'));
                        if (!$request->input('consent') || $consentText === '' || !hash_equals($expectedHash, $consentHash)) {
                            return redirect()->back()->with('danger', 'Please confirm the consent statement before signing.')->withInput();
                        }

                        $png_url = "signature-".time().".png";
                        $path = public_path().'/uploads/signature/' . $png_url;
                        Image::read(file_get_contents($request->signature))->save($path);

                        $data=$request->all();
                        $booking->rental_aggrement_status="true";
                        if(isset ($request->file("image")[0])){
                            $booking->rental_aggrement_images = Upload::fileUpload($request->file("image")[0],"cms");
                        }
                        // Legacy audit columns — keep populating for existing readers
                        $booking->rentar_agreement_date=date('Y-m-d H:i:s');
                        $booking->rentar_agreement_ip=Helper::getIp();
                        $booking->rental_aggrement_signature='uploads/signature/' . $png_url;

                        // New properly-named audit columns
                        $signedAt = now();
                        $booking->rental_agreement_signed_at         = $signedAt;
                        $booking->rental_agreement_signed_ip         = $request->ip();
                        $booking->rental_agreement_signed_user_agent = substr((string) $request->userAgent(), 0, 500);
                        $booking->rental_agreement_consent_text      = $consentText;

                        // Generate per-booking PDF
                        try {
                            $signaturePngPath = public_path('uploads/signature/' . $png_url);
                            $signatureDataUrl = 'data:image/png;base64,' . base64_encode(file_get_contents($signaturePngPath));

                            $generator = app(\App\Services\Booking\RentalAgreementGenerator::class);
                            $pdfRelativePath = $generator->generate($booking, [
                                'signature_data_url'   => $signatureDataUrl,
                                'signed_at_iso'        => $signedAt->utc()->toIso8601String(),
                                'signed_ip'            => $request->ip(),
                                'signed_user_agent'    => substr((string) $request->userAgent(), 0, 500),
                                'signed_consent_text'  => $consentText,
                            ]);

                            $booking->rental_agreement_pdf_path = $pdfRelativePath;
                            $booking->rental_agreement_link     = $pdfRelativePath; // legacy column repurposed
                        } catch (\Throwable $e) {
                            \Log::error('Rental agreement PDF generation failed', [
                                'booking_id' => $booking->id,
                                'error'      => $e->getMessage(),
                            ]);
                            return redirect()->back()->with('danger', 'Could not generate the signed agreement. Please try again.')->withInput();
                        }

                        if ($booking->pms_source) {
                            $booking->booking_status = $booking->booking_status ?: 'booked';
                        } elseif($booking->operation=="direct-booking"){
                          	$booking->booking_status="rental-aggrement-success";
                        }else{
                            $booking->booking_status="booked";
                            $booking->payment_status="check-booking";
                        }
                        $booking->save();

                        // Dispatch new RentalAgreementSigned mailable to guest + admin
                        try {
                            $freshBooking = BookingRequest::find($request->booking_id);
                            $adminEmail = ModelHelper::getDataFromSetting('rental_aggrement_receiving_mail')
                                ?: (ModelHelper::getDataFromSetting('contact_us_receiving_mail') ?: config('mail.from.address'));

                            if ($freshBooking->email) {
                                \Mail::to($freshBooking->email)->send(new \App\Mail\RentalAgreementSigned($freshBooking, $property, $pdfRelativePath, 'user'));
                            }
                            if ($adminEmail) {
                                \Mail::to($adminEmail)->send(new \App\Mail\RentalAgreementSigned($freshBooking, $property, $pdfRelativePath, 'admin'));
                            }
                        } catch (\Throwable $e) {
                            \Log::warning('Rental agreement signed-email dispatch failed', [
                                'booking_id' => $booking->id,
                                'error'      => $e->getMessage(),
                            ]);
                            // Continue — the signed PDF exists; email failure is recoverable manually
                        }

                        $data=BookingRequest::find($request->booking_id)->toArray();
                        if($booking->pms_source){
                             return redirect()->to('pms/payment/success/'.$data['id']);
                        }elseif($data['operation']=="check-booking"){
                             return redirect()->to('check-payment/success/'.$data['id']);
                        }elseif($data['operation']=="venmo-booking"){
                             return redirect()->to('venmo-payment/success/'.$data['id']);
                        }else{
                            return redirect()->to('booking/payment/paypal/'.$data['id']);
                        }
                    }
                }else{
                     if($booking->pms_source){
                       return redirect()->to('pms/payment/success/'.$booking->id);
                     }
                     if($booking->operation=="check-booking"){
                       return redirect()->to('check-payment/success/'.$booking->id);
                     }else{
                       return redirect()->to('booking/payment/paypal/'.$booking->id);
                     }
                     return redirect()->to('booking/payment/paypal/'.$booking->id)->with("danger","Rental Agreement already submitted");
                }
            }
        }
        return abort(404);
    }

    public function notfound(){
        return view("errors.404");
    }

    public function sitemap(){
        return response()->view("front.sitemap")->header('Content-Type', 'text/xml');
    }
}

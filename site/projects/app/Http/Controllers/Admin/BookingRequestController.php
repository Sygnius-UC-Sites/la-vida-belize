<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookingRequest;
use App\Models\Property;
use App\Helper\Upload;
use App\Helper\StripeHelper;
use Validator;
use MailHelper;
use LiveCart;
use ModelHelper;
use Stripe;
use App\Models\ScheduledPayment;

class BookingRequestController extends Controller{
    
    public function __construct(BookingRequest $model){
        $this->model=$model;
        $this->admin_base_url="booking-enquiries.index";
        $this->admin_view="admin.booking-enquiries";
    }
    
    function getCheckinCheckoutDataGaurav(Request $request){
        $new_data_blocked=LiveCart::iCalDataCheckInCheckOut($request->id);
        $checkin=$new_data_blocked['checkin'];
        $checkout=$new_data_blocked['checkout'];
        return response()->json($new_data_blocked);
    }
    
    public function index(){
        $data=$this->model::orderBy("id","desc")->get();

        // Imported reservations from connected PMS accounts (Lodgify/Guesty/…).
        // These are external bookings (Airbnb/Booking.com/direct-on-PMS) that
        // drive calendar blocking. Surfaced here read-only so the owner sees
        // every upcoming stay — not just bookings made on this website.
        $pmsBookings = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('pms_bookings')) {
            $pmsBookings = \Illuminate\Support\Facades\DB::table('pms_bookings')
                ->where(function ($q) {
                    // is_deleted is NULL for normal rows, 1 only for tombstones.
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderByRaw("date(arrival) >= date('now') desc")  // upcoming first
                ->orderBy('arrival', 'desc')
                ->limit(300)
                ->get();

            // Provider bookings often omit property_name — resolve it from the
            // synced room (by provider property id) so the table isn't blank.
            $roomNames = \Illuminate\Support\Facades\Schema::hasTable('pms_rooms')
                ? \Illuminate\Support\Facades\DB::table('pms_rooms')->pluck('name', 'pms_property_id')
                : collect();
            foreach ($pmsBookings as $b) {
                if (empty($b->property_name)) {
                    $b->property_name = $roomNames[$b->property_id] ?? null;
                }
            }
        }
        return view($this->admin_view.".index",compact("data","pmsBookings"));
    }
    
    public function singlePropertyBookoing($id){
        $property=Property::find($id);
        if($property){
             $data=$this->model::where("property_id",$id)->orderBy("id","desc")->get();
            return view($this->admin_view.".show",compact("data"));
        }   
        return abort(404);
    }
    
    public function create(){
        return view($this->admin_view.".create");
    }

    public function store(Request $request){
        $validator = Validator::make($request->all(), [ ]);   
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $data=$request->all();
        if ($request->hasFile("image")) {
            $data['image'] = Upload::fileUpload($request->file("image"),"booking-enquiries");
        }
        if ($request->hasFile("bannerImage")) {
            $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"),"booking-enquiries");
        }
        if($request->booking_type_admin){
            if($request->booking_type_admin!="invoice"){
                $data['booking_status']="booked";
            }
        }
        if($request->product_name){
            $ar=[];
            foreach($request->product_name as $key=>$value){
                $ar[]=["product_name"=>$request->product_name[$key],"product_amount"=>$request->product_amount[$key]]; 
            }
            $data['custom_before_total_fees']=json_encode($ar);
        }else{
            $data['custom_before_total_fees']=json_encode([]);
        }
        $data=$this->model::create($data);
        if($request->booking_type_admin){
            if($request->booking_type_admin!="invoice"){
                return redirect()->route($this->admin_base_url)->with("success","Successfully Added");
            }
        }
        return redirect()->route('booking-enquiry-confirm',$data->id)->with("success","Successfully Added");
    }
  
    public function show($id){
        return redirect()->route($this->admin_base_url);
    }
   
    public function edit($id){
        $data=$this->model::find($id);
        if($data){
            return view($this->admin_view.".edit",compact("data"));
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }
  
    public function update(Request $request, $id){
        $validator = Validator::make($request->all(), [ ]);   
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $exist=$this->model::find($id);
        if($exist){
            $data=$request->all();
            if ($request->hasFile("image")) {
                $data['image'] = Upload::fileUpload($request->file("image"),"booking-enquiries");
            }
            if ($request->hasFile("bannerImage")) {
                $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"),"booking-enquiries");
            }
            $property_id=$exist->property_id;
            if($request->booking_type_admin){
                if($request->booking_type_admin!="invoice"){
                    $data['booking_status']="booked";
                }
            }
            if($request->product_name){
                $ar=[];
                foreach($request->product_name as $key=>$value){
                    $ar[]=["product_name"=>$request->product_name[$key],"product_amount"=>$request->product_amount[$key]];
                }
                $data['custom_before_total_fees']=json_encode($ar);
            }else{
                $data['custom_before_total_fees']=json_encode([]);
            }

            // Phase 13J — persist before_total_fees + after_total_fees as JSON from the new
            // structured-editor arrays. Empty/zero rows are filtered out on save.
            $packFees = function (?array $names, ?array $amounts) {
                $out = [];
                foreach ((array) $names as $i => $n) {
                    $name = trim((string) $n);
                    $amt  = (float) ($amounts[$i] ?? 0);
                    if ($name === '' || $amt == 0) continue;
                    $out[] = ['name' => $name, 'amount' => $amt];
                }
                return $out;
            };
            if ($request->has('before_fees_name') || $request->has('before_fees_amount')) {
                $data['before_total_fees'] = json_encode($packFees($request->input('before_fees_name'), $request->input('before_fees_amount')));
            }
            if ($request->has('after_fees_name') || $request->has('after_fees_amount')) {
                $data['after_total_fees'] = json_encode($packFees($request->input('after_fees_name'), $request->input('after_fees_amount')));
            }

            // Phase 13K — auto-recompute total_amount on save when the booking is
            // unpaid (no risk of overwriting a real captured charge). For paid /
            // partially-paid bookings, we trust the explicit total the admin typed
            // (the form shows a yellow warning that delta is manual).
            $isPaid = in_array(($exist->payment_status ?? null), ['paid', 'partially'], true);
            if (!$isPaid) {
                $sum = 0;
                $sum += (float) ($data['gross_amount'] ?? $exist->gross_amount ?? 0);
                // Custom admin-added (product_amount[])
                foreach ((array) $request->input('product_amount', []) as $v) $sum += (float) $v;
                // Pre-tax fee lines (Phase 13J)
                foreach ((array) $request->input('before_fees_amount', []) as $v) $sum += (float) $v;
                // Post-tax fee lines
                foreach ((array) $request->input('after_fees_amount', []) as $v) $sum += (float) $v;
                $sum += (float) ($exist->pet_fee ?? 0);
                $sum += (float) ($exist->guest_fee ?? 0);
                $sum += (float) ($exist->tax ?? 0);
                $sum -= (float) ($exist->discount ?? 0);
                $data['total_amount'] = round($sum, 2);
            }

            $this->model::find($id)->update($data);

            // Phase 13H — optional re-send of selected lifecycle emails after edit.
            // Form submits resend_emails[] = ['welcome', 'checkin', ...]; we delegate
            // to resendEmail() for each so the same logic + failure tracking applies.
            $resendList = (array) $request->input('resend_emails', []);
            $resendResults = [];
            foreach ($resendList as $type) {
                if (!in_array($type, ['welcome','checkin','checkout','review','cancellation','awaiting-payment'], true)) continue;
                $resp = $this->resendEmail($request, $id, $type);
                $payload = $resp instanceof \Illuminate\Http\JsonResponse ? json_decode($resp->getContent(), true) : null;
                $resendResults[$type] = $payload['ok'] ?? false;
            }
            $resendNote = '';
            if (!empty($resendList)) {
                $okCount = count(array_filter($resendResults));
                $resendNote = " — re-sent {$okCount}/" . count($resendList) . " selected email(s).";
            }

            return redirect()->route($this->admin_base_url)->with("success","Successfully Updated" . $resendNote);
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    public function destroy($id){
        $exist=$this->model::find($id);
        if(!$exist){
            return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
        }

        $exist->booking_status='cancelled';
        $exist->cancel_at = now();
        $exist->save();

        $cancelledInstallments = ScheduledPayment::where('booking_request_id', $exist->id)
            ->where('status', 'pending')
            ->count();
        ScheduledPayment::where('booking_request_id', $exist->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
        if ($cancelledInstallments > 0) {
            \Log::channel('lodgify')->info('Admin: cancelled pending direct-booking installments', [
                'booking_id' => $exist->id,
                'count'      => $cancelledInstallments,
            ]);
        }

        if (!empty($exist->stripe_deposit_charge_id) && $exist->security_deposit_status === 'authorized') {
            try {
                Stripe\Stripe::setApiKey(ModelHelper::getDataFromSetting('stripe_secret_key'));
                StripeHelper::releaseDepositHold($exist->stripe_deposit_charge_id);
                $exist->security_deposit_status = 'released';
                $exist->save();
            } catch (\Throwable $e) {
                \Log::channel('lodgify')->error('Admin: direct-booking deposit release failed on cancel', [
                    'booking_id' => $exist->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $property = Property::find($exist->property_id) ?? Property::where('pms_room_id', $exist->property_id)->first();
        if($property){
            LiveCart::getFileIcalFileData($exist->property_id);

            // Phase 13C — admin-editable cancellation email (subject + HTML body
            // with merge tokens, both managed on /admin/settings/stay-communications).
            // The previous booking_type_admin == "invoice" filter was dropped — every
            // cancellation now notifies the guest.
            $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $exist);
            $subjectTemplate = (string) (\ModelHelper::getDataFromSetting('cancellation_default_subject') ?: 'Your booking at {{property_name}} has been cancelled');
            $bodyTemplate    = (string) (\ModelHelper::getDataFromSetting('cancellation_default_body_html') ?: '<p>Hi {{guest_name}},</p><p>Your booking at {{property_name}} for {{check_in}} → {{check_out}} has been cancelled.</p><p>Best regards,<br>{{brand}}</p>');

            $renderedSubject = $resolver->expandMergeFields($subjectTemplate);
            $renderedBody    = $resolver->expandMergeFieldsWithQuote($bodyTemplate);

            $brand       = \ModelHelper::getDataFromSetting('mailer_admin_name')
                        ?: \ModelHelper::getDataFromSetting('mail_from_name')
                        ?: \ModelHelper::getDataFromSetting('owner_name')
                        ?: 'Notification';
            $mailFooter  = \ModelHelper::getDataFromSetting('mail_footer') ?: '';

            $shellHtml = view('mail.email-shell', [
                'body_html_rendered' => $renderedBody,
                'brand'              => $brand,
                'mail_footer'        => $mailFooter,
                'booking'            => $exist,
                'property'           => $property,
            ])->render();

            // 1) Customer (attach any admin-managed cancellation attachments).
            MailHelper::emailSenderByController($shellHtml, $exist->email, $renderedSubject, $resolver->attachmentsFor('cancellation'));
            \App\Services\Email\SendLog::record((int)$exist->id, 'cancellation', (string)$exist->email, $renderedSubject, $shellHtml);

            // 2) Admin alert — Phase 13E editable on Email Templates → Owner alerts → Booking cancelled.
            $adminTo = \ModelHelper::getDataFromSetting('cancel_receiving_mail')
                    ?: \ModelHelper::getDataFromSetting('admin_email_primary');
            if ($adminTo) {
                $adminSubject = $resolver->expandMergeFields((string) (\ModelHelper::getDataFromSetting('admin_alert_cancelled_subject') ?: 'Booking #{{booking_id}} cancelled — {{property_name}}'));
                $adminBody    = $resolver->expandMergeFieldsWithQuote((string) (\ModelHelper::getDataFromSetting('admin_alert_cancelled_body_html') ?: '<p>Booking #{{booking_id}} cancelled.</p><p><a href="{{admin_url}}">View →</a></p>'));
                $adminShellHtml = view('mail.email-shell', [
                    'body_html_rendered' => $adminBody,
                    'brand'              => $brand,
                    'mail_footer'        => $mailFooter,
                    'booking'            => $exist,
                    'property'           => $property,
                ])->render();
                MailHelper::emailSenderByController($adminShellHtml, $adminTo, $adminSubject);
            }
        }
        return redirect()->route($this->admin_base_url)->with("success","Successfully Cancelled");
    }

    /**
     * Phase 13G — manually re-fire any of the lifecycle emails for a single booking.
     * Used by the "Send now" buttons in the booking-detail modal.
     *
     * Type ∈ {welcome, checkin, checkout, review, cancellation, awaiting-payment}.
     * Returns JSON for the AJAX caller in the modal.
     */
    public function resendEmail(Request $request, $id, $type)
    {
        $booking = BookingRequest::find($id);
        if (!$booking) return response()->json(['ok' => false, 'error' => 'Booking not found'], 404);
        $property = Property::find($booking->property_id) ?? Property::where('pms_room_id', $booking->property_id)->first();
        if (!$property) return response()->json(['ok' => false, 'error' => 'Property not found'], 404);

        try {
            switch ($type) {
                case 'welcome':
                    $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
                    $payload  = $resolver->resolveWelcomePayload();
                    $mailable = new \App\Mail\WelcomePackageSent($booking, $property, $payload['description_html'], $payload['files']);
                    $rendered = $mailable->render();
                    $subject  = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('welcome_default_subject') ?: 'Welcome to {{property_name}}'));
                    \MailHelper::emailSenderByController($rendered, $booking->email, $subject);
                    $booking->update(['welcome_email' => 'true', 'welcome_email_sent_at' => now(), 'welcome_email_attempted_at' => now(), 'welcome_email_error' => null]);
                    \App\Services\Email\SendLog::record((int)$booking->id, 'welcome', (string)$booking->email, $subject, $rendered);
                    return response()->json(['ok' => true, 'message' => 'Welcome email sent.']);

                case 'checkin':
                    $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
                    $payload  = $resolver->resolveCheckinPayload();
                    $mailable = new \App\Mail\CheckinInfoSent($booking, $property, $payload);
                    $rendered = $mailable->render();
                    $subject  = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('checkin_default_subject') ?: 'Check-in details for {{property_name}}'));
                    \MailHelper::emailSenderByController($rendered, $booking->email, $subject);
                    $booking->update(['checkin_email' => 'true', 'checkin_email_sent_at' => now(), 'checkin_email_attempted_at' => now(), 'checkin_email_error' => null]);
                    \App\Services\Email\SendLog::record((int)$booking->id, 'checkin', (string)$booking->email, $subject, $rendered);
                    return response()->json(['ok' => true, 'message' => 'Check-in info sent.']);

                case 'checkout':
                    $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
                    $payload  = $resolver->resolveCheckoutPayload();
                    $mailable = new \App\Mail\CheckoutInfoSent($booking, $property, $payload);
                    $rendered = $mailable->render();
                    $subject  = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('checkout_default_subject') ?: 'Check-out details for {{property_name}}'));
                    \MailHelper::emailSenderByController($rendered, $booking->email, $subject);
                    $booking->update(['checkout_email' => 'true', 'checkout_email_sent_at' => now(), 'checkout_email_attempted_at' => now(), 'checkout_email_error' => null]);
                    \App\Services\Email\SendLog::record((int)$booking->id, 'checkout', (string)$booking->email, $subject, $rendered);
                    return response()->json(['ok' => true, 'message' => 'Check-out info sent.']);

                case 'review':
                    $token = bin2hex(random_bytes(24));
                    \App\Models\Testimonial::firstOrCreate(
                        ['booking_id' => $booking->id],
                        ['property_id' => $booking->property_id, 'submission_token' => $token, 'status' => 'pending-link-sent', 'name' => $booking->name, 'email' => $booking->email, 'stay_date' => $booking->checkin, 'is_home' => 'false']
                    );
                    $existing = \App\Models\Testimonial::where('booking_id', $booking->id)->first();
                    $mailable = new \App\Mail\ReviewRequestSent($booking, $property, $existing->submission_token ?? $token);
                    $rendered = $mailable->render();
                    $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
                    $subject  = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('review_default_subject') ?: 'How was your stay at {{property_name}}?'));
                    \MailHelper::emailSenderByController($rendered, $booking->email, $subject);
                    $booking->update(['review_email' => 'true', 'review_email_sent_at' => now(), 'review_email_attempted_at' => now(), 'review_email_error' => null]);
                    \App\Services\Email\SendLog::record((int)$booking->id, 'review', (string)$booking->email, $subject, $rendered);
                    return response()->json(['ok' => true, 'message' => 'Review request sent.']);

                case 'cancellation':
                    $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
                    $brand    = ModelHelper::getDataFromSetting('owner_name') ?: ModelHelper::getDataFromSetting('mailer_admin_name') ?: ModelHelper::getDataFromSetting('mail_from_name') ?: 'Notification';
                    $footer   = ModelHelper::getDataFromSetting('mail_footer') ?: '';
                    $subject  = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('cancellation_default_subject') ?: 'Your booking at {{property_name}} has been cancelled'));
                    $body     = $resolver->expandMergeFieldsWithQuote((string) (ModelHelper::getDataFromSetting('cancellation_default_body_html') ?: '<p>Your booking has been cancelled.</p>'));
                    $html     = view('mail.email-shell', ['body_html_rendered' => $body, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $booking, 'property' => $property])->render();
                    MailHelper::emailSenderByController($html, $booking->email, $subject, $resolver->attachmentsFor('cancellation'));
                    \App\Services\Email\SendLog::record((int)$booking->id, 'cancellation', (string)$booking->email, $subject, $html);
                    return response()->json(['ok' => true, 'message' => 'Cancellation email sent.']);

                case 'awaiting-payment':
                    $magicLink = (string) \URL::temporarySignedRoute('booking.pay-link', now()->addDays(7), ['id' => $booking->id]);
                    $resolver  = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
                    $brand     = ModelHelper::getDataFromSetting('owner_name') ?: ModelHelper::getDataFromSetting('mailer_admin_name') ?: ModelHelper::getDataFromSetting('mail_from_name') ?: 'Notification';
                    $footer    = ModelHelper::getDataFromSetting('mail_footer') ?: '';
                    $extras    = ['{{magic_link}}' => $magicLink];
                    $subject   = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('awaiting_payment_default_subject') ?: 'Complete your booking at {{property_name}}'), $extras);
                    $body      = $resolver->expandMergeFieldsWithQuote((string) (ModelHelper::getDataFromSetting('awaiting_payment_default_body_html') ?: '<p>Pay at {{magic_link}}</p>'), $extras);
                    $html      = view('mail.email-shell', ['body_html_rendered' => $body, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $booking, 'property' => $property, 'cta_url' => $magicLink, 'cta_label' => 'Complete payment'])->render();
                    MailHelper::emailSenderByController($html, $booking->email, $subject, $resolver->attachmentsFor('awaiting-payment'));
                    \App\Services\Email\SendLog::record((int)$booking->id, 'awaiting-payment', (string)$booking->email, $subject, $html);
                    return response()->json(['ok' => true, 'message' => 'Payment link sent.']);

                case 'enquiry':
                    // Mirrors the auto-send in PageController@submitBookingRequest so the
                    // admin "Re-send" button works (QA D-003: previously hit default → fail).
                    $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
                    $brand    = ModelHelper::getDataFromSetting('mailer_admin_name') ?: ModelHelper::getDataFromSetting('mail_from_name') ?: ModelHelper::getDataFromSetting('owner_name') ?: 'Notification';
                    $footer   = ModelHelper::getDataFromSetting('mail_footer') ?: '';
                    $subject  = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('enquiry_default_subject') ?: 'We received your enquiry about {{property_name}}'));
                    $body     = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('enquiry_default_body_html') ?: '<p>Hi {{guest_name}},</p><p>Thanks for your interest in {{property_name}} — we will be in touch shortly.</p><p>{{brand}}</p>'));
                    $html     = view('mail.email-shell', ['body_html_rendered' => $body, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $booking, 'property' => $property])->render();
                    MailHelper::emailSenderByController($html, $booking->email, $subject);
                    \App\Services\Email\SendLog::record((int)$booking->id, 'enquiry', (string)$booking->email, $subject, $html);
                    return response()->json(['ok' => true, 'message' => 'Enquiry confirmation sent.']);

                case 'confirmed':
                    // Mirrors the auto-send on payment capture in PageController so the
                    // admin "Send now" button works (QA D-003).
                    $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
                    $brand    = ModelHelper::getDataFromSetting('mailer_admin_name') ?: ModelHelper::getDataFromSetting('mail_from_name') ?: ModelHelper::getDataFromSetting('owner_name') ?: 'Notification';
                    $footer   = ModelHelper::getDataFromSetting('mail_footer') ?: '';
                    $subject  = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('confirmed_default_subject') ?: 'Booking confirmed: {{property_name}}'));
                    $body     = $resolver->expandMergeFieldsWithQuote((string) (ModelHelper::getDataFromSetting('confirmed_default_body_html') ?: '<p>Hi {{guest_name}},</p><p>Your booking is confirmed.</p><p>{{brand}}</p>'));
                    $html     = view('mail.email-shell', ['body_html_rendered' => $body, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $booking, 'property' => $property])->render();
                    MailHelper::emailSenderByController($html, $booking->email, $subject);
                    \App\Services\Email\SendLog::record((int)$booking->id, 'confirmed', (string)$booking->email, $subject, $html);
                    return response()->json(['ok' => true, 'message' => 'Booking confirmation sent.']);

                default:
                    return response()->json(['ok' => false, 'error' => 'Unknown email type: ' . $type], 422);
            }
        } catch (\Throwable $e) {
            // Persist the failure on the booking row so the lifecycle UI shows red.
            $errorColumn = $type . '_email_error';
            $attemptColumn = $type . '_email_attempted_at';
            if (in_array($type, ['welcome','checkin','checkout','review'], true)) {
                $booking->update([$errorColumn => $e->getMessage(), $attemptColumn => now()]);
            }
            logger()->error('resendEmail failed', ['booking_id' => $id, 'type' => $type, 'exception' => $e]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Browser preview of every lifecycle email template, rendered with
     * sample-data merge tokens. Lets the admin see the full set in one
     * scroll-through page without needing a real booking.
     *
     * Routes:
     *   GET admin/email-templates           → index page (iframes for each type)
     *   GET admin/email-templates/{type}    → fully rendered HTML for type T
     */
    /** Internal — render a contact-form template (guest auto-reply / owner alert)
     *  with sample tokens. Mirrors the merge map used by PageController@contactPost. */
    private function renderContactTemplatePreview(string $type)
    {
        $brand  = ModelHelper::getDataFromSetting('mailer_admin_name')
               ?: ModelHelper::getDataFromSetting('mail_from_name')
               ?: 'Base Vacation Rentals';
        $footer = (string) ModelHelper::getDataFromSetting('mail_footer');

        $tokens = [
            '{{guest_name}}'    => 'Sample Guest',
            '{{guest_email}}'   => 'guest@example.com',
            '{{guest_phone}}'   => '+1 (555) 123-4567',
            '{{check_in}}'      => now()->addDays(30)->format('Y-m-d'),
            '{{check_out}}'     => now()->addDays(35)->format('Y-m-d'),
            '{{guests_count}}'  => '2',
            '{{property_type}}' => 'either',
            '{{property_slug}}' => '',
            '{{message}}'       => 'Sample message — looking forward to staying with you. We have a few flexibility on dates so let us know what works best!',
            '{{submission_id}}' => '999999',
            '{{brand}}'         => $brand,
            '{{site_email}}'    => (string) (ModelHelper::getDataFromSetting('email') ?: ''),
            '{{site_phone}}'    => (string) (ModelHelper::getDataFromSetting('mobile') ?: ''),
            '{{admin_url}}'     => url('admin/contact-us-enquiries'),
        ];

        // Map type slug → BasicSetting key prefix.
        $prefix = $type === 'contact-owner' ? 'admin_alert_contact' : 'contact_default';
        $subjectTpl = (string) (ModelHelper::getDataFromSetting($prefix . '_subject')
                        ?: ucfirst($type) . ' email');
        $bodyTpl    = (string) (ModelHelper::getDataFromSetting($prefix . '_body_html')
                        ?: '<p>(no body template)</p>');
        $subject = strtr($subjectTpl, $tokens);
        $body    = strtr($bodyTpl,    $tokens);

        $shellHtml = view('mail.email-shell', [
            'body_html_rendered' => $body,
            'brand'              => $brand,
            'mail_footer'        => $footer,
        ])->render();

        $banner = '<div style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#fef3c7;color:#78350f;border-bottom:1px solid #fcd34d;padding:14px 24px;font-size:13px;">'
            . '<strong>👁️ Template preview</strong> — sample data, not a real submission. '
            . '<strong>Subject:</strong> ' . e($subject)
            . '</div>';
        return response($banner . $shellHtml, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function templatePreviewIndex()
    {
        $types = [
            'enquiry'                  => ['label' => 'Enquiry confirmation',     'when' => 'After guest submits the enquiry form'],
            'awaiting-payment'         => ['label' => 'Awaiting payment',         'when' => 'After admin clicks Approve & send payment link'],
            'confirmed'                => ['label' => 'Booking confirmed',        'when' => 'After payment is captured'],
            'receipt'                  => ['label' => 'Payment receipt',          'when' => 'On every successful payment / instalment'],
            'welcome'                  => ['label' => 'Welcome package',          'when' => 'Cron, ~3 days after booking confirmed'],
            'checkin'                  => ['label' => 'Check-in info',            'when' => 'Cron, 2-3 days before check-in'],
            'checkout'                 => ['label' => 'Check-out info',           'when' => 'Cron, on check-out morning'],
            'review'                   => ['label' => 'Review request',           'when' => 'Cron, 2 days after check-out'],
            'cancellation'             => ['label' => 'Cancellation notice',      'when' => 'When admin cancels a confirmed booking'],
            'installment2'             => ['label' => 'Instalment 2 reminder',    'when' => 'When the 2nd payment is approaching'],
            'installment3'             => ['label' => 'Instalment 3 reminder',    'when' => 'When the final payment is approaching'],
            'contact-guest'            => ['label' => 'Contact form — guest auto-reply', 'when' => 'Immediately after a contact form is submitted'],
            'contact-owner'            => ['label' => 'Contact form — owner alert',     'when' => 'Immediately after a contact form is submitted'],
            // Phase 5 — rental-agreement lifecycle.
            'rental-agreement-request' => ['label' => 'Rental agreement — please-sign link', 'when' => 'After payment when post-confirmation signing is configured'],
            'rental-agreement-signed'  => ['label' => 'Rental agreement — signed (guest)',   'when' => 'When the guest completes signing'],
            'rental-agreement-signed-admin' => ['label' => 'Rental agreement — signed (owner alert)', 'when' => 'When the guest completes signing — copy to the owner inbox'],
        ];
        // Eager-load every attachment grouped by type so the index can render
        // its inline manager without N+1 queries (one per card).
        $attachments = \App\Models\EmailAttachment::orderBy('email_type')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('email_type');
        return view('admin.email-templates.index', compact('types', 'attachments'));
    }

    public function templatePreviewRender($type)
    {
        // Contact-form templates — no booking/resolver. Render directly with
        // the same merge-token map the live contactPost() uses, populated
        // with sample values.
        if ($type === 'contact-guest' || $type === 'contact-owner') {
            return $this->renderContactTemplatePreview($type);
        }
        // Build a sample BookingRequest + Property in memory so we can drive
        // the resolver without polluting the DB. Uses a real Property row if
        // one exists; otherwise fabricates a stub.
        $property = Property::first() ?: new Property([
            'name' => 'Casita Sample', 'address' => 'Valle Verde, Costa Rica',
            'standard_rate' => 250,
        ]);
        $booking = new BookingRequest([
            'name'         => 'Sample Guest',
            'email'        => 'guest@example.com',
            'mobile'       => '+1 (555) 123-4567',
            'property_id'  => $property->id ?? 0,
            'checkin'      => now()->addDays(30)->toDateString(),
            'checkout'     => now()->addDays(35)->toDateString(),
            'adults'       => 2, 'child' => 0, 'total_pets' => 0,
            'gross_amount' => 1250,
            'tax'          => 100,
            'currency'     => 'USD',
            'before_total_fees' => json_encode([
                ['name' => '5 nights', 'amount' => 1250, 'value' => 1250, 'type' => 'accommodation'],
                ['name' => 'Cleaning fee', 'amount' => 100, 'value' => 100, 'type' => 'cleaning'],
                ['name' => 'Taxes', 'amount' => 100, 'value' => 100, 'type' => 'tax'],
            ]),
            'amount_data' => json_encode([
                ['label' => 'Deposit (due today)', 'amount' => 725, 'date_due' => 'On agreement', 'is_current' => true],
                ['label' => 'Balance', 'amount' => 725, 'date_due' => now()->addDays(29)->toDateString(), 'is_current' => false],
            ]),
            'total_amount' => 1450,
            'total_night'  => 5,
        ]);
        $booking->id = 999999;

        $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
        $brand    = ModelHelper::getDataFromSetting('mailer_admin_name')
                 ?: ModelHelper::getDataFromSetting('mail_from_name')
                 ?: 'Base Vacation Rentals';
        $footer   = (string) ModelHelper::getDataFromSetting('mail_footer');

        $extras = [
            '{{magic_link}}'             => url('/#sample-magic-link'),
            '{{review_url}}'             => url('/#sample-review-link'),
            '{{rental_agreement_link}}'  => url('/#sample-rental-agreement-link'),
            '{{installment_due_date}}'   => now()->addDays(15)->format('M j, Y'),
            '{{payment_amount}}'         => '$725.00',
            '{{payment_date}}'           => now()->format('M j, Y · g:i A'),
            '{{payment_method}}'         => 'CARD',
            '{{transaction_id}}'         => 'pi_3SAMPLE0XYZ123abcdef',
            '{{outstanding_balance}}'    => '$725.00 remaining',
            '{{cancel_date}}'            => now()->format('M j, Y'),
        ];
        // Map type slug → BasicSetting key prefix. Most types follow the
        // simple "single hyphen → underscore" rule (e.g. awaiting-payment →
        // awaiting_payment_default_*). The two rental-agreement variants are
        // exceptions because their setting names don't follow that pattern.
        $key = match ($type) {
            'rental-agreement-request'      => 'rental_agreement_request',
            'rental-agreement-signed'       => 'rental_agreement_signed',
            'rental-agreement-signed-admin' => 'admin_alert_rental_agreement',
            default                         => str_replace('-', '_', $type),
        };
        $subjectTpl = (string) (ModelHelper::getDataFromSetting($key . '_default_subject')
                            ?: ModelHelper::getDataFromSetting($key . '_subject')
                            ?: ucfirst($type) . ' email');
        $bodyTpl    = (string) (ModelHelper::getDataFromSetting($key . '_default_body_html')
                            ?: ModelHelper::getDataFromSetting($key . '_body_html')
                            ?: '<p>(no body template)</p>');
        $subject = $resolver->expandMergeFields($subjectTpl, $extras);
        $body = in_array($type, ['awaiting-payment', 'confirmed', 'receipt', 'cancellation'], true)
            ? $resolver->expandMergeFieldsWithQuote($bodyTpl, $extras)
            : $resolver->expandMergeFields($bodyTpl, $extras);

        $shellHtml = view('mail.email-shell', [
            'body_html_rendered' => $body,
            'brand'              => $brand,
            'mail_footer'        => $footer,
        ])->render();

        $banner = '<div style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#fef3c7;color:#78350f;border-bottom:1px solid #fcd34d;padding:14px 24px;font-size:13px;">'
            . '<strong>👁️ Template preview</strong> — sample data, not a real booking. '
            . '<strong>Subject:</strong> ' . e($subject)
            . '</div>';
        return response($banner . $shellHtml, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Phase 13L → Phase 13M — show the EXACT email that was sent to the client.
     * Reads from the email_send_log snapshot store rather than re-rendering live
     * (which would drift if the template was edited after the original send).
     * If no snapshot exists for this (booking, type) the page tells the admin so
     * and points them at the Send-now button in the modal.
     *
     * Route: GET admin/booking-enquiries/{id}/email-preview/{type}
     */
    public function previewEmail($id, $type)
    {
        $booking  = BookingRequest::find($id);
        if (!$booking)  abort(404, 'Booking not found');

        $snapshot = \App\Services\Email\SendLog::latestFor((int)$id, $type);
        if (!$snapshot) {
            $banner = '<div style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#fef3c7;color:#78350f;border-bottom:1px solid #fcd34d;padding:24px;font-size:14px;line-height:1.5;">'
                . '<h2 style="margin:0 0 8px 0;font-size:18px;font-weight:700;">📭 No <code>' . e($type) . '</code> email has been sent for booking #' . e((string)$booking->id) . ' yet</h2>'
                . '<p style="margin:0 0 6px 0;">Once you fire the email (via the <strong>Send now</strong> button in the booking-detail modal, or once the cron picks it up), the rendered copy will be stored here and you\'ll be able to see exactly what went to <strong>' . e((string)$booking->email) . '</strong>.</p>'
                . '<p style="margin:6px 0 0 0;"><a href="' . url('admin/booking-enquiries') . '" style="color:#1e293b;font-weight:600;">← Back to booking enquiries</a></p>'
                . '</div>';
            return response($banner, 200)->header('Content-Type', 'text/html; charset=utf-8');
        }

        // We have a snapshot — wrap it in an admin banner showing the send time + recipient.
        $sentAt = $snapshot->sent_at ?: $snapshot->created_at;
        $sentLabel = $snapshot->success
            ? '✅ Sent successfully on ' . \Carbon\Carbon::parse($sentAt)->format('F j, Y \a\t g:i A') . ' (' . \Carbon\Carbon::parse($sentAt)->diffForHumans() . ')'
            : '❌ Send FAILED on ' . \Carbon\Carbon::parse($snapshot->created_at)->format('F j, Y \a\t g:i A');

        $errorBlock = '';
        if (!$snapshot->success && $snapshot->error) {
            $errorBlock = '<div style="margin-top:6px;padding:8px;background:#fee2e2;color:#b42318;border-radius:6px;font-family:ui-monospace,Menlo,monospace;font-size:12px;">' . e($snapshot->error) . '</div>';
        }

        $banner = '<div style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#eef2f7;color:#1e293b;border-bottom:1px solid #cbd5e1;padding:14px 24px;font-size:13px;line-height:1.5;">'
            . '<strong>📧 ' . e(ucfirst(str_replace('-', ' ', $type))) . ' email</strong> — booking #' . e((string)$booking->id) . ' (' . e((string)$booking->name) . ')<br>'
            . '<strong>To:</strong> ' . e($snapshot->recipient ?: '?') . ' &nbsp; <strong>Subject:</strong> ' . e($snapshot->subject ?: '(no subject)') . '<br>'
            . $sentLabel
            . $errorBlock
            . '</div>';

        return response($banner . $snapshot->rendered_html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Phase 13L original previewEmail body (live render). Kept temporarily as a
     * private fallback in case future code wants to "preview before sending"
     * without persisting. Currently unreferenced.
     */
    private function previewEmailLiveRender_unused($id, $type)
    {
        $booking  = BookingRequest::find($id);
        if (!$booking)  abort(404, 'Booking not found');
        $property = Property::find($booking->property_id) ?? Property::where('pms_room_id', $booking->property_id)->first();
        if (!$property) abort(404, 'Property not found');

        $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
        $brand    = ModelHelper::getDataFromSetting('mailer_admin_name')
                 ?: ModelHelper::getDataFromSetting('mail_from_name')
                 ?: ModelHelper::getDataFromSetting('owner_name')
                 ?: 'Notification';
        $footer   = ModelHelper::getDataFromSetting('mail_footer') ?: '';
        $currency = ModelHelper::getDataFromSetting('payment_currency') ?: '$';

        // Per-template subject + body resolution, mirroring the actual send paths.
        $subject = '';
        $body    = '';
        $extras  = [];

        switch ($type) {
            case 'enquiry':
                $subject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('enquiry_default_subject') ?: 'We received your enquiry about {{property_name}}'));
                $body    = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('enquiry_default_body_html') ?: ''));
                break;

            case 'awaiting-payment':
                $extras = ['{{magic_link}}' => '#preview-magic-link-not-real'];
                $subject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('awaiting_payment_default_subject') ?: ''), $extras);
                $body    = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('awaiting_payment_default_body_html') ?: ''), $extras);
                break;

            case 'confirmed':
                $subject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('confirmed_default_subject') ?: ''));
                $body    = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('confirmed_default_body_html') ?: ''));
                break;

            case 'cancellation':
                $subject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('cancellation_default_subject') ?: ''));
                $body    = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('cancellation_default_body_html') ?: ''));
                break;

            case 'welcome':
                // Welcome uses the structured Blade — render it the same way the Mailable does.
                $payload = $resolver->resolveWelcomePayload();
                $subject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('welcome_default_subject') ?: 'Welcome to {{property_name}}'));
                $body    = view('mail.welcome-package-customer', [
                    'booking' => $booking, 'data' => $booking, 'property' => $property,
                    'descriptionHtml' => $payload['description_html'],
                ])->render();
                return $this->wrapPreview($subject, $body, $brand, $footer, $type, $booking);

            case 'checkin':
                $payload = $resolver->resolveCheckinPayload();
                $subject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('checkin_default_subject') ?: 'Check-in details for {{property_name}}'));
                $body    = view('mail.booking-checkin-info', [
                    'booking' => $booking, 'data' => $booking, 'property' => $property, 'p' => $payload,
                ])->render();
                return $this->wrapPreview($subject, $body, $brand, $footer, $type, $booking);

            case 'checkout':
                $payload = $resolver->resolveCheckoutPayload();
                $subject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('checkout_default_subject') ?: 'Check-out details for {{property_name}}'));
                $body    = view('mail.booking-checkout-info', [
                    'booking' => $booking, 'data' => $booking, 'property' => $property, 'p' => $payload,
                ])->render();
                return $this->wrapPreview($subject, $body, $brand, $footer, $type, $booking);

            case 'review':
                $extras  = ['{{review_link}}' => '#preview-review-link-not-real'];
                $subject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('review_default_subject') ?: ''), $extras);
                $body    = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('review_default_body_html') ?: ''), $extras);
                break;

            case 'installment2':
            case 'installment3':
                $num = $type === 'installment2' ? '2' : '3';
                $amountCol = $type === 'installment2' ? 'second_payment_amount' : 'third_payment_amount';
                $extras = [
                    '{{magic_link}}'           => '#preview-magic-link-not-real',
                    '{{installment_number}}'   => $num,
                    '{{installment_amount}}'   => $currency . number_format((float)($booking->{$amountCol} ?? 0), 2),
                    '{{installment_due_date}}' => date('F j, Y', strtotime((string)($booking->checkin ?? 'now'))),
                ];
                $key = $type === 'installment2' ? 'installment2' : 'installment3';
                $subject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting($key . '_default_subject') ?: ''), $extras);
                $body    = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting($key . '_default_body_html') ?: ''), $extras);
                break;

            default:
                abort(404, 'Unknown email type: ' . $type);
        }

        return $this->wrapPreview($subject, $body, $brand, $footer, $type, $booking);
    }

    /**
     * Wrap the rendered email body in the same email-shell + add a small admin
     * preview header at the top (so admins know they're looking at a preview, not
     * a real send) before returning as HTML.
     */
    private function wrapPreview(string $subject, string $body, string $brand, string $footer, string $type, BookingRequest $booking)
    {
        $shellHtml = view('mail.email-shell', [
            'body_html_rendered' => $body,
            'brand'              => $brand,
            'mail_footer'        => $footer,
        ])->render();

        $banner = '<div style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#fef3c7;color:#78350f;border-bottom:1px solid #fcd34d;padding:14px 24px;font-size:14px;">'
            . '<strong>👁️ Email preview</strong> — this is how the <code>' . e($type) . '</code> email would render for booking #' . e((string)$booking->id) . ' (' . e((string)$booking->name) . ')'
            . ' as of right now (using the current saved templates + the latest booking data). '
            . '<strong>Subject:</strong> ' . e($subject) . '. '
            . 'Magic-link / review-link tokens show <code>#preview-...</code> placeholders since this is not a real send. '
            . 'Close this tab to return to the booking modal.'
            . '</div>';

        return response($banner . $shellHtml, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    function destroyData($id){
        $exist=$this->model::find($id);
        if($exist){
            $data=BookingRequest::find($id)->toArray();
            if($data){
                $property=Property::find($data['property_id']);
                if($property){
                    LiveCart::getFileIcalFileData($data['property_id']);
                    $exist->delete();
                }
            }
            return redirect()->route($this->admin_base_url)->with("success","Successfully Deleted");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    /**
     * Admin "Decline & notify guest" — flips a pending-approval request to
     * declined and emails the guest a courteous decline note. Subject + body
     * are admin-editable on the Email Templates page (Booking lifecycle tab
     * → Decline) via the standard `*_default_*` BasicSetting keys; sensible
     * fallbacks ship inline so the action works the moment it's deployed.
     */
    function decline(Request $request, $id) {
        $exist = $this->model::find($id);
        if (!$exist) {
            return redirect()->route($this->admin_base_url)->with('danger', 'Invalid Calling');
        }
        $property = Property::find($exist->property_id) ?? Property::where('pms_room_id', $exist->property_id)->first();
        if (!$property) {
            return redirect()->route($this->admin_base_url)->with('danger', 'Invalid Calling');
        }

        $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $exist);
        $brand    = ModelHelper::getDataFromSetting('mailer_admin_name')
                 ?: ModelHelper::getDataFromSetting('mail_from_name')
                 ?: ModelHelper::getDataFromSetting('owner_name')
                 ?: 'Notification';
        $footer   = ModelHelper::getDataFromSetting('mail_footer') ?: '';

        $subject = $resolver->expandMergeFields((string) (
            ModelHelper::getDataFromSetting('decline_default_subject')
            ?: 'Update on your booking request — {{property_name}}'
        ));
        $body = $resolver->expandMergeFields((string) (
            ModelHelper::getDataFromSetting('decline_default_body_html')
            ?: '<p>Hi {{guest_name}},</p>'
             . '<p>Thank you for your interest in <strong>{{property_name}}</strong>. Unfortunately we\'re unable to accept your request for these dates.</p>'
             . '<p>We\'d love to host you another time — feel free to browse other properties or different dates on our site.</p>'
             . '<p>Warm regards,<br/>{{brand}}</p>'
        ));
        $customerHtml = view('mail.email-shell', ['body_html_rendered' => $body, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $exist, 'property' => $property])->render();

        // QA 2026-06-11 (taxonomy B/F): set the status FIRST — previously an
        // unconfigured/failing mailer threw before save(), so "Decline" looked
        // pressed but the booking stayed pending. The state change is the
        // action; the email is best-effort.
        $exist->booking_status = 'declined';
        $exist->save();

        $mailOk = true;
        try {
            MailHelper::emailSenderByController($customerHtml, $exist->email, $subject);
            \App\Services\Email\SendLog::record((int)$exist->id, 'declined', (string)$exist->email, $subject, $customerHtml);
        } catch (\Throwable $e) {
            $mailOk = false;
            \Log::warning('Decline email failed (booking still declined)', ['booking_id' => $exist->id, 'error' => $e->getMessage()]);
        }

        return redirect()->route($this->admin_base_url)->with(
            $mailOk ? 'success' : 'warning',
            $mailOk ? 'Request declined and the guest has been notified.'
                    : 'Request declined, but the guest email could NOT be sent (check Mail settings).');
    }

    function confirmed($id){
        $exist=$this->model::find($id);
        if(!$exist){
            return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
        }
        $property=Property::find($exist->property_id) ?? Property::where('pms_room_id', $exist->property_id)->first();
        if(!$property){
            return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
        }

        $magic_link = \URL::temporarySignedRoute('booking.pay-link', now()->addDays(7), ['id' => $exist->id]);

        // Phase 13D — awaiting-payment email is admin-editable on the Guest Emails page
        // (Booking lifecycle tab → Awaiting payment). The {{magic_link}} token is supplied per-call.
        $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $exist);
        $brand    = ModelHelper::getDataFromSetting('mailer_admin_name')
                 ?: ModelHelper::getDataFromSetting('mail_from_name')
                 ?: ModelHelper::getDataFromSetting('owner_name')
                 ?: 'Notification';
        $footer   = ModelHelper::getDataFromSetting('mail_footer') ?: '';

        $extras  = ['{{magic_link}}' => $magic_link];
        $subject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('awaiting_payment_default_subject') ?: 'Complete your booking at {{property_name}}'), $extras);
        $body    = $resolver->expandMergeFieldsWithQuote((string) (ModelHelper::getDataFromSetting('awaiting_payment_default_body_html') ?: '<p>Hi {{guest_name}},</p><p>Please complete your payment using the secure link below.</p>'), $extras);
        $customerHtml = view('mail.email-shell', ['body_html_rendered' => $body, 'brand' => $brand, 'mail_footer' => $footer])->render();

        // QA 2026-06-11 (taxonomy B/F): accept FIRST, then best-effort mail —
        // previously a failing mailer threw before save(), leaving the booking
        // un-accepted while the admin believed it was approved.
        $exist->booking_status='booking-accepted';
        $exist->save();

        $mailOk = true;
        try {
            MailHelper::emailSenderByController($customerHtml, $exist->email, $subject, $resolver->attachmentsFor('awaiting-payment'));
            \App\Services\Email\SendLog::record((int)$exist->id, 'awaiting-payment', (string)$exist->email, $subject, $customerHtml);

            // Admin alert — Phase 13E editable on Email Templates → Owner alerts → Awaiting-payment.
            $adminTo = ModelHelper::getDataFromSetting('booking_receiving_mail')
                    ?: ModelHelper::getDataFromSetting('admin_email_primary');
            if ($adminTo) {
                $adminSubject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('admin_alert_awaiting_payment_subject') ?: 'Payment link sent — booking #{{booking_id}} ({{property_name}})'), $extras);
                $adminBody    = $resolver->expandMergeFieldsWithQuote((string) (ModelHelper::getDataFromSetting('admin_alert_awaiting_payment_body_html') ?: '<p>Magic link sent for booking #{{booking_id}}.</p><p><a href="{{admin_url}}">View →</a></p>'), $extras);
                $adminHtml    = view('mail.email-shell', ['body_html_rendered' => $adminBody, 'brand' => $brand, 'mail_footer' => $footer])->render();
                MailHelper::emailSenderByController($adminHtml, $adminTo, $adminSubject);
            }
        } catch (\Throwable $e) {
            $mailOk = false;
            \Log::warning('Awaiting-payment email failed (booking still accepted)', ['booking_id' => $exist->id, 'error' => $e->getMessage()]);
        }

        return redirect()->route($this->admin_base_url)->with(
            $mailOk ? 'success' : 'warning',
            $mailOk ? 'Booking accepted — payment link sent to the guest.'
                    : 'Booking accepted, but the payment-link email could NOT be sent (check Mail settings). The magic link is still valid.');
    }

    public function downloadAgreement($id)
    {
        $booking = BookingRequest::findOrFail($id);
        abort_unless($booking->rental_agreement_pdf_path, 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download(
            $booking->rental_agreement_pdf_path,
            sprintf('agreement-booking-%d.pdf', $id),
            ['Content-Type' => 'application/pdf']
        );
    }
}

<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookingRequest;
use App\Models\Pms\PmsProperty;
use App\Helper\Upload;
use Validator;
use MailHelper;
use LiveCart;
use LodgifyApi;
use ModelHelper;
use Stripe;
use App\Models\Pms\PmsRoom;
use App\Modules\VacationRental\Pms\PmsManager;
use App\Models\Payment;
use App\Models\ScheduledPayment;
use App\Helper\StripeHelper;
use App\Services\PmsRefundSync;

class PmsBookingController extends Controller{
    protected BookingRequest $model;
    protected string $admin_base_url;
    protected string $admin_view;
    
    public function __construct(BookingRequest $model){
        $this->model=$model;
        $this->admin_base_url="admin.pms-bookings.index";
        $this->admin_view="admin.pms_bookings";
    }
    public function getCheckinCheckoutDataGaurav(Request $request){
        $new_data_blocked=LiveCart::iCalDataCheckInCheckOut($request->id);
        $checkin=$new_data_blocked['checkin'];
        $checkout=$new_data_blocked['checkout'];
        return response()->json($new_data_blocked);
    }
    
    public function index(){
        $data=$this->model::orderBy("id","desc")->get();
        return view($this->admin_view.".index",compact("data"));
    }
    
    public function singlePropertyBookoing($id){
        $property=PmsProperty::find($id);
        if($property){
             $data=$this->model::where("property_id",$id)->orderBy("id","desc")->get();
            return view($this->admin_view.".show",compact("data"));
        }   
        return abort(404);
    }
    
    public function create(){
        return redirect()->route($this->admin_base_url);
        return view($this->admin_view.".create");
    }

    public function store(Request $request){
        return redirect()->route($this->admin_base_url);
        $validator = Validator::make($request->all(), [ ]);   
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $data=$request->all();
        if ($request->hasFile("image")) {$data['image'] = Upload::fileUpload($request->file("image"),"booking-enquiries");}
        if ($request->hasFile("bannerImage")) {$data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"),"booking-enquiries");}
        if($request->booking_type_admin){
            if($request->booking_type_admin!="invoice"){
                $data['booking_status']="booking-confirmed";
            }
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
        return redirect()->route($this->admin_base_url);
        $data=$this->model::find($id);
        if($data){
            return view($this->admin_view.".edit",compact("data"));
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }
  
    public function update(Request $request, $id){
        $validator = Validator::make($request->all(), []);
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $exist=$this->model::find($id);
        if($exist){
            $data=$request->all();
            if ($request->hasFile("image")) {$data['image'] = Upload::fileUpload($request->file("image"),"booking-enquiries");}
            if ($request->hasFile("bannerImage")) {$data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"),"booking-enquiries");}
            $property_id=$exist->property_id;
            if($request->booking_type_admin){
                if($request->booking_type_admin!="invoice"){
                    $data['booking_status']="booking-confirmed";
                }
            }

            // ── Push payment to Hostaway when admin marks a booking as paid ──
            // When payment is collected through the website and the admin changes
            // payment_status to "paid", we record it in Hostaway so the ledger stays
            // in sync and the reservation shows as paid in the Hostaway dashboard.
            $newPaymentStatus = $request->input('payment_status');
            $wasNotPaid = !in_array($exist->payment_status, ['paid', 'completed']);
            if ($newPaymentStatus === 'paid' && $wasNotPaid && !empty($exist->pms_booking_id)) {
                $room = PmsRoom::find($exist->property_id);
                if ($room && $room->pms_source === 'hostaway') {
                    try {
                        $adapter  = app(PmsManager::class)->driver('hostaway');
                        $amount   = (float)($exist->total_amount ?? $exist->gross_amount ?? 0);
                        $currency = $exist->currency ?? ModelHelper::getDataFromSetting('payment_currency') ?? 'USD';
                        $result   = $adapter->recordPayment($exist->pms_booking_id, $amount, $currency);
                        \Log::channel('lodgify')->info('Admin: Hostaway payment recorded', [
                            'booking_id'    => $id,
                            'pms_booking_id'=> $exist->pms_booking_id,
                            'amount'        => $amount,
                            'result'        => $result,
                        ]);
                    } catch (\Throwable $e) {
                        \Log::channel('lodgify')->error('Admin: Hostaway payment record failed', [
                            'booking_id' => $id,
                            'error'      => $e->getMessage(),
                        ]);
                        // Non-fatal — continue with the local update
                    }
                }
            }

            $this->model::find($id)->update($data);
            return redirect()->route('singlePropertyBookoing',$property_id)->with("success","Successfully Updated");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    public function destroy($id){
        $exist=$this->model::find($id);
        if($exist){
            // ── 1. Cancel in the PMS ──────────────────────────────────────────
            $room = PmsRoom::find($exist->property_id);
            if (!empty($exist->pms_booking_id) && $room && !empty($room->pms_source)) {
                try {
                    $adapter = app(PmsManager::class)->driver($room->pms_source);
                    $result  = $adapter->cancelReservation(['reservation_id' => $exist->pms_booking_id]);
                    \Log::channel('lodgify')->info('Admin: PMS reservation cancelled', [
                        'booking_id' => $id,
                        'pms'        => $room->pms_source,
                        'result'     => $result,
                    ]);
                } catch (\Throwable $e) {
                    \Log::channel('lodgify')->error('Admin: PMS cancel failed', [
                        'booking_id' => $id,
                        'pms'        => $room->pms_source ?? 'unknown',
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            // ── 2. Cancel all pending scheduled installments ──────────────────
            // Prevents the daily cron from charging the guest after cancellation.
            $cancelledInstallments = ScheduledPayment::where('booking_request_id', $id)
                ->where('status', 'pending')
                ->count();
            ScheduledPayment::where('booking_request_id', $id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
            if ($cancelledInstallments > 0) {
                \Log::channel('lodgify')->info('Admin: cancelled pending installments on booking cancel', [
                    'booking_id' => $id,
                    'count'      => $cancelledInstallments,
                ]);
            }

            // ── 3. Release Stripe security deposit pre-auth if still held ─────
            // Automatically voids the hold so the guest's card is freed immediately.
            // If the deposit was already captured (damage claim) or released, skip.
            if (!empty($exist->stripe_deposit_charge_id) && $exist->security_deposit_status === 'authorized') {
                try {
                    Stripe\Stripe::setApiKey(ModelHelper::getDataFromSetting('stripe_secret_key'));
                    StripeHelper::releaseDepositHold($exist->stripe_deposit_charge_id);
                    $exist->security_deposit_status = 'released';
                    \Log::channel('lodgify')->info('Admin: deposit auto-released on cancel', [
                        'booking_id' => $id,
                        'charge_id'  => $exist->stripe_deposit_charge_id,
                    ]);
                } catch (\Throwable $e) {
                    \Log::channel('lodgify')->error('Admin: deposit auto-release failed on cancel', [
                        'booking_id' => $id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            // ── 4. Mark booking cancelled ─────────────────────────────────────
            $exist->booking_status = 'booking-cancel';
            $exist->save();

            // ── 5. Send cancellation emails to guest and admin ────────────────
            try {
                $property = $room ?? PmsProperty::find($exist->property_id);
                if ($property) {
                    app(\App\Services\Email\BookingLifecycleMailer::class)
                        ->sendCancellation($exist->fresh(), $property);
                }
            } catch (\Throwable $e) {
                \Log::channel('lodgify')->error('Admin: cancellation email failed', [
                    'booking_id' => $id,
                    'error'      => $e->getMessage(),
                ]);
            }

            // ── 6. Remove blocked dates immediately so calendar shows freed dates ──
            // Without this, pms_unavailable_dates keeps the nights blocked until the
            // next hourly sync, and the availability cache keeps serving stale data.
            if ($room) {
                try {
                    $pmsProperty = \App\Models\Pms\PmsProperty::where('pms_property_id', $room->pms_property_id)
                        ->where('pms_source', $room->pms_source)
                        ->first();

                    if ($pmsProperty && $exist->checkin && $exist->checkout) {
                        $cur = new \DateTime($exist->checkin);
                        $end = new \DateTime($exist->checkout);
                        $dates = [];
                        while ($cur < $end) {
                            $dates[] = $cur->format('Y-m-d');
                            $cur->modify('+1 day');
                        }
                        if (!empty($dates)) {
                            \App\Models\Pms\PmsUnavailableDate::where('property_id', $pmsProperty->id)
                                ->whereIn('un_date', $dates)
                                ->delete();

                            // Also free dates in pms_calendar (set state=1 available)
                            \Illuminate\Support\Facades\DB::table('pms_calendar')
                                ->where('pms_source', $room->pms_source)
                                ->where('pms_room_id', $room->pms_room_id)
                                ->whereIn('date', $dates)
                                ->update(['state' => 1, 'synced_at' => now()]);
                        }
                    }

                    // Bust the availability cache so the frontend sees freed dates immediately
                    $vKey = "pms_avail_v_{$room->pms_source}_{$room->pms_property_id}";
                    \Illuminate\Support\Facades\Cache::put($vKey,
                        (\Illuminate\Support\Facades\Cache::get($vKey, 0) + 1), 86400);

                    \Log::channel('lodgify')->info('Admin: cancelled booking dates freed from calendar', [
                        'booking_id' => $id,
                        'checkin'    => $exist->checkin,
                        'checkout'   => $exist->checkout,
                    ]);
                } catch (\Throwable $e) {
                    \Log::channel('lodgify')->error('Admin: failed to free cancelled dates from calendar', [
                        'booking_id' => $id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            // ── 7. Refresh iCal ───────────────────────────────────────────────
            $property_id = $exist->property_id;
            if ($room) {
                LiveCart::getFileIcalFileData($property_id);
            }

            return redirect()->route($this->admin_base_url)->with("success", "Booking cancelled successfully.");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    function confirmed($id){
        return redirect()->route($this->admin_base_url);
        $exist=$this->model::find($id);
        if($exist){
            $property_id=$exist->property_id;
            $property=Property::find($exist->property_id);
            if($property){
                $data=$exist->toArray();
                $html=view("mail.pms-booking-confirmation-user-email",compact("data","property"))->render();
                $to=$exist->email;
                $customer_subject="Payment Request for ".$property->name;
                MailHelper::emailSenderByController($html,$to,$customer_subject);
                $exist->booking_status='rental-aggrement';
                $exist->save();
                return redirect()->route('singlePropertyBookoing',$property_id)->with("success","Successfully send");
            }
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    /**
     * Issue a full or partial Stripe refund for a booking's payment.
     * Accepts optional `refund_amount` in the request body; defaults to the full charge.
     * Only works for Stripe payments (type = 'stripe').
     */
    public function refundPayment(Request $request, $id)
    {
        $booking = BookingRequest::find($id);
        if (!$booking) {
            return redirect()->back()->with('danger', 'Booking not found.');
        }

        $payment = Payment::where('booking_id', $id)->where('type', 'stripe')->latest()->first();
        if (!$payment || empty($payment->tran_id)) {
            return redirect()->back()->with('danger', 'No Stripe payment found for this booking.');
        }

        if ($payment->status === 'refunded') {
            return redirect()->back()->with('danger', 'This payment has already been refunded.');
        }

        // Determine refund amount (cents); default = full charge
        $requestedAmount = $request->input('refund_amount');
        $refundCents     = $requestedAmount
            ? (int) round((float) $requestedAmount * 100)
            : (int) round((float) $payment->amount * 100);

        if ($refundCents <= 0 || $refundCents > (int) round((float) $payment->amount * 100)) {
            return redirect()->back()->with('danger', 'Invalid refund amount.');
        }

        try {
            Stripe\Stripe::setApiKey(ModelHelper::getDataFromSetting('stripe_secret_key'));

            $params = ['charge' => $payment->tran_id];
            // Partial refund only if less than the full amount
            if ($refundCents < (int) round((float) $payment->amount * 100)) {
                $params['amount'] = $refundCents;
            }

            $refund = Stripe\Refund::create($params);

            $isFullRefund = ($refundCents >= (int) round((float) $payment->amount * 100));
            $payment->update([
                'status'      => $isFullRefund ? 'refunded' : 'partially-refunded',
                'description' => json_encode(array_merge(
                    json_decode($payment->description, true) ?? [],
                    ['refund_id' => $refund->id, 'refund_amount' => $refundCents / 100, 'refunded_at' => now()->toDateTimeString()]
                )),
            ]);

            $booking->update([
                'payment_status' => $isFullRefund ? 'declined' : $booking->payment_status,
            ]);

            \Log::channel('lodgify')->info('Admin: Stripe refund issued', [
                'booking_id'  => $id,
                'refund_id'   => $refund->id,
                'amount'      => $refundCents / 100,
                'full_refund' => $isFullRefund,
            ]);

            // ── Notify PMS of refund (best-effort, non-blocking) ──────────────
            // Stripe refund succeeded — now record it in the PMS ledger only when
            // this booking actually has a PMS reservation. Direct website bookings
            // should not be marked as "PMS refund pending manual".
            if (!empty($booking->pms_source) && !empty($booking->pms_booking_id)) {
                $this->notifyPmsRefund($booking, $payment, $refund->id, $payment->tran_id, $refundCents / 100);
            }

            $formatted = number_format($refundCents / 100, 2);
            return redirect()->back()->with('success', "Refund of \${$formatted} issued successfully (Refund ID: {$refund->id}).");

        } catch (\Throwable $e) {
            \Log::channel('lodgify')->error('Admin: Stripe refund failed', [
                'booking_id' => $id,
                'error'      => $e->getMessage(),
            ]);
            return redirect()->back()->with('danger', 'Refund failed: ' . $e->getMessage());
        }
    }

    /**
     * Record a Stripe refund in the PMS ledger after Stripe confirms success.
     *
     * Delegates to PmsRefundSync which:
     *   - Creates/finds a PaymentRefund row per stripe_refund_id (handles partial refunds)
     *   - Acquires an optimistic lock (CAS pending→syncing) to block concurrent runs
     *   - Uses Hostfully's pms_payment_id from the Payment row as referenceTransactionId
     *   - Retries up to PmsRefundSync::MAX_RETRIES, then sends an admin exhaustion alert
     */
    private function notifyPmsRefund(
        BookingRequest $booking,
        Payment        $paymentRecord,
        string         $stripeRefundId,
        string         $stripeChargeId,
        float          $refundAmount
    ): void {
        app(PmsRefundSync::class)->syncRefund(
            $paymentRecord,
            $booking,
            $stripeRefundId,
            $refundAmount,
            $stripeChargeId
        );
    }

    /**
     * Capture (charge) the pre-authorized security deposit.
     * Call this when you want to forfeit the guest's deposit (e.g. after damages).
     */
    public function captureDeposit(Request $request, $id)
    {
        $booking = BookingRequest::find($id);
        if (!$booking || empty($booking->stripe_deposit_charge_id)) {
            return redirect()->back()->with("danger", "No deposit authorization found for this booking.");
        }
        if ($booking->security_deposit_status !== 'authorized') {
            return redirect()->back()->with("danger", "Deposit is not in an authorized state (current: {$booking->security_deposit_status}).");
        }

        try {
            Stripe\Stripe::setApiKey(ModelHelper::getDataFromSetting('stripe_secret_key'));
            StripeHelper::captureDepositHold($booking->stripe_deposit_charge_id);

            $booking->update(['security_deposit_status' => 'captured']);

            \Log::channel('lodgify')->info('Admin: security deposit captured', [
                'booking_id' => $id,
                'charge_id'  => $booking->stripe_deposit_charge_id,
                'amount'     => $booking->security_deposit_amount,
            ]);

            return redirect()->back()->with("success", "Security deposit of {$booking->security_deposit_currency} {$booking->security_deposit_amount} captured successfully.");

        } catch (\Throwable $e) {
            \Log::channel('lodgify')->error('Admin: deposit capture failed', [
                'booking_id' => $id,
                'error'      => $e->getMessage(),
            ]);
            return redirect()->back()->with("danger", "Deposit capture failed: " . $e->getMessage());
        }
    }

    /**
     * Release (void) the pre-authorized security deposit.
     * Call this when you want to return the hold to the guest (no damages).
     */
    public function releaseDeposit(Request $request, $id)
    {
        $booking = BookingRequest::find($id);
        if (!$booking || empty($booking->stripe_deposit_charge_id)) {
            return redirect()->back()->with("danger", "No deposit authorization found for this booking.");
        }
        if ($booking->security_deposit_status !== 'authorized') {
            return redirect()->back()->with("danger", "Deposit is not in an authorized state (current: {$booking->security_deposit_status}).");
        }

        try {
            Stripe\Stripe::setApiKey(ModelHelper::getDataFromSetting('stripe_secret_key'));
            StripeHelper::releaseDepositHold($booking->stripe_deposit_charge_id);

            $booking->update(['security_deposit_status' => 'released']);

            \Log::channel('lodgify')->info('Admin: security deposit released', [
                'booking_id' => $id,
                'charge_id'  => $booking->stripe_deposit_charge_id,
                'amount'     => $booking->security_deposit_amount,
            ]);

            return redirect()->back()->with("success", "Security deposit hold released. The guest's card will not be charged.");

        } catch (\Throwable $e) {
            \Log::channel('lodgify')->error('Admin: deposit release failed', [
                'booking_id' => $id,
                'error'      => $e->getMessage(),
            ]);
            return redirect()->back()->with("danger", "Deposit release failed: " . $e->getMessage());
        }
    }

    /**
     * Retry creating a PMS reservation for a booking where payment was captured
     * but the PMS call failed (status = payment-captured-pms-failed).
     */
    public function retryPms(Request $request, $id)
    {
        $booking = BookingRequest::find($id);
        if (!$booking) {
            return redirect()->back()->with('danger', 'Booking not found.');
        }

        if ($booking->booking_status !== 'payment-captured-pms-failed') {
            return redirect()->back()->with('danger', 'Booking is not in payment-captured-pms-failed status.');
        }

        $property = PmsRoom::find($booking->property_id);
        if (!$property || empty($property->pms_source)) {
            return redirect()->back()->with('danger', 'PMS property not found for this booking.');
        }

        try {
            $bookingService = app(\App\Services\PmsBookingService::class);
            $result         = $bookingService->create($booking->fresh());

            if ($result['ok'] ?? false) {
                $booking->update(['booking_status' => 'booking-confirmed']);

                \Log::channel('lodgify')->info('Admin: PMS retry succeeded', [
                    'booking_id'     => $id,
                    'pms'            => $property->pms_source,
                    'pms_booking_id' => $result['pms_booking_id'] ?? null,
                ]);

                return redirect()->back()->with('success', 'PMS reservation created successfully. Booking is now confirmed.');
            }

            \Log::channel('lodgify')->error('Admin: PMS retry failed', [
                'booking_id' => $id,
                'error'      => $result['error'] ?? 'unknown',
            ]);

            return redirect()->back()->with('danger', 'PMS retry failed: ' . ($result['error'] ?? 'Unknown error. Check logs.'));

        } catch (\Throwable $e) {
            \Log::channel('lodgify')->error('Admin: PMS retry exception', [
                'booking_id' => $id,
                'error'      => $e->getMessage(),
            ]);
            return redirect()->back()->with('danger', 'PMS retry error: ' . $e->getMessage());
        }
    }
}

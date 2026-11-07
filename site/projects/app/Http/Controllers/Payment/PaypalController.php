<?php
namespace App\Http\Controllers\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookingRequest;
use App\Models\Property;
use App\Models\Payment;
use Session;
use ModelHelper;
use Helper;
use MailHelper;

class PaypalController extends Controller
{
    function index(Request $request,$id){
        // Phase 4 (2026-05-07): the legacy `which_payment_gateway` global
        // setting is dead code now — owners pick their gateway combination
        // via the new admin payment-gateways page, and the guest's choice
        // already determined which payment route they're on. The old
        // redirects here would punt the guest from PayPal back to Stripe
        // (or wherever) producing an infinite loop with checkout. Likewise,
        // the operation-branch's `else` redirected the user back to this
        // same URL ("Rental Agreement already submitted") — also a loop.
        // Keep just the actual flow: handle check / venmo special-cases,
        // refuse if already paid / cancelled, then render PayPal.

        $booking = BookingRequest::find($id);
        if (!$booking) return abort(404);

        if ($booking->operation === 'check-booking') {
            BookingRequest::where('id', $id)->update(['booking_status' => 'booked']);
            return redirect()->to('check-payment/success/' . $booking->id);
        }
        if ($booking->operation === 'venmo-booking') {
            BookingRequest::where('id', $id)->update(['booking_status' => 'booked']);
            return redirect()->to('venmo-payment/success/' . $booking->id);
        }

        if ($booking->payment_status === 'paid') {
            return redirect('/')->with('success', 'Already paid for this booking');
        }
        if ($booking->booking_status === 'cancelled') {
            return redirect()->to('/')->with('danger', 'Booking Cancelled, Please contact Administrator');
        }

        $property = Property::find($booking->property_id);
        if (!$property) return abort(404);

        $data = new \stdClass();
        $data->name             = 'Payment Request';
        $data->meta_title       = 'Payment Request';
        $data->meta_keywords    = 'Payment Request';
        $data->meta_description = 'Payment Request';
        return view('front.booking.payment.paypal', compact('booking', 'data', 'property'));
    }

    function indexPost(Request $request,$id){
        // Detect AJAX/JSON callers (Plan D Task 10, spec D23). NOTE: PayPal's
        // route is GET-mapped (browser-redirected return-from-PayPal), so this
        // branch is rarely exercised in practice — PayPal's visitor-flow JS
        // dispatches via popup. Kept here for consistency with the other 3
        // gateways so callers can rely on a uniform JSON envelope.
        $wantsJson = $request->wantsJson() || $request->expectsJson();
        $errorCode = 'gateway_error';
        $booking=BookingRequest::find($id);
        if($booking){
            $property=Property::find($booking->property_id);
            if($property){
                try {
                    if($request->st == "COMPLETED" ){
                        // Idempotent insert keyed on PayPal transaction id (`tx`) — protects
                        // against duplicate Payment rows / double counter increments if
                        // the customer reloads the success URL (audit C-5 pattern).
                        $payment=Payment::firstOrCreate(
                            ['tran_id'=>$request->tx],
                            ['booking_id'=>$booking->id,'receipt_url'=>'' ,'customer_id'=>'' ,'amount'=>$request->amt,'description'=>json_encode($request->all()),'type'=>"paypal",'status'=>"complete"]
                        );
                        if($payment->wasRecentlyCreated){
                            ModelHelper::finalEmailAndUpdateBookingPayment($id,$booking,$payment,$property,"payment");
                        }
                        if($wantsJson){
                            return response()->json([
                                'success' => true,
                                'redirect_url' => url('payment/success/'.$payment->id),
                            ]);
                        }
                        return redirect('payment/success/'.$payment->id)->with("success","successfully Payment");
                    }else{
                        $message="something happen";
                        $errorCode = 'gateway_error';
                    }
                }  catch (Exception $e) {
                    $message =$e->getError()->message ;
                    $errorCode = 'gateway_error';
                }
           }else{
              $message="property is not longer";
              $errorCode = 'invalid_booking';
           }
        }else{
            $message="Booking is invalid";
            $errorCode = 'invalid_booking';
        }
        if($wantsJson){
            return response()->json([
                'success' => false,
                'error_code' => $errorCode,
                'error_message' => $message,
            ], 200);
        }
        return redirect()->back()->with("danger",$message);
    }
}
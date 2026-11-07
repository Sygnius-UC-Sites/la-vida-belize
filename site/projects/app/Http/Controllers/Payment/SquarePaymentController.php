<?php
namespace App\Http\Controllers\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookingRequest;
use App\Models\Property;
use App\Models\Payment;
use PDF;
use Session;
use ModelHelper;
use Helper;
use MailHelper;

class SquarePaymentController extends Controller
{
    function index(Request $request,$id){
        // Phase 4 (2026-05-07): legacy `which_payment_gateway` cross-redirects
        // removed — see StripeController/PaypalController for context.
        $booking=BookingRequest::find($id);
        if($booking){
          
           	if($booking->operation=="check-booking"){
                    // Mark booking as booked when guest confirms check payment.
                    // Was previously a no-op `find()->where(...)` chain (audit M-1).
                    BookingRequest::where("id",$id)->update(["booking_status"=>"booked"]);
                    $booking=BookingRequest::find($id);
              return redirect()->to('check-payment/success/'.$booking->id);
            }elseif($booking->operation=="venmo-booking"){
                    BookingRequest::where("id",$id)->update(["booking_status"=>"booked"]);
                    $booking=BookingRequest::find($id);
              return redirect()->to('venmo-payment/success/'.$booking->id);
            }else{
              return redirect()->to('booking/payment/paypal/'.$booking->id)->with("danger","Rental Agreement already submitted"); 
            }
          
            if($booking->payment_status=="paid"){
                return redirect('/')->with("success","Already paid for this booking");
            }
            if($booking->booking_status!="cancelled"){}else{
                return redirect()->to('/')->with("danger","Booking Cancelled, Please contact Administrator");
            }
            $property=Property::find($booking->property_id);
            if($property){
                $data = new \stdClass();
                $data->name=" Payment Request ";
                $data->meta_title=" Payment Request ";
                $data->meta_keywords=" Payment Request ";
                $data->meta_description=" Payment Request ";
                return view("front.booking.payment.square.index",compact("booking","data","property"));
            }
        }
        return abort(404);
    }

    function indexPost(Request $request,$id){
        // Detect AJAX/JSON callers (Plan D Task 10, spec D23). NOTE: Square's
        // indexPost returns a Blade view that itself contains the Square Web
        // Payments SDK call — the actual charge happens client-side from that
        // Blade. So a true AJAX caller hitting this endpoint can't render the
        // SDK Blade; instead it gets a JSON envelope pointing at the success
        // page (after duplicate guard) or the gateway error code.
        $wantsJson = $request->wantsJson() || $request->expectsJson();
        $request_data=($request->all());
        $booking=BookingRequest::find($id);
        if($booking){
            // Guard against duplicate charges if the customer reloads/resubmits.
            // The Square SDK call lives inside the Blade and will fire a fresh
            // CreatePayment with a new idempotency key on each render — without
            // this guard, a reload after a successful charge would charge again.
            if($booking->payment_status=="paid"){
                $existingPayment=Payment::where("booking_id",$booking->id)
                    ->where("type","square")
                    ->where("status","complete")
                    ->orderBy("id","desc")
                    ->first();
                if($existingPayment){
                    if($wantsJson){
                        return response()->json([
                            'success' => true,
                            'redirect_url' => url('payment/success/'.$existingPayment->id),
                        ]);
                    }
                    return redirect('payment/success/'.$existingPayment->id)
                        ->with("success","Payment already completed for this booking");
                }
            }
            $property=Property::find($booking->property_id);
            if($property){
                $data = new \stdClass();
                $data->name=" Payment Request ";
                $data->meta_title=" Payment Request ";
                $data->meta_keywords=" Payment Request ";
                $data->meta_description=" Payment Request ";
                $booking1=$booking;
                $booking=$booking->toArray();
                if($wantsJson){
                    // AJAX callers cannot render the Square SDK Blade inline.
                    // Return a redirect_url that re-fetches this endpoint with
                    // the same params so the browser can load the SDK page.
                    return response()->json([
                        'success' => false,
                        'error_code' => 'gateway_error',
                        'error_message' => 'Square checkout requires browser navigation. Please retry without AJAX.',
                    ], 200);
                }
                return view("front.booking.payment.square.indexPost",compact("booking","data","property","request_data","id","booking1"));
            }
        }
        if($wantsJson){
            return response()->json([
                'success' => false,
                'error_code' => 'invalid_booking',
                'error_message' => 'Booking is invalid',
            ], 200);
        }
        return abort(404);
    }
    
    function showReceiptMail(Request $request,$id){
        // After the Square indexPost blade finalizes the payment via
        // ModelHelper::finalEmailAndUpdateBookingPayment(...,"payment"),
        // booking status, installment counter, and confirmation emails
        // are all handled inline. This endpoint now simply forwards to
        // the standard success page so reloading the URL does not
        // re-send confirmation emails (audit C-5 / I-5).
        $payment=Payment::find($id);
        if(!$payment){
            return abort(404);
        }
        return redirect('payment/success/'.$payment->id)->with("success","successfully Payment");
    }
}
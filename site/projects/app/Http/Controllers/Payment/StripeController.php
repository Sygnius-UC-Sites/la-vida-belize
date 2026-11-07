<?php
namespace App\Http\Controllers\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookingRequest;
use App\Models\Property;
use App\Models\Payment;
use App\Services\Payments\DirectInstallmentScheduler;
use Stripe;
use Session;
use ModelHelper;
use MailHelper;
use Helper;

class StripeController extends Controller
{
    function index(Request $request,$id){
        // Phase 4 (2026-05-07): legacy `which_payment_gateway` cross-redirects
        // removed — they ran on every payment controller and produced
        // ERR_TOO_MANY_REDIRECTS once owners had a multi-gateway combo.
        // Guest's choice already determined which route they hit.
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
            if($booking->booking_status!="cancelled"){ }else{
                return redirect()->to('/')->with("danger","Booking Cancelled, Please contact Administrator");
            }
            $property=Property::find($booking->property_id);
            if($property){
                $data = new \stdClass();
                $data->name="Stripe Payment Booking ";
                $data->meta_title="Stripe Payment Booking ";
                $data->meta_keywords="Stripe Payment Booking ";
                $data->meta_description="Stripe Payment Booking ";  
                return view("front.booking.payment.stripe",compact("booking","data","property"));
            }
        }
        return abort(404);
    }

    function indexPost(Request $request,$id){
        // Detect AJAX/JSON callers (Plan D Task 10, spec D23). The visitor-flow
        // checkout JS posts here via fetch() and expects a JSON envelope, while
        // the legacy server-rendered Blade form still relies on redirects.
        $wantsJson = $request->wantsJson() || $request->expectsJson();
        $errorCode = 'gateway_error';
        $booking=BookingRequest::find($id);
        if($booking){
            $property=Property::find($booking->property_id);
            if($property){
                try {
                    Stripe\Stripe::setApiKey(ModelHelper::getDataFromSetting('stripe_secret_key'));
                    $customer = Stripe\Customer::create(array("email" => $booking->email,"source" => $request->stripeToken,));
                    $amount = \App\Services\Payments\DirectPaymentSchedule::amountDueForBooking($booking);
                    if ($amount <= 0) {
                        $amount = (float) ($booking->deposit_due_amount ?: $booking->total_amount);
                    }
                    $charge=Stripe\Charge::create ([ 'customer' => $customer->id, "amount" => $amount * 100, "currency" => "usd", "description" => "Payment for ".$property->name ]);
                    $chargeJson=$charge->jsonSerialize();
                    if($chargeJson['amount_refunded'] == 0 && empty($chargeJson['failure_code']) && $chargeJson['paid'] == 1 && $chargeJson['captured'] == 1){
                        // Idempotent insert keyed on Stripe charge id — Stripe tokens
                        // cannot themselves be reused, but firstOrCreate keeps the
                        // four gateways consistent and guards against duplicate
                        // counter increments (audit C-5 pattern).
                        $payment=Payment::firstOrCreate(
                            [ 'tran_id'=>$chargeJson['id'] ],
                            [ 'booking_id'=>$booking->id, 'receipt_url'=>$chargeJson['receipt_url'] , 'customer_id'=>$chargeJson['customer'] , 'balance_transaction'=>$chargeJson['balance_transaction'] , 'description'=>json_encode($chargeJson), 'status'=>"complete", 'type'=>"stripe", 'amount'=>$amount ]
                        );
                        if($payment->wasRecentlyCreated){
                            ModelHelper::finalEmailAndUpdateBookingPayment($id,$booking,$payment,$property,"payment");
                            $booking->refresh();
                            app(DirectInstallmentScheduler::class)->schedule(
                                $booking,
                                (string) ($chargeJson['customer'] ?? ''),
                                null,
                                strtoupper((string) ($chargeJson['currency'] ?? 'USD'))
                            );
                            $paidSoFar = (float) Payment::where('booking_id', $booking->id)
                                ->where('status', 'complete')
                                ->sum('amount');
                            $booking->forceFill([
                                'booking_status' => 'booked',
                                'payment_status' => ((float) $booking->total_amount > 0 && $paidSoFar + 0.01 >= (float) $booking->total_amount) ? 'paid' : 'partially',
                                'how_many_payment_done' => Payment::where('booking_id', $booking->id)
                                    ->where('status', 'complete')
                                    ->count(),
                            ])->save();
                            $booking->refresh();
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
                } catch(Stripe\Exception\CardException $e) {
                  $message =$e->getError()->message ;
                  $errorCode = 'card_declined';
                } catch (Stripe\Exception\RateLimitException $e) {
                    $message =$e->getError()->message ;
                    $errorCode = 'gateway_error';
                } catch (Stripe\Exception\InvalidRequestException $e) {
                    $message =$e->getError()->message ;
                    $errorCode = 'validation_failed';
                } catch (Stripe\Exception\AuthenticationException $e) {
                    $message =$e->getError()->message ;
                    $errorCode = 'gateway_error';
                } catch (Stripe\Exception\ApiConnectionException $e) {
                    $message =$e->getError()->message ;
                    $errorCode = 'gateway_error';
                } catch (Stripe\Exception\ApiErrorException $e) {
                    $message =$e->getError()->message ;
                    $errorCode = 'gateway_error';
                } catch (Exception $e) {
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

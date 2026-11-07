<?php
namespace App\Http\Controllers\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookingRequest;
use App\Models\Property;
use App\Models\Payment;
use Session;
use ModelHelper;
use MailHelper;

class CommonController extends Controller{
  
    function showReceipt(Request $request ,$id){
        // Phase 5 (2026-05-07): redirect to the new booking-confirmed
        // CMS page instead of rendering the legacy `first-preview` view —
        // first-preview uses the pre-redesign chrome (banner partial,
        // .booking-reciept selectors) which clashes badly with the new
        // checkout's white-card / orange-accent / Inter+Montserrat look.
        // No-store headers stop the back button from re-showing the
        // checkout form (browsers won't pull it from cache).
        $payment = Payment::find($id);
        if (!$payment) return abort(404);
        $booking = BookingRequest::find($payment->booking_id);
        if (!$booking) return abort(404);

        return redirect(url('booking-confirmed') . '?booking_id=' . $booking->id)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
  
    function showReceiptCheck(Request $request ,$id){
        $booking=BookingRequest::find($id);
        if($booking){
          if($booking->operation=="check-booking"){
            $property=Property::find($booking->property_id);
            if($property){
              $data = new \stdClass();
              $data->name="Booking Confirmation ";
              $data->meta_title="Booking Confirmation ";
              $data->meta_keywords="Booking Confirmation ";
              $data->meta_description="Booking Confirmation ";
              $booking=$booking->toArray();
              return view("front.booking.payment.first-preview-check",compact("booking","data","property"));
            }
          }
        }
        return abort(404);
    }
  
    function showReceiptVenmo(Request $request ,$id){
        $booking=BookingRequest::find($id);
        if($booking){
          if($booking->operation=="venmo-booking"){
            $property=Property::find($booking->property_id);
            if($property){
              $data = new \stdClass();
              $data->name="Booking Confirmation ";
              $data->meta_title="Booking Confirmation ";
              $data->meta_keywords="Booking Confirmation ";
              $data->meta_description="Booking Confirmation ";
              $booking=$booking->toArray();
              return view("front.booking.payment.first-preview-venmo",compact("booking","data","property"));
            }
          }
        }
        return abort(404);
    }
}
<?php
namespace App\Http\Controllers\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookingRequest;
use App\Models\Property;
use App\Models\Payment;
use App\Models\Pms\PmsRoom;
use App\Services\PmsBookingService;
use Illuminate\Database\Eloquent\Model;
use Session;

class PmsReceiptController extends Controller{

    /**
     * Payment success page — shown after Stripe or Square charge completes.
     *
     * Safety net for PMS reservation creation: if payment was collected but the
     * PMS reservation wasn't created yet (Square flow, or Stripe transient error),
     * we attempt to create it here before showing the confirmation page.
     */
    public function success(Request $request, $id){
        $booking = BookingRequest::find($id);
        if (!$booking) return abort(404);

        // PMS bookings use PmsRoom; CRM/Square bookings use Property.
        // Try PmsRoom first; fall back to Property so Square CRM bookings don't 404.
        $property = PmsRoom::find($booking->property_id);
        $isPmsBooking = ($property instanceof PmsRoom);

        if (!$isPmsBooking) {
            $property = Property::find($booking->property_id);
        }
        if (!$property) return abort(404);

        // PMS reservation safety net — only relevant for PMS rooms.
        // Fires when payment was collected but the PMS reservation wasn't created
        // (e.g. Square flow after old finalEmailAndUpdateBookingPayment ran Lodgify-only,
        //  or a Stripe transient failure mid-flow).
        $pmsJustCreated = false;
        if ($isPmsBooking && empty($booking->pms_booking_id) && !empty($property->pms_source)) {
            $result = app(PmsBookingService::class)->create($booking);
            if ($result['ok']) {
                $pmsJustCreated = true;
            } else {
                \Log::channel('lodgify')->error('CommonController: PMS reservation failed at receipt', [
                    'booking_id' => $id,
                    'pms'        => $property->pms_source,
                    'error'      => $result['error'] ?? 'unknown',
                ]);
            }
            $booking->refresh();
        }

        // Send confirmation emails — either PMS booking was just created here (Square flow)
        // or it was already confirmed (Stripe flow already sent emails, but safe to check).
        // Only send once: guard on booking_status being confirmed and no email sent yet.
        if ($booking->booking_status === 'booking-confirmed' && $pmsJustCreated) {
            try {
                $payment = \App\Models\Payment::where('booking_id', $booking->id)->latest()->first();
                app(\App\Services\Email\BookingLifecycleMailer::class)
                    ->sendConfirmationAndReceipt($booking, $property, $payment);
            } catch (\Throwable $e) {
                \Log::channel('lodgify')->error('CommonController: confirmation emails failed', [
                    'booking_id' => $id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // Site-design confirmation: the same booking-confirmed page the native
        // flow uses (CommonController does the identical redirect), so PMS and
        // direct bookings share one design. Falls back to the module's own
        // minimal page only when the site has no booking-confirmed CMS page.
        if (\App\Models\Cms::where('templete', 'booking-confirmed')->where('seo_url', 'booking-confirmed')->exists()) {
            return redirect(url('booking-confirmed') . '?booking_id=' . $booking->id);
        }

        $data = new \stdClass();
        $data->name = "Booking Confirmation";
        $data->meta_title = "Booking Confirmation";
        $data->meta_keywords = "Booking Confirmation";
        $data->meta_description = "Booking Confirmation";
        $booking = $booking->toArray();

        return view("front.booking.payment.pms-first-preview", compact("booking", "data", "property"));
    }
}

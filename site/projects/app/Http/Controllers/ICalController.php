<?php
namespace App\Http\Controllers;
use LiveCart;
use MailHelper;
use ModelHelper;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\BookingRequest;

class ICalController extends Controller
{
   function setCronJob(){
        // Plan C: thin wrapper — dispatches the Laravel Scheduler so all due jobs run via Kernel::schedule().
        \Artisan::call('schedule:run');
        return 'Schedule:run dispatched at ' . now();
   }
   /**
    * Public iCal feed for one property — what external sites (Airbnb, VRBO,
    * Google Calendar) subscribe to in order to block our unavailable dates
    * on their side.
    *
    * Two sources, both originating on this CRM:
    *   1. BookingRequest with status='booked' — confirmed bookings.
    *   2. PropertyRate single-day overrides with is_available falsy —
    *      manual day-blocks set by admin from the Calendar tab.
    *
    * Imported IcalEvent rows (Airbnb/VRBO inbound feeds) are intentionally
    * NOT re-exported — those channels already own those dates on their
    * side, so re-emitting would create sync loops.
    *
    * Date format: VALUE=DATE (whole days, not timestamps) — matches how
    * Airbnb / VRBO emit their feeds and avoids timezone drift.
    */
   public function getEventsICalObject($id)
   {
       $body = $this->buildIcsBody($id);
       if ($body === null) abort(404);

       return response($body, 200, [
           'Content-Type'        => 'text/calendar; charset=utf-8',
           'Content-Disposition' => 'inline; filename="property-' . sprintf('%06d', $id) . '.ics"',
           'Cache-Control'       => 'no-cache, must-revalidate',
       ]);
   }

   /**
    * Regenerate the static .ics file at uploads/ical/{padded}.ics that is
    * what external channels (Airbnb, VRBO, Google Calendar) actually
    * subscribe to. Called by the admin "Regenerate export" button and by
    * the iCal-refresh cron after each pull.
    *
    * Returns true on success, false if the property is missing or the
    * write fails. Errors are logged so a stuck cron is visible in the log.
    */
   public function writeIcsFile($id): bool
   {
       $body = $this->buildIcsBody($id);
       if ($body === null) return false;

       $dir = public_path('uploads/ical');
       if (!is_dir($dir)) @mkdir($dir, 0775, true);
       $path = $dir . '/' . sprintf('%06d', $id) . '.ics';
       return file_put_contents($path, $body) !== false;
   }

   /**
    * Compose the full iCalendar body for a property. Returns null if the
    * property doesn't exist; otherwise an RFC 5545 string with CRLFs.
    */
   public function buildIcsBody($id): ?string
   {
       $property = Property::find($id);
       if (!$property) return null;

       $bookings = BookingRequest::where('property_id', $id)
           ->where('booking_status', 'booked')
           ->whereNotNull('checkin')
           ->whereNotNull('checkout')
           ->get();

       $manualBlocks = PropertyRate::where('property_id', $id)
           ->whereNull('rate_group_id')
           ->whereNotNull('single_date')
           ->whereIn('is_available', ['0', 'false', 'no'])
           ->get();

       $host  = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'cms.local';
       $now   = gmdate('Ymd\THis\Z');
       $lines = [
           'BEGIN:VCALENDAR',
           'VERSION:2.0',
           'PRODID:-//' . $host . '//Property ' . $id . '//EN',
           'CALSCALE:GREGORIAN',
           'METHOD:PUBLISH',
           'X-WR-CALNAME:Bookings — ' . $this->icsEscape($property->name ?? ('Property ' . $id)),
       ];

       foreach ($bookings as $b) {
           $start = date('Ymd', strtotime((string) $b->checkin));
           $end   = date('Ymd', strtotime((string) $b->checkout));
           if (!$start || !$end) continue;

           $summary = 'Reserved' . ($b->name ? ' — ' . $b->name : '');

           $lines[] = 'BEGIN:VEVENT';
           $lines[] = 'UID:booking-' . $b->id . '@' . $host;
           $lines[] = 'DTSTAMP:' . $now;
           // VALUE=DATE → whole-day events (industry standard for booking feeds).
           $lines[] = 'DTSTART;VALUE=DATE:' . $start;
           $lines[] = 'DTEND;VALUE=DATE:' . $end;
           $lines[] = 'SUMMARY:' . $this->icsEscape($summary);
           $lines[] = 'DESCRIPTION:' . $this->icsEscape('Booking #' . $b->id);
           $lines[] = 'STATUS:CONFIRMED';
           if ($b->updated_at) {
               $lines[] = 'LAST-MODIFIED:' . gmdate('Ymd\THis\Z', strtotime((string) $b->updated_at));
           }
           $lines[] = 'TRANSP:OPAQUE';
           $lines[] = 'END:VEVENT';
       }

       foreach ($manualBlocks as $blk) {
           $startTs = strtotime((string) $blk->single_date);
           if (!$startTs) continue;
           // DTEND is exclusive for VALUE=DATE events: a one-day block runs from D to D+1.
           $start = date('Ymd', $startTs);
           $end   = date('Ymd', strtotime('+1 day', $startTs));
           $summary = 'Blocked' . ($blk->notes ? ' — ' . $blk->notes : '');

           $lines[] = 'BEGIN:VEVENT';
           $lines[] = 'UID:block-' . $blk->id . '@' . $host;
           $lines[] = 'DTSTAMP:' . $now;
           $lines[] = 'DTSTART;VALUE=DATE:' . $start;
           $lines[] = 'DTEND;VALUE=DATE:' . $end;
           $lines[] = 'SUMMARY:' . $this->icsEscape($summary);
           $lines[] = 'STATUS:CONFIRMED';
           if ($blk->updated_at) {
               $lines[] = 'LAST-MODIFIED:' . gmdate('Ymd\THis\Z', strtotime((string) $blk->updated_at));
           }
           $lines[] = 'TRANSP:OPAQUE';
           $lines[] = 'END:VEVENT';
       }

       $lines[] = 'END:VCALENDAR';
       // RFC 5545 line endings.
       return implode("\r\n", $lines) . "\r\n";
   }

   /** Escape per RFC 5545 §3.3.11. */
   private function icsEscape(string $s): string
   {
       return str_replace(
           ["\\", "\n", "\r", ',', ';'],
           ['\\\\', '\\n', '',  '\\,', '\\;'],
           $s
       );
   }
   
   function refresshCalendar(){
      // Plan C: thin wrapper.
      \Artisan::call('ical:refresh-feeds');
      return redirect()->back()->with("success","successfully refreshed");
   }
   
   function sendWelcomePackage(){
      // Plan C: thin wrapper.
      \Artisan::call('bookings:send-welcome-emails');
      return redirect()->back()->with("success","Welcome emails dispatched");
   }

   function sendReminderPackage(){
      // Plan C: thin wrapper. Dispatches both installment-2 AND installment-3 reminder commands.
      \Artisan::call('bookings:send-installment-2-reminders');
      \Artisan::call('bookings:send-installment-3-reminders');
      return redirect()->back()->with("success","Reminder emails dispatched");
   }

   function sendReviewEmail(){
      // Plan C: thin wrapper.
      \Artisan::call('bookings:send-review-emails');
      return redirect()->back()->with("success","Review emails dispatched");
   }
}
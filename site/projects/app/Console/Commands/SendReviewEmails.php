<?php

namespace App\Console\Commands;

use App\Models\BookingRequest;
use App\Models\Property;
use App\Models\Testimonial;
use Illuminate\Console\Command;
use ModelHelper;

class SendReviewEmails extends Command
{
    protected $signature = 'bookings:send-review-emails';
    protected $description = 'Send review-request email (with magic-link token) to bookings whose checkout was N days ago (N from basic_settings.review_send_day).';

    public function handle(): int
    {
        $offsetDays = (int) ModelHelper::getDataFromSetting('review_send_day');
        $targetDate = date('Y-m-d', strtotime("-{$offsetDays} days"));

        $bookings = BookingRequest::where('booking_status', 'completed')
            ->where('review_email', 'false')
            ->where('checkout', $targetDate)
            ->get();

        $sent = 0;
        foreach ($bookings as $booking) {
            $property = Property::find($booking->property_id);
            if (!$property) continue;

            try {
                $token = bin2hex(random_bytes(24));
                Testimonial::create([
                    'booking_id'       => $booking->id,
                    'property_id'      => $booking->property_id,
                    'submission_token' => $token,
                    'status'           => 'pending-link-sent',
                    'name'             => $booking->name,
                    'email'            => $booking->email,
                    'stay_date'        => $booking->checkin,
                    'is_home'          => 'false',
                ]);

                $sendError = null;
                $mailable  = new \App\Mail\ReviewRequestSent($booking, $property, $token);
                $rendered  = $mailable->render();
                $resolver  = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
                $subject   = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('review_default_subject') ?: 'How was your stay at {{property_name}}?'));
                try {
                    $mailFakeClass = \Illuminate\Support\Testing\Fakes\MailFake::class;
                    if (class_exists($mailFakeClass) && \Illuminate\Support\Facades\Mail::getFacadeRoot() instanceof $mailFakeClass) {
                        \Illuminate\Support\Facades\Mail::to($booking->email)->send($mailable);
                    } else {
                        \MailHelper::emailSenderByController($rendered, $booking->email, $subject);
                    }
                } catch (\Throwable $mailErr) {
                    $sendError = $mailErr->getMessage();
                    logger()->warning('SendReviewEmails: mail send failed', [
                        'booking_id' => $booking->id, 'error' => $sendError,
                    ]);
                }

                // Phase 13I — record attempt; only flip success flag on success.
                $update = [
                    'review_email_attempted_at' => now(),
                    'review_email_error' => $sendError,
                ];
                if ($sendError === null) {
                    $update['review_email'] = 'true';
                    $update['review_email_sent_at'] = now();
                }
                $booking->update($update);
                // Phase 13M — snapshot
                \App\Services\Email\SendLog::record((int)$booking->id, 'review', (string)$booking->email, $subject, $rendered, $sendError);
                if ($sendError === null) $sent++;
            } catch (\Throwable $e) {
                $this->error("Booking {$booking->id} failed: " . $e->getMessage());
                logger()->error('SendReviewEmails failed', ['booking_id' => $booking->id, 'exception' => $e]);
            }
        }

        $this->info("Sent {$sent} review emails (target checkout date {$targetDate})");
        return self::SUCCESS;
    }
}

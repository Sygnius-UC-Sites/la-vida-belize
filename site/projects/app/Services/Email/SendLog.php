<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\DB;

/**
 * Phase 13M — central recorder/reader for what-was-sent email snapshots.
 * Every Mail::send / MailHelper::emailSenderByController callsite calls
 * SendLog::record(...) right after the send so admins can later view the
 * exact HTML that went out (not a live re-render that may have drifted).
 */
final class SendLog
{
    /**
     * Persist a send snapshot. Pass $error when the send threw; success defaults to
     * (error === null). The caller should wrap their Mail::send in try/catch and
     * pass the captured exception message.
     */
    public static function record(int $bookingId, string $type, ?string $recipient, string $subject, string $html, ?string $error = null): void
    {
        DB::table('email_send_log')->insert([
            'booking_id'    => $bookingId,
            'email_type'    => $type,
            'recipient'     => $recipient,
            'subject'       => mb_substr($subject, 0, 500),
            'rendered_html' => $html,
            'sent_at'       => $error === null ? now() : null,
            'success'       => $error === null ? 1 : 0,
            'error'         => $error,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * Most recent snapshot for (booking, type), or null if never sent.
     */
    public static function latestFor(int $bookingId, string $type): ?\stdClass
    {
        return DB::table('email_send_log')
            ->where('booking_id', $bookingId)
            ->where('email_type', $type)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Set of email_types where AT LEAST one successful send exists for this booking.
     * Used by the booking-detail modal to decide which 👁️ Preview buttons to render.
     */
    public static function sentTypesFor(int $bookingId): array
    {
        return DB::table('email_send_log')
            ->where('booking_id', $bookingId)
            ->where('success', 1)
            ->pluck('email_type')
            ->unique()
            ->values()
            ->all();
    }
}

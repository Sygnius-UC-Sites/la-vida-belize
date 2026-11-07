<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingRequest;
use App\Models\ContactusRequest;
use App\Models\Payment;
use App\Models\Pms\PmsRoom;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Notification center.
 *
 * Returns a unified feed of recent activity (booking enquiries, contact
 * form submissions, payments) for the topbar bell-icon dropdown.
 */
class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $items = collect();

        $statusLabels = [
            'pending-approval' => 'awaiting approval',
            'booking-accepted' => 'accepted',
            'booked'           => 'confirmed',
            'declined'         => 'declined',
            'cancelled'        => 'cancelled',
            'pending'          => 'pending',
        ];

        $bookings = BookingRequest::query()
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'property_id', 'pms_source', 'name', 'first_name', 'last_name', 'email', 'request_id', 'checkin', 'checkout', 'total_night', 'booking_status', 'created_at']);

        // BookingRequest.property_id points at either properties or pms_rooms,
        // so a single Eloquent belongsTo relationship cannot represent both.
        $propertyIds = $bookings->pluck('property_id')->filter()->unique()->values();
        $propertyNames = Property::whereIn('id', $propertyIds)->pluck('name', 'id');
        $pmsRoomNames = PmsRoom::whereIn('id', $propertyIds)->pluck('name', 'id');

        foreach ($bookings as $b) {
            $name = trim((string) ($b->name ?: trim(($b->first_name ?? '') . ' ' . ($b->last_name ?? ''))));
            if ($name === '') $name = $b->email ?? ('Booking #' . $b->id);

            $propName = $b->pms_source
                ? ($pmsRoomNames[$b->property_id] ?? $propertyNames[$b->property_id] ?? null)
                : ($propertyNames[$b->property_id] ?? $pmsRoomNames[$b->property_id] ?? null);
            $statusKey = (string) $b->booking_status;
            $statusLbl = $statusLabels[$statusKey] ?? str_replace('-', ' ', $statusKey);

            // Title: customer name + status (no em-dashes / middle dots).
            $title = $name . ($statusLbl ? ' (' . $statusLbl . ')' : '');

            // Subtitle: property + dates + nights, plain ASCII separators.
            $sub = [];
            if ($propName) $sub[] = $propName;
            if ($b->checkin && $b->checkout) {
                $sub[] = Carbon::parse($b->checkin)->format('M j')
                       . ' - ' . Carbon::parse($b->checkout)->format('M j');
            }
            if ($b->total_night) {
                $sub[] = $b->total_night . ' night' . ($b->total_night == 1 ? '' : 's');
            }

            $items->push([
                'kind'      => 'booking',
                'icon'      => 'fas fa-calendar-check',
                'title'     => $title,
                'subtitle'  => implode(', ', $sub),
                'url'       => url('admin/booking-enquiries/' . $b->id . '/edit'),
                'time'      => $b->created_at,
                'time_iso'  => $b->created_at?->toIso8601String(),
                'time_human'=> $b->created_at?->diffForHumans(['short' => true]),
            ]);
        }

        if (class_exists(ContactusRequest::class)) {
            $contacts = ContactusRequest::orderByDesc('id')
                ->limit(5)
                ->get();
            foreach ($contacts as $c) {
                $contactName = trim((string) ($c->name ?? ''));
                if ($contactName === '') $contactName = $c->email ?? ('Contact #' . $c->id);
                $items->push([
                    'kind'      => 'contact',
                    'icon'      => 'fas fa-envelope',
                    'title'     => $contactName . ' sent an inquiry',
                    'subtitle'  => $c->email ?? ($c->mobile ?? 'No reply contact'),
                    'url'       => url('admin/contact-us-enquiries/' . $c->id . '/edit'),
                    'time'      => $c->created_at,
                    'time_iso'  => $c->created_at?->toIso8601String(),
                    'time_human'=> $c->created_at?->diffForHumans(['short' => true]),
                ]);
            }
        }

        if (class_exists(Payment::class)) {
            try {
                $payments = Payment::orderByDesc('id')->limit(4)->get();
                foreach ($payments as $p) {
                    $amt = $p->amount ?? $p->total_amount ?? null;
                    $items->push([
                        'kind'      => 'payment',
                        'icon'      => 'fas fa-dollar-sign',
                        'title'     => $amt ? 'Payment received: $' . number_format((float) $amt, 2) : 'Payment received',
                        'subtitle'  => (string) ($p->tran_id ?? $p->payment_status ?? '#' . $p->id),
                        'url'       => url('admin/payments'),
                        'time'      => $p->created_at,
                        'time_iso'  => $p->created_at?->toIso8601String(),
                        'time_human'=> $p->created_at?->diffForHumans(['short' => true]),
                    ]);
                }
            } catch (\Throwable $e) { /* model schema mismatch — skip */ }
        }

        // Sort all items by created_at desc, top 12.
        $sorted = $items
            ->filter(fn($i) => $i['time'] !== null)
            ->sortByDesc(fn($i) => $i['time']->getTimestamp())
            ->values()
            ->take(12)
            ->map(function ($i) {
                unset($i['time']);
                return $i;
            });

        // Anything in the last 24h is "new"
        $cutoff = Carbon::now()->subHours(24)->toIso8601String();
        $unread = $sorted->filter(fn($i) => ($i['time_iso'] ?? '') >= $cutoff)->count();

        return response()->json([
            'unread' => $unread,
            'items'  => $sorted->values(),
        ]);
    }
}

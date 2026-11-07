<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\BookingRequest;
use App\Models\Cms;
use App\Models\Location;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Command palette / global search.
 *
 * Returns grouped JSON results for the top-bar search dropdown. Static
 * menu destinations (Reservations / Listings / etc.) are filtered in JS;
 * this endpoint adds dynamic matches across Properties, Bookings, CMS
 * pages, Locations, and Attractions.
 */
class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['query' => '', 'groups' => []]);
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

        $groups = [];

        /* ─── Properties ─── */
        $properties = Property::where(function ($w) use ($like) {
                $w->where('name', 'LIKE', $like)
                  ->orWhere('seo_url', 'LIKE', $like)
                  ->orWhere('address', 'LIKE', $like);
            })
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$q . '%'])
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name', 'seo_url', 'type', 'address']);

        if ($properties->isNotEmpty()) {
            $groups[] = [
                'label' => 'Properties',
                'items' => $properties->map(function (Property $p) {
                    $typeLabel = $p->type ? ucfirst((string) $p->type) : 'Listing';
                    $sub = $typeLabel
                         . ($p->seo_url ? ' · ' . $p->seo_url : '')
                         . ($p->address ? ' · ' . $p->address : '');
                    return [
                        'title'    => (string) ($p->name ?? 'Untitled'),
                        'subtitle' => $sub,
                        'url'      => url('admin/properties/' . $p->id . '/edit'),
                        'icon'     => 'fas fa-fw fa-building',
                        'kbd'      => 'Edit',
                    ];
                })->all(),
            ];
        }

        /* ─── Bookings ─── */
        $bookings = BookingRequest::where(function ($w) use ($like) {
                $w->where('name', 'LIKE', $like)
                  ->orWhere('first_name', 'LIKE', $like)
                  ->orWhere('last_name', 'LIKE', $like)
                  ->orWhere('email', 'LIKE', $like)
                  ->orWhere('request_id', 'LIKE', $like);
            })
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'name', 'first_name', 'last_name', 'email', 'request_id', 'checkin', 'checkout', 'booking_status']);

        if ($bookings->isNotEmpty()) {
            $groups[] = [
                'label' => 'Bookings',
                'items' => $bookings->map(function (BookingRequest $b) {
                    $name = trim((string) ($b->name ?: trim(($b->first_name ?? '') . ' ' . ($b->last_name ?? ''))));
                    if ($name === '') $name = $b->email ?? ('Booking #' . $b->id);
                    $sub = ($b->request_id ?: '#' . $b->id)
                         . ($b->checkin && $b->checkout ? ' · ' . $b->checkin . ' → ' . $b->checkout : '')
                         . ($b->booking_status ? ' · ' . str_replace('-', ' ', (string) $b->booking_status) : '');
                    return [
                        'title'    => $name,
                        'subtitle' => $sub,
                        'url'      => url('admin/booking-enquiries/' . $b->id . '/edit'),
                        'icon'     => 'fas fa-fw fa-calendar-check',
                        'kbd'      => 'Open',
                    ];
                })->all(),
            ];
        }

        /* ─── CMS pages ─── */
        $pages = Cms::where(function ($w) use ($like) {
                $w->where('name', 'LIKE', $like)
                  ->orWhere('seo_url', 'LIKE', $like)
                  ->orWhere('meta_title', 'LIKE', $like);
            })
            ->orderBy('name')
            ->limit(4)
            ->get(['id', 'name', 'seo_url']);

        if ($pages->isNotEmpty()) {
            $groups[] = [
                'label' => 'CMS pages',
                'items' => $pages->map(function (Cms $c) {
                    return [
                        'title'    => (string) ($c->name ?? 'Untitled page'),
                        'subtitle' => '/' . ltrim((string) ($c->seo_url ?? ''), '/'),
                        'url'      => url('admin/cms/' . $c->id . '/edit'),
                        'icon'     => 'fas fa-fw fa-file-alt',
                        'kbd'      => 'Edit',
                    ];
                })->all(),
            ];
        }

        /* ─── Locations ─── */
        $locations = Location::where('name', 'LIKE', $like)
            ->orderBy('name')
            ->limit(4)
            ->get(['id', 'name']);

        if ($locations->isNotEmpty()) {
            $groups[] = [
                'label' => 'Locations',
                'items' => $locations->map(function (Location $l) {
                    return [
                        'title'    => (string) ($l->name ?? ''),
                        'subtitle' => 'Location',
                        'url'      => url('admin/locations/' . $l->id . '/edit'),
                        'icon'     => 'fas fa-fw fa-map-marker-alt',
                        'kbd'      => 'Edit',
                    ];
                })->all(),
            ];
        }

        /* ─── Attractions ─── */
        $attractions = Attraction::where('name', 'LIKE', $like)
            ->orderBy('name')
            ->limit(4)
            ->get(['id', 'name']);

        if ($attractions->isNotEmpty()) {
            $groups[] = [
                'label' => 'Attractions',
                'items' => $attractions->map(function (Attraction $a) {
                    return [
                        'title'    => (string) ($a->name ?? ''),
                        'subtitle' => 'Attraction',
                        'url'      => url('admin/attractions/' . $a->id . '/edit'),
                        'icon'     => 'fas fa-fw fa-magnet',
                        'kbd'      => 'Edit',
                    ];
                })->all(),
            ];
        }

        return response()->json([
            'query'  => $q,
            'groups' => $groups,
        ]);
    }
}

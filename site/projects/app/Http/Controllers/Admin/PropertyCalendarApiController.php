<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ICalController;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\PropertyRateGroup;
use App\Models\IcalEvent;
use App\Models\IcalImportList;
use App\Models\BookingRequest;
use App\Helper\Upload;
use App\Imports\RateSheetImport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use LiveCart;
use Maatwebsite\Excel\Facades\Excel;

class PropertyCalendarApiController extends Controller
{
    /**
     * GET /admin/properties/{id}/calendar/days?from=Y-m-d&to=Y-m-d
     * Returns one day-cell payload per date in the inclusive range.
     */
    public function days(Request $request, $property_id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);
        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to   = Carbon::parse($request->input('to'))->startOfDay();
        if ($from->diffInDays($to) > 366) {
            return response()->json(['error' => 'Range too large (max 366 days)'], 422);
        }

        // Pre-fetch everything in the window once (avoid N queries per day)
        $bookings = BookingRequest::where('property_id', $property_id)
            ->where('booking_status', 'booked')
            ->where('checkout', '>=', $from->toDateString())
            ->where('checkin', '<=', $to->toDateString())
            ->get();
        $icalEvents = IcalEvent::where('event_pid', $property_id)
            ->where('end_date', '>=', $from->toDateString())
            ->where('start_date', '<=', $to->toDateString())
            ->get();
        $icalFeeds = IcalImportList::where('property_id', $property_id)->get()->keyBy('id');
        $singles = PropertyRate::where('property_id', $property_id)
            ->whereNull('rate_group_id')
            ->whereBetween('single_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(function ($row) { return date('Y-m-d', strtotime((string) $row->single_date)); });
        $ranges = PropertyRateGroup::where('property_id', $property_id)
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->orderBy('id', 'desc')
            ->get();

        $defaultPrice = $property->standard_rate;
        $defaultMinStay = $property->min_stay;
        $currency = '$';

        $weekendRate = ($property->weekend_rate !== null && $property->weekend_rate !== '')
            ? (int) $property->weekend_rate : null;
        $weekendDays = $property->weekend_days
            ? array_values(array_filter(array_map('trim', explode(',', $property->weekend_days)), 'strlen'))
            : [];

        $out = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $d = $cursor->toDateString();
            $payload = [
                'date' => $d,
                'price' => $defaultPrice,
                'currency' => $currency,
                'min_stay' => $defaultMinStay,
                'is_available' => true,
                'status' => 'available',
                'source' => 'default',
                'source_label' => null,
                'source_color' => null,
                'notes' => null,
            ];

            // Weekend uplift — sits between default and explicit rules.
            if ($weekendRate !== null && $weekendDays && in_array((string) $cursor->dayOfWeek, $weekendDays, true)) {
                $payload['price']        = (float) $weekendRate;
                $payload['source']       = 'weekend';
                $payload['source_label'] = 'Weekend rate';
            }

            // Apply most-recent matching range rule
            foreach ($ranges as $rg) {
                if ($d >= $rg->start_date && $d <= $rg->end_date) {
                    $weekdayPrice = $this->weekdayPrice($rg, $cursor->dayOfWeek);
                    $payload['price'] = $weekdayPrice ?? $rg->price ?? $defaultPrice;
                    $payload['min_stay'] = $rg->min_stay ?? $defaultMinStay;
                    $payload['source'] = 'range';
                    $payload['source_label'] = $rg->name_of_price ?: 'Range rule #' . $rg->id;
                    break;
                }
            }

            // Single-day override beats range rule
            if (isset($singles[$d])) {
                $sd = $singles[$d];
                if ($sd->price !== null && $sd->price !== '') {
                    $payload['price'] = (float) $sd->price;
                }
                if ($sd->min_stay !== null && $sd->min_stay !== '') {
                    $payload['min_stay'] = (int) $sd->min_stay;
                }
                if ($sd->is_available !== null && $sd->is_available !== '') {
                    $payload['is_available'] = !in_array((string)$sd->is_available, ['0','false','no'], true);
                }
                if (!$payload['is_available']) {
                    $payload['status'] = 'blocked';
                }
                $payload['notes'] = $sd->notes;
                $payload['source'] = 'override';
                $payload['source_label'] = 'Day override';
            }

            // iCal-imported block
            foreach ($icalEvents as $ev) {
                if ($d >= $ev->start_date && $d < $ev->end_date) {
                    $payload['status'] = 'blocked';
                    $payload['is_available'] = false;
                    $feed = $icalFeeds[$ev->cat_id] ?? null;
                    $payload['source'] = 'ical';
                    $payload['source_label'] = $feed && $feed->label ? $feed->label : 'iCal import';
                    $payload['source_color'] = $feed->color ?? '#6c757d';
                    break;
                }
            }

            // Native booking — wins over iCal block
            foreach ($bookings as $bk) {
                if ($d >= $bk->checkin && $d < $bk->checkout) {
                    $payload['status'] = 'booked';
                    $payload['is_available'] = false;
                    $payload['source'] = 'booking';
                    $payload['source_label'] = $bk->name ?: ('Booking #' . $bk->id);
                    $payload['source_color'] = '#28a745';
                    $payload['booking_id'] = $bk->id;
                    break;
                }
            }

            $out[] = $payload;
            $cursor->addDay();
        }

        return response()->json(['days' => $out]);
    }

    private function weekdayPrice(PropertyRateGroup $rg, int $dayOfWeek): ?float
    {
        $map = [
            0 => 'sunday_price', 1 => 'monday_price', 2 => 'tuesday_price',
            3 => 'wednesday_price', 4 => 'thrusday_price',
            5 => 'friday_price', 6 => 'saturday_price',
        ];
        $col = $map[$dayOfWeek] ?? null;
        if ($col && $rg->{$col} !== null && $rg->{$col} !== '') {
            return (float) $rg->{$col};
        }
        return null;
    }

    /**
     * POST /admin/properties/{id}/calendar/defaults
     * Updates the property-level standard_rate (default nightly price),
     * min_stay (default min nights), and weekend_rate / weekend_days
     * (the optional weekend-uplift preset).
     */
    public function updateDefaults(Request $request, $property_id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $request->validate([
            'standard_rate' => 'nullable|numeric|min:0',
            'min_stay'      => 'nullable|integer|min:1',
            'weekend_rate'  => 'nullable|numeric|min:0',
            'weekend_days'  => 'nullable|string|regex:/^[0-6](,[0-6])*$/',
        ]);
        $property->standard_rate = $request->filled('standard_rate') ? (int) $request->standard_rate : null;
        $property->min_stay      = $request->filled('min_stay')      ? (int) $request->min_stay      : null;
        $property->weekend_rate  = $request->filled('weekend_rate')  ? (int) $request->weekend_rate  : null;
        $property->weekend_days  = $request->filled('weekend_days')  ? (string) $request->weekend_days : null;
        $property->save();
        return response()->json([
            'ok' => true,
            'standard_rate' => $property->standard_rate,
            'min_stay'      => $property->min_stay,
            'weekend_rate'  => $property->weekend_rate,
            'weekend_days'  => $property->weekend_days,
        ]);
    }

    /**
     * GET /admin/properties/{id}/calendar/overrides-count?from=&to=
     * Counts single-day override rows in the range. Used by the bulk-clear
     * modal preview before the user confirms the destructive DELETE.
     */
    public function overridesCount(Request $request, $property_id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);
        $count = PropertyRate::where('property_id', $property_id)
            ->whereNull('rate_group_id')
            ->whereBetween('single_date', [$request->from, $request->to])
            ->count();
        return response()->json(['count' => $count]);
    }

    /**
     * POST /admin/properties/{id}/calendar/days/bulk-update
     * Body: from, to, price?, min_stay?, is_available?, notes?
     */
    public function bulkUpdateDays(Request $request, $property_id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'price' => 'nullable|numeric|min:0',
            'min_stay' => 'nullable|integer|min:1',
            'is_available' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);
        $from = Carbon::parse($request->from);
        $to   = Carbon::parse($request->to);
        if ($from->diffInDays($to) > 366) {
            return response()->json(['error' => 'Range too large'], 422);
        }

        $count = 0;
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $d = $cursor->toDateString();
            $attrs = [
                'single_date_timestamp' => $cursor->timestamp,
                'platform_type' => 'airbnb',
                'currency' => 'USD',
            ];
            if ($request->filled('price')) $attrs['price'] = $request->price;
            if ($request->filled('min_stay')) $attrs['min_stay'] = $request->min_stay;
            if ($request->has('is_available')) $attrs['is_available'] = $request->boolean('is_available') ? 1 : 0;
            if ($request->has('notes')) $attrs['notes'] = $request->notes;

            // FIX (Jun 18 2026): property_rates.price is NOT NULL with no default, so blocking a
            // date WITHOUT a price 500'd on insert. Use firstOrNew so we keep an existing custom
            // price, and fall back to the property's standard rate only for brand-new rows.
            $rate = PropertyRate::firstOrNew(
                ['property_id' => $property_id, 'single_date' => $d, 'rate_group_id' => null]
            );
            $rate->fill($attrs);
            if ($rate->price === null || $rate->price === '') {
                $rate->price = $property->standard_rate ?? 0;
            }
            $rate->save();
            $count++;
            $cursor->addDay();
        }
        return response()->json(['ok' => true, 'updated' => $count]);
    }

    /**
     * DELETE /admin/properties/{id}/calendar/days?from=&to=
     * Removes single-day overrides in the range, reverting to range/default.
     */
    public function clearDayOverrides(Request $request, $property_id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);
        $deleted = PropertyRate::where('property_id', $property_id)
            ->whereNull('rate_group_id')
            ->whereBetween('single_date', [$request->from, $request->to])
            ->delete();
        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    /**
     * GET /admin/properties/{id}/calendar/reservations
     * Returns native bookings + iCal-imported events for the next 365 days.
     */
    public function reservations($property_id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $cutoff = Carbon::now()->subDays(90)->toDateString();
        $bookings = BookingRequest::where('property_id', $property_id)
            ->where('booking_status', 'booked')
            ->where('checkout', '>=', $cutoff)
            ->orderBy('checkin', 'asc')
            ->get(['id','name','email','checkin','checkout','total_amount','booking_status'])
            ->map(function ($b) {
                return [
                    'kind' => 'booking',
                    'id' => $b->id,
                    'guest' => $b->name,
                    'email' => $b->email,
                    'checkin' => $b->checkin,
                    'checkout' => $b->checkout,
                    'total' => $b->total_amount,
                    'status' => $b->booking_status,
                ];
            });
        $ical = IcalEvent::where('event_pid', $property_id)
            ->where('end_date', '>=', $cutoff)
            ->orderBy('start_date', 'asc')
            ->get()
            ->map(function ($e) {
                return [
                    'kind' => 'ical',
                    'id' => $e->id,
                    'summary' => $e->text,
                    'checkin' => $e->start_date,
                    'checkout' => $e->end_date,
                    'feed_id' => $e->cat_id,
                ];
            });
        return response()->json([
            'bookings' => $bookings,
            'ical' => $ical,
        ]);
    }

    /**
     * GET /admin/properties/{id}/calendar/pricing-rules
     * Returns the property's pricing rules as JSON for the AJAX rule
     * table re-render after a save/delete.
     */
    public function listPricingRules($property_id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $rules = PropertyRateGroup::where('property_id', $property_id)
            ->orderBy('start_date')
            ->get();
        return response()->json(['rules' => $rules]);
    }

    /**
     * POST /admin/properties/{id}/calendar/pricing-rule
     */
    public function storePricingRule(Request $request, $property_id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $request->validate([
            'name_of_price' => 'nullable|string|max:120',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'price' => 'nullable|numeric|min:0',
            'min_stay' => 'nullable|integer|min:1',
        ]);
        $data = $request->only([
            'name_of_price','start_date','end_date','price','base_price',
            'monday_price','tuesday_price','wednesday_price','thrusday_price',
            'friday_price','saturday_price','sunday_price',
            'min_stay','base_min_stay','discount_weekly','discount_monthly',
            'checkin_day','checkout_day','type_of_price','currency','platform_type','notes',
        ]);
        $data['property_id'] = $property_id;
        $data['start_date_timestamp'] = strtotime($request->start_date);
        $data['end_date_timestamp'] = strtotime($request->end_date);
        $data['is_available'] = 1;
        // Schema requires non-null name_of_price + type_of_price.
        $data['name_of_price'] = $data['name_of_price'] ?? 'Untitled rule';
        $data['type_of_price'] = $data['type_of_price'] ?? (
            ($data['monday_price'] ?? null) !== null ||
            ($data['tuesday_price'] ?? null) !== null ||
            ($data['wednesday_price'] ?? null) !== null ||
            ($data['thrusday_price'] ?? null) !== null ||
            ($data['friday_price'] ?? null) !== null ||
            ($data['saturday_price'] ?? null) !== null ||
            ($data['sunday_price'] ?? null) !== null
            ? 'weekday' : 'default'
        );
        $created = PropertyRateGroup::create($data);
        return response()->json(['ok' => true, 'rule' => $created], 201);
    }

    /**
     * PUT /admin/properties/{id}/calendar/pricing-rule/{rule_id}
     */
    public function updatePricingRule(Request $request, $property_id, $id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $rule = PropertyRateGroup::where('property_id', $property_id)->where('id', $id)->first();
        if (!$rule) {
            return response()->json(['error' => 'Rule not found'], 404);
        }
        $request->validate([
            'name_of_price' => 'nullable|string|max:120',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'price' => 'nullable|numeric|min:0',
            'min_stay' => 'nullable|integer|min:1',
        ]);
        $data = $request->only([
            'name_of_price','start_date','end_date','price','base_price',
            'monday_price','tuesday_price','wednesday_price','thrusday_price',
            'friday_price','saturday_price','sunday_price',
            'min_stay','base_min_stay','discount_weekly','discount_monthly',
            'checkin_day','checkout_day','type_of_price','currency','platform_type','notes',
        ]);
        $data['start_date_timestamp'] = strtotime($request->start_date);
        $data['end_date_timestamp'] = strtotime($request->end_date);
        $rule->update($data);
        return response()->json(['ok' => true, 'rule' => $rule->fresh()]);
    }

    /**
     * DELETE /admin/properties/{id}/calendar/pricing-rule/{rule_id}
     */
    public function destroyPricingRule($property_id, $id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $rule = PropertyRateGroup::where('property_id', $property_id)->where('id', $id)->first();
        if (!$rule) {
            return response()->json(['error' => 'Rule not found'], 404);
        }
        PropertyRate::where('rate_group_id', $id)->delete();
        $rule->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * GET /admin/properties/{id}/calendar/ical-feeds
     */
    public function listIcalFeeds($property_id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $feeds = IcalImportList::where('property_id', $property_id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($f) {
                return [
                    'id' => $f->id,
                    'ical_link' => $f->ical_link,
                    'label' => $f->label,
                    'color' => $f->color,
                    'last_refreshed_at' => optional($f->last_refreshed_at)->toIso8601String(),
                    'last_refreshed_human' => $f->last_refreshed_at ? $f->last_refreshed_at->diffForHumans() : null,
                    'last_error' => $f->last_error,
                ];
            });
        return response()->json(['feeds' => $feeds]);
    }

    /**
     * POST /admin/properties/{id}/calendar/ical-feeds
     */
    public function storeIcalFeed(Request $request, $property_id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $request->validate([
            'ical_link' => 'required|url|max:2048',
            'label' => 'nullable|string|max:64',
            'color' => 'nullable|string|max:16',
        ]);
        $row = IcalImportList::create([
            'property_id' => $property_id,
            'ical_link' => $request->ical_link,
            'label' => $request->label,
            'color' => $request->color,
        ]);
        LiveCart::refreshIcalData($property_id, $row->ical_link, $row->id);
        return response()->json(['ok' => true, 'feed' => $row->fresh()], 201);
    }

    /**
     * POST /admin/properties/{id}/calendar/ical-feeds/{id}/refresh
     */
    public function refreshIcalFeed($property_id, $id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $row = IcalImportList::where('property_id', $property_id)->where('id', $id)->first();
        if (!$row) {
            return response()->json(['error' => 'Feed not found'], 404);
        }
        LiveCart::refreshIcalData($property_id, $row->ical_link, $row->id);
        return response()->json(['ok' => true, 'feed' => $row->fresh()]);
    }

    /**
     * DELETE /admin/properties/{id}/calendar/ical-feeds/{id}
     */
    public function destroyIcalFeed($property_id, $id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $row = IcalImportList::where('property_id', $property_id)->where('id', $id)->first();
        if (!$row) {
            return response()->json(['error' => 'Feed not found'], 404);
        }
        IcalEvent::where('ical_link', $row->ical_link)->where('event_pid', $property_id)->delete();
        $row->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * POST /admin/properties/{id}/calendar/regenerate-export
     */
    public function regenerateExport($property_id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $written = (new ICalController())->writeIcsFile($property_id);
        if (!$written) {
            return response()->json(['error' => 'Failed to write iCal file'], 500);
        }
        return response()->json([
            'ok' => true,
            'url' => asset('uploads/ical/' . sprintf('%06d', $property_id) . '.ics'),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /admin/properties/{id}/calendar/import-prices
     * Multipart: file (csv|xls|xlsx).
     */
    public function importExcelPrices(Request $request, $property_id)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $request->validate([
            'file' => 'required|file|mimes:csv,xls,xlsx|max:5120',
        ]);
        $rows = Excel::toCollection(null, $request->file('file'))[0];
        $fileId = (string) Str::uuid();
        $filename = 'rates_' . now()->format('Y_m_d') . '_' . $fileId;
        $imported = 0;
        foreach ($rows as $index => $row) {
            if ($index === 0) continue;
            $date = $row[0] ?? null;
            $price = $row[1] ?? null;
            if (!$date || !$price) continue;
            $dateObj = \DateTime::createFromFormat('m/d/Y', $date) ?: \DateTime::createFromFormat('d/m/Y', $date);
            if (!$dateObj) continue;
            $cleanPrice = (float) preg_replace('/[^\d.]/', '', $price);
            PropertyRate::updateOrCreate(
                ['property_id' => $property_id, 'single_date' => $dateObj->format('Y-m-d'), 'rate_group_id' => null],
                [
                    'single_date_timestamp' => $dateObj->getTimestamp(),
                    'price' => $cleanPrice,
                    'currency' => '$',
                    'type_of_price' => 'daywise',
                    'is_available' => 1,
                    'fileid' => $fileId,
                    'filename' => $filename,
                ]
            );
            $imported++;
        }
        return response()->json([
            'ok' => true,
            'imported' => $imported,
            'file_id' => $fileId,
            'filename' => $filename,
        ]);
    }

    /**
     * DELETE /admin/properties/{id}/calendar/import-prices/{fileid}
     */
    public function deleteImportedFile($property_id, $fileid)
    {
        $property = Property::find($property_id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        $deleted = PropertyRate::where('fileid', $fileid)->where('property_id', $property_id)->delete();
        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }
}

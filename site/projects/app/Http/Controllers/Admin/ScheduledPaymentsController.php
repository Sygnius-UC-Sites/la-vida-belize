<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScheduledPayment;
use App\Models\BookingRequest;
use Illuminate\Http\Request;

class ScheduledPaymentsController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'all');

        $query = ScheduledPayment::with('bookingRequest')
            ->orderBy('due_date', 'asc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $payments  = $query->get();
        $counts    = ScheduledPayment::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.scheduled-payments.index', compact('payments', 'status', 'counts'));
    }

    /**
     * Manually mark a scheduled payment as charged (e.g. collected offline).
     */
    public function markCharged(Request $request, $id)
    {
        $sp = ScheduledPayment::findOrFail($id);
        $sp->update([
            'status'     => 'charged',
            'charged_at' => now(),
            'error_message' => null,
        ]);
        return redirect()->back()->with('success', "Payment #{$id} marked as charged.");
    }

    /**
     * Reset a failed payment back to pending so the cron retries it.
     */
    public function resetToPending(Request $request, $id)
    {
        $sp = ScheduledPayment::findOrFail($id);
        $sp->update([
            'status'        => 'pending',
            'attempts'      => 0,
            'error_message' => null,
        ]);
        return redirect()->back()->with('success', "Payment #{$id} reset to pending — will retry on next cron run.");
    }
}

<?php

namespace App\Services\Payments;

use App\Models\BookingRequest;
use App\Services\Booking\Quote;
use Illuminate\Support\Facades\DB;

class DirectPaymentSchedule
{
    public static function fromQuote(Quote $quote): array
    {
        $rows = [];
        foreach ($quote->installments as $index => $installment) {
            $sequence = (int) ($installment['sequence'] ?? ($index + 1));
            $amount = round((float) ($installment['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }

            $label = $sequence === 1 ? 'Payment due today' : 'Scheduled payment ' . $sequence;
            $rows[] = [
                'amount' => $amount,
                'date_due' => $installment['due_date'] ?? $installment['date_due'] ?? now()->toDateString(),
                'due_date' => $installment['due_date'] ?? $installment['date_due'] ?? now()->toDateString(),
                'label' => $label,
                'message' => $label,
                'type' => ['first', 'second', 'third'][$sequence - 1] ?? 'payment',
                'sequence' => $sequence,
                'is_current' => $sequence === 1,
            ];
        }

        if (empty($rows) && $quote->total > 0) {
            $rows[] = [
                'amount' => round((float) $quote->total, 2),
                'date_due' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'label' => 'Payment due today',
                'message' => 'Payment due today',
                'type' => 'first',
                'sequence' => 1,
                'is_current' => true,
            ];
        }

        return $rows;
    }

    public static function scheduleFromBooking(BookingRequest $booking): array
    {
        $raw = json_decode((string) ($booking->amount_data ?? ''), true);
        if (!is_array($raw) || empty($raw)) {
            return [];
        }

        $rows = isset($raw['installments']) && is_array($raw['installments'])
            ? $raw['installments']
            : (array_is_list($raw) ? $raw : []);

        return array_values(array_filter(array_map(function ($row, int $index): ?array {
            if (!is_array($row)) {
                return null;
            }

            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount <= 0) {
                return null;
            }

            $sequence = (int) ($row['sequence'] ?? ($index + 1));
            $label = trim((string) ($row['label'] ?? $row['message'] ?? ''));
            if ($label === '') {
                $label = $sequence === 1 ? 'Payment due today' : 'Scheduled payment ' . $sequence;
            }

            return [
                'amount' => $amount,
                'date_due' => $row['date_due'] ?? $row['due_date'] ?? '',
                'due_date' => $row['due_date'] ?? $row['date_due'] ?? '',
                'label' => $label,
                'message' => $row['message'] ?? $label,
                'type' => $row['type'] ?? (['first', 'second', 'third'][$sequence - 1] ?? 'payment'),
                'sequence' => $sequence,
                'is_current' => (bool) ($row['is_current'] ?? $sequence === 1),
                'status' => $row['status'] ?? null,
            ];
        }, $rows, array_keys($rows))));
    }

    public static function initialDue(array $schedule, float $fallbackTotal): float
    {
        $current = array_filter($schedule, fn ($row) => !empty($row['is_current']));
        $amount = !empty($current)
            ? array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? 0), $current))
            : (float) ($schedule[0]['amount'] ?? 0);

        return round($amount > 0 ? $amount : $fallbackTotal, 2);
    }

    public static function amountDueForBooking(BookingRequest $booking): float
    {
        $total = round((float) ($booking->total_amount ?? 0), 2);
        $paidRemaining = (float) DB::table('payments')
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['complete', 'completed', 'succeeded', 'paid'])
            ->sum('amount');

        foreach (self::scheduleFromBooking($booking) as $row) {
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }

            if ($paidRemaining + 0.01 >= $amount) {
                $paidRemaining -= $amount;
                continue;
            }

            return $amount;
        }

        return round(max(0, $total - $paidRemaining), 2);
    }
}

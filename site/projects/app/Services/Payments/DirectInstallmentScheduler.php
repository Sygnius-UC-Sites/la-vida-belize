<?php

namespace App\Services\Payments;

use App\Models\BookingRequest;
use App\Models\ScheduledPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use ModelHelper;

class DirectInstallmentScheduler
{
    public function schedule(
        BookingRequest $booking,
        string $stripeCustomerId,
        ?string $stripePaymentMethodId = null,
        string $currency = 'USD'
    ): int {
        if ($stripeCustomerId === '' || !Schema::hasTable('scheduled_payments')) {
            return 0;
        }

        $amountData = json_decode((string) $booking->amount_data, true);
        if (!is_array($amountData) || empty($amountData)) {
            return 0;
        }

        $installments = $this->normaliseInstallments($amountData, $booking);
        if (count($installments) <= 1) {
            return 0;
        }

        ScheduledPayment::where('booking_request_id', $booking->id)
            ->where('pms_source', 'crm')
            ->where('status', 'pending')
            ->delete();

        $created = 0;
        foreach ($installments as $installment) {
            if (!is_array($installment)) {
                continue;
            }

            $sequence = (int) ($installment['sequence'] ?? 0);
            if ($sequence <= 1 || ($installment['status'] ?? null) === 'complete') {
                continue;
            }

            $amount = round((float) ($installment['amount'] ?? 0), 2);
            $dueDate = $this->parseDueDate($installment['due_date'] ?? $installment['date_due'] ?? null);
            if ($amount <= 0 || !$dueDate || $dueDate->lte(Carbon::today())) {
                continue;
            }

            ScheduledPayment::create([
                'booking_request_id'        => $booking->id,
                'pms_source'                => 'crm',
                'pms_booking_id'            => 'direct-' . $booking->id,
                'stripe_customer_id'        => $stripeCustomerId,
                'stripe_payment_method_id'  => $stripePaymentMethodId,
                'amount'                    => $amount,
                'currency'                  => strtoupper($currency ?: 'USD'),
                'due_date'                  => $dueDate->toDateString(),
                'status'                    => 'pending',
                'attempts'                  => 0,
                'pms_notified'              => false,
                'pms_notify_error'          => null,
                'reminder_sent'             => false,
            ]);

            $created++;
        }

        return $created;
    }

    private function normaliseInstallments(array $amountData, BookingRequest $booking): array
    {
        if (isset($amountData['installments']) && is_array($amountData['installments'])) {
            return array_map(function (array $row): array {
                return [
                    'sequence' => (int) ($row['sequence'] ?? 0),
                    'amount' => (float) ($row['amount'] ?? 0),
                    'due_date' => $row['due_date'] ?? $row['date_due'] ?? null,
                    'status' => $row['status'] ?? null,
                ];
            }, $amountData['installments']);
        }

        $totalPayments = (int) ($booking->total_payment ?? 1);
        if ($totalPayments <= 1 || !array_is_list($amountData)) {
            return [];
        }

        $checkin = $this->parseCheckin($booking);
        if (!$checkin) {
            return [];
        }

        $dueDates = [
            'second' => $this->dueDateForSecondInstallment($checkin, $totalPayments),
            'third'  => $this->dueDate($checkin, 'third_how_many_days'),
        ];

        return array_values(array_filter(array_map(function (array $row) use ($dueDates): ?array {
            $type = strtolower((string) ($row['type'] ?? ''));
            if (!in_array($type, ['first', 'second', 'third'], true)) {
                return null;
            }

            return [
                'sequence' => ['first' => 1, 'second' => 2, 'third' => 3][$type],
                'amount' => (float) ($row['amount'] ?? 0),
                'due_date' => isset($dueDates[$type]) ? $dueDates[$type]->toDateString() : Carbon::today()->toDateString(),
                'status' => $row['status'] ?? null,
            ];
        }, $amountData)));
    }

    private function parseDueDate(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseCheckin(BookingRequest $booking): ?Carbon
    {
        try {
            return $booking->checkin ? Carbon::parse($booking->checkin)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function dueDateForSecondInstallment(Carbon $checkin, int $totalPayments): Carbon
    {
        $setting = $totalPayments >= 3 ? 'second_third_how_many_days' : 'second_how_many_days';

        return $this->dueDate($checkin, $setting);
    }

    private function dueDate(Carbon $checkin, string $setting): Carbon
    {
        $offset = max(0, (int) ModelHelper::getDataFromSetting($setting));

        return $checkin->copy()->subDays($offset);
    }
}

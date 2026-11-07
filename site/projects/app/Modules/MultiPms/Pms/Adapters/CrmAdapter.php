<?php

namespace App\Modules\MultiPms\Pms\Adapters;

use App\Modules\MultiPms\Pms\PmsAdapter;

class CrmAdapter implements PmsAdapter
{
    public function getName(): string
    {
        return 'crm';
    }

    public function healthCheck(): array
    {
        return ['ok' => true, 'status' => 200];
    }

    public function listProperties(): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function getProperty(string $propertyId): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function listUnits(?string $propertyId = null): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function getAvailability(array $input): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function getQuote(array $input): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function createReservation(array $input): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function updateReservation(array $input): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function cancelReservation(array $input): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function listReservations(array $input = []): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function getPaymentLink(string $bookingId): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function getPropertyAddons(string $propertyId, array $query = []): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function parseWebhook(array $payload): array
    {
        return [];
    }

    public function getRawQuote(array $input): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function getReservation(string $reservationId): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function recordPayment(string $bookingId, float $amount, string $currency): array
    {
        return ['ok' => true, 'status' => 200];
    }

    public function registerWebhook(string $url, array $events): array
    {
        return ['ok' => true];
    }

    public function getReviews(string $propertyId): array
    {
        return [];
    }

    public function fetchAndSaveRawData(string $propertyId): void {}

    public function getFrontendData(string $propertyId): array
    {
        return [];
    }
}

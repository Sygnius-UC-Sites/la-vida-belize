<?php

namespace App\Modules\MultiPms\Pms;

interface PmsAdapter
{
    public function getName(): string;
    public function parseWebhook(array $payload): array;
    
    // New Phase 3 Methods
    public function healthCheck(): array;
    public function listProperties(): array;
    public function getProperty(string $propertyId): array;
    public function listUnits(?string $propertyId = null): array;
    public function getAvailability(array $input): array;
    public function getQuote(array $input): array;
    public function createReservation(array $input): array;
    public function updateReservation(array $input): array;
    public function cancelReservation(array $input): array;
    public function listReservations(array $input = []): array;
    public function getPaymentLink(string $bookingId): array;
    public function recordPayment(string $bookingId, float $amount, string $currency): array;

    /**
     * Register or update a webhook URL with this PMS.
     *
     * @param  string  $url     The public HTTPS URL the PMS should POST events to
     * @param  array   $events  List of event names to subscribe to (PMS-specific strings)
     * @return array   ['ok' => bool, 'webhook_id' => string|null, 'error' => string|null]
     */
    public function registerWebhook(string $url, array $events): array;
    public function getPropertyAddons(string $propertyId, array $query = []): array;
    
    /**
     * Fetch reviews for a property from the PMS.
     * Returns normalized array: [{id, name, score, message, stay_date, end_date, profile}]
     * Returns empty array if PMS doesn't support reviews or none exist.
     */
    public function getReviews(string $propertyId): array;

    // Raw Storage & Frontend Mapping
    public function fetchAndSaveRawData(string $propertyId): void;
    public function getFrontendData(string $propertyId): array;
}

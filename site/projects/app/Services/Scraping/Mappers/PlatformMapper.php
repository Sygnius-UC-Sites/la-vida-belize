<?php

namespace App\Services\Scraping\Mappers;

interface PlatformMapper
{
    /**
     * Build the input payload for the platform's Apify Actor from a single
     * source URL. Different Actors expect different input shapes.
     */
    public function buildActorInput(string $sourceUrl, int $maxReviews = 0): array;

    /**
     * Input shape for the dedicated reviews Actor (different Actor from the
     * listing scraper). Returns null if reviews-scraping isn't supported.
     */
    public function buildReviewsActorInput(string $sourceUrl, int $maxReviews): ?array;

    /**
     * Map a single dataset item from the dedicated reviews Actor → one review
     * record. The reviews Actor returns one review per dataset item (vs the
     * listing Actor which returns ONE listing with reviews nested inside).
     *
     * @param array<string, mixed> $reviewItem
     * @return array<string, mixed>|null  Same shape as elements of mapReviews(); null to skip.
     */
    public function mapReviewItem(array $reviewItem): ?array;

    /**
     * Extract review records from one dataset item. The orchestrator routes
     * these through ReviewImporter for idempotent persistence.
     *
     * @param array<string, mixed> $datasetItem
     * @return list<array{
     *   external_id?:    string,
     *   reviewer_name?:  string,
     *   reviewer_photo?: string,
     *   reviewer_location?: string,
     *   rating?:         int,
     *   comment?:        string,
     *   comment_translated?: string,
     *   language?:       string,
     *   response_text?:  string,
     *   response_date?:  string,
     *   review_date?:    string,
     * }>
     */
    public function mapReviews(array $datasetItem): array;

    /**
     * Map one dataset item (raw JSON from Apify) to a normalised payload
     * the orchestrator can write into the properties table + related tables.
     *
     * @param array<string, mixed> $datasetItem
     * @return array{
     *   property:       array<string, mixed>,
     *   images:         list<string>,
     *   amenities_raw:  list<string>,
     *   amenity_groups: list<array{name: string, items: list<array{name: string, icon?: string}>}>,
     *   highlights:     list<array{title: string, subtitle?: string, icon?: string}>,
     * }
     */
    public function mapToProperty(array $datasetItem): array;
}

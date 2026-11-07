<?php

namespace App\Helpers;

use Illuminate\Http\Request;

/**
 * Reads/writes the standard visitor search-context URL params:
 *   check_in, check_out, adults, children, pets, category, bedrooms, min_price, max_price
 *
 * Spec D19. Used by:
 *   - Home hero search → /stays
 *   - /stays listing filters
 *   - Property detail widget pre-fill
 *   - Checkout page pre-fill
 */
class BookingContext
{
    public ?string $check_in = null;
    public ?string $check_out = null;
    public int $adults = 1;
    public int $children = 0;
    public int $pets = 0;
    public ?string $category = null;
    /** @deprecated Kept only so old shared links continue to parse. */
    public ?string $type = null;
    public ?int $bedrooms = null;
    public ?int $min_price = null;
    public ?int $max_price = null;

    public static function fromRequest(?Request $request = null): self
    {
        $request ??= request();
        $ctx = new self();
        $ctx->check_in   = $request->query('check_in') ?: null;
        $ctx->check_out  = $request->query('check_out') ?: null;
        $ctx->adults     = max(1, (int) $request->query('adults', 1));
        $ctx->children   = max(0, (int) $request->query('children', 0));
        $ctx->pets       = max(0, (int) $request->query('pets', 0));
        $category = trim((string) $request->query('category', ''));
        $ctx->category = preg_match('/^[a-z0-9][a-z0-9-]*$/', $category) ? $category : null;
        $ctx->type = in_array($request->query('type'), ['condo', 'house'], true)
            ? $request->query('type')
            : null;
        $ctx->bedrooms   = $request->query('bedrooms') !== null ? (int) $request->query('bedrooms') : null;
        $ctx->min_price  = $request->query('min_price') !== null ? (int) $request->query('min_price') : null;
        $ctx->max_price  = $request->query('max_price') !== null ? (int) $request->query('max_price') : null;
        return $ctx;
    }

    /** Returns the params suitable for forwarding via http_build_query(). Skips defaults. */
    public function forwardable(): array
    {
        $a = [];
        if ($this->check_in)  $a['check_in']  = $this->check_in;
        if ($this->check_out) $a['check_out'] = $this->check_out;
        if ($this->adults > 1) $a['adults']   = $this->adults;
        if ($this->children > 0) $a['children'] = $this->children;
        if ($this->pets > 0)  $a['pets']      = $this->pets;
        if ($this->category)  $a['category']  = $this->category;
        if ($this->type)      $a['type']      = $this->type;
        if ($this->bedrooms)  $a['bedrooms']  = $this->bedrooms;
        if ($this->min_price) $a['min_price'] = $this->min_price;
        if ($this->max_price) $a['max_price'] = $this->max_price;
        return $a;
    }
}

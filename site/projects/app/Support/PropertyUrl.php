<?php

namespace App\Support;

use App\Models\Property;

class PropertyUrl
{
    private static ?bool $hasMultipleTypes = null;

    public static function hasMultipleTypes(): bool
    {
        if (app()->runningUnitTests()) {
            return self::queryHasMultipleTypes();
        }

        if (self::$hasMultipleTypes !== null) {
            return self::$hasMultipleTypes;
        }

        self::$hasMultipleTypes = self::queryHasMultipleTypes();

        return self::$hasMultipleTypes;
    }

    private static function queryHasMultipleTypes(): bool
    {
        return Property::query()
            ->where('status', 'true')
            ->whereIn('type', ['condo', 'house'])
            ->distinct()
            ->count('type') > 1;
    }

    public static function path(Property $property): string
    {
        $slug = ltrim((string) $property->seo_url, '/');

        if (!self::hasMultipleTypes()) {
            return '/stay/' . $slug;
        }

        $prefix = match (strtolower((string) $property->type)) {
            'condo', 'condos' => 'condos',
            'house', 'houses' => 'houses',
            default => 'stay',
        };

        return '/' . $prefix . '/' . $slug;
    }

    public static function url(Property $property, array $query = [], ?string $fragment = null): string
    {
        $url = url(self::path($property));
        $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        if ($fragment) {
            $url .= '#' . ltrim($fragment, '#');
        }

        return $url;
    }
}

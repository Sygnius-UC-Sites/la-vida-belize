<?php

namespace App\Services\Scraping;

use App\Services\Scraping\Mappers\PlatformMapper;

class MapperFactory
{
    public function for(string $platform): ?PlatformMapper
    {
        $map = (array) config('importer.mappers', []);
        $fqcn = $map[$platform] ?? null;
        if (!is_string($fqcn) || !class_exists($fqcn)) {
            return null;
        }
        $instance = app($fqcn);
        return $instance instanceof PlatformMapper ? $instance : null;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PMS data lifecycle: archive a provider's synced data to a JSON snapshot,
 * clear it from the live tables, and restore a snapshot later.
 *
 * Cross-table relations use the provider's EXTERNAL ids (pms_room_id,
 * pms_property_id), so internal autoincrement PKs are dropped on restore and
 * reassigned safely. Site pages (core properties) are never deleted — they
 * are unlinked on clear and re-linked on restore via their URL slugs.
 */
class PmsArchiveService
{
    private const TABLES = [
        'pms_properties', 'pms_rooms', 'pms_images', 'pms_bookings',
        'pms_calendar', 'pms_rates', 'pms_unavailable_dates',
        'pms_amenity_groups', 'pms_amenity_group_items', 'pms_property_links',
    ];

    public function archiveDir(): string
    {
        $dir = storage_path('app/pms-archives');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }

    /** Returns the snapshot filename, or null when the provider has no data. */
    public function archive(string $provider): ?string
    {
        $snapshot = [
            'provider'    => $provider,
            'archived_at' => now()->toIso8601String(),
            'tables'      => [],
        ];

        [$roomIds, $propIds] = $this->externalIds($provider);

        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) continue;
            if (Schema::hasColumn($table, 'pms_source')) {
                $rows = DB::table($table)->where('pms_source', $provider)->get();
            } elseif ($table === 'pms_images' && ($roomIds || $propIds)) {
                $rows = DB::table('pms_images')->where(function ($q) use ($roomIds, $propIds) {
                    if ($roomIds) $q->whereIn('pms_room_id', $roomIds);
                    if ($propIds) $q->orWhereIn('pms_property_id', $propIds);
                })->get();
            } else {
                continue;
            }
            if ($rows->count()) $snapshot['tables'][$table] = $rows->toArray();
        }

        // Provider-specific raw-data tables (pms_lodgify_*, pms_guesty_data, …).
        foreach (Schema::getTables() as $t) {
            $tname = is_array($t) ? ($t['name'] ?? '') : ($t->name ?? '');
            if (str_starts_with($tname, 'pms_' . $provider)) {
                $rows = DB::table($tname)->get();
                if ($rows->count()) $snapshot['tables'][$tname] = $rows->toArray();
            }
        }

        $snapshot['linked_site_properties'] = DB::table('properties')
            ->where('pms_source', $provider)->pluck('seo_url')->toArray();

        if (empty($snapshot['tables'])) return null;

        $file = $provider . '-' . now()->format('Y-m-d-His') . '.json';
        file_put_contents($this->archiveDir() . '/' . $file,
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $file;
    }

    /** Remove a provider's rows from all live PMS tables; unlink (keep) site pages. */
    public function clear(string $provider): void
    {
        [$roomIds, $propIds] = $this->externalIds($provider);
        if (Schema::hasTable('pms_images') && ($roomIds || $propIds)) {
            DB::table('pms_images')->where(function ($q) use ($roomIds, $propIds) {
                if ($roomIds) $q->whereIn('pms_room_id', $roomIds);
                if ($propIds) $q->orWhereIn('pms_property_id', $propIds);
            })->delete();
        }
        foreach (self::TABLES as $table) {
            if ($table === 'pms_images') continue;
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'pms_source')) {
                DB::table($table)->where('pms_source', $provider)->delete();
            }
        }
        foreach (Schema::getTables() as $t) {
            $tname = is_array($t) ? ($t['name'] ?? '') : ($t->name ?? '');
            if (str_starts_with($tname, 'pms_' . $provider)) {
                DB::table($tname)->delete();
            }
        }
        DB::table('properties')->where('pms_source', $provider)
            ->update(['pms_source' => null, 'pms_room_id' => null]);
    }

    /**
     * Restore a snapshot. Any data the provider currently has is archived
     * first (nothing is ever silently lost), then replaced by the snapshot.
     * Returns a human summary.
     */
    public function restore(string $file): string
    {
        $path = $this->archiveDir() . '/' . basename($file);
        if (!is_file($path)) {
            throw new \RuntimeException('Archive not found.');
        }
        $snap = json_decode(file_get_contents($path), true);
        if (!is_array($snap) || empty($snap['provider']) || empty($snap['tables'])) {
            throw new \RuntimeException('Archive file is not a valid PMS snapshot.');
        }
        $provider = $snap['provider'];

        // Safety: snapshot whatever is live right now before replacing it.
        $preRestoreArchive = $this->archive($provider);
        $this->clear($provider);

        $restored = [];
        foreach ($snap['tables'] as $table => $rows) {
            if (!Schema::hasTable($table) || empty($rows)) continue;
            $cols = array_flip(array_column(Schema::getColumns($table), 'name'));
            $clean = [];
            foreach ($rows as $row) {
                $row = (array) $row;
                unset($row['id']); // PKs are reassigned; relations use external ids
                $clean[] = array_intersect_key($row, $cols);
            }
            foreach (array_chunk($clean, 100) as $chunk) {
                DB::table($table)->insert($chunk);
            }
            $restored[$table] = count($clean);
        }

        // Re-link site pages by slug → new internal room ids.
        $relinked = 0;
        foreach (($snap['linked_site_properties'] ?? []) as $slug) {
            $room = DB::table('pms_rooms')->where('pms_source', $provider)
                ->where('seo_url', $slug)->first();
            if ($room) {
                $relinked += DB::table('properties')->where('seo_url', $slug)
                    ->update(['pms_source' => $provider, 'pms_room_id' => $room->id]);
            }
        }

        $parts = [];
        foreach ($restored as $t => $n) $parts[] = str_replace('pms_', '', $t) . ': ' . $n;
        $msg = ucfirst($provider) . ' snapshot restored (' . implode(', ', $parts) . ').';
        if ($relinked) $msg .= ' ' . $relinked . ' site page(s) re-linked.';
        if ($preRestoreArchive) $msg .= ' The data that was live before this restore was archived as ' . $preRestoreArchive . '.';
        $msg .= ' Note: make sure the matching API key is set, or sync will overwrite this with the current account.';
        return $msg;
    }

    /** @return array{0: array, 1: array} provider's external room + property ids */
    private function externalIds(string $provider): array
    {
        $roomIds = Schema::hasTable('pms_rooms')
            ? DB::table('pms_rooms')->where('pms_source', $provider)->pluck('pms_room_id')->filter()->toArray() : [];
        $propIds = Schema::hasTable('pms_properties')
            ? DB::table('pms_properties')->where('pms_source', $provider)->pluck('pms_property_id')->filter()->toArray() : [];
        return [$roomIds, $propIds];
    }
}

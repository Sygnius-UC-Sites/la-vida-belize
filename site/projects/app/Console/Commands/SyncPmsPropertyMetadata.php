<?php

namespace App\Console\Commands;

use App\Models\Pms\PmsRoom;
use App\Services\PmsPropertySyncService;
use Illuminate\Console\Command;

class SyncPmsPropertyMetadata extends Command
{
    protected $signature = 'pms:sync-property-metadata
                            {--provider= : Restrict reconciliation to one PMS}';

    protected $description = 'Reconcile property content, pet rules, and canonical fee definitions';

    public function handle(PmsPropertySyncService $propertySync): int
    {
        $query = PmsRoom::query()
            ->select(['pms_source', 'pms_property_id'])
            ->whereNotNull('pms_source')
            ->whereNotNull('pms_property_id')
            ->distinct();

        if ($provider = $this->option('provider')) {
            $query->where('pms_source', strtolower((string) $provider));
        }

        $failed = 0;
        $query->orderBy('pms_source')->orderBy('pms_property_id')->chunk(25, function ($rooms) use ($propertySync, &$failed) {
            foreach ($rooms as $room) {
                $result = $propertySync->sync((string) $room->pms_source, (string) $room->pms_property_id);
                if (!$result['success']) {
                    $failed++;
                    $this->error("{$room->pms_source}:{$room->pms_property_id} - {$result['message']}");
                }
            }
        });

        if ($failed > 0) {
            $this->error("Property metadata reconciliation completed with {$failed} failure(s).");
            return self::FAILURE;
        }

        $this->info('Property metadata and fee reconciliation complete.');
        return self::SUCCESS;
    }
}

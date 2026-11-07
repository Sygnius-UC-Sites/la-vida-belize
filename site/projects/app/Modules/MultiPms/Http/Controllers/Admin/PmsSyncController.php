<?php

namespace App\Modules\MultiPms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MultiPms\Pms\PmsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PmsSyncController extends Controller
{
    public function __construct(protected PmsManager $pms) {}

    /**
     * Sync all configured PMS sources.
     */
    public function sync(Request $request)
    {
        try {
            $results = $this->runSync(null);
            return redirect()->back()->with('success', "Sync complete: {$results['synced']} properties synced.");
        } catch (\Throwable $e) {
            Log::error('PMS sync failed: ' . $e->getMessage());
            return redirect()->back()->with('danger', 'Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Sync a single PMS source (guesty, hostaway, etc.)
     */
    public function syncOne(Request $request, string $pms)
    {
        try {
            $results = $this->runSync($pms);
            return redirect()->back()->with('success', "Sync complete for {$pms}: {$results['synced']} properties.");
        } catch (\Throwable $e) {
            Log::error("PMS sync ({$pms}) failed: " . $e->getMessage());
            return redirect()->back()->with('danger', "Sync failed for {$pms}: " . $e->getMessage());
        }
    }

    private function runSync(?string $filter): array
    {
        // Run the SyncAllPmsData artisan command
        \Illuminate\Support\Facades\Artisan::call('pms:sync-all', $filter ? ['--pms' => $filter] : []);
        return ['synced' => 0]; // command outputs its own count
    }
}

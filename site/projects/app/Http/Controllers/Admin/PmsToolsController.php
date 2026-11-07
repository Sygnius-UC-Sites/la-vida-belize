<?php

namespace App\Http\Controllers\Admin;

/**
 * PMS MODULE — admin tools: PMS settings page, manual sync triggers,
 * webhook registration. Extracted from a legacy DashboardController so the
 * host site's DashboardController is never touched.
 */

use App\Http\Controllers\Controller;
use App\Models\BasicSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class PmsToolsController extends Controller
{
    /**
     * Standalone PMS settings page (renders the same fields used by the PMS module
     * inside its settings tab; posts to the site's existing setting save URL).
     */
    public function settings()
    {
        return view('admin.pms_tools.settings');
    }

    public function registerPmsWebhooks()
    {
        try {
            $baseUrl = \ModelHelper::getDataFromSetting('webhook_base_url')
                    ?: env('WEBHOOK_BASE_URL', '');

            if (empty($baseUrl)) {
                return redirect()->back()->with('danger', 'Webhook Base URL is not set. Add it in the PMS settings and save first.');
            }

            putenv("WEBHOOK_BASE_URL={$baseUrl}");
            $_ENV['WEBHOOK_BASE_URL'] = $baseUrl;

            Artisan::call('webhooks:register');
            $output = Artisan::output();

            $dateString = now()->format('Y-m-d H:i:s') . ' (base: ' . $baseUrl . ')';
            $data = BasicSetting::where('name', 'pms_webhooks_registered_at')->first();
            if ($data) {
                $data->value = $dateString;
                $data->save();
            } else {
                BasicSetting::create(['name' => 'pms_webhooks_registered_at', 'value' => $dateString]);
            }

            return redirect()->back()
                ->with('success', 'Webhook registration complete.')
                ->with('webhook_register_output', $output);
        } catch (\Exception $e) {
            return redirect()->back()->with('danger', 'Failed to register webhooks: ' . $e->getMessage());
        }
    }

    public function syncPmsData($provider)
    {
        set_time_limit(0); // PMS sync hits multiple APIs — no 30s web limit
        try {
            Artisan::call('pms:sync-all', ['--provider' => $provider]);
            $output = Artisan::output();

            return redirect()->back()->with('success', ucfirst($provider) . ' sync completed successfully! Output: ' . substr($output, 0, 200) . '...');
        } catch (\Exception $e) {
            return redirect()->back()->with('danger', 'Failed to sync ' . ucfirst($provider) . ': ' . $e->getMessage());
        }
    }

    public function syncPmsReviews()
    {
        set_time_limit(0);
        try {
            Artisan::call('pms:sync-reviews');
            $output = Artisan::output();
            return redirect()->back()->with('success', 'Reviews synced! ' . substr($output, 0, 300));
        } catch (\Exception $e) {
            return redirect()->back()->with('danger', 'Failed to sync reviews: ' . $e->getMessage());
        }
    }
}

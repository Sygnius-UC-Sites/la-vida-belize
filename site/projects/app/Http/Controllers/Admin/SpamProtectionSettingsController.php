<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Spam\SpamSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpamProtectionSettingsController extends Controller
{
    private const KEYS = [
        // toggles
        'spam_botguard_enabled', 'spam_honeypot_enabled', 'spam_time_trap_enabled',
        'spam_rate_limit_enabled', 'spam_disposable_email_enabled',
        // values
        'spam_botguard_site_key', 'spam_botguard_secret_key',
        'spam_time_trap_min_seconds',
        'spam_rate_limit_max_contact', 'spam_rate_limit_max_booking', 'spam_rate_limit_max_review',
        'spam_extra_disposable_domains',
    ];

    public function edit()
    {
        $rows = DB::table('basic_settings')->whereIn('name', self::KEYS)->pluck('value', 'name')->toArray();
        $settings = app(SpamSettings::class);

        $values = [
            'spam_botguard_enabled'        => $settings->turnstileEnabled(),
            'spam_honeypot_enabled'         => $settings->honeypotEnabled(),
            'spam_time_trap_enabled'        => $settings->timeTrapEnabled(),
            'spam_rate_limit_enabled'       => $settings->rateLimitEnabled(),
            'spam_disposable_email_enabled' => $settings->disposableEmailEnabled(),
            'spam_botguard_site_key'       => $settings->turnstileSiteKey(),
            'spam_botguard_secret_key'     => $settings->turnstileSecretKey(),
            'spam_time_trap_min_seconds'    => $settings->timeTrapMinSeconds(),
            'spam_rate_limit_max_contact'   => $settings->rateLimitMax('contact'),
            'spam_rate_limit_max_booking'   => $settings->rateLimitMax('booking'),
            'spam_rate_limit_max_review'    => $settings->rateLimitMax('review'),
            'spam_extra_disposable_domains' => $settings->extraDisposableDomains(),
        ];

        $recentLog = $this->readRecentLog(50);

        return view('admin.settings.spam-protection', compact('values', 'recentLog'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'spam_botguard_enabled'        => 'nullable|boolean',
            'spam_honeypot_enabled'         => 'nullable|boolean',
            'spam_time_trap_enabled'        => 'nullable|boolean',
            'spam_rate_limit_enabled'       => 'nullable|boolean',
            'spam_disposable_email_enabled' => 'nullable|boolean',
            'spam_botguard_site_key'       => 'nullable|string|max:255',
            'spam_botguard_secret_key'     => 'nullable|string|max:255',
            'spam_time_trap_min_seconds'    => 'nullable|integer|min:0|max:60',
            'spam_rate_limit_max_contact'   => 'nullable|integer|min:1|max:1000',
            'spam_rate_limit_max_booking'   => 'nullable|integer|min:1|max:1000',
            'spam_rate_limit_max_review'    => 'nullable|integer|min:1|max:1000',
            'spam_extra_disposable_domains' => 'nullable|string|max:20000',
        ]);

        $bools = [
            'spam_botguard_enabled', 'spam_honeypot_enabled', 'spam_time_trap_enabled',
            'spam_rate_limit_enabled', 'spam_disposable_email_enabled',
        ];

        foreach (self::KEYS as $key) {
            $value = in_array($key, $bools, true)
                ? ($request->boolean($key) ? '1' : '0')
                : (string) ($validated[$key] ?? '');

            DB::table('basic_settings')->updateOrInsert(
                ['name' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        // Drop SpamSettings cache so the next request reads fresh values.
        app(SpamSettings::class)->refresh();

        return redirect()->back()->with('success', 'Spam protection settings updated.');
    }

    /**
     * Read the tail of today's spam log for the admin to eyeball recent rejections.
     * Returns parsed lines (newest first) or empty array if the file isn't there yet.
     *
     * @return list<array{ts:string, reason:string, form:string, ip:string, email:?string}>
     */
    private function readRecentLog(int $max): array
    {
        $path = storage_path('logs/spam-' . now()->format('Y-m-d') . '.log');
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_slice(array_reverse($lines), 0, $max);

        $out = [];
        foreach ($lines as $line) {
            // Default Laravel log format:  [2026-05-04 12:34:56] local.INFO: spam_rejected {"form":"contact","reason":"honeypot",...}
            if (!preg_match('/^\[([^\]]+)\][^:]+:\s*spam_rejected\s+(\{.*\})\s*$/', $line, $m)) {
                continue;
            }
            $ts = $m[1];
            $ctx = json_decode($m[2], true);
            if (!is_array($ctx)) continue;
            $out[] = [
                'ts'     => $ts,
                'reason' => (string) ($ctx['reason'] ?? ''),
                'form'   => (string) ($ctx['form'] ?? ''),
                'ip'     => (string) ($ctx['ip'] ?? ''),
                'email'  => isset($ctx['email']) ? (string) $ctx['email'] : null,
            ];
        }
        return $out;
    }
}

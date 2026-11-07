<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename basic_settings keys so the DB no longer reveals which upstream
 * services we use. Old vendor-specific names are migrated to generic ones.
 *
 *   mail_ses_aws_access_key_id     → mail_provider_key_id
 *   mail_ses_aws_secret_access_key → mail_provider_secret
 *   mail_ses_aws_region            → mail_provider_region
 *   mail_ses_*                     → mail_*
 *   apify_api_token                → importer_api_token
 *   apify_*                        → importer_*
 *   spam_turnstile_*               → spam_botguard_*
 */
return new class extends Migration {
    public function up(): void
    {
        $renames = [
            // Mail (SES → generic)
            'mail_ses_aws_access_key_id'        => 'mail_provider_key_id',
            'mail_ses_aws_secret_access_key'    => 'mail_provider_secret',
            'mail_ses_aws_region'               => 'mail_provider_region',
            'mail_ses_enabled'                  => 'mail_enabled',
            'mail_ses_send_disabled'            => 'mail_send_disabled',
            'mail_ses_from_email'               => 'mail_from_email',
            'mail_ses_from_name'                => 'mail_from_name',
            'mail_ses_reply_to'                 => 'mail_reply_to',
            'mail_ses_domain'                   => 'mail_domain',
            'mail_ses_configuration_set'        => 'mail_configuration_set',
            'mail_ses_max_per_minute'           => 'mail_max_per_minute',
            'mail_ses_bounce_rate_threshold'    => 'mail_bounce_rate_threshold',
            'mail_ses_complaint_rate_threshold' => 'mail_complaint_rate_threshold',
            'mail_ses_sns_topic_arn_prefix'     => 'mail_topic_arn_prefix',

            // Listing imports (Apify → generic)
            'apify_enabled'                     => 'importer_enabled',
            'apify_api_token'                   => 'importer_api_token',
            'apify_actor_airbnb'                => 'importer_actor_airbnb',
            'apify_actor_airbnb_reviews'        => 'importer_actor_airbnb_reviews',
            'apify_max_runs_per_minute'         => 'importer_max_runs_per_minute',
            'apify_max_reviews'                 => 'importer_max_reviews',
            'apify_webhook_secret'              => 'importer_webhook_secret',

            // Spam (Turnstile → generic bot challenge)
            'spam_turnstile_enabled'            => 'spam_botguard_enabled',
            'spam_turnstile_site_key'           => 'spam_botguard_site_key',
            'spam_turnstile_secret_key'         => 'spam_botguard_secret_key',
        ];

        foreach ($renames as $old => $new) {
            // Skip when source doesn't exist — otherwise we'd nuke a legitimate
            // pre-existing row at the destination name (e.g. `mail_from_name`
            // is used by AppServiceProvider for SMTP config).
            $sourceExists = DB::table('basic_settings')->where('name', $old)->exists();
            if (!$sourceExists) continue;

            // If both exist, drop the destination first so the rename can succeed.
            DB::table('basic_settings')->where('name', $new)->delete();
            DB::table('basic_settings')->where('name', $old)->update(['name' => $new]);
        }
    }

    public function down(): void
    {
        // Reverse mapping for rollback.
        $back = [
            'mail_provider_key_id'              => 'mail_ses_aws_access_key_id',
            'mail_provider_secret'              => 'mail_ses_aws_secret_access_key',
            'mail_provider_region'              => 'mail_ses_aws_region',
            'mail_enabled'                      => 'mail_ses_enabled',
            'mail_send_disabled'                => 'mail_ses_send_disabled',
            'mail_from_email'                   => 'mail_ses_from_email',
            'mail_from_name'                    => 'mail_ses_from_name',
            'mail_reply_to'                     => 'mail_ses_reply_to',
            'mail_domain'                       => 'mail_ses_domain',
            'mail_configuration_set'            => 'mail_ses_configuration_set',
            'mail_max_per_minute'               => 'mail_ses_max_per_minute',
            'mail_bounce_rate_threshold'        => 'mail_ses_bounce_rate_threshold',
            'mail_complaint_rate_threshold'     => 'mail_ses_complaint_rate_threshold',
            'mail_topic_arn_prefix'             => 'mail_ses_sns_topic_arn_prefix',
            'importer_enabled'                  => 'apify_enabled',
            'importer_api_token'                => 'apify_api_token',
            'importer_actor_airbnb'             => 'apify_actor_airbnb',
            'importer_actor_airbnb_reviews'     => 'apify_actor_airbnb_reviews',
            'importer_max_runs_per_minute'      => 'apify_max_runs_per_minute',
            'importer_max_reviews'              => 'apify_max_reviews',
            'importer_webhook_secret'           => 'apify_webhook_secret',
            'spam_botguard_enabled'             => 'spam_turnstile_enabled',
            'spam_botguard_site_key'            => 'spam_turnstile_site_key',
            'spam_botguard_secret_key'          => 'spam_turnstile_secret_key',
        ];
        foreach ($back as $old => $new) {
            DB::table('basic_settings')->where('name', $new)->delete();
            DB::table('basic_settings')->where('name', $old)->update(['name' => $new]);
        }
    }
};

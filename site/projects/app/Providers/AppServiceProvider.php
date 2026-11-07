<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Config;
use Session;
use Illuminate\Support\Facades\View;
use App\Models\BasicSetting;
use MailHelper;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\App\Services\Spam\SpamSettings::class);
        $this->app->singleton(\App\Services\Mail\SesSettings::class);
        $this->app->singleton(\App\Services\Mail\SuppressionList::class);
        $this->app->singleton(\App\Services\Mail\HealthMonitor::class);
        $this->app->singleton(\App\Services\Mail\EmailValidator::class);
        $this->app->singleton(\App\Services\Mail\SesMailer::class);
        $this->app->singleton(\App\Services\Scraping\ApifySettings::class);
        $this->app->singleton(\App\Services\Scraping\MapperFactory::class);
        $this->app->singleton(\App\Services\Scraping\ReviewImporter::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Defensive: if APP_URL is configured without a scheme, force a sane root URL
        // so signed routes / absolute links don't double-up (e.g. http://localhost/localhost/...).
        $appUrl = config('app.url', '');
        if ($appUrl !== '' && !preg_match('#^https?://#i', $appUrl)) {
            \URL::forceRootUrl('http://' . ltrim($appUrl, '/'));
        }
        if ($this->app->environment('production')) {
            \URL::forceScheme('https');
        }

         Paginator::useBootstrap();

        // Defensive: during first boot (fresh DB, pre-migrate) the table may not exist yet.
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('basic_settings')) {
                View::share('setting_data', []);
                return;
            }
        } catch (\Throwable $e) {
            View::share('setting_data', []);
            return;
        }

         $ar = BasicSetting::get();

        // Keep the isolated array mailer selected by phpunit. Runtime database
        // credentials should only override real application environments.
        if (!$this->app->environment('testing')) {
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.transport', 'smtp');
            Config::set('mail.mailers.smtp.host', MailHelper::mailsendbydbdata('smtp_host'));
            Config::set('mail.mailers.smtp.port', MailHelper::mailsendbydbdata('smtp_port'));
            Config::set('mail.mailers.smtp.encryption', MailHelper::mailsendbydbdata('email_encryption'));
            Config::set('mail.mailers.smtp.username', MailHelper::mailsendbydbdata('smtp_user'));
            Config::set('mail.mailers.smtp.password', MailHelper::mailsendbydbdata('smtp_pass'));
            Config::set('mail.from.address', MailHelper::mailsendbydbdata('mail_from'));
            Config::set('mail.from.name', MailHelper::mailsendbydbdata('mail_from_name'));
        }
      
      
         $setting_data=[];
         foreach($ar as $a){
            $setting_data[$a->name]=$a->value;
         }
         View::share('setting_data', $setting_data);
    }
}

<?php
namespace App\Console;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule){
        // Booking-flow emails
        $this->monitor($schedule->command('bookings:send-welcome-emails')->everyFiveMinutes()->withoutOverlapping(), 'bookings:send-welcome-emails');
        $this->monitor($schedule->command('bookings:send-installment-2-reminders')->hourly()->withoutOverlapping(), 'bookings:send-installment-2-reminders');
        $this->monitor($schedule->command('bookings:send-installment-3-reminders')->hourly()->withoutOverlapping(), 'bookings:send-installment-3-reminders');
        $this->monitor($schedule->command('bookings:send-checkin-emails')->dailyAt('09:00')->withoutOverlapping(), 'bookings:send-checkin-emails');
        $this->monitor($schedule->command('bookings:send-checkout-emails')->dailyAt('09:00')->withoutOverlapping(), 'bookings:send-checkout-emails');
        $this->monitor($schedule->command('bookings:mark-completed')->dailyAt('03:00')->withoutOverlapping(), 'bookings:mark-completed');
        $this->monitor($schedule->command('bookings:send-review-emails')->dailyAt('10:00')->withoutOverlapping(), 'bookings:send-review-emails');

        // External integrations
        $this->monitor($schedule->command('ical:refresh-feeds')->hourlyAt(30)->withoutOverlapping(), 'ical:refresh-feeds');

        // Operational proof that the server cron is actually invoking Laravel.
        $this->monitor($schedule->command('ops:scheduler-heartbeat')->everyMinute()->withoutOverlapping(), 'ops:scheduler-heartbeat');

        // PMS schedules are owned by PmsServiceProvider so module installs and
        // the baseline application cannot register the same task twice.
    }

    private function monitor($event, string $command): void
    {
        $event
            ->before(fn () => \App\Services\Operations\OperationsRecorder::scheduledStarted($command))
            ->onSuccess(fn () => \App\Services\Operations\OperationsRecorder::scheduledFinished($command, 'success'))
            ->onFailure(fn () => \App\Services\Operations\OperationsRecorder::scheduledFinished($command, 'failure', 'Scheduled command failed.'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(){
        $this->load(__DIR__.'/Commands');
        // MultiPms disabled — superseded by pms-module-v2 (its commands self-register
        // via App\Providers\PmsServiceProvider; loading both causes pms:sync-* clashes).
        // $this->load(app_path('Modules/MultiPms'));

        require base_path('routes/console.php');
    }
}

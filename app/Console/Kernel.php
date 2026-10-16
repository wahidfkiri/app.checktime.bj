<?php

namespace App\Console;

use App\Jobs\SyncZonesJob;
use App\Models\ScheduledNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Schema;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Synchronisation automatique toutes les heures
        $schedule->job(new SyncZonesJob(), 'zones', 'database')->hourly();

        // Synchronisation forcée tous les jours à 2h du matin
        $schedule->job(new SyncZonesJob(null, true), 'zones-high', 'database')->dailyAt('02:00');

        // Nettoyage hebdomadaire
        $schedule->command('zones:cleanup')->weekly();

        // Surveiller la queue
        $schedule->command('queue:work --stop-when-empty')
            ->everyMinute()
            ->withoutOverlapping();

        // Rapports / notifications : planifiés PAR CLIENT depuis la base
        // (table scheduled_notifications), remplace les plannings fixes.
        $this->scheduleNotificationJobs($schedule);
    }

    /**
     * Planifie dynamiquement les tâches activées, une entrée par client.
     * Chaque tâche exécute sa commande avec --client={client_id}.
     */
    protected function scheduleNotificationJobs(Schedule $schedule): void
    {
        try {
            if (!Schema::hasTable('scheduled_notifications')) {
                return;
            }

            $jobs = ScheduledNotification::where('is_active', true)->get();

            foreach ($jobs as $job) {
                if ($job->frequency === 'once' && !$job->run_at) {
                    continue;
                }

                $command = $job->command . ' --client=' . $job->client_id;

                $event = $schedule->command($command)
                    ->cron($job->cronExpression())
                    ->timezone('Africa/Casablanca')
                    ->withoutOverlapping();

                // Mensuel au-delà de 28 : n'exécuter que le jour effectivement dû.
                if ($job->frequency === 'monthly' && ($job->day_of_month ?: 1) >= 29) {
                    $event->when(fn () => $job->isMonthlyDayDueToday(now('Africa/Casablanca')));
                }

                $jobId = $job->id;
                $isOnce = $job->frequency === 'once';
                $scheduledYear = $isOnce && $job->run_at ? $job->run_at->year : null;
                $alreadyRun = !is_null($job->last_run_at);

                if ($isOnce) {
                    $event->when(fn () => !$alreadyRun && now('Africa/Casablanca')->year === $scheduledYear);
                }

                // Horodatage de la dernière exécution (et désactivation si "once").
                $event->after(function () use ($jobId, $isOnce) {
                    ScheduledNotification::where('id', $jobId)->update([
                        'last_run_at' => now(),
                        'last_status' => $isOnce ? 'Exécutée (date unique)' : 'Exécutée (planifiée)',
                        'is_active'   => $isOnce ? false : true,
                    ]);
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Planification des notifications impossible: ' . $e->getMessage());
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

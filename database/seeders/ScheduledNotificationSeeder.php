<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ScheduledNotification;
use Illuminate\Database\Seeder;

/**
 * Crée les tâches planifiées par défaut pour chaque client existant (idempotent).
 * Les nouveaux clients sont provisionnés à leur création (ClientController@store).
 *
 * Usage : php artisan db:seed --class=ScheduledNotificationSeeder
 */
class ScheduledNotificationSeeder extends Seeder
{
    public function run(): void
    {
        Client::pluck('id')->each(function ($clientId) {
            ScheduledNotification::ensureDefaultsForClient((int) $clientId);
        });

        $this->command?->info('Tâches planifiées par défaut créées pour tous les clients.');
    }
}

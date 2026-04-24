<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceSyncService;
use Carbon\Carbon;

class SyncAttendanceCommand extends Command
{
    protected $signature = 'attendance:sync 
                            {--client= : ID du client spécifique}
                            {--date= : Date spécifique (format: Y-m-d)}
                            {--days-back=7 : Nombre de jours à synchroniser en arrière}
                            {--force : Forcer la synchronisation même si déjà faite}';
    
    protected $description = 'Synchroniser les pointages depuis l\'API externe';
    
    protected $attendanceService;
    
    public function __construct(AttendanceSyncService $attendanceService)
{
    parent::__construct();
    \Log::info('Constructeur de SyncAttendanceCommand appelé');
    $this->attendanceService = $attendanceService;
}
    
    public function handle()
{
    $this->info('Début de la synchronisation des pointages...');
    
    try {
        // Créez le service manuellement
        $this->attendanceService = app()->make(AttendanceSyncService::class);
        
        $clientId = $this->option('client');
        $specificDate = $this->option('date');
        $daysBack = 7;
        $force = $this->option('force');
        
        if ($specificDate) {
            $date = Carbon::parse($specificDate);
            $this->info("Synchronisation pour la date: {$date->format('Y-m-d')}");
            
            if ($clientId) {
                $client = \App\Models\Client::find($clientId);
                if ($client) {
                    $this->attendanceService->syncClientForDate($client, $date);
                    $this->info("Synchronisation terminée pour le client: {$client->name}");
                } else {
                    $this->error("Client ID {$clientId} non trouvé");
                }
            } else {
                $this->attendanceService->syncForDate($date);
                $this->info("Synchronisation terminée pour tous les clients");
            }
        } else {
            if ($clientId) {
                $client = \App\Models\Client::find($clientId);
                if ($client) {
                    $this->attendanceService->syncClientAttendances($client, $daysBack);
                    $this->info("Synchronisation terminée pour le client: {$client->name}");
                } else {
                    $this->error("Client ID {$clientId} non trouvé");
                }
            } else {
                // Utilisez la méthode syncAllClients
                $this->info("Appel de syncAllClients()...");
                \Log::info("Appel de syncAllClients()");
                $this->attendanceService->syncAllClients();
                $this->info("Synchronisation terminée pour tous les clients");
            }
        }
        
        $this->info('Synchronisation complétée avec succès!');
        
    } catch (\Exception $e) {
        $this->error('Erreur: ' . $e->getMessage());
        \Log::error('Erreur dans attendance:sync: ' . $e->getMessage());
        \Log::error($e->getTraceAsString());
        return 1;
    }
    
    return 0;
}
}
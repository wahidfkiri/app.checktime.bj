<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Zone;

class SyncZonesBackground extends Command
{
    protected $signature = 'app:sync-zones-bg {--client=}';
    protected $description = 'Synchroniser les zones en arrière-plan';
    
    public function handle()
    {
        Log::info('Démarrage sync zones background');
        
        try {
            $query = DB::table('access_configs');
            
            if ($this->option('client')) {
                $query->where('client_id', $this->option('client'));
            }
            
            $configs = $query->get();
            $total = 0;
            
            foreach ($configs as $config) {
                $this->info("Syncing client {$config->client_id}");
                
                $response = Http::withHeaders([
                    "Authorization" => "Token " . $config->general_token,
                    "Accept" => "application/json"
                ])
                ->timeout(45)
                ->get(config('services.checktime.base_url') . '/personnel/api/areas/', [
                    'limit' => 500
                ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['data']) && is_array($data['data'])) {
                        $batchData = [];
                        $now = now();
                        
                        foreach ($data['data'] as $zone) {
                            if (empty($zone['area_code']) || empty($zone['area_name'])) {
                                continue;
                            }
                            
                            $batchData[] = [
                                'code' => $zone['area_code'],
                                'client_id' => $config->client_id,
                                'name' => $zone['area_name'],
                                'metadata' => json_encode($zone),
                                'created_at' => $now,
                                'updated_at' => $now
                            ];
                            
                            $total++;
                        }
                        
                        if (!empty($batchData)) {
                            Zone::upsert(
                                $batchData,
                                ['code', 'client_id'],
                                ['name', 'metadata', 'updated_at']
                            );
                        }
                    }
                }
            }
            
            // Mettre à jour le cache
            Cache::put('zones_last_sync', time(), now()->addHours(2));
            Cache::forget('zones_syncing');
            
            Log::info("Sync terminé: {$total} zones");
            $this->info("Terminé: {$total} zones synchronisées");
            
        } catch (\Exception $e) {
            Log::error('Sync error: ' . $e->getMessage());
            Cache::forget('zones_syncing');
            $this->error('Erreur: ' . $e->getMessage());
        }
    }
}
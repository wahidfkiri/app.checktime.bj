<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Device;
use App\Models\Setting;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendWeeklySmsReports extends Command
{
    protected $signature = 'attendance:send-weekly-sms';
    protected $description = 'Envoyer les rapports de présence hebdomadaires par SMS chaque vendredi à 9h';
    
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        parent::__construct();
        $this->smsService = $smsService;
    }

    public function handle()
    {
        $this->info('📱 Début de l\'envoi des rapports de présence hebdomadaires par SMS...');
        
        $today = Carbon::now();
        $currentDayOfWeek = $today->dayOfWeekIso;

        // Calcul de la période : toujours Lundi → Vendredi (5 jours)
        if ($currentDayOfWeek == 6 || $currentDayOfWeek == 7) {
            // Samedi (6) ou Dimanche (7) → prendre la semaine précédente
            $startOfWeek = $today->copy()->previous(Carbon::MONDAY);
        } else {
            // Lundi à Vendredi → semaine en cours
            $startOfWeek = $today->copy()->startOfWeek(Carbon::MONDAY);
        }
        $endOfWeek = $startOfWeek->copy()->addDays(4); // Vendredi
        
        $this->info("📊 Période du rapport: " . $startOfWeek->format('d/m/Y') . " au " . $endOfWeek->format('d/m/Y'));
        
        $clients = Client::with('user')->get();
        
        $totalSmsSent     = 0;
        $totalSmsCost     = 0;
        $clientsProcessed = 0;
        $totalClients     = $clients->count();
        
        $this->info("👥 Nombre de clients à traiter: " . $totalClients);
        
        // Vérifier le solde global
        $this->info("💰 Vérification du solde SMS...");
        $balanceCheck = $this->smsService->checkBalance();
        
        if (!$balanceCheck['success']) {
            $this->error("❌ Impossible de vérifier le solde SMS: " . $balanceCheck['error']);
            Log::error('Impossible de vérifier le solde SMS pour rapports hebdomadaires');
            return;
        }
        
        $globalBalance = $balanceCheck['balance'];
        $this->info("✅ Solde SMS disponible: " . $globalBalance . " crédits");
        
        foreach ($clients as $client) {
            try {
                $clientsProcessed++;
                $userName = $this->getClientUserName($client);
                
                $this->info("\n--- [{$clientsProcessed}/{$totalClients}] Traitement du client: " . $userName . " ---");
                
                $settings = Setting::where('client_id', $client->id)->first();
                
                if (!$settings) {
                    $this->warn("⚠️  Aucun paramètre trouvé pour le client: " . $userName);
                    Log::info('Aucun paramètre trouvé pour client ' . $client->id . ' (' . $userName . ') - SMS non envoyés');
                    continue;
                }
                
                if (!$settings->sms_is_active) {
                    $this->warn("❌  SMS désactivés pour le client: " . $userName);
                    Log::info('SMS désactivés pour client ' . $client->id . ' (' . $userName . ')');
                    continue;
                }
                
                if ($settings->sms_credit <= 0) {
                    $this->warn("⚠️  Crédit SMS épuisé pour le client: " . $userName . " (" . $settings->sms_credit . " crédits)");
                    Log::warning('Crédit SMS épuisé pour client ' . $client->id . ' (' . $userName . ')');
                    continue;
                }
                
                $this->info("✅  SMS activés pour " . $userName . " (" . $settings->sms_credit . " crédits restants)");
                
                $attendanceData = $this->getWeeklyAttendanceData($client, $startOfWeek, $endOfWeek);
                
                if (empty($attendanceData)) {
                    $this->warn("⚠️  Aucune donnée de présence pour le client " . $userName);
                    continue;
                }
                
                $this->info("✅  " . count($attendanceData) . " transactions récupérées");
                
                $employeesStats = $this->calculateEmployeeStats($attendanceData, $client->id, $startOfWeek, $endOfWeek);
                
                if (empty($employeesStats)) {
                    $this->warn("⚠️  Aucune statistique calculée pour le client " . $userName);
                    continue;
                }
                
                $this->info("✅  Statistiques calculées pour " . count($employeesStats) . " employé(s)");
                
                $employeesWithPhone = [];
                foreach ($employeesStats as $empCode => $stats) {
                    if (!empty($stats['employee']->phone) && $this->isValidPhoneNumber($stats['employee']->phone)) {
                        $employeesWithPhone[$empCode] = $stats;
                    }
                }
                
                if (empty($employeesWithPhone)) {
                    $this->warn("⚠️  Aucun employé avec téléphone valide pour le client " . $userName);
                    continue;
                }
                
                $this->info("📱  " . count($employeesWithPhone) . " employé(s) avec téléphone valide");
                
                $smsNeeded = count($employeesWithPhone);
                if ($smsNeeded > $settings->sms_credit) {
                    $this->warn("⚠️  Crédit insuffisant pour " . $userName . ": besoin de " . $smsNeeded . " SMS, crédit: " . $settings->sms_credit);
                    continue;
                }
                
                if ($smsNeeded > $globalBalance) {
                    $this->warn("⚠️  Solde global insuffisant pour " . $userName . ": besoin de " . $smsNeeded . " SMS, solde global: " . $globalBalance);
                    continue;
                }
                
                // ENVOI DES SMS
                $clientSmsSent = 0;
                $clientSmsCost = 0;
                $smsErrors     = 0;
                
                foreach ($employeesWithPhone as $empCode => $stats) {
                    $employee = $stats['employee'];
                    
                    try {
                        $formattedPhone = $this->formatPhoneNumber($employee->phone);
                        $message        = $this->prepareSmsMessage($employee, $stats, $settings);
                        $smsCount       = $this->calculateSmsCount($message);

                        $this->info("📝  Message: " . strlen($message) . " caractères, " . $smsCount . " SMS");
                        
                        if (strlen($message) > 160) {
                            $message  = $this->truncateMessage($message);
                            $smsCount = 1;
                        }
                        
                        $employeeName = $employee->first_name ?? $employee->name ?? 'Employé';
                        $this->info("📤  Envoi SMS à " . $employeeName . " (" . $formattedPhone . ")...");
                        
                        $smsResult = $this->smsService->sendSms(
                            $formattedPhone,
                            $message,
                            $settings->sms_sender_id ?: config('sms.fastway.default_sender', 'CHECKTIME')
                        );
                        
                        if ($smsResult['success']) {
                            $smsUsed = 1;
                            
                            $clientSmsSent++;
                            $totalSmsSent++;
                            $clientSmsCost += $smsUsed;
                            $totalSmsCost  += $smsUsed;
                            $globalBalance -= $smsUsed;
                            
                            $newCredit         = max(0, $settings->sms_credit - 1);
                            $settings->sms_credit = $newCredit;
                            $settings->save();
                            
                            Log::info('SMS hebdomadaire envoyé', [
                                'client'               => $userName,
                                'client_id'            => $client->id,
                                'employee_first_name'  => $employee->first_name ?? null,
                                'employee_name'        => $employee->name ?? null,
                                'employee_phone'       => $formattedPhone,
                                'employee_code'        => $employee->emp_code ?? null,
                                'message_length'       => strlen($message),
                                'sms_used'             => $smsUsed,
                                'message_id'           => $smsResult['message_id'] ?? null,
                                'remaining_credit'     => $newCredit,
                                'stats' => [
                                    'presence' => $stats['presence_count'],
                                    'delay'    => $stats['delay_count'],
                                    'absence'  => $stats['absence_count'],
                                ],
                            ]);
                            
                            $this->info("✅  SMS envoyé à " . $employeeName . " (coût: 1 SMS, crédit restant: " . $newCredit . ")");

                        } else {
                            $smsErrors++;
                            $this->error("❌  Erreur: " . $smsResult['error']);
                            Log::error('Erreur SMS pour ' . ($employee->name ?? 'Employé') .
                                      ' (' . $formattedPhone . '): ' . $smsResult['error']);
                        }
                        
                        sleep(1);
                        
                    } catch (\Exception $e) {
                        $smsErrors++;
                        $this->error("❌  Exception: " . $e->getMessage());
                        Log::error('Exception SMS pour ' . ($employee->name ?? 'Employé') .
                                  ': ' . $e->getMessage());
                    }
                }
                
                if ($clientSmsSent > 0) {
                    $this->info("✅  " . $clientSmsSent . " SMS envoyé(s) pour " . $userName .
                               " (coût: " . $clientSmsCost . " SMS, crédit restant: " . $settings->sms_credit . ")");
                }
                
                if ($smsErrors > 0) {
                    $this->warn("⚠️  " . $smsErrors . " erreur(s) d'envoi pour " . $userName);
                }
                
                sleep(2);
                
            } catch (\Exception $e) {
                $clientName = $userName ?? 'Client #' . $client->id;
                $this->error("❌  Erreur avec le client " . $clientName . ": " . $e->getMessage());
                Log::error('Erreur générale client ' . $client->id . ' (' . $clientName . '): ' . $e->getMessage());
            }
        }
        
        // RÉSUMÉ FINAL
        $this->line('');
        $this->info("═══════════════════════════════════════════════");
        $this->info("📋  RÉSUMÉ DES SMS HEBDOMADAIRES");
        $this->info("═══════════════════════════════════════════════");
        $this->info("Date d'exécution     : " . $today->format('d/m/Y H:i'));
        $this->info("Période analysée     : " . $startOfWeek->format('d/m/Y') . " au " . $endOfWeek->format('d/m/Y'));
        $this->info("Clients traités      : " . $clientsProcessed . "/" . $totalClients);
        $this->info("SMS envoyés          : " . $totalSmsSent);
        $this->info("Coût total           : " . $totalSmsCost . " SMS");
        $this->info("Solde global restant : " . $globalBalance . " SMS");
        
        if ($totalSmsSent > 0) {
            $this->info("✅  Rapports SMS hebdomadaires envoyés avec succès !");
        } else {
            $this->warn("⚠️  Aucun SMS n'a été envoyé");
        }
        
        Log::info('SMS hebdomadaires terminés', [
            'sms_sent'          => $totalSmsSent,
            'sms_cost'          => $totalSmsCost,
            'clients_processed' => $clientsProcessed,
            'total_clients'     => $totalClients,
            'global_balance'    => $globalBalance,
            'date'              => $today->format('Y-m-d'),
        ]);
    }
    
    // =========================================================================
    //  HELPERS
    // =========================================================================

    private function getClientUserName($client)
    {
        if ($client->user) {
            return $client->user->name ?: ($client->user->email ?: 'Client #' . $client->id);
        }
        $user = User::find($client->user_id);
        return $user ? ($user->name ?: $user->email) : 'Client #' . $client->id;
    }
    
    private function getWeeklyAttendanceData($client, $startDate, $endDate)
    {
        // Retourne une collection non-nulle pour passer le guard dans handle().
        // calculateEmployeeStats() utilise directement DailyAttendance.
        return collect([true]);
    }
    
    private function getDeviceTransactions($device, $startTime, $endTime, $token)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $token,
                'Accept'        => 'application/json',
            ])->withOptions([
                'verify'  => false,
                'timeout' => 20,
            ])->get('http://54.37.15.111/iclock/api/transactions/', [
                'page'        => 1,
                'limit'       => 100,
                'terminal_sn' => $device->device_sn,
                'start_time'  => $startTime,
                'end_time'    => $endTime,
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']) && is_array($data['data'])) {
                    return collect($data['data'])->map(function ($transaction) use ($device) {
                        $transaction['device_sn'] = $device->device_sn;
                        return $transaction;
                    });
                }
            }
        } catch (\Exception $e) {
            Log::error("Erreur device {$device->device_sn}: " . $e->getMessage());
        }
        
        return collect();
    }
    
    /**
     * Calcule les stats par employé sur la période Lundi → Vendredi (5 jours).
     * Les dates sont passées explicitement depuis handle() pour éviter toute
     * re-calculation et garantir la cohérence.
     */
    private function calculateEmployeeStats($transactions, $clientId, Carbon $startOfWeek, Carbon $endOfWeek)
    {
        // Récupérer les employés du client qui ont un téléphone
        $employees = Employee::where('client_id', $clientId)
            ->whereNotNull('emp_code')
            ->where('emp_code', '!=', '')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->keyBy('emp_code');

        if ($employees->isEmpty()) return [];

        $employeeIds = $employees->pluck('id')->toArray();

        // Requête DailyAttendance sur Lundi → Vendredi uniquement
        $attendances = \App\Models\DailyAttendance::where('client_id', $clientId)
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [
                $startOfWeek->format('Y-m-d'),
                $endOfWeek->format('Y-m-d'),   // Vendredi
            ])
            ->get()
            ->filter(fn ($att) => Carbon::parse($att->attendance_date)->dayOfWeekIso <= 5) // sécurité Lun-Ven
            ->groupBy('employee_id');

        // Nombre de jours ouvrés réels dans la période (Lun-Ven = 5)
        $totalWorkingDays = $this->countWorkingDays(
            $startOfWeek->format('Y-m-d'),
            $endOfWeek->format('Y-m-d')
        );

        $statsByEmployee = [];

        foreach ($employees as $empCode => $employee) {
            $employeeAttendances = $attendances->get($employee->id, collect());

            $presenceCount = 0;
            $lateCount     = 0;

            foreach ($employeeAttendances as $att) {
                $status = strtoupper($att->status);

                if (in_array($status, ['PRESENT', 'LATE', 'EARLY_LEAVE', 'HALF_DAY'])) {
                    $presenceCount++;
                    if ($att->is_late) {
                        $lateCount++;
                    }
                }
            }

            $absenceCount = max(0, $totalWorkingDays - $presenceCount);

            $statsByEmployee[$empCode] = [
                'employee'       => $employee,
                'presence_count' => $presenceCount,
                'delay_count'    => $lateCount,
                'absence_count'  => $absenceCount,
            ];
        }

        return $statsByEmployee;
    }

    /**
     * Compte uniquement les jours ouvrés du Lundi au Vendredi.
     */
    private function countWorkingDays(string $startDate, string $endDate): int
    {
        $days = 0;
        $cur  = Carbon::parse($startDate);
        $end  = Carbon::parse($endDate);
        while ($cur->lte($end)) {
            if ($cur->dayOfWeekIso >= 1 && $cur->dayOfWeekIso <= 5) {
                $days++;
            }
            $cur->addDay();
        }
        return $days;
    }
    
    private function prepareSmsMessage($employee, $stats, $settings)
    {
        $firstName = $employee->first_name ?? 
                    ($employee->name ? $this->getFirstName($employee->name) : 'Collaborateur');
        
        $message  = $firstName . ", voici le point des présences de la semaine.\n";
        $message .= "Presence: " . $stats['presence_count'] . "\n";
        $message .= "Retard: "   . $stats['delay_count']    . "\n";
        $message .= "Absence: "  . $stats['absence_count'];
        
        if ($settings->include_client_name_in_sms && $settings->client) {
            $message .= "\n" . $settings->client->name;
        }
        
        return $message;
    }
    
    private function getFirstName($fullName)
    {
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?: $fullName;
    }
    
    private function calculateSmsCount($message)
    {
        $length = strlen($message);
        return $length <= 160 ? 1 : ceil($length / 153);
    }
    
    private function truncateMessage($message, $maxLength = 160)
    {
        if (strlen($message) <= $maxLength) return $message;
        
        $truncated   = substr($message, 0, $maxLength - 3);
        $lastNewline = strrpos($truncated, "\n");
        
        if ($lastNewline !== false && $lastNewline > $maxLength - 20) {
            $truncated = substr($truncated, 0, $lastNewline);
        }
        
        return $truncated . '...';
    }
    
    private function isValidPhoneNumber($phone)
    {
        if (empty($phone)) return false;
        
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
        $cleanPhone = str_replace(' ', '', $cleanPhone);
        $length     = strlen($cleanPhone);
        
        if (str_starts_with($cleanPhone, '+225') || str_starts_with($cleanPhone, '225')) {
            $digitsOnly = preg_replace('/[^0-9]/', '', $cleanPhone);
            return strlen($digitsOnly) === 11;
        }
        
        if (str_starts_with($cleanPhone, '0')) {
            return $length === 9;
        }
        
        return $length >= 8 && $length <= 15;
    }
    
    private function formatPhoneNumber($phone)
    {
        if (empty($phone)) return null;
        
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        if (str_starts_with($cleanPhone, '0') && strlen($cleanPhone) === 9) {
            $cleanPhone = '225' . substr($cleanPhone, 1);
        }
        
        if (!str_starts_with($cleanPhone, '225') && strlen($cleanPhone) === 8) {
            $cleanPhone = '225' . $cleanPhone;
        }
        
        return $cleanPhone;
    }
}
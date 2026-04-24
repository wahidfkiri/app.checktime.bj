<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Employee;
use App\Models\DailyAttendance;
use App\Models\Mission;
use App\Models\Leave;
use App\Models\Setting;
use App\Models\User;
use App\Mail\WeeklyAttendanceReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendWeeklyAttendanceReports extends Command
{
    protected $signature = 'attendance:send-weekly-reports';

    protected $description = 'Envoyer les rapports de présence hebdomadaires aux employés chaque vendredi à 9h';

    public function handle()
    {
        $this->info('🚀 Début de l\'envoi des rapports de présence hebdomadaires...');

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

        $startDate = $startOfWeek->toDateString();
        $endDate   = $endOfWeek->toDateString();

        $this->info("📊 Période du rapport: {$startOfWeek->format('d/m/Y')} au {$endOfWeek->format('d/m/Y')}");
        $this->info("📆 Jours ouvrés (Lun-Ven): 5 jours");

        // ── Jours ouvrés + liste pour la vue (Lundi → Vendredi uniquement) ──
        $workingDays = $this->countWorkingDays($startDate, $endDate);

        $daysList = [];
        $cur      = Carbon::parse($startDate);
        while ($cur->lte(Carbon::parse($endDate))) {
            if ($cur->dayOfWeekIso <= 5) { // Lundi à Vendredi uniquement
                $daysList[] = [
                    'date'     => $cur->copy(),
                    'date_str' => $cur->format('Y-m-d'),
                    'day_name' => $this->getDayNameFrench($cur->dayOfWeekIso),
                ];
            }
            $cur->addDay();
        }

        // ── Clients actifs ──────────────────────────────────────────
        $clients = Client::with('user')
          //  ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->get();

        $totalEmailsSent  = 0;
        $clientsProcessed = 0;
        $totalClients     = $clients->count();

        $this->info("👥 Nombre de clients à traiter: {$totalClients}");

        foreach ($clients as $client) {
            try {
                $clientsProcessed++;
                $userName = $this->getClientUserName($client);

                $this->info("\n--- [{$clientsProcessed}/{$totalClients}] Client: {$userName} ---");

                // ── Paramètres ──────────────────────────────────────
                $settings = Setting::where('client_id', $client->id)->first();
                if (!$settings) {
                    $this->warn("⚠️  Aucun paramètre pour: {$userName}");
                    Log::info("Aucun paramètre pour client {$client->id} ({$userName})");
                    continue;
                }

                if (!$settings->email_employees_is_active) {
                    $this->warn("❌  Emails désactivés pour: {$userName}");
                    Log::info("Emails désactivés pour client {$client->id} ({$userName})");
                    continue;
                }

                $this->info("✅  Emails activés pour {$userName}");

                // ── Données du client pour la semaine ───────────────
                $allAttendances = DailyAttendance::where('client_id', $client->id)
                    ->whereBetween('attendance_date', [$startDate, $endDate])
                    ->get();

                $allMissions = Mission::where('client_id', $client->id)
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate])
                          ->orWhere(fn ($q2) => $q2->where('start_date', '<=', $startDate)
                                                    ->where('end_date', '>=', $endDate));
                    })->get();

                $allLeaves = Leave::with('type')
                    ->where('client_id', $client->id)
                    ->where('status', 'approved')
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate])
                          ->orWhere(fn ($q2) => $q2->where('start_date', '<=', $startDate)
                                                    ->where('end_date', '>=', $endDate));
                    })->get();

                // Index par employee_id
                $attendanceByEmployee = [];
                foreach ($allAttendances as $att) {
                    $attendanceByEmployee[$att->employee_id][] = $att;
                }

                $missionsByEmployee = [];
                foreach ($allMissions as $mission) {
                    $missionsByEmployee[$mission->employee_id][] = $mission;
                }

                $leavesByEmployee = [];
                foreach ($allLeaves as $leave) {
                    $leavesByEmployee[$leave->employee_id][] = $leave;
                }

                // ── Employés avec email valide ──────────────────────
                $employees = Employee::where('client_id', $client->id)
                    ->whereNotNull('emp_code')->where('emp_code', '!=', '')
                    ->whereNotNull('email')->where('email', '!=', '')
                    ->orderBy('dept_name')->orderBy('first_name')
                    ->get();

                if ($employees->isEmpty()) {
                    $this->warn("⚠️  Aucun employé avec email pour {$userName}");
                    continue;
                }

                $this->info("📧  {$employees->count()} employé(s) avec email valide");

                $clientEmailsSent = 0;
                $emailErrors      = 0;

                foreach ($employees as $employee) {

                    if (!filter_var($employee->email, FILTER_VALIDATE_EMAIL)) {
                        Log::warning("Email invalide ignoré: {$employee->email} (Code: {$employee->emp_code})");
                        continue;
                    }

                    try {
                        $employeeAttendances = $attendanceByEmployee[$employee->id] ?? [];
                        $employeeMissions    = $missionsByEmployee[$employee->id]   ?? [];
                        $employeeLeaves      = $leavesByEmployee[$employee->id]     ?? [];

                        // ── Dates de mission (Lun-Ven uniquement) ────
                        $missionDates = [];
                        foreach ($employeeMissions as $mission) {
                            $cur = Carbon::parse($mission->start_date);
                            $end = Carbon::parse($mission->end_date);
                            while ($cur->lte($end)) {
                                if ($cur->dayOfWeekIso <= 5) {
                                    $missionDates[$cur->format('Y-m-d')] = [
                                        'title'       => $mission->title,
                                        'destination' => $mission->destination,
                                    ];
                                }
                                $cur->addDay();
                            }
                        }

                        // ── Dates de congé (Lun-Ven uniquement) ─────
                        $leaveDates = [];
                        foreach ($employeeLeaves as $leave) {
                            $cur      = Carbon::parse($leave->start_date);
                            $end      = Carbon::parse($leave->end_date);
                            $typeName = $leave->type ? $leave->type->name : 'Congé';
                            while ($cur->lte($end)) {
                                if ($cur->dayOfWeekIso <= 5) {
                                    $leaveDates[$cur->format('Y-m-d')] = ['type_name' => $typeName];
                                }
                                $cur->addDay();
                            }
                        }

                        // ── Boucle journalière (Lun-Ven uniquement) ──
                        $dailyChecks = [];
                        $cur         = Carbon::parse($startDate);
                        $endObj      = Carbon::parse($endDate);

                        while ($cur->lte($endObj)) {
                            $dateStr = $cur->format('Y-m-d');

                            // Ignorer Samedi et Dimanche
                            if ($cur->dayOfWeekIso > 5) {
                                $cur->addDay();
                                continue;
                            }

                            // Trouver l'attendance du jour
                            $attendance = null;
                            foreach ($employeeAttendances as $att) {
                                $attDate = $att->attendance_date instanceof Carbon
                                    ? $att->attendance_date->format('Y-m-d')
                                    : date('Y-m-d', strtotime($att->attendance_date));
                                if ($attDate === $dateStr) {
                                    $attendance = $att;
                                    break;
                                }
                            }

                            $isMission = isset($missionDates[$dateStr]);
                            $isLeave   = isset($leaveDates[$dateStr]);

                            if ($isMission) {
                                $dailyChecks[$dateStr] = [
                                    'check_in'       => null,
                                    'check_out'      => null,
                                    'status'         => 'MISSION',
                                    'is_late'        => false,
                                    'late_minutes'   => 0,
                                    'is_early_leave' => false,
                                    'is_mission'     => true,
                                    'mission_info'   => $missionDates[$dateStr],
                                    'is_leave'       => false,
                                    'leave_info'     => null,
                                ];

                            } elseif ($isLeave) {
                                $dailyChecks[$dateStr] = [
                                    'check_in'       => null,
                                    'check_out'      => null,
                                    'status'         => 'CONGE',
                                    'is_late'        => false,
                                    'late_minutes'   => 0,
                                    'is_early_leave' => false,
                                    'is_mission'     => false,
                                    'mission_info'   => null,
                                    'is_leave'       => true,
                                    'leave_info'     => $leaveDates[$dateStr],
                                ];

                            } elseif ($attendance && strtoupper($attendance->status) !== 'ABSENT') {
                                $checkIn = null;
                                if ($attendance->check_in) {
                                    $checkIn = $attendance->check_in instanceof Carbon
                                        ? $attendance->check_in->format('H:i')
                                        : substr($attendance->check_in, 11, 5);
                                }
                                $checkOut = null;
                                if ($attendance->check_out) {
                                    $checkOut = $attendance->check_out instanceof Carbon
                                        ? $attendance->check_out->format('H:i')
                                        : substr($attendance->check_out, 11, 5);
                                }

                                $dailyChecks[$dateStr] = [
                                    'check_in'       => $checkIn,
                                    'check_out'      => $checkOut,
                                    'status'         => $attendance->status,
                                    'is_late'        => (bool) $attendance->is_late,
                                    'late_minutes'   => (int) ($attendance->late_minutes ?? 0),
                                    'is_early_leave' => (bool) $attendance->is_early_leave,
                                    'is_mission'     => false,
                                    'mission_info'   => null,
                                    'is_leave'       => false,
                                    'leave_info'     => null,
                                ];

                            } else {
                                $dailyChecks[$dateStr] = null; // Absent
                            }

                            $cur->addDay();
                        }

                        // ── Stats employé (Lun-Ven uniquement) ───────
                        $totalPresent    = 0;
                        $totalLate       = 0;
                        $totalEarlyLeave = 0;
                        $totalHalfDay    = 0;
                        $totalMission    = 0;
                        $totalLeave      = 0;

                        foreach ($employeeAttendances as $att) {
                            // Ignorer les éventuels pointages du week-end
                            if (Carbon::parse($att->attendance_date)->dayOfWeekIso > 5) continue;

                            $status = strtoupper($att->status);
                            if ($status !== 'ABSENT') {
                                $totalPresent++;
                                if ($status === 'LATE')        $totalLate++;
                                if ($status === 'EARLY_LEAVE') $totalEarlyLeave++;
                                if ($status === 'HALF_DAY')    $totalHalfDay++;
                            }
                        }

                        // Missions déjà filtrées Lun-Ven en amont
                        foreach ($missionDates as $dateStr => $m) {
                            $totalMission++;
                        }

                        // Congés déjà filtrés Lun-Ven en amont
                        foreach ($leaveDates as $dateStr => $l) {
                            if (!isset($missionDates[$dateStr])) {
                                $totalLeave++;
                            }
                        }

                        $totalPresent   += $totalMission + $totalLeave;
                        $totalAbsent     = max(0, $workingDays - $totalPresent);
                        $presenceRate    = $workingDays > 0
                            ? round(($totalPresent / $workingDays) * 100, 1) : 0;
                        $ponctualiteRate = $totalPresent > 0
                            ? round((($totalPresent - $totalLate - $totalEarlyLeave) / $totalPresent) * 100, 1) : 0;

                        // ── Observations ──────────────────────────────
                        $observations = [];
                        foreach ($employeeAttendances as $att) {
                            // Ignorer le week-end
                            if (Carbon::parse($att->attendance_date)->dayOfWeekIso > 5) continue;

                            $status = strtoupper($att->status);
                            $date   = Carbon::parse($att->attendance_date)->format('d/m');
                            if ($status === 'HALF_DAY')         $observations[] = 'Demi-journée le ' . $date;
                            elseif ($status === 'LATE')         $observations[] = 'Retard ' . ($att->late_minutes ?? 0) . ' min le ' . $date;
                            elseif ($status === 'EARLY_LEAVE')  $observations[] = 'Départ anticipé le ' . $date;
                            elseif ($status === 'ABSENT')       $observations[] = 'Absent le ' . $date;
                        }
                        foreach ($missionDates as $dateStr => $m) {
                            $observations[] = 'Mission: ' . $m['title'] . ' (' . $m['destination'] . ') le ' . Carbon::parse($dateStr)->format('d/m');
                        }
                        foreach ($leaveDates as $dateStr => $l) {
                            if (!isset($missionDates[$dateStr])) {
                                $observations[] = $l['type_name'] . ' le ' . Carbon::parse($dateStr)->format('d/m');
                            }
                        }

                        // ── Données formatées pour le Mailable ────────
                        $employeeData = [
                            'employee_code' => $employee->emp_code,
                            'employee_name' => trim($employee->first_name . ' ' . $employee->last_name),
                            'daily_checks'  => $dailyChecks,
                            'stats'         => [
                                'present'          => $totalPresent,
                                'absent'           => $totalAbsent,
                                'late'             => $totalLate,
                                'early_leave'      => $totalEarlyLeave,
                                'half_day'         => $totalHalfDay,
                                'mission'          => $totalMission,
                                'leave'            => $totalLeave,
                                'presence_rate'    => $presenceRate,
                                'ponctualite_rate' => $ponctualiteRate,
                            ],
                            'observations' => !empty($observations)
                                ? implode(', ', array_slice($observations, 0, 5))
                                : 'Aucune observation',
                        ];

                        $emailData = [
                            'employee'      => $employee,
                            'employee_data' => $employeeData,
                            'client'        => $client,
                            'client_name'   => $userName,
                            'start_date'    => $startOfWeek->format('d/m/Y'),
                            'end_date'      => $endOfWeek->format('d/m/Y'),
                            'week_range'    => $startOfWeek->format('d/m/Y') . ' au ' . $endOfWeek->format('d/m/Y'),
                            'days_list'     => $daysList,
                            'working_days'  => $workingDays,
                            'export_date'   => Carbon::now(),
                        ];

                        Mail::to($employee->email)->send(new WeeklyAttendanceReport($emailData));

                        $clientEmailsSent++;
                        $totalEmailsSent++;

                        Log::info("Email envoyé à {$employee->email} (Code: {$employee->emp_code}) - Client: {$client->id} ({$userName})");

                        if ($clientEmailsSent % 5 === 0) sleep(1);

                    } catch (\Exception $e) {
                        $emailErrors++;
                        Log::error("Erreur envoi email à {$employee->email} (Code: {$employee->emp_code}): " . $e->getMessage());
                        $this->warn("   ❌  Erreur pour {$employee->emp_code}: " . $e->getMessage());
                    }
                }

                if ($clientEmailsSent > 0) {
                    $this->info("✅  {$clientEmailsSent} email(s) envoyé(s) pour {$userName}");
                    if ($emailErrors > 0) $this->warn("⚠️  {$emailErrors} erreur(s) pour {$userName}");
                } else {
                    $this->warn("⚠️  Aucun email envoyé pour {$userName}");
                }

                if ($clientsProcessed < $totalClients) sleep(3);

            } catch (\Exception $e) {
                $clientName = $userName ?? 'Client #' . $client->id;
                $this->error("❌  Erreur avec le client {$clientName}: " . $e->getMessage());
                Log::error("Erreur générale client {$client->id} ({$clientName}): " . $e->getMessage());
                Log::error('Trace: ' . $e->getTraceAsString());
            }
        }

        // ── RÉSUMÉ FINAL ────────────────────────────────────────────
        $this->line('');
        $this->info("═══════════════════════════════════════════");
        $this->info("📋  RÉSUMÉ DE L'EXÉCUTION HEBDOMADAIRE");
        $this->info("═══════════════════════════════════════════");
        $this->info("Date d'exécution : " . $today->format('d/m/Y H:i'));
        $this->info("Période analysée : {$startOfWeek->format('d/m/Y')} au {$endOfWeek->format('d/m/Y')}");
        $this->info("Clients traités  : {$clientsProcessed}/{$totalClients}");
        $this->info("Emails envoyés   : {$totalEmailsSent}");

        if ($totalEmailsSent > 0) {
            $this->info("✅  Rapports hebdomadaires envoyés avec succès !");
            $this->info("⏰  Prochaine exécution: Vendredi prochain à 9h");
        } else {
            $this->warn("⚠️  Aucun email n'a été envoyé");
        }

        Log::info("Rapports hebdomadaires terminés: {$totalEmailsSent} emails, {$totalClients} clients, Date: " . $today->format('Y-m-d'));
    }

    // =========================================================================
    //  HELPERS
    // =========================================================================

    private function getClientUserName($client): string
    {
        if ($client->user) {
            return $client->user->name ?: ($client->user->email ?: 'Client #' . $client->id);
        }
        $user = User::find($client->user_id);
        return $user
            ? ($user->name ?: ($user->email ?: 'Client #' . $client->id))
            : 'Client #' . $client->id;
    }

    private function getDayNameFrench(int $dayOfWeekIso): string
    {
        return [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
        ][$dayOfWeekIso] ?? '';
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
}
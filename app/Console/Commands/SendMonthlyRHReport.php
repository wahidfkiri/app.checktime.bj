<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Employee;
use App\Models\DailyAttendance;
use App\Models\Mission;
use App\Models\Leave;
use App\Models\Setting;
use App\Models\User;
use App\Mail\MonthlyRHReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class SendMonthlyRHReport extends Command
{
    protected $signature = 'reports:send-monthly-rh {--test : Mode test} {--date= : Date spécifique}';
    protected $description = 'Envoyer les rapports mensuels RH par email le dernier jour du mois';

    public function handle()
    {
        $this->info('🚀 Début de l\'envoi des rapports mensuels RH...');

        $today        = Carbon::create(2025, 12, 31, 9, 0, 0);
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth   = $today->copy()->endOfMonth();

        $this->info("📊 Période du rapport: " . $startOfMonth->format('d/m/Y') . " au " . $endOfMonth->format('d/m/Y'));

        $clients = Client::with('user')->get();

        if ($clients->isEmpty()) {
            $this->warn('❌ Aucun client trouvé.');
            return;
        }

        $this->info('👥 Nombre de clients à traiter: ' . $clients->count());

        $totalEmailsSent  = 0;
        $clientsProcessed = 0;

        foreach ($clients as $client) {
            try {
                $clientsProcessed++;
                $userName = $this->getClientUserName($client);

                $this->info("[{$clientsProcessed}/{$clients->count()}] Traitement du client: " . $userName);

                // ── Vérifications settings ─────────────────────────
                $settings = Setting::where('client_id', $client->id)->first();

                if (!$settings) {
                    $this->warn("⚠️  Aucun paramètre pour: " . $userName);
                    continue;
                }

                if (!$settings->email_is_active) {
                    $this->warn("❌  Emails désactivés pour: " . $userName);
                    continue;
                }

                if (empty($settings->email) || !filter_var(trim($settings->email), FILTER_VALIDATE_EMAIL)) {
                    $this->warn("⚠️  Email invalide ou absent pour: " . $userName);
                    continue;
                }

                $rhEmail = trim($settings->email);
                $this->info("✅  Config OK pour " . $userName . " (" . $rhEmail . ")");

                // ── Générer les données rapport (même logique exportCustomPdfByDept) ──
                $this->info("📊  Génération des données...");
                $reportData = $this->buildDepartmentReportData(
                    $client,
                    $startOfMonth->format('Y-m-d'),
                    $endOfMonth->format('Y-m-d')
                );

                if (empty($reportData['report_data'])) {
                    $this->warn("⚠️  Aucune donnée pour: " . $userName);
                    continue;
                }

                $this->info("✅  " . count($reportData['report_data']) . " département(s) analysé(s)");

                // ── Générer le PDF ─────────────────────────────────
                $this->info("🔄  Génération du PDF...");
                $pdfPath = $this->generatePdf($client, $userName, $reportData, $startOfMonth, $endOfMonth);

                if (!$pdfPath) {
                    $this->error("❌  Échec génération PDF pour: " . $userName);
                    continue;
                }

                $this->info("✅  PDF généré: " . basename($pdfPath));

                // ── Envoyer l'email ────────────────────────────────
                $this->info("📤  Envoi email à " . $rhEmail . "...");
                $this->sendReportEmail($client, $userName, $rhEmail, $reportData, $startOfMonth, $endOfMonth, $pdfPath);
                $totalEmailsSent++;

                $this->info("✅  Email envoyé à " . $rhEmail);

                $this->cleanupTempFile($pdfPath);

                if ($clientsProcessed < $clients->count()) {
                    sleep(2);
                }

            } catch (\Exception $e) {
                $clientName = $userName ?? 'Client #' . $client->id;
                $this->error("❌  Erreur avec " . $clientName . ": " . $e->getMessage());
                Log::error('Erreur client ' . $client->id . ': ' . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
        }

        $this->info("\n✅  Terminé! {$totalEmailsSent} email(s) envoyé(s) sur {$clients->count()} client(s)");
    }

    // ══════════════════════════════════════════════════════════════
    // CORE : même logique que exportCustomPdfByDept du controller
    // ══════════════════════════════════════════════════════════════

    /**
     * Construire les données du rapport par département
     * Logique identique à exportCustomPdfByDept dans CustomReportController
     */
    private function buildDepartmentReportData($client, $startDate, $endDate)
    {
        $workingDays = $this->countWorkingDays($startDate, $endDate);

        // ── Présences ──────────────────────────────────────────────
        $attendances = DailyAttendance::where('client_id', $client->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        // ── Missions ───────────────────────────────────────────────
        $missions = Mission::where('client_id', $client->id)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            })
            ->get();

        // ── Congés approuvés ───────────────────────────────────────
        $leaves = Leave::with('type')
            ->where('client_id', $client->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            })
            ->get();

        // ── Employés ───────────────────────────────────────────────
        $employees = Employee::where('client_id', $client->id)
            ->orderBy('dept_name')
            ->orderBy('first_name')
            ->get();

        // ── Index par employé ──────────────────────────────────────
        $attendanceByEmployee = [];
        foreach ($attendances as $att) {
            $attendanceByEmployee[$att->employee_id][] = $att;
        }

        $missionsByEmployee = [];
        foreach ($missions as $mission) {
            $missionsByEmployee[$mission->employee_id][] = $mission;
        }

        $leavesByEmployee = [];
        foreach ($leaves as $leave) {
            $leavesByEmployee[$leave->employee_id][] = $leave;
        }

        // ── Construction par département ───────────────────────────
        $departmentData = [];

        foreach ($employees as $employee) {
            $deptName = $employee->dept_name ?: 'Sans département';

            if (!isset($departmentData[$deptName])) {
                $departmentData[$deptName] = ['department_name' => $deptName, 'employees' => []];
            }

            $employeeAttendances = $attendanceByEmployee[$employee->id] ?? [];
            $employeeMissions    = $missionsByEmployee[$employee->id]   ?? [];
            $employeeLeaves      = $leavesByEmployee[$employee->id]     ?? [];

            // Dates de mission
            $missionDates = [];
            foreach ($employeeMissions as $mission) {
                $current = Carbon::parse($mission->start_date)->copy();
                $end     = Carbon::parse($mission->end_date);
                while ($current <= $end) {
                    $missionDates[$current->format('Y-m-d')] = [
                        'title'       => $mission->title,
                        'destination' => $mission->destination,
                    ];
                    $current->addDay();
                }
            }

            // Dates de congé
            $leaveDates = [];
            foreach ($employeeLeaves as $leave) {
                $current  = Carbon::parse($leave->start_date)->copy();
                $end      = Carbon::parse($leave->end_date);
                $typeName = $leave->type ? $leave->type->name : 'Congé';
                while ($current <= $end) {
                    $leaveDates[$current->format('Y-m-d')] = ['type_name' => $typeName];
                    $current->addDay();
                }
            }

            // Stats employé
            $totalPresent    = 0;
            $totalLate       = 0;
            $totalEarlyLeave = 0;
            $totalHalfDay    = 0;
            $totalMission    = 0;
            $totalLeave      = 0;

            foreach ($employeeAttendances as $att) {
                $status = strtoupper($att->status);
                if ($status !== 'ABSENT') {
                    $totalPresent++;
                    if ($status === 'LATE')        $totalLate++;
                    if ($status === 'EARLY_LEAVE') $totalEarlyLeave++;
                    if ($status === 'HALF_DAY')    $totalHalfDay++;
                }
            }

            foreach ($missionDates as $dateStr => $m) {
                if (Carbon::parse($dateStr)->dayOfWeekIso <= 5) $totalMission++;
            }

            foreach ($leaveDates as $dateStr => $l) {
                if (Carbon::parse($dateStr)->dayOfWeekIso <= 5 && !isset($missionDates[$dateStr])) {
                    $totalLeave++;
                }
            }

            $totalPresent    += $totalMission + $totalLeave;
            $totalAbsent      = $workingDays - $totalPresent;
            $presenceRate     = $workingDays > 0 ? round(($totalPresent / $workingDays) * 100, 1) : 0;
            $ponctualiteRate  = $totalPresent > 0
                ? round((($totalPresent - $totalLate - $totalEarlyLeave) / $totalPresent) * 100, 1) : 0;

            $departmentData[$deptName]['employees'][] = [
                'stats' => [
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
            ];
        }

        // ── Agrégation par département ─────────────────────────────
        $reportData = [];
        foreach ($departmentData as $deptName => $dept) {
            if (empty($dept['employees'])) continue;

            $totalEmployees       = count($dept['employees']);
            $totalPresent         = 0;
            $totalAbsent          = 0;
            $totalLate            = 0;
            $totalEarlyLeave      = 0;
            $totalHalfDay         = 0;
            $totalMission         = 0;
            $totalLeave           = 0;
            $totalOnTime          = 0;
            $totalPresenceRate    = 0;
            $totalPonctualiteRate = 0;

            foreach ($dept['employees'] as $emp) {
                $s = $emp['stats'];
                $totalPresent        += $s['present'];
                $totalAbsent         += $s['absent'];
                $totalLate           += $s['late'];
                $totalEarlyLeave     += $s['early_leave'];
                $totalHalfDay        += $s['half_day'];
                $totalMission        += $s['mission'];
                $totalLeave          += $s['leave'];
                $totalOnTime         += ($s['present'] - $s['late'] - $s['early_leave']);
                $totalPresenceRate   += $s['presence_rate'];
                $totalPonctualiteRate += $s['ponctualite_rate'];
            }

            $reportData[] = [
                'department_name'      => $deptName,
                'total_employees'      => $totalEmployees,
                'total_present'        => $totalPresent,
                'total_absent'         => $totalAbsent,
                'total_late'           => $totalLate,
                'total_early_leave'    => $totalEarlyLeave,
                'total_half_day'       => $totalHalfDay,
                'total_mission'        => $totalMission,
                'total_leave'          => $totalLeave,
                'total_on_time'        => $totalOnTime,
                'avg_presence_rate'    => round($totalPresenceRate / $totalEmployees, 1),
                'avg_ponctualite_rate' => round($totalPonctualiteRate / $totalEmployees, 1),
            ];
        }

        // ── Totaux globaux ─────────────────────────────────────────
        $totals = [
            'total_employees'      => 0,
            'total_present'        => 0,
            'total_absent'         => 0,
            'total_late'           => 0,
            'total_early_leave'    => 0,
            'total_half_day'       => 0,
            'total_mission'        => 0,
            'total_leave'          => 0,
            'total_on_time'        => 0,
            'avg_presence_rate'    => 0,
            'avg_ponctualite_rate' => 0,
        ];

        foreach ($reportData as $data) {
            $totals['total_employees']   += $data['total_employees'];
            $totals['total_present']     += $data['total_present'];
            $totals['total_absent']      += $data['total_absent'];
            $totals['total_late']        += $data['total_late'];
            $totals['total_early_leave'] += $data['total_early_leave'];
            $totals['total_half_day']    += $data['total_half_day'];
            $totals['total_mission']     += $data['total_mission'];
            $totals['total_leave']       += $data['total_leave'];
            $totals['total_on_time']     += $data['total_on_time'];
        }

        if (!empty($reportData)) {
            $totals['avg_presence_rate']    = round(array_sum(array_column($reportData, 'avg_presence_rate')) / count($reportData), 1);
            $totals['avg_ponctualite_rate'] = round(array_sum(array_column($reportData, 'avg_ponctualite_rate')) / count($reportData), 1);
        }

        return [
            'report_data'       => $reportData,
            'totals'            => $totals,
            'total_departments' => count($reportData),
            'period_days'       => $workingDays,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // PDF : utilise uniquement le premier tableau (récapitulatif)
    // ══════════════════════════════════════════════════════════════

    private function generatePdf($client, $userName, $reportData, $startDate, $endDate)
    {
        try {
            $pdfData = [
                'client'            => $client,
                'client_name'       => $userName,
                'start_date'        => $startDate->format('Y-m-d'),
                'end_date'          => $endDate->format('Y-m-d'),
                'month_name'        => $startDate->locale('fr')->monthName,
                'year'              => $startDate->year,
                'export_date'       => Carbon::now(),
                'report_data'       => $reportData['report_data'],
                'totals'            => $reportData['totals'],
                'total_departments' => $reportData['total_departments'],
                'period_days'       => $reportData['period_days'],
            ];

            $pdf = Pdf::loadView('reports.exports.monthly-rh-report-summary-pdf', $pdfData);
            $pdf->setPaper('A4', 'landscape');
            $pdf->setOption('defaultFont', 'DejaVu Sans');
            $pdf->setOption('isHtml5ParserEnabled', true);

            $filename = 'rapport_mensuel_rh_' . $client->id . '_' .
                        $startDate->format('Y_m') . '_' .
                        Carbon::now()->format('Ymd_His') . '.pdf';

            $tempPath = storage_path('app/temp/' . $filename);
            $tempDir  = dirname($tempPath);

            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $pdf->save($tempPath);

            if (!file_exists($tempPath)) {
                $this->error("❌ PDF non créé: " . $tempPath);
                return null;
            }

            $this->info("✅ PDF: " . basename($tempPath) . " (" . number_format(filesize($tempPath) / 1024, 2) . " KB)");
            Log::info('PDF mensuel généré pour client ' . $client->id . ': ' . $tempPath);

            return $tempPath;

        } catch (\Exception $e) {
            $this->error("❌ Erreur PDF: " . $e->getMessage());
            Log::error('Erreur PDF client ' . $client->id . ': ' . $e->getMessage());
            return null;
        }
    }

    // ══════════════════════════════════════════════════════════════
    // EMAIL
    // ══════════════════════════════════════════════════════════════

    private function sendReportEmail($client, $userName, $rhEmail, $reportData, $startDate, $endDate, $pdfPath)
    {
        $emailData = [
            'client'            => $client,
            'client_name'       => $userName,
            'email'             => $rhEmail,
            'start_date'        => $startDate->format('d/m/Y'),
            'end_date'          => $endDate->format('d/m/Y'),
            'month_name'        => $startDate->locale('fr')->monthName,
            'year'              => $startDate->year,
            'report_data'       => $reportData['report_data'],
            'totals'            => $reportData['totals'],
            'total_departments' => $reportData['total_departments'],
            'period_days'       => $reportData['period_days'],
            'generated_at'      => Carbon::now(),
            'pdf_filename'      => basename($pdfPath),
        ];

        $mail = new \App\Mail\MonthlyRHReport($emailData);
        $mail->attach($pdfPath, [
            'as'   => 'Rapport_Mensuel_RH_' . $userName . '_' . $startDate->format('Y_m') . '.pdf',
            'mime' => 'application/pdf',
        ]);

        Mail::to($rhEmail)->send($mail);

        Log::info('Rapport mensuel RH envoyé à ' . $rhEmail . ' pour client ' . $client->id);
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

    private function countWorkingDays($startDate, $endDate)
    {
        $start       = Carbon::parse($startDate);
        $end         = Carbon::parse($endDate);
        $workingDays = 0;

        for ($date = $start->copy(); $date <= $end; $date->addDay()) {
            if ($date->dayOfWeekIso >= 1 && $date->dayOfWeekIso <= 5) {
                $workingDays++;
            }
        }

        return $workingDays;
    }

    private function getClientUserName($client)
    {
        if ($client->user) {
            return $client->user->name ?: ($client->user->email ?: 'Client #' . $client->id);
        }
        $user = User::find($client->user_id);
        return $user ? ($user->name ?: $user->email) : 'Client #' . $client->id;
    }

    private function cleanupTempFile($filePath)
    {
        try {
            if (file_exists($filePath)) {
                unlink($filePath);
                Log::info('Fichier temporaire supprimé: ' . $filePath);
            }
        } catch (\Exception $e) {
            Log::warning('Impossible de supprimer le fichier temporaire: ' . $e->getMessage());
        }
    }
}

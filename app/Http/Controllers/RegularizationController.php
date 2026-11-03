<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRegularization;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\RegularizationCause;
use App\Support\CurrentClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * « Autre » — régularisation manuelle de pointage (coupure d'électricité, oubli
 * de pointage, …). Une régularisation écrit/met à jour la ligne
 * daily_attendances correspondante (statut présent + heures + cause), de sorte
 * qu'elle apparaisse automatiquement dans tous les rapports HTML/PDF/e-mails.
 *
 * Multi-tenant : tout est scopé par CurrentClient::id().
 */
class RegularizationController extends Controller
{
    /** Causes par défaut garanties pour chaque client. */
    private const DEFAULT_CAUSES = [
        'Coupure d\'électricité',
        'Oubli de pointage',
    ];

    private function ensureDefaultCauses(int $clientId): void
    {
        foreach (self::DEFAULT_CAUSES as $label) {
            RegularizationCause::firstOrCreate(
                ['client_id' => $clientId, 'label' => $label],
                ['is_protected' => true]
            );
        }
    }

    public function index()
    {
        $clientId = CurrentClient::id();
        $this->ensureDefaultCauses($clientId);

        $causes = RegularizationCause::where('client_id', $clientId)->orderBy('label')->get();

        $employees = Employee::where('client_id', $clientId)
            ->whereNotNull('emp_code')->where('emp_code', '!=', '')
            ->orderBy('emp_code')
            ->get()
            ->map(fn ($e) => [
                'id'        => $e->id,
                'emp_code'  => $e->emp_code,
                'full_name' => trim($e->first_name . ' ' . ($e->last_name ?? '')),
            ]);

        $recent = AttendanceRegularization::with(['cause', 'employee'])
            ->where('client_id', $clientId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('regularizations.index', compact('causes', 'employees', 'recent'));
    }

    /** Test temps réel : quels employés ont déjà un pointage le jour choisi. */
    public function checkConflicts(Request $request)
    {
        $request->validate([
            'date'           => 'required|date',
            'employee_ids'   => 'required|array',
            'employee_ids.*' => 'integer',
        ]);

        $clientId = CurrentClient::id();
        $date = $request->input('date');

        $rows = DailyAttendance::where('client_id', $clientId)
            ->whereIn('employee_id', $request->input('employee_ids'))
            ->whereDate('attendance_date', $date)
            ->get();

        $conflicts = [];
        foreach ($rows as $r) {
            // Un pointage « existe » s'il y a une arrivée ou un statut non-absent.
            $hasPunch = $r->check_in || strtoupper((string) $r->status) !== 'ABSENT';
            if (!$hasPunch) {
                continue;
            }
            $conflicts[(int) $r->employee_id] = [
                'check_in'  => $r->check_in ? \Carbon\Carbon::parse($r->check_in)->format('H:i') : null,
                'check_out' => $r->check_out ? \Carbon\Carbon::parse($r->check_out)->format('H:i') : null,
                'status'    => $r->status,
            ];
        }

        return response()->json(['success' => true, 'date' => $date, 'conflicts' => $conflicts]);
    }

    public function store(Request $request)
    {
        $clientId = CurrentClient::id();

        $request->validate([
            'cause_id'       => 'required|integer',
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'integer',
            'date'           => 'required|date',
            'check_in'       => 'required|date_format:H:i',
            'check_out'      => 'required|date_format:H:i|after:check_in',
            'force'          => 'nullable|boolean',
        ]);

        $cause = RegularizationCause::where('client_id', $clientId)->find($request->input('cause_id'));
        if (!$cause) {
            return response()->json(['success' => false, 'message' => 'Cause introuvable.'], 404);
        }

        $date     = $request->input('date');
        $checkIn  = $request->input('check_in');
        $checkOut = $request->input('check_out');
        $force    = $request->boolean('force');

        $employees = Employee::where('client_id', $clientId)
            ->whereIn('id', $request->input('employee_ids'))
            ->get()
            ->keyBy('id');

        $checkInDt  = $date . ' ' . $checkIn . ':00';
        $checkOutDt = $date . ' ' . $checkOut . ':00';
        $workHours  = round((strtotime($checkOutDt) - strtotime($checkInDt)) / 3600, 2);

        $applied = [];
        $skipped = [];

        try {
            DB::transaction(function () use ($employees, $clientId, $cause, $date, $checkInDt, $checkOutDt, $checkIn, $checkOut, $workHours, $force, &$applied, &$skipped) {
                foreach ($employees as $emp) {
                    $existing = DailyAttendance::where('client_id', $clientId)
                        ->where('employee_id', $emp->id)
                        ->whereDate('attendance_date', $date)
                        ->first();

                    $hasPunch = $existing && ($existing->check_in || strtoupper((string) $existing->status) !== 'ABSENT');

                    // Pointage déjà présent et pas de confirmation d'écrasement : on ignore.
                    if ($hasPunch && !$force) {
                        $skipped[] = $emp->emp_code;
                        continue;
                    }

                    DailyAttendance::updateOrCreate(
                        ['client_id' => $clientId, 'employee_id' => $emp->id, 'attendance_date' => $date],
                        [
                            'emp_code'       => $emp->emp_code,
                            'check_in'       => $checkInDt,
                            'check_out'      => $checkOutDt,
                            'work_hours'     => $workHours,
                            'status'         => 'PRESENT',
                            'is_late'        => false,
                            'is_early_leave' => false,
                            'is_overtime'    => false,
                            'notes'          => 'Régularisation — ' . $cause->label,
                            'raw_data'       => json_encode([
                                'source' => 'regularization',
                                'cause'  => $cause->label,
                                'by'     => auth()->id(),
                                'at'     => now()->toDateTimeString(),
                            ]),
                        ]
                    );

                    AttendanceRegularization::create([
                        'client_id'   => $clientId,
                        'cause_id'    => $cause->id,
                        'employee_id' => $emp->id,
                        'date'        => $date,
                        'check_in'    => $checkIn,
                        'check_out'   => $checkOut,
                        'created_by'  => auth()->id(),
                    ]);

                    $applied[] = $emp->emp_code;
                }
            });
        } catch (\Throwable $e) {
            Log::error('Regularization store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }

        $msg = count($applied) . ' pointage(s) régularisé(s).';
        if (!empty($skipped)) {
            $msg .= ' ' . count($skipped) . ' ignoré(s) (pointage déjà existant).';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'applied' => $applied,
            'skipped' => $skipped,
        ]);
    }

    /** Ajouter une cause (bouton +). */
    public function storeCause(Request $request)
    {
        $clientId = CurrentClient::id();

        $request->validate(['label' => 'required|string|max:255']);

        $label = trim($request->input('label'));

        if (RegularizationCause::where('client_id', $clientId)->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])->exists()) {
            return response()->json(['success' => false, 'message' => 'Cette cause existe déjà.'], 422);
        }

        $cause = RegularizationCause::create(['client_id' => $clientId, 'label' => $label, 'is_protected' => false]);

        return response()->json(['success' => true, 'message' => 'Cause ajoutée.', 'cause' => $cause]);
    }

    /** Supprimer une cause (hors causes protégées et non utilisées). */
    public function destroyCause($id)
    {
        $cause = RegularizationCause::where('client_id', CurrentClient::id())->find($id);
        if (!$cause) {
            return response()->json(['success' => false, 'message' => 'Cause introuvable.'], 404);
        }
        if ($cause->is_protected) {
            return response()->json(['success' => false, 'message' => 'Cause par défaut non supprimable.'], 409);
        }
        if ($cause->regularizations()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cause utilisée par des régularisations : suppression impossible.'], 409);
        }

        $cause->delete();

        return response()->json(['success' => true, 'message' => 'Cause supprimée.']);
    }
}

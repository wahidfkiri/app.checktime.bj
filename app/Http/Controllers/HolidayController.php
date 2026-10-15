<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Support\CurrentClient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

/**
 * Gestion des jours fériés du client courant (multi-tenant).
 * Toutes les requêtes sont scopées via CurrentClient::id().
 */
class HolidayController extends Controller
{
    public function index()
    {
        $years = Holiday::where('client_id', CurrentClient::id())
            ->selectRaw('DISTINCT YEAR(holiday_date) as year')
            ->orderBy('year', 'desc')
            ->pluck('year');

        return view('holidays.index', compact('years'));
    }

    public function datatable(Request $request)
    {
        $query = Holiday::query()->where('client_id', CurrentClient::id());

        if ($request->filled('year_filter')) {
            $year = $request->year_filter;
            $query->where(function ($q) use ($year) {
                $q->whereYear('holiday_date', $year)->orWhere('is_recurring', true);
            });
        }
        if ($request->filled('recurring_filter') && $request->recurring_filter !== '') {
            $query->where('is_recurring', $request->recurring_filter === '1');
        }
        if ($request->filled('working_day_filter') && $request->working_day_filter !== '') {
            $query->where('is_working_day', $request->working_day_filter === '1');
        }

        return DataTables::of($query)
            ->addColumn('date_formatted', function ($h) {
                $date = Carbon::parse($h->holiday_date);
                return $h->is_recurring
                    ? $date->locale('fr')->isoFormat('DD MMMM') . ' (chaque année)'
                    : $date->locale('fr')->isoFormat('DD MMMM YYYY');
            })
            ->addColumn('recurring_badge', fn ($h) => $h->is_recurring
                ? '<span class="badge bg-info">Récurrent</span>'
                : '<span class="badge bg-secondary">Ponctuel</span>')
            ->addColumn('working_day_badge', fn ($h) => $h->is_working_day
                ? '<span class="badge bg-warning text-dark">Travaillé</span>'
                : '<span class="badge bg-success">Non travaillé</span>')
            ->addColumn('actions', function ($h) {
                return '<div class="btn-group" role="group">'
                    . '<button type="button" class="btn btn-sm btn-warning edit-btn" data-id="' . $h->id . '" title="Modifier"><i class="bi bi-pencil"></i></button>'
                    . '<button type="button" class="btn btn-sm btn-danger delete-btn" data-id="' . $h->id . '" data-name="' . e($h->name) . '" title="Supprimer"><i class="bi bi-trash"></i></button>'
                    . '</div>';
            })
            ->rawColumns(['recurring_badge', 'working_day_badge', 'actions'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'holiday_date'   => 'required|date',
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string|max:1000',
            'is_recurring'   => 'nullable|boolean',
            'is_working_day' => 'nullable|boolean',
        ]);

        $clientId = CurrentClient::id();
        $date = Carbon::parse($validated['holiday_date'])->format('Y-m-d');
        $isRecurring = $request->boolean('is_recurring');

        if ($dup = $this->duplicateMessage($clientId, $date, $isRecurring)) {
            return response()->json(['success' => false, 'message' => $dup, 'errors' => ['holiday_date' => [$dup]]], 422);
        }

        try {
            $holiday = Holiday::create([
                'client_id'      => $clientId,
                'holiday_date'   => $date,
                'name'           => $validated['name'],
                'description'    => $validated['description'] ?? null,
                'is_recurring'   => $isRecurring,
                'is_working_day' => $request->boolean('is_working_day'),
            ]);

            return response()->json(['success' => true, 'message' => 'Jour férié créé avec succès', 'data' => $holiday]);
        } catch (\Exception $e) {
            Log::error('Holiday store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    public function edit($id)
    {
        $holiday = Holiday::where('client_id', CurrentClient::id())->find($id);
        if (!$holiday) {
            return response()->json(['success' => false, 'message' => 'Jour férié non trouvé'], 404);
        }

        return response()->json(['success' => true, 'data' => [
            'id'             => $holiday->id,
            'holiday_date'   => $holiday->holiday_date->format('Y-m-d'),
            'name'           => $holiday->name,
            'description'    => $holiday->description,
            'is_recurring'   => $holiday->is_recurring,
            'is_working_day' => $holiday->is_working_day,
        ]]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'holiday_date'   => 'required|date',
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string|max:1000',
            'is_recurring'   => 'nullable|boolean',
            'is_working_day' => 'nullable|boolean',
        ]);

        $clientId = CurrentClient::id();
        $holiday = Holiday::where('client_id', $clientId)->find($id);
        if (!$holiday) {
            return response()->json(['success' => false, 'message' => 'Jour férié non trouvé'], 404);
        }

        $date = Carbon::parse($validated['holiday_date'])->format('Y-m-d');
        $isRecurring = $request->boolean('is_recurring');

        if ($dup = $this->duplicateMessage($clientId, $date, $isRecurring, $holiday->id)) {
            return response()->json(['success' => false, 'message' => $dup, 'errors' => ['holiday_date' => [$dup]]], 422);
        }

        try {
            $holiday->update([
                'holiday_date'   => $date,
                'name'           => $validated['name'],
                'description'    => $validated['description'] ?? null,
                'is_recurring'   => $isRecurring,
                'is_working_day' => $request->boolean('is_working_day'),
            ]);

            return response()->json(['success' => true, 'message' => 'Jour férié mis à jour avec succès', 'data' => $holiday]);
        } catch (\Exception $e) {
            Log::error('Holiday update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $holiday = Holiday::where('client_id', CurrentClient::id())->find($id);
        if (!$holiday) {
            return response()->json(['success' => false, 'message' => 'Jour férié non trouvé'], 404);
        }

        try {
            $holiday->delete();
            return response()->json(['success' => true, 'message' => 'Jour férié supprimé']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Contrôle de doublon au sein du client : date exacte, et jour/mois pour un
     * férié récurrent. Retourne le message d'erreur, ou null si pas de doublon.
     */
    private function duplicateMessage(?int $clientId, string $date, bool $isRecurring, ?int $ignoreId = null): ?string
    {
        $exact = Holiday::where('client_id', $clientId)->where('holiday_date', $date)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists();
        if ($exact) {
            return 'Un jour férié existe déjà à cette date.';
        }

        if ($isRecurring) {
            $recur = Holiday::where('client_id', $clientId)->where('is_recurring', true)
                ->whereMonth('holiday_date', Carbon::parse($date)->format('m'))
                ->whereDay('holiday_date', Carbon::parse($date)->format('d'))
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists();
            if ($recur) {
                return 'Un jour férié récurrent existe déjà pour ce jour/mois.';
            }
        }

        return null;
    }
}

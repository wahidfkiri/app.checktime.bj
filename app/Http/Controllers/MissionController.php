<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\Client;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class MissionController extends Controller
{
    /**
     * Afficher la liste des missions
     */
    public function index(Request $request)
    {
        // Récupérer le client de l'utilisateur connecté
        $client = Client::where('user_id', auth()->id())->first();
        
        if (!$client) {
            if ($request->ajax()) {
                return response()->json(['error' => 'Client non trouvé'], 404);
            }
            return redirect()->route('home')->with('error', 'Client non trouvé.');
        }

        // Récupérer les employés pour les filtres
        $employees = Employee::where('client_id', $client->id)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'emp_code', 'dept_name']);

        // Récupérer les départements UNIQUES depuis la table employees
        $departments = Employee::where('client_id', $client->id)
            ->whereNotNull('dept_name')
            ->where('dept_name', '!=', '')
            ->select('dept_name')
            ->distinct()
            ->orderBy('dept_name')
            ->pluck('dept_name');

        // Si c'est une requête AJAX pour DataTables
        if ($request->ajax()) {
            return $this->getMissionsData($request, $client->id);
        }

        return view('missions.index', compact('employees', 'departments'));
    }

    /**
     * Récupérer les données pour DataTables
     */
    private function getMissionsData(Request $request, $clientId)
    {
        $query = Mission::where('client_id', $clientId)
            ->with('employee')
            ->select('missions.*');

        // Appliquer les filtres
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('department')) {
            // Filtrer par département via la relation employee
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('dept_name', $request->department);
            });
        }

        if ($request->filled('start_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $query->where('start_date', '>=', $startDate);
        }

        if ($request->filled('end_date')) {
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->where('end_date', '<=', $endDate);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('employee_name', function($mission) {
                if (!$mission->employee) {
                    return '<span class="text-muted">Employé supprimé</span>';
                }
                return $mission->employee->first_name . ' ' . $mission->employee->last_name;
            })
            ->addColumn('department_name', function($mission) {
                return $mission->employee?->dept_name ?? '-';
            })
            ->addColumn('period', function($mission) {
                if (!$mission->start_date || !$mission->end_date) {
                    return '-';
                }
                return $mission->start_date->format('d/m/Y H:i') . '<br>' . 
                       $mission->end_date->format('d/m/Y H:i');
            })
            ->addColumn('duration_formatted', function($mission) {
                if (!$mission->start_date || !$mission->end_date) {
                    return '-';
                }
                $days = $mission->start_date->diffInDays($mission->end_date);
                $hours = $mission->start_date->diffInHours($mission->end_date) % 24;
                
                if ($days > 0) {
                    return $days . 'j' . ($hours > 0 ? ' ' . $hours . 'h' : '');
                }
                return $hours . 'h';
            })
            ->addColumn('actions', function($mission) {
                return '
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-info view-btn" 
                                data-id="' . $mission->id . '" 
                                title="Voir détails">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-warning edit-btn" 
                                data-id="' . $mission->id . '" 
                                title="Modifier">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                data-id="' . $mission->id . '" 
                                title="Supprimer">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                ';
            })
            ->rawColumns(['period', 'actions'])
            ->make(true);
    }

    /**
     * Stocker une nouvelle mission
     */
    public function store(Request $request)
    {
        try {
            $client = Client::where('user_id', auth()->id())->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client non trouvé'
                ], 404);
            }

            // Validation
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'reference' => 'required|string|max:50|unique:missions,reference',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'destination' => 'nullable|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            // Ajouter le client_id
            $validated['client_id'] = $client->id;

            // Créer la mission
            $mission = Mission::create($validated);

            // Charger la relation employee
            $mission->load('employee');

            Log::info("Mission créée: {$mission->reference} pour le client {$client->id}");

            return response()->json([
                'success' => true,
                'message' => 'Mission créée avec succès',
                'data' => $mission
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur création mission: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une mission spécifique
     */
    public function show($id)
    {
        try {
            $client = Client::where('user_id', auth()->id())->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client non trouvé'
                ], 404);
            }

            $mission = Mission::where('client_id', $client->id)
                ->with('employee')
                ->find($id);

            if (!$mission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mission non trouvée'
                ], 404);
            }

            // Formater les données pour l'affichage
            $data = [
                'id' => $mission->id,
                'reference' => $mission->reference,
                'title' => $mission->title,
                'description' => $mission->description,
                'destination' => $mission->destination,
                'start_date' => $mission->start_date->toISOString(),
                'end_date' => $mission->end_date->toISOString(),
                'employee_id' => $mission->employee_id,
                'employee' => $mission->employee ? [
                    'id' => $mission->employee->id,
                    'first_name' => $mission->employee->first_name,
                    'last_name' => $mission->employee->last_name,
                    'full_name' => $mission->employee->first_name . ' ' . $mission->employee->last_name,
                    'dept_name' => $mission->employee->dept_name
                ] : null,
                'created_at' => $mission->created_at->toISOString(),
                'updated_at' => $mission->updated_at->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur affichage mission: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour une mission
     */
    public function update(Request $request, $id)
    {
        try {
            $client = Client::where('user_id', auth()->id())->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client non trouvé'
                ], 404);
            }

            $mission = Mission::where('client_id', $client->id)->find($id);

            if (!$mission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mission non trouvée'
                ], 404);
            }

            // Validation
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'destination' => 'nullable|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            // Mettre à jour la mission
            $mission->update($validated);

            Log::info("Mission mise à jour: {$mission->reference}");

            return response()->json([
                'success' => true,
                'message' => 'Mission modifiée avec succès',
                'data' => $mission->load('employee')
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur modification mission: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une mission
     */
    public function destroy($id)
    {
        try {
            $client = Client::where('user_id', auth()->id())->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client non trouvé'
                ], 404);
            }

            $mission = Mission::where('client_id', $client->id)->find($id);

            if (!$mission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mission non trouvée'
                ], 404);
            }

            $reference = $mission->reference;
            $mission->delete();

            Log::info("Mission supprimée: {$reference}");

            return response()->json([
                'success' => true,
                'message' => 'Mission supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur suppression mission: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer une référence automatique
     */
    public function generateReference()
    {
        $client = Client::where('user_id', auth()->id())->first();
        
        if (!$client) {
            return response()->json(['reference' => null], 404);
        }

        $year = now()->format('Y');
        $month = now()->format('m');
        $day = now()->format('d');
        
        // Compter les missions du jour
        $count = Mission::where('client_id', $client->id)
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        $reference = 'MISS-' . $year . $month . $day . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        return response()->json([
            'reference' => $reference
        ]);
    }
}
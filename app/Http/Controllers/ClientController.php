<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Services\CheckTimeService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    private CheckTimeService $api;

    
    public function __construct(CheckTimeService $api)
    {
        $this->api = $api;
    }
    /**
     * Affiche la liste des clients
     */
    public function index(Request $request)
    {
        $all = Client::count();
        $actifs = Client::where('is_active', true)->count();
        $inactifs = Client::where('is_active', false)->count();

        return view('clients.index', compact('all', 'actifs', 'inactifs'));
    }

    /**
     * Retourne les données pour DataTable (AJAX)
     */
    public function datatable(Request $request)
{
    try {
        $query = Client::query();
        
        if ($request->has('status') && in_array($request->status, ['active', 'inactive'])) {
            $query->{$request->status}();
        }
        
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('nom_complet', function($client) {
                return $client->nom_complet;
            })
            ->addColumn('status_badge', function($client) {
                return $client->status_badge;
            })
            ->addColumn('email_linked', function($client) {
                return '<a href="mailto:' . $client->email . '">' . $client->email . '</a>';
            })
            ->addColumn('telephone_formatted', function($client) {
                return $client->telephone_formatted;
            })
            ->addColumn('actions', function($client) {
                // Échapper les caractères spéciaux pour le nom
                $clientName = htmlspecialchars($client->raison_sociale, ENT_QUOTES, 'UTF-8');
                
                $html = '<div class="btn-group" role="group" style="gap: 3px;">';
                
                // Bouton Éditer
                $html .= '<button type="button" 
                            class="btn btn-sm btn-info edit-client-btn" 
                            data-id="' . $client->id . '"
                            title="Modifier">
                            <i class="bi bi-pencil"></i>
                         </button>';
                
                // Bouton Activer/Désactiver
                if ($client->is_active) {
                    $html .= '<button type="button" 
                                class="btn btn-sm btn-warning toggle-status-btn" 
                                data-id="' . $client->id . '"
                                data-action="deactivate"
                                title="Désactiver">
                                <i class="bi bi-toggle-off"></i>
                             </button>';
                } else {
                    $html .= '<button type="button" 
                                class="btn btn-sm btn-success toggle-status-btn" 
                                data-id="' . $client->id . '"
                                data-action="activate"
                                title="Activer">
                                <i class="bi bi-toggle-on"></i>
                             </button>';
                }
                
                // Bouton Supprimer
                $html .= '<button type="button" 
                            class="btn btn-sm btn-danger btn-delete-client" 
                            data-id="' . $client->id . '"
                            data-name="' . $clientName . '"
                            title="Supprimer">
                            <i class="bi bi-trash"></i>
                         </button>';
                
                $html .= '</div>';
                
                return $html;
            })
            ->editColumn('created_at', function($client) {
                return $client->date_creation_formatted;
            })
            ->editColumn('rccm', function($client) {
                return '<span class="text-uppercase">' . $client->rccm . '</span>';
            })
            ->rawColumns(['status_badge', 'email_linked', 'actions', 'rccm'])
            ->make(true);
            
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Erreur lors du chargement des données: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Affiche le formulaire de création (pour modal)
     */
    public function create()
    {
        return view('clients.modals.create');
    }

    /**
     * Enregistre un nouveau client (AJAX)
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'raison_sociale' => 'required|string|max:255',
                'sigle' => 'nullable|string|max:50',
                'rccm' => 'required|string|max:255|unique:clients',
                'ifu' => 'nullable|string|max:255',
                'directeur' => 'nullable|string|max:255',
                'email' => 'required|email|max:255|unique:clients',
                'telephone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string|max:500',
                'is_active' => 'boolean'
            ]);

            $validatedAccess = $request->validate([
                'general_token' => 'required|unique:access_configs,general_token|string'
            ]);

            $validatedCredentials = $request->validate([
                'login_user' => 'required|unique:users,email|string|max:100',
                'password_user' => 'required|string|max:255',
                'password_confirmation' => 'required|string|same:password_user|max:255',
            ]);
           // DB::beginTransaction();

            // Need to test login and password with CheckTime API
            $this->api->testTokenValid($validatedAccess['general_token']);

            $user = User::create([
                'name' => $validated['raison_sociale'],
                'email' => $validatedCredentials['login_user'],
                'password' => bcrypt($validatedCredentials['password_user']),
            ]);
            $client = Client::create($validated + ['user_id' => $user->id]);
            $access_config = $client->accessConfigs()->create($validatedAccess);


            return response()->json([
                'success' => true,
                'message' => 'Client créé avec succès.',
                'client' => $client
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Affiche le formulaire d'édition (pour modal)
     */
    public function edit(Client $client)
    {
        return view('clients.modals.edit', compact('client'));
    }

    /**
     * Met à jour un client (AJAX)
     */
    public function update(Request $request, Client $client)
    {
        try {
            $validated = $request->validate([
                'raison_sociale' => 'required|string|max:255',
                'sigle' => 'nullable|string|max:50',
                'rccm' => 'required|string|max:255|unique:clients,rccm,' . $client->id,
                'ifu' => 'nullable|string|max:255',
                'directeur' => 'nullable|string|max:255',
                'email' => 'required|email|max:255|unique:clients,email,' . $client->id,
                'telephone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string|max:500',
                'ville' => 'nullable|string|max:100',
                'is_active' => 'boolean'
            ]);

            //  $validatedAccess = $request->validate([
            //     'general_token' => 'required|unique:access_configs,general_token|string',
            // ]);

            // Need to test login and password with CheckTime API
         //   $this->api->testTokenValid($validatedAccess['general_token']);
           // $client->accessConfigs()->update($validatedAccess);

            $client->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Client mis à jour avec succès.',
                'client' => $client
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprime un client (AJAX)
     */
    public function destroy(Client $client)
    {
        try {
            // Vérifier s'il y a des dépendances
            if ($client->employees()->exists()) {
                foreach ($client->employees as $employee) {
                    // Logique de suppression ou d'archivage des employés
                    $employee->delete(); // Ou toute autre logique nécessaire
                }
              //  throw new \Exception('Impossible de supprimer ce client car il a des employés associés.');
            }
            
            if ($client->users()->exists()) {
                throw new \Exception('Impossible de supprimer ce client car il a des utilisateurs associés.');
            }

            if($client->accessConfigs()->exists()) {
                $client->accessConfigs()->delete();
            }

            if($client->user_id) {
                $client->user()->delete();
            }
            
            $client->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Client supprimé avec succès.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change le statut d'un client (AJAX)
     */
    public function toggleStatus(Request $request, Client $client)
    {
        try {
            $client->toggleStatus();
            
            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès.',
                'is_active' => $client->is_active
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifie si un RCCM existe déjà (AJAX)
     */
    public function checkRccm(Request $request)
    {
        try {
            $rccm = $request->input('rccm');
            $clientId = $request->input('client_id');
            
            $query = Client::where('rccm', $rccm);
            
            if ($clientId) {
                $query->where('id', '!=', $clientId);
            }
            
            $exists = $query->exists();

            return response()->json([
                'exists' => $exists,
                'message' => $exists ? 'Ce RCCM existe déjà' : 'RCCM disponible'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la vérification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Affiche les détails d'un client (optionnel)
     */
    public function show(Client $client)
    {
        return view('clients.show', compact('client'));
    }
}
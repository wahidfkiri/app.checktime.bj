<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\DailyAttendance;
use App\Models\Device;
use App\Models\Employee;
use App\Support\CurrentClient;
use App\Support\PasswordGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Espace du super-admin : pilotage multi-clients et sélection du contexte
 * de travail.
 *
 * Une fois un client sélectionné (« entrer »), le super-admin navigue dans les
 * écrans existants exactement comme l'administrateur de ce client, car toute la
 * couche métier résout le client courant via App\Support\CurrentClient.
 *
 * La création / modification d'un client réutilise le ClientController existant
 * (routes admin.clients.store / admin.clients.update). Ce contrôleur n'héberge
 * que les actions propres au super-admin.
 */
class ClientAdminController extends Controller
{
    /** Liste des clients avec quelques indicateurs. */
    public function index()
    {
        $clients = Client::with('user')->orderBy('id')->get()->map(function (Client $client) {
            return [
                'model' => $client,
                'employees' => Employee::where('client_id', $client->id)->count(),
                'attendances' => DailyAttendance::where('client_id', $client->id)->count(),
            ];
        });

        return view('admin.clients.index', [
            'clients' => $clients,
            'selectedId' => session(CurrentClient::SESSION_KEY),
            'stats' => [
                'clients' => $clients->count(),
                'active' => $clients->filter(fn ($row) => $row['model']->is_active)->count(),
                'employees' => Employee::count(),
            ],
        ]);
    }

    /** Entre dans le contexte d'un client. */
    public function select(Request $request, $id)
    {
        $client = Client::find($id);

        if (!$client) {
            return back()->with('error', 'Client introuvable.');
        }

        if (!$client->is_active) {
            return back()->with('error', "Ce client est désactivé : activez-le avant d'y entrer.");
        }

        CurrentClient::select($client->id);

        return redirect()->route('dashboard')
            ->with('success', "Vous travaillez désormais dans « {$client->raison_sociale} ».");
    }

    /** Quitte le contexte client. */
    public function release()
    {
        CurrentClient::clear();

        return redirect()->route('admin.clients.index')
            ->with('success', 'Vous avez quitté le contexte client.');
    }

    /** Active ou désactive un client. */
    public function toggleStatus($id)
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client introuvable'], 404);
        }

        $client->toggleStatus();

        // Ne pas laisser le super-admin dans un client qu'il vient de désactiver.
        if (!$client->is_active && session(CurrentClient::SESSION_KEY) == $client->id) {
            CurrentClient::clear();
        }

        return response()->json([
            'success' => true,
            'message' => $client->is_active ? 'Client activé' : 'Client désactivé',
            'is_active' => $client->is_active,
        ]);
    }

    /**
     * Change le mot de passe du compte administrateur d'un client.
     *
     * Le super-admin peut saisir un mot de passe ou en faire générer un. Dans
     * les deux cas il est retourné une seule fois : rien n'est conservé en clair.
     */
    public function resetPassword(Request $request, $id)
    {
        $client = Client::with('user')->find($id);

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client introuvable'], 404);
        }

        if (!$client->user) {
            return response()->json([
                'success' => false,
                'message' => "Ce client n'a aucun compte de connexion associé.",
            ], 409);
        }

        // Garde-fou : cette action vise les comptes clients, pas les pairs.
        if ($client->user->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Ce compte est un super-admin : son mot de passe ne se change pas ici.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'password' => 'nullable|string|min:8|max:64|confirmed',
            ], [
                'password.min' => 'Le mot de passe doit comporter au moins 8 caractères.',
                'password.confirmed' => 'La confirmation ne correspond pas au mot de passe saisi.',
            ]);

            $password = $validated['password'] ?? PasswordGenerator::make();

            $client->user->update(['password' => Hash::make($password)]);

            Log::warning(sprintf(
                'Mot de passe du client #%d (%s) réinitialisé par le super-admin #%d',
                $client->id,
                $client->user->email,
                auth()->id()
            ));

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe modifié',
                'data' => [
                    'email' => $client->user->email,
                    // Affiché une seule fois : il n'est jamais stocké en clair.
                    'password' => $password,
                    'generated' => !isset($validated['password']),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Erreur réinitialisation mot de passe client: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ce que la suppression détruirait, présenté avant confirmation.
     */
    public function deletionImpact($id)
    {
        $client = Client::with('user')->find($id);

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client introuvable'], 404);
        }

        $counts = [
            'employees' => Employee::where('client_id', $client->id)->count(),
            'attendances' => DailyAttendance::where('client_id', $client->id)->count(),
            'devices' => Device::where('client_id', $client->id)->count(),
        ];

        if (Schema::hasTable('attendance_transactions')) {
            $counts['transactions'] = DB::table('attendance_transactions')->where('client_id', $client->id)->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'raison_sociale' => $client->raison_sociale,
                'is_current' => session(CurrentClient::SESSION_KEY) == $client->id,
                'counts' => $counts,
                'login_email' => $client->user?->email,
            ],
        ]);
    }

    /**
     * Supprime définitivement un client (confirmation par recopie du nom).
     */
    public function destroy(Request $request, $id)
    {
        $client = Client::with('user')->find($id);

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client introuvable'], 404);
        }

        // Supprimer le client dans lequel on travaille laisserait la session
        // pointer sur un identifiant disparu.
        if (session(CurrentClient::SESSION_KEY) == $client->id) {
            return response()->json([
                'success' => false,
                'message' => "Vous travaillez actuellement dans ce client : quittez son contexte avant de le supprimer.",
            ], 409);
        }

        // Confirmation par recopie du nom : une suppression irréversible ne doit
        // pas tenir à un seul clic.
        $saisi = trim((string) $request->input('confirmation'));

        if (mb_strtolower($saisi) !== mb_strtolower(trim((string) $client->raison_sociale))) {
            return response()->json([
                'success' => false,
                'message' => 'La confirmation ne correspond pas à la raison sociale du client.',
            ], 422);
        }

        try {
            $nom = $client->raison_sociale;
            $utilisateur = $client->user;

            DB::transaction(function () use ($client) {
                // Tables sans contrainte de clé étrangère : retirées explicitement.
                foreach (['signataires', 'signataire_postes'] as $table) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, 'client_id')) {
                        DB::table($table)->where('client_id', $client->id)->delete();
                    }
                }

                // Employés + configuration d'accès rattachés au client.
                if (method_exists($client, 'employees')) {
                    $client->employees()->delete();
                }
                if (method_exists($client, 'accessConfigs')) {
                    $client->accessConfigs()->delete();
                }

                $client->delete();
            });

            // Hors transaction du client : clients.user_id référence users.
            if ($utilisateur && !$utilisateur->hasRole('super-admin')) {
                $utilisateur->delete();
            }

            Log::warning(sprintf(
                'Client #%d (%s) supprimé définitivement par le super-admin #%d',
                $id,
                $nom,
                auth()->id()
            ));

            return response()->json(['success' => true, 'message' => "Client « {$nom} » supprimé."]);
        } catch (\Exception $e) {
            Log::error('Erreur suppression client : ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage(),
            ], 500);
        }
    }
}

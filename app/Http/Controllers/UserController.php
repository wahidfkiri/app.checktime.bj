<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CurrentClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Gestion des utilisateurs (sous-comptes) du client courant — multi-tenant.
 *
 * - Un compte « administrateur du client » porte le rôle `client` (accès plein
 *   à son client, via toutes les permissions menu.* du rôle).
 * - Un sous-compte restreint porte le rôle `client-user` (aucune permission de
 *   menu héritée) + des permissions DIRECTES choisies → isolation naturelle.
 * - Toutes les requêtes sont scopées par users.client_id = CurrentClient::id().
 */
class UserController extends Controller
{
    /** Catalogue des permissions de menu, groupées pour le formulaire. */
    public static function menuPermissionGroups(): array
    {
        return [
            'Employés' => [
                'menu.employees'   => 'Liste des employés',
                'menu.departments' => 'Départements',
                'menu.areas'       => 'Zones',
            ],
            'Plannings' => [
                'menu.work-hours'         => "Types d'horaires",
                'menu.employee-schedules' => 'Plannings employés',
                'menu.schedules'          => 'Calendrier',
            ],
            'Autorisations' => [
                'menu.employee-permissions' => 'Permissions',
                'menu.missions'             => 'Missions',
                'menu.leaves'               => 'Congés',
                'menu.leave-types'          => 'Types de congés',
                'menu.holidays'             => 'Jours fériés',
            ],
            'Suivi' => [
                'menu.daily-attendance' => 'Historique des pointages',
                'menu.reports'          => 'Rapports des présences',
            ],
            'Administration' => [
                'menu.devices'          => 'Appareils',
                'menu.users'            => 'Utilisateurs',
                'menu.settings'         => 'Paramètres',
                'menu.email-templates'  => "Modèles d'e-mails",
                'menu.report-templates' => 'Modèles de rapports',
                'menu.journal'          => "Journal d'activité",
            ],
        ];
    }

    private function scopedUser($id): ?User
    {
        return User::where('client_id', CurrentClient::id())->find($id);
    }

    public function index()
    {
        $users = User::with('roles', 'permissions')
            ->where('client_id', CurrentClient::id())
            ->orderBy('id')
            ->get();

        $permissionGroups = $this->menuPermissionGroups();

        return view('users.index', compact('users', 'permissionGroups'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users,email',
            'password'      => 'required|string|min:8',
            'is_admin'      => 'nullable|boolean',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            $user = User::create([
                'client_id' => CurrentClient::id(),
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
            ]);

            if ($request->boolean('is_admin')) {
                $user->syncRoles(['client']);
                $user->syncPermissions([]);
            } else {
                $user->syncRoles(['client-user']);
                $user->syncPermissions($validated['permissions'] ?? []);
            }

            Log::info("Utilisateur créé (client #" . CurrentClient::id() . "): {$user->email}");

            return response()->json(['success' => true, 'message' => 'Utilisateur créé avec succès']);
        } catch (\Exception $e) {
            Log::error('User store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $user = $this->scopedUser($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
        }

        return response()->json(['success' => true, 'data' => [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'is_admin'    => $user->hasRole('client'),
            'permissions' => $user->getDirectPermissions()->pluck('name'),
        ]]);
    }

    public function update(Request $request, $id)
    {
        $user = $this->scopedUser($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
        }

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'      => 'nullable|string|min:8',
            'is_admin'      => 'nullable|boolean',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $willBeAdmin = $request->boolean('is_admin');

        // Garde-fou : ne pas retirer le dernier administrateur (rôle client) du client.
        if ($user->hasRole('client') && !$willBeAdmin && $this->clientAdminsCount() <= 1) {
            return response()->json([
                'success' => false,
                'message' => "Impossible de rétrograder : c'est le dernier administrateur de ce client.",
            ], 422);
        }

        try {
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }
            $user->save();

            if ($willBeAdmin) {
                $user->syncRoles(['client']);
                $user->syncPermissions([]);
            } else {
                $user->syncRoles(['client-user']);
                $user->syncPermissions($validated['permissions'] ?? []);
            }

            Log::info("Utilisateur mis à jour (client #" . CurrentClient::id() . "): {$user->email}");

            return response()->json(['success' => true, 'message' => 'Utilisateur mis à jour avec succès']);
        } catch (\Exception $e) {
            Log::error('User update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $user = $this->scopedUser($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
        }

        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        if ($user->hasRole('client') && $this->clientAdminsCount() <= 1) {
            return response()->json(['success' => false, 'message' => "Impossible de supprimer le dernier administrateur du client."], 422);
        }

        try {
            $user->delete();
            return response()->json(['success' => true, 'message' => 'Utilisateur supprimé']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /** Nombre d'administrateurs (rôle client) du client courant. */
    private function clientAdminsCount(): int
    {
        return User::where('client_id', CurrentClient::id())->role('client')->count();
    }
}

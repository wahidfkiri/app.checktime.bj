<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use App\Support\CurrentClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gestion des types de congés du client courant (multi-tenant).
 * Scopé via CurrentClient::id() ; l'unicité du nom est vérifiée par client.
 */
class LeaveTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = LeaveType::where('client_id', CurrentClient::id())
            ->orderBy('name')
            ->get();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $types]);
        }

        return view('leave-types.index', compact('types'));
    }

    public function store(Request $request)
    {
        $clientId = CurrentClient::id();

        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        if ($this->nameExists($clientId, $request->name)) {
            return response()->json([
                'success' => false,
                'message' => 'Ce type de congé existe déjà.',
                'errors' => ['name' => ['Nom déjà utilisé.']],
            ], 422);
        }

        try {
            $type = LeaveType::create([
                'client_id' => $clientId,
                'name'      => trim($request->name),
                'is_active' => true,
            ]);

            return response()->json(['success' => true, 'message' => 'Type de congé créé avec succès', 'data' => $type]);
        } catch (\Exception $e) {
            Log::error('LeaveType store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $clientId = CurrentClient::id();
        $type = LeaveType::where('client_id', $clientId)->find($id);
        if (!$type) {
            return response()->json(['success' => false, 'message' => 'Type de congé non trouvé'], 404);
        }

        $request->validate([
            'name'      => 'required|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($this->nameExists($clientId, $request->name, $type->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Ce type de congé existe déjà.',
                'errors' => ['name' => ['Nom déjà utilisé.']],
            ], 422);
        }

        try {
            $type->update([
                'name'      => trim($request->name),
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $type->is_active,
            ]);

            return response()->json(['success' => true, 'message' => 'Type de congé mis à jour', 'data' => $type]);
        } catch (\Exception $e) {
            Log::error('LeaveType update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    public function toggleStatus($id)
    {
        $type = LeaveType::where('client_id', CurrentClient::id())->find($id);
        if (!$type) {
            return response()->json(['success' => false, 'message' => 'Type de congé non trouvé'], 404);
        }

        $type->update(['is_active' => !$type->is_active]);

        return response()->json([
            'success'   => true,
            'message'   => $type->is_active ? 'Type activé' : 'Type désactivé',
            'is_active' => $type->is_active,
        ]);
    }

    public function destroy($id)
    {
        $type = LeaveType::where('client_id', CurrentClient::id())->find($id);
        if (!$type) {
            return response()->json(['success' => false, 'message' => 'Type de congé non trouvé'], 404);
        }

        // Refuser la suppression s'il est utilisé par des congés existants.
        if ($type->leaves()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce type est utilisé par des congés : désactivez-le plutôt que de le supprimer.',
            ], 409);
        }

        try {
            $type->delete();
            return response()->json(['success' => true, 'message' => 'Type de congé supprimé']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /** Unicité du nom au sein du client (insensible à la casse). */
    private function nameExists(?int $clientId, string $name, ?int $ignoreId = null): bool
    {
        return LeaveType::where('client_id', $clientId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}

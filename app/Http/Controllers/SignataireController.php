<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Signataire;
use App\Models\SignatairePoste;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gestion des signataires (cartouche de signatures des rapports).
 * Un poste (colonne) possède plusieurs responsables (Nom complet + fonction).
 */
class SignataireController extends Controller
{
    private function currentClient(): ?Client
    {
        return Client::where('user_id', auth()->user()->id)->first();
    }

    /**
     * Lister les postes et leurs responsables (AJAX).
     */
    public function index()
    {
        $client = $this->currentClient();

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé.'], 404);
        }

        $postes = SignatairePoste::with('signataires')
            ->where('client_id', $client->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return response()->json(['success' => true, 'postes' => $postes]);
    }

    /**
     * Créer un poste.
     */
    public function storePoste(Request $request)
    {
        $client = $this->currentClient();

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé.'], 404);
        }

        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $poste = SignatairePoste::create([
            'client_id' => $client->id,
            'name'      => trim($request->input('name')),
            'position'  => (int) SignatairePoste::where('client_id', $client->id)->max('position') + 1,
        ]);

        $poste->load('signataires');

        return response()->json([
            'success' => true,
            'message' => 'Poste ajouté avec succès.',
            'poste'   => $poste,
        ]);
    }

    /**
     * Modifier le nom d'un poste.
     */
    public function updatePoste(Request $request, $id)
    {
        $client = $this->currentClient();

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé.'], 404);
        }

        $poste = SignatairePoste::where('client_id', $client->id)->find($id);

        if (!$poste) {
            return response()->json(['success' => false, 'message' => 'Poste non trouvé.'], 404);
        }

        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $poste->update(['name' => trim($request->input('name'))]);

        return response()->json([
            'success' => true,
            'message' => 'Poste modifié avec succès.',
            'poste'   => $poste,
        ]);
    }

    /**
     * Supprimer un poste (et ses responsables via cascade).
     */
    public function destroyPoste($id)
    {
        $client = $this->currentClient();

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé.'], 404);
        }

        $poste = SignatairePoste::where('client_id', $client->id)->find($id);

        if (!$poste) {
            return response()->json(['success' => false, 'message' => 'Poste non trouvé.'], 404);
        }

        $poste->delete();

        return response()->json(['success' => true, 'message' => 'Poste supprimé avec succès.']);
    }

    /**
     * Ajouter un responsable à un poste.
     */
    public function storeSignataire(Request $request)
    {
        $client = $this->currentClient();

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé.'], 404);
        }

        $validator = \Validator::make($request->all(), [
            'poste_id'  => 'required|integer',
            'full_name' => 'required|string|max:255',
            'fonction'  => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Le poste doit appartenir au client courant.
        $poste = SignatairePoste::where('client_id', $client->id)->find($request->input('poste_id'));

        if (!$poste) {
            return response()->json(['success' => false, 'message' => 'Poste non trouvé.'], 404);
        }

        $signataire = Signataire::create([
            'client_id' => $client->id,
            'poste_id'  => $poste->id,
            'full_name' => trim($request->input('full_name')),
            'fonction'  => trim((string) $request->input('fonction')),
            'position'  => (int) Signataire::where('poste_id', $poste->id)->max('position') + 1,
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Responsable ajouté avec succès.',
            'signataire' => $signataire,
        ]);
    }

    /**
     * Supprimer un responsable.
     */
    public function destroySignataire($id)
    {
        $client = $this->currentClient();

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé.'], 404);
        }

        $signataire = Signataire::where('client_id', $client->id)->find($id);

        if (!$signataire) {
            return response()->json(['success' => false, 'message' => 'Responsable non trouvé.'], 404);
        }

        $signataire->delete();

        return response()->json(['success' => true, 'message' => 'Responsable supprimé avec succès.']);
    }
}

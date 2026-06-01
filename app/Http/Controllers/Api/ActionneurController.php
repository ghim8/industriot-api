<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actionneur;
use App\Models\Relai;
use App\Models\Capteur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActionneurController extends BaseController
{
    // Liste des actionneurs d'une machine
    public function index(Request $request)
{
    $entrepriseId = $request->attributes->get('_entreprise_id') 
                ?? $request->user()?->entreprise_id;
    $machineId = $request->query('machine_id');
    $chefId    = $request->query('chef_id');

    if ($chefId && $machineId === 'all') {
        // Toutes les machines du chef
        $machineIds = \App\Models\Affectation::where('utilisateur_id', $chefId)
                                             ->pluck('machine_id');
        $actionneurs = Actionneur::with(['capteurs', 'relais' , 'machine'])
                                 ->whereIn('machine_id', $machineIds)
                                 ->get();
   } elseif ($machineId) {
    $actionneurs = Actionneur::with(['capteurs', 'relais'])
                             ->where('machine_id', $machineId)
                             ->get()
                             ->map(function($a) {
                                 $a->operateur_id = \DB::table('actionneur_operateur')
                                     ->where('actionneur_id', $a->id)
                                     ->value('operateur_id');
                                 return $a;
                             });
 } else {
    $query = Actionneur::with(['capteurs', 'relais']);
    if ($entrepriseId) {
        $query->whereHas('machine', function($q) use ($entrepriseId) {
            $q->where('entreprise_id', $entrepriseId);
        });
    }
    $actionneurs = $query->get();
}

    return response()->json($actionneurs);
}

    // Créer un actionneur + relais automatique
    public function store(Request $request)
    {
        $request->validate([
            'machine_id'  => 'required|integer',
            'nom'         => 'required|string|max:150',
            'type'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'capteur_ids' => 'nullable|array',
        ]);

        // Créer l'actionneur
        $actionneur = Actionneur::create([
    'machine_id'    => $request->machine_id,
    'nom'           => $request->nom,
    'type'          => $request->type,
    'description'   => $request->description ?? '',
    'actif'         => 1,
    'entreprise_id' => $this->getEntrepriseId($request),
]);

        // Associer les capteurs
       // Créer et associer les capteurs
if ($request->has('capteurs') && is_array($request->capteurs)) {
    foreach ($request->capteurs as $cap) {
        // Chercher si ce capteur existe déjà sur cette machine
        $capteur = \App\Models\Capteur::where('machine_id', $request->machine_id)
                                      ->where('type', $cap['type'])
                                      ->first();
        if ($capteur) {
            $capteur->update([
                'seuil_min' => $cap['seuil_min'] ?? null,
                'seuil_max' => $cap['seuil_max'] ?? null,
            ]);
        } else {
            $capteur = \App\Models\Capteur::create([
                'machine_id' => $request->machine_id,
                'type'       => $cap['type'],
                'unite'      => $cap['unite'] ?? '',
                'seuil_min'  => $cap['seuil_min'] ?? null,
                'seuil_max'  => $cap['seuil_max'] ?? null,
                'actif'      => 1,
            ]);
        }
        $actionneur->capteurs()->attach($capteur->id);
    }
}

        // Créer le relais automatiquement
        $relais = Relai::create([
        'machine_id'    => $request->machine_id,
        'actionneur_id' => $actionneur->id,
        'nom'           => 'Relais — ' . $request->nom,
        'canal'         => 'Canal auto — ' . $actionneur->id,
        'etat'          => 0,
        ]);

        return response()->json([
            'actionneur' => $actionneur->load(['capteurs', 'relais']),
            'relais'     => $relais,
        ], 201);
    }

    // Supprimer un actionneur
    public function destroy($id)
    {
        $actionneur = Actionneur::findOrFail($id);
        // Le relais sera supprimé en cascade
        $actionneur->delete();
        return response()->json(['message' => 'Actionneur supprimé']);
    }
    // Actionneurs d'une machine filtrés par opérateur
public function parOperateur(Request $request)
{
    $operateurId  = $request->query('operateur_id');
    $actionneurId = $request->query('actionneur_id');

    // Cas 1 : tous les actionneurs d'un opérateur
    if ($operateurId) {
        $actionneurs = Actionneur::with(['capteurs', 'relais', 'machine'])
            ->whereHas('operateurs', function($q) use ($operateurId) {
                $q->where('utilisateurs.id', $operateurId);
            })
            ->get();
        return response()->json($actionneurs);
    }

    // Cas 2 : tous les opérateurs d'un actionneur
    if ($actionneurId) {
        $actionneur = Actionneur::with(['operateurs'])->find($actionneurId);
        if (!$actionneur) return response()->json([]);
        return response()->json($actionneur->operateurs);
    }

    return response()->json([]);
}

// Affecter un actionneur à un opérateur
public function affecter(Request $request, $id)
{
    $request->validate([
        'operateur_id' => 'required|integer',
        'affecte_par'  => 'nullable|integer',
    ]);

    $actionneur = Actionneur::findOrFail($id);

    $exists = \DB::table('actionneur_operateur')
                 ->where('actionneur_id', $id)
                 ->where('operateur_id', $request->operateur_id)
                 ->exists();

    if (!$exists) {
        \DB::table('actionneur_operateur')->insert([
            'actionneur_id' => $id,
            'operateur_id'  => $request->operateur_id,
            'affecte_par'   => $request->affecte_par,
            'affecte_le'    => now(),
        ]);
    }

    // Affecter automatiquement la machine
    \App\Models\Affectation::firstOrCreate([
        'utilisateur_id' => $request->operateur_id,
        'machine_id'     => $actionneur->machine_id,
    ], [
        'affecte_par' => $request->affecte_par,
    ]);

    return response()->json(['message' => 'Actionneur affecté']);
}
// Retirer affectation actionneur/opérateur
public function retirerAffectation(Request $request, $id)
{
    \DB::table('actionneur_operateur')
       ->where('actionneur_id', $id)
       ->where('operateur_id', $request->operateur_id)
       ->delete();

    return response()->json(['message' => 'Affectation retirée']);
}
public function update(Request $request, $id)
{
    $actionneur = \App\Models\Actionneur::findOrFail($id);

    $actionneur->update([
        'nom'         => $request->nom         ?? $actionneur->nom,
        'type'        => $request->type        ?? $actionneur->type,
        'description' => $request->description ?? $actionneur->description,
    ]);

    // Mettre à jour les capteurs associés
    if ($request->has('capteurs') && is_array($request->capteurs)) {

        // Détacher tous les capteurs de cet actionneur
        $actionneur->capteurs()->detach();

        foreach ($request->capteurs as $cap) {
            // ✅ Chercher si ce capteur existe déjà sur cette machine
            $capteur = \App\Models\Capteur::where('machine_id', $actionneur->machine_id)
                                          ->where('type', $cap['type'])
                                          ->first();

            if ($capteur) {
                // ✅ Le capteur existe → mettre à jour les seuils
                $capteur->update([
                    'seuil_min' => $cap['seuil_min'] ?? null,
                    'seuil_max' => $cap['seuil_max'] ?? null,
                ]);
            } else {
                // ✅ Le capteur n'existe pas → créer
                $capteur = \App\Models\Capteur::create([
                    'machine_id' => $actionneur->machine_id,
                    'type'       => $cap['type'],
                    'unite'      => $cap['unite'] ?? '',
                    'seuil_min'  => $cap['seuil_min'] ?? null,
                    'seuil_max'  => $cap['seuil_max'] ?? null,
                    'actif'      => 1,
                ]);
            }

            // ✅ Rattacher le capteur à cet actionneur
            $actionneur->capteurs()->attach($capteur->id);
        }
    }

    return response()->json($actionneur->load('capteurs', 'relais'));
}
}
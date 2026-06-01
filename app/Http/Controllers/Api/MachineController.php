<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Affectation;
use Illuminate\Http\Request;

class MachineController extends BaseController
{
    private function getMachineIds(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        if (!$user || $user->role === 'admin') return null;
        return Affectation::where('utilisateur_id', $user->id)->pluck('machine_id');
    }

   public function index(Request $request)
{
    $entrepriseId = $request->attributes->get('_entreprise_id') 
                ?? $request->user()?->entreprise_id;
$user = $request->user();

    $query = Machine::query();

    // Filtre tenant — chaque entreprise voit seulement ses machines
    if ($entrepriseId) {
        $query->where('entreprise_id', $entrepriseId);
    }

    // Filtre par rôle
    if ($user && $user->role === 'chef') {
        $machineIds = Affectation::where('utilisateur_id', $user->id)
                                 ->pluck('machine_id');
        $query->whereIn('id', $machineIds);
    } elseif ($user && $user->role === 'operateur') {
        $machineIds = \DB::table('actionneur_operateur')
                        ->where('operateur_id', $user->id)
                        ->join('actionneurs', 'actionneurs.id', '=', 'actionneur_operateur.actionneur_id')
                        ->pluck('actionneurs.machine_id')
                        ->unique();
        $query->whereIn('id', $machineIds);
    }

    return response()->json($query->get());
}
public function show(Request $request, $id)
{
    $entrepriseId = $request->attributes->get('_entreprise_id') ?? $request->user()?->entreprise_id;

    $machine = Machine::findOrFail($id);

    // Vérifier que la machine appartient à l'entreprise
    if ($entrepriseId && $machine->entreprise_id !== $entrepriseId) {
        return response()->json(['message' => 'Accès refusé'], 403);
    }

    return response()->json($machine);
}
public function store(Request $request)
{
    $request->validate([
        'nom'          => 'required|string|max:150',
        'localisation' => 'nullable|string',
        'topic_mqtt'   => 'nullable|string',
        'description'  => 'nullable|string',
        'statut'       => 'nullable|string',
    ]);

    $machine = Machine::create([
        'nom'           => $request->nom,
        'localisation'  => $request->localisation ?? '',
        'topic_mqtt'    => $request->topic_mqtt ?? '',
        'description'   => $request->description ?? '',
        'statut'        => $request->statut ?? 'EN SERVICE',
        'entreprise_id' => $request->attributes->get('_entreprise_id') ?? $request->user()?->entreprise_id, // ← vient du middleware
        'cree_par'      => $request->user()->id,
    ]);
    

    // Créer les actionneurs si fournis
    if ($request->has('actionneurs') && is_array($request->actionneurs)) {
        foreach ($request->actionneurs as $act) {
            $actionneur = \App\Models\Actionneur::create([
                'machine_id'    => $machine->id,
                'nom'           => $act['nom'],
                'type'          => $act['type'],
                'description'   => $act['description'] ?? '',
                'actif'         => 1,
                'entreprise_id' => $this->getEntrepriseId($request),
            ]);

            foreach ($act['capteurs'] ?? [] as $cap) {
                $capteur = \App\Models\Capteur::create([
                    'machine_id' => $machine->id,
                    'type'       => $cap['type'],
                    'unite'      => $cap['unite'] ?? '',
                    'seuil_min'  => $cap['seuil_min'] ?? null,
                    'seuil_max'  => $cap['seuil_max'] ?? null,
                    'actif'      => 1,
                ]);
                $actionneur->capteurs()->attach($capteur->id);
            }
            

            \App\Models\Relai::create([
                'machine_id'    => $machine->id,
                'actionneur_id' => $actionneur->id,
                'nom'           => 'Relais — ' . $act['nom'],
                'canal'         => 'Canal auto — ' . $actionneur->id,
                'etat'          => 0,
            ]);
        }
    }

    return response()->json($machine->load('actionneurs'), 201);
}



    public function update(Request $request, $id)
    {
        $machine = Machine::findOrFail($id);
        $machine->update([
            'nom'          => $request->nom          ?? $machine->nom,
            'localisation' => $request->localisation ?? $machine->localisation,
            'topic_mqtt'   => $request->topic_mqtt   ?? $machine->topic_mqtt,
            'description'  => $request->description  ?? $machine->description,
            'statut'       => $request->statut        ?? $machine->statut,
        ]);
        return response()->json($machine);
    }

    public function destroy($id)
    {
        $machine = Machine::findOrFail($id);
        if ($machine->est_statique) {
            return response()->json(['message' => 'Machine statique non supprimable'], 403);
        }
        $machine->delete();
        return response()->json(['message' => 'Machine supprimée']);
    }

    // Affecter une machine à un utilisateur (chef ou opérateur)
    public function affecter(Request $request, $id)
    {
        $request->validate([
            'utilisateur_id' => 'required|integer',
        ]);

        $exists = Affectation::where('utilisateur_id', $request->utilisateur_id)
                             ->where('machine_id', $id)
                             ->exists();

        if ($exists) {
            return response()->json(['message' => 'Déjà affecté'], 409);
        }

        Affectation::create([
            'utilisateur_id' => $request->utilisateur_id,
            'machine_id'     => $id,
            'affecte_par'    => $request->affecte_par ?? null,
        ]);

        return response()->json(['message' => 'Affectation créée']);
    }

    // Retirer une affectation
    public function retirerAffectation(Request $request, $id)
    {
        Affectation::where('utilisateur_id', $request->utilisateur_id)
                   ->where('machine_id', $id)
                   ->delete();
        return response()->json(['message' => 'Affectation retirée']);
    }

    // Lister les affectations d'une machine
    public function affectations($id)
    {
        $affectations = Affectation::where('machine_id', $id)
                                   ->with('utilisateur')
                                   ->get();
        return response()->json($affectations);
    }
    public function getConfig($topic)
{
    $machine = \App\Models\Machine::where('topic_mqtt', $topic)->first();
    
    if (!$machine) {
        return response()->json(['error' => 'Machine non trouvée'], 404);
    }

    $capteurs = \App\Models\Capteur::where('machine_id', $machine->id)
        ->select('id', 'type', 'seuil_min', 'seuil_max')
        ->get();

    $actionneurs = \App\Models\Actionneur::where('machine_id', $machine->id)
        ->select('id', 'nom', 'type')
        ->get();

    return response()->json([
        'machine_id'  => $machine->id,
        'nom'         => $machine->nom,
        'capteurs'    => $capteurs,
        'actionneurs' => $actionneurs,
    ]);
}
}
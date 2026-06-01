<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Utilisateur;
use Illuminate\Http\Request;

class AffectationController extends BaseController
{
    public function index(Request $request)
    {
        $entrepriseId = $this->getEntrepriseId($request);

        $query = Utilisateur::where('role', 'chef')->where('statut', 'ACTIF');
        if ($entrepriseId) $query->where('entreprise_id', $entrepriseId);
        $chefs = $query->get();

        $result = $chefs->map(function($chef) {
            $affectations = Affectation::where('utilisateur_id', $chef->id)
                                       ->with('machine')->get();
            return [
                'id'        => $chef->id,
                'nom'       => $chef->nom,
                'email'     => $chef->email,
                'initiales' => $chef->initiales,
                'statut'    => $chef->statut,
                'machines'  => $affectations->map(fn($a) => $a->machine),
            ];
        });

        return response()->json($result);
    }

    public function affecter(Request $request)
    {
        $request->validate([
            'utilisateur_id' => 'required|integer',
            'machine_id'     => 'required|integer',
        ]);

        $exists = Affectation::where('utilisateur_id', $request->utilisateur_id)
                             ->where('machine_id', $request->machine_id)
                             ->exists();

        if ($exists) return response()->json(['message' => 'Déjà existante'], 409);

        $affectation = Affectation::create([
            'utilisateur_id' => $request->utilisateur_id,
            'machine_id'     => $request->machine_id,
            'affecte_par'    => $request->affecte_par ?? null,
        ]);

        return response()->json($affectation, 201);
    }

    public function retirer($utilisateur_id, $machine_id)
    {
        Affectation::where('utilisateur_id', $utilisateur_id)
                   ->where('machine_id', $machine_id)
                   ->delete();
        return response()->json(['message' => 'Affectation retirée']);
    }

    public function mesOperateurs(Request $request)
{
    $user   = $request->user();
    $chefId = $request->query('chef_id') ?? $user->id;
    
    if (!$chefId) return response()->json([]);

    $operateurs = \App\Models\Utilisateur::where('chef_id', $chefId)
                                         ->where('role', 'operateur')
                                         ->where('statut', 'ACTIF')
                                         ->get();

    if ($operateurs->isEmpty()) {
        $machineIds   = \App\Models\Affectation::where('utilisateur_id', $chefId)->pluck('machine_id');
        $operateurIds = \App\Models\Affectation::whereIn('machine_id', $machineIds)
                                               ->pluck('utilisateur_id')->unique();
        $operateurs   = \App\Models\Utilisateur::whereIn('id', $operateurIds)
                                               ->where('role', 'operateur')
                                               ->where('statut', 'ACTIF')->get();
    }

    return response()->json($operateurs);
}

    public function addChef(Request $request)
{
    $entrepriseId = $this->getEntrepriseId($request);
    $entreprise   = \App\Models\Entreprise::find($entrepriseId);
    $slug         = $entreprise?->slug ?? '';

    $request->validate([
        'nom'          => 'required|string|max:100',
        'mot_de_passe' => 'required|string|min:6',
    ]);

    // Utiliser l'email fourni ou générer automatiquement
    if ($request->email) {
        $emailFinal = $request->email;
        // Vérifier le domaine
        $domain = explode('@', $emailFinal)[1] ?? '';
        if ($domain !== $slug . '.local') {
            return response()->json([
                'message' => "L'email doit être au format @{$slug}.local"
            ], 422);
        }
        // Vérifier unicité
        if (\App\Models\Utilisateur::where('email', $emailFinal)->exists()) {
            return response()->json(['message' => 'Email déjà utilisé'], 422);
        }
    } else {
        // Générer automatiquement
        $mots       = explode(' ', trim($request->nom));
        $prenom     = strtolower($mots[0]);
        $nomFamille = strtolower(implode('', array_slice($mots, 1)));
        $emailFinal = $prenom[0] . '.' . $nomFamille . '@' . $slug . '.local';

        $base  = $emailFinal;
        $count = 1;
        while (\App\Models\Utilisateur::where('email', $emailFinal)->exists()) {
            $emailFinal = $prenom[0] . '.' . $nomFamille . $count . '@' . $slug . '.local';
            $count++;
        }
    }

    // Calcul initiales
    $mots      = explode(' ', trim($request->nom));
    $initiales = '';
    foreach ($mots as $mot) {
        $initiales .= strtoupper(mb_substr($mot, 0, 1));
        if (strlen($initiales) >= 3) break;
    }

    $chef = \App\Models\Utilisateur::create([
        'nom'           => $request->nom,
        'email'         => $emailFinal,
        'mot_de_passe'  => \Illuminate\Support\Facades\Hash::make($request->mot_de_passe),
        'role'          => 'chef',
        'initiales'     => $initiales,
        'statut'        => 'ACTIF',
        'mdp_change'    => 0,
        'entreprise_id' => $entrepriseId,
    ]);

    return response()->json([
        'chef'         => $chef,
        'email_genere' => $emailFinal,
    ], 201);
}}
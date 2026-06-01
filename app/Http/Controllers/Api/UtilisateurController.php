<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UtilisateurController extends BaseController
{
    public function index(Request $request)
    {
        $entrepriseId = $this->getEntrepriseId($request);
        $chefId       = $request->query('chef_id');
        $role         = $request->query('role');

        $query = Utilisateur::query();

        if ($entrepriseId) {
            $query->where('entreprise_id', $entrepriseId);
        }
        if ($chefId) $query->where('chef_id', $chefId);
        if ($role)   $query->where('role', $role);

        return response()->json($query->get());
    }

    public function operateurs(Request $request)
    {
        $entrepriseId = $this->getEntrepriseId($request);
        $query = Utilisateur::where('role', 'operateur');
        if ($entrepriseId) $query->where('entreprise_id', $entrepriseId);
        return response()->json($query->get());
    }

    public function store(Request $request)
{
    $entrepriseId = $this->getEntrepriseId($request);
    $entreprise   = \App\Models\Entreprise::find($entrepriseId);
    $slug         = $entreprise?->slug ?? '';

    $request->validate([
        'nom'          => 'required|string|max:100',
        'mot_de_passe' => 'nullable|string|min:6',
        'role'         => 'required|in:admin,chef,operateur',
        'chef_id'      => 'nullable|integer',
        'email'        => 'nullable|email',
    ]);

    // ✅ Utiliser l'email du frontend s'il est fourni
    if ($request->filled('email')) {
        $emailFinal = $request->email;
        if (Utilisateur::where('email', $emailFinal)->exists()) {
            return response()->json(['message' => 'Email déjà utilisé'], 422);
        }
    } else {
        // Générer au format s.nom@slug.local
        $mots       = explode(' ', trim($request->nom));
        $prenom     = strtolower($mots[0]);
        $nomFamille = strtolower(implode('', array_slice($mots, 1)));

        // Nettoyer accents et caractères spéciaux
        $prenom     = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $prenom));
        $nomFamille = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nomFamille));

        $emailFinal = substr($prenom, 0, 1) . '.' . $nomFamille . '@' . $slug . '.local';

        $base  = $emailFinal;
        $count = 1;
        while (Utilisateur::where('email', $emailFinal)->exists()) {
            $emailFinal = substr($prenom, 0, 1) . '.' . $nomFamille . $count . '@' . $slug . '.local';
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

    $user = Utilisateur::create([
        'nom'           => $request->nom,
        'email'         => $emailFinal,
        'mot_de_passe'  => Hash::make($request->mot_de_passe ?? 'changeme123'),
        'role'          => $request->role,
        'initiales'     => $initiales,
        'statut'        => 'ACTIF',
        'mdp_change'    => 0,
        'chef_id'       => $request->role === 'operateur' ? $request->chef_id : null,
        'entreprise_id' => $entrepriseId,
    ]);

    // Si opérateur → affecter aux machines du chef
    if ($request->role === 'operateur' && $request->chef_id) {
        $machineIds = \App\Models\Affectation::where('utilisateur_id', $request->chef_id)
                                             ->pluck('machine_id');
        foreach ($machineIds as $machineId) {
            \App\Models\Affectation::firstOrCreate([
                'utilisateur_id' => $user->id,
                'machine_id'     => $machineId,
            ], ['affecte_par' => $request->chef_id]);
        }
    }

    return response()->json([
        'user'         => $user,
        'email_genere' => $emailFinal,
    ], 201);
}

    public function update(Request $request, $id)
    {
        $user         = Utilisateur::findOrFail($id);
        $entrepriseId = $this->getEntrepriseId($request);

        if ($entrepriseId && $user->entreprise_id !== $entrepriseId) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $data = $request->except('mot_de_passe', '_entreprise_id');
        if ($request->filled('mot_de_passe')) {
            $data['mot_de_passe'] = Hash::make($request->mot_de_passe);
        }

        $user->update($data);
        return response()->json($user);
    }

    public function destroy(Request $request, $id)
    {
        $user         = Utilisateur::findOrFail($id);
        $entrepriseId = $this->getEntrepriseId($request);

        if ($entrepriseId && $user->entreprise_id !== $entrepriseId) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $user->delete();
        return response()->json(['message' => 'Utilisateur supprimé']);
    }
}
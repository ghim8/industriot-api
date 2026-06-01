<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EntrepriseController extends BaseController
{
    // Liste toutes les entreprises
    public function index()
    {
        $entreprises = Entreprise::withCount('utilisateurs', 'machines')->get();
        return response()->json($entreprises);
    }

    // Créer une entreprise + son admin
    public function store(Request $request)
{
    $request->validate([
        'nom'                => 'required|string|max:150',
        'slug'               => 'required|string|unique:entreprises,slug',
        'email_contact'      => 'required|email',
        'admin_nom'          => 'required|string',
        'admin_mot_de_passe' => 'required|string|min:6',
    ]);

    $slug = strtolower($request->slug);

    // Créer l'entreprise
    $entreprise = Entreprise::create([
        'nom'           => $request->nom,
        'slug'          => $slug,
        'email_contact' => $request->email_contact,
        'telephone'     => $request->telephone ?? '',
        'adresse'       => $request->adresse ?? '',
        'actif'         => 1,
    ]);

    // Générer email admin automatiquement
    $prenomSlug = strtolower(explode(' ', trim($request->admin_nom))[0]);
    $emailAdmin = $prenomSlug . '@' . $slug . '.local';

    // Gérer les doublons d'email
    $base  = $emailAdmin;
    $count = 1;
    while (Utilisateur::where('email', $emailAdmin)->exists()) {
        $emailAdmin = str_replace('@', $count . '@', $base);
        $count++;
    }

    // Calculer les initiales
    $mots      = explode(' ', trim($request->admin_nom));
    $initiales = '';
    foreach ($mots as $mot) {
        $initiales .= strtoupper(mb_substr($mot, 0, 1));
        if (strlen($initiales) >= 2) break;
    }

    // Créer l'admin
    $admin = Utilisateur::create([
        'nom'           => $request->admin_nom,
        'email'         => $emailAdmin,
        'mot_de_passe'  => Hash::make($request->admin_mot_de_passe),
        'role'          => 'admin',
        'initiales'     => $initiales,
        'statut'        => 'ACTIF',
        'mdp_change'    => 0,
        'entreprise_id' => $entreprise->id,
    ]);

    return response()->json([
        'entreprise'   => $entreprise,
        'admin'        => $admin,
        'email_genere' => $emailAdmin,
    ], 201);
}

    // Activer / Désactiver une entreprise
    public function toggleActif($id)
    {
        $entreprise = Entreprise::findOrFail($id);
        $entreprise->actif = !$entreprise->actif;
        $entreprise->save();
        return response()->json($entreprise);
    }

    // Supprimer une entreprise
    public function destroy($id)
    {
        Entreprise::findOrFail($id)->delete();
        return response()->json(['message' => 'Entreprise supprimée']);
    }

    // Stats d'une entreprise
    public function stats($id)
    {
        $entreprise = Entreprise::with(['utilisateurs', 'machines'])->findOrFail($id);
        return response()->json([
            'entreprise'   => $entreprise,
            'nb_users'     => $entreprise->utilisateurs->count(),
            'nb_machines'  => $entreprise->machines->count(),
            'nb_admins'    => $entreprise->utilisateurs->where('role', 'admin')->count(),
            'nb_chefs'     => $entreprise->utilisateurs->where('role', 'chef')->count(),
            'nb_operateurs'=> $entreprise->utilisateurs->where('role', 'operateur')->count(),
        ]);
    }
}
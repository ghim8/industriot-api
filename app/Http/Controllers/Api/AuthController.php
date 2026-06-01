<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseController
{
    // Fonction helper en haut du controller
private function getClientIp(Request $request): string
{
    return $request->header('X-Real-IP')
        ?? $request->header('X-Forwarded-For')
        ?? $request->ip();
}
    // POST /api/login
   public function login(Request $request)
{
    $request->validate([
        'email'       => 'required|email',
        'mot_de_passe'=> 'required|string',
    ]);

    $user = Utilisateur::with('entreprise')
                       ->where('email', $request->email)
                       ->first();

    // Vérifications
    if (!$user || !\Hash::check($request->mot_de_passe, $user->mot_de_passe)) {
    \App\Models\Connexion::create([
        'email_tente'   => $request->email,
        'ip'            => $this->getClientIp($request), // ← fix
        'statut'        => 'ÉCHEC',
        'entreprise_id' => $user?->entreprise_id ?? null,
    ]);
    return response()->json(['message' => 'Email ou mot de passe incorrect'], 401);
}

    if ($user->statut !== 'ACTIF') {
        return response()->json(['message' => 'Compte inactif'], 403);
    }

    // Vérifier que l'entreprise est active (sauf super_admin)
    if ($user->role !== 'super_admin' && $user->entreprise && !$user->entreprise->actif) {
        return response()->json(['message' => 'Entreprise désactivée'], 403);
    }

    // Supprimer les anciens tokens pour éviter les conflits
$user->tokens()->delete();
$token = $user->createToken('auth_token')->plainTextToken;

    // Log connexion
    \App\Models\Connexion::create([
        'utilisateur_id' => $user->id,
        'email_tente'    => $user->email,
        'ip'             => $this->getClientIp($request), // ← fix
        'statut'         => 'SUCCÈS',
        'entreprise_id'  => $user->entreprise_id,
    ]);

    return response()->json([
        'token' => $token,
        'user'  => [
            'id'            => $user->id,
            'nom'           => $user->nom,
            'email'         => $user->email,
            'role'          => $user->role,
            'initiales'     => $user->initiales,
            'mdp_change'    => (int) $user->mdp_change,
            'entreprise_id' => $user->entreprise_id,
            'entreprise'    => $user->entreprise ? [
                'id'   => $user->entreprise->id,
                'nom'  => $user->entreprise->nom,
                'slug' => $user->entreprise->slug,
                'logo' => $user->entreprise->logo,
            ] : null,
        ]
    ]);
}
    // POST /api/logout
    public function logout(Request $request)
{
    try {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }
    } catch (\Exception $e) {
        // ignore
    }
    return response()->json(['message' => 'Déconnecté avec succès']);
}
    // GET /api/me
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
    public function changerMdp(Request $request)
{
    $request->validate([
        'utilisateur_id' => 'required|integer',
        'nouveau_mdp'    => 'required|string|min:6',
    ]);

    $user = \App\Models\Utilisateur::findOrFail($request->utilisateur_id);
    $user->mot_de_passe = \Hash::make($request->nouveau_mdp);
    $user->mdp_change   = 1;
    $user->save();

    return response()->json(['message' => 'Mot de passe changé avec succès']);
}
}
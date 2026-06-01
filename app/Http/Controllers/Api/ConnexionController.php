<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connexion;
use App\Models\Affectation;
use App\Models\Utilisateur;
use Illuminate\Http\Request;

class ConnexionController extends BaseController
{
  public function index(Request $request)
{
    $entrepriseId = $this->getEntrepriseId($request);
   $user = $request->user();


    $query = \App\Models\Connexion::with('utilisateur')
                                   ->orderBy('horodatage', 'desc');

    // Filtre tenant
    if ($entrepriseId) {
    $query->where(function($q) use ($entrepriseId) {
        $q->where('entreprise_id', $entrepriseId)
          ->orWhereNull('entreprise_id');
    });
}
    // Filtre par rôle
    if ($user?->role === 'chef') {
        $operateurIds = \App\Models\Utilisateur::where('chef_id', $user->id)
                                               ->where('role', 'operateur')
                                               ->pluck('id')
                                               ->push($user->id);
        $query->whereIn('utilisateur_id', $operateurIds);

    } elseif ($user?->role === 'operateur') {
        $query->where('utilisateur_id', $user->id)->limit(100);
    } else {
        $query->limit(200);
    }

    return response()->json($query->get());
}
   public function destroy($id)
{
    Connexion::findOrFail($id)->delete();
    return response()->json(['message' => 'Connexion supprimée']);
}

public function destroySelection(Request $request)
{
    $request->validate(['ids' => 'required|array']);
    Connexion::whereIn('id', $request->ids)->delete();
    return response()->json(['message' => 'Connexions supprimées']);
}
}
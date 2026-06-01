<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alerte;
use Illuminate\Http\Request;

class AlerteController extends BaseController
{
   public function index(Request $request)
{
    $entrepriseId = $this->getEntrepriseId($request);
    $user = $request->user();


    $query = \App\Models\Alerte::orderBy('cree_le', 'desc');

    if ($entrepriseId) {
    $query->where(function($q) use ($entrepriseId) {
        $q->where('entreprise_id', $entrepriseId)
          ->orWhereNull('entreprise_id');
    });
}

    // Filtre par rôle opérateur
    if ($user?->role === 'operateur') {
        $actionneurIds = \DB::table('actionneur_operateur')
                           ->where('operateur_id', $user->id)
                           ->pluck('actionneur_id');
        $capteurIds = \DB::table('actionneur_capteur')
                        ->whereIn('actionneur_id', $actionneurIds)
                        ->pluck('capteur_id');
        $query->whereIn('capteur_id', $capteurIds);
    }

    $alertes = $query->get()->map(function($alerte) {
        $actionneur = \DB::table('actionneur_capteur')
            ->join('actionneurs', 'actionneurs.id', '=', 'actionneur_capteur.actionneur_id')
            ->where('actionneur_capteur.capteur_id', $alerte->capteur_id)
            ->select('actionneurs.id', 'actionneurs.nom', 'actionneurs.type')
            ->first();
        $alerte->actionneur  = $actionneur;
        $alerte->machine_nom = \App\Models\Machine::find($alerte->machine_id)?->nom;
        return $alerte;
    });

    return response()->json($alertes);
}

   public function acquitter(Request $request, $id)
{
    $alerte = Alerte::findOrFail($id);
    $alerte->acquittee     = 1;
    $alerte->acquittee_par = $request->utilisateur_id ?? null;
    $alerte->acquittee_le  = now();
    $alerte->save();
    return response()->json(['message' => 'Alerte acquittée']);
}
public function destroy($id)
{
    \App\Models\Alerte::findOrFail($id)->delete();
    return response()->json(['message' => 'Alerte supprimée']);
}

public function destroySelection(Request $request)
{
    $request->validate(['ids' => 'required|array']);
    \App\Models\Alerte::whereIn('id', $request->ids)->delete();
    return response()->json(['message' => 'Alertes supprimées']);
}
}
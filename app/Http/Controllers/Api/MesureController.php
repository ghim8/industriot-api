<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mesure;
use App\Models\Capteur;
use App\Models\Actionneur;
use App\Models\Affectation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\AlerteMail;

class MesureController extends BaseController
{
    public function index(Request $request)
{
    $entrepriseId = $request->attributes->get('_entreprise_id') 
                ?? $request->user()?->entreprise_id;
    $machineId = $request->query('machine_id');
    $user = $request->user();


    if (!$user || $user->role === 'admin' || $user->role === 'chef') {
        $actionneurs = \App\Models\Actionneur::with(['capteurs'])
                                             ->where('machine_id', $machineId)
                                             ->where('actif', 1)
                                             ->get();
    } else {
        $actionneurIds = \DB::table('actionneur_operateur')
                            ->where('operateur_id', $user->id)
                            ->pluck('actionneur_id');
        $actionneurs = \App\Models\Actionneur::with(['capteurs'])
                                             ->whereIn('id', $actionneurIds)
                                             ->where('machine_id', $machineId)
                                             ->where('actif', 1)
                                             ->get();
    }

    $grouped = [];

    foreach ($actionneurs as $actionneur) {
        foreach ($actionneur->capteurs as $capteur) {
            $type = $capteur->type;

            $mesures = Mesure::where('capteur_id', $capteur->id)
                             ->where(function($q) use ($actionneur) {
                                 $q->where('actionneur_id', $actionneur->id)
                                   ->orWhereNull('actionneur_id');
                             })
                             ->orderBy('horodatage', 'desc')
                             ->limit(30)
                             ->get()
                             ->reverse()
                             ->values();

            if (!isset($grouped[$type])) $grouped[$type] = [];

            $grouped[$type][] = [
                'capteur'    => $capteur,
                'actionneur' => [
                    'id'   => $actionneur->id,
                    'nom'  => $actionneur->nom,
                    'type' => $actionneur->type,
                ],
                'mesures'  => $mesures,
                'derniere' => $mesures->last(),
            ];
        }
    }

    // Fallback — capteurs sans actionneur
    if (empty($grouped)) {
        $capteurs = Capteur::with(['machine'])
                           ->where('machine_id', $machineId)
                           ->where('actif', 1)
                           ->get();

        foreach ($capteurs as $capteur) {
            $type = $capteur->type;

            $mesures = Mesure::where('capteur_id', $capteur->id)
                             ->whereNull('actionneur_id')
                             ->orderBy('horodatage', 'desc')
                             ->limit(30)
                             ->get()
                             ->reverse()
                             ->values();

            if (!isset($grouped[$type])) $grouped[$type] = [];

            $grouped[$type][] = [
                'capteur'    => $capteur,
                'actionneur' => ['id' => null, 'nom' => $capteur->machine->nom ?? 'Machine', 'type' => ''],
                'mesures'    => $mesures,
                'derniere'   => $mesures->last(),
            ];
        }
    }

    return response()->json($grouped);
}

    public function store(Request $request)
    {
        $request->validate([
            'machine_id' => 'required|integer',
            'capteur_id' => 'required|integer',
            'valeur'     => 'required|numeric',
        ]);

        $capteur      = Capteur::with('machine')->findOrFail($request->capteur_id);
        $horseSeuil   = false;
        $seuilDepasse = null;

        if ($capteur->seuil_min !== null && $request->valeur < $capteur->seuil_min) {
            $horseSeuil = true; $seuilDepasse = $capteur->seuil_min;
        }
        if ($capteur->seuil_max !== null && $request->valeur > $capteur->seuil_max) {
            $horseSeuil = true; $seuilDepasse = $capteur->seuil_max;
        }

        $mesure = Mesure::create([
    'machine_id'    => $request->machine_id,
    'capteur_id'    => $request->capteur_id,
    'actionneur_id' => $request->actionneur_id ?? null,  // ← nouveau
    'valeur'        => $request->valeur,
    'hors_seuil'    => $horseSeuil ? 1 : 0,
]);

        if ($horseSeuil) {
            $niveau      = 'WARNING';
            if ($capteur->seuil_max !== null && $request->valeur > $capteur->seuil_max) {
                $depassement = ($request->valeur - $capteur->seuil_max) / max($capteur->seuil_max, 0.01) * 100;
                $niveau      = $depassement > 15 ? 'CRITIQUE' : 'WARNING';
            }
            $machineName = $capteur->machine->nom ?? 'Machine';
            $typeLabel   = ucfirst($capteur->type);

            $alerteExistante = \App\Models\Alerte::where('capteur_id', $request->capteur_id)
                                                 ->where('acquittee', 0)
                                                 ->where('niveau', $niveau)
                                                 ->exists();
            // Trouver l'actionneur du capteur
            $actionneurNom = \DB::table('actionneur_capteur')
                ->join('actionneurs', 'actionneurs.id', '=', 'actionneur_capteur.actionneur_id')
                ->where('actionneur_capteur.capteur_id', $request->capteur_id)
                ->value('actionneurs.nom');

            $messageAlerte = $actionneurNom
                ? "{$typeLabel} hors seuil — {$actionneurNom} ({$machineName})"
                : "{$typeLabel} hors seuil — {$machineName}";

            if (!$alerteExistante) {
                $nouvelleAlerte = \App\Models\Alerte::create([
                    'machine_id' => $request->machine_id,
                    'capteur_id' => $request->capteur_id,
                    'niveau'     => $niveau,
                    'message'    => $messageAlerte,
                    'valeur'     => $request->valeur,
                    'seuil'      => $seuilDepasse,
                    'acquittee'  => 0,
                    'entreprise_id' => $request->user()?->entreprise_id,
                ]);
                try {
                    $destinataire = env('ALERT_EMAIL', 'admin@usine.local');
                    Mail::to($destinataire)->send(new AlerteMail($nouvelleAlerte, $capteur->machine));
                } catch (\Exception $e) {
                    \Log::warning('Email alerte non envoyé : ' . $e->getMessage());
                }
            } else {
                \App\Models\Alerte::where('capteur_id', $request->capteur_id)
                                  ->where('acquittee', 0)
                                  ->where('niveau', $niveau)
                                  ->update(['valeur' => $request->valeur, 'seuil' => $seuilDepasse]);
            }
        }

        return response()->json($mesure, 201);
    }
}
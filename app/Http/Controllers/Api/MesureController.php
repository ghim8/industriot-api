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
        $user      = $request->user();

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

        $capteur    = Capteur::with('machine')->findOrFail($request->capteur_id);
        $valeur     = (float) $request->valeur;
        $horseSeuil = false;
        $niveau     = null;
        $seuilDepasse = null;

        // ── Logique Warning 90% / Critique 100% ──
        if ($capteur->seuil_max !== null) {
            $seuil100 = (float) $capteur->seuil_max;
            $seuil90  = $seuil100 * 0.9;

            if ($valeur >= $seuil100) {
                $horseSeuil   = true;
                $niveau       = 'CRITIQUE';
                $seuilDepasse = $seuil100;
            } elseif ($valeur >= $seuil90) {
                $horseSeuil   = true;
                $niveau       = 'WARNING';
                $seuilDepasse = round($seuil90, 2);
            }
        }

        // Vérifier seuil_min si défini
        if ($capteur->seuil_min !== null && $valeur < (float) $capteur->seuil_min) {
            $horseSeuil   = true;
            $niveau       = $niveau ?? 'WARNING';
            $seuilDepasse = $seuilDepasse ?? $capteur->seuil_min;
        }

        // ── Enregistrer la mesure ──
        $mesure = Mesure::create([
            'machine_id'    => $request->machine_id,
            'capteur_id'    => $request->capteur_id,
            'actionneur_id' => $request->actionneur_id ?? null,
            'valeur'        => $valeur,
            'hors_seuil'    => $horseSeuil ? 1 : 0,
        ]);

        // ── Gestion des alertes ──
        if ($horseSeuil && $niveau) {

            $machineName   = $capteur->machine->nom ?? 'Machine';
            $typeLabel     = ucfirst($capteur->type);
            $actionneurNom = \DB::table('actionneur_capteur')
                ->join('actionneurs', 'actionneurs.id', '=', 'actionneur_capteur.actionneur_id')
                ->where('actionneur_capteur.capteur_id', $request->capteur_id)
                ->value('actionneurs.nom');

            $messageAlerte = $actionneurNom
                ? "{$typeLabel} hors seuil — {$actionneurNom} ({$machineName})"
                : "{$typeLabel} hors seuil — {$machineName}";

            // ✅ Chercher UNE alerte existante non acquittée pour ce capteur + machine
            $alerteExistante = \App\Models\Alerte::where('capteur_id', $request->capteur_id)
                                                 ->where('machine_id', $request->machine_id)
                                                 ->where('acquittee', 0)
                                                 ->latest('cree_le')
                                                 ->first();

            // Log debug temporaire
            \Log::info('Alerte check', [
                'capteur_id'       => $request->capteur_id,
                'machine_id'       => $request->machine_id,
                'valeur'           => $valeur,
                'niveau'           => $niveau,
                'seuil85'          => $capteur->seuil_max * 0.85,
                'seuil100'         => $capteur->seuil_max,
                'alerte_existante' => $alerteExistante?->id,
            ]);

            if ($alerteExistante) {
                // ✅ Mettre à jour — escalade WARNING → CRITIQUE possible
                $alerteExistante->update([
                    'valeur'  => $valeur,
                    'seuil'   => $seuilDepasse,
                    'niveau'  => $niveau,
                    'message' => $messageAlerte,
                ]);

                // Email si escalade vers CRITIQUE
                if ($niveau === 'CRITIQUE' && $alerteExistante->getOriginal('niveau') !== 'CRITIQUE') {
                    try {
                        $destinataire = env('ALERT_EMAIL', 'admin@usine.local');
                        Mail::to($destinataire)->send(new AlerteMail($alerteExistante, $capteur->machine));
                    } catch (\Exception $e) {
                        \Log::warning('Email alerte non envoyé : ' . $e->getMessage());
                    }
                }

            } else {
                // ✅ Créer nouvelle alerte
                $nouvelleAlerte = \App\Models\Alerte::create([
                    'machine_id'    => $request->machine_id,
                    'capteur_id'    => $request->capteur_id,
                    'niveau'        => $niveau,
                    'message'       => $messageAlerte,
                    'valeur'        => $valeur,
                    'seuil'         => $seuilDepasse,
                    'acquittee'     => 0,
                    'entreprise_id' => $request->user()?->entreprise_id,
                ]);

                // Email uniquement pour CRITIQUE
                if ($niveau === 'CRITIQUE') {
                    try {
                        $destinataire = env('ALERT_EMAIL', 'admin@usine.local');
                        Mail::to($destinataire)->send(new AlerteMail($nouvelleAlerte, $capteur->machine));
                    } catch (\Exception $e) {
                        \Log::warning('Email alerte non envoyé : ' . $e->getMessage());
                    }
                }
            }

        } else {
            // ✅ Valeur revenue à la normale → auto-acquittement
            \App\Models\Alerte::where('capteur_id', $request->capteur_id)
                              ->where('machine_id', $request->machine_id)
                              ->where('acquittee', 0)
                              ->update([
                                  'acquittee'    => 1,
                                  'acquittee_le' => now(),
                              ]);
        }

        return response()->json($mesure, 201);
    }
}
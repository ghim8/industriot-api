<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Capteur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CapteurController extends BaseController
{
  public function index(Request $request)
{
    $entrepriseId = $request->attributes->get('_entreprise_id') 
                ?? $request->user()?->entreprise_id;
    
    $machineId = $request->query('machine_id');
    $user = $request->user();


    // Déterminer les actionneurs accessibles
    if (!$user || $user->role === 'admin') {
    $query = \App\Models\Actionneur::with(['capteurs.machine'])
                                   ->where('actif', 1);
    if ($entrepriseId) {
        $query->whereHas('machine', fn($q) => $q->where('entreprise_id', $entrepriseId));
    }
    if ($machineId) $query->where('machine_id', $machineId);
    $actionneurs = $query->get();

} elseif ($user->role === 'chef') {
    $machineIds = \App\Models\Affectation::where('utilisateur_id', $user->id)
                                         ->pluck('machine_id');
    $query = \App\Models\Actionneur::with(['capteurs.machine'])
                                   ->whereIn('machine_id', $machineIds)
                                   ->where('actif', 1);
    if ($machineId) $query->where('machine_id', $machineId);
    $actionneurs = $query->get();

} else {
    $actionneurIds = DB::table('actionneur_operateur')
                       ->where('operateur_id', $user->id)
                       ->pluck('actionneur_id');
    $query = \App\Models\Actionneur::with(['capteurs.machine'])
                                   ->whereIn('id', $actionneurIds)
                                   ->where('actif', 1);
    if ($machineId) $query->where('machine_id', $machineId);
    $actionneurs = $query->get();
}

    // Formater : un groupe par actionneur
    $grouped = $actionneurs->map(function($actionneur) {
        return [
            'actionneur' => $actionneur,
            'capteurs'   => $actionneur->capteurs,
        ];
    });

    return response()->json($grouped->values());
}
    public function store(Request $request)
    {
        $request->validate([
            'machine_id' => 'required|integer',
            'type' => 'required|in:temperature,humidite,courant,vibration,gaz,pression',
            'unite'      => 'required|string|max:20',
        ]);

        $capteur = Capteur::create([
            'machine_id' => $request->machine_id,
            'type'       => $request->type,
            'unite'      => $request->unite,
            'seuil_min'  => $request->seuil_min ?? null,
            'seuil_max'  => $request->seuil_max ?? null,
            'actif'      => 1,
        ]);

        return response()->json($capteur, 201);
    }

    public function update(Request $request, $id)
{
    $capteur = \App\Models\Capteur::findOrFail($id);
    $capteur->update($request->only(['type','unite','seuil_min','seuil_max','actif']));

    try {
        $mqttHost = config('mqtt.host');
        $mqttPort = (int) config('mqtt.port');
        $mqttUser = config('mqtt.username');
        $mqttPass = config('mqtt.password');

        if (!$mqttHost) throw new \Exception('MQTT_HOST manquant');

        $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)
            ->setUsername($mqttUser)
            ->setPassword($mqttPass)
            ->setUseTls(true)
            ->setTlsSelfSignedAllowed(true)
            ->setConnectTimeout(3);

        $mqtt = new \PhpMqtt\Client\MqttClient(
            $mqttHost, $mqttPort,
            'laravel-seuils-' . $id . '-' . time()
        );
        $mqtt->connect($connectionSettings);

        $machine = $capteur->machine;
        $topic   = $machine
            ? $machine->topic_mqtt . '/seuils'
            : 'usine/machine' . $capteur->machine_id . '/seuils';

        $payload = json_encode([
            'capteur_id' => $capteur->id,
            'type'       => $capteur->type,
            'seuil_min'  => (float) $capteur->seuil_min,
            'seuil_max'  => (float) $capteur->seuil_max,
        ]);

        $mqtt->publish($topic, $payload, \PhpMqtt\Client\MqttClient::QOS_AT_LEAST_ONCE);
        $mqtt->disconnect();

    } catch (\Exception $e) {
        \Log::warning('MQTT seuil failed: ' . $e->getMessage());
    }

    return response()->json($capteur);
}
public function updateSeuil(Request $request, $id)
{
    $capteur = \App\Models\Capteur::findOrFail($id);
    $capteur->seuil_min = $request->seuil_min;
    $capteur->seuil_max = $request->seuil_max;
    $capteur->save();

    // Publier le nouveau seuil vers l'ESP32 via MQTT
    try {
        $host     = env('MQTT_HOST');
        $port     = (int) env('MQTT_PORT', 8883);
        $username = env('MQTT_USERNAME');
        $password = env('MQTT_PASSWORD');

        $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password)
            ->setUseTls(true)
            ->setTlsSelfSignedAllowed(true)
            ->setConnectTimeout(5);

        $mqtt = new \PhpMqtt\Client\MqttClient(
            $host, $port, 'laravel-seuil-' . $id
        );
        $mqtt->connect($connectionSettings, false);

        // Topic selon la machine du capteur
        $machine = $capteur->machine;
        $topic   = $machine->topic_mqtt . '/seuils';

        $payload = json_encode([
            'type'      => $capteur->type,
            'seuil_min' => (float) $capteur->seuil_min,
            'seuil_max' => (float) $capteur->seuil_max,
        ]);

        $mqtt->publish($topic, $payload, 1);
        $mqtt->disconnect();

        \Log::info("Seuil publié → $topic : $payload");

    } catch (\Exception $e) {
        \Log::warning('MQTT seuil publish failed: ' . $e->getMessage());
    }

    return response()->json($capteur);
}
    public function destroy($id)
    {
        Capteur::findOrFail($id)->delete();
        return response()->json(['message' => 'Capteur supprimé']);
    }
}
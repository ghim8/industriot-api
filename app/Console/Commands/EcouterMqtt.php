<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class EcouterMqtt extends Command
{
    protected $signature   = 'mqtt:ecouter';
    protected $description = 'Écoute les topics MQTT et sauvegarde les mesures';

    public function handle()
    {
        $this->info('🚀 MQTT Listener démarré...');

        $connectionSettings = (new ConnectionSettings)
            ->setUsername(env('MQTT_USERNAME'))
            ->setPassword(env('MQTT_PASSWORD'))
            ->setUseTls(true)
            ->setTlsSelfSignedAllowed(true)
            ->setConnectTimeout(10)
            ->setKeepAliveInterval(60);

        $mqtt = new MqttClient(
            env('MQTT_HOST'),
            (int) env('MQTT_PORT', 8883),
            'laravel-listener-' . uniqid()
        );

        $mqtt->connect($connectionSettings, true);
        $this->info('✅ Connecté à HiveMQ');

        // S'abonner à tous les topics de mesures
        $mqtt->subscribe('usine/+/temperature', function($topic, $message) {
            $this->traiterMesure($topic, $message, 'temperature');
        }, 1);

        $mqtt->subscribe('usine/+/humidite', function($topic, $message) {
            $this->traiterMesure($topic, $message, 'humidite');
        }, 1);

        $mqtt->subscribe('usine/+/courant', function($topic, $message) {
            $this->traiterMesure($topic, $message, 'courant');
        }, 1);

        $mqtt->subscribe('usine/+/gaz', function($topic, $message) {
            $this->traiterMesure($topic, $message, 'gaz');
        }, 1);

        $mqtt->subscribe('usine/+/vibration', function($topic, $message) {
            $this->traiterMesure($topic, $message, 'vibration');
        }, 1);

        $this->info('📡 Abonné aux topics usine/+/...');

        // Boucle infinie
        $mqtt->loop(true);
    }

    private function traiterMesure($topic, $message, $type)
{
    $data = json_decode($message, true);
    if (!$data) {
        \Log::warning("MQTT JSON invalide: $message");
        return;
    }

    $this->info("📥 $topic → " . json_encode($data));

    try {
        $capteur = \App\Models\Capteur::find($data['capteur_id'] ?? null);
        if (!$capteur) {
            \Log::warning("Capteur introuvable: " . ($data['capteur_id'] ?? 'null'));
            return;
        }

        $valeur    = (float) $data['valeur'];
        $machine   = \App\Models\Machine::find($data['machine_id']);

        // ← Faire confiance au hors_seuil de l'ESP32
        // Accepter true, 1, "true", "1"
$horsSeuil = filter_var($data['hors_seuil'] ?? false, FILTER_VALIDATE_BOOLEAN);

// Si pas de valeur ESP32 → vérifier avec les seuils DB en backup
if (!$horsSeuil && $capteur->seuil_max !== null) {
    if ($valeur > (float)$capteur->seuil_max) $horsSeuil = true;
}
if (!$horsSeuil && $capteur->seuil_min !== null) {
    if ($valeur < (float)$capteur->seuil_min) $horsSeuil = true;
}

        // Sauvegarder la mesure
        \App\Models\Mesure::create([
            'machine_id'    => $data['machine_id'],
            'capteur_id'    => $data['capteur_id'],
            'actionneur_id' => $data['actionneur_id'] ?? null,
            'valeur'        => $valeur,
            'hors_seuil'    => $horsSeuil ? 1 : 0,
            'entreprise_id' => $machine?->entreprise_id,
        ]);

        $this->info("✅ Mesure sauvegardée — hors_seuil: " . ($horsSeuil ? 'OUI' : 'NON'));

        // Créer alerte si hors seuil
        if ($horsSeuil) {
            // Calculer niveau
            $niveau = 'CRITIQUE';
            if ($capteur->seuil_max !== null && $valeur > (float)$capteur->seuil_max) {
                $depassement = ($valeur - (float)$capteur->seuil_max) / max((float)$capteur->seuil_max, 0.01) * 100;
                $niveau = $depassement > 15 ? 'CRITIQUE' : 'WARNING';
            }

// Créer l'alerte
$nouvelleAlerte = \App\Models\Alerte::create([
    'machine_id'    => $data['machine_id'],
    'capteur_id'    => $capteur->id,
    'niveau'        => $niveau,
    'message'       => ucfirst($type) . " hors seuil — " . ($machine?->nom ?? 'Machine'),
    'valeur'        => $valeur,
    'seuil'         => $capteur->seuil_max ?? $capteur->seuil_min,
    'acquittee'     => 0,
    'entreprise_id' => $machine?->entreprise_id,
]);
$this->warn("⚠️ Alerte $niveau créée — $type = $valeur");

// Email — max 1 email par capteur toutes les 5 minutes
$derniereAlerteMail = \App\Models\Alerte::where('capteur_id', $capteur->id)
    ->where('cree_le', '>=', now()->subMinutes(5))
    ->count();

if ($derniereAlerteMail <= 1) {
    try {
        $destinataire = env('ALERT_EMAIL', 'azaizzrima@gmail.com');
        \Mail::to($destinataire)->send(new \App\Mail\AlerteMail($nouvelleAlerte, $machine));
        $this->info("📧 Email envoyé à $destinataire");
    } catch (\Exception $e) {
        $this->error("❌ Email non envoyé : " . $e->getMessage());
        \Log::error("Email alerte failed: " . $e->getMessage());
    }
} else {
    $this->info("📧 Email ignoré — déjà envoyé dans les 5 dernières minutes");
}
        }

    } catch (\Exception $e) {
        \Log::error("Erreur traitement mesure: " . $e->getMessage());
        $this->error("❌ Erreur: " . $e->getMessage());
    }
}
}
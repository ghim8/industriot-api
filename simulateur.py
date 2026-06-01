import paho.mqtt.client as mqtt
import requests
import json
import time
import random
from datetime import datetime

# ── Configuration ──────────────────────────────────────────
BROKER   = "127.0.0.1"
PORT     = 1883
API_URL  = "http://localhost:8000/api"
INTERVAL = 3

# Valeurs de base par type de capteur
BASE_VALEURS = {
    "temperature": {"base": 65,  "variation": 10, "seuil_max": 85},
    "humidite":    {"base": 55,  "variation": 15, "seuil_max": 80},
    "courant":     {"base": 12,  "variation": 5,  "seuil_max": 20},
    "vibration":   {"base": 0.5, "variation": 0.3,"seuil_max": 0.9},
    "gaz":         {"base": 120, "variation": 30, "seuil_max": 200},
    "pression":    {"base": 3.5, "variation": 1,  "seuil_max": 6},
}

def fetch_machines():
    try:
        res = requests.get(f"{API_URL}/machines", timeout=5)
        machines = res.json()
        result = []
        for m in machines:
            # Récupérer les actionneurs avec leurs capteurs
            act_res = requests.get(f"{API_URL}/actionneurs?machine_id={m['id']}", timeout=5)
            actionneurs = act_res.json()

            machine_capteurs = []
            for a in actionneurs:
                capteurs = a.get('capteurs', [])
                for c in capteurs:
                    cap = dict(c)
                    cap['actionneur_id']  = a['id']
                    cap['actionneur_nom'] = a['nom']
                    machine_capteurs.append(cap)

            # Fallback — capteurs directs sans actionneur
            if not machine_capteurs:
                cap_res = requests.get(f"{API_URL}/capteurs?machine_id={m['id']}", timeout=5)
                for g in cap_res.json():
                    for c in (g.get('capteurs') or []):
                        cap = dict(c)
                        cap['actionneur_id']  = None
                        cap['actionneur_nom'] = m['nom']
                        machine_capteurs.append(cap)

            if machine_capteurs:
                result.append({
                    "id":       m["id"],
                    "nom":      m["nom"],
                    "topic":    m.get("topic_mqtt") or f"usine/machine_{m['id']}",
                    "capteurs": machine_capteurs,
                })
        return result
    except Exception as e:
        print(f"❌ Erreur API : {e}")
        return []

def generer_valeur(capteur):
    cfg = BASE_VALEURS.get(capteur["type"], {"base": 50, "variation": 10, "seuil_max": 100})
    valeur = cfg["base"] + (random.random() - 0.5) * cfg["variation"] * 2
    
    seuil_max_raw = capteur.get("seuil_max")
    seuil_max = float(seuil_max_raw) if seuil_max_raw is not None else cfg["seuil_max"]
    
    if random.random() < 0.9:
        valeur = seuil_max * 1.1
    
    return round(valeur, 4)

def hors_seuil(valeur, capteur):
    seuil_min = capteur.get("seuil_min")
    seuil_max = capteur.get("seuil_max")
    if seuil_min is not None and valeur < float(seuil_min): return True
    if seuil_max is not None and valeur > float(seuil_max): return True
    return False

def main():
    print("=" * 55)
    print("  INDUSTRIOT — Simulateur IoT Dynamique")
    print("=" * 55)

    # Connexion MQTT
    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
    try:
        client.connect(BROKER, PORT, 60)
        client.loop_start()
        print("✅ MQTT connecté")
    except Exception as e:
        print(f"❌ MQTT erreur : {e}")
        return

    cycle     = 0
    machines  = []
    last_fetch = 0

    try:
        while True:
            # Recharger les machines toutes les 30 secondes
            if time.time() - last_fetch > 30:
                print("\n🔄 Chargement des machines depuis l'API...")
                machines = fetch_machines()
                last_fetch = time.time()
                print(f"   {len(machines)} machine(s) trouvée(s) avec capteurs\n")

            if not machines:
                print("⚠️  Aucune machine avec capteurs. Retry dans 10s...")
                time.sleep(10)
                last_fetch = 0
                continue

            cycle += 1
            print(f"📊 Cycle #{cycle} — {datetime.now().strftime('%H:%M:%S')}")

            for machine in machines:
                for capteur in machine["capteurs"]:
                    valeur = generer_valeur(capteur)
                    hs     = hors_seuil(valeur, capteur)

                    # Publier sur MQTT
                    topic   = f"{machine['topic']}/{capteur['type']}"
                    payload = json.dumps({
                        "machine_id":    machine["id"],
                        "capteur_id":    capteur["id"],
                        "actionneur_id": capteur.get("actionneur_id"),  # ← nouveau
                        "type":          capteur["type"],
                        "valeur":        valeur,
                        "unite":         capteur.get("unite", ""),
                        "hors_seuil":    hs,
                        "timestamp":     time.time(),
                    })
                    client.publish(topic, payload, qos=1)

                    # Sauvegarder en base via API
                    try:
                        requests.post(f"{API_URL}/mesures", json={
                            "machine_id": machine["id"],
                            "capteur_id": capteur["id"],
                            "actionneur_id": capteur.get("actionneur_id"),
                            "valeur":     valeur,
                        }, timeout=3)
                    except:
                        pass

                    status = "🔴 HORS SEUIL" if hs else "🟢 OK"
                    act_nom = capteur.get("actionneur_nom", "—")
                    print(f"  {machine['nom']:15} | {act_nom:12} | {capteur['type']:12} | {valeur:8.4f} | {status}")

            print(f"  ⏱️  Prochain cycle dans {INTERVAL}s...")
            time.sleep(INTERVAL)

    except KeyboardInterrupt:
        print("\n⛔ Simulateur arrêté.")
        client.loop_stop()
        client.disconnect()

if __name__ == "__main__":
    main()
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
  .container { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.10); }
  .header { padding: 24px 28px; color: #fff; }
  .header.critique { background: #c62828; }
  .header.warning  { background: #e65100; }
  .header-title { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
  .header-sub   { font-size: 13px; opacity: 0.85; }
  .body { padding: 24px 28px; }
  .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
  .info-label { color: #888; font-weight: 500; }
  .info-val   { color: #1a1a1a; font-weight: 600; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
  .badge.critique { background: #ffebee; color: #c62828; }
  .badge.warning  { background: #fff3e0; color: #e65100; }
  .footer { background: #f9f9f9; padding: 14px 28px; font-size: 11px; color: #aaa; text-align: center; border-top: 1px solid #eee; }
</style>
</head>
<body>
<div class="container">
  <div class="header {{ strtolower($alerte->niveau) }}">
    <div class="header-title">
      ⚠ Alerte {{ $alerte->niveau }} — {{ $machine->nom }}
    </div>
    <div class="header-sub">Plateforme IndustrioT · Supervision industrielle</div>
  </div>
  <div class="body">
    <div class="info-row">
      <span class="info-label">Machine</span>
      <span class="info-val">{{ $machine->nom }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Localisation</span>
      <span class="info-val">{{ $machine->localisation }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Message</span>
      <span class="info-val">{{ $alerte->message }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Niveau</span>
      <span class="info-val">
        <span class="badge {{ strtolower($alerte->niveau) }}">{{ $alerte->niveau }}</span>
      </span>
    </div>
    @if($alerte->valeur !== null)
    <div class="info-row">
      <span class="info-label">Valeur mesurée</span>
      <span class="info-val">{{ number_format($alerte->valeur, 3) }}</span>
    </div>
    @endif
    @if($alerte->seuil !== null)
    <div class="info-row">
      <span class="info-label">Seuil dépassé</span>
      <span class="info-val">{{ number_format($alerte->seuil, 3) }}</span>
    </div>
    @endif
    <div class="info-row">
      <span class="info-label">Date / Heure</span>
      <span class="info-val">{{ \Carbon\Carbon::parse($alerte->cree_le)->format('d/m/Y H:i:s') }}</span>
    </div>
  </div>
  <div class="footer">
    Ce message est généré automatiquement par IndustrioT · Ne pas répondre
  </div>
</div>
</body>
</html>
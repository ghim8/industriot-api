<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AlerteDepassementMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $emoji  = $this->data['type'] === 'max' ? '🔴' : '🟡';
        $niveau = $this->data['type'] === 'max' ? 'DÉPASSEMENT MAX' : 'VALEUR BASSE';

        return $this
            ->subject("{$emoji} ALERTE — {$this->data['machine']} ({$this->data['capteur']})")
            ->html($this->buildHtml($emoji, $niveau));
    }

    private function buildHtml(string $emoji, string $niveau): string
    {
        $couleur   = $this->data['type'] === 'max' ? '#e53e3e' : '#d97706';
        $couleurBg = $this->data['type'] === 'max' ? '#fff5f5' : '#fffbeb';
        $machine   = $this->data['machine'];
        $capteur   = $this->data['capteur'];
        $valeur    = $this->data['valeur'];
        $seuil     = $this->data['seuil'];
        $heure     = now()->format('d/m/Y à H:i:s');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">

        <!-- HEADER -->
        <tr>
          <td style="background:#1a1a2e;padding:24px 32px;">
            <table width="100%">
              <tr>
                <td>
                  <div style="font-size:11px;color:#00d4aa;letter-spacing:2px;font-weight:bold;margin-bottom:4px;">SYSTÈME DE SURVEILLANCE INDUSTRIELLE</div>
                  <div style="font-size:20px;color:#ffffff;font-weight:bold;">Rapport d'Alerte Automatique</div>
                </td>
                <td align="right">
                  <div style="background:{$couleur};color:#fff;padding:8px 16px;border-radius:20px;font-size:12px;font-weight:bold;white-space:nowrap;">{$emoji} {$niveau}</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- BANDEAU ALERTE -->
        <tr>
          <td style="background:{$couleurBg};border-left:4px solid {$couleur};padding:16px 32px;">
            <div style="font-size:14px;color:{$couleur};font-weight:bold;">
              ⚠️ Une valeur hors seuil a été détectée sur votre installation
            </div>
          </td>
        </tr>

        <!-- DETAILS -->
        <tr>
          <td style="padding:32px;">
            <div style="font-size:13px;color:#666;letter-spacing:1px;margin-bottom:16px;font-weight:bold;">DÉTAILS DE L'INCIDENT</div>

            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;">
              <tr style="background:#f8fafc;">
                <td style="padding:12px 16px;font-size:13px;color:#64748b;width:40%;border-bottom:1px solid #e2e8f0;">🏭 Machine</td>
                <td style="padding:12px 16px;font-size:13px;font-weight:bold;color:#1a202c;border-bottom:1px solid #e2e8f0;">{$machine}</td>
              </tr>
              <tr>
                <td style="padding:12px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">📡 Capteur</td>
                <td style="padding:12px 16px;font-size:13px;font-weight:bold;color:#1a202c;border-bottom:1px solid #e2e8f0;">{$capteur}</td>
              </tr>
              <tr style="background:#f8fafc;">
                <td style="padding:12px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">📊 Valeur mesurée</td>
                <td style="padding:12px 16px;font-size:16px;font-weight:bold;color:{$couleur};border-bottom:1px solid #e2e8f0;">{$valeur}</td>
              </tr>
              <tr>
                <td style="padding:12px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">⚠️ Seuil configuré</td>
                <td style="padding:12px 16px;font-size:13px;color:#1a202c;border-bottom:1px solid #e2e8f0;">{$seuil}</td>
              </tr>
              <tr style="background:#f8fafc;">
                <td style="padding:12px 16px;font-size:13px;color:#64748b;">🕐 Date / Heure</td>
                <td style="padding:12px 16px;font-size:13px;color:#1a202c;">{$heure}</td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ACTION REQUISE -->
        <tr>
          <td style="padding:0 32px 32px;">
            <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:16px;">
              <div style="font-size:13px;color:#166534;font-weight:bold;margin-bottom:6px;">✅ Action requise</div>
              <div style="font-size:13px;color:#166534;">Veuillez vérifier immédiatement l'état de la machine <b>{$machine}</b> et prendre les mesures correctives nécessaires.</div>
            </div>
          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td style="background:#f8fafc;padding:20px 32px;border-top:1px solid #e2e8f0;">
            <table width="100%">
              <tr>
                <td style="font-size:11px;color:#94a3b8;">Ce message est généré automatiquement par le système de surveillance industrielle.<br>Ne pas répondre à cet email.</td>
                <td align="right" style="font-size:11px;color:#94a3b8;">© 2026 Système Industriel</td>
              </tr>
            </table>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>

</body>
</html>
HTML;
    }
}
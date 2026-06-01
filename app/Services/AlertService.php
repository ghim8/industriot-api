<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\AlerteDepassementMail;

class AlertService
{
    public static function sendTelegram(string $message): void
    {
        $token  = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');

        Http::withoutVerifying()
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'    => $chatId,
                'text'       => $message,
                'parse_mode' => 'HTML',
            ]);
    }

    public static function sendAlert(array $data): void
    {
        $emoji  = $data['type'] === 'max' ? '🔴' : '🟡';
        $niveau = $data['type'] === 'max' ? 'DÉPASSEMENT MAX' : 'VALEUR BASSE';
        $heure  = now()->format('d/m/Y H:i:s');

        // Telegram
        $message = "{$emoji} <b>{$niveau}</b>\n"
                 . "🏭 Machine : <b>{$data['machine']}</b>\n"
                 . "📡 Capteur : {$data['capteur']}\n"
                 . "📊 Valeur  : <b>{$data['valeur']}</b>\n"
                 . "⚠️ Seuil   : {$data['seuil']}\n"
                 . "🕐 Heure   : {$heure}";

        self::sendTelegram($message);

        // Email professionnel
        Mail::to(env('ALERT_MAIL_TO'))->send(new AlerteDepassementMail($data));
    }
}
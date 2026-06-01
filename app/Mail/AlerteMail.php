<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AlerteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $alerte;
    public $machine;

    public function __construct($alerte, $machine)
    {
        $this->alerte  = $alerte;
        $this->machine = $machine;
    }

    public function build()
    {
        $sujet = "[{$this->alerte->niveau}] Alerte — {$this->machine->nom}";

        return $this->subject($sujet)->view('emails.alerte');
    }
}
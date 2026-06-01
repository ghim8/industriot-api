<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class JournalRelai extends Model
{
    protected $table      = 'journal_relais';
    public $timestamps    = false;

    protected $fillable = [
        'relais_id', 'utilisateur_id', 'ancien_etat', 'nouvel_etat', 'source'
    ];

public function utilisateur()
{
    return $this->belongsTo(\App\Models\Utilisateur::class, 'utilisateur_id');
}

public function relais()
{
    return $this->belongsTo(\App\Models\Relai::class, 'relais_id');
}
}
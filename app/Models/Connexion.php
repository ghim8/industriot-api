<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Connexion extends Model
{
    protected $table      = 'connexions';
    protected $primaryKey = 'id';
    public $timestamps    = false;

    protected $fillable = [
        'utilisateur_id', 'email_tente', 'ip', 'user_agent', 'statut', 'horodatage'
    ];
    public function utilisateur()
{
    return $this->belongsTo(\App\Models\Utilisateur::class, 'utilisateur_id');
}
}
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Actionneur extends Model
{
    protected $table   = 'actionneurs';
    public $timestamps = false;

    protected $fillable = [
        'machine_id', 'nom', 'type', 'description', 'actif'
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function capteurs()
    {
        return $this->belongsToMany(Capteur::class, 'actionneur_capteur', 'actionneur_id', 'capteur_id');
    }

    public function relais()
    {
        return $this->hasOne(Relai::class, 'actionneur_id');
        
    }
    public function operateurs()
{
    return $this->belongsToMany(
        Utilisateur::class,
        'actionneur_operateur',
        'actionneur_id',
        'operateur_id'
    );
}
}
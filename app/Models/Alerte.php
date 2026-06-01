<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Alerte extends Model
{
    protected $table = 'alertes';
    protected $fillable = ['machine_id', 'capteur_id', 'niveau', 'message', 'valeur', 'seuil', 'acquittee', 'acquittee_par', 'acquittee_le'];
    const CREATED_AT = 'cree_le';
    const UPDATED_AT = null;

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
}
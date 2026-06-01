<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    protected $table      = 'machines';
    protected $primaryKey = 'id';

    const CREATED_AT = 'cree_le';
    const UPDATED_AT = 'modifie_le';

    protected $fillable = [
    'nom', 'localisation', 'topic_mqtt', 'description',
    'statut', 'est_statique', 'cree_par', 'entreprise_id',
];
public function actionneurs()
{
    return $this->hasMany(\App\Models\Actionneur::class, 'machine_id');
}
}
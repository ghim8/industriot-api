<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Affectation extends Model
{
    protected $table    = 'affectations';
    const CREATED_AT    = 'affecte_le';
    const UPDATED_AT    = null;

    protected $fillable = [
        'utilisateur_id', 'machine_id', 'affecte_par'
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
    public function utilisateur()
{
    return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
}
}
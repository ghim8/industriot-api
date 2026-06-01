<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Utilisateur extends Authenticatable
{
    use HasApiTokens;

    protected $table      = 'utilisateurs';
    protected $primaryKey = 'id';

    const CREATED_AT = 'cree_le';
    const UPDATED_AT = 'modifie_le';

    protected $fillable = [
    'nom', 'email', 'mot_de_passe', 'role', 'initiales',
    'statut', 'mdp_change', 'chef_id', 'entreprise_id',
];

    protected $hidden = ['mot_de_passe'];

    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    public function entreprise()
{
    return $this->belongsTo(Entreprise::class, 'entreprise_id');
}
}
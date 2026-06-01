<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entreprise extends Model
{
    public $timestamps = false;
    protected $table   = 'entreprises';
    protected $fillable = [
        'nom', 'slug', 'email_contact',
        'telephone', 'adresse', 'logo', 'actif',
    ];

    public function utilisateurs()
    {
        return $this->hasMany(Utilisateur::class, 'entreprise_id');
    }

    public function machines()
    {
        return $this->hasMany(Machine::class, 'entreprise_id');
    }

    public function actionneurs()
    {
        return $this->hasMany(Actionneur::class, 'entreprise_id');
    }
}
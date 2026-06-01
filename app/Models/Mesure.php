<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Mesure extends Model
{
    protected $table      = 'mesures';
    public $timestamps    = false;

    const CREATED_AT      = 'horodatage';

    protected $fillable = ['machine_id', 'capteur_id', 'actionneur_id', 'valeur', 'hors_seuil'];
}
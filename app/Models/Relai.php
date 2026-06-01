<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Relai extends Model
{
    protected $table = 'relais';
    protected $fillable = ['machine_id', 'actionneur_id', 'nom', 'canal', 'etat'];
    public $timestamps = false;

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
    public function actionneur()
{
    return $this->belongsTo(Actionneur::class, 'actionneur_id');
}
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $fillable = [
        'empresa_id',
        'nombre',
        'hora_inicio',
        'hora_fin',
        'tolerancia_entrada',
        'tolerancia_salida',
        'minutos_almuerzo',
    ];

    protected $casts = [
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'empleado_turno');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    use HasFactory;

    protected $fillable = [
        'usuario_id',
        'empresa_id',
        'sucursal_id',
        'fecha',
        'hora_entrada',
        'hora_salida',
        'estado',

        // Campos de cálculo
        'minutos_atraso',
        'minutos_salida_anticipada',
        'minutos_horas_extra',
        'minutos_trabajados',
        'estado_jornada',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_entrada' => 'datetime:H:i:s',
        'hora_salida' => 'datetime:H:i:s',
        'minutos_atraso' => 'integer',
        'minutos_salida_anticipada' => 'integer',
        'minutos_horas_extra' => 'integer',
        'minutos_trabajados' => 'integer',
    ];
}

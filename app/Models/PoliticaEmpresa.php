<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoliticaEmpresa extends Model
{
    use HasFactory;

    // Tu tabla NO sigue el plural por defecto "politica_empresas"
    protected $table = 'politicas_empresa';

    protected $fillable = [
        'empresa_id',
        'jornada_diaria_horas',
        'minutos_tolerancia_atraso',
        'minutos_tolerancia_salida',
        'minutos_almuerzo',
        'minutos_descanso',
        'cantidad_descansos_permitidos',
        'permite_acumular_descansos',
        'descuenta_permiso_sin_goce',
        'max_horas_extra_por_dia',
        'politica_redondeo_tiempos',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}

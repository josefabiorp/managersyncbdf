<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Descanso extends Model
{
    use HasFactory;

    protected $table = 'descansos';

    protected $fillable = [
        'usuario_id',
        'empresa_id',
        'sucursal_id',
        'tipo',
        'hora_inicio',
        'hora_fin',
        'estado',
        'cerrado_por',
        'nota',
    ];

    // RELACIONES
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cerradoPor()
    {
        return $this->belongsTo(Usuario::class, 'cerrado_por');
    }
}

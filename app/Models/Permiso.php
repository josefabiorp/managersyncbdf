<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    use HasFactory;

    protected $fillable = [
        'usuario_id',
        'empresa_id',
        'sucursal_id',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'hora_inicio',
        'hora_fin',
        'motivo',
        'estado',
        'aprobado_por',
        'respuesta_admin',
    ];

    // Usuario que solicitó el permiso
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    // Sucursal del usuario
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    // Admin que aprobó o rechazó
    public function aprobador()
    {
        return $this->belongsTo(Usuario::class, 'aprobado_por');
    }

    // ⭐ RELACIÓN QUE FALTA (CRÍTICA)
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }


   

}

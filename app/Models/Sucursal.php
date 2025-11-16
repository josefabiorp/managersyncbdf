<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    use HasFactory;

    protected $table = 'sucursales';

    /**
     * ===================================================
     *  CAMPOS ASIGNABLES (mass assignment)
     * ===================================================
     */
    protected $fillable = [
        'empresa_id',
        'nombre',
        'codigo',
        'direccion',
        'telefono',
        'latitud',
        'longitud',
        'mapa_url',
        'activa',
    ];

    /**
     * ===================================================
     *  CASTS Y CONFIGURACIONES AVANZADAS
     * ===================================================
     */
    protected $casts = [
        'latitud'  => 'float',
        'longitud' => 'float',
        'activa'   => 'boolean',
    ];

    /**
     * ===================================================
     *  RELACIONES
     * ===================================================
     */

    // 🔗 Una sucursal pertenece a una empresa
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // 👥 Una sucursal puede tener muchos usuarios
    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'sucursal_id');
    }

    // 🕒 Relación con asistencias (si aplica)
    public function asistencias()
    {
        return $this->hasMany(Asistencia::class, 'sucursal_id');
    }

    /**
     * ===================================================
     *  ACCESORES PERSONALIZADOS
     * ===================================================
     */

    // Genera automáticamente la URL del mapa si no existe
    public function getMapaUrlAttribute($value)
    {
        if ($value) {
            return $value;
        }

        if ($this->latitud && $this->longitud) {
            return sprintf('https://www.google.com/maps?q=%s,%s', $this->latitud, $this->longitud);
        }

        return null;
    }

    // Devuelve una ubicación formateada
    public function getUbicacionAttribute()
    {
        if ($this->latitud && $this->longitud) {
            return "{$this->latitud}, {$this->longitud}";
        }
        return 'No definida';
    }
}

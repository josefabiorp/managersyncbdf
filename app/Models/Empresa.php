<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresas';

    protected $fillable = [
        'nombre',
        'telefono',
        'correo',
        'codigo_actividad',
        'descripcion',
        'cedula_empresa',
        'provincia',
        'canton',
        'distrito',
        'otras_senas',
        'empresa',
        'logo',
    ];

    /**
     * 🔵 RELACIÓN CORRECTA: una empresa tiene UNA política
     * Usa el modelo REAL: PoliticaEmpresa (singular)
     */
    public function politica()
    {
        return $this->hasOne(\App\Models\PoliticaEmpresa::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function proveedores()
    {
        return $this->hasMany(Proveedor::class);
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }

    public function facturas()
    {
        return $this->hasMany(Factura::class);
    }
}

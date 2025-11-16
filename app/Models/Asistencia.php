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
];



    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');

        
    }

    public function empresa() {
    return $this->belongsTo(Empresa::class);


    
}
public function sucursal() {
    return $this->belongsTo(Sucursal::class);
}


}

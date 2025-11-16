<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'usuario_id',
        'accion',
        'codigo_qr',
        'fecha_hora',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

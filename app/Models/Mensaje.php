<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mensaje extends Model
{
    use HasFactory;

    protected $table = 'mensajes';

   protected $fillable = [
    'empresa_id',
    'sucursal_id',
    'remitente_id',
    'destinatario_id',
    'contenido', // ✔ NOMBRE REAL DE TU MIGRACIÓN
    'leido',
    'tipo',
    'adjunto_url',
    'leido_en',
    'eliminado_para_remitente',
    'eliminado_para_destinatario',
];

    protected $hidden = [
        'updated_at',
        'eliminado_para_remitente',
        'eliminado_para_destinatario',
    ];

    protected $casts = [
        'leido' => 'boolean',
        'leido_en' => 'datetime',
        'eliminado_para_remitente' => 'datetime',
        'eliminado_para_destinatario' => 'datetime',
        'created_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function remitente()
    {
        return $this->belongsTo(Usuario::class, 'remitente_id');
    }

    public function destinatario()
    {
        return $this->belongsTo(Usuario::class, 'destinatario_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeEntreUsuarios($query, int $a, int $b)
    {
        return $query
            ->where(function ($q) use ($a, $b) {
                $q->where('remitente_id', $a)
                  ->where('destinatario_id', $b);
            })
            ->orWhere(function ($q) use ($a, $b) {
                $q->where('remitente_id', $b)
                  ->where('destinatario_id', $a);
            })
            ->orderBy('created_at', 'asc');
    }

    public function scopeNoLeidosPara($query, int $userid)
    {
        return $query->where('destinatario_id', $userid)
                     ->where('leido', false);
    }

    public function scopeVisiblesPara($query, int $userid)
    {
        return $query->where(function ($q) use ($userid) {
            $q->where(function ($sub) use ($userid) {
                $sub->where('remitente_id', $userid)
                    ->whereNull('eliminado_para_remitente');
            })->orWhere(function ($sub) use ($userid) {
                $sub->where('destinatario_id', $userid)
                    ->whereNull('eliminado_para_destinatario');
            });
        });
    }

    public function scopeInboxDe($query, int $userid)
    {
        return $query->where('destinatario_id', $userid);
    }
}

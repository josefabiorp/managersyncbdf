<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nombre',
        'email',
        'cedula',
        'role',
        'password',
        'profile_image',
           'sucursal_id',

        'empresa_id',

    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


public function asistencias()
{
    return $this->hasMany(Asistencia::class, 'usuario_id');
}

public function descansos()
{
    return $this->hasMany(Descanso::class, 'usuario_id');
}

public function turnos()
{
    return $this->belongsToMany(\App\Models\Turno::class, 'empleado_turno');
}

/**
 * Si solo quieres manejar 1 turno activo por empleado:
 */
public function turnoActual()
{
    return $this->turnos()->first(); // o ->latest('empleado_turno.created_at')->first();
}



public function empresa()
{
    return $this->belongsTo(Empresa::class, 'empresa_id');
}

public function sucursal()
{
    return $this->belongsTo(Sucursal::class, 'sucursal_id');
}







}

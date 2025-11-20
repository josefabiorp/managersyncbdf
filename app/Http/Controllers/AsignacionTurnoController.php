<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Models\Usuario;
use Illuminate\Http\Request;

class AsignacionTurnoController extends Controller
{
    public function asignar(Request $request, Usuario $usuario)
    {
        $user = $request->user();

        if ($usuario->empresa_id !== $user->empresa_id) {
            abort(403, 'No autorizado para asignar turnos a este usuario.');
        }

        $data = $request->validate([
            'turno_id' => ['required', 'exists:turnos,id'],
        ]);

        $turno = Turno::findOrFail($data['turno_id']);

        if ($turno->empresa_id !== $user->empresa_id) {
            abort(403, 'No autorizado: el turno no pertenece a tu empresa.');
        }

        // Si solo manejas 1 turno por usuario:
        $usuario->turnos()->sync([$turno->id]);

        return response()->json([
            'message' => 'Turno asignado correctamente.',
        ]);
    }

    public function obtenerTurno(Request $request, Usuario $usuario)
    {
        $user = $request->user();

        if ($usuario->empresa_id !== $user->empresa_id) {
            abort(403, 'No autorizado para ver turnos de este usuario.');
        }

        $turno = $usuario->turnos()->first();

        return response()->json([
            'turno' => $turno,
        ]);
    }
}

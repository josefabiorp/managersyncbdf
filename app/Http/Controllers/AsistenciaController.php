<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Usuario;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    /** ======================
     * LISTAR TODAS LAS ASISTENCIAS
     * ====================== */
    public function index()
    {
        return Asistencia::with(['usuario', 'empresa', 'sucursal'])->get();
    }

    /** ======================
     * REGISTRAR ENTRADA
     * ====================== */
    public function marcarEntrada(Request $request)
    {
        $usuario = Usuario::find($request->usuario_id);
        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $fecha = now()->toDateString();
        $asistencia = Asistencia::where('usuario_id', $usuario->id)
            ->where('fecha', $fecha)
            ->first();

        if ($asistencia) {
            return response()->json([
                'message' => 'Entrada ya registrada',
                'estado' => is_null($asistencia->hora_salida) ? 'presente' : 'fuera',
                'asistencia' => $asistencia
            ]);
        }

        // ✅ Crear nueva asistencia con empresa y sucursal
        $asistencia = Asistencia::create([
            'usuario_id'   => $usuario->id,
            'empresa_id'   => $usuario->empresa_id ?? null,
            'sucursal_id'  => $usuario->sucursal_id ?? null,
            'fecha'        => $fecha,
            'hora_entrada' => now()->toTimeString(),
            'estado'       => 'presente',
        ]);

        return response()->json([
            'message' => 'Entrada registrada correctamente',
            'estado'  => 'presente',
            'asistencia' => $asistencia
        ]);
    }

    /** ======================
     * REGISTRAR SALIDA
     * ====================== */
    public function marcarSalida(Request $request)
    {
        $asistencia = Asistencia::where('usuario_id', $request->usuario_id)
            ->where('fecha', now()->toDateString())
            ->first();

        if (!$asistencia) {
            return response()->json(['error' => 'No hay entrada registrada'], 404);
        }

        if ($asistencia->hora_salida) {
            return response()->json([
                'error' => 'Salida ya registrada',
                'estado' => 'fuera'
            ], 400);
        }

        $asistencia->update([
            'hora_salida' => now()->toTimeString(),
            'estado' => 'fuera',
        ]);

        return response()->json([
            'message' => 'Salida registrada correctamente',
            'estado'  => 'fuera',
            'asistencia' => $asistencia
        ]);
    }

    /** ======================
     * ESTADO ACTUAL
     * ====================== */
   public function estadoActual($usuario_id)
{
    try {
        $userAuth = auth()->user();

        if (!$userAuth) {
            return response()->json([
                'error' => 'Token inválido o sesión expirada. Debe autenticarse primero.'
            ], 401);
        }

        $query = Asistencia::where('usuario_id', $usuario_id)
            ->whereDate('fecha', now()->toDateString());

        $rol = strtolower($userAuth->role ?? $userAuth->rol ?? '');

        // 🔒 Si no es admin, filtrar solo por su empresa (no exigir sucursal si no la tiene)
        if (!in_array($rol, ['admin', 'administrador'])) {
            $query->where('empresa_id', $userAuth->empresa_id);

            if (!empty($userAuth->sucursal_id)) {
                $query->where('sucursal_id', $userAuth->sucursal_id);
            }
        }

        $asistencia = $query->first();

        if (!$asistencia) {
            return response()->json([
                'estado' => 'sin_entrada',
                'message' => 'No hay registro de asistencia para hoy.'
            ], 200);
        }

        $estado = match (true) {
            $asistencia->hora_entrada && !$asistencia->hora_salida => 'presente',
            $asistencia->hora_entrada && $asistencia->hora_salida => 'fuera',
            default => 'sin_entrada',
        };

        return response()->json([
            'estado' => $estado,
            'asistencia' => $asistencia
        ], 200);

    } catch (\Throwable $e) {
        return response()->json([
            'error' => 'Error interno en estadoActual',
            'details' => $e->getMessage(),
        ], 500);
    }
}

    /** ======================
     * OBTENER POR RANGO
     * ====================== */
    public function obtenerPorRango(Request $request)
    {
        $usuario_id = $request->query('usuario_id');
        $from = $request->query('from');
        $to = $request->query('to');
        $userAuth = auth()->user();

        if (!$usuario_id || !$from || !$to) {
            return response()->json(['error' => 'Faltan parámetros'], 400);
        }

        try {
            $query = Asistencia::where('usuario_id', $usuario_id)
                ->whereBetween('fecha', [$from, $to])
                ->orderBy('fecha', 'desc');

            $rol = strtolower($userAuth->role ?? $userAuth->rol ?? '');

            // 🔒 Si no es admin, limitar por empresa y sucursal
            if (!in_array($rol, ['admin', 'administrador'])) {
                $query->where('empresa_id', $userAuth->empresa_id)
                      ->where('sucursal_id', $userAuth->sucursal_id);
            } else {
                $query->where('empresa_id', $userAuth->empresa_id);
            }

            $asistencias = $query->get();

            return response()->json([
                'usuario_id' => $usuario_id,
                'from' => $from,
                'to' => $to,
                'total' => $asistencias->count(),
                'data' => $asistencias
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

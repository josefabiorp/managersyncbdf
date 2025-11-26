<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Carbon\Carbon;

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

        // Ya existe entrada
        $asistencia = Asistencia::where('usuario_id', $usuario->id)
            ->where('fecha', $fecha)
            ->first();

        if ($asistencia) {
            return response()->json([
                'message' => 'Entrada ya registrada',
                'estado'  => $asistencia->hora_salida ? 'fuera' : 'presente',
                'asistencia' => $asistencia
            ]);
        }

        // Crear registro
        $horaEntradaReal = now()->format("H:i:s");

        $asistencia = Asistencia::create([
            'usuario_id'   => $usuario->id,
            'empresa_id'   => $usuario->empresa_id,
            'sucursal_id'  => $usuario->sucursal_id,
            'fecha'        => $fecha,
            'hora_entrada' => $horaEntradaReal,
            'estado'       => 'presente',
        ]);

        // Turno asignado
        $turno = $usuario->turnoActual();
        if (!$turno) {
            return response()->json([
                'message' => 'Entrada registrada sin turno asignado',
                'estado' => 'presente',
                'asistencia' => $asistencia
            ]);
        }

        // Políticas
        $politicas = $usuario->empresa->politicasEmpresa ?? null;
        if (!$politicas) {
            return response()->json([
                'message' => 'Entrada registrada (sin políticas configuradas)',
                'estado' => 'presente',
                'asistencia' => $asistencia
            ]);
        }

        // Cálculo atraso
        $horaEsperada = Carbon::parse($turno->hora_inicio);
        $horaReal     = Carbon::parse($horaEntradaReal);

        $diferenciaMin = $horaEsperada->diffInMinutes($horaReal, false);
        $tolerancia    = $politicas->minutos_tolerancia_atraso ?? 0;

        $minutosAtraso = ($diferenciaMin > $tolerancia) ? $diferenciaMin : 0;

        $asistencia->minutos_atraso = $minutosAtraso;
        $asistencia->estado_jornada = $minutosAtraso > 0 ? 'tarde' : 'normal';
        $asistencia->save();

        return response()->json([
            'message' => 'Entrada registrada correctamente',
            'estado'  => 'presente',
            'asistencia' => $asistencia,
            'turno_usado' => $turno->nombre,
            'politica_tolerancia' => $tolerancia
        ]);
    }

    /** ======================
     * REGISTRAR SALIDA
     * ====================== */
    public function marcarSalida(Request $request)
    {
        $usuario = Usuario::find($request->usuario_id);
        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $fecha = now()->toDateString();

        $asistencia = Asistencia::where('usuario_id', $usuario->id)
            ->where('fecha', $fecha)
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

        $horaSalidaReal = now()->format("H:i:s");
        $asistencia->hora_salida = $horaSalidaReal;

        // Turno asignado
        $turno = $usuario->turnoActual();
        if (!$turno) {
            $asistencia->estado = 'fuera';
            $asistencia->save();

            return response()->json([
                'message' => 'Salida registrada sin turno asignado',
                'estado'  => 'fuera',
                'asistencia' => $asistencia
            ]);
        }

        // Políticas
        $politicas = $usuario->empresa->politicasEmpresa ?? null;
        if (!$politicas) {
            $asistencia->estado = 'fuera';
            $asistencia->save();

            return response()->json([
                'message' => 'Salida registrada (sin políticas configuradas)',
                'estado'  => 'fuera',
                'asistencia' => $asistencia
            ]);
        }

        // ===============================
        // SALIDA ANTICIPADA
        // ===============================
        $horaEsperadaSalida = Carbon::parse($turno->hora_fin);
        $horaRealSalida     = Carbon::parse($horaSalidaReal);

        $toleranciaSalida = $politicas->minutos_tolerancia_salida ?? 0;
        $minutosSalidaAnticipada = 0;

        if ($horaRealSalida->lt($horaEsperadaSalida->copy()->subMinutes($toleranciaSalida))) {
            $minutosSalidaAnticipada = $horaEsperadaSalida->diffInMinutes($horaRealSalida);
        }

        // ===============================
        // HORAS EXTRA
        // ===============================
        $minutosHorasExtra = 0;
        if ($horaRealSalida->gt($horaEsperadaSalida)) {
            $minutosHorasExtra = $horaRealSalida->diffInMinutes($horaEsperadaSalida);

            $maxExtra = $politicas->max_horas_extra_por_dia;
            if ($maxExtra) {
                $minutosHorasExtra = min($minutosHorasExtra, $maxExtra * 60);
            }
        }

        // ===============================
        // TIEMPO TRABAJADO REAL
        // ===============================
        $horaEntradaReal = Carbon::parse($asistencia->hora_entrada);
        $minutosTrabajados = $horaRealSalida->diffInMinutes($horaEntradaReal);

        // Estado final
        $estadoJornada =
            $minutosSalidaAnticipada > 0 ? 'incompleta'
            : ($minutosHorasExtra > 0 ? 'extra'
            : 'completa');

        // Guardar
        $asistencia->minutos_salida_anticipada = $minutosSalidaAnticipada;
        $asistencia->minutos_horas_extra       = $minutosHorasExtra;
        $asistencia->minutos_trabajados        = $minutosTrabajados;
        $asistencia->estado_jornada            = $estadoJornada;
        $asistencia->estado                    = 'fuera';
        $asistencia->save();

        return response()->json([
            'message' => 'Salida registrada correctamente',
            'estado'  => 'fuera',
            'asistencia' => $asistencia,
            'turno_usado' => $turno->nombre,
            'politicas' => [
                'tolerancia_salida' => $toleranciaSalida,
                'max_extra'         => $politicas->max_horas_extra_por_dia
            ]
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
                return response()->json(['error' => 'Token inválido'], 401);
            }

            $query = Asistencia::where('usuario_id', $usuario_id)
                ->where('fecha', now()->toDateString());

            // No admin → filtrar empresa y opcional sucursal
            if (!in_array($userAuth->role, ['admin'])) {
                $query->where('empresa_id', $userAuth->empresa_id);

                if ($userAuth->sucursal_id) {
                    $query->where('sucursal_id', $userAuth->sucursal_id);
                }
            }

            $asistencia = $query->first();

            if (!$asistencia) {
                return response()->json([
                    'estado' => 'sin_entrada',
                    'message' => 'Sin registro hoy'
                ]);
            }

            $estado = $asistencia->hora_salida
                ? 'fuera'
                : 'presente';

            return response()->json([
                'estado' => $estado,
                'asistencia' => $asistencia
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Error interno',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /** ======================
     * OBTENER POR RANGO
     * ====================== */
   

public function obtenerPorRango(Request $request)
{
    $usuario_id = $request->usuario_id;
    $from = $request->from;
    $to = $request->to;

    $userAuth = auth()->user();

    if (!$usuario_id || !$from || !$to) {
        return response()->json(['error' => 'Faltan parámetros'], 400);
    }

    // Usuario + turno actual
    $usuario = Usuario::with(['turnos' => function ($q) {
        $q->orderBy('empleado_turno.created_at', 'desc');
    }])->findOrFail($usuario_id);

    $turno = $usuario->turnos->first();
    $politicas = $usuario->empresa->politicasEmpresa ?? null;

    // ============================
    // TRAER ASISTENCIAS
    // ============================
    $query = Asistencia::where('usuario_id', $usuario_id)
        ->whereBetween('fecha', [$from, $to])
        ->orderBy('fecha', 'asc');

    // Admin igual se limita a su empresa
    $query->where('empresa_id', $userAuth->empresa_id);

    $asistencias = $query->get();

    // ============================
    // TRAER DESCANSOS DEL RANGO
    // ============================
    $descansos = \App\Models\Descanso::where('usuario_id', $usuario_id)
        ->whereBetween('hora_inicio', [$from . " 00:00:00", $to . " 23:59:59"])
        ->orderBy('hora_inicio', 'asc')
        ->get();

    // ============================
    // PROCESAR POR DÍA
    // ============================
    $diaADia = [];
    $resumen = [
        'dias' => 0,
        'total_trabajado_min' => 0,
        'total_extra_min' => 0,
        'total_atraso_min' => 0,
        'total_salida_anticipada_min' => 0,
        'total_descansos_min' => 0,
        'exceso_descanso_sum' => 0,
    ];

    foreach ($asistencias as $asis) {

        // Descansos DEL DÍA
        $descansosDia = $descansos->filter(function ($d) use ($asis) {
            return substr($d->hora_inicio, 0, 10) === $asis->fecha;
        });

        // Cálculo total de descansos reales
        $minDescanso = 0;
        foreach ($descansosDia as $d) {
            if ($d->hora_fin) {
                $inicio = Carbon\Carbon::parse($d->hora_inicio);
                $fin = Carbon\Carbon::parse($d->hora_fin);
                $minDescanso += $inicio->diffInMinutes($fin);
            }
        }

        // Políticas → minutos permitidos de descanso (si hay turno)
        $minPermitido = $turno ? ($turno->minutos_almuerzo ?? 0) : 0;
        $excesoDesc = max(0, $minDescanso - $minPermitido);

        // =============================
        // Armado del registro por día
        // =============================
        $dia = [
            'fecha' => $asis->fecha,
            'entrada' => $asis->hora_entrada,
            'salida' => $asis->hora_salida,
            'trabajado_min' => $asis->minutos_trabajados ?? 0,
            'atraso_min' => $asis->minutos_atraso ?? 0,
            'salida_anticipada_min' => $asis->minutos_salida_anticipada ?? 0,
            'horas_extra_min' => $asis->minutos_horas_extra ?? 0,
            'descansos_usados_min' => $minDescanso,
            'exceso_descanso_min' => $excesoDesc,
            'estado_jornada' => $asis->estado_jornada,
            'cumplio_turno' => ($asis->estado_jornada === 'completa' || $asis->estado_jornada === 'extra')
        ];

        $diaADia[] = $dia;

        // =============================
        // ACUMULAR RESUMEN GENERAL
        // =============================
        $resumen['dias']++;
        $resumen['total_trabajado_min'] += $dia['trabajado_min'];
        $resumen['total_extra_min'] += $dia['horas_extra_min'];
        $resumen['total_atraso_min'] += $dia['atraso_min'];
        $resumen['total_salida_anticipada_min'] += $dia['salida_anticipada_min'];
        $resumen['total_descansos_min'] += $minDescanso;
        $resumen['exceso_descanso_sum'] += $excesoDesc;
    }

    // ============================
    // CUMPLIMIENTO GENERAL
    // ============================
    if ($resumen['dias'] === 0) {
        $resumen['cumplimiento_general'] = "sin_registros";
    } else {
        $porcentaje = 100 - (
            ($resumen['total_atraso_min'] / max(1, $resumen['dias'])) * 0.5 +
            ($resumen['exceso_descanso_sum'] > 0 ? 10 : 0)
        );

        $resumen['cumplimiento_general'] =
            $porcentaje >= 90 ? "excelente" :
            ($porcentaje >= 75 ? "aceptable" : "bajo");
    }

    // ============================
    // RESPUESTA FINAL
    // ============================
    return response()->json([
        'usuario_id' => $usuario_id,
        'from'       => $from,
        'to'         => $to,

        'turno' => $turno ? [
            'id'                 => $turno->id,
            'nombre'             => $turno->nombre,
            'hora_inicio'        => $turno->hora_inicio,
            'hora_fin'           => $turno->hora_fin,
            'tolerancia_entrada' => $turno->tolerancia_entrada,
            'tolerancia_salida'  => $turno->tolerancia_salida,
            'minutos_almuerzo'   => $turno->minutos_almuerzo,
        ] : null,

        'data' => $asistencias,

        'dia_a_dia' => $diaADia,
        'resumen' => $resumen,
    ]);
}


}

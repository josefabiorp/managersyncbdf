<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\AsistenciaCalculator;

class AsistenciaController extends Controller
{
    /** ======================
     * LISTAR ASISTENCIAS (MULTIEMPRESA)
     * ====================== */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Asistencia::with(['usuario', 'empresa', 'sucursal']);

        // Si hay usuario autenticado, filtramos de forma segura por empresa/usuario
        if ($user) {
            $role = strtolower($user->role ?? $user->rol ?? '');

            // Admin → ve todas las asistencias de su empresa
            if (in_array($role, ['admin', 'administrador'], true)) {
                $query->where('empresa_id', $user->empresa_id);
            } else {
                // Empleado/otros roles → solo sus propias asistencias
                $query->where('usuario_id', $user->id);
            }
        }

        return response()->json(
            $query->orderBy('fecha', 'desc')->get()
        );
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

        // Ya existe entrada para hoy
        $asistencia = Asistencia::where('usuario_id', $usuario->id)
            ->where('fecha', $fecha)
            ->first();

        if ($asistencia) {
            return response()->json([
                'message'    => 'Entrada ya registrada',
                'estado'     => $asistencia->hora_salida ? 'fuera' : 'presente',
                'asistencia' => $asistencia
            ]);
        }

        // Crear registro de entrada
        $horaEntradaReal = now()->format("H:i:s");

        $asistencia = Asistencia::create([
            'usuario_id'   => $usuario->id,
            'empresa_id'   => $usuario->empresa_id,
            'sucursal_id'  => $usuario->sucursal_id,
            'fecha'        => $fecha,
            'hora_entrada' => $horaEntradaReal,
            'estado'       => 'presente',
        ]);

        // Turno asignado (turno actual del empleado)
        $turno = $usuario->turnoActual();
        if (!$turno) {
            return response()->json([
                'message'    => 'Entrada registrada sin turno asignado',
                'estado'     => 'presente',
                'asistencia' => $asistencia
            ]);
        }

        // Políticas de la empresa
        $politicas = $usuario->empresa->politica;
        if (!$politicas) {
            return response()->json([
                'message'    => 'Entrada registrada (sin políticas configuradas)',
                'estado'     => 'presente',
                'asistencia' => $asistencia
            ]);
        }

        // Cálculo de atraso (datos coherentes)
        $horaEsperada = Carbon::parse($turno->hora_inicio);
        $horaReal     = Carbon::parse($horaEntradaReal);

        $diferenciaMin = $horaEsperada->diffInMinutes($horaReal, false); // negativo si llega antes
        $tolerancia    = (int) ($politicas->minutos_tolerancia_atraso ?? 0);

        $minutosAtraso = ($diferenciaMin > $tolerancia) ? $diferenciaMin : 0;

        $asistencia->minutos_atraso = $minutosAtraso;
        $asistencia->estado_jornada = $minutosAtraso > 0 ? 'tarde' : 'normal';
        $asistencia->save();

        return response()->json([
            'message'            => 'Entrada registrada correctamente',
            'estado'             => 'presente',
            'asistencia'         => $asistencia,
            'turno_usado'        => $turno->nombre,
            'politica_tolerancia'=> $tolerancia
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
                'error'  => 'Salida ya registrada',
                'estado' => 'fuera'
            ], 400);
        }

        $horaSalidaReal = now()->format("H:i:s");
        $asistencia->hora_salida = $horaSalidaReal;

        // Turno asignado (turno actual del empleado)
        $turno = $usuario->turnoActual();
        if (!$turno) {
            $asistencia->estado = 'fuera';
            $asistencia->save();

            return response()->json([
                'message'    => 'Salida sin turno asignado',
                'estado'     => 'fuera',
                'asistencia' => $asistencia
            ]);
        }

        // Políticas de la empresa
        $politicas = $usuario->empresa->politica;
        if (!$politicas) {
            $asistencia->estado = 'fuera';
            $asistencia->save();

            return response()->json([
                'message'    => 'Salida registrada (sin políticas configuradas)',
                'estado'     => 'fuera',
                'asistencia' => $asistencia
            ]);
        }

        // SALIDA ANTICIPADA
        $horaEsperadaSalida = Carbon::parse($turno->hora_fin);
        $horaRealSalida     = Carbon::parse($horaSalidaReal);

        $toleranciaSalida = (int) ($politicas->minutos_tolerancia_salida ?? 0);
        $minutosSalidaAnticipada = 0;

        $limiteSinPenalizar = $horaEsperadaSalida->copy()->subMinutes($toleranciaSalida);

        if ($horaRealSalida->lt($limiteSinPenalizar)) {
            $minutosSalidaAnticipada = $horaEsperadaSalida->diffInMinutes($horaRealSalida);
        }

        // HORAS EXTRA
        $minutosHorasExtra = 0;
        if ($horaRealSalida->gt($horaEsperadaSalida)) {
            $minutosHorasExtra = $horaRealSalida->diffInMinutes($horaEsperadaSalida);

            $maxExtra = (int) ($politicas->max_horas_extra_por_dia ?? 0);
            if ($maxExtra > 0) {
                $minutosHorasExtra = min($minutosHorasExtra, $maxExtra * 60);
            }
        }

        // TIEMPO TRABAJADO (protegiendo datos incoherentes)
        $horaEntradaReal = Carbon::parse($asistencia->hora_entrada);
        if ($horaRealSalida->lt($horaEntradaReal)) {
            // Si por error la salida es "antes" que la entrada, no tiramos excepción,
            // pero dejamos trabajados en 0 para no dañar reportes.
            $minutosTrabajados = 0;
        } else {
            $minutosTrabajados = $horaRealSalida->diffInMinutes($horaEntradaReal);
        }

        // Estado final (mantenemos la misma lógica de prioridad)
        $estadoJornada =
            $minutosSalidaAnticipada > 0 ? 'incompleta'
            : ($minutosHorasExtra > 0 ? 'extra'
            : 'completa');

        // Guardar en DB (mismos campos que ya usabas)
        $asistencia->minutos_salida_anticipada = $minutosSalidaAnticipada;
        $asistencia->minutos_horas_extra       = $minutosHorasExtra;
        $asistencia->minutos_trabajados        = $minutosTrabajados;
        $asistencia->estado_jornada            = $estadoJornada;
        $asistencia->estado                    = 'fuera';
        $asistencia->save();

        return response()->json([
            'message'    => 'Salida registrada correctamente',
            'estado'     => 'fuera',
            'asistencia' => $asistencia,
            'politicas'  => [
                'tolerancia_salida' => $toleranciaSalida,
                'max_extra'         => $politicas->max_horas_extra_por_dia
            ]
        ]);
    }

    /** ======================
     * ESTADO ACTUAL DEL DÍA
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

            // No admin → filtrar empresa y sucursal
            $role = strtolower($userAuth->role ?? $userAuth->rol ?? '');
            if (!in_array($role, ['admin', 'administrador'], true)) {
                $query->where('empresa_id', $userAuth->empresa_id);

                if ($userAuth->sucursal_id) {
                    $query->where('sucursal_id', $userAuth->sucursal_id);
                }
            }

            $asistencia = $query->first();

            if (!$asistencia) {
                return response()->json([
                    'estado'  => 'sin_entrada',
                    'message' => 'Sin registro hoy'
                ]);
            }

            $estado = $asistencia->hora_salida
                ? 'fuera'
                : 'presente';

            return response()->json([
                'estado'     => $estado,
                'asistencia' => $asistencia
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Error interno',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /** ======================
     * OBTENER RANGO (USA SERVICIO)
     * ====================== */
    public function obtenerPorRango(Request $request)
    {
        $usuario_id = $request->usuario_id;
        $from       = $request->from;
        $to         = $request->to;

        $userAuth = $request->user();

        if (!$usuario_id || !$from || !$to) {
            return response()->json(['error' => 'Faltan parámetros'], 400);
        }

        // Normalizar fechas (si vienen invertidas, las corregimos)
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        // Usuario + últimos turnos asignados
        $usuario = Usuario::with(['turnos' => function ($q) {
            $q->orderBy('empleado_turno.created_at', 'desc');
        }])->findOrFail($usuario_id);

        // Para este primer diseño, asumimos un turno principal (último asignado)
        $turno = $usuario->turnos->first() ?: null;

        // Políticas de la empresa del usuario
        $politicas = $usuario->empresa->politica ?? null;

        // Obtener asistencias del rango (limitadas por empresa de quien consulta)
        $query = Asistencia::where('usuario_id', $usuario_id)
            ->whereBetween('fecha', [$from, $to])
            ->orderBy('fecha', 'asc');

        if ($userAuth) {
            $query->where('empresa_id', $userAuth->empresa_id);
        }

        $asistencias = $query->get();

        // Obtener descansos del rango (filtrados por empresa si ya lo tenés en la tabla)
        $descansosQuery = \App\Models\Descanso::where('usuario_id', $usuario_id)
            ->whereBetween('hora_inicio', [$from . " 00:00:00", $to . " 23:59:59"])
            ->orderBy('hora_inicio', 'asc');

        if ($userAuth && \Schema::hasColumn('descansos', 'empresa_id')) {
            $descansosQuery->where('empresa_id', $userAuth->empresa_id);
        }

        $descansos = $descansosQuery->get();

        // Procesamiento corporativo
        $diaADia = [];
        $resumen = [
            'dias'                          => 0,
            'total_trabajado_min'           => 0,
            'total_extra_min'               => 0,
            'total_atraso_min'              => 0,
            'total_salida_anticipada_min'   => 0,
            'total_descansos_min'           => 0,
            'exceso_descanso_sum'           => 0,
        ];

        foreach ($asistencias as $asis) {

            // Descansos de ese día (por fecha)
            $descansosDia = $descansos->filter(function ($d) use ($asis) {
                return substr($d->hora_inicio, 0, 10) === $asis->fecha;
            });

            // Servicio corporativo centralizado
            $datos = AsistenciaCalculator::calcularDia(
                $asis,
                $turno,
                $politicas,
                $descansosDia
            );

            $dia = array_merge([
                'fecha'   => $asis->fecha,
                'entrada' => $asis->hora_entrada,
                'salida'  => $asis->hora_salida,
            ], $datos);

            $diaADia[] = $dia;

            // Acumulados generales
            $resumen['dias']++;
            $resumen['total_trabajado_min']           += $datos['trabajado_min'];
            $resumen['total_extra_min']               += $datos['horas_extra_min'];
            $resumen['total_atraso_min']              += $datos['atraso_min'];
            $resumen['total_salida_anticipada_min']   += $datos['salida_anticipada_min'];
            $resumen['total_descansos_min']           += $datos['descansos_usados_min'];
            $resumen['exceso_descanso_sum']           += $datos['exceso_descanso_min'];
        }

        // Cumplimiento general (mantenemos tu lógica original, solo ordenada)
        if ($resumen['dias'] === 0) {
            $resumen['cumplimiento_general'] = "sin_registros";
        } else {
            $promedioAtraso = $resumen['total_atraso_min'] / max(1, $resumen['dias']);

            $porcentaje = 100 - (
                ($promedioAtraso * 0.5) +
                ($resumen['exceso_descanso_sum'] > 0 ? 10 : 0)
            );

            $resumen['cumplimiento_general'] =
                $porcentaje >= 90 ? "excelente" :
                ($porcentaje >= 75 ? "aceptable" : "bajo");
        }

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

            // Data cruda de la tabla (mantengo por compatibilidad)
            'data'      => $asistencias,

            // Data calculada día a día (lo que usa el frontend profesional)
            'dia_a_dia' => $diaADia,

            'resumen'   => $resumen,
        ]);
    }
}



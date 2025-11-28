<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AsistenciaCalculator
{
    /**
     * Calcula todos los valores de un día de asistencia:
     * - tiempo trabajado neto (restando descansos)
     * - atraso
     * - salida anticipada
     * - horas extra
     * - descansos usados
     * - exceso de descanso
     * - estado de la jornada
     * - si cumplió o no el turno
     *
     * IMPORTANTE:
     * - No modifica la asistencia ni otras tablas.
     * - No cambia el contrato de salida: mismas claves y tipos.
     *
     * @param  \App\Models\Asistencia|null  $asistencia
     * @param  \App\Models\Turno|null       $turno
     * @param  mixed                        $politicas
     * @param  iterable                     $descansosDia  Colección/array de descansos del mismo día
     * @return array{
     *   trabajado_min:int,
     *   atraso_min:int,
     *   salida_anticipada_min:int,
     *   horas_extra_min:int,
     *   descansos_usados_min:int,
     *   exceso_descanso_min:int,
     *   estado_jornada:string,
     *   cumplio_turno:bool
     * }
     */
    public static function calcularDia($asistencia, $turno, $politicas, $descansosDia)
    {
        // ============================
        // 0. VALIDACIONES BÁSICAS
        // ============================
        if (!$asistencia) {
            // Si no hay asistencia, devolvemos todo en cero y estado sin_datos
            return self::responseSinDatos();
        }

        // Si no hay turno o políticas, devolvemos un cálculo mínimo
        // usando los valores ya precalculados en la asistencia (si existen).
        if (!$turno || !$politicas) {
            return self::responseMinimo($asistencia);
        }

        // ============================
        // 1. NORMALIZAR MARCAS Y HORARIOS
        // ============================
        [$entrada, $salida, $horaEsperadaEntrada, $horaEsperadaSalida] =
            self::normalizarMarcasYTurno($asistencia, $turno);

        // ============================
        // 2. DESCANSOS USADOS (MINUTOS)
        // ============================
        [$totalDescanso, $excesoDescanso] = self::calcularDescansos(
            $descansosDia,
            (int) ($turno->minutos_almuerzo ?? 0)
        );

        // ============================
        // 3. TIEMPO TRABAJADO NETO
        //     (restando todos los descansos)
        // ============================
        $trabajado = self::calcularTiempoTrabajado($entrada, $salida, $totalDescanso);

        // ============================
        // 4. ATRASO
        // ============================
        $atraso = self::calcularAtraso(
            $entrada,
            $horaEsperadaEntrada,
            (int) ($politicas->minutos_tolerancia_atraso ?? 0)
        );

        // ============================
        // 5. SALIDA ANTICIPADA
        // ============================
        $salidaAnticipada = self::calcularSalidaAnticipada(
            $salida,
            $horaEsperadaSalida,
            (int) ($politicas->minutos_tolerancia_salida ?? 0)
        );

        // ============================
        // 6. HORAS EXTRA
        // ============================
        $horasExtraMin = self::calcularHorasExtra(
            $salida,
            $horaEsperadaSalida,
            (int) ($politicas->max_horas_extra_por_dia ?? 0)
        );

        // ============================
        // 7. ESTADO DE LA JORNADA
        // ============================
        $estado = self::determinarEstadoJornada(
            $atraso,
            $salidaAnticipada,
            $horasExtraMin
        );

        // Cumplimiento: consideramos "completa" o "extra" como OK
        $cumplio = in_array($estado, ['completa', 'extra'], true);

        // ============================
        // 8. RESPUESTA FINAL
        // ============================
        return [
            'trabajado_min'         => $trabajado,        // NETO (ya descuenta descansos)
            'atraso_min'            => $atraso,
            'salida_anticipada_min' => $salidaAnticipada,
            'horas_extra_min'       => $horasExtraMin,
            'descansos_usados_min'  => $totalDescanso,
            'exceso_descanso_min'   => $excesoDescanso,
            'estado_jornada'        => $estado,
            'cumplio_turno'         => $cumplio,
        ];
    }

    // ========================================================
    //  BLOQUE: RESPUESTAS BÁSICAS
    // ========================================================

    /**
     * Cuando no hay turno/políticas calculamos solo lo mínimo
     * usando los campos ya guardados en la asistencia.
     *
     * No modificamos el comportamiento original.
     */
    private static function responseMinimo($a): array
    {
        return [
            'trabajado_min'         => $a->minutos_trabajados       ?? 0,
            'atraso_min'            => $a->minutos_atraso           ?? 0,
            'salida_anticipada_min' => $a->minutos_salida_anticipada ?? 0,
            'horas_extra_min'       => $a->minutos_horas_extra      ?? 0,
            'descansos_usados_min'  => 0,
            'exceso_descanso_min'   => 0,
            'estado_jornada'        => $a->estado_jornada           ?? 'sin_datos',
            'cumplio_turno'         => false,
        ];
    }

    /**
     * Cuando no existe asistencia ese día.
     */
    private static function responseSinDatos(): array
    {
        return [
            'trabajado_min'         => 0,
            'atraso_min'            => 0,
            'salida_anticipada_min' => 0,
            'horas_extra_min'       => 0,
            'descansos_usados_min'  => 0,
            'exceso_descanso_min'   => 0,
            'estado_jornada'        => 'sin_datos',
            'cumplio_turno'         => false,
        ];
    }

    // ========================================================
    //  BLOQUE: NORMALIZACIÓN DE HORAS
    // ========================================================

    /**
     * Normaliza y devuelve:
     * - entrada real
     * - salida real
     * - hora esperada de entrada
     * - hora esperada de salida
     *
     * No cambia el comportamiento; solo encapsula la lógica.
     *
     * @return array{0: ?Carbon, 1: ?Carbon, 2: Carbon, 3: Carbon}
     */
    private static function normalizarMarcasYTurno($asistencia, $turno): array
    {
        $entrada = $asistencia->hora_entrada
            ? Carbon::parse($asistencia->hora_entrada)
            : null;

        $salida = $asistencia->hora_salida
            ? Carbon::parse($asistencia->hora_salida)
            : null;

        // Asumimos que hora_inicio / hora_fin siempre vienen bien formateadas (HH:MM:SS)
        $horaEsperadaEntrada = Carbon::parse($turno->hora_inicio);
        $horaEsperadaSalida  = Carbon::parse($turno->hora_fin);

        return [$entrada, $salida, $horaEsperadaEntrada, $horaEsperadaSalida];
    }

    // ========================================================
    //  BLOQUE: DESCANSOS
    // ========================================================

    /**
     * Calcula:
     * - total de minutos de descanso usado
     * - exceso sobre el mínimo permitido
     *
     * Si algún descanso no tiene hora_fin, NO se suma tiempo (como antes),
     * pero dejamos un log suave para poder detectar inconsistencias.
     *
     * @param iterable $descansosDia
     * @param int      $minPermitidoDescanso
     * @return array{0:int, 1:int} [totalDescanso, excesoDescanso]
     */
    private static function calcularDescansos(iterable $descansosDia, int $minPermitidoDescanso): array
    {
        $totalDescanso = 0;

        foreach ($descansosDia as $d) {
            // Descanso bien cerrado
            if ($d->hora_inicio && $d->hora_fin) {
                $ini = Carbon::parse($d->hora_inicio);
                $fin = Carbon::parse($d->hora_fin);

                // Proteger ante errores de datos (fin antes que inicio)
                if ($fin->lt($ini)) {
                    Log::warning('Descanso con hora_fin anterior a hora_inicio detectado', [
                        'descanso_id' => $d->id ?? null,
                        'hora_inicio' => $d->hora_inicio,
                        'hora_fin'    => $d->hora_fin,
                    ]);
                    continue;
                }

                $totalDescanso += $ini->diffInMinutes($fin);
            }
            // Descanso sin hora_fin (en curso o mal cerrado)
            elseif ($d->hora_inicio && !$d->hora_fin) {
                Log::info('Descanso sin hora_fin no contabilizado en totalDescanso', [
                    'descanso_id' => $d->id ?? null,
                    'hora_inicio' => $d->hora_inicio,
                ]);
            }
        }

        $totalDescanso          = max(0, (int) $totalDescanso);
        $minPermitidoDescanso   = max(0, $minPermitidoDescanso);
        $excesoDescanso         = max(0, $totalDescanso - $minPermitidoDescanso);

        return [$totalDescanso, $excesoDescanso];
    }

    // ========================================================
    //  BLOQUE: TIEMPO TRABAJADO
    // ========================================================

    /**
     * Calcula el tiempo trabajado neto (en minutos),
     * restando todos los descansos usados.
     *
     * Caso especial:
     * - Si no hay salida, se retorna 0 (misma lógica original).
     */
    private static function calcularTiempoTrabajado(?Carbon $entrada, ?Carbon $salida, int $totalDescanso): int
    {
        if ($entrada && $salida) {
            // Bruto: sin descontar descansos
            $bruto = $salida->diffInMinutes($entrada);

            // Protección ante datos inconsistentes (salida antes que entrada)
            if ($salida->lt($entrada)) {
                Log::warning('Asistencia con salida antes que entrada detectada', [
                    'hora_entrada' => $entrada->toTimeString(),
                    'hora_salida'  => $salida->toTimeString(),
                ]);
                return 0;
            }

            $trabajado = max(0, $bruto - $totalDescanso);
        } else {
            // Sin salida → día en curso o marca incompleta
            // Mantenemos el comportamiento: 0 hasta que se marque salida.
            $trabajado = 0;
        }

        return (int) $trabajado;
    }

    // ========================================================
    //  BLOQUE: ATRASO
    // ========================================================

    /**
     * Calcula el atraso en minutos, respetando la tolerancia.
     *
     * Si el colaborador llega antes de la hora esperada,
     * el valor se considera 0 (sin atraso), igual que antes.
     */
    private static function calcularAtraso(?Carbon $entrada, Carbon $horaEsperadaEntrada, int $toleranciaAtraso): int
    {
        if (!$entrada) {
            // Sin marca de entrada → consideramos 0 aquí.
            // Casos extremos se pueden manejar en otro nivel de negocio si se desea.
            return 0;
        }

        // diffInMinutes con $absolute = false puede devolver negativos
        // si la persona llega antes. En ese caso, atraso = 0.
        $diffEntrada = $horaEsperadaEntrada->diffInMinutes($entrada, false);

        if ($diffEntrada <= 0) {
            return 0;
        }

        // Aplicar tolerancia
        if ($diffEntrada > $toleranciaAtraso) {
            return (int) $diffEntrada;
        }

        return 0;
    }

    // ========================================================
    //  BLOQUE: SALIDA ANTICIPADA
    // ========================================================

    /**
     * Calcula minutos de salida anticipada, respetando la tolerancia.
     */
    private static function calcularSalidaAnticipada(?Carbon $salida, Carbon $horaEsperadaSalida, int $toleranciaSalida): int
    {
        if (!$salida) {
            return 0;
        }

        // Punto a partir del cual se considera "permitido" irse
        $limiteSalidaSinPenalizar = $horaEsperadaSalida->copy()->subMinutes($toleranciaSalida);

        if ($salida->lt($limiteSalidaSinPenalizar)) {
            return (int) $horaEsperadaSalida->diffInMinutes($salida);
        }

        return 0;
    }

    // ========================================================
    //  BLOQUE: HORAS EXTRA
    // ========================================================

    /**
     * Calcula las horas extra en minutos, respetando el máximo permitido por día.
     *
     * @param  int  $maxHorasExtraPorDia  Máximo de horas extra permitidas (en horas, no minutos)
     */
    private static function calcularHorasExtra(?Carbon $salida, Carbon $horaEsperadaSalida, int $maxHorasExtraPorDia): int
    {
        if (!$salida || $salida->lte($horaEsperadaSalida)) {
            return 0;
        }

        $extraMin = (int) $salida->diffInMinutes($horaEsperadaSalida);

        // Límite máximo diario por políticas (viene en horas → convertimos a minutos)
        if ($maxHorasExtraPorDia > 0) {
            $maxExtraMin = $maxHorasExtraPorDia * 60;
            $extraMin    = min($extraMin, $maxExtraMin);
        }

        return max(0, $extraMin);
    }

    // ========================================================
    //  BLOQUE: ESTADO DE LA JORNADA
    // ========================================================

    /**
     * Determina el estado final de la jornada según:
     * - atraso
     * - salida anticipada
     * - horas extra
     *
     * Se mantiene el mismo orden de prioridad:
     * 1) incompleta (salida anticipada)
     * 2) extra
     * 3) tarde
     * 4) completa
     */
    private static function determinarEstadoJornada(int $atraso, int $salidaAnticipada, int $extraMin): string
    {
        if ($salidaAnticipada > 0) {
            return 'incompleta';
        }

        if ($extraMin > 0) {
            return 'extra';
        }

        if ($atraso > 0) {
            return 'tarde';
        }

        return 'completa';
    }
}

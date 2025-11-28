<?php

namespace App\Services;

use Carbon\Carbon;

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
     * @param  \App\Models\Asistencia  $asistencia
     * @param  \App\Models\Turno|null  $turno
     * @param  mixed                   $politicas
     * @param  iterable                $descansosDia  Colección/array de descansos del mismo día
     * @return array
     */
    public static function calcularDia($asistencia, $turno, $politicas, $descansosDia)
    {
        // ============================
        // 0. VALIDACIONES BÁSICAS
        // ============================
        if (!$asistencia) {
            return self::responseSinDatos();
        }

        // Si no hay turno o políticas, devolvemos un cálculo mínimo
        // (usando lo que ya tenga la asistencia guardado)
        if (!$turno || !$politicas) {
            return self::responseMinimo($asistencia);
        }

        // ============================
        // 1. MARCAS Y HORARIOS BASE
        // ============================
        $entrada = $asistencia->hora_entrada
            ? Carbon::parse($asistencia->hora_entrada)
            : null;

        $salida = $asistencia->hora_salida
            ? Carbon::parse($asistencia->hora_salida)
            : null;

        $horaEsperadaEntrada = Carbon::parse($turno->hora_inicio);
        $horaEsperadaSalida  = Carbon::parse($turno->hora_fin);

        // ============================
        // 2. DESCANSOS USADOS (MINUTOS)
        // ============================
        $totalDescanso = 0;

        foreach ($descansosDia as $d) {
            if ($d->hora_fin) {
                $ini = Carbon::parse($d->hora_inicio);
                $fin = Carbon::parse($d->hora_fin);

                $totalDescanso += $ini->diffInMinutes($fin);
            }
        }

        $minPermitidoDescanso = $turno->minutos_almuerzo ?? 0;
        $excesoDescanso       = max(0, $totalDescanso - $minPermitidoDescanso);

        // ============================
        // 3. TIEMPO TRABAJADO NETO
        //     (restando todos los descansos)
        // ============================
        if ($entrada && $salida) {
            $bruto = $salida->diffInMinutes($entrada); // sin descontar nada
            $trabajado = max(0, $bruto - $totalDescanso);
        } else {
            $trabajado = 0;
        }

        // ============================
        // 4. ATRASO
        // ============================
        $diffEntrada = $entrada
            ? $horaEsperadaEntrada->diffInMinutes($entrada, false) // negativo si llegó antes
            : 0;

        $toleranciaAtraso = $politicas->minutos_tolerancia_atraso ?? 0;

        $atraso = ($diffEntrada > $toleranciaAtraso)
            ? $diffEntrada
            : 0;

        // ============================
        // 5. SALIDA ANTICIPADA
        // ============================
        $toleranciaSalida = $politicas->minutos_tolerancia_salida ?? 0;
        $salidaAnticipada = 0;

        if ($salida && $salida->lt($horaEsperadaSalida->copy()->subMinutes($toleranciaSalida))) {
            $salidaAnticipada = $horaEsperadaSalida->diffInMinutes($salida);
        }

        // ============================
        // 6. HORAS EXTRA
        // ============================
        $extra = 0;

        if ($salida && $salida->gt($horaEsperadaSalida)) {
            $extra = $salida->diffInMinutes($horaEsperadaSalida);

            // Límite máximo diario por políticas
            $maxExtra = $politicas->max_horas_extra_por_dia ?? 0;
            if ($maxExtra > 0) {
                $extra = min($extra, $maxExtra * 60);
            }
        }

        // ============================
        // 7. ESTADO DE LA JORNADA
        // ============================
        $estado =
            ($salidaAnticipada > 0) ? 'incompleta' :
            (($extra > 0)          ? 'extra'      :
            (($atraso > 0)         ? 'tarde'      : 'completa'));

        // Cumplimiento: consideramos "completa" o "extra" como OK
        $cumplio = in_array($estado, ['completa', 'extra'], true);

        // ============================
        // 8. RESPUESTA FINAL
        // ============================
        return [
            'trabajado_min'         => $trabajado,         // NETO (ya descuenta descansos)
            'atraso_min'            => $atraso,
            'salida_anticipada_min' => $salidaAnticipada,
            'horas_extra_min'       => $extra,
            'descansos_usados_min'  => $totalDescanso,
            'exceso_descanso_min'   => $excesoDescanso,
            'estado_jornada'        => $estado,
            'cumplio_turno'         => $cumplio,
        ];
    }

    /**
     * Cuando no hay turno/políticas calculamos solo lo mínimo
     * usando los campos ya guardados en la asistencia.
     */
    private static function responseMinimo($a)
    {
        return [
            'trabajado_min'         => $a->minutos_trabajados ?? 0,
            'atraso_min'            => $a->minutos_atraso ?? 0,
            'salida_anticipada_min' => $a->minutos_salida_anticipada ?? 0,
            'horas_extra_min'       => $a->minutos_horas_extra ?? 0,
            'descansos_usados_min'  => 0,
            'exceso_descanso_min'   => 0,
            'estado_jornada'        => $a->estado_jornada ?? 'sin_datos',
            'cumplio_turno'         => false,
        ];
    }

    /**
     * Cuando no existe asistencia ese día.
     */
    private static function responseSinDatos()
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
}

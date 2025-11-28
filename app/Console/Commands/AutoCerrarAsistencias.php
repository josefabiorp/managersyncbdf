<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Asistencia;
use App\Models\Usuario;
use App\Services\AsistenciaCalculator;
use Carbon\Carbon;

class AutoCerrarAsistencias extends Command
{
    protected $signature = 'asistencias:auto-cerrar';
    protected $description = 'Cierra asistencias automáticamente si ya pasaron 30 minutos del fin de turno';

    public function handle()
    {
        $hoy = now()->toDateString();

        // Buscar asistencias SIN salida
        $asistencias = Asistencia::whereNull('hora_salida')
            ->where('fecha', $hoy)
            ->get();

        foreach ($asistencias as $asis) {

            $usuario = Usuario::find($asis->usuario_id);
            if (!$usuario) continue;

            $turno = $usuario->turnoActual();
            if (!$turno) continue;

            $politicas = $usuario->empresa->politica;
            if (!$politicas) continue;

            $horaSalidaProgramada = Carbon::parse($turno->hora_fin);
            $limite = $horaSalidaProgramada->copy()->addMinutes(30);

            // Aún no toca autocerrar
            if (now()->lt($limite)) {
                continue;
            }

            // --- AUTO CERRAR ---
            $asis->hora_salida = $horaSalidaProgramada->format("H:i:s");
            $asis->estado = 'fuera';
            $asis->save();

            // Calcular corporativo
            $datos = AsistenciaCalculator::calcularDia(
                $asis,
                $turno,
                $politicas,
                [] // descansos del día (si querés luego lo relacionamos)
            );

            // Guardar datos calculados
            $asis->minutos_trabajados        = $datos['trabajado_min'];
            $asis->minutos_atraso            = $datos['atraso_min'];
            $asis->minutos_salida_anticipada = $datos['salida_anticipada_min'];
            $asis->minutos_horas_extra       = $datos['horas_extra_min'];
            $asis->estado_jornada            = $datos['estado_jornada'];
            $asis->save();

            $this->info("Asistencia autocerrada para usuario {$asis->usuario_id}");
        }

        return Command::SUCCESS;
    }
}

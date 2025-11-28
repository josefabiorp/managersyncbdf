<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();

            /**
             * RELACIONES PRINCIPALES
             */
            $table->foreignId('usuario_id')
                ->constrained('usuarios')
                ->onDelete('cascade');

            $table->foreignId('empresa_id')
                ->nullable()
                ->constrained('empresas')
                ->onDelete('cascade');

            $table->foreignId('sucursal_id')
                ->nullable()
                ->constrained('sucursales')
                ->onDelete('set null');

            /**
             * FECHA DE ASISTENCIA
             */
            $table->date('fecha');

            /**
             * MARCADAS DEL DÍA
             */
            $table->time('hora_entrada')->nullable();
            $table->time('hora_salida')->nullable();

            /**
             * ESTADO DEL DÍA (NO CONFUNDIR CON estado_jornada)
             * - ausente → no marcó
             * - presente → marcó entrada
             * - fuera → marcó salida
             */
            $table->enum('estado', ['ausente', 'presente', 'fuera'])
                ->default('ausente');

            /**
             * CAMPOS DE CÁLCULO CORPORATIVOS
             * Estos se llenan al cerrar el día con el servicio de cálculo
             */
            $table->integer('minutos_atraso')->default(0);
            $table->integer('minutos_salida_anticipada')->default(0);
            $table->integer('minutos_horas_extra')->default(0);
            $table->integer('minutos_trabajados')->default(0);

            /**
             * ESTADO DE LA JORNADA CALCULADO:
             * - sin_datos
             * - incompleta
             * - tarde
             * - completa
             * - extra
             * - permiso_con_goce
             * - permiso_sin_goce
             */
            $table->string('estado_jornada', 30)->nullable();

            /**
             * AUDITORÍA
             * Perfecto para empresas grandes, logs, BI, etc.
             */
            $table->json('auditoria')->nullable();

            $table->timestamps();

            /**
             * Índices para consulta rápida de reportes
             */
            $table->index(['empresa_id', 'fecha']);
            $table->index(['usuario_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};

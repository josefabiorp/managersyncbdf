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
        Schema::create('politicas_empresa', function (Blueprint $table) {
            $table->id();

            // IMPORTANTE: tu tabla es "empresas", NO "users"
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id')
                ->references('id')
                ->on('empresas')
                ->onDelete('cascade');

            // Jornada
            $table->unsignedTinyInteger('jornada_diaria_horas')->default(8); // 8h
            $table->unsignedTinyInteger('minutos_tolerancia_atraso')->default(10); // ej. 10 min
            $table->unsignedTinyInteger('minutos_tolerancia_salida')->default(5); // ej. 5 min

            // Pausas
            $table->unsignedTinyInteger('minutos_almuerzo')->default(60);  // 60 min
            $table->unsignedTinyInteger('minutos_descanso')->default(10);  // 10 min por descanso
            $table->unsignedTinyInteger('cantidad_descansos_permitidos')->default(2);
            $table->boolean('permite_acumular_descansos')->default(false);

            // Permisos
            $table->boolean('descuenta_permiso_sin_goce')->default(true);

            // Horas extra
            $table->unsignedTinyInteger('max_horas_extra_por_dia')->default(4);

            // Redondeo
            $table->enum('politica_redondeo_tiempos', ['arriba', 'abajo', 'normal'])
                ->default('normal');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('politicas_empresa');
    }
};

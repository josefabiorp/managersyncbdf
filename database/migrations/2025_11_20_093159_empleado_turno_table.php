<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleado_turno', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id')
                ->references('id')
                ->on('usuarios')   // ← tu tabla real de empleados
                ->onDelete('cascade');

            $table->unsignedBigInteger('turno_id');
            $table->foreign('turno_id')
                ->references('id')
                ->on('turnos')
                ->onDelete('cascade');

            // En el futuro podrías agregar fechas de vigencia
            // $table->date('fecha_inicio')->nullable();
            // $table->date('fecha_fin')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleado_turno');
    }
};

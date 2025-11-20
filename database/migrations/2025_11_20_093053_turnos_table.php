<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();

            // Turno pertenece a una empresa
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id')
                ->references('id')
                ->on('empresas')
                ->onDelete('cascade');

            $table->string('nombre', 100);

            // Horario básico del turno
            $table->time('hora_inicio');
            $table->time('hora_fin');

            // Tolerancias específicas del turno (en minutos)
            $table->unsignedSmallInteger('tolerancia_entrada')->default(0);
            $table->unsignedSmallInteger('tolerancia_salida')->default(0);

            // Almuerzo particular de este turno (puede usar el general de políticas si lo dejas en 0)
            $table->unsignedSmallInteger('minutos_almuerzo')->default(60);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};

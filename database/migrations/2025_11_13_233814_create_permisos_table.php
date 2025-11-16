<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos', function (Blueprint $table) {
            $table->id();

            // Relación base
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('sucursal_id')->nullable();

            // Datos del permiso
            $table->string('tipo', 100); // Ej: "Permiso personal", "Vacaciones", etc.
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();

            $table->text('motivo')->nullable();

            // Estado del flujo
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado', 'cancelado'])
                  ->default('pendiente');

            $table->unsignedBigInteger('aprobado_por')->nullable();
            $table->text('respuesta_admin')->nullable();

            $table->timestamps();

            // FKs (ajusta nombres de tablas si fueran distintos)
            $table->foreign('usuario_id')
                ->references('id')
                ->on('usuarios')
                ->onDelete('cascade');

            $table->foreign('aprobado_por')
                ->references('id')
                ->on('usuarios')
                ->nullOnDelete();

            $table->foreign('sucursal_id')
                ->references('id')
                ->on('sucursales')
                ->nullOnDelete();

            $table->foreign('empresa_id')
                ->references('id')
                ->on('empresas')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos');
    }
};

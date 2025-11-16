<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('descansos', function (Blueprint $table) {
            $table->id();

            // Relaciones correctas
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('sucursal_id')->constrained('sucursales')->onDelete('cascade');

            // Info del descanso
            $table->string('tipo', 50);
            $table->time('hora_inicio');
            $table->time('hora_fin')->nullable();

            $table->enum('estado', ['abierto', 'cerrado', 'forzado', 'cancelado'])
                  ->default('abierto');

            $table->text('nota')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('usuarios');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('descansos');
    }
};

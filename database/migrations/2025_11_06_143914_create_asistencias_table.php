<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::create('asistencias', function (Blueprint $table) {
        $table->id();
        $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
        $table->date('fecha');
        $table->time('hora_entrada')->nullable();
        $table->time('hora_salida')->nullable();
        $table->enum('estado', ['ausente', 'presente'])->default('ausente');
        $table->unsignedBigInteger('empresa_id')->nullable();
$table->unsignedBigInteger('sucursal_id')->nullable();

$table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
$table->foreign('sucursal_id')->references('id')->on('sucursales')->onDelete('set null');

        $table->timestamps();
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

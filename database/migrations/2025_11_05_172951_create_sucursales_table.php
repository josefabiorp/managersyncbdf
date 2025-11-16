<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * ================================================
         *  1️⃣ CREAR TABLA SUCURSALES COMPLETA
         * ================================================
         */
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();

            // Relación con empresa
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id')
                  ->references('id')
                  ->on('empresas')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // Datos principales
            $table->string('nombre', 150);
            $table->string('codigo', 50)->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono', 20)->nullable();

            // Ubicación geográfica (mapa)
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('mapa_url')->nullable();

            // Estado
            $table->boolean('activa')->default(true);

            $table->timestamps();
        });

        /**
         * ================================================
         *  2️⃣ AÑADIR RELACIÓN sucursal_id EN USUARIOS
         * ================================================
         */
        Schema::table('usuarios', function (Blueprint $table) {
            if (!Schema::hasColumn('usuarios', 'sucursal_id')) {
                $table->unsignedBigInteger('sucursal_id')->nullable()->after('empresa_id');

                $table->foreign('sucursal_id')
                      ->references('id')
                      ->on('sucursales')
                      ->cascadeOnUpdate()
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        /**
         * ================================================
         *  DESHACER CAMBIOS EN ORDEN INVERSO
         * ================================================
         */
        Schema::table('usuarios', function (Blueprint $table) {
            if (Schema::hasColumn('usuarios', 'sucursal_id')) {
                $table->dropForeign(['sucursal_id']);
                $table->dropColumn('sucursal_id');
            }
        });

        Schema::dropIfExists('sucursales');
    }
};

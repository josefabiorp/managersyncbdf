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
    DB::statement("ALTER TABLE asistencias MODIFY estado ENUM('ausente', 'presente', 'fuera') DEFAULT 'ausente'");
}

public function down()
{
    DB::statement("ALTER TABLE asistencias MODIFY estado ENUM('ausente', 'presente') DEFAULT 'ausente'");
}

};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();

            // Identidad corporativa
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('sucursal_id')->nullable();

            // Usuarios involucrados
            $table->unsignedBigInteger('remitente_id');
            $table->unsignedBigInteger('destinatario_id');

            // Contenido del mensaje
            $table->text('contenido')->nullable(); // ← corregido

            // Lectura
            $table->boolean('leido')->default(false);
            $table->timestamp('leido_en')->nullable();

            // Tipo de mensaje
            $table->string('tipo', 20)->default('texto');
            $table->string('adjunto_url')->nullable();

            // Eliminación lógica (WhatsApp style)
            $table->timestamp('eliminado_para_remitente')->nullable();
            $table->timestamp('eliminado_para_destinatario')->nullable();

            $table->timestamps();

            // Relaciones
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('sucursal_id')->references('id')->on('sucursales')->onDelete('set null');

            $table->foreign('remitente_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->foreign('destinatario_id')->references('id')->on('usuarios')->onDelete('cascade');

            // Índices
            $table->index(['empresa_id', 'sucursal_id']);
            $table->index(['remitente_id', 'destinatario_id'], 'idx_chat_pair');
            $table->index(['destinatario_id', 'leido'], 'idx_unread');
            $table->index('created_at', 'idx_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
    }
};

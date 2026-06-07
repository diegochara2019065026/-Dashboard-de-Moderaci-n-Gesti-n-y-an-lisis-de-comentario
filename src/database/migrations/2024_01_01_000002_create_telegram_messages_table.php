<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla telegram_messages
 *
 * Almacena el registro de todos los mensajes procesados por el bot
 * de Telegram (Aegis Filter Moderador). Guarda tanto mensajes
 * aprobados como spam detectado y eliminado.
 *
 * Curso: SI784 – Calidad y Pruebas de Software
 * Proyecto: Aegis Filter | Bot Moderador de Telegram
 */
return new class extends Migration
{
    /**
     * Crear la tabla telegram_messages.
     */
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();

            // ─── Datos del mensaje de Telegram ────────────
            $table->bigInteger('telegram_message_id')
                ->comment('ID del mensaje en Telegram');

            $table->bigInteger('chat_id')
                ->comment('ID del grupo/chat de Telegram');

            $table->string('chat_title', 255)->nullable()
                ->comment('Nombre del grupo de Telegram');

            // ─── Datos del usuario ────────────────────────
            $table->bigInteger('user_id')
                ->comment('ID del usuario en Telegram');

            $table->string('username', 100)->nullable()
                ->comment('@username del usuario');

            $table->string('first_name', 150)
                ->comment('Nombre del usuario en Telegram');

            // ─── Contenido y análisis ─────────────────────
            $table->text('content')
                ->comment('Texto del mensaje');

            $table->enum('status', ['approved', 'spam'])
                ->default('approved')
                ->comment('Resultado del análisis antispam');

            $table->string('spam_reason', 50)->nullable()
                ->comment('blacklisted_word | too_many_urls | null');

            $table->string('action_taken', 20)->nullable()
                ->comment('Acción tomada: deleted | null');

            // ─── Timestamps ───────────────────────────────
            $table->timestamps();

            // ─── Índices ──────────────────────────────────
            $table->index('chat_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Revertir: eliminar la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};

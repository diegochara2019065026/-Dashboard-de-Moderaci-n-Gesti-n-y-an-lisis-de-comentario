<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo TelegramMessage – Registro de mensajes procesados por el bot
 *
 * Almacena cada mensaje que el bot de Telegram procesa,
 * tanto los aprobados como los marcados como spam y eliminados.
 * Permite tener un historial completo de la moderación automática.
 *
 * @property int         $id
 * @property int         $telegram_message_id  ID del mensaje en Telegram
 * @property int         $chat_id              ID del grupo/chat
 * @property string|null $chat_title            Nombre del grupo
 * @property int         $user_id              ID del usuario en Telegram
 * @property string|null $username              @username del usuario
 * @property string      $first_name           Nombre del usuario
 * @property string      $content              Texto del mensaje
 * @property string      $status               'approved'|'spam'
 * @property string|null $spam_reason          Razón del bloqueo
 * @property string|null $action_taken         'deleted'|null
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TelegramMessage extends Model
{
    use HasFactory;

    protected $table = 'telegram_messages';

    /**
     * Campos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'telegram_message_id',
        'chat_id',
        'chat_title',
        'user_id',
        'username',
        'first_name',
        'content',
        'status',
        'spam_reason',
        'action_taken',
    ];

    /**
     * Casting de tipos para atributos.
     */
    protected $casts = [
        'telegram_message_id' => 'integer',
        'chat_id'             => 'integer',
        'user_id'             => 'integer',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    // ─── Scopes ─────────────────────────────────────────────

    /**
     * Scope para mensajes aprobados.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope para mensajes marcados como spam.
     */
    public function scopeSpam($query)
    {
        return $query->where('status', 'spam');
    }

    /**
     * Scope para mensajes eliminados por el bot.
     */
    public function scopeDeleted($query)
    {
        return $query->where('action_taken', 'deleted');
    }

    // ─── Accessors ──────────────────────────────────────────

    /**
     * Retorna el nombre de display del usuario.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->username) {
            return '@' . $this->username;
        }

        return $this->first_name;
    }

    /**
     * Retorna la etiqueta visual del estado.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'approved' => '✅ Aprobado',
            'spam'     => '🚫 Spam',
            default    => '❓ Desconocido',
        };
    }
}

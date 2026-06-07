<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Comment – Representa un comentario del foro Tech Hub
 *
 * @property int         $id
 * @property string      $author
 * @property string|null $email
 * @property string      $content
 * @property string      $status      'pending'|'approved'|'spam'
 * @property string|null $spam_reason 'blacklisted_word'|'too_many_urls'|null
 * @property string|null $ip_address
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 */

class Comment extends Model
{
    use HasFactory;

    protected $table = 'comments';

    /**
     * Campos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'author',
        'email',
        'content',
        'status',
        'spam_reason',
        'ip_address',
    ];

    /**
     * Casting de tipos para atributos.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Scopes ─────────────────────────────────────────────

    /**
     * Scope para comentarios aprobados.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope para comentarios marcados como spam.
     */
    public function scopeSpam($query)
    {
        return $query->where('status', 'spam');
    }

    /**
     * Scope para comentarios pendientes.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // ─── Accessors ──────────────────────────────────────────

    /**
     * Retorna la etiqueta visual del estado.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'approved' => '✅ Aprobado',
            'spam'     => '🚫 Spam',
            'pending'  => '⏳ Pendiente',
            default    => '❓ Desconocido',
        };
    }

    /**
     * Retorna la clase CSS Bootstrap del badge de estado.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'badge-approved',
            'spam'     => 'badge-spam',
            'pending'  => 'badge-pending',
            default    => 'badge-secondary',
        };
    }

    /**
     * Retorna el extracto del contenido (primeros 120 caracteres).
     */
    public function getExcerptAttribute(): string
    {
        return strlen($this->content) > 120
            ? substr($this->content, 0, 120) . '...'
            : $this->content;
    }
}

<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Aegis Filter – API Routes
|--------------------------------------------------------------------------
| Endpoints JSON para integrar el antispam desde cualquier página web.
|
| Base URL: /api
|  POST /api/comments              → Enviar comentario (analiza + guarda)
|  GET  /api/comments              → Listar comentarios aprobados
|  POST /api/check-spam            → Solo verificar spam, sin guardar
|  POST /api/telegram/webhook      → Webhook de Telegram (bot moderador)
|  POST /api/telegram/setup-webhook→ Configurar webhook
|  GET  /api/telegram/status       → Estado del bot
*/

// ─── Rutas del Foro (Comentarios) ─────────────────────

// Enviar un comentario desde un sitio externo
Route::post('/comments', [CommentController::class, 'apiStore'])
    ->name('api.comments.store');

// Obtener comentarios aprobados (para mostrar en la página externa)
Route::get('/comments', [CommentController::class, 'apiIndex'])
    ->name('api.comments.index');

// Solo verificar si un texto es spam, sin guardarlo
Route::post('/check-spam', [CommentController::class, 'checkSpam'])
    ->name('api.check-spam');

// ─── Rutas del Bot de Telegram ────────────────────────

// Webhook: Telegram envía updates aquí automáticamente
Route::post('/telegram/webhook', [TelegramController::class, 'handleWebhook'])
    ->name('telegram.webhook');

// Configurar el webhook (ejecutar una vez)
Route::post('/telegram/setup-webhook', [TelegramController::class, 'setupWebhook'])
    ->name('telegram.setup-webhook');

// Estado del bot y estadísticas
Route::get('/telegram/status', [TelegramController::class, 'status'])
    ->name('telegram.status');


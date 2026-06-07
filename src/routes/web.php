<?php

use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Aegis Filter – Web Routes
|--------------------------------------------------------------------------
*/


// Redirigir raíz al formulario de comentarios.

Route::get('/', fn() => redirect()->route('comments.form'));

// ─── Rutas del Formulario Público ────────────────────

Route::get('/comentarios', [CommentController::class, 'showForm'])
    ->name('comments.form');

Route::post('/comentarios', [CommentController::class, 'store'])
    ->name('comments.store');

// ─── Rutas del Dashboard de Administración ───────────

Route::get('/dashboard', [CommentController::class, 'dashboard'])
    ->name('dashboard');

Route::patch('/dashboard/{id}/approve', [CommentController::class, 'approve'])
    ->name('comments.approve');

Route::delete('/dashboard/{id}', [CommentController::class, 'destroy'])
    ->name('comments.destroy');



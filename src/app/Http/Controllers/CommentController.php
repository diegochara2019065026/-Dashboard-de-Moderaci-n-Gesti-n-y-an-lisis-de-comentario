<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Services\SpamFilterService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

/**
 * CommentController – Controlador de Comentarios del Foro
 *
 * Este controlador es DELGADO (thin controller) por diseño.
 * NO contiene lógica de negocio ni reglas antispam.
 * Toda la lógica de filtrado se delega al SpamFilterService.
 *
 * Principios aplicados:
 *  - SRP: Solo gestiona el flujo HTTP
 *  - DI: SpamFilterService inyectado en el constructor
 *
 * Rutas que maneja:
 *  GET  /comentarios          → showForm()
 *  POST /comentarios          → store()
 *  GET  /dashboard            → dashboard()
 */
class CommentController extends Controller
{
    /**
     * Servicio de filtrado antispam (inyectado por el contenedor IoC de Laravel).
     */
    public function __construct(
        private readonly SpamFilterService $spamFilter
    ) {}

    // ══════════════════════════════════════════════════
    // GET /comentarios – Mostrar formulario
    // ══════════════════════════════════════════════════

    /**
     * Mostrar el formulario de envío de comentarios.
     *
     * @return \Illuminate\View\View
     */
    public function showForm(): View
    {
        return view('form', [
            'title' => 'Tech Hub Forum – Enviar Comentario',
        ]);
    }

    // ══════════════════════════════════════════════════
    // POST /comentarios – Procesar y guardar comentario
    // ══════════════════════════════════════════════════

    /**
     * Recibir el POST del formulario, analizar el contenido
     * con el motor antispam y persistir en la base de datos.
     *
     * Flujo:
     *  1. Validar campos de entrada
     *  2. Delegar análisis al SpamFilterService
     *  3. Asignar status: 'approved' o 'spam'
     *  4. Guardar en BD con todos los metadatos
     *  5. Redirigir con mensaje de feedback
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        // Paso 1: Validación de la solicitud HTTP
        $validated = $request->validate([
            'author'  => ['required', 'string', 'min:2', 'max:100'],
            'email'   => ['nullable', 'email', 'max:150'],
            'content' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'author.required'  => 'El nombre del autor es obligatorio.',
            'author.min'       => 'El nombre debe tener al menos 2 caracteres.',
            'content.required' => 'El contenido del comentario es obligatorio.',
            'content.min'      => 'El comentario debe tener al menos 10 caracteres.',
            'content.max'      => 'El comentario no puede superar los 2000 caracteres.',
            'email.email'      => 'El correo electrónico no tiene un formato válido.',
        ]);

        // Paso 2: Analizar el contenido con el motor antispam
        // ⚠️  La lógica de negocio está AQUÍ FUERA: en SpamFilterService
        $analysisResult = $this->spamFilter->analyze(
            content: $validated['content'],
            author:  $validated['author']
        );

        // Paso 3: Determinar el estado final del comentario
        $status     = $analysisResult['isSpam'] ? 'spam' : 'approved';
        $spamReason = $analysisResult['reason'] ?? null;

        // Paso 4: Persistir en la base de datos
        $comment = Comment::create([
            'author'      => $validated['author'],
            'email'       => $validated['email'] ?? null,
            'content'     => $validated['content'],
            'status'      => $status,
            'spam_reason' => $spamReason,
            'ip_address'  => $request->ip(),
        ]);

        // Paso 5: Redirigir con feedback al usuario
        if ($analysisResult['isSpam']) {
            return redirect()
                ->route('comments.form')
                ->with('warning', '⚠️ Tu comentario fue marcado como posible spam y está pendiente de revisión.');
        }

        return redirect()
            ->route('comments.form')
            ->with('success', '✅ ¡Tu comentario fue enviado correctamente y está pendiente de aprobación!');
    }

    // ══════════════════════════════════════════════════
    // GET /dashboard – Panel de administración
    // ══════════════════════════════════════════════════

    /**
     * Mostrar el dashboard de administración con todos los comentarios.
     * Permite filtrar por estado (todos, aprobados, spam).
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\View\View
     */
    public function dashboard(Request $request): View
    {
        $filter = $request->get('status', 'all');

        // Construir query con filtro opcional
        $query = Comment::latest();

        if (in_array($filter, ['approved', 'spam', 'pending'])) {
            $query->where('status', $filter);
        }

        $comments = $query->paginate(15);

        // Estadísticas para el dashboard
        $stats = [
            'total'    => Comment::count(),
            'approved' => Comment::where('status', 'approved')->count(),
            'spam'     => Comment::where('status', 'spam')->count(),
            'pending'  => Comment::where('status', 'pending')->count(),
        ];

        return view('dashboard', compact('comments', 'stats', 'filter'));
    }

    // ══════════════════════════════════════════════════
    // POST /dashboard/{id}/approve – Aprobar comentario spam
    // ══════════════════════════════════════════════════

    /**
     * Cambiar el estado de un comentario spam a aprobado (falso positivo).
     *
     * @param  int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approve(int $id): RedirectResponse
    {
        $comment = Comment::findOrFail($id);
        $comment->update([
            'status'      => 'approved',
            'spam_reason' => null,
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', "✅ Comentario #{$id} aprobado correctamente.");
    }

    /**
     * Eliminar un comentario.
     *
     * @param  int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $comment = Comment::findOrFail($id);
        $comment->delete();

        return redirect()
            ->route('dashboard')
            ->with('success', "🗑️ Comentario #{$id} eliminado correctamente.");
    }

    // ══════════════════════════════════════════════════
    // POST /api/comments – Enviar comentario vía API
    // ══════════════════════════════════════════════════

    public function apiStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'author'  => ['required', 'string', 'min:2', 'max:100'],
            'email'   => ['nullable', 'email', 'max:150'],
            'content' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $result = $this->spamFilter->analyze(
            content: $validated['content'],
            author:  $validated['author']
        );

        $status = $result['isSpam'] ? 'spam' : 'approved';

        $comment = Comment::create([
            'author'      => $validated['author'],
            'email'       => $validated['email'] ?? null,
            'content'     => $validated['content'],
            'status'      => $status,
            'spam_reason' => $result['reason'] ?? null,
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'success'    => true,
            'comment_id' => $comment->id,
            'status'     => $status,
            'isSpam'     => $result['isSpam'],
            'score'      => $result['score'],
            'message'    => $result['isSpam']
                ? '⚠️ Tu comentario fue marcado como posible spam y está pendiente de revisión.'
                : '✅ ¡Tu comentario fue enviado y está pendiente de aprobación!',
        ], 201);
    }

    // ══════════════════════════════════════════════════
    // GET /api/comments – Listar comentarios aprobados
    // ══════════════════════════════════════════════════

    public function apiIndex(): JsonResponse
    {
        $comments = Comment::where('status', 'approved')
            ->latest()
            ->paginate(10);

        return response()->json([
            'data'       => $comments->map(fn($c) => [
                'id'         => $c->id,
                'author'     => $c->author,
                'content'    => $c->content,
                'created_at' => $c->created_at->toIso8601String(),
            ]),
            'total'       => $comments->total(),
            'current_page'=> $comments->currentPage(),
            'last_page'   => $comments->lastPage(),
        ]);
    }

    // ══════════════════════════════════════════════════
    // POST /api/check-spam – Verificar spam sin guardar
    // ══════════════════════════════════════════════════

    public function checkSpam(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'author'  => ['required', 'string', 'min:2', 'max:100'],
            'content' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $result = $this->spamFilter->analyze(
            content: $validated['content'],
            author:  $validated['author']
        );

        return response()->json([
            'isSpam' => $result['isSpam'],
            'score'  => $result['score'],
            'reason' => $result['reason'],
            'detail' => $result['detail'],
        ]);
    }
}

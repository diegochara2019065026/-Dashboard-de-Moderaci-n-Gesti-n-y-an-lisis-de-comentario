<?php

namespace App\Http\Controllers;

use App\Models\TelegramMessage;
use App\Services\SpamFilterService;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * TelegramController – Controlador del Webhook de Telegram
 *
 * Este controlador es DELGADO (thin controller) por diseño.
 * NO contiene lógica antispam: todo se delega al SpamFilterService.
 * NO contiene lógica de comunicación con Telegram: usa TelegramBotService.
 *
 * Principios aplicados:
 *  - SRP: Solo gestiona el flujo del webhook
 *  - DI: Ambos servicios inyectados en el constructor
 *
 * Flujo del webhook:
 *  1. Telegram envía un POST con el mensaje del grupo
 *  2. Se extrae el texto y datos del usuario
 *  3. SpamFilterService analiza el contenido
 *  4. Si es spam → TelegramBotService elimina el mensaje + envía advertencia
 *  5. Se registra el evento en telegram_messages
 *
 * Rutas:
 *  POST /api/telegram/webhook        → handleWebhook()
 *  POST /api/telegram/setup-webhook  → setupWebhook()
 *  GET  /api/telegram/status         → status()
 *
 * Curso: SI784 – Calidad y Pruebas de Software
 * Proyecto: Aegis Filter | Bot Moderador de Telegram
 */
class TelegramController extends Controller
{
    /**
     * Servicios inyectados por el contenedor IoC de Laravel.
     */
    public function __construct(
        private readonly SpamFilterService $spamFilter,
        private readonly TelegramBotService $telegramBot
    ) {}

    // ══════════════════════════════════════════════════
    // POST /api/telegram/webhook – Procesar mensaje
    // ══════════════════════════════════════════════════

    /**
     * Recibir y procesar un update de Telegram (webhook).
     *
     * Este endpoint es llamado automáticamente por Telegram cada vez
     * que alguien envía un mensaje en un grupo donde el bot está presente.
     *
     * Payload de Telegram (simplificado):
     * {
     *   "update_id": 123456,
     *   "message": {
     *     "message_id": 789,
     *     "from": { "id": 111, "first_name": "Juan", "username": "juan123" },
     *     "chat": { "id": -100123, "title": "Mi Grupo", "type": "group" },
     *     "text": "Compra ahora esta oferta increíble"
     *   }
     * }
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        // ─── Verificar el secret token del webhook ────────
        $expectedSecret = config('services.telegram.webhook_secret');
        $receivedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($expectedSecret && $receivedSecret !== $expectedSecret) {
            Log::warning('TelegramWebhook: Secret token inválido', [
                'received' => $receivedSecret,
            ]);

            return response()->json(['status' => 'unauthorized'], 403);
        }

        // ─── Extraer datos del payload ────────────────────
        $update  = $request->all();
        $message = $update['message'] ?? null;

        // Ignorar updates que no sean mensajes de texto
        if (!$message || !isset($message['text'])) {
            Log::debug('TelegramWebhook: Update sin texto, ignorado', [
                'update_id' => $update['update_id'] ?? 'unknown',
            ]);

            return response()->json(['status' => 'ignored']);
        }

        $text      = $message['text'];
        $chatId    = $message['chat']['id'];
        $chatTitle = $message['chat']['title'] ?? 'Chat privado';
        $chatType  = $message['chat']['type'] ?? 'private';
        $messageId = $message['message_id'];
        $from      = $message['from'];
        $userId    = $from['id'];
        $username  = $from['username'] ?? null;
        $firstName = $from['first_name'] ?? 'Unknown';

        // Ignorar mensajes privados (solo moderar grupos)
        if (!in_array($chatType, ['group', 'supergroup'])) {
            // En chats privados, responder con info del bot
            $this->telegramBot->sendMessage(
                $chatId,
                "🛡️ <b>Aegis Filter Bot</b>\n\n"
                . "Soy un bot moderador antispam.\n"
                . "Agrégame a un grupo como administrador y moderaré los mensajes automáticamente.\n\n"
                . "🔗 Proyecto: SI784 – Calidad y Pruebas de Software"
            );

            return response()->json(['status' => 'private_chat_response']);
        }

        // Ignorar comandos de bot (empiezan con /)
        if (str_starts_with($text, '/')) {
            return $this->handleCommand($text, $chatId, $messageId);
        }

        Log::info('TelegramWebhook: Procesando mensaje', [
            'chat_id'    => $chatId,
            'chat_title' => $chatTitle,
            'user'       => $username ?? $firstName,
            'text_preview' => mb_substr($text, 0, 50),
        ]);

        // ─── Analizar con SpamFilterService ───────────────
        $analysisResult = $this->spamFilter->analyze(
            content: $text,
            author:  $username ?? $firstName
        );

        $status     = $analysisResult['isSpam'] ? 'spam' : 'approved';
        $spamReason = $analysisResult['reason'] ?? null;
        $actionTaken = null;

        // ─── Si es spam: eliminar y advertir ──────────────
        if ($analysisResult['isSpam']) {
            Log::warning('TelegramWebhook: SPAM detectado – eliminando mensaje', [
                'chat_id'  => $chatId,
                'user'     => $username ?? $firstName,
                'reason'   => $spamReason,
                'score'    => $analysisResult['score'],
            ]);

            // 1. Eliminar el mensaje del grupo
            $deleteResult = $this->telegramBot->deleteMessage($chatId, $messageId);

            if ($deleteResult['ok'] ?? false) {
                $actionTaken = 'deleted';
            }

            // 2. Enviar mensaje de advertencia
            $warningMessage = $this->buildWarningMessage(
                $firstName,
                $username,
                $analysisResult
            );

            $this->telegramBot->sendMessage($chatId, $warningMessage);
        }

        // ─── Registrar en la base de datos ────────────────
        TelegramMessage::create([
            'telegram_message_id' => $messageId,
            'chat_id'             => $chatId,
            'chat_title'          => $chatTitle,
            'user_id'             => $userId,
            'username'            => $username,
            'first_name'          => $firstName,
            'content'             => $text,
            'status'              => $status,
            'spam_reason'         => $spamReason,
            'action_taken'        => $actionTaken,
        ]);

        return response()->json([
            'status'  => 'processed',
            'isSpam'  => $analysisResult['isSpam'],
            'action'  => $actionTaken,
        ]);
    }

    // ══════════════════════════════════════════════════
    // POST /api/telegram/setup-webhook – Configurar
    // ══════════════════════════════════════════════════

    /**
     * Registrar el webhook con Telegram.
     *
     * Configura la URL a la que Telegram enviará los updates.
     * Debe ejecutarse una sola vez (o cuando cambie la URL).
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function setupWebhook(Request $request): JsonResponse
    {
        // Construir la URL del webhook
        $webhookUrl = $request->input('url')
            ?? url('/api/telegram/webhook');

        $result = $this->telegramBot->setWebhook($webhookUrl);

        if ($result['ok'] ?? false) {
            Log::info('TelegramWebhook: Webhook configurado exitosamente', [
                'url' => $webhookUrl,
            ]);

            return response()->json([
                'success' => true,
                'message' => '✅ Webhook configurado correctamente.',
                'url'     => $webhookUrl,
                'result'  => $result,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => '❌ Error al configurar el webhook.',
            'result'  => $result,
        ], 500);
    }

    // ══════════════════════════════════════════════════
    // GET /api/telegram/status – Estado del bot
    // ══════════════════════════════════════════════════

    /**
     * Verificar el estado del bot y la configuración del webhook.
     *
     * Útil para diagnóstico durante la demostración:
     * confirma que el bot está conectado y el webhook activo.
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        $botInfo     = $this->telegramBot->getMe();
        $webhookInfo = $this->telegramBot->getWebhookInfo();

        // Estadísticas de mensajes procesados
        $stats = [
            'total_processed'  => TelegramMessage::count(),
            'spam_detected'    => TelegramMessage::where('status', 'spam')->count(),
            'messages_deleted' => TelegramMessage::where('action_taken', 'deleted')->count(),
            'clean_messages'   => TelegramMessage::where('status', 'approved')->count(),
        ];

        return response()->json([
            'bot' => [
                'connected' => $botInfo['ok'] ?? false,
                'info'      => $botInfo['result'] ?? null,
            ],
            'webhook' => [
                'configured' => !empty($webhookInfo['result']['url'] ?? ''),
                'url'        => $webhookInfo['result']['url'] ?? null,
                'pending'    => $webhookInfo['result']['pending_update_count'] ?? 0,
                'last_error' => $webhookInfo['result']['last_error_message'] ?? null,
            ],
            'stats' => $stats,
        ]);
    }

    // ══════════════════════════════════════════════════
    // HELPERS PRIVADOS
    // ══════════════════════════════════════════════════

    /**
     * Construir el mensaje de advertencia cuando se detecta spam.
     *
     * Usa HTML para formato (bold, italic) que Telegram soporta nativamente.
     *
     * @param  string      $firstName  Nombre del usuario
     * @param  string|null $username   @username del usuario
     * @param  array       $analysis   Resultado del análisis
     * @return string                  Mensaje HTML formateado
     */
    private function buildWarningMessage(string $firstName, ?string $username, array $analysis): string
    {
        $userDisplay = $username ? "@{$username}" : $firstName;

        $reasonText = match ($analysis['reason']) {
            'blacklisted_word' => '🔤 Palabra o frase prohibida detectada',
            'too_many_urls'    => '🔗 Exceso de enlaces/URLs detectado',
            default            => '⚠️ Contenido sospechoso detectado',
        };

        return "🛡️ <b>Aegis Filter – Mensaje Eliminado</b>\n\n"
            . "👤 Usuario: {$userDisplay}\n"
            . "📋 Motivo: {$reasonText}\n"
            . "📊 Puntaje de spam: {$analysis['score']}/100\n\n"
            . "<i>Este mensaje fue eliminado automáticamente por el sistema antispam.</i>";
    }

    /**
     * Manejar comandos del bot (mensajes que empiezan con /).
     *
     * @param  string $text      Texto del comando
     * @param  int    $chatId    ID del chat
     * @param  int    $messageId ID del mensaje
     * @return JsonResponse
     */
    private function handleCommand(string $text, int $chatId, int $messageId): JsonResponse
    {
        $command = strtolower(explode(' ', $text)[0]);

        switch ($command) {
            case '/start':
            case '/help':
                $this->telegramBot->sendMessage($chatId,
                    "🛡️ <b>Aegis Filter Bot – Ayuda</b>\n\n"
                    . "Soy un bot moderador antispam que protege tu grupo.\n\n"
                    . "<b>¿Qué hago?</b>\n"
                    . "• 🔤 Detecto palabras y frases prohibidas (spam, phishing, contenido inapropiado)\n"
                    . "• 🔗 Bloqueo mensajes con exceso de URLs (más de 2 enlaces)\n"
                    . "• 🗑️ Elimino automáticamente los mensajes spam\n"
                    . "• 📊 Registro todas las acciones para auditoría\n\n"
                    . "<b>Comandos:</b>\n"
                    . "/help – Mostrar esta ayuda\n"
                    . "/status – Ver estadísticas del grupo\n"
                    . "/rules – Ver reglas de moderación\n\n"
                    . "<i>Proyecto SI784 – Calidad y Pruebas de Software</i>"
                );
                break;

            case '/status':
                $chatStats = TelegramMessage::where('chat_id', $chatId);
                $total     = $chatStats->count();
                $spam      = (clone $chatStats)->where('status', 'spam')->count();
                $clean     = (clone $chatStats)->where('status', 'approved')->count();

                $this->telegramBot->sendMessage($chatId,
                    "📊 <b>Estadísticas de Moderación</b>\n\n"
                    . "📨 Mensajes procesados: {$total}\n"
                    . "✅ Mensajes limpios: {$clean}\n"
                    . "🚫 Spam detectado: {$spam}\n"
                    . "🛡️ Tasa de protección: " . ($total > 0 ? round(($spam / $total) * 100, 1) : 0) . "%"
                );
                break;

            case '/rules':
                $this->telegramBot->sendMessage($chatId,
                    "📜 <b>Reglas de Moderación – Aegis Filter</b>\n\n"
                    . "<b>Se bloquean mensajes que contengan:</b>\n\n"
                    . "🔤 <b>Palabras prohibidas:</b>\n"
                    . "• Spam: \"compra ahora\", \"oferta increíble\", \"gana dinero\"\n"
                    . "• Phishing: \"verifica tu cuenta\", \"ganaste un premio\"\n"
                    . "• Inapropiado: \"casino online\", \"apuestas deportivas\"\n\n"
                    . "🔗 <b>Exceso de URLs:</b>\n"
                    . "• Máximo 2 enlaces por mensaje\n"
                    . "• Más de 2 enlaces = mensaje eliminado\n\n"
                    . "<i>Las reglas se aplican automáticamente en tiempo real.</i>"
                );
                break;

            default:
                // Comando no reconocido, ignorar silenciosamente
                break;
        }

        return response()->json(['status' => 'command_handled', 'command' => $command]);
    }
}

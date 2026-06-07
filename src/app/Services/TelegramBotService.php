<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TelegramBotService – Cliente para la API de Telegram Bot
 *
 * Servicio dedicado para toda la comunicación con la API de Telegram.
 * Implementa el Principio de Responsabilidad Única (SRP):
 * Solo maneja la comunicación HTTP con Telegram, NUNCA lógica antispam.
 *
 * Métodos principales:
 *  - sendMessage()    → Enviar mensaje de advertencia al grupo
 *  - deleteMessage()  → Eliminar un mensaje spam del grupo
 *  - setWebhook()     → Registrar la URL del webhook con Telegram
 *  - getMe()          → Verificar estado de conexión del bot
 *
 * Curso: SI784 – Calidad y Pruebas de Software
 * Proyecto: Aegis Filter | Bot Moderador de Telegram
 */
class TelegramBotService
{
    /**
     * Token del bot de Telegram.
     */
    private string $botToken;

    /**
     * URL base de la API de Telegram.
     */
    private string $apiBaseUrl;

    /**
     * Constructor: obtiene la configuración desde config/services.php
     */
    public function __construct()
    {
        $this->botToken   = config('services.telegram.bot_token');
        $this->apiBaseUrl = config('services.telegram.api_base_url');
    }

    // ══════════════════════════════════════════════════
    // ENVIAR MENSAJE
    // ══════════════════════════════════════════════════

    /**
     * Enviar un mensaje de texto a un chat/grupo de Telegram.
     *
     * Se usa para enviar advertencias cuando se detecta spam:
     * "⚠️ Mensaje eliminado por contener contenido spam."
     *
     * @param  int|string  $chatId           ID del chat/grupo
     * @param  string      $text             Texto del mensaje
     * @param  int|null    $replyToMessageId ID del mensaje al que responder (opcional)
     * @return array                         Respuesta de la API de Telegram
     */
    public function sendMessage(int|string $chatId, string $text, ?int $replyToMessageId = null): array
    {
        $params = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyToMessageId) {
            $params['reply_to_message_id'] = $replyToMessageId;
        }

        return $this->makeRequest('sendMessage', $params);
    }

    // ══════════════════════════════════════════════════
    // ELIMINAR MENSAJE
    // ══════════════════════════════════════════════════

    /**
     * Eliminar un mensaje de un chat/grupo de Telegram.
     *
     * Se usa cuando el SpamFilterService detecta spam:
     * el bot elimina el mensaje del grupo inmediatamente.
     *
     * ⚠️ El bot debe ser administrador del grupo con permiso
     *    "Delete Messages" para que esto funcione.
     *
     * @param  int|string $chatId    ID del chat/grupo
     * @param  int        $messageId ID del mensaje a eliminar
     * @return array                 Respuesta de la API de Telegram
     */
    public function deleteMessage(int|string $chatId, int $messageId): array
    {
        return $this->makeRequest('deleteMessage', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ]);
    }

    // ══════════════════════════════════════════════════
    // CONFIGURAR WEBHOOK
    // ══════════════════════════════════════════════════

    /**
     * Registrar la URL del webhook con Telegram.
     *
     * Telegram enviará un POST a esta URL cada vez que el bot
     * reciba un mensaje en cualquier chat donde esté presente.
     *
     * @param  string $url URL pública HTTPS del webhook
     * @return array       Respuesta de la API de Telegram
     */
    public function setWebhook(string $url): array
    {
        $secret = config('services.telegram.webhook_secret');

        $params = [
            'url'            => $url,
            'allowed_updates' => ['message'],
        ];

        if ($secret) {
            $params['secret_token'] = $secret;
        }

        Log::info('TelegramBot: Configurando webhook', ['url' => $url]);

        return $this->makeRequest('setWebhook', $params);
    }

    /**
     * Eliminar el webhook actual.
     *
     * @return array Respuesta de la API de Telegram
     */
    public function deleteWebhook(): array
    {
        return $this->makeRequest('deleteWebhook');
    }

    /**
     * Obtener información del webhook actual.
     *
     * @return array Respuesta de la API de Telegram
     */
    public function getWebhookInfo(): array
    {
        return $this->makeRequest('getWebhookInfo');
    }

    // ══════════════════════════════════════════════════
    // VERIFICAR ESTADO DEL BOT
    // ══════════════════════════════════════════════════

    /**
     * Obtener información del bot (verifica que el token es válido).
     *
     * Retorna datos como:
     * - id: ID numérico del bot
     * - first_name: "Aegis Filter"
     * - username: "AegisFilter_bot"
     *
     * @return array Respuesta de la API de Telegram
     */
    public function getMe(): array
    {
        return $this->makeRequest('getMe');
    }

    // ══════════════════════════════════════════════════
    // HELPER: PETICIÓN HTTP A LA API DE TELEGRAM
    // ══════════════════════════════════════════════════

    /**
     * Realizar una petición HTTP POST a la API de Telegram.
     *
     * Construye la URL: https://api.telegram.org/bot{TOKEN}/{METHOD}
     * y envía los parámetros como JSON.
     *
     * @param  string $method  Método de la API (sendMessage, deleteMessage, etc.)
     * @param  array  $params  Parámetros de la petición
     * @return array           Respuesta decodificada de la API
     */
    private function makeRequest(string $method, array $params = []): array
    {
        $url = $this->apiBaseUrl . $this->botToken . '/' . $method;

        try {
            $response = Http::timeout(10)
                ->post($url, $params);

            $result = $response->json();

            if (!$response->successful() || !($result['ok'] ?? false)) {
                Log::error('TelegramBot: Error en API', [
                    'method'      => $method,
                    'status_code' => $response->status(),
                    'response'    => $result,
                ]);
            }

            return $result ?? [];

        } catch (\Exception $e) {
            Log::error('TelegramBot: Excepción en petición HTTP', [
                'method'  => $method,
                'error'   => $e->getMessage(),
            ]);

            return [
                'ok'          => false,
                'description' => $e->getMessage(),
            ];
        }
    }
}

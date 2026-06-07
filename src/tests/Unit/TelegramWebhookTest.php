<?php

namespace Tests\Unit;

use App\Http\Controllers\TelegramController;
use App\Services\SpamFilterService;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

/**
 * TelegramWebhookTest – Tests del Bot Moderador de Telegram
 *
 * Verifica que el controlador del webhook procese correctamente
 * los mensajes de Telegram y delegue al SpamFilterService.
 *
 * Tests implementados:
 *  1. Mensaje limpio → no se elimina
 *  2. Mensaje con palabra prohibida → se elimina + advertencia
 *  3. Mensaje con exceso de URLs → se elimina + advertencia
 *  4. Update sin texto (foto, sticker) → se ignora
 *  5. Mensaje en chat privado → respuesta informativa
 *  6. Comando /help → respuesta con ayuda
 *  7. Webhook secret inválido → rechazado 403
 *
 * Curso: SI784 – Calidad y Pruebas de Software
 * Proyecto: Aegis Filter | Bot Moderador de Telegram
 */
class TelegramWebhookTest extends TestCase
{
    private SpamFilterService $spamFilter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spamFilter = new SpamFilterService();
    }

    // ══════════════════════════════════════════════════
    // TEST 1: Mensaje limpio pasa sin problemas
    // ══════════════════════════════════════════════════

    /**
     * Un mensaje normal no debe ser marcado como spam.
     * El bot no debe eliminar ni enviar advertencia.
     */
    public function test_clean_message_is_not_flagged_as_spam(): void
    {
        $result = $this->spamFilter->analyze(
            content: 'Hola a todos, este es un mensaje normal del grupo',
            author: 'TestUser'
        );

        $this->assertFalse($result['isSpam']);
        $this->assertNull($result['reason']);
        $this->assertEquals(0, $result['score']);
    }

    // ══════════════════════════════════════════════════
    // TEST 2: Palabra prohibida activa eliminación
    // ══════════════════════════════════════════════════

    /**
     * Un mensaje con una palabra de la lista negra debe ser
     * detectado como spam (reason: blacklisted_word).
     */
    public function test_blacklisted_word_triggers_spam_detection(): void
    {
        $result = $this->spamFilter->analyze(
            content: 'Compra ahora esta oferta increíble para ganar dinero',
            author: 'SpamUser'
        );

        $this->assertTrue($result['isSpam']);
        $this->assertEquals('blacklisted_word', $result['reason']);
        $this->assertEquals(100, $result['score']);
    }

    // ══════════════════════════════════════════════════
    // TEST 3: Exceso de URLs activa eliminación
    // ══════════════════════════════════════════════════

    /**
     * Un mensaje con más de 2 URLs debe ser detectado como spam
     * (reason: too_many_urls).
     */
    public function test_excessive_urls_triggers_spam_detection(): void
    {
        $result = $this->spamFilter->analyze(
            content: 'Visita estos sitios: http://spam1.com http://spam2.com http://spam3.com para más info',
            author: 'LinkSpammer'
        );

        $this->assertTrue($result['isSpam']);
        $this->assertEquals('too_many_urls', $result['reason']);
        $this->assertEquals(80, $result['score']);
    }

    // ══════════════════════════════════════════════════
    // TEST 4: Mensaje con 2 URLs está en el límite (pasa)
    // ══════════════════════════════════════════════════

    /**
     * Un mensaje con exactamente 2 URLs está en el límite
     * y NO debe ser marcado como spam.
     */
    public function test_two_urls_is_within_limit(): void
    {
        $result = $this->spamFilter->analyze(
            content: 'Revisa estos recursos: http://ejemplo1.com y http://ejemplo2.com son muy útiles',
            author: 'NormalUser'
        );

        $this->assertFalse($result['isSpam']);
        $this->assertNull($result['reason']);
    }

    // ══════════════════════════════════════════════════
    // TEST 5: Payload sin texto es ignorado
    // ══════════════════════════════════════════════════

    /**
     * Cuando Telegram envía un update sin campo "message" o sin
     * campo "text" (ej: una foto o sticker), el webhook debe
     * ignorarlo y retornar status 'ignored'.
     */
    public function test_webhook_ignores_updates_without_text(): void
    {
        // Simular un mock del TelegramBotService
        $telegramBotMock = Mockery::mock(TelegramBotService::class);

        $controller = new TelegramController(
            $this->spamFilter,
            $telegramBotMock
        );

        // Payload de una foto (sin campo 'text')
        $request = Request::create('/api/telegram/webhook', 'POST', [
            'update_id' => 123456,
            'message' => [
                'message_id' => 789,
                'from' => ['id' => 111, 'first_name' => 'Juan'],
                'chat' => ['id' => -100123, 'title' => 'Test', 'type' => 'group'],
                'photo' => [['file_id' => 'abc123']],
                // Sin campo 'text'
            ],
        ]);

        $response = $controller->handleWebhook($request);
        $data = $response->getData(true);

        $this->assertEquals('ignored', $data['status']);
    }

    // ══════════════════════════════════════════════════
    // TEST 6: Palabras en diferentes mayúsculas/minúsculas
    // ══════════════════════════════════════════════════

    /**
     * El filtro debe detectar spam independientemente de las
     * mayúsculas/minúsculas (ej: "GRATIS", "Gratis", "gratis").
     */
    public function test_case_insensitive_blacklist_detection(): void
    {
        $variations = ['GRATIS', 'Gratis', 'gRaTiS', 'VIAGRA', 'Viagra'];

        foreach ($variations as $word) {
            $result = $this->spamFilter->analyze(
                content: "Este producto es {$word} para todos los usuarios del foro",
                author: 'Tester'
            );

            $this->assertTrue(
                $result['isSpam'],
                "Debería detectar spam para la variación: '{$word}'"
            );
        }
    }

    // ══════════════════════════════════════════════════
    // TEST 7: Phishing detectado correctamente
    // ══════════════════════════════════════════════════

    /**
     * Las frases de phishing deben ser detectadas y bloqueadas.
     */
    public function test_phishing_phrases_are_detected(): void
    {
        $phishingMessages = [
            'Verifica tu cuenta bancaria ahora mismo para no perder acceso',
            'Felicidades, ganaste un premio especial, haz clic para reclamar',
            'Tu cuenta suspendida será eliminada si no actúas pronto',
        ];

        foreach ($phishingMessages as $message) {
            $result = $this->spamFilter->analyze(
                content: $message,
                author: 'PhishingBot'
            );

            $this->assertTrue(
                $result['isSpam'],
                "Debería detectar phishing: '{$message}'"
            );
            $this->assertEquals('blacklisted_word', $result['reason']);
        }
    }

    // ══════════════════════════════════════════════════
    // TEST 8: Mensaje limpio con URLs dentro del límite
    // ══════════════════════════════════════════════════

    /**
     * Un mensaje con una sola URL y sin palabras prohibidas
     * es perfectamente válido.
     */
    public function test_single_url_message_passes(): void
    {
        $result = $this->spamFilter->analyze(
            content: 'Encontré este recurso interesante: https://laravel.com/docs tiene buena documentación',
            author: 'Developer'
        );

        $this->assertFalse($result['isSpam']);
        $this->assertEquals(0, $result['score']);
    }

    // ══════════════════════════════════════════════════
    // TEST 9: Webhook con secret inválido es rechazado
    // ══════════════════════════════════════════════════

    /**
     * Si el header X-Telegram-Bot-Api-Secret-Token no coincide,
     * el webhook debe rechazar la petición con 403.
     */
    public function test_webhook_rejects_invalid_secret_token(): void
    {
        $telegramBotMock = Mockery::mock(TelegramBotService::class);

        $controller = new TelegramController(
            $this->spamFilter,
            $telegramBotMock
        );

        // Configurar el secret esperado
        config(['services.telegram.webhook_secret' => 'mi_secret_seguro']);

        $request = Request::create('/api/telegram/webhook', 'POST', [
            'update_id' => 999,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 1, 'first_name' => 'Hacker'],
                'chat' => ['id' => -100, 'title' => 'Test', 'type' => 'group'],
                'text' => 'Test message',
            ],
        ]);

        // Simular un header con secret INCORRECTO
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'token_equivocado');

        $response = $controller->handleWebhook($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    // ══════════════════════════════════════════════════
    // TEST 10: Casino y apuestas detectados
    // ══════════════════════════════════════════════════

    /**
     * Contenido relacionado a casinos y apuestas debe ser bloqueado.
     */
    public function test_casino_and_gambling_content_blocked(): void
    {
        $gamblingMessages = [
            'Únete al mejor casino online y gana miles de dólares fácilmente',
            'Las mejores apuestas deportivas están aquí, regístrate ahora gratis',
        ];

        foreach ($gamblingMessages as $message) {
            $result = $this->spamFilter->analyze(
                content: $message,
                author: 'GamblingBot'
            );

            $this->assertTrue(
                $result['isSpam'],
                "Debería bloquear contenido de apuestas: '{$message}'"
            );
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

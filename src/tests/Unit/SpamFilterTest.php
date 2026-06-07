<?php

namespace Tests\Unit;
use App\Services\SpamFilterService;
use PHPUnit\Framework\TestCase;

/**
 *
 * SpamFilterTest – Suite de Pruebas BDD para SpamFilterService
 *
 * Metodología: Behavior-Driven Development (BDD)
 * Cada test documenta su comportamiento en formato Gherkin:
 *   DADO (Arrange)   – Estado inicial del sistema
 *   CUANDO (Act)     – Acción que se ejecuta
 *   ENTONCES (Assert) – Resultado esperado
 *
 * Cobertura:
 *   ✅ Regla 1: Bloqueo por palabras negras
 *   ✅ Regla 2: Bloqueo por exceso de URLs
 *   ✅ Casos límite y mensajes limpios
 *
 * Curso: SI784 – Calidad y Pruebas de Software
 * Proyecto: Aegis Filter | Tech Hub Forum
 *
 */
class SpamFilterTest extends TestCase
{
    /**
     * Instancia del servicio bajo prueba.
     *
     * @var SpamFilterService
     */
    private SpamFilterService $spamFilter;

    /**
     * Configuración inicial antes de cada prueba.
     * Se ejecuta automáticamente por PHPUnit antes de cada test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->spamFilter = new SpamFilterService();
    }

    // ══════════════════════════════════════════════════════════
    // GRUPO A: Mensajes Limpios (Aprobados)
    // ══════════════════════════════════════════════════════════

    /**
     * @test
     * @group aprobados
     *
     * Gherkin:
     *   DADO que un usuario del foro Tech Hub escribe un mensaje normal
     *   CUANDO el mensaje es analizado por SpamFilterService
     *   ENTONCES el resultado debe ser isSpam=false y reason=null
     */
    public function test_mensaje_normal_es_aprobado(): void
    {
        // DADO: Un mensaje limpio sin palabras prohibidas ni URLs
        $mensaje = 'Hola, tengo una pregunta sobre el nuevo framework Laravel. ¿Alguien puede ayudarme?';

        // CUANDO: El servicio analiza el mensaje
        $resultado = $this->spamFilter->analyze($mensaje, 'JuanPerez');

        // ENTONCES: El mensaje debe ser aprobado
        $this->assertFalse($resultado['isSpam'], 'Un mensaje normal no debería ser marcado como spam');
        $this->assertNull($resultado['reason'], 'Un mensaje limpio no debe tener razón de bloqueo');
        $this->assertEquals(0, $resultado['score'], 'El puntaje de spam debe ser 0 para mensajes limpios');
    }

    /**
     * @test
     * @group aprobados
     *
     * Gherkin:
     *   DADO que un mensaje contiene exactamente 2 URLs (el límite permitido)
     *   CUANDO el servicio evalúa el contenido
     *   ENTONCES el mensaje debe ser aprobado sin restricciones
     */
    public function test_mensaje_con_dos_urls_exactas_es_aprobado(): void
    {
        // DADO: Mensaje con exactamente 2 URLs (en el límite)
        $mensaje = 'Revisa esta documentación: https://laravel.com y también https://php.net para más información.';

        // CUANDO
        $resultado = $this->spamFilter->analyze($mensaje, 'MariaLopez');

        // ENTONCES: 2 URLs exactas deben ser aprobadas
        $this->assertFalse($resultado['isSpam'], 'Un mensaje con exactamente 2 URLs debe ser aprobado');
        $this->assertEquals(0, $resultado['score']);
    }

    /**
     * @test
     * @group aprobados
     *
     * Gherkin:
     *   DADO que el mensaje está completamente vacío
     *   CUANDO se ejecuta el análisis
     *   ENTONCES no debe marcar como spam (no hay contenido que analizar)
     */
    public function test_mensaje_vacio_no_es_spam(): void
    {
        // DADO: Mensaje vacío
        $mensaje = '';

        // CUANDO
        $resultado = $this->spamFilter->analyze($mensaje);

        // ENTONCES: Un mensaje vacío no puede ser spam
        $this->assertFalse($resultado['isSpam']);
    }

    // ══════════════════════════════════════════════════════════
    // GRUPO B: Bloqueo por Palabras Negras
    // ══════════════════════════════════════════════════════════

    /**
     * @test
     * @group palabras_negras
     *
     * Gherkin:
     *   DADO que un mensaje contiene la palabra "gratis" de la lista negra
     *   CUANDO SpamFilterService analiza el contenido
     *   ENTONCES debe retornar isSpam=true con reason="blacklisted_word"
     */
    public function test_mensaje_con_palabra_negra_es_spam(): void
    {
        // DADO: Mensaje con palabra prohibida "gratis"
        $mensaje = 'Obtén bitcoin gratis haciendo clic en este enlace mágico';

        // CUANDO
        $resultado = $this->spamFilter->analyze($mensaje, 'SpamUser123');

        // ENTONCES: Debe ser detectado como spam
        $this->assertTrue($resultado['isSpam'], 'Un mensaje con palabra negra debe ser marcado como spam');
        $this->assertEquals('blacklisted_word', $resultado['reason']);
        $this->assertEquals(100, $resultado['score'], 'El puntaje debe ser 100 para palabras negras');
    }

    /**
     * @test
     * @group palabras_negras
     *
     * Gherkin:
     *   DADO que un mensaje contiene la frase "compra ahora" en mayúsculas
     *   CUANDO el servicio normaliza y analiza el texto
     *   ENTONCES debe detectar la palabra negra independientemente del caso
     */
    public function test_deteccion_es_insensible_al_caso(): void
    {
        // DADO: Palabra negra en mayúsculas
        $mensaje = 'COMPRA AHORA y obtén descuentos increíbles en nuestro catálogo';

        // CUANDO
        $resultado = $this->spamFilter->analyze($mensaje);

        // ENTONCES: La detección debe funcionar sin importar el caso
        $this->assertTrue($resultado['isSpam'], 'La detección debe ser insensible al caso');
        $this->assertEquals('blacklisted_word', $resultado['reason']);
    }

    /**
     * @test
     * @group palabras_negras
     *
     * Gherkin:
     *   DADO que se agrega dinámicamente la palabra "maliciosa" a la lista negra
     *   CUANDO se analiza un mensaje que contiene esa palabra
     *   ENTONCES el sistema debe bloquear el mensaje correctamente
     */
    public function test_agregar_palabra_negra_dinamicamente(): void
    {
        // DADO: Se agrega una nueva palabra negra en tiempo de ejecución
        $this->spamFilter->addBlacklistedWord('maliciosa');
        $mensaje = 'Este es un mensaje con palabra maliciosa inyectada';

        // CUANDO
        $resultado = $this->spamFilter->analyze($mensaje);

        // ENTONCES: La nueva palabra debe ser detectada
        $this->assertTrue($resultado['isSpam'], 'Las palabras agregadas dinámicamente deben ser detectadas');
        $this->assertEquals('blacklisted_word', $resultado['reason']);
    }

    /**
     * @test
     * @group palabras_negras
     *
     * Gherkin:
     *   DADO que el servicio tiene una lista negra configurada
     *   CUANDO se obtiene la lista con getBlacklistedWords()
     *   ENTONCES debe retornar un array no vacío
     */
    public function test_lista_negra_no_esta_vacia(): void
    {
        // DADO / CUANDO
        $lista = $this->spamFilter->getBlacklistedWords();

        // ENTONCES: La lista negra por defecto debe tener palabras
        $this->assertIsArray($lista);
        $this->assertNotEmpty($lista, 'La lista negra no debe estar vacía');
        $this->assertGreaterThan(5, count($lista), 'Debe haber al menos 5 palabras en la lista negra');
    }

    /**
     * @test
     * @group palabras_negras
     *
     * Gherkin:
     *   DADO que un mensaje contiene la palabra "viagra" (spam farmacéutico)
     *   CUANDO se ejecuta el análisis antispam
     *   ENTONCES debe ser bloqueado con razón "blacklisted_word"
     */
    public function test_spam_farmaceutico_es_bloqueado(): void
    {
        // DADO: Mensaje de spam farmacéutico
        $mensaje = 'Consigue viagra al mejor precio, envío discreto a todo el país.';

        // CUANDO
        $resultado = $this->spamFilter->analyze($mensaje);

        // ENTONCES
        $this->assertTrue($resultado['isSpam']);
        $this->assertEquals('blacklisted_word', $resultado['reason']);
    }

    // ══════════════════════════════════════════════════════════
    // GRUPO C: Bloqueo por Exceso de URLs
    // ══════════════════════════════════════════════════════════

    /**
     * @test
     * @group exceso_urls
     *
     * Gherkin:
     *   DADO que un mensaje contiene 3 URLs (superando el límite de 2)
     *   CUANDO SpamFilterService analiza el contenido
     *   ENTONCES debe retornar isSpam=true con reason="too_many_urls"
     */
    public function test_mensaje_con_tres_urls_es_spam(): void
    {
        // DADO: Mensaje con 3 URLs (supera el límite)
        $mensaje = 'Visita https://site1.com y también https://site2.com además de https://site3.com';

        // CUANDO
        $resultado = $this->spamFilter->analyze($mensaje, 'SpamBot');

        // ENTONCES: Debe ser detectado como spam por exceso de URLs
        $this->assertTrue($resultado['isSpam'], 'Un mensaje con más de 2 URLs debe ser marcado como spam');
        $this->assertEquals('too_many_urls', $resultado['reason']);
        $this->assertEquals(80, $resultado['score']);
    }

    /**
     * @test
     * @group exceso_urls
     *
     * Gherkin:
     *   DADO que un mensaje contiene 5 URLs de tipo http y https
     *   CUANDO el servicio ejecuta checkExcessiveUrls()
     *   ENTONCES el contador de URLs debe ser 5 y exceeded=true
     */
    public function test_contador_urls_detecta_multiples_protocolos(): void
    {
        // DADO: Mensaje con URLs de distintos protocolos
        $mensaje = 'Links: http://a.com https://b.com ftp://c.com https://d.net http://e.org';

        // CUANDO: Llamamos directamente al método de verificación
        $resultado = $this->spamFilter->checkExcessiveUrls($mensaje);

        // ENTONCES
        $this->assertTrue($resultado['exceeded'], 'Debe exceder el límite de URLs');
        $this->assertEquals(5, $resultado['count'], 'Deben contarse exactamente 5 URLs');
    }

    /**
     * @test
     * @group exceso_urls
     *
     * Gherkin:
     *   DADO que se cambia el límite máximo de URLs a 5
     *   CUANDO se analiza un mensaje con 4 URLs
     *   ENTONCES el mensaje debe ser aprobado (está por debajo del nuevo límite)
     */
    public function test_limite_de_urls_es_configurable(): void
    {
        // DADO: Se aumenta el límite a 5 URLs
        $this->spamFilter->setMaxAllowedUrls(5);
        $mensaje = 'Links: https://a.com https://b.com https://c.com https://d.com (solo 4 links)';

        // CUANDO
        $resultado = $this->spamFilter->analyze($mensaje);

        // ENTONCES: Con límite=5, un mensaje con 4 URLs debe aprobarse
        $this->assertFalse($resultado['isSpam'], 'Con límite ampliado, 4 URLs no debe ser spam');
    }

    /**
     * @test
     * @group exceso_urls
     *
     * Gherkin:
     *   DADO que un mensaje no contiene ninguna URL
     *   CUANDO se ejecuta checkExcessiveUrls()
     *   ENTONCES el conteo debe ser 0 y exceeded=false
     */
    public function test_mensaje_sin_urls_no_excede_limite(): void
    {
        // DADO: Mensaje sin URLs
        $mensaje = 'Este es un mensaje completamente normal sin ningún enlace';

        // CUANDO
        $resultado = $this->spamFilter->checkExcessiveUrls($mensaje);

        // ENTONCES
        $this->assertFalse($resultado['exceeded']);
        $this->assertEquals(0, $resultado['count']);
    }

    // ══════════════════════════════════════════════════════════
    // GRUPO D: Pruebas del Método analyze() – Estructura
    // ══════════════════════════════════════════════════════════

    /**
     * @test
     * @group estructura
     *
     * Gherkin:
     *   DADO que se llama al método analyze()
     *   CUANDO se procesa cualquier mensaje
     *   ENTONCES el resultado debe tener las claves: isSpam, reason, score, detail
     */
    public function test_analyze_retorna_estructura_correcta(): void
    {
        // DADO
        $mensaje = 'Mensaje de prueba para verificar estructura';

        // CUANDO
        $resultado = $this->spamFilter->analyze($mensaje);

        // ENTONCES: La respuesta debe tener exactamente las claves esperadas
        $this->assertArrayHasKey('isSpam', $resultado);
        $this->assertArrayHasKey('reason', $resultado);
        $this->assertArrayHasKey('score', $resultado);
        $this->assertArrayHasKey('detail', $resultado);
        $this->assertIsBool($resultado['isSpam']);
        $this->assertIsInt($resultado['score']);
    }

    /**
     * @test
     * @group estructura
     *
     * Gherkin:
     *   DADO que un mensaje es spam por palabras negras
     *   CUANDO se revisa el campo 'detail'
     *   ENTONCES debe contener información descriptiva del bloqueo
     */
    public function test_mensaje_spam_tiene_detalle_descriptivo(): void
    {
        // DADO
        $mensaje = 'Gana dinero fácil sin esfuerzo';

        // CUANDO
        $resultado = $this->spamFilter->analyze($mensaje);

        // ENTONCES
        $this->assertTrue($resultado['isSpam']);
        $this->assertNotEmpty($resultado['detail'], 'El campo detail debe tener descripción del bloqueo');
    }
}

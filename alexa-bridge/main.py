"""
Aegis Filter – Alexa Bridge Microservice
=========================================

Microservicio intermediario entre Amazon Alexa y el motor antispam
Aegis Filter (Laravel). Recibe peticiones de voz via Alexa Skills Kit,
consulta al motor de Laravel y devuelve la respuesta formateada para Alexa.

Arquitectura:
  [Alexa Device] → [Amazon Cloud] → [Alexa Bridge (este servicio)]
                                          ↓ POST /api/analyze
                                     [Aegis Core (Laravel)]

Curso: SI784 – Calidad y Pruebas de Software
Proyecto: Aegis Filter | Tech Hub Forum
"""

import os
import json
import logging
import base64
from datetime import datetime, timezone
from urllib.parse import urlparse
from typing import Any

import httpx
from fastapi import FastAPI, Request, HTTPException
from fastapi.concurrency import run_in_threadpool
from fastapi.responses import JSONResponse

from ask_sdk_core.skill_builder import SkillBuilder
from ask_sdk_core.dispatch_components import (
    AbstractRequestHandler,
    AbstractExceptionHandler,
)
from ask_sdk_core.utils import is_request_type, is_intent_name
from ask_sdk_core.handler_input import HandlerInput
from ask_sdk_model import RequestEnvelope
from ask_sdk_model import Response as AlexaResponse

from cryptography import x509
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.exceptions import InvalidSignature


# ══════════════════════════════════════════════════
# CONFIGURACIÓN
# ══════════════════════════════════════════════════

AEGIS_CORE_URL: str = os.getenv(
    "AEGIS_CORE_URL", "http://aegis-core:8000/api/analyze"
)
AEGIS_REQUEST_TIMEOUT: int = int(os.getenv("AEGIS_REQUEST_TIMEOUT", "10"))
ALEXA_SKILL_ID: str = os.getenv("ALEXA_SKILL_ID", "")
VERIFY_SIGNATURES: bool = os.getenv(
    "VERIFY_SIGNATURES", "true"
).lower() == "true"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(name)s | %(levelname)s | %(message)s",
)
logger = logging.getLogger("alexa-bridge")


# ══════════════════════════════════════════════════════════════════
# VERIFICACIÓN DE SEGURIDAD DE AMAZON ALEXA
# ══════════════════════════════════════════════════════════════════
#
# Amazon exige que todo Custom HTTPS Endpoint valide:
#   1. Timestamp del request (máx. 150 segundos de antigüedad)
#   2. URL del certificado (debe ser de Amazon S3: s3.amazonaws.com)
#   3. Vigencia y SAN del certificado (echo-api.amazon.com)
#   4. Firma RSA del body (SHA-256 o SHA-1)
#
# Ref: https://developer.amazon.com/docs/alexa/custom-skills/
#      host-a-custom-skill-as-a-web-service.html
# ══════════════════════════════════════════════════════════════════

class AlexaRequestVerifier:
    """Verifica la autenticidad de las peticiones de Amazon Alexa."""

    CERT_URL_SCHEME = "https"
    CERT_URL_HOST = "s3.amazonaws.com"
    CERT_URL_PATH_PREFIX = "/echo.api/"
    CERT_URL_PORT = 443
    SAN_DOMAIN = "echo-api.amazon.com"
    MAX_TIMESTAMP_TOLERANCE = 150  # segundos

    # Cache de certificados para evitar descargas repetidas
    _cert_cache: dict[str, x509.Certificate] = {}

    @classmethod
    def verify(
        cls,
        headers: dict[str, str],
        body: str,
        request_envelope: RequestEnvelope,
    ) -> None:
        """
        Ejecuta todas las validaciones de seguridad.

        Args:
            headers: Headers HTTP del request (claves en minúsculas).
            body: Body crudo del request (string JSON).
            request_envelope: Objeto RequestEnvelope deserializado.

        Raises:
            ValueError: Si alguna validación falla.
        """
        cls._verify_timestamp(request_envelope)
        cls._verify_skill_id(request_envelope)

        # Intentar Signature-256 (SHA-256, preferido) o Signature (SHA-1)
        cert_url = headers.get("signaturecertchainurl", "")
        signature_b64 = headers.get("signature-256", "")
        hash_algo: hashes.HashAlgorithm = hashes.SHA256()

        if not signature_b64:
            signature_b64 = headers.get("signature", "")
            hash_algo = hashes.SHA1()

        if not cert_url or not signature_b64:
            raise ValueError(
                "Headers de verificación ausentes: "
                "SignatureCertChainUrl y/o Signature(-256)"
            )

        cls._validate_cert_url(cert_url)
        cert = cls._download_certificate(cert_url)
        cls._validate_certificate(cert)
        cls._verify_signature(cert, signature_b64, body, hash_algo)

        logger.info("Verificación de firma de Amazon exitosa")

    # ── Timestamp ──────────────────────────────────────

    @classmethod
    def _verify_timestamp(cls, envelope: RequestEnvelope) -> None:
        """Verifica que el request no sea más antiguo que 150 segundos."""
        timestamp = getattr(envelope.request, "timestamp", None)
        if timestamp is None:
            raise ValueError("Request sin timestamp")

        if isinstance(timestamp, str):
            request_time = datetime.fromisoformat(
                timestamp.replace("Z", "+00:00")
            )
        elif isinstance(timestamp, datetime):
            request_time = (
                timestamp.replace(tzinfo=timezone.utc)
                if timestamp.tzinfo is None
                else timestamp
            )
        else:
            raise ValueError(f"Formato de timestamp no soportado: {type(timestamp)}")

        now = datetime.now(timezone.utc)
        diff = abs((now - request_time).total_seconds())

        if diff > cls.MAX_TIMESTAMP_TOLERANCE:
            raise ValueError(
                f"Timestamp expirado: {diff:.0f}s "
                f"(máximo {cls.MAX_TIMESTAMP_TOLERANCE}s)"
            )

    # ── Skill ID ───────────────────────────────────────

    @classmethod
    def _verify_skill_id(cls, envelope: RequestEnvelope) -> None:
        """Verifica que el Skill ID coincida con el configurado."""
        if not ALEXA_SKILL_ID:
            return  # Omitir si no está configurado

        incoming_id = None
        if envelope.session and envelope.session.application:
            incoming_id = envelope.session.application.application_id

        if incoming_id != ALEXA_SKILL_ID:
            raise ValueError(
                f"Skill ID inválido: esperado={ALEXA_SKILL_ID}, "
                f"recibido={incoming_id}"
            )

    # ── Certificate URL ────────────────────────────────

    @classmethod
    def _validate_cert_url(cls, url: str) -> None:
        """Valida que la URL del certificado sea legítima de Amazon S3."""
        parsed = urlparse(url.lower())

        if parsed.scheme != cls.CERT_URL_SCHEME:
            raise ValueError(f"Esquema de URL inválido: {parsed.scheme}")

        if parsed.hostname != cls.CERT_URL_HOST:
            raise ValueError(f"Host inválido: {parsed.hostname}")

        if not parsed.path.startswith(cls.CERT_URL_PATH_PREFIX):
            raise ValueError(f"Path inválido: {parsed.path}")

        port = parsed.port or cls.CERT_URL_PORT
        if port != cls.CERT_URL_PORT:
            raise ValueError(f"Puerto inválido: {port}")

    # ── Certificate Download & Validation ──────────────

    @classmethod
    def _download_certificate(cls, url: str) -> x509.Certificate:
        """Descarga el certificado de Amazon (con cache en memoria)."""
        if url in cls._cert_cache:
            cert = cls._cert_cache[url]
            now = datetime.now(timezone.utc)
            not_after = cert.not_valid_after_utc
            if not_after > now:
                return cert

        with httpx.Client(timeout=10) as client:
            response = client.get(url)
            response.raise_for_status()

        cert = x509.load_pem_x509_certificate(response.content)
        cls._cert_cache[url] = cert
        return cert

    @classmethod
    def _validate_certificate(cls, cert: x509.Certificate) -> None:
        """Valida vigencia y Subject Alternative Name del certificado."""
        now = datetime.now(timezone.utc)

        if cert.not_valid_before_utc > now:
            raise ValueError("Certificado aún no vigente")

        if cert.not_valid_after_utc < now:
            raise ValueError("Certificado expirado")

        # Verificar que el SAN contenga echo-api.amazon.com
        try:
            san = cert.extensions.get_extension_for_class(
                x509.SubjectAlternativeName
            )
            dns_names = san.value.get_values_for_type(x509.DNSName)
            if cls.SAN_DOMAIN not in dns_names:
                raise ValueError(
                    f"SAN '{cls.SAN_DOMAIN}' ausente en certificado"
                )
        except x509.ExtensionNotFound:
            raise ValueError("Certificado sin extensión SAN")

    # ── Signature Verification ─────────────────────────

    @classmethod
    def _verify_signature(
        cls,
        cert: x509.Certificate,
        signature_b64: str,
        body: str,
        hash_algorithm: hashes.HashAlgorithm,
    ) -> None:
        """Verifica la firma RSA del body usando el certificado."""
        signature = base64.b64decode(signature_b64)
        public_key = cert.public_key()

        try:
            public_key.verify(
                signature,
                body.encode("utf-8"),
                padding.PKCS1v15(),
                hash_algorithm,
            )
        except InvalidSignature:
            raise ValueError("Firma RSA inválida: el body fue alterado")


# ══════════════════════════════════════════════════
# ALEXA REQUEST HANDLERS
# ══════════════════════════════════════════════════

class LaunchRequestHandler(AbstractRequestHandler):
    """Maneja el evento de apertura de la skill (LaunchRequest)."""

    def can_handle(self, handler_input: HandlerInput) -> bool:
        return is_request_type("LaunchRequest")(handler_input)

    def handle(self, handler_input: HandlerInput) -> AlexaResponse:
        speech = (
            "Bienvenido a Aegis Filter. "
            "Puedo analizar un mensaje para detectar spam. "
            "Dime el texto que quieres verificar."
        )
        reprompt = "¿Qué mensaje deseas analizar?"

        return (
            handler_input.response_builder
            .speak(speech)
            .ask(reprompt)
            .response
        )


class CheckSpamIntentHandler(AbstractRequestHandler):
    """
    Maneja el intent CheckSpamIntent.

    Flujo:
      1. Extrae el texto del slot 'Mensaje'
      2. Consulta al Aegis Core (POST /api/analyze)
      3. Formatea la respuesta de voz según resultado
    """

    def can_handle(self, handler_input: HandlerInput) -> bool:
        return is_intent_name("CheckSpamIntent")(handler_input)

    def handle(self, handler_input: HandlerInput) -> AlexaResponse:
        # ── Paso 1: Extraer el slot 'Mensaje' ─────────
        slots = handler_input.request_envelope.request.intent.slots
        mensaje_slot = slots.get("Mensaje") if slots else None

        if not mensaje_slot or not mensaje_slot.value:
            speech = (
                "No pude entender el mensaje. "
                "Por favor, repite el texto que deseas analizar."
            )
            return (
                handler_input.response_builder
                .speak(speech)
                .ask("¿Qué mensaje deseas analizar?")
                .response
            )

        texto = mensaje_slot.value
        logger.info("Analizando mensaje: '%s'", texto)

        # ── Paso 2: Consultar al Aegis Core ───────────
        try:
            resultado = self._consultar_aegis_core(texto)
        except httpx.TimeoutException:
            logger.error("Timeout al consultar Aegis Core")
            return (
                handler_input.response_builder
                .speak(
                    "El servicio de análisis no respondió a tiempo. "
                    "Por favor, intenta de nuevo más tarde."
                )
                .response
            )
        except httpx.HTTPStatusError as exc:
            logger.error(
                "Error HTTP %d de Aegis Core",
                exc.response.status_code,
            )
            return (
                handler_input.response_builder
                .speak(
                    "Hubo un error al comunicarse con el motor antispam. "
                    "Por favor, intenta más tarde."
                )
                .response
            )
        except httpx.HTTPError as exc:
            logger.error("Error de conexión con Aegis Core: %s", exc)
            return (
                handler_input.response_builder
                .speak(
                    "No se pudo conectar con el motor antispam. "
                    "Por favor, intenta más tarde."
                )
                .response
            )

        # ── Paso 3: Formatear respuesta según resultado ──
        is_spam = resultado.get("is_spam", False)

        if is_spam:
            speech = (
                "El sistema Aegis ha bloqueado este mensaje "
                "por contener spam."
            )
        else:
            speech = (
                "El mensaje está limpio y ha sido "
                "procesado correctamente."
            )

        logger.info("Resultado: is_spam=%s", is_spam)
        return handler_input.response_builder.speak(speech).response

    @staticmethod
    def _consultar_aegis_core(texto: str) -> dict[str, Any]:
        """
        POST sincrónico al motor Aegis Core.

        Args:
            texto: Texto transcrito por Alexa.

        Returns:
            Diccionario con clave 'is_spam' (bool).

        Raises:
            httpx.TimeoutException: Si el Core no responde a tiempo.
            httpx.HTTPStatusError: Si el Core devuelve un error HTTP.
            httpx.HTTPError: Si hay un error de conexión.
        """
        with httpx.Client(timeout=AEGIS_REQUEST_TIMEOUT) as client:
            response = client.post(
                AEGIS_CORE_URL,
                json={"text": texto},
                headers={
                    "Accept": "application/json",
                    "Content-Type": "application/json",
                },
            )
            response.raise_for_status()
            return response.json()


class HelpIntentHandler(AbstractRequestHandler):
    """Maneja AMAZON.HelpIntent."""

    def can_handle(self, handler_input: HandlerInput) -> bool:
        return is_intent_name("AMAZON.HelpIntent")(handler_input)

    def handle(self, handler_input: HandlerInput) -> AlexaResponse:
        speech = (
            "Aegis Filter analiza mensajes para detectar spam. "
            "Puedes decirme: analiza el mensaje, seguido del texto "
            "que quieras verificar. ¿Qué mensaje deseas analizar?"
        )
        return (
            handler_input.response_builder
            .speak(speech)
            .ask("¿Qué mensaje deseas analizar?")
            .response
        )


class CancelStopIntentHandler(AbstractRequestHandler):
    """Maneja AMAZON.CancelIntent y AMAZON.StopIntent."""

    def can_handle(self, handler_input: HandlerInput) -> bool:
        return (
            is_intent_name("AMAZON.CancelIntent")(handler_input)
            or is_intent_name("AMAZON.StopIntent")(handler_input)
        )

    def handle(self, handler_input: HandlerInput) -> AlexaResponse:
        return (
            handler_input.response_builder
            .speak("Hasta luego. Aegis Filter siempre protegiendo.")
            .response
        )


class FallbackIntentHandler(AbstractRequestHandler):
    """Maneja AMAZON.FallbackIntent (intents no reconocidos)."""

    def can_handle(self, handler_input: HandlerInput) -> bool:
        return is_intent_name("AMAZON.FallbackIntent")(handler_input)

    def handle(self, handler_input: HandlerInput) -> AlexaResponse:
        speech = (
            "Lo siento, no entendí eso. "
            "Puedes pedirme que analice un mensaje diciendo: "
            "analiza el mensaje, seguido de tu texto."
        )
        return (
            handler_input.response_builder
            .speak(speech)
            .ask("¿Qué mensaje deseas analizar?")
            .response
        )


class SessionEndedRequestHandler(AbstractRequestHandler):
    """Maneja el cierre de sesión (SessionEndedRequest)."""

    def can_handle(self, handler_input: HandlerInput) -> bool:
        return is_request_type("SessionEndedRequest")(handler_input)

    def handle(self, handler_input: HandlerInput) -> AlexaResponse:
        reason = getattr(
            handler_input.request_envelope.request, "reason", "unknown"
        )
        logger.info("Sesión terminada: %s", reason)
        return handler_input.response_builder.response


# ══════════════════════════════════════════════════
# EXCEPTION HANDLER GLOBAL
# ══════════════════════════════════════════════════

class CatchAllExceptionHandler(AbstractExceptionHandler):
    """Captura cualquier excepción no controlada en los handlers."""

    def can_handle(
        self, handler_input: HandlerInput, exception: Exception
    ) -> bool:
        return True

    def handle(
        self, handler_input: HandlerInput, exception: Exception
    ) -> AlexaResponse:
        logger.error(
            "Error no controlado: %s", exception, exc_info=True
        )
        return (
            handler_input.response_builder
            .speak(
                "Lo siento, ha ocurrido un error inesperado. "
                "Por favor, intenta de nuevo."
            )
            .response
        )


# ══════════════════════════════════════════════════
# SKILL BUILDER – Ensamblaje del Skill de Alexa
# ══════════════════════════════════════════════════

sb = SkillBuilder()

# Registrar handlers (el orden importa: se evalúan secuencialmente)
sb.add_request_handler(LaunchRequestHandler())
sb.add_request_handler(CheckSpamIntentHandler())
sb.add_request_handler(HelpIntentHandler())
sb.add_request_handler(CancelStopIntentHandler())
sb.add_request_handler(FallbackIntentHandler())
sb.add_request_handler(SessionEndedRequestHandler())

# Registrar handler global de excepciones
sb.add_exception_handler(CatchAllExceptionHandler())

# Configurar verificación automática de Skill ID
if ALEXA_SKILL_ID:
    sb.skill_id = ALEXA_SKILL_ID

skill = sb.create()


# ══════════════════════════════════════════════════
# FASTAPI APPLICATION
# ══════════════════════════════════════════════════

app = FastAPI(
    title="Aegis Filter – Alexa Bridge",
    description=(
        "Microservicio intermediario entre Amazon Alexa "
        "y el motor antispam Aegis Filter (Laravel)."
    ),
    version="1.0.0",
    docs_url="/docs",
)


@app.post("/alexa", summary="Webhook de Amazon Alexa")
async def alexa_endpoint(request: Request) -> JSONResponse:
    """
    Endpoint principal que recibe los webhooks de Amazon Alexa.

    Flujo:
      1. Recibe el request HTTP crudo de Alexa
      2. Deserializa el RequestEnvelope (ASK SDK)
      3. Verifica firma y certificado de Amazon (si habilitado)
      4. Despacha al handler ASK correspondiente
      5. Retorna la respuesta serializada para Alexa
    """
    body = await request.body()
    body_str = body.decode("utf-8")
    headers = dict(request.headers)

    # ── Paso 1: Deserializar el request de Alexa ──────
    try:
        request_envelope: RequestEnvelope = skill.serializer.deserialize(
            payload=body_str, obj_type=RequestEnvelope
        )
    except Exception as exc:
        logger.error("Error deserializando request: %s", exc)
        raise HTTPException(status_code=400, detail="Request body inválido")

    # ── Paso 2: Verificación de seguridad (Crítico) ───
    # Se ejecuta en threadpool: descarga del certificado y verificación
    # RSA son operaciones bloqueantes que no deben correr en el event loop.
    if VERIFY_SIGNATURES:
        try:
            await run_in_threadpool(
                AlexaRequestVerifier.verify, headers, body_str, request_envelope
            )
        except ValueError as exc:
            logger.warning("Verificación fallida: %s", exc)
            raise HTTPException(
                status_code=403,
                detail=f"Verificación de seguridad fallida: {exc}",
            )
    else:
        logger.warning(
            "VERIFY_SIGNATURES=false: omitiendo verificación de firma"
        )

    # ── Paso 3: Invocar el Skill ──────────────────────
    # skill.invoke() es síncrono (incluye el POST a Aegis Core vía httpx);
    # se delega a threadpool para no bloquear el event loop de FastAPI.
    try:
        response_envelope = await run_in_threadpool(
            skill.invoke, request_envelope, None
        )
    except Exception as exc:
        logger.error("Error invocando skill: %s", exc, exc_info=True)
        raise HTTPException(
            status_code=500,
            detail="Error interno procesando la solicitud",
        )

    # ── Paso 4: Serializar y retornar ─────────────────
    response_dict = skill.serializer.serialize(response_envelope)
    return JSONResponse(content=response_dict)


@app.get("/health", summary="Health Check")
async def health_check() -> dict[str, Any]:
    """Health check para Docker y balanceadores de carga."""
    return {
        "status": "healthy",
        "service": "alexa-bridge",
        "version": "1.0.0",
        "aegis_core_url": AEGIS_CORE_URL,
        "verify_signatures": VERIFY_SIGNATURES,
    }

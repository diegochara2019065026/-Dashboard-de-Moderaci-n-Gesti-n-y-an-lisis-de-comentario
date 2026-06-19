"""
Tests para CheckSpamIntentHandler: extracción del slot, consulta al
Aegis Core y formateo de la respuesta de voz.
"""

import json
from types import SimpleNamespace
from unittest.mock import MagicMock

import httpx
import pytest
import respx

import main
from main import CheckSpamIntentHandler


def make_handler_input(mensaje_value):
    slots = {"Mensaje": SimpleNamespace(value=mensaje_value)} if mensaje_value is not None else {}
    intent = SimpleNamespace(slots=slots)
    request = SimpleNamespace(intent=intent)
    request_envelope = SimpleNamespace(request=request)

    response_builder = MagicMock()
    response_builder.speak.return_value = response_builder
    response_builder.ask.return_value = response_builder
    response_builder.response = "RESPONSE_SENTINEL"

    handler_input = SimpleNamespace(
        request_envelope=request_envelope,
        response_builder=response_builder,
    )
    return handler_input, response_builder


# ── handle(): formateo de respuesta ──────────────────────

def test_handle_speaks_spam_message_when_is_spam_true(monkeypatch):
    handler_input, response_builder = make_handler_input("oferta gratis ahora")
    monkeypatch.setattr(
        CheckSpamIntentHandler, "_consultar_aegis_core",
        staticmethod(lambda texto: {"is_spam": True}),
    )

    handler = CheckSpamIntentHandler()
    result = handler.handle(handler_input)

    response_builder.speak.assert_called_once_with(
        "El sistema Aegis ha bloqueado este mensaje por contener spam."
    )
    response_builder.ask.assert_not_called()
    assert result == "RESPONSE_SENTINEL"


def test_handle_speaks_clean_message_when_is_spam_false(monkeypatch):
    handler_input, response_builder = make_handler_input("hola, buen día")
    monkeypatch.setattr(
        CheckSpamIntentHandler, "_consultar_aegis_core",
        staticmethod(lambda texto: {"is_spam": False}),
    )

    handler = CheckSpamIntentHandler()
    handler.handle(handler_input)

    response_builder.speak.assert_called_once_with(
        "El mensaje está limpio y ha sido procesado correctamente."
    )


def test_handle_reprompts_when_slot_is_missing():
    handler_input, response_builder = make_handler_input(None)

    handler = CheckSpamIntentHandler()
    handler.handle(handler_input)

    response_builder.speak.assert_called_once()
    response_builder.ask.assert_called_once_with("¿Qué mensaje deseas analizar?")


def test_handle_reprompts_when_slot_value_is_empty():
    handler_input, response_builder = make_handler_input("")

    handler = CheckSpamIntentHandler()
    handler.handle(handler_input)

    response_builder.ask.assert_called_once()


def test_handle_handles_timeout_gracefully(monkeypatch):
    handler_input, response_builder = make_handler_input("texto cualquiera")
    monkeypatch.setattr(
        CheckSpamIntentHandler, "_consultar_aegis_core",
        staticmethod(lambda texto: (_ for _ in ()).throw(httpx.TimeoutException("timeout"))),
    )

    handler = CheckSpamIntentHandler()
    handler.handle(handler_input)

    speech = response_builder.speak.call_args[0][0]
    assert "no respondió a tiempo" in speech


def test_handle_handles_http_status_error_gracefully(monkeypatch):
    handler_input, response_builder = make_handler_input("texto cualquiera")
    req = httpx.Request("POST", "http://app:80/api/analyze")
    resp = httpx.Response(500, request=req)
    error = httpx.HTTPStatusError("server error", request=req, response=resp)

    monkeypatch.setattr(
        CheckSpamIntentHandler, "_consultar_aegis_core",
        staticmethod(lambda texto: (_ for _ in ()).throw(error)),
    )

    handler = CheckSpamIntentHandler()
    handler.handle(handler_input)

    speech = response_builder.speak.call_args[0][0]
    assert "error al comunicarse con el motor antispam" in speech


def test_handle_handles_connection_error_gracefully(monkeypatch):
    handler_input, response_builder = make_handler_input("texto cualquiera")
    monkeypatch.setattr(
        CheckSpamIntentHandler, "_consultar_aegis_core",
        staticmethod(lambda texto: (_ for _ in ()).throw(httpx.ConnectError("conn refused"))),
    )

    handler = CheckSpamIntentHandler()
    handler.handle(handler_input)

    speech = response_builder.speak.call_args[0][0]
    assert "No se pudo conectar con el motor antispam" in speech


# ── _consultar_aegis_core(): integración HTTP ────────────

@respx.mock
def test_consultar_aegis_core_posts_text_and_parses_response(monkeypatch):
    monkeypatch.setattr(main, "AEGIS_CORE_URL", "http://app:80/api/analyze")
    route = respx.post("http://app:80/api/analyze").mock(
        return_value=httpx.Response(200, json={"is_spam": True})
    )

    resultado = CheckSpamIntentHandler._consultar_aegis_core("mensaje de prueba")

    assert resultado == {"is_spam": True}
    assert route.called
    sent_request = route.calls.last.request
    assert sent_request.headers["content-type"] == "application/json"
    assert json.loads(sent_request.content) == {"text": "mensaje de prueba"}


@respx.mock
def test_consultar_aegis_core_raises_on_http_error(monkeypatch):
    monkeypatch.setattr(main, "AEGIS_CORE_URL", "http://app:80/api/analyze")
    respx.post("http://app:80/api/analyze").mock(
        return_value=httpx.Response(500)
    )

    with pytest.raises(httpx.HTTPStatusError):
        CheckSpamIntentHandler._consultar_aegis_core("mensaje de prueba")

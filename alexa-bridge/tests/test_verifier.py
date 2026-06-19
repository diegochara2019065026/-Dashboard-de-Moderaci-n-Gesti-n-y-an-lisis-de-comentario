"""
Tests para AlexaRequestVerifier.

Cubre las 4 validaciones exigidas por Amazon para un Custom HTTPS Endpoint:
timestamp, Skill ID, URL del certificado y firma RSA del body.
"""

import base64
import datetime

import pytest
from cryptography import x509
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import padding, rsa
from cryptography.x509.oid import NameOID

from main import AlexaRequestVerifier


def _build_cert(private_key, *, san_domain="echo-api.amazon.com",
                 not_before_delta=-1, not_after_delta=1):
    now = datetime.datetime.now(datetime.timezone.utc)
    name = x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, "echo-api.amazon.com")])
    builder = (
        x509.CertificateBuilder()
        .subject_name(name)
        .issuer_name(name)
        .public_key(private_key.public_key())
        .serial_number(x509.random_serial_number())
        .not_valid_before(now + datetime.timedelta(days=not_before_delta))
        .not_valid_after(now + datetime.timedelta(days=not_after_delta))
    )
    if san_domain:
        builder = builder.add_extension(
            x509.SubjectAlternativeName([x509.DNSName(san_domain)]), critical=False
        )
    return builder.sign(private_key, hashes.SHA256())


def _sign(private_key, body: str) -> str:
    signature = private_key.sign(
        body.encode("utf-8"), padding.PKCS1v15(), hashes.SHA256()
    )
    return base64.b64encode(signature).decode("utf-8")


class FakeRequest:
    def __init__(self, timestamp):
        self.timestamp = timestamp


class FakeApplication:
    def __init__(self, application_id):
        self.application_id = application_id


class FakeSession:
    def __init__(self, application_id):
        self.application = FakeApplication(application_id)


class FakeEnvelope:
    def __init__(self, timestamp=None, application_id=None):
        self.request = FakeRequest(timestamp)
        self.session = FakeSession(application_id) if application_id else None


@pytest.fixture
def keypair():
    return rsa.generate_private_key(public_exponent=65537, key_size=2048)


# ── Timestamp ────────────────────────────────────────────

def test_verify_timestamp_accepts_recent_request():
    envelope = FakeEnvelope(
        timestamp=datetime.datetime.now(datetime.timezone.utc).isoformat()
    )
    AlexaRequestVerifier._verify_timestamp(envelope)


def test_verify_timestamp_rejects_expired_request():
    old = datetime.datetime.now(datetime.timezone.utc) - datetime.timedelta(seconds=200)
    envelope = FakeEnvelope(timestamp=old.isoformat())
    with pytest.raises(ValueError, match="Timestamp expirado"):
        AlexaRequestVerifier._verify_timestamp(envelope)


def test_verify_timestamp_requires_timestamp():
    envelope = FakeEnvelope(timestamp=None)
    with pytest.raises(ValueError, match="sin timestamp"):
        AlexaRequestVerifier._verify_timestamp(envelope)


# ── Skill ID ─────────────────────────────────────────────

def test_verify_skill_id_accepts_matching_id(monkeypatch):
    monkeypatch.setattr("main.ALEXA_SKILL_ID", "amzn1.ask.skill.abc")
    envelope = FakeEnvelope(application_id="amzn1.ask.skill.abc")
    AlexaRequestVerifier._verify_skill_id(envelope)


def test_verify_skill_id_rejects_mismatched_id(monkeypatch):
    monkeypatch.setattr("main.ALEXA_SKILL_ID", "amzn1.ask.skill.abc")
    envelope = FakeEnvelope(application_id="amzn1.ask.skill.OTHER")
    with pytest.raises(ValueError, match="Skill ID inv"):
        AlexaRequestVerifier._verify_skill_id(envelope)


def test_verify_skill_id_skips_when_not_configured(monkeypatch):
    monkeypatch.setattr("main.ALEXA_SKILL_ID", "")
    envelope = FakeEnvelope(application_id=None)
    AlexaRequestVerifier._verify_skill_id(envelope)


# ── URL del certificado ──────────────────────────────────

@pytest.mark.parametrize("url", [
    "https://s3.amazonaws.com/echo.api/echo-api-cert.pem",
    "https://s3.amazonaws.com/echo.api/echo-api-cert-2.pem",
])
def test_validate_cert_url_accepts_valid_amazon_url(url):
    AlexaRequestVerifier._validate_cert_url(url)


@pytest.mark.parametrize("url", [
    "http://s3.amazonaws.com/echo.api/cert.pem",      # esquema incorrecto
    "https://evil.com/echo.api/cert.pem",              # host falso
    "https://s3.amazonaws.com/wrong/cert.pem",          # path incorrecto
    "https://s3.amazonaws.com:9999/echo.api/cert.pem",  # puerto incorrecto
])
def test_validate_cert_url_rejects_invalid_url(url):
    with pytest.raises(ValueError):
        AlexaRequestVerifier._validate_cert_url(url)


# ── Certificado ──────────────────────────────────────────

def test_validate_certificate_accepts_valid_cert(keypair):
    cert = _build_cert(keypair)
    AlexaRequestVerifier._validate_certificate(cert)


def test_validate_certificate_rejects_expired_cert(keypair):
    cert = _build_cert(keypair, not_before_delta=-10, not_after_delta=-1)
    with pytest.raises(ValueError, match="expirado"):
        AlexaRequestVerifier._validate_certificate(cert)


def test_validate_certificate_rejects_not_yet_valid_cert(keypair):
    cert = _build_cert(keypair, not_before_delta=1, not_after_delta=2)
    with pytest.raises(ValueError, match="vigente"):
        AlexaRequestVerifier._validate_certificate(cert)


def test_validate_certificate_rejects_missing_san(keypair):
    cert = _build_cert(keypair, san_domain=None)
    with pytest.raises(ValueError, match="SAN"):
        AlexaRequestVerifier._validate_certificate(cert)


def test_validate_certificate_rejects_wrong_san(keypair):
    cert = _build_cert(keypair, san_domain="not-amazon.example.com")
    with pytest.raises(ValueError, match="SAN"):
        AlexaRequestVerifier._validate_certificate(cert)


# ── Firma RSA ────────────────────────────────────────────

def test_verify_signature_accepts_valid_signature(keypair):
    cert = _build_cert(keypair)
    body = '{"foo": "bar"}'
    signature_b64 = _sign(keypair, body)
    AlexaRequestVerifier._verify_signature(cert, signature_b64, body, hashes.SHA256())


def test_verify_signature_rejects_tampered_body(keypair):
    cert = _build_cert(keypair)
    signature_b64 = _sign(keypair, '{"foo": "bar"}')
    with pytest.raises(ValueError, match="Firma RSA inv"):
        AlexaRequestVerifier._verify_signature(
            cert, signature_b64, '{"foo": "TAMPERED"}', hashes.SHA256()
        )


# ── Flujo completo (verify) ──────────────────────────────

def test_verify_full_flow_success(monkeypatch, keypair):
    monkeypatch.setattr("main.ALEXA_SKILL_ID", "")
    cert = _build_cert(keypair)
    monkeypatch.setattr(
        AlexaRequestVerifier,
        "_download_certificate",
        classmethod(lambda cls, url: cert),
    )

    body = '{"request": {"type": "LaunchRequest"}}'
    headers = {
        "signaturecertchainurl": "https://s3.amazonaws.com/echo.api/cert.pem",
        "signature-256": _sign(keypair, body),
    }
    envelope = FakeEnvelope(
        timestamp=datetime.datetime.now(datetime.timezone.utc).isoformat()
    )

    AlexaRequestVerifier.verify(headers, body, envelope)


def test_verify_full_flow_missing_headers(monkeypatch):
    monkeypatch.setattr("main.ALEXA_SKILL_ID", "")
    envelope = FakeEnvelope(
        timestamp=datetime.datetime.now(datetime.timezone.utc).isoformat()
    )
    with pytest.raises(ValueError, match="Headers de verificaci"):
        AlexaRequestVerifier.verify({}, "{}", envelope)

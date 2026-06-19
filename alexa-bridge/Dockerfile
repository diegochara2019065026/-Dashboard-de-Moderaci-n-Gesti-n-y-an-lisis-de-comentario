########################################################
# Aegis Filter – Alexa Bridge Dockerfile
# Imagen optimizada multi-stage: Python 3.11 + FastAPI
#
# Curso: SI784 – Calidad y Pruebas de Software
# Proyecto: Aegis Filter | Tech Hub Forum
########################################################

# ─── Etapa 1: Instalar dependencias (Builder) ────────
FROM python:3.11-slim AS builder

WORKDIR /build

# Crear entorno virtual aislado
RUN python -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Instalar dependencias Python
COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt

# ─── Etapa 2: Imagen de Producción ───────────────────
FROM python:3.11-slim AS production

LABEL maintainer="AegisFilter Team <aegis@techhub.pe>"
LABEL description="Alexa Bridge – Puente entre Amazon Alexa y Aegis Filter"
LABEL version="1.0.0"

# Crear usuario no-root por seguridad (buena práctica)
RUN groupadd --gid 1000 appuser \
    && useradd --uid 1000 --gid appuser --create-home appuser

WORKDIR /app

# Copiar el entorno virtual completo desde el builder
COPY --from=builder /opt/venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Copiar código fuente de la aplicación
COPY main.py .

# Cambiar a usuario no-root
USER appuser

# Variables de entorno por defecto
ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    AEGIS_CORE_URL="http://aegis-core:8000/api/analyze" \
    VERIFY_SIGNATURES="true" \
    ALEXA_SKILL_ID="" \
    AEGIS_REQUEST_TIMEOUT="10"

EXPOSE 8080

# Health check nativo de Docker
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD python -c \
    "import urllib.request; urllib.request.urlopen('http://localhost:8080/health')" \
    || exit 1

# Arrancar Uvicorn con 2 workers para producción
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8080", "--workers", "2"]

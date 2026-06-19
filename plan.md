# Plan de Desarrollo: Microservicio "Alexa Bridge" para Aegis Filter

## 1. Contexto del Proyecto
Actúa como un Ingeniero de Software Senior experto en arquitecturas de microservicios, Python y AWS Alexa Skills. 

Soy el creador de **Aegis Filter**, un motor antispam construido en Laravel y empaquetado en contenedores Docker, desplegado en un VPS. El objetivo de esta sesión es construir un contenedor intermediario (Bridge) **exclusivo para Amazon Alexa**. Este puente recibirá las peticiones de voz, consultará mi motor de Laravel y le devolverá la respuesta a Alexa.

## 2. Arquitectura de Red y Entorno
El sistema funciona con contenedores comunicándose a través de una red interna de Docker:
- **Aegis Core (Ya existe):** API en Laravel corriendo en un contenedor.
  - Endpoint interno simulado: `http://aegis-core:8000/api/analyze` (Petición POST).
  - Payload que espera recibir: `{"text": "texto transcrito por el usuario"}`
  - Respuesta que devuelve: `{"is_spam": true}` o `{"is_spam": false}`
- **Alexa Bridge (Lo que vamos a crear hoy):** Un microservicio nuevo que funcionará como un *Custom HTTPS Endpoint* para una Skill de Alexa.

## 3. Especificaciones Técnicas del Alexa Bridge
- **Lenguaje/Framework:** Python 3.11+ con **FastAPI**.
- **SDK:** Utilizar `ask-sdk-core` para manejar las peticiones y respuestas de la *Alexa Skills Kit (ASK)* de forma nativa.
- **Comunicación HTTP:** Utilizar `httpx` o `requests` para consultar al Aegis Core.
- **Infraestructura:** Todo el servicio debe estar *dockerizado*.

## 4. Requerimientos Funcionales y Flujo
1. **Recepción:** Crear un endpoint `POST /alexa` en FastAPI que reciba el webhook de Amazon.
2. **Validación de Seguridad (Crítico):** El código DEBE incluir la validación de firmas y certificados de Amazon (Skill ID verification / Signature validation) exigida para usar un Custom Endpoint en Alexa.
3. **Extracción:** Extraer el texto de un `IntentRequest` (asumamos que el intent se llama `CheckSpamIntent` y el slot con el texto se llama `Mensaje`).
4. **Consulta al Core:** Hacer un POST sincrónico a `http://aegis-core:8000/api/analyze` enviando el texto extraído.
5. **Formateo de Respuesta ASK:** - Si `is_spam` es `true`: El asistente de voz debe responder: *"El sistema Aegis ha bloqueado este mensaje por contener spam."*
   - Si `is_spam` es `false`: El asistente debe responder: *"El mensaje está limpio y ha sido procesado correctamente."*

## 5. Entregables
Por favor, genera los siguientes archivos paso a paso, aplicando buenas prácticas (Clean Code, manejo de excepciones):

1. **`main.py`**: El código completo de la aplicación FastAPI.
2. **`requirements.txt`**: Todas las dependencias necesarias.
3. **`Dockerfile`**: Un archivo Docker optimizado y ligero para producción (ej. usando `python:3.11-slim`).
4. **`docker-compose.yml` (Snippet)**: Un ejemplo claro de cómo declarar este nuevo servicio `alexa-bridge`, configurarle el puerto (ej. 8080) y cómo conectarlo a la misma red interna (`aegis-network`) donde ya vive `aegis-core`.
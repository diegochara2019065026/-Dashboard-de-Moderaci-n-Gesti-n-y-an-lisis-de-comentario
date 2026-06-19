# Despliegue del Alexa Bridge en el VPS

Pasos para exponer `alexa-bridge` con HTTPS público (requisito de Amazon
para Custom Endpoints) y registrar la Skill en la consola de Amazon.

## 1. Prerrequisitos

- DNS: `alexa.sytes.net` ya apunta a la IP pública del VPS (registro A).
- Firewall/NSG: puertos 80 y 443 abiertos (ya configurado en
  `infrastructure/terraform/main.tf`).
- Docker y Docker Compose instalados en el VPS.

## 2. Configurar el `.env` en el VPS

```bash
cp .env.example .env
```

Editar `.env` y ajustar, además de las credenciales de BD:

```bash
CADDY_ACME_EMAIL=tu-correo-real@dominio.com   # avisos de renovación TLS
APP_HTTP_PORT=8001                             # libera el 80 para Caddy
ALEXA_BRIDGE_BIND=127.0.0.1:8090               # sin exponerlo sin TLS
```

`APP_HTTP_PORT` es obligatorio cambiarlo en el VPS: Caddy pasa a ocupar
los puertos 80/443 del host, y `app` no puede seguir publicando el 80.

## 3. Levantar los servicios (con el proxy)

```bash
docker compose --profile proxy up -d --build
```

Verificar que Caddy obtuvo el certificado Let's Encrypt:

```bash
docker compose logs -f caddy
```

Cuando deje de reintentar y aparezca el certificado emitido, probar:

```bash
curl -I https://alexa.sytes.net/health   # ver alexa-bridge/main.py
```

(Si da 404, es normal: `/health` existe pero `/` no; lo importante es
que la conexión TLS se establezca sin errores de certificado.)

## 4. Configurar la Skill en la consola de Amazon Developer

1. Ir a https://developer.amazon.com/alexa/console/ask y crear una
   nueva Skill (modelo **Custom**, hosting **Provision your own**).
2. En **Build → Interaction Model → JSON Editor**, pegar el contenido
   de [`alexa-bridge/skill-package/interactionModels/custom/es-ES.json`](alexa-bridge/skill-package/interactionModels/custom/es-ES.json),
   luego **Save Model** y **Build Model**.
3. En **Build → Endpoint**:
   - Tipo: **HTTPS**.
   - Default Region: `https://alexa.sytes.net/alexa`.
   - SSL certificate type: *"My development endpoint has a certificate
     from a trusted certificate authority"* (Let's Encrypt aplica).
4. Copiar el **Skill ID** (visible en la URL o en la esquina superior
   izquierda) y pegarlo en `ALEXA_SKILL_ID` dentro del `.env` del VPS.
5. Reiniciar el bridge para que tome el Skill ID:

   ```bash
   docker compose restart alexa-bridge
   ```

## 5. Probar

En el simulador de la consola Alexa (o un dispositivo real con la
skill habilitada en modo desarrollo):

> "abre filtro antispam"
> "analiza el mensaje gana dinero fácil ahora"

Debe responder si el mensaje fue bloqueado o procesado correctamente.

## Notas

- Sin `ALEXA_SKILL_ID` configurado, el bridge omite esa validación
  (no recomendado dejarlo así en producción).
- Si Caddy no logra emitir el certificado, revisar que el puerto 80
  sea alcanzable desde internet (Let's Encrypt usa el reto HTTP-01).

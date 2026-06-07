---
marp: false
theme: default
class: lead
paginate: true
backgroundColor: #f8f9fa
color: #212529
---

# 🛡️ Aegis Filter
## Sistema Automático de Filtrado Antispam
**Curso:** Base de Datos II
**Integrantes:** Dayan Jahuira Pilco & Cristhian Mamani Cori
**Universidad Privada de Tacna**

---

<!-- class: default -->
# 🚨 El Problema: El Spam en la Web

- **Ataques de Bots:** Formularios web inundados con enlaces maliciosos y phishing.
- **Moderación Manual:** Requiere horas de revisión humana.
- **Basura en la BD:** Los registros no deseados consumen espacio y ensucian las analíticas.

**Objetivo:** Bloquear la amenaza *antes* de que llegue a la capa de persistencia (Base de Datos).

---

# 💡 Nuestra Solución: Aegis Filter

Un middleware backend diseñado para interceptar peticiones HTTP y evaluar su contenido en tiempo real.

**Reglas de Negocio Implementadas:**
1. **Límite de Enlaces:** Bloqueo automático si el comentario supera las 2 URLs permitidas (Regex).
2. **Lista Negra:** Filtro contra un diccionario de palabras clave fraudulentas.
3. **Respuesta Rápida:** Retorno de un código `HTTP 403 Forbidden` al detectar una amenaza.

---

# 🏗️ Arquitectura y Tecnologías

Nuestra arquitectura está diseñada para ser escalable, segura y fácil de desplegar:

- **Framework Core:** Laravel 11 (PHP 8.2) usando el patrón *Service* (`SpamFilterService`).
- **Persistencia:** MySQL 8 para almacenar comentarios limpios y métricas de bloqueo.
- **Contenedorización:** Docker & Docker Compose para un entorno inmutable.
- **Infraestructura (IaC):** Terraform para aprovisionar Máquinas Virtuales y NSG en Microsoft Azure.

---

# 🧪 Metodología Ágil (BDD)

Diseñamos el sistema utilizando *Behavior-Driven Development* (Gherkin):

> **Escenario:** Detección exitosa de Spam por exceso de URLs
> **DADO** que la regla de negocio limita a 2 los enlaces permitidos
> **CUANDO** un usuario envía un comentario con 3 direcciones web diferentes
> **ENTONCES** el motor Aegis Filter debe denegar el acceso a la base de datos
> **Y** registrar el evento como una amenaza bloqueada retornando un HTTP 403.

---

# 🚀 Demostración del Sistema

*(En este espacio, durante la exposición en vivo, mostraremos el funcionamiento real del filtro interceptando peticiones maliciosas en Postman / Navegador).*

---

# 📈 Conclusiones

1. **Eficiencia:** Aegis Filter reduce la necesidad de moderación manual casi en su totalidad.
2. **Desacoplamiento:** Al usar un `SpamFilterService`, la lógica no satura los controladores ni la base de datos.
3. **Seguridad en la Nube:** El despliegue automatizado con Docker y Terraform garantiza un entorno cerrado y profesional.

---

# 🛡️ ¡Gracias!
**¿Preguntas?**

Repositorio del proyecto: `github.com/UPT-FAING-EPIS/proyecto-si784-2026-i-u1-antispam`
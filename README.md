# 🛡️ Aegis Filter – Sistema Antispam Tech Hub Forum

[![CI/CD Pipeline](https://github.com/TU_ORG/proyecto-si784-2026-i-u1-antispam/actions/workflows/ci-cd.yml/badge.svg)](https://github.com/TU_ORG/proyecto-si784-2026-i-u1-antispam/actions)
[![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=aegisfilter-techhub&metric=alert_status)](https://sonarcloud.io)
[![PHP Version](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel)](https://laravel.com)

> **Curso:** SI784 – Calidad y Pruebas de Software  
> **Proyecto:** Aegis Filter integrado en simulador de foro Tech Hub

---

## 📐 Arquitectura

```
proyecto-si784-2026-i-u1-antispam/
├── .github/workflows/
│   └── ci-cd.yml              ← Pipeline GitHub Actions
├── infrastructure/terraform/
│   └── main.tf                ← Provisión VM Azure (Standard_B1s / Debian 12)
├── docker/
│   ├── entrypoint.sh          ← Script de inicio del contenedor
│   └── mysql/init.sql         ← Inicialización BD MySQL
├── Dockerfile                 ← Imagen multi-stage Laravel + PHP 8.2
├── docker-compose.yml         ← App + MySQL 8 + PHPMyAdmin
└── src/                       ← Aplicación Laravel
    ├── app/
    │   ├── Http/Controllers/
    │   │   └── CommentController.php   ← Controlador delgado (SRP)
    │   ├── Models/
    │   │   └── Comment.php
    │   └── Services/
    │       └── SpamFilterService.php   ← 🔥 Motor antispam
    ├── database/migrations/
    │   └── ..._create_comments_table.php
    ├── resources/views/
    │   ├── form.blade.php              ← Formulario público
    │   └── dashboard.blade.php         ← Panel de admin
    ├── routes/web.php
    ├── tests/Unit/
    │   └── SpamFilterTest.php          ← Tests BDD Gherkin
    ├── sonar-project.properties
    └── phpunit.xml
```

---

## 🚀 Inicio Rápido con Docker

```bash
# 1. Clonar el repositorio
git clone https://github.com/TU_ORG/proyecto-si784-2026-i-u1-antispam.git
cd proyecto-si784-2026-i-u1-antispam

# 2. Copiar variables de entorno
cp src/.env.example src/.env

# 3. Levantar los contenedores
docker compose up -d --build

# 4. Generar clave de aplicación
docker exec aegisfilter_app php artisan key:generate

# 5. Ejecutar migraciones
docker exec aegisfilter_app php artisan migrate

# Acceder a:
# Formulario:    http://localhost/comentarios
# Dashboard:     http://localhost/dashboard
# PHPMyAdmin:    http://localhost:8080
```

---

## 🧪 Ejecutar Pruebas

```bash
# Pruebas unitarias con PHPUnit
docker exec aegisfilter_app ./vendor/bin/phpunit --testdox

# Con cobertura de código
docker exec aegisfilter_app ./vendor/bin/phpunit --coverage-html=coverage-report
```

---

## 📦 FD04 – Ingeniería Inversa (Diagrama ER)

El proyecto usa **beyondcode/laravel-er-diagram-generator** para generar diagramas de clases y BD automáticamente.

```bash
# Instalar (ya incluido en composer.json como dev dependency)
composer require --dev beyondcode/laravel-er-diagram-generator

# Generar diagrama ER de todas las tablas
php artisan generate:erd

# Generar solo modelos específicos
php artisan generate:erd --models=Comment

# La imagen se guarda en: graph.png (raíz del proyecto)
```

---

## 🏗️ Terraform – Provisionar Azure

```bash
cd infrastructure/terraform

# Inicializar providers
terraform init

# Planificar la infraestructura
terraform plan -var="admin_password=TuPassword123!"

# Aplicar (requiere Azure CLI autenticado)
az login
terraform apply -var="admin_password=TuPassword123!" -auto-approve

# Ver IP pública de la VM
terraform output public_ip_address
```

---

## 🔍 Reglas Antispam

| Regla | Descripción | Acción |
|-------|-------------|--------|
| **Lista Negra** | Palabras/frases prohibidas (ej. "gratis", "bitcoin gratis") | `status = spam`, `spam_reason = blacklisted_word` |
| **Exceso URLs** | Más de 2 URLs en el mensaje | `status = spam`, `spam_reason = too_many_urls` |

---

## 🔐 Secrets GitHub Actions Requeridos

| Secret | Descripción |
|--------|-------------|
| `SONAR_TOKEN` | Token de autenticación SonarCloud |
| `SONAR_ORG` | Nombre de la organización en SonarCloud |
| `SONAR_PROJECT_KEY` | Clave del proyecto en SonarCloud |
| `SNYK_TOKEN` | Token de autenticación Snyk |
| `DOCKER_USERNAME` | Usuario Docker Hub |
| `DOCKER_PASSWORD` | Contraseña/token Docker Hub |


---


## 👥 Equipo

**Curso:** SI784 – Calidad y Pruebas de Software  
**Universidad:** UPT – FAING – EPIS  
**Semestre:** 2026-I

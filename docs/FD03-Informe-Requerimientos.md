<center>

![Logo UPT](../media/logo-upt.png)

**UNIVERSIDAD PRIVADA DE TACNA**

**FACULTAD DE INGENIERÍA**

**Escuela Profesional de Ingeniería de Sistemas**

**Proyecto Antispam**

Curso: *Base de Datos II*

Docente: *Patrick Cuadros Quiroga*

Integrantes:

- Jahuira Pilco, Dayan Elvis (2022075749)
- Mamani Cori, Cristhian Carlos (2023077282)

**Tacna – Perú**

**2026**

</center>

---


Sistema Antispam  
**Documento de Especificación de Requerimientos de Software**  
Versión 1.0  

---

## CONTROL DE VERSIONES

| Versión | Hecha por | Revisada por | Aprobada por | Fecha | Motivo |
|--------|----------|-------------|-------------|-------|--------|
| 1.0 | Cristhian M. | Dayan J. | Patrick C. | 14/04/2026 | Versión Original |

---

# ÍNDICE GENERAL

1. Generalidades de la Empresa
   1.1. Nombre de la Empresa
   1.2. Visión
   1.3. Misión
   1.4. Organigrama
2. Visionamiento de la Empresa
   2.1. Descripción del Problema
   2.2. Objetivos de Negocios
   2.3. Objetivos de Diseño
   2.4. Alcance del proyecto
   2.5. Viabilidad del Sistema
   2.6. Información obtenida del Levantamiento de Información
3. Análisis de Procesos
   3.1. Diagrama del Proceso Actual – Diagrama de actividades
   3.2. Diagrama del Proceso Propuesto – Diagrama de actividades Inicial
4. Especificación de Requerimientos de Software
   4.1. Cuadro de Requerimientos funcionales Inicial
   4.2. Cuadro de Requerimientos No funcionales
   4.3. Cuadro de Requerimientos funcionales Final
   4.4. Reglas de Negocio
5. Fase de Desarrollo
   5.1. Perfiles de Usuario
   5.2. Modelo Conceptual
      a) Diagrama de Paquetes
      b) Diagrama de Casos de Uso
      c) Escenarios de Caso de Uso (narrativa)
   5.3. Modelo Lógico
      a) Análisis de Objetos
      b) Diagrama de Actividades con objetos
      c) Diagrama de Secuencia
      d) Diagrama de Clases
6. CONCLUSIONES
7. RECOMENDACIONES
8. BIBLIOGRAFÍA
9. WEBGRAFÍA

---

# 1. Generalidades de la Empresa

## 1.1. Nombre de la Empresa
Aegis Filter

## 1.2. Visión
Ser una herramienta backend de referencia y código abierto, capaz de integrarse en cualquier plataforma web moderna para proporcionar entornos digitales seguros, limpios y libres de contenido malicioso.

## 1.3. Misión
Proveer un servicio de filtrado automatizado altamente eficiente y fácil de desplegar mediante contenedores, que reduzca la carga de trabajo manual de los moderadores web y proteja a los usuarios finales de enlaces fraudulentos.

## 1.4. Organigrama
* Líder de Proyecto / DevOps (Encargado de Terraform y Azure)
* Desarrollador Backend (Encargado de Laravel y Motor de Reglas)
* Analista QA y Base de Datos (Encargado de Pruebas, Docker y MySQL)

---

# 2. Visionamiento de la Empresa

## 2.1. Descripción del Problema
Las secciones de comentarios y foros en las aplicaciones web son vulnerables a ataques masivos de bots que publican enlaces de phishing, publicidad engañosa y contenido irrelevante (Spam). Las soluciones actuales suelen requerir horas de revisión manual, lo que retrasa la publicación de contenido legítimo y expone la base de datos a información indeseada.

## 2.2. Objetivos de Negocios
* Automatizar la moderación de contenido para reducir el tiempo de revisión manual en un 80%.
* Proporcionar una capa de seguridad preventiva que bloquee los comentarios antes de que sean persistidos en la base de datos de producción.

## 2.3. Objetivos de Diseño
* Construir una arquitectura basada en microservicios (Backend separado) para que sea invisible al usuario final.
* Garantizar un despliegue ágil, inmutable y escalable utilizando Docker y automatización con GitHub Actions.

## 2.4. Alcance del proyecto
El sistema interceptará las peticiones HTTP de creación de comentarios (desarrolladas en Laravel), evaluará el texto utilizando un motor de Expresiones Regulares y Listas Negras (SpamFilterService), y determinará si el registro se inserta en la base de datos MySQL 8 o es rechazado. Todo el entorno estará alojado en la nube de Microsoft Azure.

## 2.5. Viabilidad del Sistema
El sistema es factible técnica y económicamente, respaldado por el uso de herramientas Open Source (PHP, Laravel, Debian) e Infraestructura como Código (Terraform) que optimiza el consumo de recursos en la nube.

## 2.6. Información obtenida del Levantamiento de Información
Se determinó que la principal amenaza de spam consiste en textos repetitivos que contienen más de dos enlaces externos o que utilizan palabras clave fraudulentas recurrentes. Por lo tanto, el sistema debe enfocarse en filtrar estos dos vectores principales.

---

# 3. Análisis de Procesos

## 3.1. Diagrama del Proceso Actual – Diagrama de actividades
```mermaid
flowchart TD
    classDef userNode fill:#e1f5fe,stroke:#0288d1,stroke-width:2px,color:#000;
    classDef sysNode fill:#f3e5f5,stroke:#8e24aa,stroke-width:2px,color:#000;
    classDef modNode fill:#fff3e0,stroke:#f57c00,stroke-width:2px,color:#000;
    classDef decisionNode fill:#ffd54f,stroke:#f57f17,stroke-width:2px,color:#000;
    classDef startEndNode fill:#cfd8dc,stroke:#455a64,stroke-width:2px,color:#000;

    subgraph Usuario
        A([Inicio: Escribir comentario]):::userNode --> B(Enviar formulario):::userNode
    end

    subgraph Sistema Web
        B --> C[Guardar en Base de Datos]:::sysNode
        C --> D[Publicar comentario en la web]:::sysNode
    end

    subgraph Moderador
        D --> E(Ingresar al panel):::modNode
        E --> F(Leer comentarios publicados):::modNode
        F --> G{¿Contiene Spam?}:::decisionNode
        G -- Sí --> H[Eliminar comentario manualmente]:::modNode
        G -- No --> I[Dejar publicado]:::modNode
        H --> J([Fin del proceso]):::startEndNode
        I --> J
    end

    style Usuario fill:#fafafa,stroke:#bdbdbd,stroke-width:2px,stroke-dasharray: 5 5,color:#333
    style Sistema Web fill:#fafafa,stroke:#bdbdbd,stroke-width:2px,stroke-dasharray: 5 5,color:#333
    style Moderador fill:#fafafa,stroke:#bdbdbd,stroke-width:2px,stroke-dasharray: 5 5,color:#333
```

## 3.2. Diagrama del Proceso Propuesto – Diagrama de actividades Inicial
```mermaid
flowchart TD
    classDef userNode fill:#e1f5fe,stroke:#0288d1,stroke-width:2px,color:#000;
    classDef backendNode fill:#e8f5e9,stroke:#388e3c,stroke-width:2px,color:#000;
    classDef dbNode fill:#ede7f6,stroke:#5e35b1,stroke-width:2px,color:#000;
    classDef decisionNode fill:#ffd54f,stroke:#f57f17,stroke-width:2px,color:#000;
    classDef errorNode fill:#ffccbc,stroke:#d84315,stroke-width:2px,color:#000;
    classDef startEndNode fill:#cfd8dc,stroke:#455a64,stroke-width:2px,color:#000;

    subgraph Usuario
        A([Inicio: Escribir comentario]):::userNode --> B(Enviar petición POST):::userNode
    end

    subgraph AegisFilter [Aegis Filter - Backend]
        B --> C[Ejecutar SpamFilterService]:::backendNode
        C --> D[Analizar Regex y URLs]:::backendNode
        D --> E[Comparar con Lista Negra]:::backendNode
        E --> F{¿Detecta Spam?}:::decisionNode
        F -- Sí --> G[Rechazar petición HTTP 403]:::errorNode
        G --> H[Registrar métrica de bloqueo]:::backendNode
    end

    subgraph BD [Base de Datos MySQL]
        F -- No --> I[(Insertar registro en BD)]:::dbNode
        I --> J[Confirmar guardado exitoso]:::dbNode
    end

    H --> K([Fin del proceso]):::startEndNode
    J --> K

    style Usuario fill:#fafafa,stroke:#bdbdbd,stroke-width:2px,stroke-dasharray: 5 5,color:#333
    style AegisFilter fill:#fafafa,stroke:#bdbdbd,stroke-width:2px,stroke-dasharray: 5 5,color:#333
    style BD fill:#fafafa,stroke:#bdbdbd,stroke-width:2px,stroke-dasharray: 5 5,color:#333
```

---

# 4. Especificación de Requerimientos de Software

## 4.1. Cuadro de Requerimientos funcionales Inicial

| ID | Descripción | Prioridad |
|---|---|---|
| RF-01 | El sistema debe interceptar las peticiones de creación de comentarios en tiempo real. | Muy Alta |
| RF-02 | El sistema debe validar el texto mediante un motor de expresiones regulares para detectar patrones de URL. | Muy Alta |
| RF-03 | El sistema debe comparar el contenido contra una lista negra de términos prohibidos. | Muy Alta |
| RF-04 | El sistema debe registrar métricas sobre la cantidad de comentarios bloqueados y permitidos. | Muy Alta |

## 4.2. Cuadro de Requerimientos No funcionales

| ID | Descripción | Prioridad |
|---|---|---|
| RNF-01 | El sistema debe estar desplegado en Microsoft Azure para garantizar un acceso constante vía web | Muy Alta |
| RNF-02 | La infraestructura debe ser gestionada mediante Terraform (IaC) para asegurar configuraciones de red cerradas (puerto 3306 bloqueado al exterior) | Alta |
| RNF-03 | La aplicación debe estar contenedorizada en Docker para facilitar actualizaciones inmutables | Alta |

## 4.3. Cuadro de Requerimientos funcionales Final

| ID | Descripción | Prioridad |
|---|---|---|
| RF-01 | El sistema debe interceptar las peticiones de creación de comentarios en tiempo real. | Muy Alta |
| RF-02 | El sistema debe validar el texto mediante un motor de expresiones regulares para detectar patrones de URL. | Muy Alta |
| RF-03 | El sistema debe comparar el contenido contra una lista negra de términos prohibidos. | Muy Alta |
| RF-04 | El sistema debe registrar métricas sobre la cantidad de comentarios bloqueados y permitidos. | Muy Alta |

## 4.4. Reglas de Negocio

| ID | Descripción | Prioridad |
|---|---|---|
| RN-01 | Un comentario será bloqueado automáticamente si contiene 3 o más enlaces (http o https). | Muy Alta |
| RN-02 | Si se detecta Spam, el sistema debe retornar un código de estado HTTP 403 (Prohibido) o 422 (Entidad no procesable) para evitar que el registro llegue a la base de datos. | Muy Alta |

---

# 5. Fase de Desarrollo

## 5.1. Perfiles de Usuario
* **Administrador / DevOps:** Encargado del despliegue en Azure y la configuración de reglas en el archivo `main.tf`.
* **Moderador:** Responsable de verificar las estadísticas de filtrado en el panel administrativo.

## 5.2. Modelo Conceptual

### a) Diagrama de Paquetes
```mermaid
flowchart TD
    classDef layer fill:#e3f2fd,stroke:#1e88e5,stroke-width:2px,color:#000
    classDef db fill:#e8f5e9,stroke:#43a047,stroke-width:2px,color:#000

    subgraph Presentacion ["Capa de Presentación (Frontend)"]
        UI[Vistas Blade / Interfaz de Usuario]:::layer
    end

    subgraph Aplicacion ["Capa de Aplicación (Backend Laravel)"]
        C[Controllers]:::layer
        S[Aegis SpamFilterService]:::layer
        M[Models ORM]:::layer
    end

    subgraph Persistencia ["Capa de Persistencia"]
        BD[(Base de Datos MySQL)]:::db
    end

    UI -->|Petición HTTP POST| C
    C -->|Inyección de Dependencias| S
    C -->|Mapeo de Datos| M
    M -->|Consultas SQL| BD
```

### b) Diagrama de Casos de Uso
```mermaid
flowchart LR
    User((Cliente))
    Admin((Administrador))
    Sys((Sistema Aegis))

    UC1([Enviar Comentario])
    UC2([Filtrar Contenido Spam])
    UC3([Gestionar Listas Negras])
    UC4([Visualizar Métricas de Spam])

    User --- UC1
    UC1 ..->|<< include >>| UC2
    Sys --- UC2
    Admin --- UC3
    Admin --- UC4
    
    classDef usecase fill:#fff9c4,stroke:#fbc02d,stroke-width:2px,color:#000
    class UC1,UC2,UC3,UC4 usecase
```

### c) Escenarios de Caso de Uso (narrativa)
* **Como** desarrollador backend...
* **Quiero** crear un servicio de validación por expresiones regulares...
* **Para** identificar comentarios que contengan múltiples enlaces maliciosos de forma automática.

> **Escenario Gherkin: Detección exitosa de Spam por URLs**
> **DADO** que la regla de negocio limita a 2 los enlaces permitidos
> **CUANDO** un usuario envía un comentario con 3 direcciones web diferentes
> **ENTONCES** el sistema debe denegar el acceso a la base de datos
> **Y** registrar el evento como una amenaza bloqueada.

## 5.3. Modelo Lógico

### a) Análisis de Objetos
```mermaid
classDiagram
    class cliente_1 {
        ip: "192.168.1.50"
        navegador: "Chrome"
    }
    
    class comentario_recibido {
        author: "SpamBot99"
        content: "Gana dinero facil entra a [http://spam.com](http://spam.com)"
    }
    
    class motor_aegis {
        max_links_allowed: 2
        estado: "Activo"
    }
    
    class respuesta_servidor {
        status_code: 403
        message: "Acceso denegado: Spam detectado"
    }

    cliente_1 --> comentario_recibido : Envía payload
    comentario_recibido --> motor_aegis : Es evaluado por
    motor_aegis --> respuesta_servidor : Retorna
```

### b) Diagrama de Actividades con objetos
```mermaid
flowchart TD
    classDef process fill:#e1f5fe,stroke:#0288d1,stroke-width:2px,color:#000
    classDef objState fill:#fff3e0,stroke:#e65100,stroke-width:2px,color:#000,shape:rect
    classDef decision fill:#ffd54f,stroke:#f57f17,stroke-width:2px,color:#000

    A[Usuario envía formulario]:::process --> O1
    O1[[Objeto Comentario: Estado NO VALIDADO]]:::objState --> B[SpamFilterService recibe texto]:::process
    B --> C{¿Supera límite de enlaces\no palabras prohibidas?}:::decision
    
    C -- Sí --> O2[[Objeto Comentario: Estado SPAM / RECHAZADO]]:::objState
    O2 --> D[Controlador aborta y retorna HTTP 403]:::process
    
    C -- No --> O3[[Objeto Comentario: Estado LIMPIO / VALIDADO]]:::objState
    O3 --> E[Modelo guarda registro]:::process
    E --> F[(BD MySQL)]
```

### c) Diagrama de Secuencia
```mermaid
sequenceDiagram
    autonumber
    actor Usuario
    participant Controller as CommentController
    participant Service as SpamFilterService
    participant Model as Comment (Model)
    participant BD as MySQL

    Usuario->>Controller: POST /comments (data)
    activate Controller
    
    Controller->>Service: validateSpam(request->content)
    activate Service
    
    alt Contiene Spam (Falla Regex o Lista Negra)
        Service-->>Controller: return true (Es Spam)
        Controller-->>Usuario: HTTP 403 Forbidden (Bloqueado)
    else Contenido Limpio
        Service-->>Controller: return false (No es Spam)
        deactivate Service
        
        Controller->>Model: Comment::create(data)
        activate Model
        Model->>BD: INSERT INTO comments
        BD-->>Model: OK
        Model-->>Controller: Instancia guardada exitosamente
        deactivate Model
        
        Controller-->>Usuario: HTTP 201 Created (Publicado)
    end
    deactivate Controller
```

### d) Diagrama de Clases

```mermaid
classDiagram
    class CommentController {
        - spamFilter: SpamFilterService
        + store(request: Request): Response
        + index(): View
    }

    class SpamFilterService {
        - blacklistedWords: array
        - maxLinks: int
        + isSpam(content: string): bool
        - checkBlacklist(content: string): bool
        - countLinks(content: string): int
    }

    class Comment {
        - id: bigint
        - author_name: string
        - content: text
        - is_spam: boolean
        + save(): bool
    }

    CommentController ..> SpamFilterService : Usa (Inyección de Dependencia)
    CommentController ..> Comment : Crea y Gestiona
```

---

# 6. CONCLUSIONES
El diseño de Aegis Filter bajo la especificación FD03 asegura un filtrado robusto y preventivo. El uso de criterios de aceptación Gherkin facilitará la creación de pruebas unitarias automáticas en el pipeline de GitHub Actions, garantizando la calidad antes del despliegue.

# 7. RECOMENDACIONES
Se recomienda integrar las métricas de filtrado con un servicio de alertas (como Telegram o Email) para notificar al administrador en caso de ataques masivos de bots en tiempo real.

# 8. BIBLIOGRAFÍA
* Microsoft Azure. (2026). Documentación oficial de máquinas virtuales e infraestructura de Azure.
* HashiCorp. (2026). Terraform Registry: Azure Provider Documentation.
* Laravel. (2026). Laravel 11.x Documentation: Testing and Services.
* Docker Inc. (2026). Docker Compose reference.

# 9. WEBGRAFÍA
* Guías de GitHub Actions y Wikis: https://docs.github.com/es
* Documentación de calidad de código en SonarCloud: https://docs.sonarsource.com/sonarcloud/
* Auditoría de seguridad con Snyk: https://docs.snyk.io/

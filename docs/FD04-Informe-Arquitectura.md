<center>

![Logo UPT](../media/logo-upt.png)

**UNIVERSIDAD PRIVADA DE TACNA**

**FACULTAD DE INGENIERÍA**

**Escuela Profesional de Ingeniería de Sistemas**

**Proyecto Antispam**

Curso: Base de Datos II

Docente: Patrick Cuadros Quiroga

Integrantes:
* Jahuira Pilco, Dayan Elvis (2022075749)
* Mamani Cori, Cristhian Carlos (2023077282)

Tacna – Perú


2026

</center>

---

Sistema Antispam  
**Documento de Arquitectura de Software**  
Versión 1.0

---

## CONTROL DE VERSIONES

| Versión | Hecha por | Revisada por | Aprobada por | Fecha | Motivo |
|--------|----------|-------------|-------------|-------|--------|
| 1.0 | Cristhian M. | Dayan J. | Patrick C. | 14/04/2026 | Versión Original |

---

## INDICE GENERAL
1. INTRODUCCIÓN
    1.1. Propósito (Diagrama 4+1)
    1.2. Alcance
    1.3. Definición, siglas y abreviaturas
    1.4. Organización del documento
2. OBJETIVOS Y RESTRICCIONES ARQUITECTONICAS
    2.1.1. Requerimientos Funcionales
    2.1.2. Requerimientos No Funcionales – Atributos de Calidad
3. REPRESENTACIÓN DE LA ARQUITECTURA DEL SISTEMA
    3.1. Vista de Caso de uso
    3.2. Vista Lógica
    3.3. Vista de Implementación (vista de desarrollo)
    3.4. Vista de procesos
    3.5. Vista de Despliegue (vista física)
4. ATRIBUTOS DE CALIDAD DEL SOFTWARE

---

## 1. INTRODUCCIÓN

**1.1. Propósito (Diagrama 4+1)**
El presente documento tiene como propósito definir la arquitectura de software del sistema "Aegis Filter" utilizando el modelo de vistas 4+1 (Lógica, Implementación, Procesos, Despliegue y Casos de Uso). Presenta una visión global del diseño, justificando cómo las decisiones arquitectónicas satisfacen los requerimientos funcionales de detección de spam y las prioridades de alto rendimiento, modularidad y fácil despliegue en la nube.

**1.2. Alcance**
Este documento se centra en el desarrollo de la arquitectura del backend en Laravel 11 y su despliegue contenedorizado. Incluye la vista lógica (MVC y Servicios), la vista de despliegue (Terraform en Azure) y la estructura de datos (MySQL). Se omiten procesos complejos de Frontend ya que el sistema opera como un middleware invisible al usuario.

**1.3. Definición, siglas y abreviaturas**
* **API:** Interfaz de Programación de Aplicaciones.
* **BDD:** Desarrollo Guiado por Comportamiento (Behavior-Driven Development).
* **Docker:** Plataforma de contenedorización de software.
* **IaC:** Infraestructura como Código (uso de Terraform).
* **MVC:** Patrón de arquitectura Modelo-Vista-Controlador.
* **NSG:** Grupo de Seguridad de Red (Azure).

**1.4. Organización del documento**
El documento está organizado en cuatro secciones principales: Objetivos y restricciones (define qué se debe cumplir), Representación de la arquitectura (donde se exponen los diagramas 4+1), y finalmente los atributos de calidad del software.

---

## 2. OBJETIVOS Y RESTRICCIONES ARQUITECTONICAS

### 2.1. Priorización de requerimientos

**Requerimientos Funcionales**

| ID | Descripcion | Prioridad |
|---|---|---|
| RF-01 | Interceptar las peticiones POST de comentarios antes de interactuar con la base de datos. | Alta |
| RF-02 | Validar el texto del comentario contra expresiones regulares para detectar múltiples URLs. | Alta |
| RF-03 | Evaluar el texto contra una lista negra de palabras ofensivas almacenadas en el sistema. | Alta |
| RF-04 | Bloquear peticiones sospechosas retornando un estado HTTP 403. | Alta |
| RF-05 | Almacenar métricas de los comentarios permitidos y rechazados. | Media |

**Requerimientos No Funcionales – Atributos de Calidad**

| ID | Descripcion | Prioridad |
|---|---|---|
| RNF-01 | Disponibilidad: El sistema debe operar en la nube de Azure mediante contenedores para asegurar un 99.9% de uptime. | Alta |
| RNF-02 | Rendimiento: El análisis heurístico de cada comentario no debe superar los 500ms para evitar cuellos de botella en la web. | Alta |
| RNF-03 | Seguridad: La base de datos debe estar aislada en una red virtual, impidiendo el acceso desde el exterior (puerto 3306 cerrado). | Alta |
| RNF-04 | Mantenibilidad: El código debe adherirse a los estándares PSR-12 y utilizar inyección de dependencias para facilitar futuras actualizaciones. | Media |

### 2.2. Restricciones
* Tecnológicas: El desarrollo debe utilizar estrictamente PHP 8.2+ y Laravel 11.
* Infraestructura: La máquina virtual en producción está restringida al plan Standard_B1ms de Azure por motivos de presupuesto académico, limitando los recursos a 1 vCPU y 2 GB de RAM.
* Despliegue: Prohibido el acceso manual FTP al servidor; todo cambio en producción debe realizarse a través del pipeline de GitHub Actions.

---

## 3. REPRESENTACIÓN DE LA ARQUITECTURA DEL SISTEMA

### 3.1. Vista de Caso de uso

**3.1.1. Diagramas de Casos de uso**
```mermaid
flowchart LR
    %% Actores
    User((Cliente))
    Admin((Administrador))
    Sys((Sistema Aegis))

    %% Casos de Uso
    UC1([Enviar Comentario])
    UC2([Filtrar Contenido Spam])
    UC3([Gestionar Listas Negras])
    UC4([Visualizar Métricas de Spam])

    %% Relaciones
    User --- UC1
    UC1 ..->|<< include >>| UC2
    Sys --- UC2
    Admin --- UC3
    Admin --- UC4
    
    classDef usecase fill:#fff9c4,stroke:#fbc02d,stroke-width:2px,color:#000
    class UC1,UC2,UC3,UC4 usecase
```

### 3.2. Vista Lógica

**3.2.1. Diagrama de Subsistemas (paquetes)**

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

    subgraph Persistencia ["Capa de Persistencia (Docker)"]
        BD[(Base de Datos MySQL)]:::db
    end

    UI -->|Petición HTTP POST| C
    C -->|Inyección de Dependencias| S
    C -->|Mapeo de Datos| M
    M -->|Consultas SQL| BD
```

**3.2.2. Diagrama de Secuencia (vista de diseño)**
```mermaid
sequenceDiagram
    autonumber
    actor Usuario
    participant Controller as CommentController
    participant Service as SpamFilterService
    participant Model as Comment Model
    participant BD as MySQL

    Usuario->>Controller: POST /comments (data)
    activate Controller
    
    Controller->>Service: isSpam(request->content)
    activate Service
    
    alt Contiene Spam (Falla Regex o Lista Negra)
        Service-->>Controller: return true
        Controller-->>Usuario: HTTP 403 Forbidden (Bloqueado)
    else Contenido Limpio
        Service-->>Controller: return false
        deactivate Service
        
        Controller->>Model: create(data)
        activate Model
        Model->>BD: INSERT INTO comments
        BD-->>Model: OK
        Model-->>Controller: Instancia guardada exitosamente
        deactivate Model
        
        Controller-->>Usuario: HTTP 201 Created (Publicado)
    end
    deactivate Controller
```

**3.2.3. Diagrama de Colaboración (vista de diseño)**

```mermaid
flowchart TD
    U((Usuario))
    C[ : CommentController ]
    S[ : SpamFilterService ]
    M[ : Comment Model ]
    DB[( : MySQL )]

    U -- "1: POST /comments" --> C
    C -- "2: isSpam(content)" --> S
    S -. "3: return boolean" .-> C
    C -- "4: [Si es false] create(data)" --> M
    M -- "5: INSERT" --> DB
    DB -. "6: OK" .-> M
    M -. "7: retorna instancia" .-> C
    C -. "8: Respuesta HTTP 201 o 403" .-> U

    classDef obj fill:#e1f5fe,stroke:#0288d1,stroke-width:2px,color:#000
    class C,S,M obj
```

**3.2.4. Diagrama de Objetos**
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

**3.2.5. Diagrama de Clases**
```mermaid
classDiagram
    class CommentController {
        - spamFilter: SpamFilterService
        + index() View
        + store(request: Request) RedirectResponse
    }

    class SpamFilterService {
        - maxLinksAllowed: int
        - blacklist: array
        + __construct()
        + isSpam(content: string) bool
        - containsBlacklistedWords(content: string) bool
        - exceedsUrlLimit(content: string) bool
    }

    class Comment {
        # fillable: array
        + create(attributes: array) Comment
    }

    CommentController ..> SpamFilterService : Usa (Inyección)
    CommentController ..> Comment : Crea
```

**3.2.6. Diagrama de Base de datos (relacional o no relacional)**
```mermaid
erDiagram
    COMMENTS {
        bigint id PK
        varchar author_name
        varchar author_email
        text content
        boolean is_spam
        decimal spam_score
        timestamp created_at
        timestamp updated_at
    }
    
    SPAM_RULES {
        bigint id PK
        enum rule_type "keyword, regex, url_pattern"
        varchar pattern
        boolean is_active
        timestamp created_at
    }

    COMMENTS ||--o{ SPAM_RULES : "es evaluado contra"
```

### 3.3. Vista de Implementación (vista de desarrollo)

**3.3.1. Diagrama de arquitectura software (paquetes)**
```mermaid
flowchart TD
    classDef layer fill:#f5f5f5,stroke:#333,stroke-width:2px,color:#000
    classDef module fill:#e1f5fe,stroke:#0288d1,stroke-width:2px,color:#000
    classDef external fill:#fff3e0,stroke:#f57c00,stroke-width:2px,color:#000

    subgraph UI ["Paquete: UI (Presentación)"]
        V[Views / Blade Templates]:::module
    end

    subgraph App ["Paquete: App (Dominio y Aplicación)"]
        C[Http\Controllers]:::module
        S[Services\SpamFilter]:::module
        M[Models]:::module
    end

    subgraph DB ["Paquete: Infraestructura"]
        O[Eloquent ORM]:::module
        D[(MySQL Database)]:::layer
    end

    Cliente((Navegador)):::external -->|Rutas web.php| C
    C -->|Retorna| V
    C -->|Inyecta| S
    C -->|Usa| M
    M -->|Hereda| O
    O -->|Query SQL| D
```

**3.3.2. Diagrama de arquitectura del sistema (Diagrama de componentes)**
```mermaid
flowchart TD
    classDef external fill:#fff3e0,stroke:#f57c00,stroke-width:2px,color:#000
    classDef comp fill:#e1f5fe,stroke:#0288d1,stroke-width:2px,color:#000
    classDef db fill:#e8f5e9,stroke:#43a047,stroke-width:2px,color:#000
    classDef note fill:#fff9c4,stroke:#fbc02d,stroke-dasharray: 5 5,color:#000

    Cliente([Cliente Externo]):::external

    subgraph Docker ["Docker Host (Debian 12 Linux)"]
        direction TB
        Web[Nginx Web Server<br>Puerto: 80]:::comp
        App[Laravel Application<br>PHP 8.2 FPM]:::comp
        DB[(MySQL 8 Database<br>Puerto: 3306)]:::db
    end

    Cliente -->|HTTP / HTTPS| Web
    Web -->|Peticiones FastCGI 9000| App
    App -->|TCP/IP PDO 3306| DB

    %% Nota explicativa
    Nota[Nota: El puerto 3306 está expuesto<br>solo en la red interna de Docker,<br>no al exterior.]:::note
    DB -.-> Nota
```

### 3.4. Vista de procesos

**3.4.1. Diagrama de Procesos del sistema (diagrama de actividad)**
```mermaid
flowchart TD
    classDef startEnd fill:#cfd8dc,stroke:#455a64,stroke-width:2px,color:#000
    classDef process fill:#e1f5fe,stroke:#0288d1,stroke-width:2px,color:#000
    classDef decision fill:#ffd54f,stroke:#f57f17,stroke-width:2px,color:#000
    classDef error fill:#ffccbc,stroke:#d84315,stroke-width:2px,color:#000
    classDef success fill:#c8e6c9,stroke:#388e3c,stroke-width:2px,color:#000

    A([Inicio: Recibir Petición POST]):::startEnd --> B[Extraer contenido del comentario]:::process
    B --> C[SpamFilterService: Validar Lista Negra]:::process
    C --> D{¿Contiene palabras\nprohibidas?}:::decision
    
    D -- Sí --> E[Retornar HTTP 403 Forbidden]:::error
    D -- No --> F[SpamFilterService: Contar URLs con Regex]:::process
    
    F --> G{¿URLs > Límite?}:::decision
    G -- Sí --> E
    G -- No --> H[Model: Guardar en Base de Datos]:::process
    
    H --> I[Retornar HTTP 201 Created]:::success
    E --> J([Fin del proceso]):::startEnd
    I --> J
```

### 3.5. Vista de Despliegue (vista física)

**3.5.1. Diagrama de despliegue**
```mermaid
flowchart TD
    classDef cloud fill:#e3f2fd,stroke:#1e88e5,stroke-width:2px,color:#000,stroke-dasharray: 5 5
    classDef node fill:#fff,stroke:#333,stroke-width:2px,color:#000
    classDef db fill:#e8f5e9,stroke:#43a047,stroke-width:2px,color:#000

    Internet((Internet Pública))

    subgraph Azure ["Microsoft Azure Cloud (Aprovisionado con Terraform)"]
        NSG{Grupo de Seguridad de Red\nNetwork Security Group\nInbound: 80, 443, 22}:::node
        
        subgraph VM ["Máquina Virtual (Standard_B1ms)"]
            subgraph Docker ["Docker Compose Network"]
                App[Contenedor: app_aegis\nLaravel 11]:::node
                DB[(Contenedor: db_aegis\nMySQL 8)]:::db
            end
        end
    end

    Internet -->|Tráfico HTTP/SSH| NSG
    NSG -->|Tráfico Filtrado| VM
    App -->|Conexión interna aislada| DB
```

---

## 4. ATRIBUTOS DE CALIDAD DEL SOFTWARE

**Escenario de Funcionalidad**
El sistema demuestra su funcionalidad al interceptar exitosamente el 100% de las peticiones que cumplan con las reglas de negocio (ej. superar las 2 URLs) y bloqueándolas antes de alcanzar la base de datos.

**Escenario de Usabilidad**
Al ser un servicio de backend, la usabilidad se enfoca en el desarrollador y el administrador. Se garantiza mediante un código limpio, variables de entorno claras en el .env y un despliegue sin fricciones con comandos automatizados (Docker/Terraform).

**Escenario de confiabilidad**
El sistema previene inyecciones y ataques mediante la validación previa con expresiones regulares. La capa de datos en Azure está resguardada por un NSG (Network Security Group) que bloquea todo el tráfico no autorizado al puerto 3306.

**Escenario de rendimiento**
El motor de validación SpamFilterService es altamente eficiente, capaz de evaluar la heurística del texto y las listas negras devolviendo una respuesta en tiempos inferiores a 500 ms.

**Escenario de mantenibilidad**
La arquitectura separada en capas (Controlador -> Servicio -> Modelo) facilita la extensibilidad. Nuevas reglas anti-spam pueden añadirse al servicio sin necesidad de reescribir la lógica de la API ni la estructura de la base de datos.

<center>


[comment]: <img src="./media/media/image1.png" style="width:1.088in;height:1.46256in" alt="escudo.png" />


![./media/media/image1.png](../media/logo-upt.png)


**UNIVERSIDAD PRIVADA DE TACNA**

**FACULTAD DE INGENIERÍA**

**Escuela Profesional de Ingeniería de Sistemas**

**Proyecto Aegis Filter**

Curso: *Calidad y Pruebas de Software*

Docente: *Patrick Cuadros Quiroga*

Integrantes:

- Jahuira Pilco, Dayan Elvis (2022075749)
- Mamani Cori, Cristhian Carlos (2023077282)

**Tacna – Perú**

**2026**

</center>

---

Sistema Aegis Filter  
**Informe de Factibilidad**  
Versión 1.0  

---

## CONTROL DE VERSIONES

| Versión | Hecha por | Revisada por | Aprobada por | Fecha | Motivo |
|--------|----------|-------------|-------------|-------|--------|
| 1.0 | Cristhian M. | Dayan J. | Patrick C. | 29/04/2026 | Versión Original |

---

# ÍNDICE GENERAL

1. Descripción del Proyecto  
2. Riesgos  
3. Análisis de la Situación actual  
4. Estudio de Factibilidad  
5. Análisis Financiero  
6. Conclusiones  

---

# 1. Descripción del Proyecto

## 1.1 Nombre del proyecto
Aegis Filter - Sistema Anti-Spam

## 1.2 Duración del proyecto
1 mes (4 semanas)

## 1.3 Descripción
El proyecto consiste en el desarrollo de una aplicación web basada en Laravel, dockerizada y desplegada en la infraestructura de Microsoft Azure. Su propósito principal es gestionar comentarios de usuarios y aplicar un filtro automatizado (Aegis Filter) capaz de detectar contenido malicioso, palabras prohibidas y exceso de enlaces (Spam), mejorando así la calidad del contenido y reduciendo la carga de moderación manual. El sistema interactúa con una base de datos MySQL 8 y es evaluado bajo prácticas de Integración y Despliegue Continuo (CI/CD).

## 1.4 Objetivos

### 1.4.1 Objetivo general
Desarrollar e implementar un sistema web seguro y escalable para la gestión y filtrado automático de comentarios spam, utilizando arquitectura de contenedores e Infraestructura como Código (IaC).

### 1.4.2 Objetivos específicos
- Implementar un servicio de análisis de contenido en el backend para identificar spam mediante listas negras y expresiones regulares.
- Aprovisionar la infraestructura necesaria en la nube (Azure) utilizando Terraform para asegurar reproducibilidad y control.
- Automatizar las pruebas de calidad de software y seguridad del código fuente utilizando GitHub Actions, SonarCloud y Snyk.

---

# 2. Riesgos

- Inestabilidad en el despliegue debido a configuraciones incorrectas en los contenedores Docker.
- Falta de memoria RAM en la máquina virtual, causando la caída de los servicios.
- Falsos positivos en el filtro anti-spam que restrinjan la participación legítima de los usuarios.
- Consumo excesivo de créditos de Azure si no se gestionan adecuadamente los estados de la máquina virtual (encendido/apagado).

---

# 3. Análisis de la Situación Actual

## 3.1 Planteamiento del problema
Actualmente, muchas plataformas web carecen de mecanismos eficientes para filtrar comentarios no deseados, lo que requiere horas de revisión manual. Los enfoques tradicionales suelen ser vulnerables a variaciones simples en las URLs o palabras clave. Existe la necesidad de un sistema robusto, que además esté preparado para entornos de producción modernos (nube).

## 3.2 Consideraciones de hardware y software

| Tipo | Recurso | Descripción |
|------|--------|------------|
| Hardware | Computadora personal | Intel i5 / Ryzen 5, RAM: 8/16 GB. Equipo para desarrollar y probar el sistema. |
| Software | Windows 10/11 | Sistema Operativo base para ejecutar herramientas de desarrollo (Docker, VS Code). |

---

# 4. Estudio de Factibilidad

## 4.1 Factibilidad Técnica

| Cantidad | Recurso | Descripción |
|---------|--------|------------|
| 1 | Laptop | Laptop DELL, Procesador Ryzen 5, RAM: 16 GB, SSD: 1 TB |
| 1 | Laptop | Laptop ASUS, Procesador Ryzen, RAM: 16 GB, SSD: 1 TB |

---

## 4.2 Factibilidad Económica

**Análisis Económico de Infraestructura mediante Terraform:** 
El mayor impacto económico del proyecto recae en el alojamiento en la nube. Para estimar y controlar este gasto de manera precisa, se ha utilizado Terraform (Infraestructura como Código). Al analizar nuestro archivo de configuración (`main.tf`), se aprovisionan los siguientes recursos en Azure:
- **azurerm_linux_virtual_machine:** Escalado a tamaño `Standard_B1ms` para soportar Docker (Costo estimado: S/ 54.00 al mes).
- **os_disk:** Almacenamiento Standard_LRS de 30 GB (Costo estimado: S/ 6.00 al mes).
- **azurerm_public_ip:** Dirección IP estática (Costo estimado: S/ 15.00 al mes).

### 4.2.1 Costos Generales

| Item | Cantidad | Costo Unitario | Total (S/.) |
|------|---------|---------------|------|
| Uso y desgaste de Laptops de desarrollo | 2 | 150.00 | 300.00 |
| Útiles de escritorio y papelería | 1 | 50.00 | 50.00 |
| **TOTAL** | | | **350.00** |

---

### 4.2.2 Costos Operativos

| Concepto | Costo mensual | Duración | Total (S/.) |
|----------|--------------|----------|------|
| Servicio de Internet | 120.00 | 1 | 120.00 |
| Consumo de Energía Eléctrica | 80.00 | 1 | 80.00 |
| **TOTAL** | | | **200.00** |

---

### 4.2.3 Costos del Ambiente

| Concepto | Costo mensual | Duración | Total (S/.) |
|----------|--------------|----------|------|
| Infraestructura Azure (VM Standard_B1ms, IP, Disco) | 75.00 | 1 | 75.00 |
| Dominio web y certificados SSL | 50.00 | 1 | 50.00 |
| **TOTAL** | | | **125.00** |

---

### 4.2.4 Costos de Personal

| Rol | Integrante | Costo mensual | Total (S/.) |
|-----|------------|--------------|------|
| Líder de Proyecto / DevOps / Backend | Cristhian | 1500.00 | 1500.00 |
| Desarrollador Frontend / QA / BD | Dayan | 1500.00 | 1500.00 |
| **TOTAL** | | | **3000.00** |

---

### 4.2.5 Costos Totales

| Categoría | Total (S/.) |
|----------|------|
| Generales | 350.00 |
| Operativos | 200.00 |
| Ambiente | 125.00 |
| Personal | 3000.00 |
| **TOTAL GENERAL** | **3675.00** |

---

## 4.3 Factibilidad Operativa

| Aspecto | Descripción | Estado |
|---------|------------|--------|
| Beneficios del producto | Automatización del filtrado de spam (Aegis Filter), reduciendo en un 80% el tiempo de moderación manual. | Viable |
| Capacidad de mantenimiento | El equipo cuenta con capacidad técnica para administrar el sistema mediante Integración Continua (GitHub Actions) y Docker. | Viable |

---

## 4.4 Factibilidad Legal

| Aspecto Legal | Descripción | Cumplimiento |
|--------------|------------|-------------|
| Propiedad Intelectual | Uso de tecnologías de código abierto (Laravel, Docker, Terraform) que no requieren licencias de pago | Cumple |
| Protección de Datos | El procesamiento de comentarios cumple normativas básicas de privacidad (datos simulados). | Cumple |

---

## 4.5 Factibilidad Social

| Aspecto | Descripción | Impacto |
|---------|------------|---------|
| Ética y entorno digital | Promueve un espacio limpio, libre de enlaces maliciosos o fraudes, protegiendo a la comunidad. | Alto |
| Clima Laboral | Reduce drásticamente la carga de estrés y el trabajo mecánico de los moderadores. | Alto |

---

## 4.6 Factibilidad Ambiental

| Aspecto | Descripción | Impacto |
|---------|------------|---------|
| Consumo Energético | El uso de Terraform permite apagar la VM en Azure cuando no está en uso, ahorrando energía. | Bajo |
| Sostenibilidad | Los centros de datos de Azure operan bajo compromisos globales de reducción de huella de carbono. | Bajo |

---

# 5. Análisis Financiero

## 5.1 Justificación de la Inversión

### 5.1.1 Beneficios del Proyecto
- **Beneficios Tangibles:** Ahorro estimado de S/ 4,800.00 anuales (S/ 400.00 mensuales) al evitar la contratación de un moderador humano gracias al servicio automatizado.
- **Beneficios Intangibles:** Mejor servicio al cliente, toma acertada de decisiones con métricas en tiempo real y aumento en la confiabilidad de la plataforma.

---

### 5.1.2 Criterios de Inversión

*(Proyección a 3 años, con un COK del 12% y Flujo Neto Anual de S/ 3,300.00)*

#### Relación Beneficio/Costo (B/C)
**Fórmula:**
B/C = Beneficios / Costos Totales

**Cálculo:**
B/C = 7926.04 / 3675 = 2.15
*Como el B/C es mayor a 1, el proyecto es factible y aceptado.*

---

#### Valor Actual Neto (VAN)
**Fórmula:**
VAN = Σ (Flujos / (1 + r)^t) - Inversión

**Cálculo:**
VAN = (3300 / 1.12) + (3300 / 1.2544) + (3300 / 1.4049) - 3675
VAN = 2946.43 + 2630.74 + 2348.87 - 3675
VAN = 7926.04 - 3675 = 4251.04
*Como el VAN es positivo (S/ 4,251.04), se acepta el proyecto.*

---

#### Tasa Interna de Retorno (TIR)
**Fórmula:**
0 = Σ (Flujos / (1 + TIR)^t) - Inversión

**Resultado aproximado:**
TIR ≈ 72.5%
*Al ser mayor que el Costo de Oportunidad de Capital (12%), se acepta el proyecto.*

---

# 6. Conclusiones

- **El proyecto es técnicamente viable:** La arquitectura basada en Docker, GitHub Actions y Terraform cumple con los estándares modernos.
- **Es económicamente factible:** La inversión inicial se recupera rápidamente y los costos en la nube están subvencionados académicamente.
- **Es operativamente eficiente:** Resuelve el problema de moderación manual, ahorrando un 80% de tiempo.
- **Tiene impacto social positivo:** Protege a los usuarios de fraudes y mejora la seguridad digital.
- **Presenta indicadores financieros favorables:** Un VAN positivo y un TIR del 72.5% aseguran su rentabilidad.
- **Es escalable a futuro:** La infraestructura puede crecer dinámicamente según la demanda gracias a la nube de Azure.

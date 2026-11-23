# Plan de Pruebas Formal y Protocolo UAT
## Intranet de Tienda Bomberos SpA
### Proyecto de Título / Taller de Integración (CIT3203 - Universidad Diego Portales)

---

| **Documento** | Plan de Aseguramiento de Calidad de Software (SQA) y Protocolo UAT |
| :--- | :--- |
| **Proyecto** | Intranet de Tienda Bomberos SpA |
| **Código Curso** | CIT3203 - Universidad Diego Portales |
| **Responsable de QA** | Sergio Soto (`sergio.soto1@mail.udp.cl`) |
| **Contraparte Técnica** | Carlos Sánchez (Tienda Bomberos SpA) |
| **Versión** | 2.0.0 (Reporte de Avance 2) |
| **Fecha de Emisión** | Noviembre 2026 |
| **Estado** | Aprobado para Integración Continua |

---

## 1. Introducción, Objetivos y Alcance del Testing

### 1.1. Propósito
El presente documento constituye el **Plan de Pruebas Formal** de la plataforma web corporativa **Intranet de Tienda Bomberos SpA**, desarrollada para sistematizar la gestión comercial, el catálogo técnico, la configuración de cotizaciones institucionales y el seguimiento operativo del taller de confección textil. Su propósito es definir la estrategia, los niveles de prueba, la matriz de trazabilidad y el protocolo de aceptación técnica exigido en la pauta de evaluación académica de la Universidad Diego Portales y los acuerdos de servicio con la contraparte empresarial.

### 1.2. Objetivos de Calidad
1. **Integridad Transaccional:** Garantizar que ninguna orden de corte ingrese a producción sin una cotización formalmente aprobada por el cliente institucional (RF-06).
2. **Robustez de la API RESTful:** Asegurar que los endpoints del backend en Laravel 11 respondan con códigos de estado HTTP semánticos (200, 201, 401, 404, 422) y ejecuten validaciones estrictas de esquemas de datos.
3. **Ergonomía y Usabilidad en Planta (RNF-04):** Validar que la interfaz web opere de forma fluida y sin fallas en tablets de 10 pulgadas (1024x768 y 1280x800 px), garantizando áreas táctiles mínimas de 48x48 píxeles y mecanismos de confirmación para operarios con guantes.
4. **Legibilidad y Contraste Industrial (RNF-02):** Verificar que los componentes cromáticos del tablero de taller cumplan las pautas WCAG 2.1 (ratios de contraste $\ge 4.5:1$ en texto general y $\ge 3.0:1$ en controles de gran tamaño).
5. **Trazabilidad y Auditoría (RF-08):** Registrar en base de datos cada transición de etapa productiva vinculando al usuario autenticado, marca temporal y bitácora técnica.

### 1.3. Alcance del Testing
* **Módulos Incluidos:**
  * Autenticación y Control de Acceso por Roles (RF-01).
  * Catálogo de Prendas Técnicas y Gestión de Variantes (RF-02).
  * Directorio Jerárquico de Cuerpos de Bomberos y Compañías (RF-03).
  * Carrito de Cotización y Cálculo Aritmético de Impuestos (RF-04).
  * Personalización Textil Institucional (Bordados, Tallas por Grupo) (RF-05).
  * Generación Automatizada de Órdenes de Corte (RF-06).
  * Máquina de Estados de Taller y Tablero Kanban Táctil (RF-07).
  * Auditoría Histórica de Etapas de Fabricación (RF-08).
  * Generación y Exportación de Documentos PDF Oficiales (RF-09).
* **Módulos Fuera de Alcance para este Hito:**
  * Integración con pasarelas de pago electrónico externo (Webpay/Transbank), dado que el modelo de negocio opera mediante órdenes de compra institucional y facturación a 30 días.
  * Facturación electrónica directa con el Servicio de Impuestos Internos (SII), la cual se gestiona a través del ERP central.

---

## 2. Estrategia de Pruebas

La estrategia de aseguramiento de calidad implementa una pirámide de pruebas adaptada al stack tecnológico del proyecto:

```
                  / \
                 /   \
                / UAT \   <- Validación Técnica con Carlos Sánchez
               /-------\
              / Usabil. \  <- Pruebas Táctiles en Tablet 10" (RNF-04)
             /   Tablet  \
            /-------------\
           /  Integración  \ <- Laravel Feature Tests (API RESTful)
          /       API       \
         /-------------------\
        /  Pruebas Unitarias  \ <- Aritmética, Validadores Zod, Reglas de Dominio
       /_______________________\
```

### 2.1. Pruebas Unitarias
* **Backend:** Ejecución de pruebas unitarias sobre modelos Eloquent, cálculos de IVA (19%), plazos de entrega y mutadores de datos utilizando PHPUnit.
* **Frontend:** Validación de esquemas Zod (`userSchema`, `quoteSchema`), utilitarios de formateo de moneda nacional (CLP) y normalización de estados de taller utilizando el test runner nativo de Node.js (`node:test`) y TypeScript.

### 2.2. Pruebas de Integración de API
* **Aislamiento de Base de Datos:** Empleo del trait `RefreshDatabase` de Laravel con base de datos SQLite en memoria (`:memory:`) y ejecutor de migraciones específico de pruebas (`tests/database/migrations`).
* **Seguridad y Control de Sesión:** Simulación de usuarios autenticados mediante tokens de Laravel Sanctum (`actingAsUser`, `actingAsAdmin`, `actingAsEjecutivo`).
* **Validación de Casos de Borde:** Verificación de inmutabilidad de órdenes finalizadas, rechazo de saltos inválidos en la máquina de estados y protección contra vulnerabilidades de inyección y parámetros huérfanos.

### 2.3. Pruebas de Usabilidad y Ergonomía en Tablets (RNF-04)
* **Entorno de Hardware Simulado:** Tablet táctil de 10 pulgadas bajo resoluciones WXGA (1280x800 px) y XGA (1024x768 px).
* **Dimensionamiento Táctil:** Verificación programática de que las clases Tailwind de los botones de taller especifiquen `min-h-16` (64 píxeles) y `min-w-16` (64 píxeles), superando el umbral de 48x48 px exigido por WCAG 2.5.5 y Google Material Guidelines.
* **Tolerancia a Fallos Ergonómicos:** Comprobación del mecanismo de doble toque (`CONFIRM_WINDOW_MS = 5000`) para evitar cambios de estado accidentales causados por roce de guantes de trabajo o manipulación en ambientes dinámicos de planta.
* **Contraste Lumínico (RNF-02):** Análisis de contraste de luminancia relativa sobre la paleta de colores de taller (`amber-600`, `blue-700`, `violet-700`, `emerald-700`, `red-600`) para garantizar visualización clara bajo focos industriales y luz natural directa.

---

## 3. Matriz de Trazabilidad de Pruebas vs Requerimientos

| **Req. ID** | **Descripción del Requerimiento** | **Tipo de Prueba** | **Suite / Archivo Automatizado** | **Criterio de Aceptación** | **Estado** |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **RF-01** | Autenticación y control de acceso basado en roles (Superadmin, Admin, Ejecutivo, Taller). | Integración API / Unitaria | `backend/tests/Feature/AuthTest.php`<br>`frontend/tests/session-auth.test.cjs` | Retorno de token Sanctum válido en login; bloqueo 401 en no autenticado; perfil `/api/me`. | **Aprobado** |
| **RF-02** | Catálogo técnico de prendas y gestión de variantes (talla, color, SKU base). | Integración / Regresión | `frontend/tests/catalog-loading.test.cjs`<br>`frontend/tests/catalog-variant-edit.test.cjs`<br>`frontend/tests/quotable-skus.test.cjs` | Carga asíncrona de familias; edición de matrices de talla; validación de SKUs cotizables. | **Aprobado** |
| **RF-03** | Directorio de clientes institucionales (Cuerpos de Bomberos y Compañías). | Integración API / Frontend | `backend/tests/Feature/Comercial/CompanyOrderHistoryApiTest.php`<br>`frontend/tests/destinatario.test.cjs` | Asociación estricta Compañía-Cuerpo; carga de contactos oficiales de recepción. | **Aprobado** |
| **RF-04** | Carrito de cotización con cálculo de impuestos y plazos de entrega. | Aritmética / Regresión | `frontend/tests/vencimiento-cotizacion.test.cjs`<br>`backend/tests/Feature/Comercial/CotizacionOptionsTest.php` | Desglose exacto de Neto + IVA 19%; cálculo dinámico de vigencia y entrega en días hábiles. | **Aprobado** |
| **RF-05** | Personalización institucional de prendas (bordados, parches, tallas por grupo). | Integración / UI | `frontend/tests/quote-personalization.test.cjs`<br>`backend/tests/Feature/Comercial/CotizacionItemPersonalizacionTest.php` | Almacenamiento JSON de matrices de bordado y distribución de tallas por brigada. | **Aprobado** |
| **RF-06** | Generación de Órdenes de Corte desde Cotizaciones aprobadas. | Integración API | `backend/tests/Feature/WorkshopWorkflowTest.php`<br>`backend/tests/Feature/Fabrica/CuttingOrderWorkflowApiTest.php` | Bloqueo 422 si cotización está en Borrador; creación 201 en estado 'corte' si está Aprobada. | **Aprobado** |
| **RF-07** | Máquina de estados operativa de taller (Corte -> Confección -> Empaque -> Terminado). | Integración de Flujo | `backend/tests/Feature/WorkshopWorkflowTest.php`<br>`frontend/tests/tablet-usability.test.cjs` | Transiciones secuenciales validadas; bloqueo de saltos arbitrarios o retrocesos inválidos. | **Aprobado** |
| **RF-08** | Auditoría y trazabilidad histórica de etapas productivas (`historial_etapas`). | Integración de Datos | `backend/tests/Feature/WorkshopWorkflowTest.php`<br>`backend/tests/Feature/Fabrica/CuttingOrderWorkflowApiTest.php` | Registro inmutable de cada cambio: usuario, etapa anterior, etapa nueva, timestamp y nota. | **Aprobado** |
| **RF-09** | Generación y exportación de documentos PDF oficiales con numeración correlativa. | Integración / Archivo | `backend/tests/Feature/Comercial/CotizacionExportPdfTest.php`<br>`frontend/tests/quote-personalization.test.cjs` | Generación de buffer PDF formal con membrete corporativo y desglose de prendas. | **Aprobado** |
| **RNF-02** | Contraste cromático adecuado para iluminación de planta textil. | Usabilidad / Accesibilidad | `frontend/tests/tablet-usability.test.cjs`<br>`frontend/tests/tablet-usability.test.tsx` | Ratios de contraste $\ge 4.5:1$ en texto general y $\ge 3.0:1$ en controles grandes (WCAG 2.1). | **Aprobado** |
| **RNF-04** | Usabilidad táctil en tablets de 10" con controles $\ge 48\times 48$ px y doble toque. | Usabilidad Táctil | `frontend/tests/tablet-usability.test.cjs`<br>`frontend/tests/tablet-usability.test.tsx` | Viewports 1024x768 y 1280x800 px; botones `min-h-16` (64px); ventana de confirmación 5s. | **Aprobado** |

---

## 4. Casos de Prueba Detallados

### CP-WORKSHOP-01: Rechazo de Generación de Orden de Corte desde Cotización en Borrador
* **ID:** `CP-WORKSHOP-01`
* **Requerimiento:** RF-06
* **Componente:** `CuttingOrderController@storeFromQuote` / `WorkshopStageService`
* **Precondición:** Existe una cotización en base de datos con estado "Borrador".
* **Datos de Entrada:** `POST /api/cutting-orders/from-quote/{draftQuoteId}` con token de usuario autenticado.
* **Pasos de Ejecución:**
  1. Autenticar como usuario con rol Administrativo.
  2. Enviar petición HTTP POST al endpoint indicando el ID de la cotización en borrador.
  3. Evaluar el código de respuesta HTTP.
  4. Consultar la tabla `ordenes_corte` para comprobar persistencia.
* **Resultado Esperado:** Respuesta HTTP 422 Unprocessable Content, payload JSON con `success: false` y mensaje descriptivo. La tabla `ordenes_corte` mantiene 0 registros asociados.
* **Estado:** **Aprobado (Automatizado)** en `WorkshopWorkflowTest.php::test_draft_quote_cannot_generate_cutting_order_and_fails_with_422`.

---

### CP-WORKSHOP-02: Generación Exitosa de Orden de Corte desde Cotización Aprobada
* **ID:** `CP-WORKSHOP-02`
* **Requerimiento:** RF-06, RF-05
* **Componente:** `CuttingOrderController@storeFromQuote` / `WorkshopStageService`
* **Precondición:** Cotización formalmente vinculada al Cuerpo de Bomberos de Santiago y 14ª Compañía, con estado "Aprobada por Cliente" y detalle de 8 parkas con personalización de bordado institucional y desglose de tallas (3 M, 5 L).
* **Datos de Entrada:** `POST /api/cutting-orders/from-quote/{approvedQuoteId}`.
* **Pasos de Ejecución:**
  1. Emitir la petición POST con usuario administrativo.
  2. Inspeccionar la respuesta HTTP y estructura JSON.
  3. Comprobar la creación de la orden de corte y sus ítems hijos (`items_orden_corte`).
  4. Verificar el registro inicial de la etapa en `historial_etapas`.
* **Resultado Esperado:** Código HTTP 201 Created. La orden se crea con `status: corte`, `total_unidades: 8`, asociación a cuerpo/compañía, desglose exacto de ítems con sus tallas/bordados y registro en `historial_etapas`.
* **Estado:** **Aprobado (Automatizado)** en `WorkshopWorkflowTest.php::test_approved_quote_successfully_generates_cutting_order_with_items_and_customization`.

---

### CP-WORKSHOP-03: Transición Secuencial de Máquina de Estados y Auditoría con Usuario
* **ID:** `CP-WORKSHOP-03`
* **Requerimiento:** RF-07, RF-08
* **Componente:** `CuttingOrderController@updateStage` / `WorkshopStageService`
* **Precondición:** Orden de corte generada en estado inicial `corte`. Usuario autenticado con rol `ejecutivo` ("Operario Textil Especializado").
* **Datos de Entrada:**
  * Intento 1: Salto inválido a `empaque`.
  * Intento 2: Transición válida a `confeccion` con nota de taller.
  * Intento 3: Retroceso inválido a `corte`.
  * Intento 4: Transición válida a `empaque`.
  * Intento 5: Transición válida a `terminado`.
* **Pasos de Ejecución:**
  1. Ejecutar peticiones `PATCH /api/cutting-orders/{id}/stage` para cada intento.
  2. Verificar los códigos de retorno HTTP (422 en intentos 1 y 3; 200 en intentos 2, 4 y 5).
  3. Consultar la tabla `historial_etapas` filtrando por `model_id`.
* **Resultado Esperado:** Las transiciones no permitidas son rechazadas con HTTP 422. Las transiciones válidas avanzan el estado y acumulan 4 registros de auditoría en `historial_etapas`, almacenando el `user_id` del operario y las notas técnicas ingresadas.
* **Estado:** **Aprobado (Automatizado)** en `WorkshopWorkflowTest.php::test_stage_transitions_respect_state_machine_and_log_history_with_authenticated_user`.

---

### CP-TABLET-01: Área de Interacción Táctil y Prevención de Pulsaciones Accidental
* **ID:** `CP-TABLET-01`
* **Requerimiento:** RNF-04
* **Componente:** `WorkshopOrderCard.tsx` (`/fabrica`)
* **Precondición:** Operario visualizando tarjetas de órdenes en tablet táctil de 10 pulgadas.
* **Datos de Entrada:** Inspección de clases de estilo Tailwind y temporizadores en `WorkshopOrderCard.tsx`.
* **Pasos de Ejecución:**
  1. Verificar la altura mínima del botón de avance de etapa (`min-h-16`).
  2. Verificar las dimensiones mínimas del botón de cancelación de confirmación (`min-h-16 min-w-16`).
  3. Evaluar el temporizador `CONFIRM_WINDOW_MS` y el mensaje de doble toque.
* **Resultado Esperado:** Los botones presentan dimensiones de 64x64 px (superando ampliamente el estándar WCAG de 48x48 px). El botón requiere un primer toque para armar el estado de confirmación ("Tocar otra vez para confirmar") y expira a los 5000 ms si no es confirmado.
* **Estado:** **Aprobado (Automatizado)** en `frontend/tests/tablet-usability.test.cjs`.

---

### CP-TABLET-02: Adaptabilidad a Viewports de Tablet 10" y Contraste Cromático
* **ID:** `CP-TABLET-02`
* **Requerimiento:** RNF-02, RNF-04
* **Componente:** `workshop-stages.ts` / `WorkshopOrderCard.tsx`
* **Precondición:** Condiciones de iluminación industrial de planta textil con pantallas táctiles fijas o móviles.
* **Datos de Entrada:** Resoluciones 1024x768 px y 1280x800 px; paleta cromática `amber-600`, `blue-700`, `violet-700`, `emerald-700`, `red-600` con texto `#ffffff`.
* **Pasos de Ejecución:**
  1. Simular anchos de pantalla y verificar capacidad de despliegue multi-columna.
  2. Calcular el ratio de contraste W3C WCAG 2.1 entre el color de fondo y el texto blanco.
* **Resultado Esperado:** La interfaz garantiza al menos 2 columnas simultáneas sin scroll horizontal destructivo. Todos los botones superan los ratios de contraste normados (azul confección 6.70:1, violeta empaque 8.42:1, verde terminado 5.14:1, rojo confirmación 4.70:1, ámbar corte 3.19:1 para texto grande en negrita).
* **Estado:** **Aprobado (Automatizado)** en `frontend/tests/tablet-usability.test.cjs`.

---

## 5. Protocolo de Pruebas de Aceptación del Usuario (UAT)

El **Protocolo UAT (User Acceptance Testing)** establece el marco formal bajo el cual la contraparte técnica del proyecto, **Carlos Sánchez**, valida la conformidad de los entregables del sistema en un entorno representativo de producción antes de autorizar el despliegue a régimen.

### 5.1. Datos Generales de la Sesión UAT
* **Entorno de Evaluación:** Servidor Staging local / Docker Compose en red interna.
* **Dispositivos Empleados:**
  * Estación de Trabajo PC (Navegador Chrome/Edge, resolución 1920x1080 px) para perfil Comercial/Administrador.
  * Tablet Android de 10 pulgadas (resolución 1280x800 px) montada en mesón de corte para perfil Operario de Taller.
* **Participantes:**
  * **Carlos Sánchez:** Contraparte Técnica y Evaluador Principal (Tienda Bomberos SpA).
  * **Sergio Soto:** Líder de Aseguramiento de Calidad y Pruebas (CIT3203 - UDP).
  * **Matías Tobar:** Arquitecto de Software y Desarrollador Backend.
  * **Felipe:** Ingeniero de Interfaz de Usuario y Desarrollador Frontend.

---

### 5.2. Escenarios de Negocio UAT para Validación Técnica

#### Escenario UAT-01: Ciclo Comercial Completo (Catálogo -> Cotización con Bordado -> Aprobación)
1. **Rol:** Ejecutivo Comercial.
2. **Acción 1:** Iniciar sesión en la intranet con credenciales corporativas autorizadas.
3. **Acción 2:** Acceder al módulo de Catálogo, filtrar prendas ignífugas y seleccionar "Casaca Institucional Bomberil".
4. **Acción 3:** Asociar la cotización al Cuerpo de Bomberos de Santiago y 14ª Compañía.
5. **Acción 4:** Configurar en el modal de personalización un bordado de pecho "14ª Cía" y distribución de tallas para 12 voluntarios.
6. **Acción 5:** Verificar en el carrito el cálculo de Neto, IVA (19%) y total en pesos chilenos (CLP).
7. **Acción 6:** Exportar la cotización formal en PDF con numeración correlativa y marcar el estado como "Aprobada por Cliente".
* **Criterio de Aceptación UAT:** El PDF se genera de forma instantánea sin distorsión de membrete y la cotización pasa a estado "Aprobada" quedando habilitada para producción.

---

#### Escenario UAT-02: Generación de Orden de Producción y Operación en Tablet de Planta
1. **Rol:** Jefe de Taller / Operario Textil.
2. **Acción 1:** Abrir la interfaz `/fabrica` en la tablet de 10 pulgadas situada en el área de corte.
3. **Acción 2:** Comprobar que la cotización aprobada en UAT-01 aparece disponible y se genera su correspondiente orden de corte con todos los desgloses de talla y especificaciones de tela.
4. **Acción 3:** Tocar el botón "Corte listo · Pasar a Confección". Verificar que el sistema entra en modo de confirmación ("Tocar otra vez para confirmar") con alerta visual roja.
5. **Acción 4:** Tocar nuevamente para confirmar antes de 5 segundos.
6. **Acción 5:** Verificar que la orden avanza a la columna de "Confección" con badge azul y barra de progreso al 50%.
7. **Acción 6:** Repetir la operación para avanzar a "Empaque" (75%) y finalmente a "Terminado" (100%).
* **Criterio de Aceptación UAT:** El avance táctil se ejecuta sin retrasos perceptibles (< 300 ms), los botones responden con ergonomía al tacto con guantes y no se producen bloqueos visuales ni desbordamientos de pantalla en la tablet.

---

#### Escenario UAT-03: Auditoría y Verificación de Trazabilidad Histórica
1. **Rol:** Administrador General.
2. **Acción 1:** Ingresar al detalle de auditoría de la orden generada o consultar el historial de etapas vía API `/api/cutting-orders/{id}/logs`.
3. **Acción 2:** Constatar que figuran registrados cronológicamente todos los cambios de etapa, con el nombre del operario responsable, fecha/hora exacta y las notas técnicas asociadas.
* **Criterio de Aceptación UAT:** Trazabilidad 100% auditable; los registros coinciden con las acciones ejecutadas en la tablet y no existe discrepancia de datos.

---

### 5.3. Criterios de Aceptación y Veredicto Formal (Go / No-Go)

Para declarar formalmente aceptado el sistema por la contraparte técnica, se deben cumplir los siguientes umbrales:
* **Severidad Crítica (Bloqueante):** 0 incidencias abiertas.
* **Severidad Mayor:** 0 incidencias abiertas que afecten cálculos comerciales o transiciones de orden.
* **Severidad Menor / Cosmética:** Máximo 3 observaciones visuales que no impidan la operación fluida.
* **Cobertura de Suites Automatizadas:** 100% de los casos de prueba unitarios, de integración y usabilidad táctil ejecutados en estado **PASS**.

---

### 5.4. Acta de Aceptación y Conformidad Técnica

| **Criterio Evaluado** | **Resultado Observado** | **Conformidad Contraparte** |
| :--- | :--- | :---: |
| Flujo de Autenticación y Perfiles de Usuario | Operación correcta con tokens Sanctum | **Aceptado** |
| Configuración de Prendas y Cotizaciones en PDF | Cálculo de IVA exacto y renderizado PDF conforme | **Aceptado** |
| Generación de Órdenes de Corte (RF-06) | Transición íntegra de ítems y bloqueo de borradores | **Aceptado** |
| Máquina de Estados y Usabilidad en Tablet (RF-07, RNF-04) | Operación táctil en 10" fluida con doble confirmación | **Aceptado** |
| Trazabilidad e Historial de Etapas (RF-08) | Auditoría completa con usuario y marca temporal | **Aceptado** |

**Declaración de Cierre UAT:**  
Habiéndose verificado la ejecución satisfactoria de las suites de pruebas automatizadas y las pruebas de usabilidad táctil en tablet de planta, se certifica que la plataforma cumple con los estándares técnicos y funcionales acordados para el Hito 2.

```
__________________________________              __________________________________
        Carlos Sánchez                                     Sergio Soto
   Contraparte Técnica Empresarial                  Líder de Calidad y Pruebas
       Tienda Bomberos SpA                             CIT3203 - UDP
```

# Guía de Colaboración y Estándares de Ingeniería

> **Proyecto:** Intranet de Tienda Bomberos: Plataforma de Gestión Comercial y Productiva  
> **Asignatura:** Proyectos en TIC II (CIT3203) — Universidad Diego Portales  
> **Cliente:** Tienda Bomberos SpA (Contraparte: Carlos Sánchez)  
> **Equipo:** Diego Tapia (PM), Matías Tobar (Backend), Felipe Ormazábal (Frontend), Sergio Soto (QA & Docs)

---

## 1. Arquitectura del Repositorio (Monorepo)

El proyecto está unificado en una sola raíz para garantizar consistencia entre la API y la aplicación web:

```text
tienda-bomberos-intranet/
├── backend/               # API REST en Laravel 11 (PHP 8.2+)
│   ├── app/               # Controladores, Modelos, Políticas de acceso
│   ├── database/          # Migraciones, Seeds y Factorías
│   └── routes/            # Definición de rutas API (api.php)
├── frontend/              # Aplicación web en Next.js 14 + TypeScript
│   ├── app/               # App Router (Páginas y layouts)
│   ├── components/        # Componentes UI reutilizables (Tailwind / Shadcn)
│   └── lib/               # Clientes HTTP, utilidades y hooks
└── docker-compose.yml     # Orquestación de servicios (Frontend, Backend, DB)
```

---

## 2. Roles y Distribución de Responsabilidades

| Integrante | Rol Oficial | Áreas Principales |
| :--- | :--- | :--- |
| **Diego Tapia** | **Gerente de Proyecto (PM)** | Gestión de cronograma, coordinación de entregables, integración continua y control de releases. |
| **Matías Tobar** | **Backend y Datos** | Modelado relacional, migraciones, controladores API, servicios de PDF y autenticación. |
| **Felipe Ormazábal** | **Frontend y UI** | Vistas web en Next.js, integración con endpoints, interfaz de cotizaciones y catálogo. |
| **Sergio Soto** | **UI/UX, Calidad y Docs** | Interfaz táctil de taller, pruebas funcionales (QA/UAT), manuales de usuario y documentación técnica. |

---

## 3. Modelo de Ramas (Branching Strategy)

Trabajamos bajo un modelo basado en características (*feature-branching*):

1. **`main`**: Rama protegida y estable. Solo contiene versiones listas para evaluación y entrega oficial con el profesor.
2. **`develop`**: Rama base de integración diaria. Todos los features se integran aquí primero.
3. **Ramas de trabajo:**
   * `feature/<modulo>-<descripcion>`: Para nuevas funcionalidades (ej. `feature/catalogo-variantes`, `feature/cotizador-pdf`).
   * `fix/<modulo>-<descripcion>`: Para corrección de errores (ej. `fix/cotizaciones-calculo-iva`).
   * `docs/<modulo>`: Para manuales técnicos y especificaciones de arquitectura.
   * `test/<modulo>`: Para pruebas unitarias o de integración (ej. `test/auth-roles`).

---

## 4. Convención de Commits (Conventional Commits)

Cada commit debe ser atómico y utilizar el siguiente formato estricto:

```text
<tipo>(<módulo>): <descripción clara en minúsculas y tiempo presente>
```

### Tipos Permitidos:
* `feat`: Nueva funcionalidad o vista (ej. `feat(catalogo): implementar selector de tallas y variantes`).
* `fix`: Corrección de un fallo o bug (ej. `fix(auth): validar expiración del token de sesión`).
* `docs`: Cambios exclusivos en documentación técnica (ej. `docs(api): documentar contratos de endpoints de cotizaciones`).
* `style`: Ajustes de diseño visual o formato sin cambio en lógica (ej. `style(taller): optimizar botones táctiles para tablet`).
* `refactor`: Refactorización de código sin añadir funcionalidad (ej. `refactor(backend): modularizar servicio de generación de pdf`).
* `test`: Creación o actualización de pruebas (ej. `test(cotizaciones): agregar pruebas de cálculo de totales`).
* `chore`: Tareas de configuración, Docker o dependencias (ej. `chore(docker): actualizar dependencias de compose`).

### Módulos Sugeridos (Scopes):
`auth`, `catalogo`, `clientes`, `cotizaciones`, `taller`, `seguimiento`, `reportes`, `docker`.

---

## 5. Reglas de Calidad y Seguridad

1. **Desarrollo Incremental:** Todo cambio debe verse reflejado de forma natural en el flujo de trabajo del equipo.
2. **Buenas Prácticas:** Mantener código modular, componentes reutilizables, tipado estricto en TypeScript y buenas prácticas en Eloquent ORM.
3. **Cero Secretos:** Nunca subir archivos `.env` reales, contraseñas o llaves privadas al repositorio. Utilizar siempre `.env.example`.

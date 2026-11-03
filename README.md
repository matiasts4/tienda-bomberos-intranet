# Intranet de Tienda Bomberos

Plataforma integral para la gestión comercial y productiva de Tienda Bomberos SpA, orientada a centralizar el flujo de cotizaciones institucionales, personalización de uniformes y seguimiento de pedidos.

---

## 🚀 Características Principales

* **Catálogo de Productos:** Administración de prendas técnicas y accesorios con soporte para variantes de talla y color.
* **Directorio Institucional:** Registro y gestión jerárquica de Cuerpos de Bomberos, Compañías y contactos de despacho.
* **Cotizador Comercial:** Configuración de cotizaciones con personalización estructurada de prendas (bordados, logos institucionales, tallas y nombres).
* **Documentos en PDF:** Generación e impresión ágil de cotizaciones formales con numeración correlativa única.
* **Seguridad y Perfiles:** Autenticación de usuarios y permisos según rol operativo.

---

## 🛠️ Stack Tecnológico

* **Backend:** Laravel 11 (PHP 8.2), API RESTful, Eloquent ORM.
* **Frontend:** Next.js 14 (React 18), TypeScript, Tailwind CSS.
* **Base de Datos:** MariaDB / MySQL.
* **Infraestructura:** Docker y Docker Compose.

---

## 💻 Puesta en Marcha Rápida

### Prerrequisitos
* Docker y Docker Compose (v2.20+)
* Node.js v18+ (opcional para desarrollo local)
* PHP 8.2+ y Composer (opcional para desarrollo local)

### Iniciar con Docker (Recomendado)
```bash
# 1. Clonar el repositorio
git clone https://github.com/matiasts4/tienda-bomberos-intranet.git
cd tienda-bomberos-intranet

# 2. Levantar los servicios
docker compose up -d --build

# 3. Acceso a las aplicaciones
# Frontend: http://localhost:3000
# Backend API: http://localhost:8000/api
```

---

## 📖 Guías del Proyecto

Para conocer el modelo de ramas, convenciones de commits y estándares de código, consulta la [Guía de Colaboración](GUIA_COLABORACION.md).

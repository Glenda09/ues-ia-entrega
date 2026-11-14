# 🎓 UES IA
### Plataforma de Gestión Académica Inteligente

Sistema web para gestionar procesos académicos y de admisión, con **asistentes de inteligencia artificial** para aspirantes y estudiantes.

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![Vue 3](https://img.shields.io/badge/Vue-3-42B883?logo=vuedotjs&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)

</div>

## ✨ Funcionalidades

- **Administración académica:** carreras, materias, pensums, sedes, docentes y ciclos.
- **Admisión:** convocatorias, registro de aspirantes, solicitudes y expedientes.
- **Portales especializados:** inscripción y avance estudiantil; grupos y calificaciones docentes.
- **Asistentes de IA:** orientación para aspirantes y apoyo académico a estudiantes, con control de consumo y presupuesto.
- **Seguridad:** autenticación, perfiles, permisos y verificación en dos pasos.

## 🛠️ Tecnologías

| Capa | Tecnologías |
| --- | --- |
| Backend | PHP 8.2+ · Laravel 12 |
| Frontend | Vue 3 · Vite · PrimeVue · Tailwind CSS 4 |
| Base de datos | PostgreSQL 17 |
| Infraestructura | Docker Compose · Nginx |
| Pruebas | PHPUnit · Playwright · JMeter |

## 🚀 Inicio rápido

Requisitos: **Docker con Compose** y **Node.js 22** para el frontend en desarrollo.

```bash
# 1. Preparar la configuración
cp .env.example .env

# 2. Levantar aplicación, PostgreSQL y Mailpit
docker compose -f docker-compose.yml -f docker-compose.databases.yml -f docker-compose.dev.yml up -d --build

# 3. Crear tablas y datos iniciales
docker compose exec app php artisan migrate --seed

# 4. Iniciar Vite en tu equipo
npm ci
npm run dev
```

Abre **http://localhost:8090** (puerto predeterminado de Docker). Ajusta las variables de `.env` según tu entorno. La IA funciona con un **adaptador simulado** si no se configura `OPENAI_API_KEY` o si se establece `AI_PROVIDER=mock`.

## 📁 Estructura principal

```text
app/              Lógica de negocio, controladores y servicios de IA
resources/js/     Interfaz Vue y módulos académicos
routes/           Rutas web y API
database/         Migraciones y datos iniciales
tests/            Pruebas funcionales y de extremo a extremo
deploy/           Configuración de despliegue
```

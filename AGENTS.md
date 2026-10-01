# Saberé Marketing

Aplicación de planificación y publicación de contenido de redes sociales de Saberé (Instagram, LinkedIn, TikTok).

Stack: Laravel 12 + Inertia + Vue 3 + TypeScript + Vite + Tailwind + MySQL (Laravel Sail). Tests con PHPUnit.

Diseño completo y fases: `docs/superpowers/specs/2026-10-01-sabere-marketing-agent-design.md`.
- **Fase 1 (implementada)**: base + grilla mensual (brand profile, pilares, estrategias por red, eventos de calendario, generación con LLM, calendario con aprobar/editar/regenerar).
- **Fase 2 (pendiente)**: render de diseños (plantillas, fondos IA, Playwright + ffmpeg).
- **Fase 3 (pendiente)**: publicación vía APIs oficiales (OAuth, publishers, scheduler).

## Comandos

```bash
# Levantar el entorno (app + mysql)
./vendor/bin/sail up -d

# Migraciones y seeds
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate:fresh --seed   # AdminUserSeeder + DefaultsSeeder

# Tests
./vendor/bin/sail artisan test

# Frontend
./vendor/bin/sail npm run dev     # Vite dev server
./vendor/bin/sail npm run build   # build + vue-tsc (typecheck)

# Estilo de código
./vendor/bin/sail pint

# Colas (los jobs de generación corren por queue 'database')
./vendor/bin/sail artisan queue:work          # worker continuo
./vendor/bin/sail artisan queue:work --once   # procesa un solo job
```

Sin Sail, los comandos equivalentes usan `php artisan` / `npm` directamente en el host.

## Puertos

| Servicio | Puerto | Variable |
|---|---|---|
| App (HTTP) | 5600 | `APP_PORT` |
| MySQL (host) | 3307 | `FORWARD_DB_PORT` |
| Vite dev | 5174 | `VITE_PORT` |

Los puertos 9000, 3306, 5500 y 5173 están ocupados por otros proyectos en esta máquina.

## Acceso

- App: http://localhost:5600
- Admin: `admin@sabere.com` / `password` (creado por `AdminUserSeeder`). No hay registro público.

## LLM

- `LLM_DRIVER=anthropic` usa la API real (requiere `ANTHROPIC_API_KEY`, modelo `ANTHROPIC_MODEL`, default `claude-sonnet-4-5`).
- `LLM_DRIVER=fake` usa `FakeLlmClient` (respuestas encoladas con `withResponse()`); forzado en `phpunit.xml` para tests.
- La interfaz es `App\Services\Llm\LlmClient`, bindeada en `AppServiceProvider`. Todas las llamadas se registran en `llm_logs`.

## Flujo: generar la grilla de un mes

1. En **Configuración → Pilares de contenido**, la suma de pesos debe ser 100 (el indicador lo muestra; el endpoint devuelve 422 si no).
2. En `/plans/{año}/{mes}` (o "Grilla" en el menú) pulsa **Generar grilla** — encola el job `GenerateMonthlyPlan` (`generating_at` marca el estado y la UI hace polling a `plans.status`).
3. Corre el worker: `./vendor/bin/sail artisan queue:work --once` (o déjalo corriendo).
4. Los posts quedan en `drafted`; desde el drawer de cada post se puede **Aprobar**, **Editar** o **Regenerar** (job `RegeneratePost`, también encolado — corre el worker de nuevo).

## Notas

- Locale `es`, timezone `America/Caracas`, faker `es_VE`.
- Paleta Tailwind `sabere.*` (`dark`, `light`, `accent`, `purple`) y fuente Inter.
- Alias `@/*` → `resources/js/*`.

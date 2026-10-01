# Plan Fase 1 — Base + grilla mensual

Spec: `docs/superpowers/specs/2026-10-01-sabere-marketing-agent-design.md` (§4, §5, §8, §10).

Entorno: Laravel Sail. Puertos: `APP_PORT=5600`, `FORWARD_DB_PORT=3307`, `VITE_PORT=5174` (5500/5173/3306/9000 ya están ocupados en esta máquina).

## Paso 1 — Scaffolding

- `laravel new` → Laravel 12 con Breeze (stack Inertia + Vue + TypeScript, dark mode no), Pest **no**: usar PHPUnit.
- Sail con servicio `mysql` solamente.
- Tailwind: añadir paleta `sabere.*` y fuente Inter (spec §3).
- Locale `es`, timezone `America/Caracas`, faker `es_VE`.
- Deshabilitar registro público: quitar rutas `register` de Breeze; crear admin por seeder (`admin@sabere.com` / `password`).
- Alias `@/*` → `resources/js/*`.
- `AGENTS.md` con comandos (sail up, migrate, seed, test, build, pint).
- Commit.

## Paso 2 — Modelo de datos

Migraciones + modelos + factories (spec §4):

- `brand_profile`: `tone_of_voice` text, `audience` text, `base_hashtags` json, `cta` string, `url` string, `palette` json. Singleton: modelo `BrandProfile::current()` que crea la fila si no existe.
- `network_strategies`: `network` enum(`instagram`,`linkedin`,`tiktok`) unique, `posts_per_week` tinyint, `preferred_formats` json, `focus` text.
- `content_pillars`: `name`, `description` text, `weight` tinyint (0–100).
- `calendar_events`: `date` date, `name`, `description` nullable, `recurring` bool, `no_posting` bool.
- `monthly_plans`: `year` smallint, `month` tinyint, `status` enum(`draft`,`approved`), `generated_at` nullable; unique(year, month).
- `posts`: `monthly_plan_id` FK, `scheduled_at` datetime, `network` enum, `content_pillar_id` FK nullable, `format` string, `hook` string, `copy` text, `hashtags` json, `design_payload` json, `background_prompt` text nullable, `template` string nullable, `asset_path` nullable, `external_id` nullable, `permalink` nullable, `status` enum(`drafted`,`approved`,`rendered`,`scheduled`,`published`,`failed`) default `drafted`, `error` text nullable, `published_at` nullable.
- `llm_logs`: `purpose` string, `prompt` longtext, `response` longtext, `input_tokens`, `output_tokens`, `model`, `duration_ms`.

Enums PHP: `Network`, `PostStatus`, `PostFormat` en `app/Enums/`. `PostStatus` expone `canTransitionTo(PostStatus)` con las reglas de §4 (fase 1 solo usa `drafted`→`approved` y edición; el resto de transiciones se definen ya para no rehacerlas).

Seeder `DefaultsSeeder`: brand profile con tono/audiencia de Saberé, 3 estrategias con los valores de §4, 4 pilares (Tips para docentes 30 %, Funcionalidades de Saberé 30 %, Educación en Venezuela 20 %, Testimonios/Comunidad 20 %), eventos de calendario fijos venezolanos (1 ene, 15 ene Día del Maestro, carnaval y semana santa 2026/2027 como fechas puntuales, 19 abr, 1 may, 24 jun, 5 jul, 24 jul, 12 oct, 24–25 dic, 31 dic) marcando `no_posting` en 1 ene, 24–25 dic y 31 dic.

Tests: factories + test de `PostStatus::canTransitionTo`.

## Paso 3 — Configuración (UI)

Páginas Inertia bajo `/settings`:
- `Settings/Brand.vue` — formulario brand profile.
- `Settings/Networks.vue` — tabla editable de las 3 estrategias.
- `Settings/Pillars.vue` — CRUD pilares con validación de que la suma de pesos sea 100.
- `Settings/Calendar.vue` — CRUD eventos.

Controladores en `app/Http/Controllers/Settings/`, Form Requests para validación. Tests Feature por cada endpoint (auth requerido, validación, persistencia).

## Paso 4 — LlmClient

- `app/Services/Llm/LlmClient` interface: `complete(string $system, string $user, array $jsonSchema, string $purpose): array` — devuelve el JSON decodificado y registra en `llm_logs`.
- `AnthropicClient` con `Http` de Laravel (`claude-sonnet-4-5`, `max_tokens` 16000), usando tool-use forzado para salida estructurada según `$jsonSchema`.
- `FakeLlmClient` para tests (devuelve fixture configurable).
- Binding en `AppServiceProvider` según `config('llm.driver')` (`anthropic` | `fake`). `config/llm.php` + `.env.example` con `ANTHROPIC_API_KEY`, `LLM_DRIVER`.
- Tests: `AnthropicClient` con `Http::fake()` (éxito, error HTTP → excepción `LlmException`, JSON inválido → excepción).

## Paso 5 — Generación de la grilla

- `app/Services/Planning/PromptBuilder`: arma system + user prompt a partir de brand profile, estrategias, pilares, eventos del mes, resumen del mes anterior (hooks + pilar) y un comentario opcional. Reglas en el prompt: cadencia por red, pesos de pilares, no repetir tema entre redes en la misma semana, no publicar en días `no_posting`, horarios sugeridos por red (LinkedIn 8:00 L–V, Instagram 18:00, TikTok 19:00), todo en español de Venezuela.
- `app/Services/Planning/PlanSchema`: JSON Schema de la respuesta (`posts[]` con `date`, `time`, `network`, `pillar`, `format`, `hook`, `copy`, `hashtags[]`, `design_payload` {`slides`: [{`title`,`body`}]}, `background_prompt`).
- `app/Services/Planning/PlanValidator`: valida que `format` sea compatible con `network`, que `pillar` exista, que las fechas caigan en el mes y no en `no_posting`; los posts inválidos se descartan y se registran en log.
- Job `GenerateMonthlyPlan(int $year, int $month)`: crea/obtiene `monthly_plans`, borra posts `drafted` previos del plan, llama LLM, valida, inserta posts, marca `generated_at`.
- Job `RegeneratePost(Post $post, ?string $comment)`: prompt acotado a un solo post (misma red, fecha, pilar), reemplaza copy/hook/hashtags/design_payload/background_prompt.
- Tests Feature con `FakeLlmClient`: fixture con 2 semanas de posts → cantidades por red correctas; fixture con post inválido (formato incompatible) → descartado; regenerar post conserva fecha/red.

## Paso 6 — Calendario (UI)

- `Plans/Show.vue` en `/plans/{year}/{month}`: grilla mensual (7 columnas), cada celda lista los posts del día con chip de red y color por estado. Filtro por red. Botón "Generar grilla" (si no hay posts) / "Regenerar mes" (con confirmación). Navegación mes anterior/siguiente. Cuando el job está en cola, polling cada 3 s a `/plans/{y}/{m}/status`.
- `Plans/PostDrawer.vue` (panel lateral): muestra hook, copy, hashtags, pilar, formato, fecha/hora; acciones **Aprobar**, **Editar** (formulario inline: copy, hashtags, scheduled_at, format), **Regenerar** (textarea comentario opcional). Ya muestra placeholder "Diseño pendiente (Fase 2)".
- Endpoints: `GET /plans/{y}/{m}`, `POST /plans/{y}/{m}/generate`, `GET /plans/{y}/{m}/status`, `PATCH /posts/{post}`, `POST /posts/{post}/approve`, `POST /posts/{post}/regenerate`.
- Dashboard `/` redirige al mes actual.
- Tests Feature: cada endpoint (auth, validación, cambio de estado, `approve` rechaza si no está `drafted`).

## Paso 7 — Cierre

- `php artisan test` en verde, `vue-tsc` sin errores, `pint` limpio.
- Probar manualmente con `LLM_DRIVER=fake` y luego con Anthropic real (si hay API key) generando octubre 2026.
- Actualizar `AGENTS.md`. Commit por paso.

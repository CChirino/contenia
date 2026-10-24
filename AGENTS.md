# Contenia

Plataforma multimarca de contenido para redes sociales (agencia). **Saberé es la marca id=1**; la app se llama Contenia y también planifica y publica en Instagram, LinkedIn y TikTok.

Stack: Laravel 12 + Inertia + Vue 3 + TypeScript + Vite + Tailwind + MySQL (Laravel Sail). Tests con PHPUnit.

Diseño completo y fases: `docs/superpowers/specs/2026-10-01-sabere-marketing-agent-design.md`.
- **Fase 1 (implementada)**: base + grilla mensual (brand profile, pilares, estrategias por red, eventos de calendario, generación con LLM, calendario con aprobar/editar/regenerar).
- **Fase 2 (implementada)**: render de diseños (plantillas, fondos IA, Playwright + ffmpeg).
- **Fase 3 (pendiente)**: publicación vía APIs oficiales (OAuth, publishers, scheduler).

**Multi-marca (Fase 1 multimarca, implementada)**: una base de datos, `brand_id` en el dominio, marca activa por contenedor. Saberé es la marca `id=1`. Spec: `docs/superpowers/specs/2026-10-13-plataforma-multimarca-design.md`.

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

# Colas (los jobs de generación/render corren por queue 'database')
# El servicio `queue` de compose.yaml levanta un worker permanente con `sail up -d`.
./vendor/bin/sail restart queue               # recargar el worker tras cambiar código de jobs
./vendor/bin/sail logs -f queue               # ver qué está procesando
./vendor/bin/sail artisan queue:work --once   # procesar un solo job a mano (si el servicio está parado)

# Generación por CLI y depuración del LLM
./vendor/bin/sail artisan plan:generate 2026 10          # genera la grilla en el momento (semana a semana)
./vendor/bin/sail artisan plan:generate 2026 10 --queue  # solo encola el job
./vendor/bin/sail artisan llm:probe 2026-10-05 2026-10-11  # una llamada real de una semana, muestra la respuesta cruda
./vendor/bin/sail artisan llm:inspect [id]               # inspecciona los arguments guardados en llm_logs
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

- `LLM_DRIVER=anthropic` usa la API de Anthropic (`ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, default `claude-sonnet-4-5`).
- `LLM_DRIVER=openrouter` usa OpenRouter (`OPENROUTER_API_KEY`, `OPENROUTER_MODEL`, default `anthropic/claude-sonnet-4.5`).
- `LLM_DRIVER=fake` usa `FakeLlmClient` (respuestas encoladas con `withResponse()`); forzado en `phpunit.xml` para tests.
- La interfaz es `App\Services\Llm\LlmClient`, bindeada en `AppServiceProvider`. Todas las llamadas se registran en `llm_logs`.
- Ajustes comunes: `LLM_TIMEOUT` (default 300 s por llamada), `LLM_RETRIES` (default 3 intentos, con backoff de 5 s·intento ante 429/5xx/errores de conexión) y `LLM_MAX_TOKENS` (default 8000; con modelos gratuitos de OpenRouter conviene 16000).
- Modelos gratuitos de OpenRouter (verificado 2026-10-24 con tool-calling): `nvidia/nemotron-3.5-super-vl:free` (en uso), `nvidia/nemotron-3.5-lightning:free`, `nvidia/nemotron-3-ultra-550b-a55b:free`. `nvidia/nemotron-3-super-120b-a12b:free` devuelve 404 del proveedor desde esa fecha (incluso sin tools); `google/gemma-4-*:free` suele estar saturado (429). Si un job LLM falla con 404/"Function … Not found", prueba el modelo a mano con `curl` y cambia `OPENROUTER_MODEL` (`GET /api/v1/models` lista los `:free` con `tools` en `supported_parameters`); el cliente solo reintenta 429/5xx. Algunos modelos ignoran `tool_choice` y devuelven el JSON en `message.content` (con o sin ```json), o `posts` doble-codificado — el cliente y el job lo toleran como fallback.

## Imágenes (fondos de diseño)

- `IMAGE_DRIVER=pollinations` usa la API vigente `gen.pollinations.ai/image/{prompt}` (`POLLINATIONS_API_KEY`, `POLLINATIONS_MODEL`, `POLLINATIONS_BASE_URL`). La clave debe ser `sk_...` de enter.pollinations.ai; la API legacy `image.pollinations.ai` está obsoleta (ignora el token y añade marca de agua).
- Modelos sugeridos: `z-image-turbo` (default, buena adherencia a paleta y a "sin texto"), `flux` (alternativa barata).
- La respuesta es JPEG con dimensiones aproximadas — `PollinationsGenerator` la normaliza a PNG exacto (cover). `geometric` es el fallback sin dependencias externas; `fake` va en `phpunit.xml`.
- `BackgroundService::for($post)` genera `storage/app/brands/{brand_id}/posts/{post_id}/bg.png` (seed = post id, prompt = `background_prompt ?? hook` + `images.style_suffix`) y marca `posts.background_source`. La ruta la centraliza `Post::assetDir()` / `Post::assetPath($file)` — no construyas `assets/{id}` a mano.
- Prueba rápida del driver: `./vendor/bin/sail artisan images:test [ancho] [alto] --prompt="..."` → guarda `storage/app/test-bg.png`.

## Video (reel/TikTok)

- `RenderPost` ensambla `final.mp4` para formatos `reel`/`video` tras los frames PNG: `VideoAssembler` → `FfmpegVideoAssembler` (ffmpeg en el contenedor) con `FakeVideoAssembler` para tests.
- Parámetros en `config/render.php`: `seconds_per_frame` (3.5), `transition_seconds` (0.5, xfade=fade), `ffmpeg_binary`, `audio_track` (`resources/audio/bg-upbeat.mp3`, loop recortado con `-shortest`, volumen 0.6 y fade-out 1.5 s). Salida h264 `yuv420p` 30 fps `+faststart` + AAC 128k; duración = n·3.5 − (n−1)·0.5.
- La pista es un pad ambiente sintético generado con ffmpeg (`resources/audio/LICENSE.md`); freepd.com está cerrado.
- `assets.show` sirve `final.mp4` con `BinaryFileResponse` (Content-Type y Range); `posts.download` devuelve el MP4 directo.

## Flujo: generar la grilla de un mes

1. En **Configuración → Pilares de contenido**, la suma de pesos debe ser 100 (el indicador lo muestra; el endpoint devuelve 422 si no).
2. En `/b/{marca}/plans/{año}/{mes}` (o "Grilla" en el menú) pulsa **Generar grilla** / **Completar mes** — encola el job `GenerateMonthlyPlan` (`generating_at` marca el estado y la UI hace polling a `plans.status`). El job recorre las semanas lunes–domingo recortadas al mes con una llamada LLM por semana, **solo en las semanas que no tienen ningún post** (no borra nada). El botón ↻ de cada fila regenera solo esa semana (reemplaza sus `drafted`, conserva aprobados). Si una semana falla conserva las demás y anota el fallo en `last_error` (JSON `[{from,to,message}]`; solo relanza si fallan todas).
3. El servicio `queue` de Sail procesa el job (si "Generando..." no avanza, comprueba `./vendor/bin/sail ps` — sin worker el job queda esperando en la tabla `jobs`). Tras cambiar código de los jobs, `./vendor/bin/sail restart queue` — un worker residente mantiene el código viejo cargado.
4. Los posts quedan en `drafted`; desde el drawer de cada post se puede **Aprobar**, **Editar** o **Regenerar** (job `RegeneratePost`, también encolado — corre el worker de nuevo).

## Marcas

- Tabla `brands`: Saberé = `id=1`, slug `sabere`. Planes en `plans` (Básico/Pro/Interno). `brand_user` asigna operadores; `users.role` = `admin|operator`.
- Toda la app cuelga de `/b/{brand:slug}/...` (plans, posts, settings). `/plans/{a}/{m}` antiguo → 301 a la marca del usuario (`last_brand_id` o primera asignada).
- **Marca activa**: `SetActiveBrand` (alias `brand`) resuelve el slug, autoriza `BrandPolicy::view` (admin o asignado), hace `app()->instance('brand', $brand)` y guarda `last_brand_id`. `BrandScope` filtra las consultas del dominio cuando hay marca en el contenedor; `CalendarEvent` usa scope propio (null=o=activa). Va **antes** de `SubstituteBindings` (`prependToPriorityList` en `bootstrap/app.php`) para que el route-model-binding ya filtre → post de otra marca = 404.
- **Jobs**: ningún job puede depender de `app('brand')` de sesión — cada `handle()` empieza con `app()->instance('brand', $model->brand)` (`GenerateMonthlyPlan`, `RegeneratePost`, `RenderPost`). El worker `queue` es residente: tras tocar código de jobs, `sail restart queue`.
- **Comandos**: `plan:generate`, `posts:enrich-photos` y `render:post` aceptan `--brand=slug` (en `render:post` se deduce del post si falta).
- **Admin**: `/brands` (crear/editar/pausar, sin borrado; logo SVG/PNG ≤1 MB) y `/users` (operadores, roles, asignación) bajo middleware `admin`. El selector de marca del nav lista las asignadas (todas para admin).
- **Logo por marca**: `GET /b/{brand}/logo` (pública, solo lectura — Chromium la usa en el render) sirve `logo_path` o el SVG del repo.
- **Paleta por marca**: `brands.palette` llega a las plantillas de render vía variables CSS `--brand-dark/light/accent/secondary` que `BrandFrame` fija en el contenedor (más `wordmark` = `brand.name.`). La UI de la app NO usa la paleta de marca: sigue con Tailwind `brand-*` (producto).
- **Calendario**: feriados globales (`brand_id=null`) compartidos por todas las marcas — solo lectura para operadores, CRUD para admin; los eventos de la marca llevan `brand_id` activo (el servidor lo decide, ignora el del cliente).
- **Crear una marca**: UI `/brands/create` (admin) o `DefaultsSeeder::forBrand($brand)` — siembra perfil vacío + estrategias por red; nunca duplica feriados globales.
- **Assets por marca**: `storage/app/brands/{brand_id}/posts/{post_id}/` (frames, bg.png, final.mp4). Migración del layout plano viejo: `sail artisan brands:migrate-assets` (idempotente; reescribe `posts.asset_path`).
- **Tests**: helpers `actingAsAdminFor(?Brand)` / `actingAsOperatorFor(?Brand)` en `TestCase` (setUp bindea Saberé; libera con `app()->forgetInstance('brand')`).

## Revisión del cliente (Fase 2 multimarca)

- Solo aplica a marcas con `requires_client_approval` (Saberé = false: `RenderPost` y `approve` con render fresco saltan `rendered → client_approved` directo). Grafo: `rendered → client_approved | drafted | approved`; `client_approved → scheduled | approved` (editar un post aprobado por el cliente lo devuelve a revisión tras re-render). Ya no existe `rendered → scheduled`.
- Tablas: `brand_contacts` (aprobadores de la marca, gestionados en `/brands/{id}/edit`), `review_requests` (rango, `token_hash` sha256, `expires_at` +14 d, `sent_to`, `submitted_at`, `revoked_at`, `general_comment`), `review_items` (decisión `pending|approved|rejected` por post, `week_from`), `review_week_comments`.
- Operador: `POST /b/{brand}/plans/{a}/{m}/reviews` (mes o `week_from`); excluye posts ya en una revisión abierta. `reviews.revoke` y `reviews.rotate` (nuevo token + reenvío). El token en claro solo viaja en `flash.review_url` una vez; en BD solo el hash. Cada solicitud encola `ReviewRequestMail` (Markdown) a los contactos.
- Portal público sin auth: `/review/{token}` + POSTs (`items/{post}`, `weeks/{w}/approve|comment`, `approve-all`, `submit`). `ResolveReviewToken` (alias `review.token`, **antes** de `SubstituteBindings`) resuelve el token vía `findByToken` (sin scope, fija `app('brand')`), 404 si no existe, `Review/Unavailable` si revocado/vencido, POST → 409 tras envío.
- `submit` aplica en transacción: aprobados → `client_approved`, rechazados → `drafted` (conserva assets/`rendered_at`), los que ya no están `rendered` se omiten; fija `submitted_at` + `general_comment` y dispara `event(new ReviewSubmitted)` (sin listeners — la memoria IA es fase posterior).
- Media del portal: `URL::temporarySignedRoute('assets.show', $review->expires_at, …)` — las URLs firmadas caducan con la revisión.
- Correo: `MAIL_FROM_*` global; en local `MAIL_MAILER=log` → el correo sale en `storage/logs/laravel.log` (lo procesa el worker `queue`).

## Planes y cuotas

- `plans`: `posts_per_month` / `regenerations_per_month` / `image_refreshes_per_month` (null = ilimitado, plan Interno), `networks` (redes permitidas) y `price_label`. Catálogo admin en `/plans` (`admin.plans.*`, sin borrado si tiene marcas). Cartera `/portfolio` (admin): consumo del mes por marca, costo IA (suma `llm_logs.cost_usd` del mes), última revisión/publicado y extensiones `POST /portfolio/{brand}/extensions` (reemplazan `extra_*`).
- `brand_usage` lleva contadores por marca y **mes del contenido** (no del calendario): `posts_generated`, `regenerations`, `image_refreshes`, `llm_tokens_in/out`, `image_calls`, `extra_*`. `Brand::usageFor($y,$m)` la crea al vuelo.
- `QuotaGuard` (`App\Services\Quota`): `limit/used/remaining/check/record/summary/allowsNetwork`. `check` lanza `QuotaExceededException` (ValidationException clave `quota` → 422 Inertia). `record` va en la misma transacción que persiste el resultado — un fallo no consume cupo.
- El periodo se fija con `app()->instance('usage_period', [$year, $month])` en los jobs; el `LlmLogObserver` suma tokens a ese periodo (fallback: mes del log). Los incrementos de `brand_usage` se hacen con `withoutGlobalScopes()->whereKey($id)` — `Model::increment` delega sin clave y el scope filtraría por la marca activa.
- Qué cuenta: posts persistidos por `GenerateMonthlyPlan` (recorte proporcional + `monthly_plans.quota_notice`), regeneraciones en `RegeneratePost`, y `image_refreshes`/`image_calls` solo cuando `BackgroundService` usa el generador IA (Pixabay/geométrico no). Redes fuera del plan no entran al prompt ni persisten; `NetworkStrategyController` las fuerza a `posts_per_week=0`.
- `config/llm_pricing.php`: USD por millón de tokens por modelo (`:free` → 0, desconocido → 0).
- Grilla: barra de uso en `Plans/Show` (oculta si ilimitado), banda ámbar con `quota_notice`, botones deshabilitados al agotarse. UI de planes/cartera solo para `admin`.

## Notas

- Locale `es`, timezone `America/Caracas`, faker `es_VE`.
- El producto se llama **Contenia** (`APP_NAME`); Saberé es solo la marca id=1. Paleta Tailwind del producto `brand.*` (`primary` índigo, `secondary`, `ink`, `tint`) — la UI usa `brand-*`, no `sabere-*`. Las plantillas de render (`Templates/`, `Pages/Render/`) usan la paleta de cada marca vía `--brand-*` y no cambian.
- Prompts por marca: `brand_profile.description` alimenta `PromptBuilder::brandIntro()` ("Eres el community manager de {marca}, {descripción}."); la regla de `photo_keywords` está centralizada en `PromptBuilder::photoKeywordsRule()` y es genérica (sector de la marca, no escolar).
- Alias `@/*` → `resources/js/*`.

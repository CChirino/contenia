# Plan Fase 1 (multi-marca) — Base: marcas, roles, scope, identidad por marca y migración

Spec: `docs/superpowers/specs/2026-10-13-plataforma-multimarca-design.md` §3–§5, §9, §11 (fase 1).

Decisiones tomadas con el usuario:
- Enfoque A: una base de datos, `brand_id` + global scope. Saberé = marca `id=1`.
- Roles `admin` / `operator` simples; operadores asignados por `brand_user`.
- Un calendario global de Venezuela (`calendar_events.brand_id = null`) + eventos propios por marca.
- Inter para todas las marcas; paleta y logo por marca.
- Las cuotas (`QuotaGuard`) NO se aplican en esta fase: solo se crea el catálogo `plans` mínimo y `brands.plan_id`.
- El portal del cliente, `client_approved` y `review_requests` van en la Fase 2. En esta fase el flujo de estados no cambia.

## Paso 1 — Modelo de datos y migración de Saberé

- Migración `create_brands_and_plans`:
  - `plans`: `name`, `posts_per_month` int nullable, `regenerations_per_month` int nullable, `image_refreshes_per_month` int nullable, `networks` json, `price_label` string nullable. Semilla en la propia migración: *Básico* (12/10/10, `[instagram, linkedin]`), *Pro* (30/40/40, las 3), *Interno* (null/null/null, las 3).
  - `brands`: `name`, `slug` único, `logo_path` nullable, `palette` json, `website_url` nullable, `status` enum `active|paused` default `active`, `plan_id` FK, `requires_client_approval` bool default true, `onboarding_proposal` json nullable.
  - Inserta Saberé: `slug=sabere`, `palette={dark:'#1A2A42', light:'#F8F5F0', accent:'#27C2A5', secondary:'#7854D2'}`, `logo_path=null` (se sirve el SVG del repo como fallback), plan Interno, `requires_client_approval=false`.
  - `brand_user` (`brand_id`, `user_id`, único compuesto).
  - `users`: `role` enum `admin|operator` default `operator`; `last_brand_id` nullable. El usuario existente → `admin`; se inserta `brand_user(1, admin)`.
- Migración `add_brand_id_to_domain_tables`: para `brand_profiles`, `network_strategies`, `content_pillars`, `monthly_plans`, `posts`, `llm_logs`: añadir `brand_id` FK **con default 1**, rellenar, quitar default (`->change()`), índice. Únicos: `monthly_plans (brand_id, year, month)` reemplaza `(year, month)`; `network_strategies (brand_id, network)`; `brand_profiles.brand_id` único. `calendar_events.brand_id` **nullable** (feriados existentes quedan `null`), índice `(brand_id, date)`.
- Enum PHP `UserRole`. Modelo `Brand` (casts `palette` array, `status` enum `BrandStatus`), relaciones `profile()`, `strategies()`, `pillars()`, `events()`, `plans()`, `posts()`, `users()`, `plan()`. Modelo `Plan`. Factories de `Brand`, `Plan`; `PostFactory`/`MonthlyPlanFactory`/etc. crean o reutilizan una marca.
- `DefaultsSeeder` pasa a sembrar por marca (`DefaultsSeeder::forBrand(Brand)`), usado por el seeder general para Saberé y por el controlador al crear una marca nueva (estrategias por red vacías con frecuencias 0 y perfil vacío; los feriados globales no se duplican).

## Paso 2 — Marca activa, scope y autorización

- Trait `BelongsToBrand` (`app/Models/Concerns`): `bootBelongsToBrand()` añade `BrandScope` (aplica `where brand_id = ` cuando `app()->bound('brand')`) y `creating` rellena `brand_id` si falta. Lo usan `BrandProfile`, `NetworkStrategy`, `ContentPillar`, `MonthlyPlan`, `Post`, `LlmLog`. `CalendarEvent` usa un scope propio: `where brand_id is null or brand_id = activa`.
- Middleware `SetActiveBrand`: resuelve `{brand}` por slug, autoriza `BrandPolicy::view` (admin, o `brand_user`), hace `app()->instance('brand', $brand)`, guarda `users.last_brand_id`. Registrado como alias `brand`.
- Rutas: todo lo que hoy cuelga de `auth` (plans, posts, settings) pasa a `Route::prefix('b/{brand:slug}')->middleware(['auth','brand'])`. Nombres de ruta se mantienen (`plans.show`, `posts.update`, `settings.brand.edit`…), solo ganan el parámetro `brand`; en Vue `route('plans.show', {brand: page.props.brand.slug, ...})` — se añade un helper `useBrandRoute()` que inyecta `brand` automáticamente para no tocar cada llamada.
- Redirecciones: `/` y `/dashboard` → marca `last_brand_id` o la primera asignada; `/plans/{y}/{m}` antiguo → `/b/sabere/plans/{y}/{m}` (301).
- Posts/planes en rutas: `Post` resuelto por route-model-binding **ya con scope aplicado** (post de otra marca → 404). `RenderFrameController`/`AssetController` (rutas firmadas, sin sesión): reciben `{brand}` en la ruta, hacen bind manual de la marca y verifican `post.brand_id === brand.id` (403 si no). `PlaywrightFrameRenderer` y `RenderPost` generan las URLs firmadas con `brand`.
- Jobs: ningún job depende de `app('brand')`. `GenerateMonthlyPlan`, `RegeneratePost`, `RenderPost`, `EnrichPhotosCommand` y `GeneratePlanCommand` reciben/deducen la marca del modelo y la fijan con `app()->instance('brand', $model->brand)` al inicio del `handle()` (para que `BrandProfile::current()`, pilares, estrategias y eventos queden filtrados). Comandos CLI ganan opción `--brand=slug` (default `sabere`).
- `BrandProfile::current()` → `Brand::current()->profile` (`Brand::current()` = `app('brand')`, lanza si no hay). `PromptBuilder` recibe la marca explícita en el constructor.
- Policies: `BrandPolicy` (`view`, `manage` = admin) y `UserPolicy` mínima. Rutas de administración (`/brands`, `/users`) solo admin (`can:manage,brand` o middleware `role:admin` propio).

## Paso 3 — Administración de marcas y usuarios (admin)

- `BrandController` (admin): `index` (`/brands`: tabla con nombre, plan, estado, operadores, nº posts del mes), `create/store` (nombre, slug auto, website, plan, paleta con 4 color pickers, logo upload SVG/PNG ≤1 MB a `storage/app/brands/{id}/logo.{ext}`, `requires_client_approval`), `edit/update`, `toggle` estado. Al crear: `DefaultsSeeder::forBrand`. Sin borrado (se pausa).
- `UserController` (admin): lista, crear operador (nombre, email, contraseña temporal), asignar marcas (checkboxes), cambiar rol. Sin registro público (igual que hoy).
- Nav: selector de marca (dropdown con logo + nombre; solo asignadas) a la izquierda de "Grilla"; menú "Administración" (Marcas, Usuarios) solo admin. `HandleInertiaRequests` comparte `brand` (id, slug, name, palette, logo_url) y `brands` (lista para el selector) y `auth.user.role`.
- Ruta `brands.logo` firmada-less pero pública solo-lectura: `/b/{brand}/logo` sirve `logo_path` o el SVG de Saberé si es null (Chromium la usa en el render).

## Paso 4 — Identidad por marca en los diseños

- `RenderFrameController` añade a las props: `palette` (4 colores) y `wordmark` (= `brand.name` + "."), `logoUrl = route('brands.logo', brand)`.
- `Frame.vue` → plantillas → `BrandFrame` reciben `palette`; `BrandFrame` setea en el contenedor `style="--brand-dark:…; --brand-light:…; --brand-accent:…; --brand-secondary:…"`. En las 5 plantillas y `BrandFrame`, reemplazar `bg-sabere-dark`, `text-sabere-light`, `bg-sabere-accent`, `bg-sabere-purple`, `text-sabere-light/85` etc. por clases arbitrarias Tailwind con variable: `bg-[var(--brand-dark)]`, `text-[var(--brand-light)]`, `text-[color:var(--brand-light)]/85` → usar `style` inline con `color-mix` o clases `opacity-85` donde haya alpha. `icons.ts` sin cambios. Texto "Saberé." del wordmark → `{{ wordmark }}`.
- Assets: `storage/app/brands/{brand_id}/posts/{post_id}/` en `RenderPost`, `BackgroundService`, `AssetController`, `PostController::download`, `FfmpegVideoAssembler` (vía un helper `Post::assetDir()` / `Post::assetPath($file)` que centraliza la ruta). Comando `brands:migrate-assets`: mueve `storage/app/assets/{post}` → nueva ruta, reescribe `posts.asset_path`/`frames` si guardan rutas relativas (verificar; si solo guardan nombres de archivo, no hace falta), idempotente.
- La UI de la app (layouts, botones) sigue con `sabere-*`: no se toca.

## Paso 5 — Configuración por marca y eventos globales

- Pantallas de Settings sin cambios funcionales, ahora bajo `/b/{brand}/settings/...` y leyendo por scope.
- Calendario: la lista separa "Feriados (globales)" (solo lectura para operador; editable para admin, `brand_id=null`) y "Eventos de la marca" (CRUD normal). `StoreCalendarEventRequest` ignora `brand_id` del cliente; el controlador decide: admin con flag `global=true` → null; si no → marca activa. `PromptBuilder`/`PlanValidator` ya consultan `CalendarEvent` con el scope mixto.
- `GeneratePlanCommand`, `EnrichPhotosCommand`, `RenderPostCommand`: opción `--brand=` (default `sabere`).

## Paso 6 — Tests

- `BrandScopeTest`: con marca activa A, `Post::all()` no devuelve posts de B; crear un post rellena `brand_id`; sin marca activa (contexto job) no filtra.
- `SetActiveBrandTest`: operador no asignado → 403; slug inexistente → 404; admin ve todas; `last_brand_id` se actualiza; `/plans/2026/10` redirige a `/b/sabere/...`.
- `CrossBrandAccessTest`: operador de A hace `PATCH /b/{A}/posts/{postDeB}` → 404; `GET /b/{A}/assets/{postDeB}/frame-01.png` firmado → 403; render frame cruzado → 403.
- `BrandAdminTest`: solo admin crea/edita marcas y usuarios; crear marca siembra defaults y no duplica feriados; subir logo guarda en la ruta de la marca.
- `CalendarEventScopeTest`: eventos globales visibles en todas las marcas; evento de A no visible en B; operador no puede editar globales.
- `RenderFrameControllerTest`: props traen `palette`/`wordmark`/`logoUrl` de la marca del post. Test `@render` real: post de una marca con paleta distinta → PNG; comprobar que el píxel del panel inferior es el `dark` de esa marca (muestreo con GD).
- Jobs: `GenerateMonthlyPlan`, `RegeneratePost`, `RenderPost` con dos marcas → usan perfil/pilares/estrategias de la marca del modelo (no de la "otra").
- `MigrateAssetsCommandTest`: mueve directorios y es idempotente.
- Actualizar los tests existentes a rutas con `brand` (helper en `TestCase`: `actingAsAdminFor(Brand)`).

## Paso 7 — Documentación y cierre

- `AGENTS.md`: marcas, selector, URLs `/b/{brand}`, roles, `--brand=` en comandos, ruta de assets nueva, cómo crear una marca.
- Verificación final: `sail artisan test`, `sail npm run build`, `sail pint`, `sail restart queue`; comprobar en el navegador: Saberé intacta (octubre con sus 45 posts y renders), crear marca de prueba "Demo" con otra paleta, generar una semana y renderizar un post → diseño con la paleta de Demo.

# Plan Fase 3 (multi-marca) — Planes, cuotas y Cartera

Spec: `docs/superpowers/specs/2026-10-13-plataforma-multimarca-design.md` §3.1 (`plans`, `brand_usage`, `llm_logs.cost_usd`), §7, §10 (cuotas), §11 (fase 3).

Decisiones tomadas:
- **Mes de cómputo = mes del contenido** (`monthly_plans.year/month` del post o del plan afectado), no el mes calendario de la acción. Así la barra de uso de la grilla de octubre siempre refleja lo consumido para octubre, y regenerar en noviembre un post de octubre consume cupo de octubre.
- `null` en un contador del plan = ilimitado (plan Interno). Con todo ilimitado la barra de uso no se muestra.
- La generación de la grilla **nunca falla por cuota**: recorta las frecuencias y deja un aviso. Solo da 422 si el cupo de posts ya está en 0 al pulsar el botón.
- Los contadores se incrementan **solo al éxito** y en la misma transacción que persiste el resultado.
- El catálogo `plans` es editable por el admin (sin borrado si hay marcas asociadas).

## Paso 1 — Datos y precios

- Migración `create_brand_usage_and_costs`:
  - `brand_usage`: `brand_id` FK, `year`, `month`, `posts_generated`, `regenerations`, `image_refreshes`, `llm_tokens_in`, `llm_tokens_out`, `image_calls` (todos unsigned int default 0), `extra_posts`, `extra_regenerations`, `extra_image_refreshes` (default 0), timestamps. Único `(brand_id, year, month)`.
  - `llm_logs.cost_usd` decimal(10,6) default 0.
  - `monthly_plans.quota_notice` string nullable (aviso de recorte).
  - Backfill: `cost_usd` de los logs existentes con `LlmPricing`; `brand_usage` del mes de octubre de Saberé a partir de lo existente: `posts_generated` = posts del plan, `llm_tokens_in/out` = suma de `llm_logs` por `brand_id` y mes de `created_at` (aproximación documentada; `regenerations`/`image_refreshes`/`image_calls` arrancan en 0).
- `config/llm_pricing.php`: `['models' => ['anthropic/claude-sonnet-4.5' => ['input' => 3.0, 'output' => 15.0], 'claude-sonnet-4-5' => [...], ...], 'default' => ['input' => 0, 'output' => 0]]` en USD por millón de tokens. Modelos `:free` → 0. Servicio `App\Services\Llm\LlmPricing::cost(string $model, int $in, int $out): float` (coincidencia exacta; si no, prefijo sin sufijo `:free`/`:nitro`; si no, default).
- `LlmLog`: en los tres clientes (`OpenRouterClient`, `AnthropicClient`, `FakeLlmClient`) rellenar `cost_usd` al crear el log. Un `LlmLogObserver::created` incrementa `brand_usage.llm_tokens_in/out` del mes de contenido: el `purpose`/contexto no trae el mes, así que el cliente recibe el mes vía `app()->bound('usage_period')` fijado por el job (`[year, month]`); si no está bindeado, se usa el mes de `created_at`.
- Modelo `BrandUsage` (`BelongsToBrand`), `Brand::usageFor(int $year, int $month): BrandUsage` (firstOrCreate). Factory.

## Paso 2 — QuotaGuard

- Enum `QuotaAction: string { PostsGenerated = 'posts_generated', Regenerations = 'regenerations', ImageRefreshes = 'image_refreshes' }` con `planColumn()` (`posts_per_month`…), `extraColumn()` (`extra_posts`…) y `label()` ("posts", "regeneraciones", "fondos nuevos").
- `App\Services\Quota\QuotaGuard`:
  - `limit(Brand, QuotaAction, year, month): ?int` = `plan.{col} + usage.extra_{col}` (null si el plan es ilimitado).
  - `used(...)`, `remaining(...): ?int`.
  - `check(Brand, QuotaAction, year, month, int $amount = 1): void` lanza `QuotaExceededException` (extiende `ValidationException` con clave `quota`, mensaje "Esta marca agotó los/las {limit} {label} del plan {plan} para {mes de año}"; HTTP 422).
  - `record(Brand, QuotaAction, year, month, int $amount = 1): void` — `increment` atómico; se llama dentro de la transacción del job.
  - `summary(Brand, year, month): array` → `[{action, used, limit, extra}]` para la UI.
  - `allowsNetwork(Brand, Network): bool` según `plan.networks`.
- Puntos de aplicación:
  - **Grilla** (`PlanController::generate`/`regenerateWeek`, `plan:generate`): si `remaining(PostsGenerated) === 0` → 422; si no, encola. En `GenerateMonthlyPlan`, por semana: `remaining` → si es `null` sin cambios; si es menor que la suma de posts previstos para la semana (`PromptBuilder` ya calcula `thisWeek` por red), recortar proporcionalmente por red (redondeo hacia abajo, mínimo 1 para la red de mayor peso mientras quede cupo) y pasar los cupos a `PromptBuilder` (nuevo parámetro `array $maxPerNetwork`); `PlanValidator` descarta los posts que excedan. Tras persistir los posts de la semana: `record(PostsGenerated, n)`. Si al llegar a una semana el restante es 0, se salta y se anota. Al final del job: `monthly_plans.quota_notice` = "Se generaron N posts de M previstos: el plan {plan} permite {limit} posts al mes" o `null`. También se excluyen del prompt las redes que `allowsNetwork` niega.
  - **Regenerar post** (`PostController::regenerate`, `RegeneratePost`): `check(Regenerations)` en el controlador y de nuevo en el job antes del LLM; `record` al persistir.
  - **Nuevo fondo** (`PostController::render` con `fresh_background=true`, `RenderPost`): `check(ImageRefreshes)` en el controlador y en el job; `record` al terminar el render con éxito. El render normal / "Generar diseño" no consume cupo.
  - **`image_calls`**: `BackgroundService::fromImageGenerator` incrementa `image_calls` al obtener imagen del generador (Pollinations); las fotos de Pixabay no cuentan.
- Comandos CLI respetan lo mismo (comparten los jobs).

## Paso 3 — UI de uso y configuración de redes

- `PlanController::show` añade prop `usage` = `QuotaGuard::summary()` (+ `plan.name`, `unlimited: bool`) y `plan.quota_notice`.
- `Show.vue`: debajo de la cabecera del mes, **barra de uso** compacta "Plan Pro · 18/30 posts · 7/40 regen · 3/40 fondos" con barras de progreso finas; ámbar ≥80 %, rojo al 100 %. Oculta si `unlimited`. Si hay `quota_notice`, banda ámbar con el texto y un enlace "Pedir extensión" que abre `mailto:` del admin **no** — en su lugar, simple texto "Pide una extensión al administrador" (sin flujo nuevo). Botones "Generar/Completar mes", "↻", "Regenerar" y "Nuevo fondo" deshabilitados con tooltip cuando el restante correspondiente es 0 (el servidor sigue siendo la autoridad: 422).
- `PostDrawer.vue`: muestra el error 422 de cuota en un aviso rojo (hoy los errores de validación del drawer ya se muestran; asegurar que `quota` se renderiza).
- `Settings/Networks.vue`: estrategia de una red **no habilitada por el plan** se muestra deshabilitada (inputs `disabled`, etiqueta "No incluida en el plan {plan}"); `UpdateNetworkStrategiesRequest` fuerza `posts_per_week=0` para esas redes. Prop `allowed_networks` desde el controlador.

## Paso 4 — Catálogo de planes y Cartera (admin)

- `Admin\PlanController` (`/plans`, middleware `admin`): `index` (tabla: nombre, posts/regen/fondos (∞ si null), redes, precio, nº marcas), `create/store`, `edit/update`, `destroy` solo si `brands_count = 0` (422 si no). Formulario: nombre, tres contadores (vacío = ilimitado), checkboxes de redes, `price_label`. Rutas `plans.*` — **ojo**: ya existen `plans.show/generate/status…` para la grilla bajo `/b/{brand}`; nombra las de admin `admin.plans.*` para no colisionar, y añade `admin.` a `useBrandRoute` como prefijo **excluido**.
- **Cartera** `/portfolio` (`portfolio.index`, admin): tabla de marcas activas y pausadas con: logo+nombre, plan, uso del mes en curso (posts/regen/fondos con `x/y`), costo IA del mes (`sum(llm_logs.cost_usd)` por `brand_id` y mes de `created_at`, en USD con 2 decimales), tokens, `image_calls`, última revisión del cliente (`estado · fecha · resumen` o "—"), último post publicado (`published_at` máx. o "—"), enlaces a la grilla del mes y a editar la marca. Selector de mes (año/mes) en la cabecera; por defecto el mes en curso.
- **Extensiones**: en cada fila de Cartera, botón "Extender" abre un modal con tres campos (`extra_posts`, `extra_regenerations`, `extra_image_refreshes`) para el mes seleccionado → `POST /portfolio/{brand}/extensions` (`portfolio.extend`), body `{year, month, extra_posts, extra_regenerations, extra_image_refreshes}` (enteros ≥0; **reemplaza** los valores, no suma). Solo admin.
- Nav: en "Administración" añadir **Cartera** y **Planes**.

## Paso 5 — Tests

- `LlmPricingTest`: exacto, prefijo sin `:free`, default 0; `cost_usd` se guarda en el log; observer incrementa tokens en `brand_usage` del periodo bindeado y, sin periodo, del mes de `created_at`.
- `QuotaGuardTest`: `limit/remaining` con plan limitado, con extras, ilimitado (`null`); `check` lanza al exceder con el mensaje esperado; `record` incrementa atómico; `allowsNetwork`.
- `GenerateMonthlyPlanQuotaTest`: plan con 5 posts/mes y estrategia de 6/semana → la primera semana genera ≤5 (prompt recibe `maxPerNetwork` recortado proporcionalmente), las siguientes se saltan, `quota_notice` informado, `posts_generated = 5`; plan Interno genera sin recorte y `quota_notice = null`; red no habilitada no aparece en el prompt ni en los posts; `generate` con restante 0 → 422; extensión permite continuar.
- `RegeneratePostQuotaTest` y `ImageRefreshQuotaTest`: 422 en el controlador al exceder; el job re-comprueba y aborta sin tocar el post si otro job agotó el cupo mientras tanto; contador solo tras éxito (un fallo del LLM/render no incrementa); el render normal no consume `image_refreshes`; `image_calls` solo cuenta cuando se usa el generador IA, no con Pixabay.
- `PlanAdminTest`: solo admin; crear/editar; `destroy` con marcas → 422; sin marcas → ok.
- `PortfolioTest`: solo admin; filas con uso, costo IA (suma correcta), última revisión y último publicado; `extend` reemplaza extras y recalcula `remaining`; selector de mes.
- `NetworkStrategyPlanTest`: `allowed_networks` en props; guardar `posts_per_week>0` en red no permitida se fuerza a 0.
- `PlanControllerTest`: props `usage` y `quota_notice`; `unlimited` para Saberé.

## Paso 6 — Documentación y cierre

- `AGENTS.md`: planes y cuotas (qué cuenta, cuándo, mes de contenido), `config/llm_pricing.php`, Cartera, extensiones, `quota_notice`.
- Verificación final: suite, build, Pint, restart queue, migración real (ver backfill de Saberé octubre y `cost_usd`). En navegador: Demo con plan **Básico**; grilla muestra la barra "0/12 posts…"; poner Demo en Pro, luego volver a Básico y comprobar que la barra cambia; Cartera con Saberé (∞) y Demo; extender Demo +5 posts y ver el nuevo límite en la grilla. Screenshots `/tmp/ui-usage-bar.png`, `/tmp/ui-portfolio.png`, `/tmp/ui-plans.png`.

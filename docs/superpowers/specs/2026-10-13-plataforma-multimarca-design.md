# Plataforma multi-marca (modo agencia) — Diseño

Fecha: 2026-10-13
Estado: borrador para revisión
Base: `2026-10-01-sabere-marketing-agent-design.md` (Fases 1 y 2 implementadas)

## 1. Objetivo

Convertir `sabere-marketing` en una plataforma donde **una agencia pequeña (1–3 operadores) gestiona el contenido de redes de varias marcas (3–10 en el primer año)**. Saberé pasa a ser la marca #1. El cliente dueño de cada marca **solo aprueba** (no edita ni configura) desde un portal sin contraseña. Los costos de IA son de la agencia, con **cuotas por plan** por marca. La IA además **propone la configuración inicial de cada marca** y **aprende del feedback** del cliente mes a mes.

## 2. Decisiones tomadas

| Tema | Decisión |
|---|---|
| Aislamiento | Una base de datos; `brand_id` en todas las tablas de dominio con global scope (enfoque A). No se usa tenancy por base de datos. |
| Usuarios de agencia | Roles `admin` y `operator`; operadores asignados a marcas (`brand_user`). Sin permisos finos. |
| Cliente | Sin cuenta. Contactos aprobadores por marca reciben enlaces firmados temporales. Aprueba todo / por semana / por post, con comentarios por post y por semana. |
| Costos | Claves únicas de la agencia (OpenRouter, Pollinations, Pixabay). Cuotas por plan y marca; extensiones puntuales por el admin. |
| Calendario | Un calendario global de Venezuela (feriados) + eventos propios por marca. Sin país por marca (se añadirá si aparece una marca extranjera). |
| IA nueva | Onboarding automático de marca y memoria de pautas por marca. Chat asistente y analíticas: fuera de alcance. |
| Tipografía | Inter para todas las marcas en v1. Paleta y logo sí son por marca. |
| Publicación | La Fase 3 original (APIs oficiales) se mantiene, ahora por marca. |

## 3. Modelo de datos

### 3.1 Nuevas tablas

| Tabla | Campos clave | Notas |
|---|---|---|
| `brands` | `name`, `slug` único, `logo_path`, `palette` json `{dark, light, accent, secondary}`, `website_url`, `status` (`active`/`paused`), `plan_id`, `requires_client_approval` bool, `onboarding_proposal` json nullable | Saberé = id 1, plan Interno, `requires_client_approval=false`. |
| `brand_user` | `brand_id`, `user_id` | Marcas asignadas a un operador. Los admin ven todas. |
| `brand_contacts` | `brand_id`, `name`, `email`, `whatsapp` nullable | Aprobadores del cliente. |
| `review_requests` | `brand_id`, `from_date`, `to_date`, `token` (hash), `expires_at`, `sent_to` json, `submitted_at`, `revoked_at`, `general_comment` | Una revisión enviada al cliente. |
| `review_items` | `review_request_id`, `post_id`, `decision` (`pending`/`approved`/`rejected`), `comment`, `week_from` | Un ítem por post incluido. |
| `review_week_comments` | `review_request_id`, `week_from`, `comment` | Comentario general por semana. |
| `plans` | `name`, `posts_per_month`, `regenerations_per_month`, `image_refreshes_per_month`, `networks` json, `price_label` | `null` en un contador = ilimitado. |
| `brand_usage` | `brand_id`, `year`, `month`, `posts_generated`, `regenerations`, `image_refreshes`, `llm_tokens_in`, `llm_tokens_out`, `image_calls`, `extra_posts`, `extra_regenerations`, `extra_image_refreshes` | Única por `(brand_id, year, month)`. |
| `brand_guidelines` | `brand_id`, `text` (≤200), `source` (`client_feedback`/`operator`/`ai_distilled`), `weight` int, `evidence_count` int, `status` (`pending`/`active`/`pending_retire`/`retired`) | Memoria de la marca. |
| `post_edits` | `post_id`, `user_id`, `field` (`hook`/`copy`), `before`, `after` | Evidencia para la memoria (§8.2). |
| `calendar_events.brand_id` | nullable | `null` = evento global (feriados). |

### 3.2 Tablas existentes

Ganan `brand_id` (indexado, obligatorio): `brand_profile` (deja de ser singleton; 1:1 con `brands`), `network_strategies`, `content_pillars`, `monthly_plans`, `posts`, `llm_logs`, y en Fase 3 `social_accounts`. La unicidad de `monthly_plans` pasa a `(brand_id, year, month)`. `llm_logs` añade `cost_usd` decimal estimado.

`users` gana `role` enum (`admin`/`operator`) y `last_brand_id` nullable.

### 3.3 Estados de `posts`

`drafted → approved → rendered → client_approved → scheduled → published | failed`.

- `rendered → client_approved`: lo hace el portal del cliente, o automáticamente al pasar a `rendered` si `requires_client_approval=false`.
- Rechazo del cliente: `rendered → drafted`, conservando assets y guardando el comentario en `review_items`.
- `client_approved → scheduled` lo hace el operador ("Programar"), como antes hacía `rendered → scheduled`.

## 4. Marca activa y aislamiento

- URLs con la marca en la ruta: `/b/{brand:slug}/plans/{year}/{month}`, `/b/{brand}/settings/...`. Middleware `SetActiveBrand` resuelve la marca, verifica acceso (`BrandPolicy::view`: admin o asignado) y la deja en el contenedor (`app('brand')`).
- Trait `BelongsToBrand`: global scope `where brand_id = app('brand')->id` cuando hay marca activa; rellena `brand_id` al crear. Los jobs no dependen de la sesión: reciben el modelo y el scope se desactiva en contexto de cola (`withoutGlobalScope`) usando el `brand_id` del modelo.
- Selector de marca en el nav (solo las asignadas). Última marca usada se guarda en `users.last_brand_id` para redirigir al entrar.
- Rutas firmadas de render y assets incluyen `brand` y verifican que el post pertenezca a esa marca.
- Assets: `storage/app/brands/{brand_id}/posts/{post_id}/...`. Comando `brands:migrate-assets` mueve los actuales.

## 5. Identidad por marca en los diseños

- `RenderFrameController` envía `palette`, `logoUrl` y `wordmark` (= `brands.name`) de la marca del post.
- `BrandFrame` define variables CSS (`--brand-dark`, `--brand-light`, `--brand-accent`, `--brand-secondary`) en el contenedor; las plantillas usan esas variables en lugar de las clases `sabere-*`. El layout M1 no cambia.
- La UI de la app (no los diseños) conserva la identidad Saberé.
- Logo: subida manual (SVG o PNG con transparencia) en la configuración de marca; se sirve por ruta firmada `brands.logo`.

## 6. Roles y portal del cliente

### 6.1 Agencia

- `admin`: marcas, planes, usuarios, contactos, extensiones de cuota, conexión de redes, todo lo del operador.
- `operator`: opera las marcas asignadas: configuración de marca, grilla, aprobar/editar/regenerar/renderizar, enviar a revisión, programar.

### 6.2 Flujo de revisión

1. En la grilla, **"Enviar a aprobación"** (mes completo o una semana) crea una `review_request` con los posts en `rendered` del rango. Token aleatorio de 40 chars; se guarda su hash; expira en 14 días; se envía por correo a los contactos (plantilla Markdown de Laravel) y el operador puede copiar el enlace para WhatsApp.
2. `/review/{token}` (sin login): cabecera con logo/nombre de la marca y rango; posts agrupados por semana; cada tarjeta muestra diseño (imagen, carrusel deslizable o video), red, fecha, copy y hashtags. Acciones: **Aprobar todo**, **Aprobar semana**, por post **Aprobar** / **Rechazar** con comentario obligatorio, y un **comentario general por semana**. Botón final **"Enviar revisión"** fija `submitted_at`; después el enlace es de solo lectura.
3. Al enviar: aprobados → `client_approved`; rechazados → `drafted` con comentario; se encola `DistillFeedback` (§8.2).
4. Grilla del operador: banda de estado "Revisión enviada el 12/10 · 18 aprobados · 3 rechazados · 2 pendientes", comentarios visibles en el drawer del post y en el encabezado de la semana. Puede **reenviar solo los corregidos** (nueva `review_request` con los posts re-renderizados).
5. Revocar: el operador puede invalidar un enlace (`revoked_at`).

Marcas con `requires_client_approval=false` no muestran "Enviar a aprobación"; sus posts pasan a `client_approved` al renderizar.

## 7. Planes y cuotas

- Catálogo `plans` editable por el admin. Semilla: *Básico* (12 posts / 10 regeneraciones / 10 fondos / 2 redes), *Pro* (30 / 40 / 40 / 3 redes), *Interno* (ilimitado).
- `QuotaGuard::check(Brand $brand, QuotaAction $action, int $amount = 1): void` lanza `QuotaExceededException` (→ 422 con mensaje "Esta marca agotó las 10 regeneraciones del plan Básico este mes"). Se invoca en los controladores antes de encolar y en los jobs antes de llamar a la IA.
- Acciones medidas: `posts_generated` (grilla mensual/semanal y comando CLI), `regenerations` (regenerar post), `image_refreshes` ("Nuevo fondo"). Los contadores se incrementan **al terminar con éxito**, en la misma transacción que persiste el resultado.
- La generación de la grilla, si la frecuencia semanal de la estrategia excede lo que queda del cupo, **recorta proporcionalmente** las frecuencias por red y lo informa en el panel de la grilla en lugar de fallar.
- Redes: el plan define qué redes puede usar la marca; la estrategia de una red no habilitada se muestra deshabilitada.
- Extensiones: el admin suma `extra_*` para el mes en curso desde la vista Cartera.
- `llm_logs.cost_usd` se calcula con una tabla `config/llm_pricing.php` (precio por millón de tokens por modelo; desconocido = 0). `image_calls` cuenta llamadas a Pollinations.
- UI: barra de uso en la cabecera de la marca ("18/30 posts · 7/40 regen · 3/40 fondos"); vista **Cartera** (`/portfolio`, admin): tabla de marcas con plan, uso del mes, costo IA estimado, estado de la última revisión y último post publicado.

## 8. IA

### 8.1 Onboarding de marca (`OnboardBrand`)

Entrada (formulario de nueva marca): `website_url` obligatoria para proponer; opcional: usuario de Instagram/LinkedIn y hasta 5 textos de posts previos que gusten.

1. **Recolección determinista** (`BrandScraper`): descarga la home y hasta 5 enlaces internos (prioriza rutas que contengan about/nosotros/servicios/blog), extrae texto visible, `<title>`, meta description y los colores más frecuentes en CSS inline/`<style>` y en el logo si se detecta `<img>` con "logo" en src/alt (análisis con GD). Timeout total 30 s; si falla, continúa solo con lo pegado.
2. **Propuesta LLM** (tool `emit_result`, esquema estricto): perfil de marca (descripción, audiencia, tono, CTA, hashtags base, URL), 4–6 pilares con pesos que suman 100, estrategia por red (frecuencia semanal, formatos permitidos, enfoque) respetando las redes del plan, y paleta `{dark, light, accent, secondary}` elegida entre los colores detectados (o propuesta si no hay).
3. **Entrega como borrador**: se guarda en `brands.onboarding_proposal` json. La pantalla de configuración muestra banner "Propuesto por IA — revisa y guarda" con los formularios precargados; nada se persiste en las tablas reales hasta que el operador guarda cada sección. El logo se sube a mano.

### 8.2 Memoria por marca (`brand_guidelines` + `DistillFeedback`)

- Fuentes de evidencia: comentarios de rechazo por post, comentarios por semana, y **ediciones del operador** a `hook`/`copy` (se guarda diff antes/después en `post_edits`: `post_id`, `field`, `before`, `after`, `user_id`).
- `DistillFeedback(brand, from, to)` se encola al cerrar una revisión y también puede lanzarse a mano. Pasa al LLM las pautas existentes (activas y pendientes) y la evidencia del periodo, y pide: pautas nuevas (texto ≤200 chars, concretas y accionables), pautas existentes reforzadas (ids) y pautas a retirar (ids, con motivo). Nuevas → `pending`; reforzadas → `evidence_count++`, `weight++`; retirar → `pending_retire` que el operador confirma.
- UI: pestaña **Pautas** en la configuración de marca: lista con estado, fuente, evidencia, acciones ✓ activar / ✗ descartar / editar texto; el operador también puede escribir pautas a mano (`source=operator`, activas de inmediato).
- `PromptBuilder` añade la sección "Pautas aprendidas de esta marca" con hasta 15 pautas activas ordenadas por `weight` desc, en: generación mensual/semanal, regeneración de post, y `posts:enrich-photos`.

## 9. Migración de lo actual

Una migración única, ejecutada en orden:

1. Crear `brands`, `plans` (semilla Básico/Pro/Interno) y la marca Saberé (`id=1`, paleta y logo actuales, plan Interno, `requires_client_approval=false`).
2. Añadir `brand_id` con `default 1` a las tablas existentes, rellenar, quitar el default y añadir índices/únicos nuevos.
3. `users.role = admin` para el usuario existente; `brand_user` (admin ↔ Saberé) por conveniencia del selector.
4. `calendar_events`: los feriados precargados pasan a `brand_id = null` (globales).
5. Posts en `rendered` de Saberé pasan a `client_approved` (la marca no requiere aprobación de cliente).
6. `brands:migrate-assets` mueve `storage/app/assets/{post}` → `storage/app/brands/1/posts/{post}` y actualiza `asset_urls`.

La app debe seguir funcionando igual para Saberé al terminar; la URL antigua `/plans/...` redirige a `/b/sabere/plans/...`.

## 10. Pruebas

PHPUnit con SQLite, `FakeLlmClient`, `FakePhotoSource`, `FakeGenerator`, `FakeVideoAssembler`.

- **Aislamiento**: operador asignado a A no lista, ve, edita ni renderiza posts/planes/configuración de B (incluye rutas firmadas de assets y render con marca cruzada → 403/404). Jobs usan el `brand_id` del modelo sin sesión.
- **Portal**: token válido/expirado/revocado/enviado; aprobar todo, semana y post; rechazo exige comentario; transición de estados correcta; comentario por semana persistido; `DistillFeedback` encolado al enviar; marca sin aprobación de cliente salta el paso.
- **Cuotas**: bloqueo al exceder (controlador y job), extensión puntual, recorte proporcional de frecuencias, contadores solo al éxito, plan Interno ilimitado, red no habilitada rechazada.
- **IA**: onboarding con web falsa (`Http::fake`) produce propuesta válida (pesos = 100, redes del plan); fallo de scraping no aborta; `DistillFeedback` crea pendientes y refuerza existentes; `PromptBuilder` incluye solo activas y máximo 15.
- **Diseños**: props de render traen paleta/logo de la marca; un test `@render` real con paleta distinta a Saberé.
- **Migración**: test que parte de datos tipo Fase 2 (sin `brand_id`) y verifica el resultado del §9.

## 11. Fases de entrega

1. **Multi-marca base** — `brands`, `plans` mínimo (sin aplicar cuotas aún), roles, `brand_user`, scope, selector, URLs `/b/{brand}`, paleta/logo por marca en diseños, eventos globales vs. de marca, migración de Saberé y assets. *Ya puedes crear la segunda marca.*
2. **Portal del cliente** — contactos, `review_requests`, página `/review/{token}`, aprobación total/semana/post, comentarios, estado `client_approved`, correo, `post_edits`.
3. **Planes y cuotas** — `QuotaGuard`, `brand_usage`, recorte de frecuencias, extensiones, barra de uso, Cartera, `cost_usd`.
4. **IA: onboarding y memoria** — `BrandScraper`, `OnboardBrand`, `brand_guidelines`, `DistillFeedback`, pestaña Pautas, bloque en prompts.
5. **Publicación por marca** — Fase 3 original con `social_accounts.brand_id`; depende de los trámites de apps/API.

## 12. Fuera de alcance

Chat asistente por marca, analíticas de rendimiento, país/calendario por marca, white-label, facturación y pagos dentro de la app, tipografías por marca, más de un idioma, edición de contenido por parte del cliente.

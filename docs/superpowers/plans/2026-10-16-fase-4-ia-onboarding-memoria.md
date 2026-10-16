# Plan Fase 4 (multi-marca) — IA: onboarding de marca y memoria de feedback

Spec: `docs/superpowers/specs/2026-10-13-plataforma-multimarca-design.md` §3.1 (`brand_guidelines`, `post_edits`, `brands.onboarding_proposal`), §8, §10 (IA), §11 (fase 4).

Decisiones:
- El onboarding es **borrador, nunca escritura directa**: la propuesta vive en `brands.onboarding_proposal` y solo llega a las tablas reales cuando el operador pulsa "Aplicar" por sección (o una a una). Así una propuesta mala nunca pisa datos a mano.
- La memoria **la propone la IA y la confirma un humano**: pautas nuevas entran `pending`; retiradas entran `pending_retire`; solo las `active` llegan al prompt.
- `BrandScraper` es determinista (HTTP + DOMDocument + GD); el LLM solo razona sobre lo recolectado.

## Paso 1 — Datos, scraper y job `OnboardBrand`

- Migración `create_onboarding_and_guidelines`:
  - `brands.onboarding_proposal` json nullable + `brands.onboarded_at` timestamp nullable.
  - `brand_guidelines`: `brand_id` FK, `text` string(200), `source` enum(`ai_distilled`,`client_feedback`,`operator`) (según spec §3.1), `status` enum(`pending`,`active`,`pending_retire`,`retired`,`discarded`) default `pending` (`discarded` la añadimos: §3.1 no la lista pero §8.2 contempla "✗ descartar"), `weight` smallint default 1, `evidence_count` int default 1, `period_from`/`period_to` dates nullable, timestamps. Índice `(brand_id, status)`.
  - `post_edits`: `post_id` FK, `user_id` FK, `field` string(10) (`hook`/`copy`, según spec §3.1), `before`/`after` text, `review_request_id` FK nullable (pertenencia a un periodo), timestamps.
- `App\Services\Onboarding\BrandScraper`:
  - `collect(string $url): array` → `{title, meta_description, pages: [{url, text}] (≤5, ≤8 000 chars cada una), colors: [hex,…], logo_img_url}`.
  - `Http::timeout(10)->get($url)` a la home; DOMDocument/`loadHTML` suprimiendo errores; elimina `script/style/noscript`; recoge `title`, `meta[name=description]`, enlaces internos del mismo host (prioriza rutas con `about|nosotros|servicios|blog|productos|equipo`), hasta 5, una petición cada una con timeout 10 (presupuesto total 30 s). Texto visible: `textContent` colapsado.
  - Colores: regex hex `#?([0-9a-f]{6})` sobre `<style>` + atributos `style=` + `bgcolor`; filtra blanco/negro/grises casi puros (sat-baja) y queda con el top 6 por frecuencia. Si hay `<img>` con `logo` en src/alt (mismo host), lo descarga y le saca los 3 colores dominantes con GD (muestreo cada 4 px, ignora píxeles con <20% saturación o >90% luz).
  - Cualquier excepción HTTP/parseo → sigue con lo que tenga; `ok => false` + `error` en el resultado si ni la home respondió.
- `App\Jobs\OnboardBrand(Brand $brand, array $samples)` (samples: `{social_handles?: string, posts: string[]}` del formulario):
  - `app()->instance('brand', $brand)` al inicio. `BrandScraper::collect($brand->website_url)`; si `ok=false` y no hay `samples` → `onboarding_proposal = ['error' => …]` y termina (la UI muestra "no se pudo leer la web, rellena a mano").
  - Prompt (vía `LlmClient` + `emit_result`, esquema estricto): del texto + título + meta + colores + samples propone: `description` (≤300 chars), `tone_of_voice`, `audience`, `cta`, `base_hashtags` (≤8, con #), `pillars` 4–6 `{name, description, weight}` sumando 100 (el LLM elige los pesos; validar y renormalizar si suma ≠ 100), `strategies` por cada red **del plan de la marca** `{network, posts_per_week 1–7, formats:[…], focus}`, `palette` `{dark, light, accent, secondary}` eligiendo entre los colores detectados o proponiendo si no hay (contraste razonable; el prompt lo exige).
  - Persiste todo en `brands.onboarding_proposal` (json) con `generated_at`; **no toca** `brand_profile`, pilares ni estrategias.
- `BrandsController` (admin): al crear/editar, botón "Proponer con IA" (solo si `website_url` presente; en edición reutiliza los posts de muestra que el usuario pegue en un textarea opcional del formulario, `sample_posts` text ≤5 000, dividido por líneas en blanco; `social_handle` text). Encola el job, redirige con flash "Generando propuesta… (tarda ~1 min)". Flag `onboarding_pending` en el `edit` para que la UI haga polling a `brands.show` (o la misma `edit` con `only:['brand']`).

## Paso 2 — UI: aplicar la propuesta por secciones

- En `Admin\Brands\Form.vue` (o una pestaña "Propuesta IA" en el `edit`): si `onboarding_proposal` existe y no está `error`, banner ámbar "Propuesta generada el {fecha} — revisa y aplica" y 4 tarjetas precargadas:
  - **Perfil** (description, tone, audience, cta, hashtags): textarea/inputs editables, botón "Aplicar a la marca" → `POST /brands/{brand}/onboarding/apply/profile` (escribe en `brand_profile`, marca `onboarding_proposal.applied.profile=true`).
  - **Pilares** (tabla nombre/descripción/peso editable, suma validada a 100 en cliente y servidor): "Aplicar pilares" → `…/apply/pillars` (reemplaza los pilares actuales **solo si la marca no tiene** o tras confirmar en un `confirm()`; si tiene, añade los que no existan por nombre y ajusta pesos).
  - **Estrategias** (tabla por red editable: frecuencia, formatos, foco; respeta `plan.networks` — redes fuera se muestran deshabilitadas): "Aplicar estrategias" → `…/apply/strategies`.
  - **Paleta** (4 swatches editables con `input[type=color]` + hex): "Aplicar paleta" → actualiza `brands.palette`.
- Cuando las 4 secciones están aplicadas → `onboarded_at` y el banner pasa a "Marca configurada con ayuda de IA ✓".
- Rutas `brands.onboarding.*` bajo `admin`. Política: solo admin.

## Paso 3 — `post_edits` y disparo de `DistillFeedback`

- En `PostController::update`, antes de guardar: para `hook`/`copy` cambiados por el operador, inserta en `post_edits` (`before`/`after` truncados a 1 000 chars; `review_request_id` = la revisión abierta/más reciente que incluya el post, si la hay).
- Listener `OnReviewSubmitted` sobre el evento `ReviewSubmitted` (ya existente, §5 de Fase 2): encola `DistillFeedback($brand, $request->from, $request->to)`.
- `App\Jobs\DistillFeedback(Brand $brand, Carbon $from, Carbon $to)`:
  - Reúne evidencia del periodo: comentarios de rechazo de `review_items`, comentarios de `review_week_comments`, `general_comment` de las solicitudes, y `post_edits` con `review_request_id` en el rango (o `created_at` en el rango).
  - Si la evidencia total está vacía → no llama al LLM, termina.
  - Prompt: pautas existentes (id, texto, status, weight, evidence_count) + evidencia → pide `{new: [{text ≤200}], reinforce: [ids], retire: [{id, reason}]}` via `emit_result`. Solo ids de la marca.
  - Aplica: nuevas → `pending` con `source=ai_distilled` (o `client_feedback` si el motivo son solo comentarios del cliente); `reinforce` → `evidence_count++`, `weight = min(weight+1, 10)`, solo si `active|pending`; `retire` → si `active` → `pending_retire`, si `pending` → `discarded`.
- `POST /brands/{brand}/guidelines/distill` (admin) lanza el job a mano con el último mes.

## Paso 4 — Pautas en UI y en los prompts

- Nueva pestaña **Pautas** en `/b/{brand}/settings` (`Settings/Guidelines.vue`, ruta `settings.guidelines.index`): lista agrupada por estado con chip de fuente (IA / Cliente / Operador) y contador de evidencias; pendientes con ✓ Activar / ✗ Descartar; `pending_retire` con "Retirar propuesta" / "Mantener"; activas con editar texto y "Retirar"; "+ Añadir pauta" (`source=operator`, `active` directo, ≤200 chars). Controlador `Settings\GuidelineController` (`index`, `store`, `activate`, `discard`, `retire`, `update`, `destroy` solo si `pending|discarded`).
- `PromptBuilder`: método `learnedGuidelines(): string` — hasta 15 `brand_guidelines` `active` ordenadas por `weight desc, updated_at desc`, como bloque "Pautas aprendidas de esta marca:\n- …". Inyectarlo en `systemPrompt()` (cubre generación, regeneración y, vía el mismo system, `enrich-photos` si su llamada usa el builder; si `EnrichPhotosCommand` construye su propio system, inyectarlo allí también).
- `PlanValidator` no cambia.

## Paso 5 — Tests y verificación

- `BrandScraperTest`: `Http::fake` con HTML fixture (title, meta, enlaces a `/nosotros`, colores en `<style>`, img de logo en data URI) → `collect` devuelve páginas priorizadas, colores hex, texto sin etiquetas; web caída → `ok=false` sin excepción; URLs externas no se siguen; budget de 5 páginas.
- `OnboardBrandTest` (`FakeLlmClient`): produce propuesta con 4–6 pilares sumando 100 (validación/renormalización), solo redes del plan, paleta hex válida, guarda en `onboarding_proposal` **sin tocar** `brand_profile`; error de scraping + sin samples → propuesta `{error}`; `OnboardBrand` fija `app('brand')` (test con marca no activa).
- `ApplyProposalTest`: cada endpoint aplica su sección, marca `applied`, y no pisa pilares existentes sin confirmar; redes fuera del plan se ignoran; solo admin.
- `PostEditsTest`: editar hook o copy crea filas con diff; regenerar por IA no crea `post_edits` (solo `update` del controlador).
- `DistillFeedbackTest`: al enviar una revisión con rechazos+comentarios se encola el job; el job con `FakeLlmClient` crea `pending`, refuerza una activa (`evidence_count++`), marca `pending_retire`; evidencia vacía → no llama al LLM; pauta retirada pendiente → descartada; no toca pautas de otra marca.
- `GuidelinesTest` (UI/controlador): CRUD, transiciones de estado válidas, ≤200 chars, aislamiento por marca.
- `PromptBuilderTest`: system prompt incluye activas (máx. 15, por weight), excluye pending/discarded/retired.
- `sail artisan test`, `npm run build`, `pint`, `sail restart queue`, capturas del formulario de onboarding y de Pautas (claro/oscuro).

## Fuera de esta fase

Chat asistente, aprendizaje desde métricas de rendimiento, soporte multilingüe del portal, propuesta de horarios óptimos.

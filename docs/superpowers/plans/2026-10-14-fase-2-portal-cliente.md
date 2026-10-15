# Plan Fase 2 (multi-marca) — Portal de aprobación del cliente

Spec: `docs/superpowers/specs/2026-10-13-plataforma-multimarca-design.md` §3.1 (`brand_contacts`, `review_requests`, `review_items`, `review_week_comments`), §3.3, §6.2, §10 (portal), §11 (fase 2).

Decisiones tomadas con el usuario:
- El cliente **no tiene cuenta**: entra por enlace con token (`/review/{token}`), sin login.
- Tres niveles de aprobación: **todo**, **semana**, **post**; comentario obligatorio al rechazar un post y comentario general opcional por semana.
- Marcas con `requires_client_approval=false` (Saberé) saltan el paso: `rendered → client_approved` automático.
- `DistillFeedback` (memoria IA) es de la Fase 4: en esta fase solo se dispara un evento `ReviewSubmitted` sin listeners.
- Cuotas y programación/publicación quedan fuera (Fases 3 y 5).

## Paso 1 — Estados y modelo de datos

- `PostStatus`: añadir `ClientApproved = 'client_approved'`. Transiciones nuevas: `Rendered → ClientApproved`, `Rendered → Drafted` (rechazo del cliente), `ClientApproved → Scheduled`, `ClientApproved → Approved` (el operador edita un post ya aprobado por el cliente → se re-renderiza y vuelve a revisión). Se **elimina** `Rendered → Scheduled`.
- Migración `create_review_tables`:
  - `brand_contacts`: `brand_id` FK, `name`, `email`, `whatsapp` nullable, timestamps. Índice `(brand_id)`.
  - `review_requests`: `brand_id` FK, `from_date`, `to_date`, `token_hash` string(64) único, `expires_at`, `sent_to` json (emails), `submitted_at` nullable, `revoked_at` nullable, `general_comment` text nullable, `created_by` FK users nullable, timestamps.
  - `review_items`: `review_request_id` FK cascade, `post_id` FK, `decision` enum `pending|approved|rejected` default `pending`, `comment` text nullable, `week_from` date, `decided_at` nullable. Único `(review_request_id, post_id)`.
  - `review_week_comments`: `review_request_id` FK cascade, `week_from` date, `comment` text. Único `(review_request_id, week_from)`.
  - Datos: `UPDATE posts SET status='client_approved' WHERE status='rendered' AND brand_id IN (brands con requires_client_approval=false)` — Saberé conserva sus renders y sus posts quedan listos para programar.
- Modelos: `BrandContact` (`BelongsToBrand`), `ReviewRequest` (`BelongsToBrand`; `items()`, `weekComments()`, `brand()`; scopes `open()` = sin `submitted_at`, sin `revoked_at`, `expires_at > now`; helpers `isOpen()`, `summary()` → `{approved, rejected, pending}`), `ReviewItem` (`post()`, enum `ReviewDecision`), `ReviewWeekComment`. Factories de los cuatro. `Brand::contacts()`, `Brand::reviewRequests()`, `Post::reviewItems()`.
- Token: `ReviewRequest::generateToken()` devuelve 40 chars aleatorios (`Str::random(40)`) y guarda `hash('sha256', $token)` en `token_hash`; `ReviewRequest::findByToken(string)` busca por hash **sin** global scope de marca (ruta pública) y fija `app()->instance('brand', $request->brand)` para que el resto consulte con scope.

## Paso 2 — Contactos aprobadores (admin)

- En `Admin/Brands/Form.vue` (edición), sección **"Contactos aprobadores"**: lista con nombre, email, WhatsApp y botones añadir/eliminar inline. Rutas bajo el grupo `admin`: `POST /brands/{brand}/contacts` (`brands.contacts.store`), `DELETE /brands/{brand}/contacts/{contact}` (`brands.contacts.destroy`); `Admin\BrandContactController`. El contacto debe pertenecer a la marca de la URL (404 si no). Validación: `email` único por marca.
- `requires_client_approval` ya existe en el formulario; si está activo y la marca no tiene contactos, mostrar aviso (no bloquea).

## Paso 3 — Enviar a aprobación (operador)

- `ReviewRequestController` bajo `/b/{brand}` (`auth`, `brand`):
  - `POST /b/{brand}/plans/{year}/{month}/reviews` (`reviews.store`), body `{ week_from?: 'Y-m-d' }`. Sin `week_from` → mes completo. Incluye los posts en `rendered` del rango **que no estén en otra `review_request` abierta**; si no hay ninguno → 422 "No hay diseños listos para revisar". Crea la solicitud (`expires_at = now()+14 días`, `sent_to` = emails de los contactos, `created_by` = usuario), encola `ReviewRequestMail` a cada contacto (`Mail::to(...)->queue(...)`), y devuelve redirect con `flash.review_url` = URL con el token en claro (**solo se muestra una vez**; el token no se guarda en claro). Marca con `requires_client_approval=false` → 422.
  - `POST /b/{brand}/reviews/{review}/revoke` (`reviews.revoke`) → `revoked_at = now()`.
  - `POST /b/{brand}/reviews/{review}/rotate` (`reviews.rotate`) → nuevo token (mismo registro), nuevo `expires_at`, reenvía el correo, devuelve `flash.review_url` nuevo. Solo si la solicitud está abierta.
- `ReviewRequestMail` (Markdown, `resources/views/mail/review-request.blade.php`): nombre de la marca, rango, nº de posts, botón "Revisar contenido", nota de expiración. Remitente global de `.env` (`MAIL_FROM_*`); en desarrollo `MAIL_MAILER=log`.
- `PlanController::show` añade props `reviews`: solicitudes del mes (abiertas o enviadas en los últimos 60 días) con `{id, from_date, to_date, status: open|submitted|revoked|expired, expires_at, submitted_at, summary, general_comment, week_comments: [{week_from, comment}]}`, y a cada post `review: {decision, comment} | null` (del ítem más reciente). Prop `brand.requires_client_approval` ya viaja en `brand`.
- UI `Show.vue`:
  - Botón **"Enviar a aprobación"** en la cabecera (mes) y en el menú ↻ de cada semana (semana). Oculto si `requires_client_approval=false`. Deshabilitado con tooltip si no hay posts `rendered` elegibles.
  - Tras crear/rotar: modal con el enlace, botón "Copiar enlace" (para WhatsApp) y aviso "El cliente también lo recibió por correo. Este enlace no se vuelve a mostrar; puedes generar uno nuevo".
  - **Banda de estado** por solicitud: "Revisión enviada el 12/10 · 18 aprobados · 3 rechazados · 2 pendientes · vence el 26/10" con acciones *Nuevo enlace* y *Revocar* (si abierta) o *Enviada el …* (si `submitted_at`). Comentario general del cliente debajo.
  - En la cabecera de la semana: comentario semanal del cliente, si existe. En las tarjetas: punto de estado para `client_approved` (verde) y marca de "rechazado por el cliente" (rojo) cuando el post está en `drafted` con `review.decision=rejected`.
  - `PostDrawer.vue`: bloque "Comentario del cliente" (si hay) encima del formulario; estado `client_approved` en la etiqueta.
- `RenderPost`: al pasar a `Rendered`, si `!$post->brand->requires_client_approval` → `ClientApproved` directamente. `PostController::approve` con render fresco: ídem.
- `PostController::update` sobre un post `client_approved` → `Approved` (re-render), igual que hoy con `rendered`.

## Paso 4 — Portal público `/review/{token}`

- Rutas públicas, middleware `throttle:60,1`, **sin** `auth` ni `brand` (la marca se fija desde el token):
  - `GET /review/{token}` → `ReviewPortalController::show` (`review.show`).
  - `POST /review/{token}/items/{post}` (`review.item`) body `{decision: approved|rejected, comment?}`; `rejected` exige `comment` (422). Guarda `decided_at`.
  - `POST /review/{token}/weeks/{week_from}/approve` (`review.week.approve`) → todos los ítems `pending|rejected` de la semana a `approved` (limpia comentario).
  - `POST /review/{token}/weeks/{week_from}/comment` (`review.week.comment`) body `{comment}` → upsert `review_week_comments`.
  - `POST /review/{token}/approve-all` (`review.approve_all`).
  - `POST /review/{token}/submit` (`review.submit`) body `{general_comment?}`.
- Resolución del token (middleware `ResolveReviewToken` o en el controlador): 404 si no existe; si `revoked_at` → página "Enlace revocado"; si `expires_at < now` → "Enlace vencido"; si `submitted_at` → modo solo lectura (GET permitido, POST → 409). Fija `app()->instance('brand', ...)`.
- `show` devuelve Inertia `Review/Show` con: `brand {name, logo_url, palette}`, `review {from_date, to_date, expires_at, submitted_at, general_comment, summary}`, `weeks: [{week_from, week_to, comment, posts: [...]}]` donde cada post trae `id, network, format, scheduled_date, hook, copy, hashtags, decision, comment, media: {type: image|carousel|video, urls: []}`. URLs de media = `URL::temporarySignedRoute('assets.show', $review->expires_at, [...])` (misma ruta firmada que usa la app, caduca con la revisión).
- `submit`: en transacción, por cada ítem: `approved` → post `Rendered → ClientApproved`; `rejected` → `Rendered → Drafted` (conserva assets, `rendered_at`); `pending` → sin cambios. Si un post ya no está en `rendered` (el operador lo editó mientras tanto) se omite sin error. Fija `submitted_at`, `general_comment`; dispara `event(new ReviewSubmitted($review))`. Respuesta: redirect a `review.show` (modo solo lectura con resumen "Gracias, revisión enviada").
- UI `resources/js/Pages/Review/Show.vue` + `Layouts/ReviewLayout.vue` (sin nav de la app): cabecera con logo y nombre de la marca, rango y barra de progreso "18 de 23 decididos"; botón **Aprobar todo**; por semana: título, **Aprobar semana**, textarea "Comentario de la semana" (guarda al perder foco); tarjetas por post: media (imagen; carrusel con flechas; `<video controls>`), badge de red, fecha, copy y hashtags, botones **Aprobar** / **Rechazar** (abre textarea obligatoria) y estado actual. Pie fijo con **Enviar revisión** (+ textarea comentario general). Diseño neutro (grises + acento `palette.accent` de la marca en botones primarios), responsive: el cliente lo verá en el móvil. Textos en español.
- Página de error compartida `Review/Unavailable.vue` (`reason: revoked|expired|not_found`).

## Paso 5 — Tests

- `PostStatusTest`: nuevas transiciones válidas/invalidas (`Rendered → Scheduled` ya no vale).
- `ReviewRequestStoreTest`: crea solicitud con los `rendered` del mes/semana; excluye posts ya en otra abierta; 422 sin elegibles; 422 si la marca no requiere aprobación; correo encolado a cada contacto (`Mail::fake`); `flash.review_url` contiene un token válido; rotate cambia el hash y reenvía; revoke; operador de otra marca → 403/404.
- `ReviewPortalTest`: token inválido 404; revocado/vencido → vista `Unavailable`; aprobar post; rechazar sin comentario 422; aprobar semana; aprobar todo; comentario semanal upsert; submit aplica transiciones (`client_approved` / `drafted`), omite posts que ya no están `rendered`, fija `submitted_at` y dispara `ReviewSubmitted` (`Event::fake`); POST tras submit → 409; media firmada vence con la revisión.
- `ClientApprovalSkipTest`: marca con `requires_client_approval=false` → `RenderPost` deja `client_approved`; `approve` con render fresco ídem; botón no aparece en props (`requires_client_approval` false).
- `BrandContactTest`: solo admin; email único por marca; contacto de otra marca → 404.
- `PlanControllerTest`: props `reviews` y `post.review`.
- Migración de datos: test que parte de un post `rendered` de una marca sin aprobación y verifica `client_approved` tras migrar (o se verifica en la BD real y se documenta).

## Paso 6 — Documentación y cierre

- `AGENTS.md`: flujo de revisión, estados nuevos, rutas `/review/{token}`, correo (`MAIL_*`), cómo probar en local (`MAIL_MAILER=log` → `storage/logs/laravel.log`).
- Verificación final: `sail artisan test`, `sail npm run build`, `sail pint`, `sail restart queue`, migración en la BD real (posts `rendered` de Saberé → `client_approved`). En el navegador: activar `requires_client_approval` en Demo, añadir un contacto, renderizar 2 posts de Demo, "Enviar a aprobación", abrir el enlace en ventana privada, aprobar uno y rechazar otro con comentario, enviar; comprobar estados y comentarios en la grilla del operador. Screenshots del portal (desktop y móvil 390 px).

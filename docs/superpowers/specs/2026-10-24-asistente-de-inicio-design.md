# Asistente de inicio ("Primeros pasos") — diseño y plan

**Fecha:** 2026-10-24 · **Estado:** aprobado

## 1. Objetivo

Un usuario nuevo no debe descubrir Contenia por los menús: al entrar por primera
vez recibe un asistente a pantalla completa que lo lleva, con acciones reales,
desde cero hasta su primera grilla (admin) o hasta saber operar su marca
(operador). Totalmente guiado, con posibilidad de saltar y de retomar.

## 2. Decisiones

| Tema | Decisión |
|---|---|
| Formato | Wizard a pantalla completa, un paso por pantalla, barra de progreso arriba, botones Atrás / Continuar / "Saltar por ahora". |
| Disparo | Middleware `EnsureOnboarded` en el grupo `auth`: si `users.onboarded_at` es null y la ruta no es `onboarding.*`, `logout`, `profile.*` ni assets → redirige a `/onboarding`. |
| Persistencia | `users.onboarded_at` (timestamp nullable), `users.onboarding_state` json `{track: 'admin'|'operator', step: int, brand_id?: int}`. "Saltar" fija `onboarded_at`; "Guía de inicio" en el menú del usuario lo vuelve a `null` conservando `onboarding_state` para retomar. |
| Recorridos | **A (admin)**: crear primera marca con IA y generar la primera grilla. **B (operador)**: 5 pantallas explicativas con enlaces a la pantalla real. Admin que ya tiene marcas elige entre A (nueva marca) y B. |
| Reutilización | El wizard es capa de UI: usa `BrandController@store`, `brands.onboarding.run/apply.*`, contactos, `plans.generate` y `plans.status` existentes. Solo se añade un endpoint para "Aplicar todo" y otro para guardar el estado del wizard. |
| Modo oscuro | Tokens `surface/fg/line` + `brand-*`; nada específico. |

## 3. Recorrido A — "Configura tu primera marca" (admin)

| Paso | Pantalla | Acción real |
|---|---|---|
| 1 | **Bienvenida**: 3 tarjetas (Marcas · Grilla con IA · Aprobación del cliente) y texto "en ~5 minutos tendrás tu primera grilla". | — |
| 2 | **Tu marca**: nombre, sitio web, plan (select, default Básico), toggle "El cliente aprueba el contenido". | `POST /onboarding/brand` → crea la marca (reusa validación de `StoreBrandRequest`/`BrandController@store`), guarda `brand_id` en `onboarding_state`. Si ya hay `brand_id`, el paso muestra la marca creada y permite editar nombre/web. |
| 3 | **Deja que la IA la lea**: textarea posts de ejemplo (opcional), usuario social (opcional), botón **Generar propuesta**. Mientras corre: barra indeterminada + mensajes rotativos ("Leyendo tu web…", "Detectando colores…", "Proponiendo pilares…"), polling cada 4 s a `onboarding.status`. Al llegar: las 4 tarjetas (`OnboardingProposal.vue` existente, prop `compact`) + botón **Aplicar todo** (`POST /onboarding/apply-all` → aplica profile+pillars(replace)+strategies+palette en transacción). Si `error` o sin web: enlace "Configurar a mano" → formulario mínimo (descripción, tono, audiencia) + 3 pilares sugeridos genéricos (Educativo 40 / Marca 30 / Comunidad 30) que se aplican igual. | job `OnboardBrand` existente |
| 4 | **Identidad**: subir logo (reusa `brands.update` con `logo`), 4 swatches de la paleta aplicada, y **vista previa real**: `<iframe>` a una ruta nueva `GET /onboarding/preview` que renderiza la plantilla `instagram_image` con un post de muestra fijo ("Hola, somos {marca}") y la paleta/logo actuales (misma vista `Render/Frame` con datos de ejemplo; sin guardar nada). | `brands.update` (logo) / `apply/palette` |
| 5 | **Quién aprueba** (solo si `requires_client_approval`): nombre, email, WhatsApp; lista de añadidos; se puede saltar. | `brands.contacts.store` existente |
| 6 | **Tu primera grilla**: selector de mes (default: mes siguiente), aviso de cuota del plan ("Básico: hasta 12 posts"), botón **Generar grilla** → progreso con polling a `plans.status` (muestra "semana 2 de 4…" si `generating_at` + conteo de posts creciente). Al terminar: resumen "N posts creados" + botón **Ver mi grilla** → fija `onboarded_at` y va a `/b/{slug}/plans/{y}/{m}`. | `plans.generate` existente |

Validaciones: no se puede pasar del paso 2 sin marca; del 3 sin perfil+pilares
aplicados (o configurados a mano); el 5 es opcional; el 6 puede saltarse con
"Lo haré después" (igual fija `onboarded_at`).

## 4. Recorrido B — "Así se trabaja en Contenia" (operador, o admin que elige)

Pantallas explicativas con ilustración SVG inline (sin dependencias), texto de
2–3 frases y botón secundario "Ir a verlo" que abre la pantalla real en la misma
pestaña y vuelve al wizard con "← Volver a la guía" (query `?from=onboarding`
que `AuthenticatedLayout` convierte en un banner fino arriba).

1. **Bienvenida**: "Trabajas en {marca(s) asignada(s)}"; qué verás.
2. **La grilla**: calendario mensual; leyenda de estados (borrador, aprobado, renderizado, aprobado por cliente, programado, publicado, fallido) con los mismos colores de la grilla; generar / completar mes / regenerar semana.
3. **El post**: el drawer: aprobar, editar, regenerar con comentario, generar diseño / nuevo fondo; qué cuentan las cuotas.
4. **Aprobación del cliente**: enviar a aprobación, enlace, qué ve el cliente, rechazos con comentario que vuelven a borrador.
5. **Pautas**: la IA propone a partir del feedback, tú activas; entran en la siguiente generación. Botón **Empezar** → `onboarded_at` → grilla de su marca.

Admin con marcas existentes y `onboarded_at = null`: pantalla previa **"¿Por dónde empezamos?"** con dos tarjetas: "Crear una marca nueva con IA" (A) / "Ver cómo funciona" (B).

## 5. Implementación

### Paso 1 — Datos y control de flujo
- Migración `add_onboarding_to_users`: `onboarded_at` timestamp nullable, `onboarding_state` json nullable. **Backfill**: usuarios existentes → `onboarded_at = now()` (nadie que ya use la app ve el wizard a la fuerza; lo abren desde "Guía de inicio").
- `App\Http\Middleware\EnsureOnboarded` (alias `onboarded`) aplicado al grupo `auth` de `routes/web.php` salvo `onboarding.*`, `profile.*`, `logout`, `brands.logo`, `assets.*`, `render.*`. Usuarios recién creados por el admin (`UserController@store`) nacen con `onboarded_at = null`.
- `OnboardingController`: `show` (decide track: operador → B; admin sin marcas → A; admin con marcas → selector; respeta `onboarding_state.track` si ya eligió), `state` (`PATCH`, guarda `{track, step, brand_id}`), `skip` (`POST`, fija `onboarded_at`), `restart` (`POST`, `onboarded_at = null`, desde el menú), `complete` (`POST`, fija `onboarded_at` y devuelve la URL de la grilla).
- Rutas `onboarding.*` bajo `auth` (sin `onboarded`).

### Paso 2 — Recorrido A (acciones)
- `OnboardingController@storeBrand` (`POST /onboarding/brand`): valida `name, website_url (nullable url), plan_id, requires_client_approval`; crea la marca con `DefaultsSeeder::forBrand` como hace `BrandController@store`; asigna al admin si aplica; guarda `brand_id` en estado. Si ya existe `brand_id`, actualiza.
- `OnboardingController@status` (`GET /onboarding/status`): `{pending: bool, proposal: ..., applied: {...}, pillars_count, contacts_count, plan: {generating_at, posts_count, last_error}}` para el polling de los pasos 3 y 6.
- `OnboardingController@applyAll` (`POST /onboarding/apply-all`): llama en transacción a los 4 métodos de `OnboardingApplyController` (extraer a un servicio `ProposalApplier` que ambos controladores usen, con `replace=true`).
- `OnboardingController@manualSetup` (`POST /onboarding/manual`): perfil mínimo + 3 pilares genéricos.
- `OnboardingController@preview` (`GET /onboarding/preview`): renderiza `Render/Frame` con un post de ejemplo en memoria (no persistido) para la marca del estado; requiere auth; usada en `<iframe>`.
- Generar grilla: el wizard llama a `plans.generate` existente con `{brand, year, month}`.

### Paso 3 — UI del wizard
- Layout `OnboardingLayout.vue`: cabecera con wordmark, barra de progreso por pasos, "Saltar por ahora" arriba a la derecha, contenido centrado (max-w-3xl), pie con Atrás / Continuar.
- `Pages/Onboarding/Index.vue` orquesta: props `track, step, brand, plans, state`; componentes por paso en `Pages/Onboarding/Admin/Step{1..6}.vue` y `Pages/Onboarding/Operator/Step{1..5}.vue`; `Pages/Onboarding/Choose.vue` para el admin con marcas.
- `useOnboardingPoll(url, intervalMs)` composable (reutiliza el patrón de polling ya usado en `Plans/Show` y `Brands/Form`).
- `AuthenticatedLayout`: ítem **"Guía de inicio"** en el dropdown del usuario (`POST onboarding.restart`) y banner "← Volver a la guía" cuando `?from=onboarding`.
- Ilustraciones: SVG inline sencillos con tokens de color (sin imágenes externas).

### Paso 4 — Tests
- `EnsureOnboardedTest`: usuario con `onboarded_at` null → redirige a `/onboarding` desde `/b/x/plans/...`; con fecha → pasa; `skip` fija la fecha; `restart` la quita; usuarios existentes migrados tienen fecha (test de la migración con `users` previos).
- `OnboardingAdminTrackTest` (`FakeLlmClient`, `Http::fake`): `storeBrand` crea marca y estado; `applyAll` aplica las 4 secciones; `manualSetup` deja perfil + 3 pilares que suman 100; `status` refleja propuesta/pending/plan; `complete` fija `onboarded_at` y devuelve la URL de la grilla; `preview` responde 200 con la paleta de la marca.
- `OnboardingOperatorTrackTest`: operador ve track B con su marca; no puede llamar a `storeBrand` (403); `complete` lo manda a su grilla.
- `OnboardingChooseTest`: admin con marcas ve `Choose`; al elegir se persiste `track`.
- Playwright e2e `tests/e2e/onboarding.spec.ts`: usuario nuevo admin recorre A completo con propuesta fake sembrada (sin LLM real) y termina en la grilla; operador recorre B.

### Paso 5 — Verificación
`sail artisan test`, `npm run build`, `pint`, e2e, capturas claro/oscuro de cada paso en `/tmp/onboarding/`, demostración en el navegador.

## 6. Fuera de alcance
Tour con globos sobre la UI real, vídeos, onboarding del cliente final (el portal ya es autoexplicativo), i18n.

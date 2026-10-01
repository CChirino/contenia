# Sabere Marketing Agent — Diseño

Fecha: 2026-10-01
Estado: borrador para revisión

## 1. Objetivo

Aplicación web independiente (`sabere-marketing`) que planifica, redacta, diseña y publica el contenido de redes sociales de Saberé en **Instagram, LinkedIn y TikTok**. Un LLM propone la grilla mensual y los copies; un humano (único usuario admin) aprueba; el sistema renderiza los diseños con la identidad de marca y publica automáticamente vía las APIs oficiales de cada red.

Cada post se crea **para una red concreta** aprovechando su formato y tono; no se replica el mismo contenido en las tres redes.

## 2. Decisiones tomadas

| Tema | Decisión |
|---|---|
| Ubicación | Proyecto separado, repo `sabere-marketing`. No toca `sabere-backend`. |
| Stack | Laravel 12 + Inertia + Vue 3 + TypeScript + Vite + Tailwind + MySQL. Colas `database`. Mismo stack que Sabere. |
| Arquitectura | Monolito construido por fases (enfoque A). Render (Playwright/ffmpeg) en el mismo contenedor. |
| Copies | LLM genera, humano aprueba. Nada se publica sin aprobación. |
| Diseños | Híbrido: plantillas Vue/HTML con paleta Saberé + fondos generados por IA de imágenes. |
| Publicación | Automática vía APIs oficiales (Instagram Graph API, LinkedIn Posts API, TikTok Content Posting API). |
| Interfaz | App web propia: calendario mensual, preview por post, aprobar/editar/regenerar. |
| Usuarios | Un solo admin. Login Breeze, sin registro público. |
| Estado de cuentas | Existen pero son personales. Requieren conversión a Business + apps de desarrollador (ver §9). |

## 3. Identidad de marca

Copiada de `sabere-backend/tailwind.config.js`:

```js
colors: {
  sabere: {
    dark:   '#1A2A42', // azul oscuro primario
    light:  '#F8F5F0', // crema
    accent: '#27C2A5', // teal
    purple: '#7854D2', // morado
  },
},
fontFamily: { sans: ['Inter', ...] }
```

## 4. Modelo de datos

| Tabla | Campos clave | Propósito |
|---|---|---|
| `users` | Breeze estándar | Único admin. |
| `brand_profile` | tono_de_voz, audiencia, hashtags_base, cta, url, paleta (json) | Singleton. Contexto inyectado en cada prompt. |
| `network_strategies` | network, posts_por_semana, formatos_preferidos (json), enfoque (texto) | Una fila por red. Define cadencia y estilo por red. |
| `content_pillars` | nombre, descripcion, peso (%) | Ejes temáticos. El LLM distribuye la grilla según pesos. |
| `calendar_events` | fecha, nombre, descripcion, recurrente (bool) | Feriados venezolanos, Día del Maestro, inicio/fin de lapso, etc. |
| `monthly_plans` | year, month, status (`draft`/`approved`), generated_at | Una grilla por mes. |
| `posts` | plan_id, scheduled_at, network, pillar_id, format, hook, copy, hashtags (json), design_payload (json), background_prompt, template, asset_path, external_id, permalink, status, error, published_at | Un post = una red. |
| `templates` | nombre, network, format, componente, dimensiones | Catálogo de plantillas de diseño. |
| `social_accounts` | network, account_id, access_token (encrypted), refresh_token (encrypted), expires_at, meta (json) | Credenciales OAuth por red. |

### Estados de `posts`

```
drafted → approved → rendered → scheduled → published
                                       ↘ failed → (retry) → scheduled
```

- `drafted`: creado por el LLM, pendiente de revisión.
- `approved`: copy aprobado; dispara `RenderPost`.
- `rendered`: asset generado; preview disponible. Puede regenerarse el diseño sin volver a `drafted`.
- `scheduled`: aprobado para publicar en `scheduled_at`.
- `published` / `failed`.

Reglas: no se puede pasar a `scheduled` sin `rendered`; no se publica sin `scheduled`; editar el copy de un post `rendered` lo devuelve a `approved` (re-render).

### Formatos por red (v1)

| Red | Formatos | Dimensiones |
|---|---|---|
| Instagram | `image`, `carousel`, `reel` | 1080×1350 (feed), 1080×1920 (reel) |
| LinkedIn | `image` | 1200×1200 |
| TikTok | `video` | 1080×1920, 15–30 s |

### Estrategia por red (valores iniciales, editables)

- **LinkedIn** — 3/sem, imagen única, tono profesional: casos de uso, datos del sector educativo, visión de producto.
- **Instagram** — 4/sem, carrusel 4:5 y reels, tono cercano: tips para docentes/representantes, funcionalidades en 3 pasos, frases.
- **TikTok** — 3/sem, video vertical, tono ágil: "¿sabías que…?", problemas del día a día escolar resueltos con Saberé.

## 5. Generación de la grilla (LLM)

1. Acción "Generar grilla de {mes}" → job `GenerateMonthlyPlan`.
2. Prompt compuesto por: `brand_profile` + `network_strategies` + `content_pillars` + `calendar_events` del mes + resumen de posts del mes anterior (evitar repetición).
3. Salida JSON validada contra esquema. Por post: `date`, `network`, `pillar`, `format`, `hook`, `copy`, `hashtags[]`, `design_payload` (textos por slide/escena), `background_prompt`.
4. Restricciones que debe respetar: cadencia por red, pesos de pilares, no repetir tema entre redes en la misma semana, no publicar en feriados marcados como "sin publicación".
5. Se crean los `posts` en `drafted`; el plan queda `draft`.
6. UI: calendario mensual con filtro por red. Por post: **Aprobar**, **Editar** (copy, fecha, formato, hashtags), **Regenerar** (solo ese post, con comentario opcional que se inyecta al prompt).
7. `LlmClient` es una interfaz; implementación inicial `AnthropicClient`. Respuestas registradas en `llm_logs` (prompt, respuesta, tokens) para depuración.

## 6. Render de diseños

### Plantillas

Componentes Vue en `resources/js/Templates/{Network}/` con props tipadas. v1:

| Red | Plantilla | Formato | Dimensión |
|---|---|---|---|
| Instagram | `CarouselSlide` | carousel | 1080×1350 |
| Instagram | `Quote` | image | 1080×1350 |
| Instagram | `ReelFrame` | reel | 1080×1920 |
| LinkedIn | `SingleImage` | image | 1200×1200 |
| TikTok | `VideoFrame` | video | 1080×1920 |

El carrusel PDF de LinkedIn queda fuera de v1.

### Pipeline (job `RenderPost`)

1. **Fondo IA** (si la plantilla lo usa): `ImageGenerator` (interfaz; implementación inicial OpenAI `gpt-image-1`). Prompt = `background_prompt` + sufijo fijo de estilo: "ilustración plana, paleta #1A2A42/#27C2A5/#7854D2/#F8F5F0, sin texto, sin personas reales". Guardado en `storage/app/assets/{post_id}/bg.png`. Se cachea; "Regenerar diseño" reutiliza el fondo salvo que se marque "nuevo fondo".
2. **Render de frames**: ruta interna `/render/{post}/{frame}` (protegida por token firmado) sirve la plantilla con sus props. Comando artisan `render:frames {post}` invoca un script Node con Playwright que captura un PNG por frame.
3. **Ensamblado**:
   - `image` → un PNG.
   - `carousel` → N PNGs.
   - `reel` / `video` → `ffmpeg`: cada frame 3–5 s, transición fade, pista de `resources/audio/` (libre de derechos), salida MP4 H.264, 30 fps, 9:16.
4. Post → `rendered`. UI muestra preview (imagen, carrusel deslizable o video) con botones **Regenerar diseño** (cambiar plantilla / nuevo fondo) y **Programar**.

Dependencias de servidor: Node 20, Chromium (Playwright), ffmpeg. Incluidas en el Dockerfile.

## 7. Publicación

### Interfaz

```php
interface Publisher {
    public function publish(Post $post): PublishResult; // external_id, permalink
}
```

Una implementación por red.

| Red | API | Flujo | Permisos |
|---|---|---|---|
| Instagram | Graph API `/{ig-user-id}/media` → `/media_publish` | Imagen/carrusel: contenedor por item con URL pública → contenedor carrusel → publicar. Reel: contenedor de video, poll `status_code=FINISHED`, publicar. | `instagram_content_publish`, `instagram_basic`, `pages_show_list` |
| LinkedIn | Posts API `/rest/images` + `/rest/posts` | `initializeUpload` → PUT imagen → crear post con `author = urn:li:organization:{id}`. | `w_organization_social`, `r_organization_social` |
| TikTok | Content Posting API `/v2/post/publish/video/init/` | Init con `PULL_FROM_URL` → poll `/status/fetch/`. | `video.publish` |

### Mecánica

- Scheduler (`schedule:run` cada minuto) ejecuta `posts:publish-due`: encola `PublishPost` por cada post `scheduled` con `scheduled_at <= now()`.
- Assets servidos por URL pública temporal firmada (`/assets/{post}/{file}?signature=...`, 1 h) porque Instagram y TikTok descargan por URL.
- Reintentos: 3 con backoff exponencial. Al agotar → `failed` con `error` visible en UI y botón **Reintentar** (vuelve a `scheduled`).
- Tras publicar: `external_id`, `permalink`, `published_at`.
- OAuth desde la UI ("Conectar Instagram / LinkedIn / TikTok"). Tokens con cast `encrypted`.
- Comando diario `tokens:refresh`: renueva tokens próximos a expirar (Meta long-lived 60 d, LinkedIn 60 d, TikTok access 24 h / refresh 365 d). Si falla, alerta en el dashboard.

### Modo manual (mientras no hay APIs aprobadas)

Todo post `rendered` ofrece **Descargar asset** y **Copiar copy + hashtags**. TikTok en sandbox publica como privado; sirve para pruebas.

## 8. Pruebas y despliegue

### Pruebas (PHPUnit, SQLite `:memory:`)

- Feature: `GenerateMonthlyPlan` con `FakeLlmClient` → crea posts correctos, respeta cadencia, valida esquema, rechaza JSON inválido. Transiciones de estado (reglas de §4). `posts:publish-due` solo toma `scheduled` vencidos.
- Unit: cada `Publisher` con `Http::fake()` (éxito, error 4xx/5xx, token vencido). `PromptBuilder`. `tokens:refresh`.
- Grupo `@render`: un test real de Playwright que genera un frame; excluido por defecto, se corre localmente/CI con Chromium.

### Despliegue

Docker Compose: `app` (PHP-FPM + Node + Chromium + ffmpeg), `mysql`, `queue` (worker), `scheduler`. Variables: `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `META_APP_ID/SECRET`, `LINKEDIN_CLIENT_ID/SECRET`, `TIKTOK_CLIENT_KEY/SECRET`, `APP_URL` pública (necesaria para que las redes descarguen assets).

## 9. Trámites previos (responsabilidad del usuario)

1. **Instagram**: convertir a cuenta Business/Creator; vincular a una Página de Facebook; crear app en Meta for Developers; solicitar App Review para `instagram_content_publish`.
2. **LinkedIn**: crear Página de empresa Saberé; crear app en LinkedIn Developers asociada a la página; solicitar acceso al producto *Community Management API*.
3. **TikTok**: pasar a cuenta Business; crear app en TikTok for Developers; agregar *Content Posting API*; solicitar auditoría (hasta entonces, publicación solo privada).

Las fases 1 y 2 (§10) no dependen de estos trámites.

## 10. Fases de entrega

1. **Base + grilla** — scaffolding, auth, brand profile, pilares, estrategia por red, eventos de calendario, generación LLM, calendario con aprobar/editar/regenerar.
2. **Render** — plantillas de §6, fondos IA, Playwright + ffmpeg, preview, descarga, modo manual.
3. **Publicación** — OAuth por red, publishers, scheduler, reintentos, refresh de tokens.

## 11. Fuera de alcance (v1)

Analíticas de rendimiento, gestión de comentarios/mensajes, múltiples usuarios o marcas, carrusel PDF de LinkedIn, stories, ideas sin post ("oportunidades"), programación de hora óptima automática.

# Plan Fase 2 — Render de diseños

Spec: `docs/superpowers/specs/2026-10-01-sabere-marketing-agent-design.md` §6.

Decisiones tomadas con el usuario:
- Fondos IA con **Pollinations** (gratis, Bearer token) y **fallback geométrico de marca** si falla. `OpenRouter` (Gemini Image) queda como driver adicional para activar después.
- Logo: `sabere-backend/public/icons/icon.svg` copiado a `resources/brand/logo.svg` (+ variante wordmark "Saberé." en texto).
- Bloque A = imágenes (todas las redes producen PNG). Bloque B = video (ffmpeg) para `reel`/`video`.

## Bloque A — Imágenes

### A1. Infraestructura
- `sail artisan sail:publish` → `docker/8.5/Dockerfile`; añadir instalación de Chromium para Playwright (`npx playwright install --with-deps chromium` como root en build) y `fonts-inter` o Inter vía `@fontsource/inter` en el proyecto (preferido: npm `@fontsource/inter`, se sirve desde Vite, sin depender del sistema). Rebuild `sail build --no-cache`.
- npm: `playwright` (dev) y `@fontsource/inter`.
- Script `scripts/render.mjs`: recibe `--url`, `--out`, `--width`, `--height`, `--frames N`; abre Chromium headless, espera `window.__RENDER_READY__ === true`, captura cada frame a PNG (`deviceScaleFactor: 1`).
- Disco: `storage/app/assets/{post_id}/` → `bg.png`, `frame-01.png`…, `final.png|mp4`. Servido por ruta firmada `assets.show` (`/assets/{post}/{file}`), necesaria además para Fase 3.

### A2. ImageGenerator
- `app/Services/Images/ImageGenerator` interface: `generate(string $prompt, int $width, int $height, int $seed): string` (devuelve bytes PNG/JPEG) + `name(): string`.
- `PollinationsGenerator`: GET `https://image.pollinations.ai/prompt/{urlencode(prompt)}` con query `model, width, height, seed, nologo=true, private=true, enhance=false`, header `Authorization: Bearer`. Timeout 120 s, 2 reintentos (429/5xx). Error → `ImageGenerationException`.
- `GeometricGenerator`: genera PNG con GD/Imagick (Intervention no; usar GD nativo): fondo `sabere.dark` o `sabere.light` según `variant`, 3–5 formas (círculos/hexágonos) en `accent`/`purple` con opacidad, posición determinista por `seed`. Siempre funciona.
- `FakeGenerator`: PNG 1×1 para tests.
- `config/images.php`: `driver`, `pollinations.{api_key, model}`, `fallback => 'geometric'`, `style_suffix` (texto fijo: "ilustración plana minimalista, paleta azul oscuro #1A2A42, teal #27C2A5, morado #7854D2, crema #F8F5F0, sin texto, sin letras, sin personas reales, alta calidad").
- `BackgroundService::for(Post $post, bool $fresh = false): string` → ruta de `bg.png`; cachea; si driver falla usa fallback y guarda `posts.background_source` (`pollinations|geometric`).

### A3. Modelo
- Migración: `posts.background_source` string nullable, `posts.render_error` text nullable, `posts.rendered_at` timestamp nullable, `posts.asset_path` ya existe (apunta a `final.png`/`final.mp4`), `posts.frames` json nullable (lista de PNGs para carrusel).
- `templates` table del spec **no** se crea: catálogo en código `app/Services/Render/TemplateRegistry` (nombre, red, formato, dimensiones, componente Vue, usa fondo IA sí/no, frames = slides). Más simple; YAGNI.

### A4. Plantillas Vue (`resources/js/Templates/`)
Página Inertia `Render/Frame.vue` recibe `{template, frame, post, background_url, logo_url, brand}` y monta el componente de plantilla a tamaño fijo con `html, body {margin:0}`; al montar y tras `document.fonts.ready` + carga de imagen de fondo setea `window.__RENDER_READY__ = true`.

| Plantilla | Red | Formato | Dim | Fondo IA | Frames |
|---|---|---|---|---|---|
| `Instagram/CarouselSlide` | instagram | carousel | 1080×1350 | sí (slide 1), geométrico resto | N slides |
| `Instagram/Quote` | instagram | image | 1080×1350 | sí | 1 |
| `Instagram/ReelFrame` | instagram | reel | 1080×1920 | sí | N escenas |
| `LinkedIn/SingleImage` | linkedin | image | 1200×1200 | sí | 1 |
| `TikTok/VideoFrame` | tiktok | video | 1080×1920 | sí | N escenas |

Diseño común: fondo IA con overlay degradado `sabere.dark` 70–90 % para legibilidad; título Inter 700 en `sabere.light`; cuerpo Inter 400; acento (línea/chip) en `accent`; logo arriba-izquierda; wordmark "Saberé." + URL abajo; indicador de slide "2/5" en carrusel; última slide de carrusel = CTA del brand profile.

### A5. Pipeline
- Job `RenderPost(Post $post, bool $freshBackground = false)` (`ShouldQueue`, timeout 600): `BackgroundService` → por cada frame invoca `scripts/render.mjs` vía `Process` apuntando a `route('render.frame', [post, frame, 'signature'])` (URL interna `http://localhost` dentro del contenedor; config `render.base_url`) → `frames` json; si formato imagen/carrusel: `final.png` = frame 1 (carrusel mantiene lista) → status `rendered`, `rendered_at`. Error → `render_error`, status se mantiene en `approved`.
- Disparo: al **Aprobar** (`posts.approve`) se despacha `RenderPost`. Endpoint `POST /posts/{post}/render` (`posts.render`, body `fresh_background` bool) para "Regenerar diseño"; permitido en `approved`/`rendered`.
- Editar un post `rendered` → vuelve a `approved` y re-despacha render (spec §4).
- Comando `render:post {post} {--fresh}` para consola.

### A6. UI
- Drawer: sección "Diseño" reemplaza el placeholder: spinner "Generando diseño…" si `approved` sin `render_error`; error en rojo + botón "Reintentar"; preview (imagen, carrusel con flechas, o grid de frames para video hasta Bloque B); botones **Descargar** (zip de frames para carrusel vía endpoint `posts.download`), **Regenerar diseño**, **Nuevo fondo**, chip "Fondo: IA / geométrico"; **Copiar copy + hashtags**. Botón **Programar** se deja para Fase 3 (oculto).
- Tarjeta del calendario: miniatura 40×40 del `final.png` si existe.

### A7. Tests
- `PollinationsGenerator` con `Http::fake` (éxito, 429 → reintenta, 500 → excepción). `GeometricGenerator` produce PNG válido de las dimensiones pedidas (GD). `BackgroundService` usa fallback cuando el driver falla y marca `background_source`.
- `TemplateRegistry::for(network, format)`.
- `RenderPost` con `Process::fake` para el script y `FakeGenerator`: crea frames, status `rendered`; fallo del script → `render_error`, status `approved`.
- Endpoints `posts.render`, `assets.show` (firma inválida → 403), `posts.download`.
- Test `@render` real (grupo excluido por defecto): renderiza `LinkedIn/SingleImage` para un post de fábrica y verifica PNG 1200×1200.

## Bloque B — Video (`reel`, `video`)
- `VideoAssembler`: ffmpeg concat de frames (3.5 s c/u, `xfade` 0.5 s), pista `resources/audio/*.mp3` (libre de derechos, incluida en repo con licencia en `resources/audio/LICENSE.md`), `-c:v libx264 -pix_fmt yuv420p -r 30`, audio con fade-out, duración = frames × 3.5 s. Salida `final.mp4`.
- `RenderPost` lo invoca para formatos de video; preview `<video controls>` en el drawer; descarga MP4.
- Test con `Process::fake`; test `@render` real que genera un MP4 y valida con `ffprobe`.

## Orden de handoffs
1. A1 + A2 + A3 (infra, generadores, migración, tests).
2. A4 + A5 (plantillas, render script, job, comando) + test `@render` real de LinkedIn.
3. A6 + resto de plantillas IG/TikTok + capturas.
4. Bloque B.

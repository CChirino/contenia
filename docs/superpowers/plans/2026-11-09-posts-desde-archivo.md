# Posts desde archivo (foto/video subido → IA redacta el resto)

Hoy el flujo es IA → diseño. Esta función añade el inverso: el operador sube
material real (foto o video) y la IA genera hook, copy, hashtags, pilar e icono.
El post entra a la grilla como un borrador más y sigue el flujo normal
(editar/aprobar → render → revisión del cliente).

Decisiones tomadas con el usuario (2026-11-09):
- Punto de entrada: **nuevo post desde archivo** en la grilla (no reemplazo en posts existentes, no lote).
- Foto: el operador elige por post entre **plantilla de marca** (la foto es el fondo) o **tal cual**.
- Video: el operador elige entre **con marca** (portada con hook + logo en esquina) o **tal cual**.
- La IA "ve" el archivo con un modelo de visión (sondeado: `nvidia/nemotron-3.5-super-vl:free`
  acepta `image_url` base64 y responde JSON en ~8 s; los modelos de texto devuelven
  404 "No endpoints found that support image input"). Nota del operador opcional.

## Datos (`posts`)

| Columna | Tipo | Uso |
|---|---|---|
| `media_source` | string default `ai` | `ai` \| `upload` |
| `media_mode` | string nullable | `template` \| `raw` |
| `source_path` | string nullable | relativo al disco `local`: `{assetDir}/source.{ext}` |
| `source_mime` | string nullable | |
| `source_meta` | json nullable | `{width,height,duration,size}` |
| `media_description` | json nullable | salida de visión |
| `operator_note` | text nullable | |
| `composing_at` | timestamp nullable | en curso (polling) |
| `compose_error` | text nullable | |

## IA en dos llamadas — job `ComposeFromMedia(Post $post, bool $reuseDescription = false, ?string $comment = null)`

1. **Visión** (`LlmClient::describeImages(system, user, imageDataUrls[], schema, purpose)`):
   `MediaInspector` prepara 1 imagen (foto redimensionada ≤1024px, JPEG) o 3 fotogramas
   (video: 5 % / 50 % / 90 % con ffmpeg, ≤768px) + metadatos. Modelo `LLM_VISION_MODEL`
   (default `nvidia/nemotron-3.5-super-vl:free`), `response_format json_object`,
   `max_tokens` 2000. Salida `{description, elements[], people, visible_text, mood, suggested_angle}`
   → `posts.media_description`. Se omite si `reuseDescription` y ya existe.
2. **Redacción** con el modelo de siempre (`complete`): `PromptBuilder::forMedia($post, $comment)`
   = `brandIntro` + `learnedGuidelines` + red/formato + pilares de la marca (id y nombre)
   + descripción del material + nota del operador. Esquema `PlanSchema::fromMedia($pillarIds)`:
   `hook, copy, hashtags, design_payload{slides}, content_pillar_id, icon`
   (sin `background_prompt`/`photo_keywords`: el fondo ya existe).
   - `image`: 1 slide. `reel`/`video` + `template`: 1 slide (portada). `raw`: slides vacío.

Cuotas: el controlador hace `check(PostsGenerated)` al subir; el job hace `record` en la
misma transacción que persiste el resultado. Ninguna llamada de imagen IA. Regenerar un post
`upload` reencola `ComposeFromMedia(reuse=true, comment)` y consume `Regenerations`.
Fallo: `compose_error`, `composing_at=null`, el post queda editable a mano con botón **Reintentar**.

## Subida — `POST /b/{brand}/plans/{year}/{month}/posts/from-media` (`posts.from-media`)

Validación (`StorePostFromMediaRequest`): `file` imagen (jpg/jpeg/png/webp ≤10 MB) o video
(mp4/mov ≤100 MB, ≤90 s por ffprobe); `network` permitida por el plan; `mode` template|raw;
`date` dentro del mes; `note` ≤500. Formato derivado: imagen → `image`; video → Instagram `reel`,
TikTok `video`; video en LinkedIn o imagen en TikTok → 422. Crea/obtiene el `MonthlyPlan`,
crea el post (`drafted`, hook "Analizando material…", `composing_at=now`), guarda `source.{ext}`
en `assetDir()`, encola el job. `POST posts/{post}/compose` (`posts.compose`) reintenta.
`plans.status` devuelve además `composing_ids` y la UI recarga cuando se vacía.

## Render (`RenderPost` con `media_source=upload`)

| Modo | Qué hace |
|---|---|
| foto + template | `source` → `bg.png` (cover al tamaño de la plantilla, misma normalización que Pollinations), `background_source=upload`, luego el render normal. Sin cuota de imágenes. |
| foto + raw | sin Playwright: `frame-01.png` = la foto ajustada (lado largo ≤1080, sin recorte). |
| video + template | portada renderizada (1 frame) + `VideoAssembler::assembleWithSource(cover, source, logoPng|null, out, w, h)`: portada 2 s, xfade, video escalado/pad a w×h, logo PNG en esquina inferior derecha (12 % del ancho, 0.9) si el logo de la marca es PNG (SVG se omite), audio del video original, h264/aac/faststart. Póster = portada. |
| video + raw | `VideoAssembler::normalize(source, out)`: h264 yuv420p aac faststart, sin reescalar. Póster `frame-01.png` extraído con ffmpeg. |

`FakeVideoAssembler` copia el origen; `FakeMediaInspector` (`MEDIA_INSPECTOR=fake` en phpunit.xml)
devuelve data URLs fijas y metadatos de prueba.

## UI (tanda 2)

Botón **"+ Desde archivo"** junto a "Generar grilla" y en la agenda móvil → modal: dropzone
(imagen/video, vista previa), red (solo del plan; el formato se muestra derivado), fecha
(por defecto primer día del mes sin post en esa red), modo (dos tarjetas con explicación),
nota opcional. Tarjeta en grilla/agenda con icono 📎 y estado "Analizando material…"
(polling). Drawer: vista previa del archivo original, chip "Desde archivo · plantilla/tal cual",
`compose_error` con **Reintentar**. Regenerar y Editar funcionan igual que hoy.

## Pruebas

- Feature `StorePostFromMediaTest`: crea post+archivo+job (`Queue::fake`); 422 cuota agotada,
  red fuera del plan, video en LinkedIn, fecha fuera de mes; operador de otra marca → 404.
- Feature `ComposeFromMediaTest`: `FakeLlmClient` con 2 respuestas → post completo,
  `media_description` guardada, `composing_at=null`, `posts_generated` +1, pilar asignado;
  fallo del LLM → `compose_error`; `reuse=true` → una sola llamada.
- Feature `RenderPostUploadTest`: los 4 modos con fakes (bg desde source y `background_source=upload`
  sin cuota de imágenes; raw sin renderer; videos con `final.mp4` + póster).
- e2e Playwright (tanda 2): subir foto y video de prueba, ver "Analizando…", abrir el drawer.

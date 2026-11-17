# Paleta por importancia: 3 primarios + hasta 3 secundarios

Decisión del usuario (2026-11-16): la paleta deja de pedirse por papel técnico
(fondo oscuro / fondo claro / acento / secundario) y pasa a pedirse como la
define un diseñador: **Primario 1, 2, 3** (obligatorios, ordenados por
importancia) y **Secundario 1–3** (opcionales). El fondo y el texto de los
diseños se eligen **automáticamente** con contraste.

## Datos

`brands.palette` (JSON) pasa a:

```json
{ "primary": ["#771339", "#BF8660", "#FFFCF3"], "secondary": ["#2B2B2B"] }
```

- `primary`: exactamente 3 hex `#RRGGBB` (mayúsculas), orden = importancia.
- `secondary`: 0–3 hex.
- Forma antigua `{dark, light, accent, secondary(string), purple?}`: se
  reconoce porque `primary` no es array. Migración de datos
  (`2026_11_16_000001_palette_primary_secondary`): `primary = [accent,
  secondary, dark]`, `secondary = [light]` (+ `purple` si existe y difiere),
  quitando duplicados y rellenando hasta 3 primarios con los restantes o con
  la derivación de abajo si faltan. `down()` escribe de vuelta los roles.
- `brand_profile.palette` no la lee nada (resto antiguo): se deja de mostrar
  y editar en Configuración → Marca. La columna se queda (NOT NULL), sin uso.

## Roles para los diseños — `App\Support\BrandPalette`

`BrandPalette::normalize(mixed $palette): array{primary: string[3], secondary: string[]}`
(acepta forma nueva o antigua; sanea hex; nunca lanza) y
`BrandPalette::roles(mixed $palette): array{dark, light, accent, secondary}`
— lo que consumen las plantillas (`--brand-*`), el portal y el logo genérico.

Regla (determinista, documentada en el código):

1. `accent` = Primario 1 (el color más importante es el más visible).
2. `dark` = el color con menor luminancia relativa entre todos (primarios +
   secundarios) **excepto accent**, si su contraste con `#FFFFFF` ≥ 4.5;
   si ninguno cumple: accent mezclado al 35 % con negro (`mix(accent, #000, 0.65)`),
   y si aún no llega a 4.5 → `#141414`.
3. `light` = el color con mayor luminancia entre todos excepto `accent` y
   `dark`, si su contraste con `dark` ≥ 4.5; si no, `#FFFFFF`.
4. `secondary` = el primero de [Primario 2, Primario 3, Secundario 1, 2, 3]
   que no sea accent/dark/light; si no hay, accent mezclado al 45 % con blanco.

Contraste y luminancia: fórmula WCAG 2.1 (sRGB linealizado).

## Dónde cambia

- Render: `RenderFrameController` y `OnboardingController::preview` pasan
  `BrandPalette::roles($brand->palette)` como `palette` (las plantillas y
  `BrandFrame.vue` no cambian). `ReviewPortalController` → roles (el portal
  usa `accent`). `BrandLogoController::initialLogo` → roles.
  `HandleInertiaRequests` sigue mandando `brand.palette` (forma nueva) y
  añade `brand.palette_roles`.
- Validación (`StoreBrandRequest`, `OnboardingApplyController::palette`):
  `palette.primary` array size:3 de `regex:/^#[0-9a-fA-F]{6}$/`;
  `palette.secondary` array max:3 nullable del mismo regex. Persistir siempre
  vía `BrandPalette::normalize`.
- IA (`OnboardingSchema::proposal` + `OnboardBrand::normalizePalette`):
  la IA propone `palette: {primary: [3], secondary: [0-3]}` ordenados por
  importancia; instrucción en el prompt: "primary = los 3 colores de marca
  por importancia (el 1 es el principal); secondary = colores de apoyo; no
  incluyas blanco/negro puros salvo que sean de la marca". Fallback con los
  colores detectados (los 3 más frecuentes → primarios) y con la derivación.
- Defaults: `OnboardingController` (paleta inicial), `DefaultsSeeder`
  (Saberé), `BrandFactory`.
- UI: componente nuevo `resources/js/Components/PaletteEditor.vue`
  (`v-model` = `{primary: string[3], secondary: string[]}`): fila
  "Colores primarios" con 3 selectores numerados **1 · 2 · 3** (texto de
  ayuda: "Ordenados por importancia: el 1 es el color principal de la
  marca"), fila "Colores secundarios (opcional)" con hasta 3 y botones
  "+ Añadir" / quitar, y una tira de vista previa "Así se usarán en los
  diseños" con los 4 roles calculados en el cliente (misma regla, en
  `resources/js/utils/brandPalette.ts`, con test de paridad PHP↔TS vía
  casos fijos compartidos). Se usa en `Admin/Brands/Form.vue`,
  `Admin/Brands/OnboardingProposal.vue` (tarjeta Paleta) y se muestra en
  `Onboarding/Admin/Step4.vue` (identidad). `Settings/Brand.vue`: se quita
  el editor de `brand_profile.palette`; se muestran los colores de la marca
  en solo lectura con enlace "Editar en Administración → Marcas" para
  admin/gestor.

## Pruebas

- Unit `BrandPaletteTest`: normalize (nueva, antigua, basura); roles para
  casos fijos: (a) vino #771339 + crema #FFFCF3 + cobre #BF8660 → accent
  vino, dark derivado (vino oscurecido) o el más oscuro, light crema;
  (b) todo claro → dark `#141414`/mezcla; (c) todo oscuro → light `#FFFFFF`;
  (d) primarios repetidos; contraste dark/light ≥ 4.5 siempre.
- Migración: forma antigua → nueva con el mapeo indicado.
- Feature: guardar marca con 3 primarios + 2 secundarios; 422 con 2
  primarios o 4 secundarios o hex inválido; aplicar paleta de propuesta;
  RenderFrame recibe roles; propuesta IA normalizada a la forma nueva.
- Los mismos casos fijos en `tests/fixtures/palette_cases.json`, usados por
  el test PHP y por un test e2e/ts mínimo que ejecute
  `resources/js/utils/brandPalette.ts` (p. ej. con `node --experimental-strip-types`
  o el runner que ya exista); si no hay runner TS, un test Playwright que
  abra el editor y compare la vista previa con los valores esperados.

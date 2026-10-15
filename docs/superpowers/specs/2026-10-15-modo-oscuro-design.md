# Modo oscuro — diseño

**Fecha:** 2026-10-15
**Estado:** aprobado (opción B: tokens semánticos)

## 1. Objetivo

Ofrecer modo oscuro en toda la interfaz de operación (grilla, configuración,
administración), en las pantallas de autenticación y en el portal del cliente
(`/review/{token}`), con un selector **Claro / Oscuro / Sistema** recordado por
navegador. Los diseños renderizados de los posts (`Pages/Render/Frame.vue` y
`resources/js/Templates/**`) **no cambian**: son la pieza de marca y llevan su
propia paleta.

## 2. Decisiones

| Tema | Decisión |
|---|---|
| Activación | Selector de 3 estados (`light` / `dark` / `system`) en el menú del usuario. Persistencia en `localStorage['theme']`; `system` sigue `prefers-color-scheme` y reacciona a cambios en vivo. |
| Alcance | App interna, login/recuperación, portal del cliente. El portal no tiene selector: aplica la preferencia guardada en ese navegador (si la hay) o el sistema. |
| Técnica | Tailwind `darkMode: 'class'` + **tokens semánticos** como variables CSS. Los componentes usan `bg-surface text-fg border-line`; no se duplican clases `dark:` salvo en colores semánticos (rojo/ámbar/verde) que no tienen token. |
| Paleta oscura | Derivada del azul Saberé, no gris neutro, para conservar identidad. Acentos teal/púrpura iguales en ambos temas. |
| Sin parpadeo | Script inline en `app.blade.php` (antes de `@vite`) que pone `class="dark"` en `<html>` según `localStorage`/`matchMedia`. |

## 3. Tokens

Definidos en `resources/css/app.css` como tripletas RGB (para admitir opacidad
`bg-surface/80`) y expuestos en `tailwind.config.js` con
`rgb(var(--c-x) / <alpha-value>)`.

| Token | Uso | Claro | Oscuro |
|---|---|---|---|
| `surface` | tarjetas, paneles, drawer, modales, nav | `#FFFFFF` | `#1A2A42` |
| `surface-2` | fondo de página, hover de filas, celdas vacías | `#F9FAFB` (gray-50) / `#F3F4F6` fondo | `#0F1A2B` |
| `surface-3` | chips, inputs deshabilitados, cabeceras de tabla | `#F3F4F6` (gray-100) / `#E5E7EB` | `#24385A` |
| `fg` | texto principal | `#111827` (gray-900) | `#F1F5F9` |
| `fg-2` | texto secundario (gray-700/600) | `#374151` | `#CBD5E1` |
| `muted` | texto terciario, iconos (gray-500/400) | `#6B7280` | `#8FA3BF` |
| `line` | bordes suaves (gray-200/100), `divide-*` | `#E5E7EB` | `#2E4366` |
| `line-strong` | bordes de inputs (gray-300/400), `ring` | `#D1D5DB` | `#3B5380` |

Los tokens de marca (`sabere.dark/light/accent/purple`) se mantienen.

**Reglas de migración** (mecánicas, en todos los `.vue` fuera de `Render/` y
`Templates/`):

- `bg-white` → `bg-surface`; `bg-gray-50` → `bg-surface-2`; `bg-gray-100|200` → `bg-surface-3`.
- Fondo de página en `AuthenticatedLayout`/`GuestLayout`/`ReviewLayout`: `bg-surface-2`.
- `text-gray-900|800` → `text-fg`; `text-gray-700|600` → `text-fg-2`; `text-gray-500|400` → `text-muted`.
- `border-gray-200|100`, `divide-gray-*` → `line`; `border-gray-300|400`, `ring-gray-*` → `line-strong`.
- `text-white` sobre fondos de color (botones primarios, badges) **no cambia**.
- `bg-gray-800|900` (botón primario Breeze, chips de red oscuros) → `bg-sabere-dark` en claro y `bg-sabere-accent text-sabere-dark` o `bg-surface-3` en oscuro según el caso (botón primario: `dark:bg-sabere-accent dark:text-sabere-dark`).
- Colores semánticos (`bg-red-50 text-red-700`, ámbar, verde, teal): añadir `dark:bg-red-900/30 dark:text-red-200` (y equivalentes), revisando contraste.
- Overlays `bg-black/50`, `bg-gray-900/50` no cambian.
- Inputs (`TextInput`, selects, textareas, `@tailwindcss/forms`): `bg-surface text-fg border-line-strong placeholder:text-muted focus:border-sabere-accent focus:ring-sabere-accent`; `color-scheme: dark` bajo `.dark` para que los nativos (date, month, checkbox) se vean oscuros.
- Logos SVG generados por inicial y miniaturas de posts no cambian.

## 4. Componentes

- **`resources/js/composables/useTheme.ts`**: estado `theme` (`'light'|'dark'|'system'`), `resolved` (`'light'|'dark'`), `setTheme()`. Lee/escribe `localStorage['theme']`, escucha `matchMedia('(prefers-color-scheme: dark)')` cuando `theme === 'system'`, y alterna la clase `dark` en `document.documentElement`. Singleton (estado de módulo) para que nav y páginas compartan.
- **Selector**: en `AuthenticatedLayout`, dentro del dropdown del usuario (arriba a la derecha), bloque "Tema" con tres botones segmentados (icono sol / luna / monitor) + el mismo bloque en el menú responsive. Sin ruta ni persistencia en servidor.
- **`app.blade.php`**: `<html class="...">` con script inline:
  `const t=localStorage.getItem('theme');if(t==='dark'||(t!=='light'&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')`
  y `<meta name="color-scheme" content="light dark">`.
- **`ReviewLayout`**: usa tokens; sin selector.

## 5. Pruebas y verificación

No hay tests de componentes Vue en el repo; la verificación es:

1. `sail npm run build` sin errores; `sail artisan test` sin regresiones (no se toca PHP).
2. Capturas claro/oscuro, desktop (1280) y móvil (390): grilla con barra de uso y banda de revisión, drawer de post, Configuración → Redes, Administración → Cartera y Planes, login, portal `/review`.
3. Comprobar que `Render/Frame.vue` renderiza idéntico con `.dark` presente (captura de un frame vía `render:post` con la preferencia oscura guardada no debe cambiar: el frame no usa tokens).
4. `grep` final: ningún `bg-white|text-gray-|border-gray-|bg-gray-` fuera de `Render/` y `Templates/`.

## 6. Fuera de alcance

Tema por marca, tema guardado en el perfil del usuario, modo oscuro en los
correos, cambios en los diseños renderizados.

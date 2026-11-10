# Rol gestor (intermedio admin/operador)

Motivación: un operador sin marcas quedaba bloqueado (403 en todo, 422 en
`onboarding/complete`). Decisión del usuario (2026-11-09): rol intermedio
**gestor** — cuenta de agencia que da de alta las marcas de sus clientes.

## Permisos

| Acción | admin | gestor | operador |
|---|---|---|---|
| Ver marca | todas | asignadas | asignadas |
| Crear marca | sí | sí (queda auto-asignado) | no |
| Editar marca/perfil/pilares/estrategias | todas | solo asignadas | no |
| Pausar marca, contactos de aprobación, IA onboarding, destilar pautas | todas | solo asignadas | no |
| Vincular/desvincular operadores a una marca | todas | solo asignadas | no |
| Usuarios (crear/editar/roles) | sí | no | no |
| Catálogo de planes y Cartera | sí | no | no |
| Trabajo diario (grilla, posts, settings de marca) | todas | asignadas | asignadas |

## Cambios

1. **`UserRole::Manager = 'gestor'`**; migración `ALTER TABLE users MODIFY role
   ENUM('admin','gestor','operator') NOT NULL DEFAULT 'operator'` (vía
   `DB::statement`, MySQL enum). `User::isManager()` y `canCreateBrands()`
   (admin||gestor).
2. **`BrandPolicy`**: `view` sin cambios (gestor ve asignadas, como operador);
   `create(User)` → admin||gestor; `update(User,Brand)` → admin || gestor
   asignado; `assignOperators(User,Brand)` → igual que `update`. `manage` (global)
   sigue admin-only.
3. **Middleware `manager`** (`EnsureManager`: admin||gestor) registrado en
   `bootstrap/app.php`. Las rutas `/brands*` (index/create/store/edit/update/
   toggle/onboarding run/apply/contacts/distill) pasan del grupo `admin` a un
   grupo `manager`; cada acción con marca hace `$this->authorize('update',$brand)`
   (o `create`/`assignOperators`). `/users`, `/plans`, `/portfolio` quedan bajo
   `admin`. Nuevas rutas `POST/DELETE /brands/{brand}/operators/{user?}` para
   vincular/desvincular operadores (`assignOperators`).
4. **`BrandController`**: `index` lista solo las marcas asignadas para gestor;
   `store` auto-asigna al creador (`brand_user`). Solo se pueden vincular
   usuarios con `role=operator` (los gestores/admin se ven vía asignación
   manual si hiciera falta, pero no se ofrece en UI).
5. **UI**: selector de rol con "Gestor" en `/users` (solo admin). Tarjeta
   **"Operadores asignados"** en `brands/{id}/edit` (lista de operadores con
   checkbox) visible para admin y gestor asignado. Menú "Administración":
   gestor ve solo "Marcas" (ocultos Usuarios, Planes, Cartera).
6. **Onboarding**: gestor sin marcas recibe el track de creación guiada (mismo
   que admin); gestor con marcas elige crear/ver. Operador sin marcas: pantalla
   amable "Pide a tu administrador o gestor que te asigne una marca" (con botón
   cerrar sesión) en vez del 422 — `complete` sin marca redirige ahí.
7. **`OnboardingController::storeBrand`/`manual`**: auto-asignar al creador
   igual que `brands.store`.
8. **Tests**: enum/migración; matriz de la policy; `brands.index` filtra para
   gestor; `authorize` en cada acción concreta (gestor no asignado → 403);
   vincular operador (ok admin+gestor asignado, 403 gestor ajeno/operador,
   422 si el usuario no es operator); onboarding track gestor; pantalla
   operador-sin-marcas; `store` auto-asigna.
9. **Docs**: AGENTS.md (rol gestor, middleware `manager`, rutas).

# Rol gestor (Fase 5a — permisos)

Motivo: un operador con `brand_user` vacío quedaba bloqueado (403 en todo y
422 "No hay marca a la que ir." en `/onboarding/complete`). Además el usuario
quiere que alguien distinto del admin pueda dar de alta marcas de clientes.

**Rol nuevo `gestor`** entre admin y operador: crea marcas y las administra
por completo, ve solo las suyas (asignadas, incluida la que él crea), puede
vincular/desvincular operadores existentes a sus marcas. NO puede: crear ni
editar usuarios, catálogo de planes ni Cartera.

## Roles finales

| Rol | Ve marcas | Crea marcas | Gestiona marca (perfil/redes/pilares/contactos/IA onboarding) | Asigna operadores a sus marcas | Usuarios / Planes / Cartera | Trabaja en grilla |
|---|---|---|---|---|---|---|
| admin | todas | sí | sí (todas) | sí | sí | sí |
| gestor | asignadas | sí | sí (las suyas) | sí (operadores existentes) | no | sí |
| operador | asignadas | no | no | no | no | sí |

## Datos

- `users.role` pasa a `ENUM('admin','gestor','operator')` — migración con
  `DB::statement('ALTER TABLE users MODIFY COLUMN role …')` (MySQL enum no se
  altera con `$table->enum()->change()` sin doctrine/dbal). `UserRole` enum
  añade `Manager`.
- `User`: `isManager()`; el nombre legible "Gestor" en el formulario de
  usuarios. `isAdmin()` se mantiene estricto (=admin) y se añade
  `isStaff()`/`can('manage-brands')` donde haga falta: admin || gestor.
- Al crear una marca (UI admin `/brands` y onboarding `storeBrand`/`manual`),
  `brand_user` auto-asigna al creador (`syncWithoutDetaching`).

## Permisos (BrandPolicy)

- `view`: sin cambios (admin o asignado). Gestor ve solo asignadas.
- `manage` → renombrar a `administer(User)` solo admin (planes, usuarios,
  cartera); y nuevo `manage(User, Brand $brand)` → admin o gestor asignado.
- `create(User)` → admin o gestor. `assignOperators(User, Brand)` → admin o
  gestor asignado.
- Middleware `admin` se mantiene para `/users`, `/plans`, `/portfolio`;
  nuevo grupo `auth + manager` (alias `manager` → `EnsureManager`: role
  admin|gestor) envuelve las rutas de `/brands*`. Dentro, `can:manage,brand`
  en las rutas con `{brand}` y un check de `create` para POST `/brands`.
- Rutas nuevas: `POST/DELETE /brands/{brand}/users/{user}` (`brands.users.*`)
  con `can:manage,brand`, que solo aceptan usuarios `role=operator`
  (adjuntar gestores/admins sigue siendo vía admin).

## Onboarding

- `OnboardingController`: el track "crear marca" y `Choose` dependen hoy de
  `isAdmin` → pasar a `canCreateBrands` (admin||gestor). Un gestor sin marcas
  recibe el track de creación; con marcas, la elección A/B.
- `complete` sin marca: si el usuario puede crear marcas → wizard; si es
  operador sin marcas → página amable "Pide a tu administrador que te asigne
  una marca" (Inertia, con botón cerrar sesión), en vez del 422.
- `AdminUserSeeder`/`DefaultsSeeder` sin cambios (admin ve todo).
- `users.index` (admin): en la tabla, "Gestor" junto a su nombre y las marcas
  asignadas igual que el operador.

## UI

- `Admin/Users/Form.vue`: etiqueta del rol `gestor` → "Gestor (crea y
  administra sus marcas)".
- `Admin/Brands/Edit.vue`: nueva tarjeta **"Operadores asignados"** — lista de
  usuarios `role=operator` con checkbox asignado; la usa admin y gestor
  (`brands.users.sync` POST con array de ids). Los gestores solo la ven en
  marcas propias.
- `AuthenticatedLayout`: menú "Administración" visible también para gestor,
  pero solo con los enlaces de marcas (`/brands`); ocultar Usuarios/Planes/
  Cartera para gestor.
- `Plans/Show` y demás: sin cambios (la marca activa ya filtra).

## Pruebas

- `UserRoleTest`: enum persiste `gestor`.
- `BrandsAccessTest` (nuevo): gestor ve solo sus marcas en `/brands`; gestor
  puede crear marca (auto-asignado) y editarla; gestor NO puede `/users`,
  `/plans`, `/portfolio` (403); gestor no edita marca ajena (403);
  `brands.users.sync` vincula solo operadores y solo en marca propia;
  operador sin marcas ve la página amable en `/onboarding/complete`.
- Reusar `actingAsAdminFor`/`actingAsOperatorFor`; añadir
  `actingAsManagerFor(?Brand)` en `TestCase`.

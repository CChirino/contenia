# Saberé Marketing

Aplicación de planificación y publicación de contenido de redes sociales de Saberé (Instagram, LinkedIn, TikTok).

Stack: Laravel 12 + Inertia + Vue 3 + TypeScript + Vite + Tailwind + MySQL (Laravel Sail). Tests con PHPUnit.

## Comandos

```bash
# Levantar el entorno (app + mysql)
./vendor/bin/sail up -d

# Migraciones y seeds
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate --seed   # incluye AdminUserSeeder y DefaultsSeeder

# Tests
./vendor/bin/sail artisan test

# Frontend
./vendor/bin/sail npm run dev     # Vite dev server
./vendor/bin/sail npm run build   # build + vue-tsc (typecheck)

# Estilo de código
./vendor/bin/sail pint            # o ./vendor/bin/pint en el host
```

Sin Sail, los comandos equivalentes usan `php artisan` / `npm` directamente en el host.

## Puertos

| Servicio | Puerto | Variable |
|---|---|---|
| App (HTTP) | 5600 | `APP_PORT` |
| MySQL (host) | 3307 | `FORWARD_DB_PORT` |
| Vite dev | 5174 | `VITE_PORT` |

Los puertos 9000, 3306, 5500 y 5173 están ocupados por otros proyectos en esta máquina.

## Acceso

- App: http://localhost:5600
- Admin: `admin@sabere.com` / `password` (creado por `AdminUserSeeder`). No hay registro público.

## Notas

- Locale `es`, timezone `America/Caracas`, faker `es_VE`.
- Paleta Tailwind `sabere.*` (`dark`, `light`, `accent`, `purple`) y fuente Inter.
- Alias `@/*` → `resources/js/*`.

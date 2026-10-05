<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <title>Render</title>
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
        <style>
            html, body { margin: 0; padding: 0; overflow: hidden; }
        </style>
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

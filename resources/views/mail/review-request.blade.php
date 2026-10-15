<x-mail::message>
# Revisión de contenido · {{ $review->brand->name }}

Hola,

El contenido de **{{ $review->brand->name }}** para el período
**{{ $review->from_date->format('d/m') }} – {{ $review->to_date->format('d/m/Y') }}**
está listo para tu revisión: **{{ $postsCount }} publicaciones**.

<x-mail::button :url="$url">
Revisar contenido
</x-mail::button>

Puedes aprobar publicaciones una a una, por semana o todas a la vez, y dejar
comentarios para el equipo.

Este enlace vence el {{ $review->expires_at->format('d/m/Y') }} y es personal:
no lo compartas fuera de tu equipo.

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>

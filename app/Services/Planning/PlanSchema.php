<?php

namespace App\Services\Planning;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostIcon;

class PlanSchema
{
    /**
     * JSON Schema de la grilla mensual: {posts: [...]}.
     *
     * @return array<string, mixed>
     */
    public static function monthly(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'posts' => [
                    'type' => 'array',
                    'items' => self::postSchema(true),
                ],
            ],
            'required' => ['posts'],
            'additionalProperties' => false,
        ];
    }

    /**
     * JSON Schema de un solo post (regeneración): no incluye
     * date/time/network/pillar porque no cambian al regenerar.
     *
     * @return array<string, mixed>
     */
    public static function single(): array
    {
        return self::postSchema(false);
    }

    /**
     * JSON Schema del post redactado a partir de material subido
     * (posts desde archivo): sin background_prompt ni photo_keywords
     * porque el fondo ya existe; el pilar se elige por id.
     *
     * @param  array<int, int>  $pillarIds  Ids de pilares válidos de la marca.
     * @return array<string, mixed>
     */
    public static function fromMedia(array $pillarIds): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'hook' => ['type' => 'string'],
                'copy' => ['type' => 'string'],
                'hashtags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'design_payload' => [
                    'type' => 'object',
                    'properties' => [
                        'slides' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'body' => ['type' => 'string'],
                                ],
                                'required' => ['title', 'body'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['slides'],
                    'additionalProperties' => false,
                ],
                'content_pillar_id' => [
                    'type' => 'integer',
                    'enum' => array_values($pillarIds),
                ],
                'icon' => [
                    'type' => 'string',
                    'enum' => PostIcon::values(),
                ],
            ],
            'required' => ['hook', 'copy', 'hashtags', 'design_payload', 'content_pillar_id', 'icon'],
            'additionalProperties' => false,
        ];
    }

    /**
     * JSON Schema de la descripción de material (modelo de visión).
     *
     * @return array<string, mixed>
     */
    public static function mediaDescription(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'description' => ['type' => 'string'],
                'elements' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'people' => ['type' => 'string'],
                'visible_text' => ['type' => 'string'],
                'mood' => ['type' => 'string'],
                'suggested_angle' => ['type' => 'string'],
            ],
            'required' => ['description'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function postSchema(bool $withSchedule): array
    {
        $properties = [
            'format' => [
                'type' => 'string',
                'enum' => array_map(fn (PostFormat $f) => $f->value, PostFormat::cases()),
            ],
            'hook' => ['type' => 'string'],
            'copy' => ['type' => 'string'],
            'hashtags' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'design_payload' => [
                'type' => 'object',
                'properties' => [
                    'slides' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'body' => ['type' => 'string'],
                            ],
                            'required' => ['title', 'body'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['slides'],
                'additionalProperties' => false,
            ],
            'background_prompt' => [
                'type' => 'string',
                'description' => 'En inglés: escena FOTOGRÁFICA de respaldo sin personas ni letreros ni pantallas con texto, máx. 25 palabras',
            ],
            'photo_keywords' => [
                'type' => 'string',
                'description' => 'En inglés, 2-4 palabras para buscar una foto de stock de ambiente (escena escolar u oficina)',
            ],
            'icon' => [
                'type' => 'string',
                'enum' => PostIcon::values(),
            ],
        ];

        $required = ['format', 'hook', 'copy', 'hashtags', 'design_payload', 'background_prompt', 'photo_keywords', 'icon'];

        if ($withSchedule) {
            $properties = array_merge([
                'date' => [
                    'type' => 'string',
                    'description' => 'Fecha en formato YYYY-MM-DD',
                    'pattern' => '^\d{4}-\d{2}-\d{2}$',
                ],
                'time' => [
                    'type' => 'string',
                    'description' => 'Hora en formato HH:MM (24 h)',
                    'pattern' => '^\d{2}:\d{2}$',
                ],
                'network' => [
                    'type' => 'string',
                    'enum' => array_map(fn (Network $n) => $n->value, Network::cases()),
                ],
                'pillar' => [
                    'type' => 'string',
                    'description' => 'Nombre exacto del pilar de contenido',
                ],
            ], $properties);

            $required = array_merge(['date', 'time', 'network', 'pillar'], $required);
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }
}

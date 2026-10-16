<?php

namespace App\Services\Onboarding;

use App\Enums\Network;
use App\Enums\PostFormat;

/**
 * Esquemas JSON estrictos para las llamadas LLM de la Fase 4
 * (mismo patrón que PlanSchema: tool emit_result).
 */
class OnboardingSchema
{
    /**
     * Propuesta de onboarding: perfil, pilares, estrategias y paleta.
     *
     * @param  array<int, Network>  $networks  redes permitidas por el plan
     * @return array<string, mixed>
     */
    public static function proposal(array $networks): array
    {
        $networkValues = array_map(fn (Network $n) => $n->value, $networks);
        $formatsByNetwork = [];
        foreach ($networks as $network) {
            $formatsByNetwork[$network->value] = array_map(
                fn (PostFormat $f) => $f->value,
                PostFormat::allowedFor($network)
            );
        }

        return [
            'type' => 'object',
            'properties' => [
                'description' => [
                    'type' => 'string',
                    'description' => 'Descripción de la marca, máx. 300 caracteres',
                    'maxLength' => 300,
                ],
                'tone_of_voice' => [
                    'type' => 'string',
                    'description' => 'Tono de voz de la marca en 1-2 frases',
                ],
                'audience' => [
                    'type' => 'string',
                    'description' => 'Audiencia objetivo en una frase',
                ],
                'cta' => [
                    'type' => 'string',
                    'description' => 'Llamado a la acción típico de la marca',
                ],
                'base_hashtags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'maxItems' => 8,
                    'description' => 'Hashtags base de la marca, con #',
                ],
                'pillars' => [
                    'type' => 'array',
                    'minItems' => 4,
                    'maxItems' => 6,
                    'description' => '4-6 pilares de contenido; los pesos deben sumar 100',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'weight' => ['type' => 'integer', 'minimum' => 1],
                        ],
                        'required' => ['name', 'description', 'weight'],
                        'additionalProperties' => false,
                    ],
                ],
                'strategies' => [
                    'type' => 'array',
                    'description' => 'Una estrategia por cada red del plan: '.implode(', ', $networkValues)
                        .'. Formatos permitidos por red: '.json_encode($formatsByNetwork),
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'network' => ['type' => 'string', 'enum' => $networkValues],
                            'posts_per_week' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 7],
                            'formats' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'focus' => ['type' => 'string'],
                        ],
                        'required' => ['network', 'posts_per_week', 'formats', 'focus'],
                        'additionalProperties' => false,
                    ],
                ],
                'palette' => [
                    'type' => 'object',
                    'description' => 'Paleta de la marca en hex #RRGGBB; elige entre los colores detectados o propone con contraste razonable',
                    'properties' => [
                        'dark' => ['type' => 'string'],
                        'light' => ['type' => 'string'],
                        'accent' => ['type' => 'string'],
                        'secondary' => ['type' => 'string'],
                    ],
                    'required' => ['dark', 'light', 'accent', 'secondary'],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['description', 'tone_of_voice', 'audience', 'cta', 'base_hashtags', 'pillars', 'strategies', 'palette'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Destilado de feedback → pautas nuevas, reforzadas y a retirar.
     *
     * @return array<string, mixed>
     */
    public static function feedback(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'new' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => [
                                'type' => 'string',
                                'maxLength' => 200,
                                'description' => 'Pauta concreta y accionable, máx. 200 caracteres',
                            ],
                        ],
                        'required' => ['text'],
                        'additionalProperties' => false,
                    ],
                ],
                'reinforce' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Ids de pautas existentes que la evidencia refuerza',
                ],
                'retire' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['id', 'reason'],
                        'additionalProperties' => false,
                    ],
                    'description' => 'Pautas existentes que la evidencia contradice o vuelve obsoletas',
                ],
            ],
            'required' => ['new', 'reinforce', 'retire'],
            'additionalProperties' => false,
        ];
    }
}

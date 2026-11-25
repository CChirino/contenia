<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * brands.palette pasa de {dark, light, accent, secondary(string), purple?}
 * a {primary: [3 hex], secondary: [0–3 hex]}. La forma antigua se reconoce
 * porque palette.primary no es array. down() reescribe los roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('brands')->orderBy('id')->each(function ($b) {
            $p = json_decode((string) $b->palette, true) ?: [];
            if (is_array($p['primary'] ?? null)) {
                return; // ya está en la forma nueva
            }

            $hex = fn ($c) => is_string($c) && preg_match('/^#?[0-9a-fA-F]{6}$/', $c)
                ? '#'.strtoupper(ltrim($c, '#'))
                : null;

            $primary = array_values(array_unique(array_filter([
                $hex($p['accent'] ?? null),
                $hex($p['secondary'] ?? null),
                $hex($p['dark'] ?? null),
            ])));
            $secondary = array_values(array_unique(array_filter([
                $hex($p['light'] ?? null),
                $hex($p['purple'] ?? null),
            ])));

            while (count($primary) < 3 && $secondary !== []) {
                $primary[] = array_shift($secondary);
            }
            $fallback = ['#4F46E5', '#8B5CF6', '#14122B'];
            while (count($primary) < 3) {
                $primary[] = $fallback[count($primary)];
            }

            DB::table('brands')->where('id', $b->id)->update([
                'palette' => json_encode([
                    'primary' => array_slice($primary, 0, 3),
                    'secondary' => array_slice(array_values(array_diff($secondary, $primary)), 0, 3),
                ]),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('brands')->orderBy('id')->each(function ($b) {
            $p = json_decode((string) $b->palette, true) ?: [];
            if (! is_array($p['primary'] ?? null)) {
                return; // ya está en la forma antigua
            }

            DB::table('brands')->where('id', $b->id)->update([
                'palette' => json_encode([
                    'dark' => $p['primary'][2] ?? '#1A2A42',
                    'light' => $p['secondary'][0] ?? '#F8F4EC',
                    'accent' => $p['primary'][0] ?? '#27C2A5',
                    'secondary' => $p['primary'][1] ?? '#7854D2',
                ]),
            ]);
        });
    }
};

<?php

namespace App\Support;

/**
 * Paleta de marca por importancia: {primary: [3 hex], secondary: [0–3 hex]}.
 *
 * normalize() acepta la forma nueva o la antigua por roles
 * {dark, light, accent, secondary(string), purple?} y devuelve siempre
 * la forma nueva saneada (nunca lanza).
 *
 * roles() deriva lo que consumen las plantillas/portal (--brand-*):
 *  1. accent  = Primario 1.
 *  2. dark    = el color de menor luminancia (excepto accent) con
 *              contraste vs #FFFFFF ≥ 4.5; si ninguno, accent mezclado
 *              65 % con negro, y si aún no llega → #141414.
 *  3. light   = el color de mayor luminancia (excepto accent y dark)
 *              con contraste vs dark ≥ 4.5; si no, #FFFFFF.
 *  4. secondary = primer color libre de [P2, P3, S1, S2, S3]; si no hay,
 *              accent mezclado 45 % con blanco.
 *
 * Contraste y luminancia: fórmula WCAG 2.1 (sRGB linealizado).
 */
class BrandPalette
{
    /** @return array{primary: array<int, string>, secondary: array<int, string>} */
    public static function normalize(mixed $palette): array
    {
        $palette = is_array($palette) ? $palette : [];

        if (is_array($palette['primary'] ?? null)) {
            $primary = array_values(array_filter(array_map(
                fn ($c) => self::hex($c),
                $palette['primary']
            )));
            $secondary = array_values(array_filter(array_map(
                fn ($c) => self::hex($c),
                is_array($palette['secondary'] ?? null) ? $palette['secondary'] : []
            )));
        } else {
            // Forma antigua: accent, secondary(string), dark → primarios;
            // light (+ purple) → secundarios.
            $primary = array_values(array_unique(array_filter([
                self::hex($palette['accent'] ?? null),
                self::hex($palette['secondary'] ?? null),
                self::hex($palette['dark'] ?? null),
            ])));
            $secondary = array_values(array_filter(array_unique([
                self::hex($palette['light'] ?? null),
                self::hex($palette['purple'] ?? null),
            ])));
        }

        // Rellena hasta 3 primarios con secundarios sobrantes o derivados.
        while (count($primary) < 3 && $secondary !== []) {
            $primary[] = array_shift($secondary);
        }
        while (count($primary) < 3) {
            $primary[] = self::fallbackPrimary(count($primary));
        }
        $primary = array_slice(array_values($primary), 0, 3);
        $secondary = array_slice(array_values(array_unique(array_diff($secondary, $primary))), 0, 3);

        return ['primary' => $primary, 'secondary' => $secondary];
    }

    /** @return array{dark: string, light: string, accent: string, secondary: string} */
    public static function roles(mixed $palette): array
    {
        $p = self::normalize($palette);
        $all = array_values(array_unique([...$p['primary'], ...$p['secondary']]));

        $accent = $p['primary'][0];

        $dark = null;
        $bestDarkLum = PHP_FLOAT_MAX;
        foreach ($all as $c) {
            if ($c === $accent) {
                continue;
            }
            $lum = self::luminance($c);
            if ($lum < $bestDarkLum && self::contrast($c, '#FFFFFF') >= 4.5) {
                $bestDarkLum = $lum;
                $dark = $c;
            }
        }
        if ($dark === null) {
            $mixed = self::mix($accent, '#000000', 0.65);
            $dark = self::contrast($mixed, '#FFFFFF') >= 4.5 ? $mixed : '#141414';
        }

        $light = '#FFFFFF';
        $bestLightLum = -1.0;
        foreach ($all as $c) {
            if ($c === $accent || $c === $dark) {
                continue;
            }
            $lum = self::luminance($c);
            if ($lum > $bestLightLum && self::contrast($c, $dark) >= 4.5) {
                $bestLightLum = $lum;
                $light = $c;
            }
        }

        $secondary = null;
        foreach ([$p['primary'][1], $p['primary'][2], ...$p['secondary']] as $c) {
            if ($c !== $accent && $c !== $dark && $c !== $light) {
                $secondary = $c;
                break;
            }
        }
        $secondary ??= self::mix($accent, '#FFFFFF', 0.45);

        return [
            'dark' => $dark,
            'light' => $light,
            'accent' => $accent,
            'secondary' => $secondary,
        ];
    }

    public static function hex(mixed $c): ?string
    {
        if (! is_string($c)) {
            return null;
        }
        $c = trim($c);
        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $c)) {
            return '#'.strtoupper(ltrim($c, '#'));
        }

        return null;
    }

    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);
        $lin = fn ($v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    }

    public static function contrast(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);
        if ($l1 < $l2) {
            [$l1, $l2] = [$l2, $l1];
        }

        return ($l1 + 0.05) / ($l2 + 0.05);
    }

    public static function mix(string $a, string $b, float $t): string
    {
        [$r1, $g1, $b1] = self::rgb($a);
        [$r2, $g2, $b2] = self::rgb($b);

        return sprintf('#%02X%02X%02X',
            (int) round($r1 * (1 - $t) + $r2 * $t),
            (int) round($g1 * (1 - $t) + $g2 * $t),
            (int) round($b1 * (1 - $t) + $b2 * $t)
        );
    }

    /** @return array{0: int, 1: int, 2: int} */
    public static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    private static function fallbackPrimary(int $i): string
    {
        return ['#4F46E5', '#8B5CF6', '#14122B'][$i] ?? '#14122B';
    }
}

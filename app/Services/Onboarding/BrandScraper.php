<?php

namespace App\Services\Onboarding;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recolección determinista para el onboarding de marca (spec §8.1):
 * descarga la home y hasta 5 páginas internas, extrae texto visible,
 * title, meta description y los colores de marca (CSS + logo con GD).
 * Nunca lanza: devuelve ok=false solo si ni la home respondió.
 */
class BrandScraper
{
    private const MAX_PAGES = 5;

    private const MAX_CHARS = 8000;

    private const TIME_BUDGET = 30.0;

    private const PRIORITY_PATH = 'about|nosotros|servicios|blog|productos|equipo';

    /**
     * @return array{ok: bool, error?: string, title: ?string, meta_description: ?string, home_text: string, pages: array<int, array{url: string, text: string}>, colors: array<int, string>, logo_img_url?: string}
     */
    public function collect(string $url): array
    {
        $result = [
            'ok' => false,
            'title' => null,
            'meta_description' => null,
            'home_text' => '',
            'pages' => [],
            'colors' => [],
        ];

        $start = microtime(true);

        try {
            $response = Http::timeout(10)->get($url);
        } catch (Throwable $e) {
            return [...$result, 'error' => 'No se pudo descargar la home: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return [...$result, 'error' => "La home respondió HTTP {$response->status()}."];
        }

        $host = parse_url($url, PHP_URL_HOST);

        [$doc, $xpath] = $this->parse($response->body());
        if ($doc === null || $xpath === null) {
            return [...$result, 'error' => 'No se pudo interpretar el HTML de la home.'];
        }

        $result['ok'] = true;
        $result['title'] = $this->title($xpath);
        $result['meta_description'] = $this->metaDescription($xpath);

        // Colores antes de quitar los <style> del documento.
        $colorFreq = $this->extractCssColors($xpath);

        // Logo: <img> con "logo" en src/alt, mismo host o data URI.
        $logo = $this->findLogo($xpath, $url, $host);
        if ($logo !== null) {
            $result['logo_img_url'] = $logo;
            if (microtime(true) - $start < self::TIME_BUDGET) {
                foreach ($this->logoColors($logo) as $hex) {
                    $colorFreq[$hex] = ($colorFreq[$hex] ?? 0) + 8;
                }
            }
        }

        $links = $this->internalLinks($xpath, $url, $host);

        // Texto visible de la home, sin script/style/noscript.
        $this->stripNoise($xpath);
        $result['home_text'] = $this->visibleText($doc);

        arsort($colorFreq);
        $result['colors'] = array_slice(array_keys($colorFreq), 0, 9);

        foreach (array_slice($links, 0, self::MAX_PAGES) as $pageUrl) {
            if (microtime(true) - $start > self::TIME_BUDGET) {
                break;
            }

            try {
                $page = Http::timeout(10)->get($pageUrl);
            } catch (Throwable) {
                continue;
            }

            if (! $page->successful()) {
                continue;
            }

            [$pageDoc, $pageXpath] = $this->parse($page->body());
            if ($pageDoc === null || $pageXpath === null) {
                continue;
            }

            $this->stripNoise($pageXpath);
            $result['pages'][] = [
                'url' => $pageUrl,
                'text' => $this->visibleText($pageDoc),
            ];
        }

        return $result;
    }

    /**
     * @return array{0: ?DOMDocument, 1: ?DOMXPath}
     */
    private function parse(string $html): array
    {
        try {
            $doc = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            // Sin <meta charset> loadHTML asume ISO-8859-1: el PI fija UTF-8.
            $doc->loadHTML('<?xml encoding="UTF-8"?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return [$doc, new DOMXPath($doc)];
        } catch (Throwable) {
            return [null, null];
        }
    }

    private function title(DOMXPath $xpath): ?string
    {
        $node = $xpath->query('//title')->item(0);
        $title = trim((string) $node?->textContent);

        return $title === '' ? null : $title;
    }

    private function metaDescription(DOMXPath $xpath): ?string
    {
        foreach ($xpath->query('//meta') as $meta) {
            if (! $meta instanceof DOMElement) {
                continue;
            }
            if (Str::lower($meta->getAttribute('name')) === 'description') {
                $content = trim($meta->getAttribute('content'));

                return $content === '' ? null : $content;
            }
        }

        return null;
    }

    /**
     * Elimina script, style y noscript para el texto visible.
     */
    private function stripNoise(DOMXPath $xpath): void
    {
        foreach ($xpath->query('//script | //style | //noscript') as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * Texto visible del documento, espacios colapsados, ≤ MAX_CHARS.
     */
    private function visibleText(DOMDocument $doc): string
    {
        $text = $doc->getElementsByTagName('body')->item(0)?->textContent
            ?? $doc->documentElement?->textContent
            ?? '';

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return Str::limit($text, self::MAX_CHARS, '');
    }

    /**
     * Enlaces internos del mismo host; primero las rutas prioritarias
     * (about, nosotros, servicios, blog, productos, equipo).
     *
     * @return array<int, string>
     */
    private function internalLinks(DOMXPath $xpath, string $baseUrl, ?string $host): array
    {
        if ($host === null) {
            return [];
        }

        $links = [];
        foreach ($xpath->query('//a[@href]') as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#')
                || Str::startsWith($href, ['mailto:', 'tel:', 'javascript:', 'data:'])) {
                continue;
            }

            $absolute = $this->absoluteUrl($href, $baseUrl);
            if ($absolute === null) {
                continue;
            }

            // Solo mismo host; sin fragmentos ni query.
            $parts = parse_url($absolute);
            if (($parts['host'] ?? null) !== $host) {
                continue;
            }

            $clean = ($parts['scheme'] ?? 'https').'://'.$parts['host']
                .(isset($parts['port']) ? ':'.$parts['port'] : '')
                .($parts['path'] ?? '/');

            if (rtrim($clean, '/') === rtrim((string) parse_url($baseUrl, PHP_URL_SCHEME).'://'.$host.parse_url($baseUrl, PHP_URL_PATH), '/')) {
                continue; // la home ya la tenemos
            }

            $links[$clean] = true;
        }

        $all = array_keys($links);

        usort($all, function (string $a, string $b): int {
            $pa = preg_match('/'.self::PRIORITY_PATH.'/i', (string) parse_url($a, PHP_URL_PATH));
            $pb = preg_match('/'.self::PRIORITY_PATH.'/i', (string) parse_url($b, PHP_URL_PATH));

            return $pb <=> $pa;
        });

        return $all;
    }

    private function absoluteUrl(string $href, string $baseUrl): ?string
    {
        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }

        $base = parse_url($baseUrl);
        if (! isset($base['scheme'], $base['host'])) {
            return null;
        }

        $root = $base['scheme'].'://'.$base['host'].(isset($base['port']) ? ':'.$base['port'] : '');

        if (str_starts_with($href, '//')) {
            return $base['scheme'].':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $root.$href;
        }

        $basePath = rtrim(dirname((string) ($base['path'] ?? '/')), '/').'/';

        return $root.$basePath.$href;
    }

    /**
     * Colores hex de <style>, style="" y bgcolor; filtra neutros
     * (sat < 0.15 o luz fuera de 0.08–0.95) y devuelve frecuencias.
     *
     * @return array<string, int> hex (#RRGGBB) => frecuencia
     */
    private function extractCssColors(DOMXPath $xpath): array
    {
        $haystack = '';

        foreach ($xpath->query('//style') as $style) {
            $haystack .= ' '.$style->textContent;
        }
        foreach ($xpath->query('//*[@style]') as $node) {
            $haystack .= ' '.$node->getAttribute('style');
        }
        foreach ($xpath->query('//*[@bgcolor]') as $node) {
            $haystack .= ' bgcolor:#'.ltrim($node->getAttribute('bgcolor'), '#');
        }

        preg_match_all('/#?([0-9a-fA-F]{6})\b/', $haystack, $matches);

        $freq = [];
        foreach ($matches[1] as $hex) {
            $hex = '#'.Str::upper($hex);
            [$h, $s, $l] = $this->hexToHsl($hex);
            if ($s < 0.15 || $l < 0.08 || $l > 0.95) {
                continue;
            }
            $freq[$hex] = ($freq[$hex] ?? 0) + 1;
        }

        arsort($freq);

        return array_slice($freq, 0, 6, true);
    }

    /**
     * URL del logo: <img> con "logo" en src o alt, mismo host o data URI.
     */
    private function findLogo(DOMXPath $xpath, string $baseUrl, ?string $host): ?string
    {
        foreach ($xpath->query('//img[@src]') as $img) {
            if (! $img instanceof DOMElement) {
                continue;
            }

            $src = trim($img->getAttribute('src'));
            $alt = $img->getAttribute('alt');

            if (! Str::contains(Str::lower($src.' '.$alt), 'logo')) {
                continue;
            }

            if (str_starts_with($src, 'data:image/')) {
                return $src;
            }

            $absolute = $this->absoluteUrl($src, $baseUrl);
            if ($absolute === null) {
                continue;
            }

            if ($host !== null && parse_url($absolute, PHP_URL_HOST) === $host) {
                return $absolute;
            }
        }

        return null;
    }

    /**
     * Los 3 colores dominantes del logo con GD: muestreo cada 4 px,
     * ignora píxeles con s<0.20 o l>0.90, cuantiza a pasos de 32.
     * Devuelve [] si GD no está o la imagen no se puede leer.
     *
     * @return array<int, string>
     */
    public function logoColors(string $logoUrl): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return [];
        }

        try {
            if (str_starts_with($logoUrl, 'data:image/')) {
                $encoded = substr($logoUrl, (int) strpos($logoUrl, ',') + 1);
                $bytes = base64_decode($encoded, true);
            } else {
                $bytes = Http::timeout(10)->get($logoUrl)->throw()->body();
            }

            if ($bytes === false || $bytes === '') {
                return [];
            }

            $image = @imagecreatefromstring($bytes);
            if ($image === false) {
                return [];
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $freq = [];

            for ($x = 0; $x < $width; $x += 4) {
                for ($y = 0; $y < $height; $y += 4) {
                    $rgb = imagecolorat($image, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    [, $s, $l] = $this->rgbToHsl($r, $g, $b);
                    if ($s < 0.20 || $l > 0.90) {
                        continue;
                    }

                    $qr = (int) round($r / 32) * 32;
                    $qg = (int) round($g / 32) * 32;
                    $qb = (int) round($b / 32) * 32;
                    $key = sprintf('%02X%02X%02X', min(255, $qr), min(255, $qg), min(255, $qb));
                    $freq[$key] = ($freq[$key] ?? 0) + 1;
                }
            }

            imagedestroy($image);

            arsort($freq);

            return array_map(fn (string $hex) => '#'.$hex, array_slice(array_keys($freq), 0, 3));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{0: float, 1: float, 2: float} [h, s, l] — h en 0–360, s/l en 0–1
     */
    private function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return $this->rgbToHsl(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function rgbToHsl(int $r, int $g, int $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => fmod(($g - $b) / $d + ($g < $b ? 6 : 0), 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h * 60, $s, $l];
    }
}

<?php

namespace App\Services\Onboarding;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Modo "perfil social" del onboarding: marcas sin web cuya presencia
 * principal es una red. Instagram y Facebook exponen og:title,
 * og:description y og:image sin login (solo con un User-Agent simple:
 * con uno de Chrome completo Instagram omite los meta). TikTok arma la
 * página con JS y LinkedIn responde 403: para ellas se devuelve ok=false
 * y la propuesta se apoya en los posts de ejemplo.
 *
 * Devuelve la misma forma que BrandScraper::collect (sin CSS ni páginas
 * internas) más source=social, network, profile_url, stats y
 * profile_image_url. Nunca lanza.
 */
class SocialProfile
{
    private const USER_AGENT = 'Mozilla/5.0';

    public const LABELS = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'linkedin' => 'LinkedIn',
    ];

    private const READABLE = ['instagram', 'facebook'];

    public function __construct(private BrandScraper $scraper) {}

    /**
     * Detecta la red a partir del sitio web o, si no hay web, del
     * usuario social (`@x`/`x` → Instagram, o una URL social completa).
     * Una web normal devuelve null: sigue el flujo de BrandScraper.
     *
     * @return array{network: string, url: string, handle: ?string}|null
     */
    public static function detect(?string $websiteUrl, ?string $handle = null): ?array
    {
        $websiteUrl = trim((string) $websiteUrl);

        if ($websiteUrl !== '') {
            return self::fromUrl($websiteUrl);
        }

        $handle = trim((string) $handle);
        if ($handle === '') {
            return null;
        }

        if ($fromUrl = self::fromUrl($handle)) {
            return $fromUrl;
        }

        $user = ltrim($handle, '@');
        if (! preg_match('/^[A-Za-z0-9._]{1,30}$/', $user)) {
            return null;
        }

        return ['network' => 'instagram', 'url' => "https://www.instagram.com/{$user}/", 'handle' => $user];
    }

    /**
     * @return array{network: string, url: string, handle: ?string}|null
     */
    private static function fromUrl(string $url): ?array
    {
        $normalized = preg_match('#^https?://#i', $url) ? $url : 'https://'.$url;
        $host = Str::lower((string) parse_url($normalized, PHP_URL_HOST));
        $host = (string) preg_replace('/^(www\.|m\.|mobile\.|web\.)/', '', $host);
        $path = trim((string) parse_url($normalized, PHP_URL_PATH), '/');
        $first = explode('/', $path)[0] ?? '';

        $network = match (true) {
            $host === 'instagram.com' => 'instagram',
            in_array($host, ['facebook.com', 'fb.com'], true) => 'facebook',
            $host === 'tiktok.com' => 'tiktok',
            $host === 'linkedin.com' => 'linkedin',
            default => null,
        };

        if ($network === null) {
            return null;
        }

        $handle = match ($network) {
            'tiktok' => ltrim($first, '@') ?: null,
            'linkedin' => explode('/', $path)[1] ?? null,
            default => $first ?: null,
        };

        return ['network' => $network, 'url' => $normalized, 'handle' => $handle];
    }

    /**
     * @param  array{network: string, url: string, handle: ?string}  $detected
     * @return array<string, mixed>
     */
    public function read(array $detected): array
    {
        $label = self::LABELS[$detected['network']];

        $result = [
            'ok' => false,
            'source' => 'social',
            'network' => $detected['network'],
            'profile_url' => $detected['url'],
            'title' => null,
            'meta_description' => null,
            'home_text' => '',
            'pages' => [],
            'colors' => [],
        ];

        $unreadable = [
            ...$result,
            'error' => "No pudimos leer el perfil de {$label} (requiere iniciar sesión). "
                .'Pega 3–5 captions reales para que la IA aprenda el tono.',
        ];

        if (! in_array($detected['network'], self::READABLE, true)) {
            return $unreadable;
        }

        try {
            $response = Http::withUserAgent(self::USER_AGENT)->timeout(10)->get($detected['url']);
        } catch (Throwable) {
            return $unreadable;
        }

        if (! $response->successful()) {
            return $unreadable;
        }

        $html = $response->body();
        $title = $this->og($html, 'og:title');
        $description = $this->og($html, 'og:description');
        $image = $this->og($html, 'og:image');

        if ($title === null && $description === null) {
            return $unreadable;
        }

        $result['ok'] = true;
        $result['title'] = $title !== null
            ? trim((string) preg_replace('/\s*[•|·-]\s*(Instagram|Facebook)\b.*$/u', '', $title))
            : null;

        if ($description !== null) {
            // "14K Followers, 133 Following, 197 Posts - See Instagram photos…"
            if (preg_match('/^(.*?(?:Followers|seguidores|Posts|Publicaciones)[^-]*?)\s+-\s+/iu', $description, $m)) {
                $result['stats'] = trim($m[1]);
            }
            $result['meta_description'] = $description;
        }

        if ($image !== null) {
            $result['profile_image_url'] = $image;
            $result['colors'] = $this->scraper->logoColors($image);
        }

        return $result;
    }

    private function og(string $html, string $property): ?string
    {
        $pattern = '/<meta[^>]+property=["\']'.preg_quote($property, '/').'["\'][^>]*content=["\']([^"\']*)["\']/i';

        if (! preg_match($pattern, $html, $m)) {
            return null;
        }

        $value = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value === '' ? null : $value;
    }
}

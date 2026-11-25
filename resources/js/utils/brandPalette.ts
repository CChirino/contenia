/**
 * Misma regla que App\Support\BrandPalette (PHP). Mantener en paridad:
 * los casos fijos de tests/fixtures/palette_cases.json validan ambas.
 */

export interface PaletteInput {
    primary?: (string | null)[];
    secondary?: (string | null)[];
    dark?: string;
    light?: string;
    accent?: string;
    purple?: string;
}

export interface PaletteNormalized {
    primary: string[];
    secondary: string[];
}

export interface PaletteRoles {
    dark: string;
    light: string;
    accent: string;
    secondary: string;
}

export function hex(color: unknown): string | null {
    if (typeof color !== 'string') return null;
    const c = color.trim();
    return /^#?[0-9a-fA-F]{6}$/.test(c) ? `#${c.replace('#', '').toUpperCase()}` : null;
}

const FALLBACK_PRIMARY = ['#4F46E5', '#8B5CF6', '#14122B'];

export function normalizePalette(palette: PaletteInput | null | undefined): PaletteNormalized {
    const p = palette ?? {};
    let primary: string[];
    let secondary: string[];

    if (Array.isArray(p.primary)) {
        primary = p.primary.map(hex).filter((c): c is string => !!c);
        secondary = (Array.isArray(p.secondary) ? p.secondary : [])
            .map(hex)
            .filter((c): c is string => !!c);
    } else {
        // Forma antigua: accent, secondary(string), dark → primarios;
        // light (+ purple) → secundarios.
        primary = [...new Set([hex(p.accent), hex(p.secondary as unknown as string), hex(p.dark)].filter(Boolean) as string[])];
        secondary = [...new Set([hex(p.light), hex(p.purple)].filter(Boolean) as string[])];
    }

    while (primary.length < 3 && secondary.length) primary.push(secondary.shift()!);
    while (primary.length < 3) primary.push(FALLBACK_PRIMARY[primary.length] ?? '#14122B');

    primary = primary.slice(0, 3);
    secondary = [...new Set(secondary.filter((c) => !primary.includes(c)))].slice(0, 3);

    return { primary, secondary };
}

export function rgb(c: string): [number, number, number] {
    const h = c.replace('#', '');
    return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
}

export function luminance(c: string): number {
    const [r, g, b] = rgb(c).map((v) => {
        const s = v / 255;
        return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrast(a: string, b: string): number {
    let l1 = luminance(a);
    let l2 = luminance(b);
    if (l1 < l2) [l1, l2] = [l2, l1];
    return (l1 + 0.05) / (l2 + 0.05);
}

export function mix(a: string, b: string, t: number): string {
    const [r1, g1, b1] = rgb(a);
    const [r2, g2, b2] = rgb(b);
    const ch = (x: number, y: number) => Math.round(x * (1 - t) + y * t).toString(16).padStart(2, '0').toUpperCase();
    return `#${ch(r1, r2)}${ch(g1, g2)}${ch(b1, b2)}`;
}

export function paletteRoles(palette: PaletteInput | null | undefined): PaletteRoles {
    const p = normalizePalette(palette);
    const all = [...new Set([...p.primary, ...p.secondary])];

    const accent = p.primary[0];

    let dark: string | null = null;
    let bestDarkLum = Infinity;
    for (const c of all) {
        if (c === accent) continue;
        const lum = luminance(c);
        if (lum < bestDarkLum && contrast(c, '#FFFFFF') >= 4.5) {
            bestDarkLum = lum;
            dark = c;
        }
    }
    if (dark === null) {
        const mixed = mix(accent, '#000000', 0.65);
        dark = contrast(mixed, '#FFFFFF') >= 4.5 ? mixed : '#141414';
    }

    let light = '#FFFFFF';
    let bestLightLum = -1;
    for (const c of all) {
        if (c === accent || c === dark) continue;
        const lum = luminance(c);
        if (lum > bestLightLum && contrast(c, dark) >= 4.5) {
            bestLightLum = lum;
            light = c;
        }
    }

    let secondary: string | null = null;
    for (const c of [p.primary[1], p.primary[2], ...p.secondary]) {
        if (c && c !== accent && c !== dark && c !== light) {
            secondary = c;
            break;
        }
    }
    secondary ??= mix(accent, '#FFFFFF', 0.45);

    return { dark, light, accent, secondary };
}

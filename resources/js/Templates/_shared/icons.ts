// Iconos SVG estilo Lucide para el círculo de marca (viewBox 24,
// stroke currentColor, fill none, stroke-width 2). Clave desconocida → lightbulb.
const ICONS: Record<string, string[]> = {
    fingerprint: [
        'M5 12a7 7 0 0 1 14 0',
        'M8.5 12a3.5 3.5 0 0 1 7 0',
        'M12 15v4',
        'M9 18.5c-.5 1-1.5 2-2.5 2.5',
        'M15 18.5c.5 1 1.5 2 2.5 2.5',
    ],
    checklist: [
        'M3 6l2 2 4-4',
        'M3 13l2 2 4-4',
        'M11 6h10',
        'M11 13h10',
        'M11 20h10',
    ],
    calendar: [
        'M8 3v4',
        'M16 3v4',
        'M4 9h16',
        'M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z',
    ],
    chart: [
        'M6 20v-6',
        'M12 20V8',
        'M18 20v-10',
        'M3 20h18',
    ],
    book: [
        'M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14z',
        'M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5',
    ],
    bell: [
        'M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8',
        'M13.7 21a2 2 0 0 1-3.4 0',
    ],
    chat: [
        'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
    ],
    clock: [
        'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z',
        'M12 6v6l4 2',
    ],
    shield: [
        'M12 3l7 3v5c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-3z',
    ],
    lightbulb: [
        'M9 18h6',
        'M10 22h4',
        'M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.5 1 2.3h6c0-.8.4-1.8 1-2.3A7 7 0 0 0 12 2z',
    ],
    users: [
        'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        'M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2',
        'M23 21v-2a4 4 0 0 0-3-3.87',
        'M16 3.13a4 4 0 0 1 0 7.75',
    ],
    device: [
        'M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z',
        'M12 18h.01',
    ],
};

export function iconPaths(name?: string | null): string[] {
    return ICONS[name ?? ''] ?? ICONS.lightbulb;
}

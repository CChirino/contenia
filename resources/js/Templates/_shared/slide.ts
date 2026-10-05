export interface SlideContent {
    title: string;
    body: string;
}

/**
 * Slide i (0-based) del post; si falta el body usa el copy truncado a 180 chars.
 */
export function slideAt(
    post: { hook: string; copy: string; design_payload?: { slides?: { title?: string; body?: string }[] } | null },
    index: number,
): SlideContent {
    const slide = post.design_payload?.slides?.[index];

    return {
        title: slide?.title ?? post.hook,
        body: slide?.body ?? truncate(post.copy, 180),
    };
}

export function truncate(text: string, max: number): string {
    return text.length > max ? `${text.slice(0, max - 1)}…` : text;
}

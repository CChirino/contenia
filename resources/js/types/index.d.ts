export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export interface BrandProfile {
    id: number;
    tone_of_voice: string;
    audience: string;
    base_hashtags: string[];
    cta: string;
    url: string;
    palette: Record<string, string>;
}

export type NetworkName = 'instagram' | 'linkedin' | 'tiktok';

export interface NetworkStrategy {
    id: number;
    network: NetworkName;
    posts_per_week: number;
    preferred_formats: string[];
    focus: string;
}

export interface ContentPillar {
    id: number;
    name: string;
    description: string;
    weight: number;
}

export interface CalendarEvent {
    id: number;
    date: string;
    name: string;
    description: string | null;
    recurring: boolean;
    no_posting: boolean;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};

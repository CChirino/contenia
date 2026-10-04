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

export type PostStatus =
    | 'drafted'
    | 'approved'
    | 'rendered'
    | 'scheduled'
    | 'published'
    | 'failed';

export type PostFormat = 'image' | 'carousel' | 'reel' | 'video';

export interface GenerationError {
    from: string;
    to: string;
    message: string;
}

export interface MonthlyPlan {
    id: number;
    year: number;
    month: number;
    status: string;
    generated_at: string | null;
    generating_at: string | null;
    last_error: GenerationError[] | null;
    posts_count: number;
}

export interface Post {
    id: number;
    monthly_plan_id: number;
    scheduled_at: string;
    scheduled_date: string;
    scheduled_time: string;
    network: NetworkName;
    content_pillar_id: number | null;
    content_pillar?: ContentPillar | null;
    format: PostFormat;
    hook: string;
    copy: string;
    hashtags: string[];
    design_payload: { slides?: { title: string; body: string }[] };
    background_prompt: string | null;
    template: string | null;
    asset_path: string | null;
    status: PostStatus;
    error: string | null;
    regeneration_error: string | null;
    published_at: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};

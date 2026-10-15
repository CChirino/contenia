export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role?: 'admin' | 'operator';
}

export interface BrandProfile {
    id: number;
    description: string | null;
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
    brand_id: number | null;
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
    | 'client_approved'
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
    quota_notice: string | null;
    posts_count: number;
}

export interface QuotaActionSummary {
    action: 'posts_generated' | 'regenerations' | 'image_refreshes';
    used: number;
    limit: number | null;
    extra: number;
}

export interface QuotaSummary {
    unlimited: boolean;
    plan: string;
    actions: QuotaActionSummary[];
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
    background_source: string | null;
    background_meta: {
        source?: string;
        model?: string;
        user?: string;
        page_url?: string;
        id?: number;
        tags?: string;
        skip?: number;
    } | null;
    photo_keywords: string | null;
    icon: string | null;
    render_error: string | null;
    rendered_at: string | null;
    render_requested_at: string | null;
    frames: string[] | null;
    asset_urls?: { frames: string[]; final: string | null };
    status: PostStatus;
    review?: { decision: string; comment: string | null } | null;
    error: string | null;
    regeneration_error: string | null;
    published_at: string | null;
}

export interface ActiveBrand {
    id: number;
    slug: string;
    name: string;
    palette: Record<string, string>;
    logo_url: string;
    requires_client_approval?: boolean;
}

export interface BrandSummary {
    id: number;
    slug: string;
    name: string;
    logo_url: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    brand: ActiveBrand | null;
    brands: BrandSummary[];
};

// Shared props from App\Modules\Core\Http\Middleware\HandleInertiaRequests::share().
// Keep both sides in sync when that method changes.

export type Role = 'desa' | 'bpd' | 'warga';

export interface AuthUser {
    id: number;
    name: string;
    role: Role;
}

export interface DemoContext {
    role: Role;
    village_slug?: string | null;
    uploads_used?: number;
    upload_limit?: number;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: { user: AuthUser | null };
            demo: DemoContext | null;
            app: { commit: string };
            flash?: {
                success?: string | null;
                error?: string | null;
                published_project?: Record<string, unknown> | null;
            };
        };
    }
}

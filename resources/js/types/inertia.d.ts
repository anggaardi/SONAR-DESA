// Shared props from App\Modules\Core\Http\Middleware\HandleInertiaRequests::share().
// Keep both sides in sync when that method changes.

export type Role = 'desa' | 'bpd' | 'warga';

export interface AuthUser {
    id: number;
    name: string;
    role: Role;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: { user: AuthUser | null };
            demo: { role: Role; village_slug?: string | null } | null;
            app: { commit: string };
        };
    }
}

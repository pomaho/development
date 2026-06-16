export type AmoAccountSummary = {
    id: number;
    name: string;
    base_domain: string;
    is_active: boolean;
    dashboard_url?: string;
};

export type AuthUser = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'viewer' | string;
};

export type SharedProps = {
    auth: {
        user: AuthUser | null;
    };
    amoAccounts: AmoAccountSummary[];
    currentAmoAccount: AmoAccountSummary | null;
    flash: {
        success: string | null;
        error: string | null;
    };
};

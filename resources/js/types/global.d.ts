import type { Auth } from '@/types/auth';
import type {
    Organization,
    OrganizationTypeOption,
} from '@/types/organizations';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentOrganization: Organization | null;
            organizations: Organization[];
            organizationTypes: OrganizationTypeOption[];
            pendingInvitationsCount: number;
            accountsUrl: string;
            [key: string]: unknown;
        };
    }
}

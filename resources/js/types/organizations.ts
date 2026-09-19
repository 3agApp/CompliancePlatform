export type OrganizationRole = 'owner' | 'admin' | 'member';

export type OrganizationType = 'distributor' | 'supplier';

export type OrganizationTypeOption = {
    value: OrganizationType;
    label: string;
    description: string;
};

export type Organization = {
    id: number;
    name: string;
    slug: string;
    type: OrganizationType;
    typeLabel: string;
    role?: OrganizationRole;
    roleLabel?: string;
    isCurrent?: boolean;
};

export type OrganizationMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: OrganizationRole;
    role_label: string;
};

export type OrganizationInvitation = {
    code: string;
    email: string;
    role: OrganizationRole;
    role_label: string;
    created_at: string;
};

export type PendingInvitation = {
    code: string;
    inviterName: string;
    roleLabel: string;
    expiresAt: string | null;
    organization: {
        name: string;
        slug: string;
    };
};

export type OrganizationPermissions = {
    canUpdateOrganization: boolean;
    canDeleteOrganization: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
};

export type RoleOption = {
    value: OrganizationRole;
    label: string;
};

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

export type OrganizationInvitationContext = {
    code: string;
    organizationName: string;
    kind: 'organization' | 'supplier_connection';
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
    canManageAiProvider: boolean;
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

/**
 * The AI provider an organization pays for itself.
 *
 * The key is not here and never will be: `key_hint` is its last four
 * characters, which is enough to tell one key from another.
 */
export type AiProviderSetting = {
    provider: string;
    provider_label: string;
    model: string;
    model_label: string;
    key_hint: string;
    updated_at: string | null;
};

export type AiModelOption = {
    value: string;
    label: string;
};

export type AiProviderOption = {
    value: string;
    label: string;
    models: AiModelOption[];
    default_model: string;
};

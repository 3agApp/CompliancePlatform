import { Form, Head, router, usePage } from '@inertiajs/react';
import { ChevronDown, Mail, Send, UserPlus, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import AttentionList from '@/components/attention-list';
import CancelInvitationModal from '@/components/cancel-invitation-modal';
import ChangeMemberRoleModal from '@/components/change-member-role-modal';
import DangerZone from '@/components/danger-zone';
import DeleteOrganizationModal from '@/components/delete-organization-modal';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import InviteMemberModal from '@/components/invite-member-modal';
import OrganizationAiProviderForm from '@/components/organization-ai-provider-form';
import RemoveMemberModal from '@/components/remove-member-modal';
import SaveButton from '@/components/save-button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { t, tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { edit, index, update } from '@/routes/organizations';
import { resend as resendInvitation } from '@/routes/organizations/invitations';
import type {
    AiProviderOption,
    AiProviderSetting,
    RoleOption,
    Organization,
    OrganizationAttentionItem,
    OrganizationInvitation,
    OrganizationMember,
    OrganizationPermissions,
} from '@/types';

type Props = {
    organization: Organization;
    members: OrganizationMember[];
    invitations: OrganizationInvitation[];
    attention: OrganizationAttentionItem[];
    permissions: OrganizationPermissions;
    availableRoles: RoleOption[];
    aiProvider: AiProviderSetting | null;
    availableAiProviders: AiProviderOption[];
};

export default function OrganizationEdit({
    organization,
    members,
    invitations,
    attention,
    permissions,
    availableRoles,
    aiProvider,
    availableAiProviders,
}: Props) {
    const getInitials = useInitials();
    const { auth, availableLocales } = usePage().props;
    const [locale, setLocale] = useState(organization.locale);
    const localeLabel = availableLocales.find(
        (option) => option.value === organization.locale,
    )?.label;

    const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [removeMemberDialogOpen, setRemoveMemberDialogOpen] = useState(false);
    const [memberToRemove, setMemberToRemove] =
        useState<OrganizationMember | null>(null);
    const [roleChange, setRoleChange] = useState<{
        member: OrganizationMember;
        role: RoleOption;
    } | null>(null);
    const [roleDialogOpen, setRoleDialogOpen] = useState(false);
    const [cancelInvitationDialogOpen, setCancelInvitationDialogOpen] =
        useState(false);
    const [invitationToCancel, setInvitationToCancel] =
        useState<OrganizationInvitation | null>(null);
    const [resending, setResending] = useState<string | null>(null);

    const pageTitle = useMemo(
        () =>
            permissions.canUpdateOrganization
                ? t('Edit :name', { name: organization.name })
                : t('View :name', { name: organization.name }),
        [permissions.canUpdateOrganization, organization.name],
    );

    const attentionItems = attention.map((item) => ({
        ...item,
        href: item.target === 'invitations' ? '#invitations' : '#ai-provider',
    }));

    const currentMember = members.find((member) => member.id === auth.user.id);

    const confirmRoleChange = (member: OrganizationMember, value: string) => {
        const role = availableRoles.find((option) => option.value === value);

        if (!role || role.value === member.role) {
            return;
        }

        setRoleChange({ member, role });
        setRoleDialogOpen(true);
    };

    const confirmRemoveMember = (member: OrganizationMember) => {
        setMemberToRemove(member);
        setRemoveMemberDialogOpen(true);
    };

    const confirmCancelInvitation = (invitation: OrganizationInvitation) => {
        setInvitationToCancel(invitation);
        setCancelInvitationDialogOpen(true);
    };

    const resend = (invitation: OrganizationInvitation) => {
        router.visit(resendInvitation([organization.slug, invitation.code]), {
            preserveScroll: true,
            onStart: () => setResending(invitation.code),
            onFinish: () => setResending(null),
        });
    };

    return (
        <>
            <Head title={pageTitle} />

            <h1 className="sr-only">{pageTitle}</h1>

            <div className="flex flex-col space-y-10">
                <AttentionList items={attentionItems} />

                <div className="space-y-6 border-t pt-8 first:border-0 first:pt-0">
                    {permissions.canUpdateOrganization ? (
                        <>
                            <Heading
                                variant="small"
                                title={t('Organization settings')}
                                description={t(
                                    'Update your organization name and settings',
                                )}
                            />

                            <Form
                                {...update.form(organization.slug)}
                                options={{ preserveScroll: true }}
                                className="space-y-6"
                            >
                                {({
                                    errors,
                                    processing,
                                    isDirty,
                                    recentlySuccessful,
                                }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                {t('Organization name')}
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                data-test="organization-name-input"
                                                defaultValue={organization.name}
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2 sm:max-w-xs">
                                            <Label htmlFor="organization-locale">
                                                {t('Default language')}
                                            </Label>
                                            {/*
                                             * Named on the select itself, so it
                                             * raises a change event the form
                                             * hears: a hidden input set from
                                             * state would leave Save disabled.
                                             */}
                                            <Select
                                                name="locale"
                                                value={locale}
                                                onValueChange={(value) =>
                                                    setLocale(
                                                        value as typeof locale,
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    id="organization-locale"
                                                    data-test="organization-locale"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {availableLocales.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                                lang={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <p className="text-muted-foreground text-xs">
                                                {t(
                                                    "Members who haven't chosen a language of their own see the app in this one, and invitations go out in it.",
                                                )}
                                            </p>
                                            <InputError
                                                message={errors.locale}
                                            />
                                        </div>

                                        <SaveButton
                                            processing={processing}
                                            isDirty={isDirty}
                                            recentlySuccessful={
                                                recentlySuccessful
                                            }
                                            data-test="organization-save-button"
                                        />
                                    </>
                                )}
                            </Form>
                        </>
                    ) : (
                        <>
                            <Heading
                                variant="small"
                                title={organization.name}
                                description={t(
                                    'Only an owner or admin can change these settings.',
                                )}
                            />
                            <dl
                                className="grid gap-4 text-sm sm:grid-cols-2"
                                data-test="organization-details"
                            >
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('Organization type')}
                                    </dt>
                                    <dd className="font-medium">
                                        {organization.typeLabel}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('Your role')}
                                    </dt>
                                    <dd className="font-medium">
                                        {currentMember?.role_label ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('Default language')}
                                    </dt>
                                    <dd className="font-medium">
                                        {localeLabel ?? '—'}
                                    </dd>
                                </div>
                            </dl>
                        </>
                    )}
                </div>

                {permissions.canManageAiProvider ? (
                    <div
                        id="ai-provider"
                        className="scroll-mt-6 space-y-6 border-t pt-8 first:border-0 first:pt-0"
                        data-test="ai-provider-section"
                    >
                        <Heading
                            variant="small"
                            title={t('AI provider')}
                            description={t(
                                'Connect a provider to have document kinds guessed for you when files are uploaded. Only file names, types and sizes are sent — never the contents of a document.',
                            )}
                        />

                        <OrganizationAiProviderForm
                            organizationSlug={organization.slug}
                            setting={aiProvider}
                            availableProviders={availableAiProviders}
                        />
                    </div>
                ) : null}

                <div className="space-y-6 border-t pt-8 first:border-0 first:pt-0">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Heading
                            variant="small"
                            title={t('Organization members')}
                            description={
                                permissions.canCreateInvitation
                                    ? t(
                                          'Manage who belongs to this organization',
                                      )
                                    : t('Who belongs to this organization')
                            }
                        />

                        {permissions.canCreateInvitation ? (
                            <Button
                                variant="outline"
                                data-test="invite-member-button"
                                onClick={() => setInviteDialogOpen(true)}
                            >
                                <UserPlus /> {t('Invite member')}
                            </Button>
                        ) : null}
                    </div>

                    <ul className="divide-y overflow-hidden rounded-lg border">
                        {members.map((member) => (
                            <li
                                key={member.id}
                                data-test="member-row"
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div className="flex min-w-0 items-center gap-4">
                                    <Avatar className="h-10 w-10">
                                        {member.avatar ? (
                                            <AvatarImage
                                                src={member.avatar}
                                                alt={member.name}
                                            />
                                        ) : null}
                                        <AvatarFallback>
                                            {getInitials(member.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2 font-medium">
                                            <span className="truncate">
                                                {member.name}
                                            </span>
                                            {member.id === auth.user.id ? (
                                                <Badge
                                                    variant="outline"
                                                    data-test="member-you"
                                                >
                                                    {t('You')}
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <div className="text-muted-foreground truncate text-sm">
                                            {member.email}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    {member.role !== 'owner' &&
                                    permissions.canUpdateMember ? (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    data-test="member-role-trigger"
                                                >
                                                    {member.role_label}
                                                    <ChevronDown className="ml-2 h-4 w-4 opacity-50" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuRadioGroup
                                                    value={member.role}
                                                    onValueChange={(value) =>
                                                        confirmRoleChange(
                                                            member,
                                                            value,
                                                        )
                                                    }
                                                >
                                                    {availableRoles.map(
                                                        (role) => (
                                                            <DropdownMenuRadioItem
                                                                key={role.value}
                                                                value={
                                                                    role.value
                                                                }
                                                                data-test="member-role-option"
                                                            >
                                                                {role.label}
                                                            </DropdownMenuRadioItem>
                                                        ),
                                                    )}
                                                </DropdownMenuRadioGroup>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    ) : (
                                        <Badge variant="secondary">
                                            {member.role_label}
                                        </Badge>
                                    )}

                                    {member.role !== 'owner' &&
                                    permissions.canRemoveMember ? (
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={t(
                                                            'Remove :name',
                                                            {
                                                                name: member.name,
                                                            },
                                                        )}
                                                        data-test="member-remove-button"
                                                        onClick={() =>
                                                            confirmRemoveMember(
                                                                member,
                                                            )
                                                        }
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{t('Remove member')}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    ) : null}
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                {invitations.length > 0 ? (
                    <div
                        id="invitations"
                        className="scroll-mt-6 space-y-6 border-t pt-8 first:border-0 first:pt-0"
                    >
                        <Heading
                            variant="small"
                            title={t('Pending invitations')}
                            description={t(
                                "Invitations that haven't been accepted yet",
                            )}
                        />

                        <ul className="divide-y overflow-hidden rounded-lg border">
                            {invitations.map((invitation) => (
                                <li
                                    key={invitation.code}
                                    data-test="invitation-row"
                                    className="flex flex-wrap items-center justify-between gap-3 p-4"
                                >
                                    <div className="flex min-w-0 items-center gap-4">
                                        <div className="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                            <Mail className="text-muted-foreground h-5 w-5" />
                                        </div>
                                        <div className="min-w-0">
                                            <div className="truncate font-medium">
                                                {invitation.email}
                                            </div>
                                            <div className="text-muted-foreground text-sm">
                                                {invitation.role_label} ·{' '}
                                                {t('Sent :when', {
                                                    when: invitation.sent_at_diff,
                                                })}
                                                {invitation.expires_at_diff ? (
                                                    <span
                                                        data-test={
                                                            invitation.is_expired
                                                                ? 'invitation-expired'
                                                                : undefined
                                                        }
                                                        className={cn(
                                                            invitation.is_expired &&
                                                                'font-medium text-amber-700 dark:text-amber-400',
                                                        )}
                                                    >
                                                        {' · '}
                                                        {invitation.is_expired
                                                            ? t(
                                                                  'Expired :when',
                                                                  {
                                                                      when: invitation.expires_at_diff,
                                                                  },
                                                              )
                                                            : t(
                                                                  'Expires :when',
                                                                  {
                                                                      when: invitation.expires_at_diff,
                                                                  },
                                                              )}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        {permissions.canCreateInvitation ? (
                                            <Button
                                                variant={
                                                    invitation.is_expired
                                                        ? 'outline'
                                                        : 'ghost'
                                                }
                                                size="sm"
                                                data-test="invitation-resend-button"
                                                disabled={
                                                    resending ===
                                                    invitation.code
                                                }
                                                onClick={() =>
                                                    resend(invitation)
                                                }
                                            >
                                                <Send className="h-4 w-4" />
                                                {t('Resend')}
                                            </Button>
                                        ) : null}

                                        {permissions.canCancelInvitation ? (
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            aria-label={t(
                                                                'Cancel invitation for :email',
                                                                {
                                                                    email: invitation.email,
                                                                },
                                                            )}
                                                            data-test="invitation-cancel-button"
                                                            onClick={() =>
                                                                confirmCancelInvitation(
                                                                    invitation,
                                                                )
                                                            }
                                                        >
                                                            <X className="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>
                                                            {t(
                                                                'Cancel invitation',
                                                            )}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : null}

                {permissions.canDeleteOrganization ? (
                    <div className="border-t pt-8 first:border-0 first:pt-0">
                        <DangerZone
                            title={t('Delete organization')}
                            description={t(
                                'Permanently delete your organization',
                            )}
                            warning={t(
                                'Everything in the organization goes with it. This cannot be undone.',
                            )}
                        >
                            <Button
                                variant="destructive"
                                data-test="delete-organization-button"
                                onClick={() => setDeleteDialogOpen(true)}
                            >
                                {t('Delete organization')}
                            </Button>
                        </DangerZone>
                    </div>
                ) : null}
            </div>

            {permissions.canCreateInvitation ? (
                <InviteMemberModal
                    organization={organization}
                    availableRoles={availableRoles}
                    open={inviteDialogOpen}
                    onOpenChange={setInviteDialogOpen}
                />
            ) : null}

            <ChangeMemberRoleModal
                organization={organization}
                member={roleChange?.member ?? null}
                role={roleChange?.role ?? null}
                open={roleDialogOpen}
                onOpenChange={setRoleDialogOpen}
            />

            <RemoveMemberModal
                organization={organization}
                member={memberToRemove}
                open={removeMemberDialogOpen}
                onOpenChange={setRemoveMemberDialogOpen}
            />

            <CancelInvitationModal
                organization={organization}
                invitation={invitationToCancel}
                open={cancelInvitationDialogOpen}
                onOpenChange={setCancelInvitationDialogOpen}
            />

            {permissions.canDeleteOrganization ? (
                <DeleteOrganizationModal
                    organization={organization}
                    open={deleteDialogOpen}
                    onOpenChange={setDeleteDialogOpen}
                />
            ) : null}
        </>
    );
}

OrganizationEdit.layout = (props: {
    organization: { name: string; slug: string };
}) => ({
    breadcrumbs: [
        {
            title: tk('Organizations'),
            href: index(),
        },
        {
            title: props.organization.name,
            href: edit(props.organization.slug),
        },
    ],
});

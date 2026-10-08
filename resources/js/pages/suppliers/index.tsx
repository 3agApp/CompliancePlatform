import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    Factory,
    MoreHorizontal,
    Package,
    Plus,
    RotateCcw,
    Send,
    Tags,
    Unplug,
} from 'lucide-react';
import { useState } from 'react';
import InviteSupplierModal from '@/components/invite-supplier-modal';
import ReviewProgress from '@/components/review-progress';
import RevokeSupplierModal from '@/components/revoke-supplier-modal';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDay, formatRelative } from '@/lib/format';
import { t, tc } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index as brandsIndex } from '@/routes/brands';
import { index as productsIndex } from '@/routes/products';
import { resend, restore } from '@/routes/suppliers';
import type {
    SupplierConnection,
    SupplierConnectionPermissions,
} from '@/types';

type Props = {
    connections: SupplierConnection[];
    permissions: SupplierConnectionPermissions;
};

export default function SuppliersIndex({ connections, permissions }: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    const [revokeDialogOpen, setRevokeDialogOpen] = useState(false);
    const [connectionToRevoke, setConnectionToRevoke] =
        useState<SupplierConnection | null>(null);

    const confirmRevoke = (connection: SupplierConnection) => {
        setConnectionToRevoke(connection);
        setRevokeDialogOpen(true);
    };

    /**
     * Grouped by what the relationship needs: the ones waiting on an
     * answer come first, because they are the ones with a job to do --
     * nobody can fill in their products until they join.
     */
    const waiting = connections.filter(
        (connection) => connection.status === 'pending',
    );
    const active = connections.filter(
        (connection) => connection.status === 'active',
    );
    const ended = connections.filter(
        (connection) =>
            connection.status === 'declined' || connection.status === 'revoked',
    );

    const waitingProducts = waiting.reduce(
        (sum, connection) => sum + connection.productsCount,
        0,
    );

    const rowProps = {
        organizationSlug,
        canManage: permissions.canManageConnection,
        onRevoke: confirmRevoke,
    };

    return (
        <>
            <Head title={t('Suppliers')} />

            <div className="workspace-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="page-heading">
                        <h1 className="page-title">{t('Suppliers')}</h1>
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'The companies that provide compliance data for :organization.',
                                { organization: currentOrganization?.name },
                            )}
                        </p>
                    </div>

                    {permissions.canManageConnection ? (
                        <InviteSupplierModal
                            organizationSlug={organizationSlug}
                        >
                            <Button data-test="invite-supplier-button">
                                <Plus /> {t('Add supplier')}
                            </Button>
                        </InviteSupplierModal>
                    ) : null}
                </div>

                {connections.length > 0 ? (
                    <>
                        {waiting.length > 0 ? (
                            <SupplierGroup
                                title={t('Waiting on an answer')}
                                description={
                                    waitingProducts > 0
                                        ? tc(
                                              'Not joined yet. Their product cannot be filled in until they do.|Not joined yet. Their :count products cannot be filled in until they do.',
                                              waitingProducts,
                                          )
                                        : t('Not joined yet.')
                                }
                                connections={waiting}
                                testId="supplier-group-waiting"
                                {...rowProps}
                            />
                        ) : null}

                        {active.length > 0 ? (
                            <SupplierGroup
                                title={t('Working with you')}
                                connections={active}
                                testId="supplier-group-active"
                                {...rowProps}
                            />
                        ) : null}

                        {ended.length > 0 ? (
                            <EndedGroup connections={ended} {...rowProps} />
                        ) : null}
                    </>
                ) : (
                    <div className="workspace-panel flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                        <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                            <Factory className="text-muted-foreground size-6" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="font-medium">
                                {t('No suppliers yet')}
                            </h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                {permissions.canManageConnection
                                    ? t(
                                          'Add your first supplier, then assign products to them. You can invite them now or later.',
                                      )
                                    : t(
                                          'Suppliers invited to this organization will show up here.',
                                      )}
                            </p>
                        </div>
                    </div>
                )}
            </div>

            <RevokeSupplierModal
                organizationSlug={organizationSlug}
                connection={connectionToRevoke}
                open={revokeDialogOpen}
                onOpenChange={setRevokeDialogOpen}
            />
        </>
    );
}

type RowProps = {
    organizationSlug: string;
    canManage: boolean;
    onRevoke: (connection: SupplierConnection) => void;
};

function SupplierGroup({
    title,
    description,
    connections,
    testId,
    ...rowProps
}: RowProps & {
    title: string;
    description?: string;
    connections: SupplierConnection[];
    testId: string;
}) {
    return (
        <section className="workspace-table" data-test={testId}>
            <div className="border-b px-6 py-4">
                <h2 className="flex items-center gap-2 font-medium">
                    {title}
                    <span className="bg-muted-foreground/15 text-muted-foreground rounded-full px-1.5 text-xs tabular-nums">
                        {connections.length}
                    </span>
                </h2>
                {description ? (
                    <p className="text-muted-foreground text-sm">
                        {description}
                    </p>
                ) : null}
            </div>

            <SupplierTable connections={connections} {...rowProps} />
        </section>
    );
}

/**
 * The relationships that have ended, folded away: they are kept for the
 * record and for the odd reconnection, not for daily reading.
 */
function EndedGroup({
    connections,
    ...rowProps
}: RowProps & { connections: SupplierConnection[] }) {
    const [open, setOpen] = useState(false);

    return (
        <Collapsible open={open} onOpenChange={setOpen} asChild>
            <section
                className="workspace-table"
                data-test="supplier-group-ended"
            >
                <CollapsibleTrigger
                    className="hover:bg-muted/40 flex w-full flex-wrap items-center justify-between gap-2 px-6 py-4 text-left transition-colors"
                    data-test="supplier-group-ended-toggle"
                >
                    <span className="flex items-center gap-2 font-medium">
                        {t('No longer working with you')}
                        <span className="bg-muted-foreground/15 text-muted-foreground rounded-full px-1.5 text-xs tabular-nums">
                            {connections.length}
                        </span>
                    </span>
                    <span className="text-muted-foreground flex items-center gap-2 text-sm">
                        <span className="max-sm:hidden">
                            {connections
                                .map(
                                    (connection) =>
                                        `${connection.companyName} (${connection.statusLabel})`,
                                )
                                .join(' · ')}
                        </span>
                        <ChevronDown
                            className={cn(
                                'size-4 transition-transform',
                                open && 'rotate-180',
                            )}
                        />
                    </span>
                </CollapsibleTrigger>
                <CollapsibleContent className="border-t">
                    <SupplierTable connections={connections} {...rowProps} />
                </CollapsibleContent>
            </section>
        </Collapsible>
    );
}

function SupplierTable({
    connections,
    ...rowProps
}: RowProps & { connections: SupplierConnection[] }) {
    return (
        <div className="min-w-0 overflow-x-auto">
            {/*
             * Fixed widths, so the groups' separate tables line up as one
             * list when read down the page.
             */}
            <table className="w-full text-left text-sm md:min-w-3xl md:table-fixed">
                <colgroup className="max-md:hidden">
                    <col className="w-[26%]" />
                    <col className="w-[15%]" />
                    <col className="w-[22%]" />
                    <col className="w-[8%]" />
                    <col className="w-[12%]" />
                    <col className="w-[17%]" />
                </colgroup>
                <thead>
                    <tr className="text-muted-foreground">
                        <th className="px-6 font-medium">{t('Company')}</th>
                        <th className="px-6 font-medium">{t('Status')}</th>
                        <th className="px-6 font-medium">{t('Products')}</th>
                        <th className="px-6 font-medium">{t('Brands')}</th>
                        <th className="px-6 font-medium whitespace-nowrap">
                            {t('Last activity')}
                        </th>
                        <th className="px-6 font-medium">
                            <span className="sr-only">{t('Actions')}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {connections.map((connection) => (
                        <SupplierRow
                            key={connection.id}
                            connection={connection}
                            {...rowProps}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * Where the relationship stands, each state in a colour of its own so the
 * one that needs chasing stands out from the ones that are fine.
 */
function StatusBadge({ connection }: { connection: SupplierConnection }) {
    const tone =
        connection.status === 'active'
            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
            : connection.isExpired
              ? 'bg-red-500/10 text-red-700 dark:text-red-400'
              : connection.status === 'pending' && connection.isInvited
                ? 'bg-sky-500/10 text-sky-700 dark:text-sky-400'
                : 'bg-muted text-muted-foreground';

    return (
        <span className="grid justify-items-end gap-0.5 md:justify-items-start">
            <span
                className={cn(
                    'rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                    tone,
                )}
                data-test="supplier-status"
            >
                {connection.statusLabel}
            </span>
            {connection.isExpired && connection.expiresAt ? (
                <span className="text-muted-foreground text-xs">
                    {t('Expired :date', {
                        date: formatDay(connection.expiresAt),
                    })}
                </span>
            ) : null}
        </span>
    );
}

function SupplierRow({
    connection,
    organizationSlug,
    canManage,
    onRevoke,
}: RowProps & { connection: SupplierConnection }) {
    const productsUrl = productsIndex(organizationSlug, {
        query: { connection: connection.id },
    }).url;

    const brandsUrl = brandsIndex(organizationSlug, {
        query: { supplier: connection.id },
    }).url;

    return (
        <tr data-test="supplier-row" className="border-t">
            <td className="px-6 break-words">
                <Link
                    href={productsUrl}
                    className="font-medium underline-offset-4 hover:underline"
                    data-test="supplier-products-link"
                >
                    {connection.companyName}
                </Link>
                <span className="text-muted-foreground block text-xs break-all">
                    {connection.contactEmail}
                </span>
            </td>
            <td className="px-6" data-label={t('Status')}>
                <StatusBadge connection={connection} />
            </td>
            <td className="px-6 md:min-w-56" data-label={t('Products')}>
                <ReviewProgress
                    counts={connection.productsByStatus}
                    className="max-md:w-1/2"
                />
            </td>
            <td className="px-6" data-label={t('Brands')}>
                {connection.brandsCount > 0 ? (
                    <Link
                        href={brandsUrl}
                        className="underline-offset-4 hover:underline"
                    >
                        {connection.brandsCount}
                    </Link>
                ) : (
                    <span className="text-muted-foreground">0</span>
                )}
            </td>
            <td
                className={cn(
                    'px-6 whitespace-nowrap',
                    connection.isQuiet
                        ? 'text-amber-700 dark:text-amber-400'
                        : 'text-muted-foreground',
                )}
                data-label={t('Last activity')}
            >
                {formatRelative(connection.lastActivityAt) ?? '—'}
            </td>
            <td className="px-6">
                <div className="flex items-center justify-end gap-1">
                    <PrimaryAction
                        connection={connection}
                        organizationSlug={organizationSlug}
                        canManage={canManage}
                        productsUrl={productsUrl}
                    />

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                data-test="supplier-actions"
                                aria-label={t('Actions for :name', {
                                    name: connection.companyName,
                                })}
                            >
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-52">
                            <DropdownMenuItem asChild>
                                <Link href={productsUrl}>
                                    <Package className="h-4 w-4" />
                                    {t('View products')}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href={brandsUrl}>
                                    <Tags className="h-4 w-4" />
                                    {t('View brands')}
                                </Link>
                            </DropdownMenuItem>
                            {canManage && connection.status !== 'revoked' ? (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        data-test="supplier-revoke-button"
                                        onSelect={() => onRevoke(connection)}
                                    >
                                        <Unplug className="h-4 w-4" />
                                        {t('Disconnect supplier…')}
                                    </DropdownMenuItem>
                                </>
                            ) : null}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </td>
        </tr>
    );
}

/**
 * The one move that matters for the relationship as it stands, spelled out
 * rather than hidden behind an icon: invite a supplier not yet told, chase
 * one who has not answered, reconnect one cut off, or go to the products of
 * one already working.
 */
function PrimaryAction({
    connection,
    organizationSlug,
    canManage,
    productsUrl,
}: {
    connection: SupplierConnection;
    organizationSlug: string;
    canManage: boolean;
    productsUrl: string;
}) {
    if (canManage && connection.canRestore) {
        return (
            <Form {...restore.form([organizationSlug, connection.id])}>
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        data-test="supplier-restore-button"
                        disabled={processing}
                    >
                        <RotateCcw className="h-4 w-4" />
                        {t('Reconnect supplier')}
                    </Button>
                )}
            </Form>
        );
    }

    if (canManage && connection.canResend) {
        return (
            <Form {...resend.form([organizationSlug, connection.id])}>
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        data-test={
                            connection.isInvited
                                ? 'supplier-resend-button'
                                : 'supplier-invite-button'
                        }
                        disabled={processing}
                    >
                        <Send className="h-4 w-4" />
                        {connection.isInvited
                            ? t('Resend invitation')
                            : t('Invite')}
                    </Button>
                )}
            </Form>
        );
    }

    if (connection.status === 'active') {
        return (
            <Button variant="outline" size="sm" asChild>
                <Link href={productsUrl}>{t('View products')}</Link>
            </Button>
        );
    }

    return null;
}

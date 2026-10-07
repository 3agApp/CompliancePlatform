import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Factory, Plus, RotateCcw, Send, Unplug } from 'lucide-react';
import { useState } from 'react';
import InviteSupplierModal from '@/components/invite-supplier-modal';
import RevokeSupplierModal from '@/components/revoke-supplier-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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

    return (
        <>
            <Head title="Suppliers" />

            <div className="workspace-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="page-heading">
                        <h1 className="page-title">Suppliers</h1>
                        <p className="text-muted-foreground text-sm">
                            The companies that provide compliance data for{' '}
                            {currentOrganization?.name}.
                        </p>
                    </div>

                    {permissions.canManageConnection ? (
                        <InviteSupplierModal
                            organizationSlug={organizationSlug}
                        >
                            <Button data-test="invite-supplier-button">
                                <Plus /> Add supplier
                            </Button>
                        </InviteSupplierModal>
                    ) : null}
                </div>

                {connections.length > 0 ? (
                    <div className="workspace-table">
                        <div className="min-w-0 overflow-x-auto">
                            <table className="w-full text-left text-sm md:min-w-xl">
                                <thead>
                                    <tr className="text-muted-foreground">
                                        <th className="px-6 font-medium">
                                            Company
                                        </th>
                                        <th className="px-6 font-medium">
                                            Contact
                                        </th>
                                        <th className="px-6 font-medium">
                                            Status
                                        </th>
                                        <th className="px-6 font-medium">
                                            Products
                                        </th>
                                        <th className="px-6 font-medium">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {connections.map((connection) => (
                                        <tr
                                            key={connection.id}
                                            data-test="supplier-row"
                                            className="border-t"
                                        >
                                            <td className="px-6 font-medium break-words">
                                                <Link
                                                    href={productsIndex(
                                                        organizationSlug,
                                                        {
                                                            query: {
                                                                connection:
                                                                    connection.id,
                                                            },
                                                        },
                                                    )}
                                                    className="hover:text-primary underline-offset-4 hover:underline"
                                                    data-test="supplier-products-link"
                                                >
                                                    {connection.companyName}
                                                </Link>
                                            </td>
                                            <td
                                                className="text-muted-foreground px-6 break-all"
                                                data-label="Contact"
                                            >
                                                {connection.contactEmail}
                                            </td>
                                            <td
                                                className="px-6"
                                                data-label="Status"
                                            >
                                                <Badge
                                                    variant={
                                                        connection.status ===
                                                        'active'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {connection.statusLabel}
                                                </Badge>
                                            </td>
                                            <td
                                                className="text-muted-foreground px-6"
                                                data-label="Products"
                                            >
                                                {connection.productsCount}
                                            </td>
                                            <td className="px-6">
                                                <div className="flex items-center justify-end gap-2">
                                                    {/*
                                                     * A supplier added but
                                                     * never invited has one
                                                     * thing left to do, so
                                                     * it is spelled out
                                                     * rather than hidden
                                                     * behind an icon.
                                                     */}
                                                    {permissions.canManageConnection &&
                                                    connection.canResend &&
                                                    !connection.isInvited ? (
                                                        <Form
                                                            {...resend.form([
                                                                organizationSlug,
                                                                connection.id,
                                                            ])}
                                                        >
                                                            {({
                                                                processing,
                                                            }) => (
                                                                <Button
                                                                    type="submit"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    data-test="supplier-invite-button"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    <Send className="h-4 w-4" />
                                                                    Invite
                                                                </Button>
                                                            )}
                                                        </Form>
                                                    ) : null}

                                                    {permissions.canManageConnection &&
                                                    connection.canResend &&
                                                    connection.isInvited ? (
                                                        <Form
                                                            {...resend.form([
                                                                organizationSlug,
                                                                connection.id,
                                                            ])}
                                                        >
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        type="submit"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        data-test="supplier-resend-button"
                                                                    >
                                                                        <Send className="h-4 w-4" />
                                                                        <span className="sr-only">
                                                                            Send
                                                                            invitation
                                                                            again
                                                                        </span>
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>
                                                                        Send
                                                                        invitation
                                                                        again
                                                                    </p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </Form>
                                                    ) : null}

                                                    {permissions.canManageConnection &&
                                                    connection.canRestore ? (
                                                        <Form
                                                            {...restore.form([
                                                                organizationSlug,
                                                                connection.id,
                                                            ])}
                                                        >
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        type="submit"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        data-test="supplier-restore-button"
                                                                    >
                                                                        <RotateCcw className="h-4 w-4" />
                                                                        <span className="sr-only">
                                                                            Reconnect
                                                                            supplier
                                                                        </span>
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>
                                                                        Reconnect
                                                                        supplier
                                                                    </p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </Form>
                                                    ) : null}

                                                    {permissions.canManageConnection &&
                                                    connection.status !==
                                                        'revoked' ? (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    data-test="supplier-revoke-button"
                                                                    onClick={() =>
                                                                        confirmRevoke(
                                                                            connection,
                                                                        )
                                                                    }
                                                                >
                                                                    <Unplug className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        Disconnect
                                                                        supplier
                                                                    </span>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>
                                                                    Disconnect
                                                                    supplier
                                                                </p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : (
                    <div className="workspace-panel flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                        <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                            <Factory className="text-muted-foreground size-6" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="font-medium">No suppliers yet</h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                {permissions.canManageConnection
                                    ? 'Add your first supplier, then assign products to them. You can invite them now or later.'
                                    : 'Suppliers invited to this organization will show up here.'}
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

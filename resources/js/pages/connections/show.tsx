import { Form, Head } from '@inertiajs/react';
import { Handshake } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/connections';
import type {
    BindableOrganization,
    ClaimableSupplierConnection,
} from '@/types';

type Props = {
    connection: ClaimableSupplierConnection;
    supplierOrganizations: BindableOrganization[];
};

type Mode = 'create' | 'existing';

export default function ConnectionShow({
    connection,
    supplierOrganizations,
}: Props) {
    const hasExisting = supplierOrganizations.length > 0;

    /**
     * Defaulting to an existing company when the person already runs one is
     * what keeps a supplier invited by two distributors from ending up as two
     * companies.
     */
    const [mode, setMode] = useState<Mode>(hasExisting ? 'existing' : 'create');
    const [organization, setOrganization] = useState<string>(
        supplierOrganizations[0]?.slug ?? '',
    );

    return (
        <>
            <Head title={`Invitation from ${connection.distributorName}`} />

            <div className="workspace-page">
                <div className="mx-auto w-full max-w-xl space-y-6">
                    <div className="page-heading">
                        <div className="bg-muted mb-3 flex size-12 items-center justify-center rounded-full">
                            <Handshake className="text-muted-foreground size-6" />
                        </div>
                        <h1 className="page-title">
                            {connection.distributorName} wants to work with you
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {connection.inviterName} invited{' '}
                            {connection.companyName} to supply them. Accepting
                            gives your company access to the products they
                            assign to you.
                        </p>
                    </div>

                    <Form
                        {...store.form(connection.code)}
                        className="bg-card space-y-6 rounded-2xl border p-6 shadow-xs"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input type="hidden" name="mode" value={mode} />

                                {hasExisting ? (
                                    <fieldset className="grid gap-3">
                                        <legend className="mb-2 text-sm font-medium">
                                            Connect as
                                        </legend>

                                        <label
                                            htmlFor="claim-connection-existing-company"
                                            data-test="claim-connection-existing-company"
                                            className="border-input has-[:checked]:border-primary has-[:checked]:bg-primary/5 cursor-pointer rounded-xl border p-4"
                                        >
                                            <input
                                                type="radio"
                                                id="claim-connection-existing-company"
                                                name="claim-mode"
                                                checked={mode === 'existing'}
                                                onChange={() =>
                                                    setMode('existing')
                                                }
                                                className="sr-only"
                                            />
                                            <span className="block text-sm font-medium">
                                                A company I already run
                                            </span>
                                            <span className="text-muted-foreground mt-1 block text-xs">
                                                Use this if you already supply
                                                another distributor here.
                                            </span>

                                            {mode === 'existing' ? (
                                                <div className="mt-3 grid gap-2">
                                                    {supplierOrganizations.map(
                                                        (supplier) => (
                                                            <label
                                                                key={
                                                                    supplier.slug
                                                                }
                                                                className="flex items-center gap-2 text-sm"
                                                            >
                                                                <input
                                                                    type="radio"
                                                                    name="organization"
                                                                    value={
                                                                        supplier.slug
                                                                    }
                                                                    checked={
                                                                        organization ===
                                                                        supplier.slug
                                                                    }
                                                                    onChange={() =>
                                                                        setOrganization(
                                                                            supplier.slug,
                                                                        )
                                                                    }
                                                                />
                                                                {supplier.name}
                                                            </label>
                                                        ),
                                                    )}
                                                </div>
                                            ) : null}
                                        </label>

                                        <label
                                            htmlFor="claim-connection-new-company"
                                            data-test="claim-connection-new-company"
                                            className="border-input has-[:checked]:border-primary has-[:checked]:bg-primary/5 cursor-pointer rounded-xl border p-4"
                                        >
                                            <input
                                                type="radio"
                                                id="claim-connection-new-company"
                                                name="claim-mode"
                                                checked={mode === 'create'}
                                                onChange={() =>
                                                    setMode('create')
                                                }
                                                className="sr-only"
                                            />
                                            <span className="block text-sm font-medium">
                                                A new company
                                            </span>
                                            <span className="text-muted-foreground mt-1 block text-xs">
                                                Sets up a new supplier company
                                                with you as its owner.
                                            </span>
                                        </label>

                                        <InputError
                                            message={errors.organization}
                                        />
                                    </fieldset>
                                ) : null}

                                {mode === 'create' ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="claim-connection-name">
                                            Company name
                                        </Label>
                                        <Input
                                            id="claim-connection-name"
                                            name="name"
                                            data-test="claim-connection-name"
                                            defaultValue={
                                                connection.companyName
                                            }
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                ) : null}

                                <InputError message={errors.connection} />

                                <Button
                                    type="submit"
                                    data-test="claim-connection-submit"
                                    disabled={processing}
                                >
                                    Accept invitation
                                </Button>
                            </>
                        )}
                    </Form>

                    <Form {...destroy.form(connection.code)}>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="ghost"
                                className="w-full"
                                data-test="claim-connection-decline"
                                disabled={processing}
                            >
                                Decline invitation
                            </Button>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

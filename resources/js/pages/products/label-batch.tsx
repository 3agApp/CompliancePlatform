import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, FileText, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { edit } from '@/routes/products';
import { pdf } from '@/routes/products/label-batches';
import type { LabelUnitOverview } from '@/types';

type Props = {
    product: { id: number; name: string };
    batch: {
        id: number;
        quantity: number;
        issuedFor: string;
        note: string | null;
        createdAt: string | null;
        createdBy: string | null;
        revokedAt: string | null;
    };
    summary: { checked: number; checks: number; unusual: number };
    unusualThreshold: number;
    units: LabelUnitOverview[];
};

type Filter = 'all' | 'checked' | 'unusual';

function onDay(timestamp: string | null, withTime = false): string {
    return timestamp
        ? new Date(timestamp).toLocaleString(undefined, {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
          })
        : '—';
}

/**
 * How one run's labels are being checked.
 *
 * Every box in the run with how often its serial was checked and from how
 * many devices, most-checked first. A buyer checks a new box once or
 * twice; a serial checked far more often than that is a label that was
 * copied onto other boxes or handed round, and the run says where it went.
 */
export default function LabelBatchOverview({
    product,
    batch,
    summary,
    unusualThreshold,
    units,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';
    const [filter, setFilter] = useState<Filter>(
        summary.unusual > 0 ? 'unusual' : 'all',
    );

    const shown = units.filter((unit) =>
        filter === 'unusual'
            ? unit.checks >= unusualThreshold
            : filter === 'checked'
              ? unit.checks > 0
              : true,
    );

    return (
        <>
            <Head title={`Labels for ${batch.issuedFor}`} />

            <div className="workspace-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="page-heading">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="-ml-2"
                            asChild
                        >
                            <Link href={edit([organizationSlug, product.id])}>
                                <ArrowLeft className="h-4 w-4" /> {product.name}
                            </Link>
                        </Button>
                        <h1 className="page-title break-words">
                            Labels for {batch.issuedFor}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {batch.quantity} boxes · issued{' '}
                            {onDay(batch.createdAt)}
                            {batch.createdBy ? ` by ${batch.createdBy}` : ''}
                            {batch.revokedAt
                                ? ` · withdrawn ${onDay(batch.revokedAt)}`
                                : ''}
                        </p>
                        {batch.note ? (
                            <p className="text-sm whitespace-pre-line">
                                {batch.note}
                            </p>
                        ) : null}
                    </div>

                    {batch.revokedAt ? null : (
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={
                                    pdf([
                                        organizationSlug,
                                        product.id,
                                        batch.id,
                                    ]).url
                                }
                            >
                                <FileText className="h-4 w-4" /> Labels (PDF)
                            </a>
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Stat label="Labels" value={batch.quantity} />
                    <Stat
                        label="Checked at least once"
                        value={summary.checked}
                        hint={`${Math.round((summary.checked / Math.max(batch.quantity, 1)) * 100)}% of the run`}
                    />
                    <Stat label="Checks in total" value={summary.checks} />
                    <Stat
                        label="Unusual"
                        value={summary.unusual}
                        hint={`Checked ${unusualThreshold}+ times`}
                        alarming={summary.unusual > 0}
                        testId="label-batch-unusual-count"
                    />
                </div>

                {summary.unusual > 0 ? (
                    <div
                        className="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-100"
                        data-test="label-batch-unusual-warning"
                    >
                        <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                        <p>
                            {summary.unusual === 1
                                ? '1 serial in this run has'
                                : `${summary.unusual} serials in this run have`}{' '}
                            been checked {unusualThreshold} or more times. A
                            buyer checks a new box once or twice, so these
                            labels have most likely been copied onto other boxes
                            or passed around. Consider withdrawing the run and
                            asking {batch.issuedFor} where these boxes went.
                        </p>
                    </div>
                ) : null}

                <section className="workspace-panel overflow-hidden">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                        <h2 className="text-sm font-medium">Serials</h2>
                        <div
                            className="bg-muted inline-flex rounded-lg p-0.5 text-xs"
                            role="tablist"
                        >
                            {(
                                [
                                    ['all', `All (${units.length})`],
                                    ['checked', `Checked (${summary.checked})`],
                                    ['unusual', `Unusual (${summary.unusual})`],
                                ] as [Filter, string][]
                            ).map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    role="tab"
                                    aria-selected={filter === value}
                                    onClick={() => setFilter(value)}
                                    data-test={`label-filter-${value}`}
                                    className={cn(
                                        'rounded-md px-2.5 py-1 font-medium transition-colors',
                                        filter === value
                                            ? 'bg-background shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {shown.length > 0 ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-4">
                                        Serial
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Checks
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Devices
                                    </TableHead>
                                    <TableHead>First checked</TableHead>
                                    <TableHead className="pr-4">
                                        Last checked
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {shown.map((unit) => {
                                    const unusual =
                                        unit.checks >= unusualThreshold;

                                    return (
                                        <TableRow
                                            key={unit.id}
                                            data-test="label-unit-row"
                                            data-unusual={unusual}
                                            className={cn(
                                                unusual &&
                                                    'bg-red-50/60 dark:bg-red-500/5',
                                            )}
                                        >
                                            <TableCell className="pl-4 font-mono text-xs">
                                                <span
                                                    className={cn(
                                                        unit.revoked &&
                                                            'text-muted-foreground line-through',
                                                    )}
                                                >
                                                    {unit.serial}
                                                </span>
                                                {unusual ? (
                                                    <TriangleAlert
                                                        className="ml-2 inline h-3.5 w-3.5 text-red-600"
                                                        aria-label="Unusual"
                                                    />
                                                ) : null}
                                            </TableCell>
                                            <TableCell
                                                className={cn(
                                                    'text-right tabular-nums',
                                                    unusual &&
                                                        'font-semibold text-red-700 dark:text-red-300',
                                                )}
                                            >
                                                {unit.checks}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {unit.devices}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-xs">
                                                {onDay(
                                                    unit.firstCheckedAt,
                                                    true,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground pr-4 text-xs">
                                                {onDay(
                                                    unit.lastCheckedAt,
                                                    true,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    ) : (
                        <p
                            className="text-muted-foreground p-6 text-center text-sm"
                            data-test="label-units-empty"
                        >
                            {filter === 'unusual'
                                ? 'No serial in this run has been checked unusually often.'
                                : 'No serial in this run has been checked yet.'}
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    hint,
    alarming = false,
    testId,
}: {
    label: string;
    value: number;
    hint?: string;
    alarming?: boolean;
    testId?: string;
}) {
    return (
        <div
            className={cn(
                'workspace-panel space-y-1 p-4',
                alarming &&
                    'border-red-200 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10',
            )}
            data-test={testId}
        >
            <p className="text-muted-foreground text-xs">{label}</p>
            <p
                className={cn(
                    'text-2xl font-semibold tabular-nums',
                    alarming && 'text-red-700 dark:text-red-300',
                )}
            >
                {value}
            </p>
            {hint ? (
                <p className="text-muted-foreground text-xs">{hint}</p>
            ) : null}
        </div>
    );
}

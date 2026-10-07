import { Form, useHttp, usePoll } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    Copy,
    Download,
    FileText,
    Info,
    Loader2,
    MinusCircle,
    Sparkles,
    Undo2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import RequestProductChangesModal from '@/components/request-product-changes-modal';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { formatLocale, t, tc } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { report, show, store } from '@/routes/products/assessments';
import { show as showDocument } from '@/routes/products/documents';
import type {
    ProductAssessmentDetail,
    ProductAssessmentFinding,
    ProductAssessmentState,
    ProductAssessmentUnavailableReason,
    ProductDetail,
    ProductFindingSeverity,
} from '@/types';

type Props = {
    organizationSlug: string;
    product: ProductDetail;
    assessment: ProductAssessmentState;
    unavailableReason: ProductAssessmentUnavailableReason | null;
    canReview: boolean;
};

/**
 * The longest note the Request changes form takes. Mirrors
 * RequestProductChangesRequest::MAX_NOTE_LENGTH.
 */
const MAX_NOTE_LENGTH = 2000;

/**
 * How often to look again while a run is going, in milliseconds.
 */
const POLL_INTERVAL = 4000;

/**
 * How each severity reads at a glance: an icon and a word as well as a
 * colour, so a printout still says it.
 */
const SEVERITY_TONES: Record<
    ProductFindingSeverity,
    { icon: LucideIcon; className: string }
> = {
    critical: {
        icon: AlertOctagon,
        className:
            'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
    },
    major: {
        icon: AlertTriangle,
        className:
            'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-300',
    },
    minor: {
        icon: MinusCircle,
        className:
            'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
    },
    info: {
        icon: Info,
        className: 'bg-muted text-muted-foreground',
    },
};

function unavailableNotes(): Record<
    ProductAssessmentUnavailableReason,
    string
> {
    return {
        not_configured: t(
            'Connect an AI provider in the organization settings to use the AI check.',
        ),
        analysis_disabled: t(
            'An admin has to allow document analysis in the organization settings first. The documents are sent to your AI provider to be read.',
        ),
    };
}

function when(timestamp: string | null): string {
    if (timestamp === null) {
        return '';
    }

    return new Date(timestamp).toLocaleString(formatLocale(), {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * A second reader for the person who rules on the product.
 *
 * The AI reads the papers and says where they fall short, how much each
 * gap matters and what to ask the factory for. It never rules: the panel
 * says so in as many words, and the only move it offers is to hand its
 * draft to the reviewer's own Request changes form, to edit and send.
 */
export default function ProductAssessmentPanel({
    organizationSlug,
    product,
    assessment,
    unavailableReason,
    canReview,
}: Props) {
    const latest = assessment.latest;
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [earlier, setEarlier] = useState<ProductAssessmentDetail | null>(
        null,
    );
    const [changesDialogOpen, setChangesDialogOpen] = useState(false);
    const [copied, setCopied] = useState(false);

    const earlierRun = useHttp<Record<string, never>, ProductAssessmentDetail>(
        {},
    );

    /**
     * Look again while the latest run is still going, and stop as soon as
     * it is not -- a finished run has nothing left to say.
     */
    const { start, stop } = usePoll(
        POLL_INTERVAL,
        { only: ['assessment'] },
        { autoStart: false },
    );

    const pending = latest?.is_pending ?? false;

    useEffect(() => {
        if (pending) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [pending, start, stop]);

    const viewing =
        selectedId !== null && selectedId !== latest?.id ? earlier : latest;

    const chooseRun = (value: string) => {
        const id = Number(value);

        if (id === latest?.id) {
            setSelectedId(null);
            setEarlier(null);

            return;
        }

        setSelectedId(id);
        setEarlier(null);

        void earlierRun.get(show.url([organizationSlug, product.id, id]), {
            onSuccess: (response: ProductAssessmentDetail) =>
                setEarlier(response),
        });
    };

    const canSendBack = canReview && product.review_status === 'in_review';

    const copyRequest = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    };

    return (
        <div
            className="workspace-panel space-y-5 p-5"
            data-test="product-assessment-panel"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    variant="small"
                    title={t('AI document check')}
                    description={t(
                        'A second reader for the test reports, declarations and certificates. Advisory only: a person decides, and nothing here approves the product or changes its seal.',
                    )}
                />

                {unavailableReason === null ? (
                    <Form
                        {...store.form([organizationSlug, product.id])}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <div className="grid justify-items-end gap-1">
                                <Button
                                    type="submit"
                                    variant="outline"
                                    data-test="run-assessment"
                                    disabled={processing || pending}
                                >
                                    <Sparkles className="h-4 w-4" />
                                    {latest === null
                                        ? t('Run AI check')
                                        : t('Run again')}
                                </Button>
                                <InputError message={errors.assessment} />
                            </div>
                        )}
                    </Form>
                ) : null}
            </div>

            {unavailableReason !== null ? (
                <p
                    className="text-muted-foreground text-sm"
                    data-test="assessment-unavailable"
                >
                    {unavailableNotes()[unavailableReason]}
                </p>
            ) : null}

            {assessment.runs.length > 1 ? (
                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="text-muted-foreground">
                        {t('Showing')}
                    </span>
                    <Select
                        value={String(viewing?.id ?? selectedId ?? latest?.id)}
                        onValueChange={chooseRun}
                    >
                        <SelectTrigger
                            className="h-8 w-auto"
                            data-test="assessment-run-select"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {assessment.runs.map((run, index) => (
                                <SelectItem key={run.id} value={String(run.id)}>
                                    {when(run.created_at)} ·{' '}
                                    {run.overall_label ?? run.status_label}
                                    {index === 0 ? ` · ${t('latest')}` : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            ) : null}

            {selectedId !== null && viewing === null ? (
                <Skeleton className="h-24 w-full" />
            ) : viewing ? (
                <AssessmentRun
                    run={viewing}
                    organizationSlug={organizationSlug}
                    productId={product.id}
                    actions={
                        viewing.factory_request && !viewing.is_pending ? (
                            <div className="flex flex-wrap gap-2">
                                {canSendBack && viewing.id === latest?.id ? (
                                    <Button
                                        data-test="assessment-use-as-request"
                                        onClick={() =>
                                            setChangesDialogOpen(true)
                                        }
                                    >
                                        <Undo2 className="h-4 w-4" />
                                        {t('Use as change request')}
                                    </Button>
                                ) : null}
                                <Button
                                    variant="outline"
                                    data-test="assessment-copy-request"
                                    onClick={() =>
                                        void copyRequest(
                                            viewing.factory_request ?? '',
                                        )
                                    }
                                >
                                    <Copy className="h-4 w-4" />
                                    {copied ? t('Copied') : t('Copy text')}
                                </Button>
                            </div>
                        ) : null
                    }
                />
            ) : unavailableReason === null ? (
                <p className="text-muted-foreground text-sm">
                    {t(
                        'No check has been run yet. It reads every PDF and image filed against the product and usually takes a minute or two.',
                    )}
                </p>
            ) : null}

            {latest?.factory_request && canSendBack ? (
                <RequestProductChangesModal
                    organizationSlug={organizationSlug}
                    product={product}
                    open={changesDialogOpen}
                    onOpenChange={setChangesDialogOpen}
                    defaultNote={latest.factory_request.slice(
                        0,
                        MAX_NOTE_LENGTH,
                    )}
                />
            ) : null}
        </div>
    );
}

/**
 * One run: where it stands, what it concluded, and every gap it found.
 */
function AssessmentRun({
    run,
    organizationSlug,
    productId,
    actions,
}: {
    run: ProductAssessmentDetail;
    organizationSlug: string;
    productId: number;
    actions: React.ReactNode;
}) {
    if (run.is_pending) {
        return (
            <div
                className="text-muted-foreground flex items-center gap-2 text-sm"
                data-test="assessment-pending"
            >
                <Loader2 className="h-4 w-4 animate-spin" />
                {run.status_label}…{' '}
                {t('You can keep working; this updates by itself.')}
            </div>
        );
    }

    if (run.status === 'failed') {
        return (
            <p
                className="text-sm text-red-700 dark:text-red-400"
                data-test="assessment-failed"
            >
                {run.failure_reason ?? t('The check could not finish.')}
            </p>
        );
    }

    return (
        <div className="space-y-4" data-test="assessment-result">
            <div className="space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={cn(
                            'rounded-md px-2 py-0.5 text-xs font-medium',
                            run.overall === 'no_gaps_found'
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300'
                                : run.overall === 'gaps_found'
                                  ? 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-300'
                                  : 'bg-muted text-muted-foreground',
                        )}
                        data-test="assessment-overall"
                    >
                        {run.overall_label}
                    </span>
                    <span className="text-muted-foreground text-xs">
                        {tc('1 finding|:count findings', run.findings.length)} ·{' '}
                        {when(run.completed_at)} · {run.provider_label}{' '}
                        {run.model_label}
                        {run.requested_by ? ` · ${run.requested_by}` : ''}
                    </span>
                </div>

                {run.summary ? (
                    <p className="text-sm leading-relaxed">{run.summary}</p>
                ) : null}

                <Button variant="outline" size="sm" asChild>
                    <a
                        href={report.url([organizationSlug, productId, run.id])}
                        data-test="assessment-download-report"
                    >
                        <Download className="h-4 w-4" />
                        {t('Download report')}
                    </a>
                </Button>
            </div>

            {run.findings.length > 0 ? (
                <ol className="space-y-3" data-test="assessment-findings">
                    {run.findings.map((finding) => (
                        <FindingRow
                            key={finding.id}
                            finding={finding}
                            href={
                                finding.document_id !== null
                                    ? showDocument.url([
                                          organizationSlug,
                                          productId,
                                          finding.document_id,
                                      ])
                                    : null
                            }
                        />
                    ))}
                </ol>
            ) : null}

            {run.skipped_documents.length > 0 ? (
                <div className="text-muted-foreground space-y-1 text-xs">
                    <p className="font-medium">{t('Not read')}</p>
                    <ul className="space-y-0.5">
                        {run.skipped_documents.map((document) => (
                            <li key={document.id}>
                                {document.name} — {document.reason}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {run.factory_request ? (
                <div className="space-y-2">
                    <p className="text-sm font-medium">
                        {t('Draft request to the factory')}
                    </p>
                    <blockquote
                        className="bg-muted/50 rounded-md p-3 text-sm leading-relaxed whitespace-pre-line"
                        data-test="assessment-factory-request"
                    >
                        {run.factory_request}
                    </blockquote>
                    {actions}
                </div>
            ) : null}
        </div>
    );
}

/**
 * One gap: what rule, how serious, why, and what to ask for.
 */
function FindingRow({
    finding,
    href,
}: {
    finding: ProductAssessmentFinding;
    href: string | null;
}) {
    const tone = SEVERITY_TONES[finding.severity] ?? SEVERITY_TONES.info;
    const Icon = tone.icon;

    return (
        <li
            className="space-y-1.5 rounded-md border p-3"
            data-test={`assessment-finding-${finding.severity}`}
        >
            <div className="flex flex-wrap items-center gap-2">
                <span
                    className={cn(
                        'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium',
                        tone.className,
                    )}
                >
                    <Icon className="h-3 w-3" />
                    {finding.severity_label}
                </span>
                <span className="text-sm font-medium">
                    {finding.requirement}
                </span>
                <span className="text-muted-foreground text-xs">
                    {finding.category_label}
                </span>
            </div>

            <p className="text-sm leading-relaxed">{finding.rationale}</p>

            {finding.evidence ? (
                <p className="text-muted-foreground text-xs italic">
                    “{finding.evidence}”
                </p>
            ) : null}

            {finding.document_name ? (
                <p className="text-xs">
                    <FileText className="mr-1 inline h-3 w-3" />
                    {href ? (
                        <a
                            href={href}
                            className="underline underline-offset-2"
                            target="_blank"
                            rel="noreferrer"
                        >
                            {finding.document_name}
                        </a>
                    ) : (
                        finding.document_name
                    )}
                </p>
            ) : null}

            {finding.ask_manufacturer ? (
                <p className="text-sm">
                    <span className="font-medium">{t('Ask for:')}</span>{' '}
                    {finding.ask_manufacturer}
                </p>
            ) : null}
        </li>
    );
}

/**
 * The panel's shape while the reading is still on its way.
 */
export function ProductAssessmentSkeleton() {
    return (
        <div className="workspace-panel space-y-3 p-5">
            <Skeleton className="h-5 w-40" />
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-16 w-full" />
        </div>
    );
}

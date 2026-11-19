import { router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ChevronDown,
    HelpCircle,
    Loader2,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import { FindingRow } from '@/components/product-assessment-panel';
import { Button } from '@/components/ui/button';
import { formatCalendarDate, formatDay } from '@/lib/format';
import { t, tc } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { store } from '@/routes/products/documents/analyses';
import type {
    ProductAssessmentOverall,
    ProductDocument,
    ProductDocumentAnalysis,
} from '@/types';

/**
 * How each verdict reads at a glance.
 */
const OVERALL_TONES: Record<
    ProductAssessmentOverall,
    { icon: typeof CheckCircle2; className: string }
> = {
    no_gaps_found: {
        icon: CheckCircle2,
        className:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    },
    gaps_found: {
        icon: AlertTriangle,
        className:
            'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-300',
    },
    insufficient_documents: {
        icon: HelpCircle,
        className: 'bg-muted text-muted-foreground',
    },
};

/**
 * The button that asks the AI to read one paper.
 *
 * Disabled, with the reason as its title, when the organization cannot run
 * one, the file is not one the AI takes, or a reading is already going.
 */
export function AnalyzeDocumentButton({
    organizationSlug,
    productId,
    document,
    analysis,
    unavailableNote,
    onError,
}: {
    organizationSlug: string;
    productId: number;
    document: ProductDocument;
    analysis: ProductDocumentAnalysis | undefined;
    unavailableNote: string | null;
    onError: (message: string | null) => void;
}) {
    const [processing, setProcessing] = useState(false);
    const pending = analysis?.is_pending ?? false;

    const reason =
        unavailableNote ??
        (document.analyzable
            ? null
            : t('Only PDFs and images up to 14 MB can be analyzed.'));

    const label = analysis
        ? t('Analyze :name again', { name: document.name })
        : t('Analyze :name with AI', { name: document.name });

    return (
        <Button
            variant="ghost"
            size="icon"
            className="size-8"
            data-test="document-analyze"
            title={reason ?? label}
            disabled={reason !== null || pending || processing}
            onClick={() =>
                router.post(
                    store.url([organizationSlug, productId, document.id]),
                    {},
                    {
                        preserveScroll: true,
                        onStart: () => {
                            setProcessing(true);
                            onError(null);
                        },
                        onHttpException: (response) => {
                            if (response.status !== 429) {
                                return;
                            }

                            onError(
                                t(
                                    'Too many AI requests for now. Try again in a little while.',
                                ),
                            );

                            return false;
                        },
                        onError: (errors) =>
                            onError(
                                errors.analysis ??
                                    t('The analysis could not be started.'),
                            ),
                        onFinish: () => setProcessing(false),
                    },
                )
            }
        >
            {processing ? (
                <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
                <Sparkles className="h-4 w-4" />
            )}
            <span className="sr-only">{label}</span>
        </Button>
    );
}

/**
 * Where the latest reading of one paper has got to, and what it said.
 *
 * A single line under the paper -- a spinner, a failure reason, or the
 * verdict -- that opens into the summary, the facts and the gaps. Nothing
 * at all for a paper never read, so an untouched list stays quiet.
 */
export function DocumentAnalysisResult({
    analysis,
    error,
}: {
    analysis: ProductDocumentAnalysis | undefined;
    error: string | null;
}) {
    const [open, setOpen] = useState(false);

    const errorLine =
        error === null ? null : (
            <p
                className="text-destructive text-xs"
                data-test="document-analysis-error"
            >
                {error}
            </p>
        );

    if (analysis === undefined) {
        return errorLine;
    }

    if (analysis.is_pending) {
        return (
            <>
                {errorLine}
                <p
                    className="text-muted-foreground inline-flex items-center gap-1.5 text-xs"
                    data-test="document-analysis-pending"
                >
                    <Loader2 className="h-3 w-3 animate-spin" />
                    {t('AI is reading this document…')}
                </p>
            </>
        );
    }

    if (analysis.status === 'failed') {
        return (
            <>
                {errorLine}
                <p
                    className="text-destructive text-xs"
                    data-test="document-analysis-failed"
                >
                    {analysis.failure_reason ?? t('The analysis failed.')}
                </p>
            </>
        );
    }

    const tone =
        OVERALL_TONES[analysis.overall ?? 'insufficient_documents'] ??
        OVERALL_TONES.insufficient_documents;
    const Icon = tone.icon;

    return (
        <div className="grid gap-2" data-test="document-analysis">
            {errorLine}
            <button
                type="button"
                aria-expanded={open}
                onClick={() => setOpen(!open)}
                className="focus-visible:ring-ring inline-flex w-fit items-center gap-2 rounded-md text-xs outline-none focus-visible:ring-2"
            >
                <span
                    className={cn(
                        'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-medium',
                        tone.className,
                    )}
                    data-test="document-analysis-verdict"
                >
                    <Icon className="h-3 w-3" />
                    {t('AI:')} {analysis.overall_label}
                </span>
                {analysis.findings.length > 0 ? (
                    <span className="text-muted-foreground">
                        {tc(
                            '1 finding|:count findings',
                            analysis.findings.length,
                        )}
                    </span>
                ) : null}
                {analysis.facts?.is_expired ? (
                    <span className="rounded-md bg-red-100 px-1.5 py-0.5 font-medium text-red-800 dark:bg-red-500/15 dark:text-red-300">
                        {t('Expired')}
                    </span>
                ) : null}
                {analysis.facts && !analysis.facts.type_matches ? (
                    <span className="rounded-md bg-amber-100 px-1.5 py-0.5 font-medium text-amber-900 dark:bg-amber-500/15 dark:text-amber-300">
                        {t('Type may be wrong')}
                    </span>
                ) : null}
                <ChevronDown
                    className={cn(
                        'text-muted-foreground h-3 w-3 transition-transform',
                        open && 'rotate-180',
                    )}
                />
            </button>

            {open ? <AnalysisDetails analysis={analysis} /> : null}
        </div>
    );
}

/**
 * The reading in full: summary, what the paper says about itself, and the
 * gaps, with a reminder that a person decides.
 */
function AnalysisDetails({ analysis }: { analysis: ProductDocumentAnalysis }) {
    const facts = analysis.facts;

    return (
        <div
            className="bg-muted/40 grid gap-3 rounded-lg border p-3"
            data-test="document-analysis-details"
        >
            {analysis.summary ? (
                <p className="text-sm leading-relaxed">{analysis.summary}</p>
            ) : null}

            {facts ? (
                <dl
                    className="grid gap-x-4 gap-y-1.5 text-xs sm:grid-cols-[auto_minmax(0,1fr)]"
                    data-test="document-analysis-facts"
                >
                    <Fact label={t('Issued by')} value={facts.issuer} />
                    <Fact label={t('Number')} value={facts.document_number} />
                    <Fact
                        label={t('Issued on')}
                        value={
                            facts.issued_on
                                ? formatCalendarDate(facts.issued_on)
                                : null
                        }
                    />
                    <Fact
                        label={t('Valid until')}
                        value={
                            facts.expires_on
                                ? formatCalendarDate(facts.expires_on) +
                                  (facts.is_expired ? ` (${t('expired')})` : '')
                                : null
                        }
                    />
                    <Fact label={t('Covers')} value={facts.covered_product} />
                    {facts.standards.length > 0 ? (
                        <>
                            <dt className="text-muted-foreground">
                                {t('Standards')}
                            </dt>
                            <dd className="flex flex-wrap gap-1">
                                {facts.standards.map((standard) => (
                                    <span
                                        key={standard}
                                        className="bg-background rounded border px-1.5 py-0.5"
                                    >
                                        {standard}
                                    </span>
                                ))}
                            </dd>
                        </>
                    ) : null}
                    {!facts.type_matches ? (
                        <>
                            <dt className="text-muted-foreground">
                                {t('Document type')}
                            </dt>
                            <dd
                                className="text-amber-800 dark:text-amber-300"
                                data-test="document-analysis-type-mismatch"
                            >
                                {facts.suggested_type_label
                                    ? t(
                                          'Looks like a :type, not what it was filed as.',
                                          {
                                              type: facts.suggested_type_label,
                                          },
                                      )
                                    : t('May not be what it was filed as.')}
                            </dd>
                        </>
                    ) : null}
                </dl>
            ) : null}

            {analysis.findings.length > 0 ? (
                <ul className="grid gap-2">
                    {analysis.findings.map((finding) => (
                        <FindingRow
                            key={finding.id}
                            finding={finding}
                            testPrefix="document-analysis-finding"
                        />
                    ))}
                </ul>
            ) : null}

            <p className="text-muted-foreground text-xs">
                {t(
                    'Read by :model on :date. Advisory only — a person decides.',
                    {
                        model: analysis.model_label,
                        date: formatDay(analysis.completed_at),
                    },
                )}
            </p>
        </div>
    );
}

function Fact({ label, value }: { label: string; value: string | null }) {
    if (value === null) {
        return null;
    }

    return (
        <>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="break-words">{value}</dd>
        </>
    );
}

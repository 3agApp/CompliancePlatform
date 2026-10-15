import {
    Form,
    Head,
    router,
    useHttp,
    usePage,
    usePoll,
} from '@inertiajs/react';
import { DatabaseZap, Download, RefreshCw, Save, Unplug } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatRelative } from '@/lib/format';
import { t, tc } from '@/lib/i18n';
import {
    destroy as destroySource,
    update as updateSource,
} from '@/routes/product-import/source';
import {
    index as manufacturersIndex,
    update as updateManufacturers,
} from '@/routes/product-import/manufacturers';
import { store as startRun } from '@/routes/product-import/runs';
import type {
    ImportSourceSettings,
    ImportRunSummary,
    ImportManufacturerChoice,
    ImportOption,
} from '@/types';

type TemplateOption = { id: number; label: string; categoryName: string };
type ConnectionOption = { id: number; label: string };

type Props = {
    source: ImportSourceSettings | null;
    manufacturers: ImportManufacturerChoice[];
    runs: ImportRunSummary[];
    hasPendingRun: boolean;
    hasAiProvider: boolean;
    availableTemplates: TemplateOption[];
    availableConnections: ConnectionOption[];
    providers: ImportOption[];
    matchKeys: ImportOption[];
    existingActions: ImportOption[];
};

/**
 * Radix Select cannot hold an empty value, so "nothing chosen" travels
 * through the select as this and is turned back into an empty field
 * before it is posted.
 */
const NONE = 'none';

/**
 * How often the page asks after a running import, in milliseconds.
 */
const POLL_INTERVAL = 3000;

export default function ProductImportIndex(props: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    return (
        <>
            <Head title={t('Product import')} />

            <div className="workspace-page">
                <div className="page-heading">
                    <h1 className="page-title">{t('Product import')}</h1>
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'Bring your catalogue in from the system you keep it in. Each manufacturer becomes a supplier, and each article a product filed under the template you choose.',
                        )}
                    </p>
                </div>

                <section
                    className="workspace-panel space-y-6 p-6"
                    data-test="import-source-section"
                >
                    <Heading
                        variant="small"
                        title={t('Source')}
                        description={t(
                            'Where your catalogue is kept, and what an import does with what it finds.',
                        )}
                    />
                    <SourceForm
                        organizationSlug={organizationSlug}
                        {...props}
                    />
                </section>

                {props.source !== null ? (
                    <section
                        className="workspace-panel space-y-6 p-6"
                        data-test="import-manufacturers-section"
                    >
                        <Heading
                            variant="small"
                            title={t('Manufacturers')}
                            description={t(
                                'Only the manufacturers ticked here are imported. Choose a supplier and a template for one, or leave them for the import to work out.',
                            )}
                        />
                        <ManufacturerPicker
                            organizationSlug={organizationSlug}
                            {...props}
                        />
                    </section>
                ) : null}

                {props.source !== null ? (
                    <section
                        className="workspace-panel space-y-6 p-6"
                        data-test="import-runs-section"
                    >
                        <Heading
                            variant="small"
                            title={t('Imports')}
                            description={t(
                                'An import runs in the background. You can leave this page; it keeps going.',
                            )}
                        />
                        <Runs organizationSlug={organizationSlug} {...props} />
                    </section>
                ) : null}
            </div>
        </>
    );
}

function SourceForm({
    organizationSlug,
    source,
    hasAiProvider,
    availableTemplates,
    providers,
    matchKeys,
    existingActions,
}: Props & { organizationSlug: string }) {
    const [provider, setProvider] = useState(
        source?.provider ?? providers[0]?.value ?? '',
    );
    const [matchKey, setMatchKey] = useState(
        source?.matchKey ?? matchKeys[0]?.value ?? '',
    );
    const [onExisting, setOnExisting] = useState(
        source?.onExisting ?? existingActions[0]?.value ?? '',
    );
    const [defaultTemplate, setDefaultTemplate] = useState(
        source?.defaultProductTemplateId?.toString() ?? NONE,
    );
    const [useAi, setUseAi] = useState(source?.useAiForTemplates ?? false);

    const disconnect = () => {
        router.visit(destroySource(organizationSlug), {
            preserveScroll: true,
        });
    };

    return (
        <Form
            {...updateSource.form(organizationSlug)}
            options={{ preserveScroll: true }}
            className="space-y-6"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-6 sm:grid-cols-2">
                        <div className="grid content-start gap-2">
                            <Label htmlFor="import-provider">
                                {t('Source')}
                            </Label>
                            <Select
                                value={provider}
                                onValueChange={setProvider}
                            >
                                <SelectTrigger
                                    id="import-provider"
                                    data-test="import-provider-select"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {providers.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="provider"
                                value={provider}
                            />
                            <InputError message={errors.provider} />
                        </div>

                        <div className="grid content-start gap-2">
                            <Label htmlFor="import-api-url">
                                {t('API URL')}
                            </Label>
                            <Input
                                id="import-api-url"
                                name="api_url"
                                type="url"
                                data-test="import-api-url"
                                defaultValue={source?.apiUrl ?? ''}
                                placeholder="https://…/api/v1/kontor"
                            />
                            <InputError message={errors.api_url} />
                        </div>

                        <div className="grid content-start gap-2">
                            <Label htmlFor="import-api-key">
                                {t('API key')}
                            </Label>
                            <Input
                                id="import-api-key"
                                name="api_key"
                                type="password"
                                autoComplete="off"
                                spellCheck={false}
                                data-test="import-api-key"
                                placeholder={
                                    source === null
                                        ? t('Paste the API key')
                                        : t(
                                              '•••• :hint — leave blank to keep this key',
                                              { hint: source.keyHint },
                                          )
                                }
                            />
                            <p className="text-muted-foreground text-xs">
                                {t(
                                    'Stored encrypted. It is never shown again and never sent to the browser.',
                                )}
                            </p>
                            <InputError message={errors.api_key} />
                        </div>

                        <div className="grid content-start gap-2">
                            <Label htmlFor="import-file-url-prefix">
                                {t('File URL prefix')}
                            </Label>
                            <Input
                                id="import-file-url-prefix"
                                name="file_url_prefix"
                                type="url"
                                data-test="import-file-url-prefix"
                                defaultValue={source?.fileUrlPrefix ?? ''}
                                placeholder="https://…/images"
                            />
                            <p className="text-muted-foreground text-xs">
                                {t(
                                    'Put in front of each image file name. Leave empty to import without images.',
                                )}
                            </p>
                            <InputError message={errors.file_url_prefix} />
                        </div>

                        <div className="grid content-start gap-2">
                            <Label htmlFor="import-match-key">
                                {t('Match existing products by')}
                            </Label>
                            <Select
                                value={matchKey}
                                onValueChange={setMatchKey}
                            >
                                <SelectTrigger
                                    id="import-match-key"
                                    data-test="import-match-key-select"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {matchKeys.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="match_key"
                                value={matchKey}
                            />
                            <InputError message={errors.match_key} />
                        </div>

                        <div className="grid content-start gap-2">
                            <Label htmlFor="import-on-existing">
                                {t('When a product already exists')}
                            </Label>
                            <Select
                                value={onExisting}
                                onValueChange={setOnExisting}
                            >
                                <SelectTrigger
                                    id="import-on-existing"
                                    data-test="import-on-existing-select"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {existingActions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="on_existing"
                                value={onExisting}
                            />
                            <InputError message={errors.on_existing} />
                        </div>

                        <div className="grid content-start gap-2 sm:col-span-2">
                            <Label htmlFor="import-default-template">
                                {t('Default template')}
                            </Label>
                            <TemplateSelect
                                id="import-default-template"
                                testId="import-default-template-select"
                                value={defaultTemplate}
                                onChange={setDefaultTemplate}
                                templates={availableTemplates}
                                noneLabel={t('None — leave such products out')}
                            />
                            <input
                                type="hidden"
                                name="default_product_template_id"
                                value={
                                    defaultTemplate === NONE
                                        ? ''
                                        : defaultTemplate
                                }
                            />
                            <p className="text-muted-foreground text-xs">
                                {t(
                                    'Used for a new product when neither its manufacturer nor the AI names a template.',
                                )}
                            </p>
                            <InputError
                                message={errors.default_product_template_id}
                            />
                        </div>
                    </div>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="import-use-ai"
                            data-test="import-use-ai"
                            checked={useAi && hasAiProvider}
                            disabled={!hasAiProvider}
                            onCheckedChange={(value) =>
                                setUseAi(value === true)
                            }
                            className="mt-0.5"
                        />
                        <input
                            type="hidden"
                            name="use_ai_for_templates"
                            value={useAi && hasAiProvider ? '1' : '0'}
                        />
                        <div className="grid gap-1">
                            <Label
                                htmlFor="import-use-ai"
                                className="font-normal"
                            >
                                {t('Let AI choose the template')}
                            </Label>
                            <p className="text-muted-foreground text-sm">
                                {hasAiProvider
                                    ? t(
                                          'For a manufacturer without a template, the name and short description of each new article are sent to your AI provider to pick one. Products placed this way say so in their history.',
                                      )
                                    : t(
                                          'Connect an AI provider in the organization settings to use this.',
                                      )}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="submit"
                            data-test="save-import-source-submit"
                            disabled={processing}
                        >
                            <Save className="h-4 w-4" />
                            {processing ? t('Saving…') : t('Save source')}
                        </Button>

                        {source !== null ? (
                            <Button
                                type="button"
                                variant="ghost"
                                data-test="disconnect-import-button"
                                onClick={disconnect}
                            >
                                <Unplug className="h-4 w-4" />
                                {t('Disconnect')}
                            </Button>
                        ) : null}
                    </div>
                </>
            )}
        </Form>
    );
}

function TemplateSelect({
    id,
    testId,
    value,
    onChange,
    templates,
    noneLabel,
}: {
    id: string;
    testId: string;
    value: string;
    onChange: (value: string) => void;
    templates: TemplateOption[];
    noneLabel: string;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger id={id} data-test={testId} className="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={NONE}>{noneLabel}</SelectItem>
                {templates.map((template) => (
                    <SelectItem
                        key={template.id}
                        value={template.id.toString()}
                    >
                        {template.categoryName} · {template.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

type FetchedManufacturer = { id: string; name: string };

function ManufacturerPicker({
    organizationSlug,
    manufacturers,
    availableTemplates,
    availableConnections,
}: Props & { organizationSlug: string }) {
    const { errors } = usePage().props;

    const [chosen, setChosen] =
        useState<ImportManufacturerChoice[]>(manufacturers);
    const [fetched, setFetched] = useState<FetchedManufacturer[] | null>(null);
    const [fetchError, setFetchError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => setChosen(manufacturers), [manufacturers]);

    const request = useHttp<
        Record<string, never>,
        { manufacturers: FetchedManufacturer[]; error: string | null }
    >({});

    const fetchManufacturers = () => {
        setFetchError(null);

        void request.get(manufacturersIndex.url(organizationSlug), {
            onSuccess: (response) => {
                setFetched(response.manufacturers);
                setFetchError(response.error);
            },
        });
    };

    /**
     * Everything the source lists, plus anything ticked earlier that the source
     * no longer lists, so a choice never disappears without being undone.
     */
    const rows: FetchedManufacturer[] = [
        ...(fetched ?? []),
        ...chosen
            .filter(
                (choice) =>
                    !(fetched ?? []).some(
                        (row) => row.id === choice.externalId,
                    ),
            )
            .map((choice) => ({ id: choice.externalId, name: choice.name })),
    ];

    const choiceFor = (id: string) =>
        chosen.find((choice) => choice.externalId === id);

    const toggle = (row: FetchedManufacturer, checked: boolean) => {
        setChosen((current) =>
            checked
                ? [
                      ...current,
                      {
                          externalId: row.id,
                          name: row.name,
                          supplierConnectionId: null,
                          productTemplateId: null,
                      },
                  ]
                : current.filter((choice) => choice.externalId !== row.id),
        );
    };

    const change = (id: string, patch: Partial<ImportManufacturerChoice>) => {
        setChosen((current) =>
            current.map((choice) =>
                choice.externalId === id ? { ...choice, ...patch } : choice,
            ),
        );
    };

    const save = () => {
        router.put(
            updateManufacturers.url(organizationSlug),
            {
                manufacturers: chosen.map((choice) => ({
                    external_id: choice.externalId,
                    name: choice.name,
                    supplier_connection_id: choice.supplierConnectionId,
                    product_template_id: choice.productTemplateId,
                })),
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    const firstError = Object.entries(errors ?? {}).find(([key]) =>
        key.startsWith('manufacturers'),
    )?.[1];

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    data-test="fetch-import-manufacturers-button"
                    onClick={fetchManufacturers}
                    disabled={request.processing}
                >
                    {request.processing ? (
                        <Spinner />
                    ) : (
                        <RefreshCw className="h-4 w-4" />
                    )}
                    {t('Fetch manufacturers')}
                </Button>
                <span className="text-muted-foreground text-sm">
                    {tc(
                        '1 manufacturer chosen|:count manufacturers chosen',
                        chosen.length,
                    )}
                </span>
            </div>

            {fetchError !== null ? (
                <p
                    className="text-destructive text-sm"
                    data-test="import-manufacturers-error"
                >
                    {fetchError}
                </p>
            ) : null}

            {rows.length > 0 ? (
                <ul
                    className="divide-y rounded-lg border"
                    data-test="import-manufacturer-list"
                >
                    {rows.map((row) => {
                        const choice = choiceFor(row.id);

                        return (
                            <li
                                key={row.id}
                                className="grid gap-3 p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] md:items-center"
                                data-test="import-manufacturer-row"
                            >
                                <label className="flex items-center gap-3">
                                    <Checkbox
                                        checked={choice !== undefined}
                                        data-test="import-manufacturer-checkbox"
                                        onCheckedChange={(value) =>
                                            toggle(row, value === true)
                                        }
                                    />
                                    <span className="font-medium break-words">
                                        {row.name}
                                    </span>
                                    <span className="text-muted-foreground text-xs">
                                        #{row.id}
                                    </span>
                                </label>

                                {choice !== undefined ? (
                                    <>
                                        <Select
                                            value={
                                                choice.supplierConnectionId?.toString() ??
                                                NONE
                                            }
                                            onValueChange={(value) =>
                                                change(row.id, {
                                                    supplierConnectionId:
                                                        value === NONE
                                                            ? null
                                                            : Number(value),
                                                })
                                            }
                                        >
                                            <SelectTrigger
                                                className="w-full"
                                                aria-label={t('Supplier')}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE}>
                                                    {t(
                                                        'Supplier with the same name',
                                                    )}
                                                </SelectItem>
                                                {availableConnections.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.id}
                                                            value={option.id.toString()}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>

                                        <TemplateSelect
                                            id={`import-template-${row.id}`}
                                            testId="import-manufacturer-template-select"
                                            value={
                                                choice.productTemplateId?.toString() ??
                                                NONE
                                            }
                                            onChange={(value) =>
                                                change(row.id, {
                                                    productTemplateId:
                                                        value === NONE
                                                            ? null
                                                            : Number(value),
                                                })
                                            }
                                            templates={availableTemplates}
                                            noneLabel={t(
                                                'AI or default template',
                                            )}
                                        />
                                    </>
                                ) : null}
                            </li>
                        );
                    })}
                </ul>
            ) : (
                <p className="text-muted-foreground text-sm">
                    {t(
                        'Fetch the list from the source to choose manufacturers.',
                    )}
                </p>
            )}

            <InputError message={firstError} />

            <Button
                type="button"
                data-test="save-import-manufacturers-button"
                onClick={save}
                disabled={saving}
            >
                <Save className="h-4 w-4" />
                {saving ? t('Saving…') : t('Save manufacturers')}
            </Button>
        </div>
    );
}

function Runs({
    organizationSlug,
    runs,
    hasPendingRun,
    manufacturers,
}: Props & { organizationSlug: string }) {
    const { errors } = usePage().props;

    const { start, stop } = usePoll(
        POLL_INTERVAL,
        { only: ['runs', 'hasPendingRun'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (hasPendingRun) {
            start();
        } else {
            stop();
        }
    }, [hasPendingRun, start, stop]);

    return (
        <div className="space-y-4">
            <Form
                {...startRun.form(organizationSlug)}
                options={{ preserveScroll: true }}
                className="flex flex-wrap items-center gap-3"
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        data-test="start-import-button"
                        disabled={
                            processing ||
                            hasPendingRun ||
                            manufacturers.length === 0
                        }
                    >
                        {hasPendingRun ? (
                            <Spinner />
                        ) : (
                            <Download className="h-4 w-4" />
                        )}
                        {hasPendingRun ? t('Importing…') : t('Import now')}
                    </Button>
                )}
            </Form>
            <InputError message={errors?.import} />

            {runs.length > 0 ? (
                <div className="workspace-table">
                    <div className="min-w-0 overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="text-muted-foreground">
                                    <th className="px-6 font-medium">
                                        {t('Started')}
                                    </th>
                                    <th className="px-6 font-medium">
                                        {t('Status')}
                                    </th>
                                    <th className="px-6 font-medium">
                                        {t('Created')}
                                    </th>
                                    <th className="px-6 font-medium">
                                        {t('Updated')}
                                    </th>
                                    <th className="px-6 font-medium">
                                        {t('Skipped')}
                                    </th>
                                    <th className="px-6 font-medium">
                                        {t('Failed')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {runs.map((run) => (
                                    <RunRow key={run.id} run={run} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            ) : (
                <p className="text-muted-foreground flex items-center gap-2 text-sm">
                    <DatabaseZap className="h-4 w-4" />
                    {t('Nothing has been imported yet.')}
                </p>
            )}
        </div>
    );
}

function RunRow({ run }: { run: ImportRunSummary }) {
    return (
        <>
            <tr className="border-t" data-test="import-run-row">
                <td className="px-6" data-label={t('Started')}>
                    <span className="block">
                        {formatRelative(run.createdAt)}
                    </span>
                    {run.triggeredBy !== null ? (
                        <span className="text-muted-foreground text-xs">
                            {run.triggeredBy}
                        </span>
                    ) : null}
                </td>
                <td className="px-6" data-label={t('Status')}>
                    <span data-test="import-run-status">{run.statusLabel}</span>
                    {run.isPending && run.total !== null ? (
                        <span className="text-muted-foreground block text-xs">
                            {t(':processed of :total', {
                                processed: run.processed,
                                total: run.total,
                            })}
                        </span>
                    ) : null}
                    {run.failureReason !== null ? (
                        <span className="text-destructive block text-xs">
                            {run.failureReason}
                        </span>
                    ) : null}
                </td>
                <td className="px-6" data-label={t('Created')}>
                    <span data-test="import-run-created">{run.created}</span>
                    {run.aiAssigned > 0 ? (
                        <span className="text-muted-foreground block text-xs">
                            {tc(
                                '1 template chosen by AI|:count templates chosen by AI',
                                run.aiAssigned,
                            )}
                        </span>
                    ) : null}
                </td>
                <td className="px-6" data-label={t('Updated')}>
                    {run.updated}
                </td>
                <td className="px-6" data-label={t('Skipped')}>
                    {run.skipped}
                </td>
                <td className="px-6" data-label={t('Failed')}>
                    {run.failed}
                </td>
            </tr>
            {run.failures.length > 0 ? (
                <tr>
                    <td colSpan={6} className="px-6 pb-4">
                        <details data-test="import-run-failures">
                            <summary className="text-muted-foreground cursor-pointer text-xs">
                                {tc(
                                    'Show 1 problem|Show :count problems',
                                    run.failures.length,
                                )}
                            </summary>
                            <ul className="mt-2 space-y-1 text-xs">
                                {run.failures.map((failure, index) => (
                                    <li key={index}>
                                        <span className="font-mono">
                                            {failure.article}
                                        </span>
                                        {' — '}
                                        {failure.reason}
                                    </li>
                                ))}
                            </ul>
                        </details>
                    </td>
                </tr>
            ) : null}
        </>
    );
}

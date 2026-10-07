import { Form, router } from '@inertiajs/react';
import { Sparkles, Unplug } from 'lucide-react';
import { useState } from 'react';
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
import { t } from '@/lib/i18n';
import { destroy, update } from '@/routes/organizations/ai-provider';
import type { AiProviderOption, AiProviderSetting } from '@/types';

type Props = {
    organizationSlug: string;
    setting: AiProviderSetting | null;
    availableProviders: AiProviderOption[];
};

/**
 * Connect the AI provider an organization pays for itself.
 *
 * The provider and the model are chosen together: a model belongs to one
 * provider, so changing the provider moves the model with it rather than
 * leaving a pair behind that the server would only refuse.
 *
 * The key already stored is never sent back down, so the field is empty on
 * every visit. Left empty it means "keep the one you have", which is what
 * lets someone change the model without going to find their key again.
 */
export default function OrganizationAiProviderForm({
    organizationSlug,
    setting,
    availableProviders,
}: Props) {
    const fallback = availableProviders[0];

    const [providerValue, setProviderValue] = useState(
        setting?.provider ?? fallback.value,
    );
    const [model, setModel] = useState(
        setting?.model ?? fallback.default_model,
    );
    const [allowDocumentAnalysis, setAllowDocumentAnalysis] = useState(
        setting?.allow_document_analysis ?? false,
    );

    const provider =
        availableProviders.find((option) => option.value === providerValue) ??
        fallback;

    /**
     * A model belongs to its provider, so the two move together. Setting
     * them apart would leave the form holding a pair the server refuses.
     */
    const chooseProvider = (value: string) => {
        const chosen = availableProviders.find(
            (option) => option.value === value,
        );

        if (chosen === undefined) {
            return;
        }

        setProviderValue(chosen.value);
        setModel(chosen.default_model);
    };

    const disconnect = () => {
        router.visit(destroy(organizationSlug), { preserveScroll: true });
    };

    return (
        <Form
            {...update.form(organizationSlug)}
            options={{ preserveScroll: true }}
            className="space-y-6"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-6 sm:grid-cols-2">
                        <div className="grid content-start gap-2">
                            <Label htmlFor="ai-provider">{t('Provider')}</Label>
                            <Select
                                value={providerValue}
                                onValueChange={chooseProvider}
                            >
                                <SelectTrigger
                                    id="ai-provider"
                                    data-test="ai-provider-select"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableProviders.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {/*
                             * Radix Select is not a form control, so the
                             * value the form posts comes from here.
                             */}
                            <input
                                type="hidden"
                                name="provider"
                                value={providerValue}
                            />
                            <InputError message={errors.provider} />
                        </div>

                        <div className="grid content-start gap-2">
                            <Label htmlFor="ai-model">{t('Model')}</Label>
                            <Select value={model} onValueChange={setModel}>
                                <SelectTrigger
                                    id="ai-model"
                                    data-test="ai-model-select"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {provider.models.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <input type="hidden" name="model" value={model} />
                            <InputError message={errors.model} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="ai-api-key">{t('API key')}</Label>
                        <Input
                            id="ai-api-key"
                            name="api_key"
                            type="password"
                            autoComplete="off"
                            spellCheck={false}
                            data-test="ai-api-key"
                            placeholder={
                                setting === null
                                    ? t('Paste the key from your provider')
                                    : t(
                                          '•••• :hint — leave blank to keep this key',
                                          { hint: setting.key_hint },
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

                    {/*
                     * Its own choice rather than part of having a key:
                     * naming a file's kind sends only its name, while the
                     * AI check sends the file itself.
                     */}
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="ai-allow-document-analysis"
                            data-test="ai-allow-document-analysis"
                            checked={allowDocumentAnalysis}
                            onCheckedChange={(value) =>
                                setAllowDocumentAnalysis(value === true)
                            }
                            className="mt-0.5"
                        />
                        <input
                            type="hidden"
                            name="allow_document_analysis"
                            value={allowDocumentAnalysis ? '1' : '0'}
                        />
                        <div className="grid gap-1">
                            <Label
                                htmlFor="ai-allow-document-analysis"
                                className="font-normal"
                            >
                                {t(
                                    'Allow the AI check to read product documents',
                                )}
                            </Label>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Test reports, declarations, certificates and images are sent to your AI provider to be read. Leave this off if your agreements with suppliers do not allow that.',
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="submit"
                            data-test="save-ai-provider-submit"
                            disabled={processing}
                        >
                            <Sparkles className="h-4 w-4" />
                            {processing ? t('Saving…') : t('Save provider')}
                        </Button>

                        {setting !== null ? (
                            <Button
                                type="button"
                                variant="ghost"
                                data-test="disconnect-ai-provider-button"
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

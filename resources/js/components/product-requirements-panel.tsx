import { Check, Circle, FileText, ListChecks } from 'lucide-react';
import CompletenessMeter from '@/components/completeness-meter';
import type {
    ProductCompleteness,
    ProductCompletenessItem,
    ProductRequirementGroup,
} from '@/types';
import { t } from '@/lib/i18n';

type Props = {
    completeness: ProductCompleteness;
    templateLabel: string;
};

/**
 * The homework, as a panel beside the product.
 *
 * Nothing here is enforced -- the product saves whether or not a single line
 * is answered -- so the panel reads as a list of what is still owed rather
 * than as a list of errors. Outstanding items come first, because those are
 * the ones anybody opened the page to deal with.
 */
export default function ProductRequirementsPanel({
    completeness,
    templateLabel,
}: Props) {
    const { score, items } = completeness;

    const outstanding = items.filter((item) => !item.satisfied);
    const done = items.filter((item) => item.satisfied);

    return (
        <section
            className="workspace-panel grid gap-5 p-6"
            data-test="product-requirements-panel"
        >
            <div className="grid gap-2">
                <div className="flex items-baseline justify-between gap-3">
                    <h2 className="text-base font-semibold">
                        {t('Requirements')}
                    </h2>
                    <span
                        className="text-2xl font-semibold tabular-nums"
                        data-test="product-completeness-score"
                    >
                        {score}%
                    </span>
                </div>

                <CompletenessMeter score={score} className="w-full" hideLabel />

                <p className="text-muted-foreground text-xs">
                    {t(
                        'What the :template template expects. Nothing here blocks saving.',
                        { template: templateLabel },
                    )}
                </p>
            </div>

            {items.length === 0 ? (
                <p
                    className="text-muted-foreground text-sm"
                    data-test="product-requirements-empty"
                >
                    {t(
                        'This template asks for nothing. Add requirements to it under Categories to turn it into a checklist.',
                    )}
                </p>
            ) : null}

            {outstanding.length > 0 ? (
                <RequirementList
                    title={t('Still needed')}
                    items={outstanding}
                />
            ) : null}

            {done.length > 0 ? (
                <RequirementList title={t('Done')} items={done} />
            ) : null}
        </section>
    );
}

function RequirementList({
    title,
    items,
}: {
    title: string;
    items: ProductCompletenessItem[];
}) {
    const groups: Array<{
        group: ProductRequirementGroup;
        label: string;
        icon: React.ReactNode;
    }> = [
        {
            group: 'document',
            label: t('Documents'),
            icon: <FileText className="size-3.5" />,
        },
        {
            group: 'data',
            label: t('Product data'),
            icon: <ListChecks className="size-3.5" />,
        },
    ];

    return (
        <div className="grid gap-3">
            <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                {title}
            </p>

            {groups.map(({ group, label, icon }) => {
                const inGroup = items.filter((item) => item.group === group);

                if (inGroup.length === 0) {
                    return null;
                }

                return (
                    <div key={group} className="grid gap-1.5">
                        <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                            {icon}
                            {label}
                        </p>

                        <ul className="grid gap-1.5">
                            {inGroup.map((item) => (
                                <li
                                    key={item.requirement}
                                    className="flex items-start gap-2 text-sm"
                                    data-test="product-requirement"
                                >
                                    {item.satisfied ? (
                                        <Check className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                    ) : (
                                        <Circle className="text-muted-foreground/50 mt-0.5 size-4 shrink-0" />
                                    )}

                                    <span
                                        className={
                                            item.satisfied
                                                ? 'text-muted-foreground'
                                                : undefined
                                        }
                                    >
                                        {item.label}
                                        {item.weight === 0 ? (
                                            <span className="text-muted-foreground ml-1 text-xs">
                                                {t(
                                                    '(does not affect the score)',
                                                )}
                                            </span>
                                        ) : null}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                );
            })}
        </div>
    );
}

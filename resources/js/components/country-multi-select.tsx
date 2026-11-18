import { X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import type { CountryOption } from '@/types';

type Props = {
    id: string;
    /** The field the countries are submitted as, one per `name[]`. */
    name: string;
    options: CountryOption[];
    defaultValue?: string[];
};

/**
 * Pick any number of countries from the full list.
 *
 * Two hundred and fifty countries are too many to scroll through, so the
 * list filters as you type, and what is chosen sits above it as chips that
 * can be taken off again. Nothing chosen still submits the field, empty, so
 * clearing every country on an existing brand is saved too.
 */
export default function CountryMultiSelect({
    id,
    name,
    options,
    defaultValue = [],
}: Props) {
    const [selected, setSelected] = useState<string[]>(defaultValue);
    const [query, setQuery] = useState('');

    const labels = useMemo(
        () => new Map(options.map((option) => [option.value, option.label])),
        [options],
    );

    const needle = query.trim().toLowerCase();
    const visible = needle
        ? options.filter(
              (option) =>
                  option.label.toLowerCase().includes(needle) ||
                  option.value.toLowerCase() === needle,
          )
        : options;

    const toggle = (code: string, checked: boolean) =>
        setSelected((current) =>
            checked
                ? [...current, code]
                : current.filter((value) => value !== code),
        );

    return (
        <div className="grid gap-2" data-test="country-multi-select">
            {selected.length > 0 ? (
                <ul className="flex flex-wrap gap-1.5">
                    {selected.map((code) => (
                        <li
                            key={code}
                            className="bg-muted inline-flex items-center gap-1 rounded-md py-0.5 pr-1 pl-2 text-xs"
                            data-test="country-chip"
                        >
                            {labels.get(code) ?? code}
                            <button
                                type="button"
                                onClick={() => toggle(code, false)}
                                className="text-muted-foreground hover:text-foreground rounded-sm"
                                aria-label={t('Remove :country', {
                                    country: labels.get(code) ?? code,
                                })}
                            >
                                <X className="size-3" />
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}

            <Input
                id={id}
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={t('Search countries')}
                autoComplete="off"
                data-test="country-search"
            />

            <ul className="max-h-44 overflow-y-auto rounded-md border p-1">
                {visible.map((option) => {
                    const checkboxId = `${id}-${option.value}`;

                    return (
                        <li key={option.value}>
                            <label
                                htmlFor={checkboxId}
                                className="hover:bg-muted flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm"
                                data-test={`country-option-${option.value}`}
                            >
                                <Checkbox
                                    id={checkboxId}
                                    checked={selected.includes(option.value)}
                                    onCheckedChange={(checked) =>
                                        toggle(option.value, checked === true)
                                    }
                                />
                                {option.label}
                            </label>
                        </li>
                    );
                })}
                {visible.length === 0 ? (
                    <li className="text-muted-foreground px-2 py-1.5 text-sm">
                        {t('No country matches.')}
                    </li>
                ) : null}
            </ul>

            {selected.length > 0 ? (
                selected.map((code) => (
                    <input
                        key={code}
                        type="hidden"
                        name={`${name}[]`}
                        value={code}
                    />
                ))
            ) : (
                <input type="hidden" name={name} value="" />
            )}
        </div>
    );
}

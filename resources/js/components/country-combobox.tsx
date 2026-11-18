import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type * as React from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { CountryOption } from '@/types/products';

/**
 * The flag of a country, drawn from its two-letter code.
 *
 * Built from the regional indicator letters, so it costs no images; a
 * system without flag glyphs shows the two letters instead, which still
 * reads as the country.
 */
export function countryFlag(code: string): string {
    return code
        .toUpperCase()
        .replace(/./g, (letter) =>
            String.fromCodePoint(127397 + letter.charCodeAt(0)),
        );
}

/**
 * Tell the surrounding form that a value changed.
 *
 * Inertia works out whether a form is dirty from its change events, and
 * React only reports a change event that comes from a real select or
 * input. So the value travels in a hidden native select -- the way Radix
 * Select does it -- and after a change has rendered, the select raises one.
 */
function useAnnounceChange<T>(value: T) {
    const select = useRef<HTMLSelectElement>(null);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        select.current?.dispatchEvent(new Event('change', { bubbles: true }));
    }, [value]);

    return select;
}

/**
 * The hidden select a combobox's value is submitted in.
 */
function HiddenSelect({
    name,
    values,
    multiple = false,
    selectRef,
}: {
    name?: string;
    values: string[];
    multiple?: boolean;
    selectRef: React.RefObject<HTMLSelectElement | null>;
}) {
    return (
        <select
            ref={selectRef}
            name={name}
            multiple={multiple}
            value={multiple ? values : (values[0] ?? '')}
            onChange={() => {}}
            aria-hidden="true"
            tabIndex={-1}
            hidden
        >
            {!multiple && <option value="" />}
            {values.map((code) => (
                <option key={code} value={code} />
            ))}
        </select>
    );
}

type CountryListProps = {
    options: CountryOption[];
    isSelected: (code: string) => boolean;
    onSelect: (code: string) => void;
};

function CountryList({ options, isSelected, onSelect }: CountryListProps) {
    return (
        <Command
            filter={(value, search, keywords) => {
                const query = search.trim().toLocaleLowerCase();
                const label = (keywords?.[0] ?? '').toLocaleLowerCase();
                const code = value.toLocaleLowerCase();

                /**
                 * The name starting with what was typed comes first, then a
                 * word inside it, then the code, then anything containing
                 * it -- so "ger" finds Germany before Algeria.
                 */
                if (label.startsWith(query)) {
                    return 1;
                }

                if (
                    label
                        .split(/[\s\-(]+/)
                        .some((word) => word.startsWith(query))
                ) {
                    return 0.75;
                }

                if (code === query) {
                    return 0.6;
                }

                return label.includes(query) ? 0.25 : 0;
            }}
        >
            <CommandInput placeholder={t('Search countries…')} />
            <CommandList>
                <CommandEmpty>{t('No country found.')}</CommandEmpty>
                <CommandGroup>
                    {options.map((option) => (
                        <CommandItem
                            key={option.value}
                            value={option.value}
                            keywords={[option.label]}
                            onSelect={() => onSelect(option.value)}
                            data-test={`country-option-${option.value}`}
                        >
                            <span aria-hidden="true" className="text-base">
                                {countryFlag(option.value)}
                            </span>
                            <span className="flex-1 truncate">
                                {option.label}
                            </span>
                            <Check
                                className={cn(
                                    'size-4',
                                    isSelected(option.value)
                                        ? 'opacity-100'
                                        : 'opacity-0',
                                )}
                            />
                        </CommandItem>
                    ))}
                </CommandGroup>
            </CommandList>
        </Command>
    );
}

type CountryComboboxProps = {
    id?: string;
    name?: string;
    options: CountryOption[];
    value: string | null;
    onChange: (value: string | null) => void;
    placeholder?: string;
    disabled?: boolean;
    clearable?: boolean;
    invalid?: boolean;
    className?: string;
    'data-test'?: string;
};

/**
 * One country, searched for by name or code.
 *
 * A form field of its own when given a name: the chosen code goes along in
 * a hidden select, empty when nothing is chosen, so clearing it is sent too.
 */
export function CountryCombobox({
    id,
    name,
    options,
    value,
    onChange,
    placeholder,
    disabled = false,
    clearable = true,
    invalid = false,
    className,
    'data-test': dataTest,
}: CountryComboboxProps) {
    const [open, setOpen] = useState(false);
    const select = useAnnounceChange(value);
    const selected = options.find((option) => option.value === value);

    return (
        <span className={cn('relative block', className)}>
            <Popover open={open} onOpenChange={setOpen} modal>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        aria-invalid={invalid || undefined}
                        disabled={disabled}
                        data-test={dataTest}
                        className="aria-invalid:border-destructive w-full justify-between px-3 font-normal"
                    >
                        {selected ? (
                            <span className="flex min-w-0 items-center gap-2">
                                <span aria-hidden="true">
                                    {countryFlag(selected.value)}
                                </span>
                                <span className="truncate">
                                    {selected.label}
                                </span>
                            </span>
                        ) : (
                            <span className="text-muted-foreground">
                                {placeholder ?? t('Select a country')}
                            </span>
                        )}
                        <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-(--radix-popover-trigger-width) min-w-64 p-0"
                    align="start"
                >
                    <CountryList
                        options={options}
                        isSelected={(code) => code === value}
                        onSelect={(code) => {
                            onChange(code === value && clearable ? null : code);
                            setOpen(false);
                        }}
                    />
                </PopoverContent>
            </Popover>
            <HiddenSelect
                name={name}
                values={value === null ? [] : [value]}
                selectRef={select}
            />
        </span>
    );
}

type CountryMultiSelectProps = {
    id?: string;
    name?: string;
    options: CountryOption[];
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    disabled?: boolean;
    max?: number;
    'data-test'?: string;
};

/**
 * Several countries, shown as removable chips under a search.
 *
 * Sent as `name[]`, one selected option per country. With none chosen a
 * single empty value goes instead, so emptying the list is a change the
 * server hears rather than a field it never received.
 */
export function CountryMultiSelect({
    id,
    name,
    options,
    value,
    onChange,
    placeholder,
    disabled = false,
    max,
    'data-test': dataTest,
}: CountryMultiSelectProps) {
    const [open, setOpen] = useState(false);
    const select = useAnnounceChange(value.join(','));
    const labelOf = (code: string) =>
        options.find((option) => option.value === code)?.label ?? code;
    const full = max !== undefined && value.length >= max;

    const toggle = (code: string) => {
        if (value.includes(code)) {
            onChange(value.filter((selected) => selected !== code));

            return;
        }

        if (!full) {
            onChange([...value, code]);
        }
    };

    return (
        <span className="block space-y-2">
            <Popover open={open} onOpenChange={setOpen} modal>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        disabled={disabled}
                        data-test={dataTest}
                        className="w-full justify-between px-3 font-normal sm:max-w-sm"
                    >
                        <span className="text-muted-foreground">
                            {value.length === 0
                                ? (placeholder ?? t('Add countries'))
                                : t(':count selected', {
                                      count: value.length,
                                  })}
                        </span>
                        <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-(--radix-popover-trigger-width) min-w-64 p-0"
                    align="start"
                >
                    <CountryList
                        options={options}
                        isSelected={(code) => value.includes(code)}
                        onSelect={toggle}
                    />
                </PopoverContent>
            </Popover>

            {value.length > 0 && (
                <ul
                    className="flex flex-wrap gap-1.5"
                    data-test={dataTest ? `${dataTest}-chips` : undefined}
                >
                    {value.map((code) => (
                        <li
                            key={code}
                            className="bg-muted inline-flex items-center gap-1.5 rounded-full border py-0.5 ps-2.5 pe-1 text-sm"
                        >
                            <span aria-hidden="true">{countryFlag(code)}</span>
                            {labelOf(code)}
                            <button
                                type="button"
                                disabled={disabled}
                                onClick={() => toggle(code)}
                                className="hover:bg-background text-muted-foreground hover:text-foreground rounded-full p-0.5 transition-colors disabled:opacity-50"
                                aria-label={t('Remove :country', {
                                    country: labelOf(code),
                                })}
                            >
                                <X className="size-3.5" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <HiddenSelect
                name={name === undefined ? undefined : `${name}[]`}
                values={value}
                multiple
                selectRef={select}
            />
            {name !== undefined && value.length === 0 && (
                <input type="hidden" name={name} value="" />
            )}
        </span>
    );
}

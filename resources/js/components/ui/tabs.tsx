import { cn } from '@/lib/utils';

export type TabDefinition<T extends string> = {
    value: T;
    label: string;
    /** Shown after the label, for a count or a marker. */
    badge?: React.ReactNode;
};

type Props<T extends string> = {
    tabs: TabDefinition<T>[];
    value: T;
    onValueChange: (value: T) => void;
    /** Shared between the tab strip and its panels, so aria wiring lines up. */
    idPrefix: string;
    className?: string;
};

/**
 * A tab strip, hand rolled rather than taken from Radix.
 *
 * Radix unmounts the panel that is not showing, which is exactly wrong for
 * a form: an input that is not in the DOM is an input the browser does not
 * submit, so switching tabs would silently drop whatever was typed on the
 * others. Here the panels all stay mounted and the hidden ones are hidden,
 * which is what TabPanel below is for.
 *
 * Keyboard behaviour follows the tabs pattern: arrows move between tabs,
 * Home and End jump to the ends, and only the selected tab is tabbable.
 */
export function TabStrip<T extends string>({
    tabs,
    value,
    onValueChange,
    idPrefix,
    className,
}: Props<T>) {
    const move = (event: React.KeyboardEvent, index: number) => {
        const last = tabs.length - 1;

        const next = {
            ArrowRight: index === last ? 0 : index + 1,
            ArrowLeft: index === 0 ? last : index - 1,
            Home: 0,
            End: last,
        }[event.key];

        if (next === undefined) {
            return;
        }

        event.preventDefault();
        onValueChange(tabs[next].value);
        document.getElementById(`${idPrefix}-tab-${tabs[next].value}`)?.focus();
    };

    return (
        <div
            role="tablist"
            aria-orientation="horizontal"
            className={cn(
                'bg-muted/60 inline-flex max-w-full gap-1 overflow-x-auto rounded-xl p-1',
                className,
            )}
        >
            {tabs.map((tab, index) => {
                const isSelected = tab.value === value;

                return (
                    <button
                        key={tab.value}
                        type="button"
                        role="tab"
                        id={`${idPrefix}-tab-${tab.value}`}
                        data-test={`${idPrefix}-tab-${tab.value}`}
                        aria-selected={isSelected}
                        aria-controls={`${idPrefix}-panel-${tab.value}`}
                        tabIndex={isSelected ? 0 : -1}
                        onClick={() => onValueChange(tab.value)}
                        onKeyDown={(event) => move(event, index)}
                        className={cn(
                            'focus-visible:ring-ring inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-2',
                            isSelected
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {tab.label}
                        {tab.badge}
                    </button>
                );
            })}
        </div>
    );
}

/**
 * One panel of a tab strip.
 *
 * Always rendered, only hidden, so the fields inside a form tab nobody has
 * opened are still submitted with it.
 */
export function TabPanel({
    value,
    active,
    idPrefix,
    className,
    children,
}: {
    value: string;
    active: string;
    idPrefix: string;
    className?: string;
    children: React.ReactNode;
}) {
    const isActive = value === active;

    return (
        <div
            role="tabpanel"
            id={`${idPrefix}-panel-${value}`}
            aria-labelledby={`${idPrefix}-tab-${value}`}
            hidden={!isActive}
            className={className}
        >
            {children}
        </div>
    );
}

/**
 * A small count or dot beside a tab label.
 */
export function TabBadge({
    children,
    tone = 'muted',
}: {
    children: React.ReactNode;
    tone?: 'muted' | 'attention';
}) {
    return (
        <span
            className={cn(
                'inline-flex min-w-5 items-center justify-center rounded-full px-1.5 text-xs tabular-nums',
                tone === 'attention'
                    ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400'
                    : 'bg-muted-foreground/15 text-muted-foreground',
            )}
        >
            {children}
        </span>
    );
}

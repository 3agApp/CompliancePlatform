import { RefreshCw, TriangleAlert } from 'lucide-react';
import { Component, type ErrorInfo, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';

type Props = { children: ReactNode };
type State = { error: Error | null };

/**
 * Catch render errors instead of letting them blank the page.
 *
 * React unmounts the whole tree when a render throws, which leaves a white
 * screen and no clue what happened. Anything that gets this far is a bug,
 * so the point is to say so plainly and offer a way out.
 */
export default class ErrorBoundary extends Component<Props, State> {
    state: State = { error: null };

    static getDerivedStateFromError(error: Error): State {
        return { error };
    }

    componentDidCatch(error: Error, info: ErrorInfo) {
        // Keep the stack in the console for whoever is looking at it.
        console.error('Unhandled render error', error, info.componentStack);

        /**
         * Caught here, the error never reaches the window, so anything
         * listening for uncaught errors -- the browser tests' "no JavaScript
         * errors" check among them -- would take a broken page for a
         * working one. Report it as uncaught too.
         */
        window.reportError(error);
    }

    render() {
        const { error } = this.state;

        if (!error) {
            return this.props.children;
        }

        return (
            <div
                data-test="error-boundary"
                className="flex min-h-svh flex-col items-center justify-center gap-4 p-6 text-center"
            >
                <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                    <TriangleAlert className="text-muted-foreground size-6" />
                </div>

                <div className="space-y-1">
                    <h1 className="text-lg font-semibold">
                        {t('Something went wrong on this page')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'The page could not finish loading. Reloading usually clears it; if it keeps happening, the details below are worth reporting.',
                        )}
                    </p>
                </div>

                <div className="flex gap-2">
                    <Button onClick={() => window.location.reload()}>
                        <RefreshCw /> {t('Reload the page')}
                    </Button>
                    <Button variant="secondary" asChild>
                        <a href="/">{t('Go back home')}</a>
                    </Button>
                </div>

                <pre
                    data-test="error-boundary-message"
                    className="bg-muted text-muted-foreground max-w-xl overflow-x-auto rounded-md p-3 text-left text-xs"
                >
                    {error.message}
                </pre>
            </div>
        );
    }
}

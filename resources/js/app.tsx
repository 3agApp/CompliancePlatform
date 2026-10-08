import { createInertiaApp } from '@inertiajs/react';
import ErrorBoundary from '@/components/error-boundary';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { installForeignDomTolerance } from '@/lib/foreign-dom-changes';
import { installTranslations } from '@/lib/i18n';
import { installPrefetchInvalidation } from '@/lib/prefetch';
import { installUrlDefaults } from '@/lib/url-defaults';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'CompliancePlatform';

// Before the app renders: a translated first page must not crash it.
installForeignDomTolerance();

// Before the app renders: the first page already needs them.
installUrlDefaults();

// Before the app renders: every string on the first page is looked up.
installTranslations();

// Before the app renders: a write on the first page already has to flush.
installPrefetchInvalidation();

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'error-page':
            /** A page for readers with no account, so no app shell either. */
            case name === 'products/public':
            case name === 'check':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
            case name.startsWith('organizations/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <ErrorBoundary>
                <TooltipProvider delayDuration={0}>
                    {app}
                    <Toaster />
                </TooltipProvider>
            </ErrorBoundary>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

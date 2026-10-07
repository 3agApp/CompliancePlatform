import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    FileQuestion,
    ServerCrash,
    Wrench,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { t, tk } from '@/lib/i18n';
import { home } from '@/routes';

type Status = 403 | 404 | 500 | 503;

const messages: Record<
    Status,
    { icon: typeof Ban; title: string; description: string }
> = {
    403: {
        icon: Ban,
        title: tk('You don’t have access to this page'),
        description: tk(
            'It may belong to an organization you aren’t a member of, or your role doesn’t allow it. Ask an organization owner if you think you should have access.',
        ),
    },
    404: {
        icon: FileQuestion,
        title: tk('We couldn’t find that page'),
        description: tk(
            'The link may be old, or the item was moved or deleted. Check the address, or head back and pick up from there.',
        ),
    },
    500: {
        icon: ServerCrash,
        title: tk('Something went wrong on our side'),
        description: tk(
            'The error has been logged. Try again in a moment; if it keeps happening, let us know what you were doing.',
        ),
    },
    503: {
        icon: Wrench,
        title: tk('We’ll be right back'),
        description: tk(
            'The Compliance Platform is being updated. This usually takes a minute or two, so try again shortly.',
        ),
    },
};

export default function ErrorPage({ status }: { status: Status }) {
    const {
        icon: Icon,
        title,
        description,
    } = messages[status] ?? messages[500];

    return (
        <>
            <Head title={t(title)} />

            <main className="bg-muted/30 flex min-h-svh flex-col items-center justify-center px-5 py-10">
                <div className="workspace-panel w-full max-w-md p-6 text-center sm:p-8">
                    <div className="bg-primary text-primary-foreground mx-auto mb-6 flex size-10 items-center justify-center rounded-xl">
                        <AppLogoIcon className="size-6" />
                    </div>

                    <p className="text-muted-foreground mb-3 inline-flex items-center gap-2 text-xs font-medium tracking-[0.18em] uppercase">
                        <Icon className="size-4" />{' '}
                        {t('Error :status', { status })}
                    </p>

                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t(title)}
                    </h1>
                    <p className="text-muted-foreground mt-3 text-sm leading-relaxed">
                        {t(description)}
                    </p>

                    <div className="mt-8 flex flex-col-reverse justify-center gap-2 sm:flex-row">
                        <Button
                            variant="secondary"
                            onClick={() => window.history.back()}
                        >
                            <ArrowLeft /> {t('Go back')}
                        </Button>
                        <Button asChild>
                            <Link href={home()}>{t('Go to home page')}</Link>
                        </Button>
                    </div>
                </div>
            </main>
        </>
    );
}

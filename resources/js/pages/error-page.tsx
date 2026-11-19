import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    FileQuestion,
    ServerCrash,
    Wrench,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { t, tk } from '@/lib/i18n';
import { home } from '@/routes';

type Status = 403 | 404 | 500 | 503;

const messages: Record<Status, { icon: typeof Ban; title: string }> = {
    403: {
        icon: Ban,
        title: tk('You don’t have access to this page'),
    },
    404: {
        icon: FileQuestion,
        title: tk('We couldn’t find that page'),
    },
    500: {
        icon: ServerCrash,
        title: tk('Something went wrong on our side'),
    },
    503: {
        icon: Wrench,
        title: tk('We’ll be right back'),
    },
};

export default function ErrorPage({ status }: { status: Status }) {
    const { icon: Icon, title } = messages[status] ?? messages[500];

    return (
        <>
            <Head title={t(title)} />

            <main className="bg-muted/30 flex min-h-svh flex-col items-center justify-center px-5 py-10">
                <div className="workspace-panel w-full max-w-md p-6 text-center sm:p-8">
                    <div className="bg-muted mx-auto mb-6 flex size-12 items-center justify-center rounded-full">
                        <Icon className="text-muted-foreground size-6" />
                    </div>

                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t(title)}
                    </h1>

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

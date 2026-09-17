import { Head, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';

export default function Dashboard() {
    const { currentOrganization, name } = usePage().props;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="bg-card flex flex-1 flex-col items-center justify-center rounded-xl border px-6 py-16 text-center shadow-sm">
                    <div className="bg-muted mb-4 flex size-12 items-center justify-center rounded-xl">
                        <Building2 className="size-6" />
                    </div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Welcome to {name}
                    </h1>
                    <p className="text-muted-foreground mt-2 max-w-md text-sm leading-relaxed">
                        {currentOrganization
                            ? `You're working in ${currentOrganization.name}. Compliance tools will show up here as you build them out.`
                            : 'Select or create an organization to get started.'}
                    </p>
                </div>
            </div>
        </>
    );
}

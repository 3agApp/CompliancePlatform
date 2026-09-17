import { Head, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';

export default function Dashboard() {
    const { currentOrganization, name } = usePage().props;

    return (
        <>
            <Head title="Dashboard" />
            <div className="workspace-page">
                <div className="page-heading">
                    <p className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                        Organization overview
                    </p>
                    <h1 className="page-title">Dashboard</h1>
                    <p className="text-muted-foreground text-sm">
                        {currentOrganization
                            ? `You're working in ${currentOrganization.name}.`
                            : 'Select or create an organization to get started.'}
                    </p>
                </div>

                <div className="workspace-panel flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                    <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                        <Building2 className="text-muted-foreground size-6" />
                    </div>
                    <div className="space-y-1">
                        <h2 className="font-medium">Welcome to {name}</h2>
                        <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                            Compliance tools will show up here as you build them
                            out.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    ClipboardCheck,
    ShieldCheck,
    Users,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, onboarding, register } from '@/routes';

const features = [
    {
        icon: Building2,
        title: 'Every organization, one workspace',
        description:
            'Switch between organizations and keep people, roles and settings scoped correctly.',
    },
    {
        icon: ClipboardCheck,
        title: 'Built for compliance work',
        description:
            'A secure foundation for policies, evidence and accountability as your needs grow.',
    },
    {
        icon: ShieldCheck,
        title: 'Access you can trust',
        description:
            'Owners, admins and members with clear permissions for sensitive work.',
    },
    {
        icon: Users,
        title: 'Invite your team',
        description:
            'Send invitations, manage membership and keep the right people in the loop.',
    },
];

const previewMembers = [
    { name: 'Alex Rivera', role: 'Owner', email: 'alex@acme.co' },
    { name: 'Jordan Lee', role: 'Admin', email: 'jordan@acme.co' },
    { name: 'Sam Patel', role: 'Member', email: 'sam@acme.co' },
    { name: 'Casey Nguyen', role: 'Member', email: 'casey@acme.co' },
];

export default function Welcome() {
    const { auth, currentOrganization, name } = usePage().props;
    const dashboardUrl = currentOrganization
        ? dashboard(currentOrganization.slug)
        : onboarding();

    return (
        <>
            <Head title="Compliance management for organizations" />
            <div className="bg-muted/20 text-foreground flex min-h-screen flex-col">
                <header className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-5">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 font-semibold tracking-tight"
                    >
                        <div className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-md">
                            <AppLogoIcon className="size-5" />
                        </div>
                        {name}
                    </Link>

                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboardUrl}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>Get started</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="flex-1">
                    <section className="mx-auto max-w-6xl px-6 pt-12 pb-16 text-center lg:pt-20">
                        <div className="bg-muted/60 text-muted-foreground mb-6 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium">
                            <ShieldCheck className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                            Compliance management for organizations
                        </div>
                        <h1 className="mx-auto max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                            Keep compliance work clear across every
                            organization.
                        </h1>
                        <p className="text-muted-foreground mx-auto mt-6 max-w-2xl text-base text-balance sm:text-lg">
                            {name} gives your team one place to manage
                            organizations, members and the access that keeps
                            compliance work on track.
                        </p>
                        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                            <Button size="lg" asChild>
                                <Link
                                    href={auth.user ? dashboardUrl : register()}
                                >
                                    {auth.user
                                        ? 'Open dashboard'
                                        : 'Start for free'}
                                    <ArrowRight />
                                </Link>
                            </Button>
                            {auth.user ? null : (
                                <Button size="lg" variant="outline" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                            )}
                        </div>
                    </section>

                    <section className="mx-auto max-w-5xl px-6">
                        <DashboardPreview />
                    </section>

                    <section className="mx-auto grid max-w-6xl gap-4 px-6 py-20 sm:grid-cols-2 lg:grid-cols-4">
                        {features.map((feature) => (
                            <div
                                key={feature.title}
                                className="workspace-panel p-6"
                            >
                                <div className="bg-muted mb-4 flex size-10 items-center justify-center rounded-lg">
                                    <feature.icon className="size-5" />
                                </div>
                                <h2 className="font-medium">{feature.title}</h2>
                                <p className="text-muted-foreground mt-2 text-sm">
                                    {feature.description}
                                </p>
                            </div>
                        ))}
                    </section>
                </main>

                <footer className="border-t">
                    <div className="text-muted-foreground mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-6 py-6 text-sm sm:flex-row">
                        <span className="text-foreground flex items-center gap-2 font-medium">
                            <AppLogoIcon className="size-4" />
                            {name}
                        </span>
                        <span>
                            © {new Date().getFullYear()} {name}. All rights
                            reserved.
                        </span>
                    </div>
                </footer>
            </div>
        </>
    );
}

function DashboardPreview() {
    return (
        <div
            aria-hidden="true"
            className="bg-card overflow-hidden rounded-xl border shadow-xl shadow-neutral-900/5 dark:shadow-black/40"
        >
            <div className="bg-muted/40 flex items-center gap-2 border-b px-4 py-3">
                <span className="size-2.5 rounded-full bg-red-400/80" />
                <span className="size-2.5 rounded-full bg-amber-400/80" />
                <span className="size-2.5 rounded-full bg-emerald-400/80" />
                <span className="text-muted-foreground ml-3 text-xs">
                    Acme Corp · Organization
                </span>
            </div>

            <div className="grid gap-3 border-b p-4 sm:grid-cols-3">
                {[
                    { label: 'Members', value: '12', icon: Users },
                    {
                        label: 'Pending invites',
                        value: '3',
                        icon: ClipboardCheck,
                    },
                    {
                        label: 'Organizations',
                        value: '4',
                        icon: Building2,
                    },
                ].map((stat) => (
                    <div
                        key={stat.label}
                        className="rounded-lg border p-3 text-left"
                    >
                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                            {stat.label}
                            <stat.icon className="size-3.5" />
                        </div>
                        <div className="mt-1 text-lg font-semibold tabular-nums">
                            {stat.value}
                        </div>
                    </div>
                ))}
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="text-muted-foreground border-b">
                        <tr>
                            <th className="px-4 py-2.5 font-medium">Member</th>
                            <th className="px-4 py-2.5 font-medium">Role</th>
                            <th className="px-4 py-2.5 font-medium">Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        {previewMembers.map((member) => (
                            <tr
                                key={member.email}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-3 font-medium whitespace-nowrap">
                                    {member.name}
                                </td>
                                <td className="px-4 py-3">
                                    <span className="bg-muted inline-flex rounded-md px-2 py-0.5 text-xs font-medium">
                                        {member.role}
                                    </span>
                                </td>
                                <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                    {member.email}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

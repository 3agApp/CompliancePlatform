import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    children,
}: {
    title?: string;
    children: React.ReactNode;
}) {
    return <AuthLayoutTemplate title={title}>{children}</AuthLayoutTemplate>;
}

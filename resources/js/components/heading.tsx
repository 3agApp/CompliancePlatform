export default function Heading({
    title,
    variant = 'default',
}: {
    title: string;
    variant?: 'default' | 'small';
}) {
    return variant === 'small' ? (
        <h2 className="text-base font-semibold">{title}</h2>
    ) : (
        <h2 className="page-title">{title}</h2>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import { Truck } from 'lucide-react';
import { t } from '@/lib/i18n';
import { index as productsIndex } from '@/routes/products';
import type { DistributorConnection } from '@/types';

type Props = {
    connections: DistributorConnection[];
};

export default function DistributorsIndex({ connections }: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';

    return (
        <>
            <Head title={t('Distributors')} />

            <div className="workspace-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="page-heading">
                        <h1 className="page-title">{t('Distributors')}</h1>
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'The companies :organization supplies products to.',
                                {
                                    organization: currentOrganization?.name,
                                },
                            )}
                        </p>
                    </div>
                </div>

                {connections.length > 0 ? (
                    <div className="workspace-table">
                        <div className="min-w-0 overflow-x-auto">
                            <table className="w-full text-left text-sm md:min-w-xl">
                                <thead>
                                    <tr className="text-muted-foreground">
                                        <th className="px-6 font-medium">
                                            {t('Company')}
                                        </th>
                                        <th className="px-6 font-medium">
                                            {t('Products assigned to you')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {connections.map((connection) => (
                                        <tr
                                            key={connection.id}
                                            data-test="distributor-row"
                                            className="border-t"
                                        >
                                            <td className="px-6 font-medium break-words">
                                                <Link
                                                    href={productsIndex(
                                                        organizationSlug,
                                                        {
                                                            query: {
                                                                connection:
                                                                    connection.id,
                                                            },
                                                        },
                                                    )}
                                                    className="hover:text-primary underline-offset-4 hover:underline"
                                                    data-test="distributor-products-link"
                                                >
                                                    {connection.distributorName}
                                                </Link>
                                            </td>
                                            <td
                                                className="text-muted-foreground px-6"
                                                data-label={t(
                                                    'Products assigned to you',
                                                )}
                                            >
                                                {connection.productsCount}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : (
                    <div className="workspace-panel flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                        <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                            <Truck className="text-muted-foreground size-6" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="font-medium">
                                {t('No distributors yet')}
                            </h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                {t(
                                    'Distributors who connect with you will show up here, along with the products they assign.',
                                )}
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

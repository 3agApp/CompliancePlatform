import CompanyProfileDetails, {
    hasCompanyProfile,
} from '@/components/company-profile-details';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/lib/i18n';
import type { SupplierConnection } from '@/types';

type Props = {
    connection: SupplierConnection | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * The company details a supplier keeps about itself, read by its
 * distributor. They are the supplier's own to write, so there is nothing
 * to edit here.
 */
export default function SupplierDetailsModal({
    connection,
    open,
    onOpenChange,
}: Props) {
    if (!connection) {
        return null;
    }

    const profile = connection.supplierProfile;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent data-test="supplier-details-modal">
                <DialogHeader>
                    <DialogTitle>{connection.companyName}</DialogTitle>
                    <DialogDescription>
                        {t('Company details kept by the supplier.')}
                    </DialogDescription>
                </DialogHeader>

                {profile !== null && hasCompanyProfile(profile) ? (
                    <CompanyProfileDetails profile={profile} />
                ) : (
                    <p
                        className="text-muted-foreground text-sm"
                        data-test="supplier-details-empty"
                    >
                        {connection.isClaimed
                            ? t(
                                  "The supplier hasn't filled in their company details yet.",
                              )
                            : t(
                                  'The supplier fills in their company details once they join.',
                              )}
                    </p>
                )}

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary">{t('Close')}</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

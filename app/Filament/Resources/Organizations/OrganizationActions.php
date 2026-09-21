<?php

namespace App\Filament\Resources\Organizations;

use App\Actions\Organizations\DeleteOrganization;
use App\Models\Organization;
use Filament\Actions\DeleteAction;

/**
 * The actions an admin can take on a organization, shared by its pages.
 */
class OrganizationActions
{
    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription('This cannot be undone. The organization goes, and with it its members, pending invitations, categories, templates, supplier connections, brands, products and every document filed against them.')
            ->using(function (Organization $record): bool {
                app(DeleteOrganization::class)->handle($record);

                return true;
            });
    }
}

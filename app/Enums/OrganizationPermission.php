<?php

namespace App\Enums;

enum OrganizationPermission: string
{
    case UpdateOrganization = 'organization:update';
    case DeleteOrganization = 'organization:delete';
    case ManageAiProvider = 'organization:manage_ai_provider';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    case ViewConnection = 'connection:view';
    case ManageConnection = 'connection:manage';

    case ViewProduct = 'product:view';
    case CreateProduct = 'product:create';
    case UpdateProduct = 'product:update';
    case DeleteProduct = 'product:delete';

    case ViewProductCategory = 'product_category:view';
    case CreateProductCategory = 'product_category:create';
    case UpdateProductCategory = 'product_category:update';
    case DeleteProductCategory = 'product_category:delete';

    case ViewBrand = 'brand:view';
    case CreateBrand = 'brand:create';
    case UpdateBrand = 'brand:update';
    case DeleteBrand = 'brand:delete';
}

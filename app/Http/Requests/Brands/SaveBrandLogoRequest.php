<?php

namespace App\Http\Requests\Brands;

use App\Http\Requests\Organizations\SaveOrganizationLogoRequest;

/**
 * A brand's logo, held to the same rules as an organization's: raster
 * images only, because it is served on a public address to anyone.
 */
class SaveBrandLogoRequest extends SaveOrganizationLogoRequest
{
    //
}

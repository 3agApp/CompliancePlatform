<?php

namespace App\Data;

readonly class BrandPermissions
{
    public function __construct(
        public bool $canCreateBrand,
        public bool $canUpdateBrand,
        public bool $canDeleteBrand,
    ) {
        //
    }
}

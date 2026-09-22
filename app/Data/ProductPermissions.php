<?php

namespace App\Data;

readonly class ProductPermissions
{
    public function __construct(
        public bool $canCreateProduct,
        public bool $canUpdateProduct,
        public bool $canDeleteProduct,
        public bool $canReviewProduct,
        public bool $canOverrideSeal,
        public bool $canDownloadLabel,
    ) {
        //
    }
}

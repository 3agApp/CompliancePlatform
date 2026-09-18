<?php

namespace App\Data;

readonly class ProductCategoryPermissions
{
    public function __construct(
        public bool $canCreateCategory,
        public bool $canUpdateCategory,
        public bool $canDeleteCategory,
    ) {
        //
    }
}

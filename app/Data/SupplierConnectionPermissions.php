<?php

namespace App\Data;

readonly class SupplierConnectionPermissions
{
    public function __construct(
        public bool $canViewConnection,
        public bool $canManageConnection,
    ) {
        //
    }
}

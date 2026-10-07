<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductEvent;
use App\Models\ProductTemplate;
use App\Models\SupplierConnection;
use BackedEnum;

/**
 * What changed about a product, in words somebody can read a year later.
 *
 * A diff of raw columns is no use in a history: half of a product's fields
 * are foreign keys, and "supplier_connection_id: 14 to 27" answers nothing
 * anybody was asking. Each side of every change is resolved to what it was
 * called at the time and kept as text, so the record still reads correctly
 * once the row it pointed at has been renamed or deleted.
 */
class ProductChanges
{
    /**
     * The columns a change is never recorded against.
     *
     * The review columns are left out because the review steps are events in
     * their own right: an approval would otherwise be written twice, once as
     * itself and once as "review status: in review to approved".
     *
     * @var array<int, string>
     */
    protected const array IGNORED = [
        'id',
        'organization_id',
        'review_status',
        'submitted_at',
        'reviewed_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Read what a just-saved product changed.
     *
     * Called after the save: getChanges() is what was actually written, so
     * a field submitted with the value it already held is not a change and
     * is not recorded. The save has already moved the originals on to the
     * new values, so what each field held before is read from
     * getPrevious().
     *
     * @return array<string, array{from: string|null, to: string|null}>
     */
    public static function of(Product $product): array
    {
        $changes = [];

        $previous = $product->getPrevious();

        foreach ($product->getChanges() as $attribute => $value) {
            if (in_array($attribute, self::IGNORED, true)) {
                continue;
            }

            $changes[$attribute] = [
                'from' => self::readable($attribute, $previous[$attribute] ?? null),
                'to' => self::readable($attribute, $value),
            ];
        }

        return $changes;
    }

    /**
     * Turn one value into the text the history shows.
     *
     * A cleared field is kept as null rather than as an empty string, so the
     * timeline can say it was cleared instead of showing a change to
     * nothing.
     */
    protected static function readable(string $attribute, mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        $value = self::named($attribute, $value) ?? $value;

        return mb_substr(trim((string) $value), 0, ProductEvent::VALUE_LENGTH);
    }

    /**
     * Read back what a key column's row is called, for the columns that name
     * one.
     *
     * A supplier is named by the connection rather than by the organization
     * behind it, because a product can be assigned to a supplier who has not
     * claimed their invitation yet and so has no organization to name. The
     * name the distributor typed is the only one there is until they do.
     */
    protected static function named(string $attribute, mixed $value): ?string
    {
        return match ($attribute) {
            'brand_id' => Brand::query()->whereKey($value)->value('name'),
            'product_category_id' => ProductCategory::query()->whereKey($value)->value('name'),
            'product_template_id' => ProductTemplate::query()->whereKey($value)->value('name'),
            'supplier_connection_id' => self::supplierName($value),
            default => null,
        };
    }

    /**
     * Read back what a connection's supplier is called.
     */
    protected static function supplierName(mixed $value): ?string
    {
        $connection = SupplierConnection::query()
            ->with('supplierOrganization')
            ->find($value);

        if (! $connection instanceof SupplierConnection) {
            return null;
        }

        $supplier = $connection->supplierOrganization;

        return $supplier !== null ? $supplier->name : $connection->company_name;
    }
}

<?php

namespace App\Data;

use App\Enums\ProductRequirement;
use App\Models\Product;
use App\Models\ProductDocument;
use BackedEnum;

/**
 * How far along a product is against the template it is held to.
 *
 * The score is weighted, because the items are not worth the same. A test
 * report and a declaration of conformity are what an authority asks for
 * first; a certificate and a safety image are most of the rest; anything a
 * person types into the form is worth one. A manual is worth nothing at all:
 * it is still listed as expected, but a product that is otherwise fully
 * documented should not read as unfinished for want of a leaflet.
 *
 * On a typical toy template -- test report, declaration, certificate, safety
 * image, manual, barcode, origin, age grading, safety notice, warning text --
 * that puts the safety wording at three points of fifteen, or a fifth of the
 * score.
 */
readonly class ProductCompleteness
{
    /**
     * @param  array<array{requirement: string, label: string, group: string, weight: int, satisfied: bool}>  $items
     */
    public function __construct(
        public int $score,
        public array $items,
    ) {
        //
    }

    /**
     * Read the product against its template.
     *
     * Both the template and the documents are expected to be loaded already:
     * this runs once per row of the product list, and a lazy relationship
     * here would be a query per row.
     */
    public static function for(Product $product): self
    {
        $requirements = $product->template->requirements();

        $filedTypes = $product->documents
            ->map(fn (ProductDocument $document) => $document->type->value)
            ->unique()
            ->all();

        $items = $requirements
            ->map(fn (ProductRequirement $requirement) => [
                'requirement' => $requirement->value,
                'label' => $requirement->label(),
                'group' => $requirement->group(),
                'weight' => $requirement->weight(),
                'satisfied' => self::isSatisfied($product, $requirement, $filedTypes),
            ])
            ->values()
            ->all();

        return new self(self::score($items), $items);
    }

    /**
     * Work out whether one requirement has been answered.
     *
     * A document requirement is answered by the first paper of that kind; a
     * second one of the same kind is more evidence, not more progress. A
     * field is answered by anything that is not blank, so a space typed into
     * a warning text does not pass for a warning.
     *
     * @param  array<int, string>  $filedTypes
     */
    protected static function isSatisfied(Product $product, ProductRequirement $requirement, array $filedTypes): bool
    {
        $documentType = $requirement->documentType();

        if ($documentType !== null) {
            return in_array($documentType->value, $filedTypes, true);
        }

        $attribute = $requirement->productAttribute();

        if ($attribute === null) {
            return false;
        }

        $value = $product->getAttribute($attribute);

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return $value !== null && trim((string) $value) !== '';
    }

    /**
     * Turn the answered items into a percentage.
     *
     * A template that asks for nothing -- or for nothing but a manual -- is
     * complete by definition rather than undefined, so the empty case reads
     * as done instead of as a division by zero.
     *
     * @param  array<array{requirement: string, label: string, group: string, weight: int, satisfied: bool}>  $items
     */
    protected static function score(array $items): int
    {
        $total = array_sum(array_column($items, 'weight'));

        if ($total === 0) {
            return 100;
        }

        $satisfied = array_sum(
            array_map(fn (array $item) => $item['satisfied'] ? $item['weight'] : 0, $items)
        );

        return (int) round(100 * $satisfied / $total);
    }
}

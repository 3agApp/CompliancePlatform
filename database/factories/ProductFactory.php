<?php

namespace Database\Factories;

use App\Enums\CountryOfOrigin;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The brand is left unset so a test that asserts on the lists offered
     * by the product pages sees only the rows it set up itself, rather than
     * ones invented for a product it happened to create.
     *
     * The category and the template cannot be: a product must have both.
     * Whatever the organization already keeps is reused before anything is
     * invented, so a test asserting on the families or the sheets a page
     * offers still sees only the rows it set up itself -- a distributor
     * already starts with three families, and a product made in passing
     * should not quietly add a fourth.
     *
     * They resolve in order, so the template always lands under the
     * product's own category: the pairing every product is required to
     * hold to. A caller that means to say which sheet to use passes
     * usingTemplate() and neither is looked up.
     *
     * A template invented here asks for nothing, so a product from here
     * scores a hundred and the score stays out of the way of tests about
     * other things.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'supplier_connection_id' => null,
            'product_category_id' => fn (array $attributes) => ProductCategory::query()
                ->where('organization_id', $attributes['organization_id'])
                ->orderBy('id')
                ->value('id')
                ?? ProductCategory::factory()
                    ->create(['organization_id' => $attributes['organization_id']])
                    ->id,
            'product_template_id' => fn (array $attributes) => ProductTemplate::query()
                ->where('product_category_id', $attributes['product_category_id'])
                ->orderBy('id')
                ->value('id')
                ?? ProductTemplate::factory()
                    ->create(['product_category_id' => $attributes['product_category_id']])
                    ->id,
            'brand_id' => null,
            'name' => fake()->unique()->words(3, true),
            'ean' => (string) fake()->unique()->numerify('#############'),
            'internal_article_number' => fake()->unique()->bothify('ART-#####'),
            'supplier_article_number' => fake()->unique()->bothify('SUP-#####'),
            'order_number' => fake()->bothify('PO-#####'),
            'customs_tariff_number' => (string) fake()->numerify('95030075'),
            'country_of_origin' => fake()->randomElement(CountryOfOrigin::cases()),
        ];
    }

    /**
     * Indicate that the product carries the given brand.
     */
    public function ofBrand(Brand $brand): static
    {
        return $this->state(fn (array $attributes) => [
            'brand_id' => $brand->id,
        ]);
    }

    /**
     * Indicate that the product is filed under the given legal family.
     *
     * The template follows the category, because the two are only ever
     * valid together. A caller that cares which sheet it is held to says so
     * with usingTemplate() instead.
     */
    public function inCategory(ProductCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'product_category_id' => $category->id,
            'product_template_id' => ProductTemplate::query()
                ->where('product_category_id', $category->id)
                ->orderBy('id')
                ->value('id')
                ?? ProductTemplate::factory()
                    ->create(['product_category_id' => $category->id])
                    ->id,
        ]);
    }

    /**
     * Indicate that the product is held to the given template.
     *
     * Sets the category with it, so the pair can never come apart.
     */
    public function usingTemplate(ProductTemplate $template): static
    {
        return $this->state(fn (array $attributes) => [
            'product_category_id' => $template->product_category_id,
            'product_template_id' => $template->id,
        ]);
    }

    /**
     * Indicate that the product has its compliance details filled in.
     *
     * Left out of the default state for the same reason the brand is: a test
     * asserting on what a product page shows should see only what it set up
     * itself.
     */
    public function withComplianceDetails(): static
    {
        return $this->state(fn (array $attributes) => [
            'age_grading' => '3+',
            'safety_notice' => 'Keep the packaging until the product has been checked.',
            'warning_text' => 'Not suitable for children under 3 years. Small parts.',
            'material_information' => 'ABS plastic, neodymium magnets, water based paint.',
            'usage_restrictions' => 'Indoor use only. Not for use in water.',
            'safety_instructions' => 'Inspect for damage before each use and replace broken parts.',
            'additional_notes' => 'Replacement parts are available from the manufacturer.',
        ]);
    }

    /**
     * Indicate that the product carries nothing but its name.
     *
     * Its category and template stay: they are not optional, and a product
     * without them cannot exist to be asserted on.
     */
    public function withoutOptionalDetails(): static
    {
        return $this->state(fn (array $attributes) => [
            'brand_id' => null,
            'ean' => null,
            'internal_article_number' => null,
            'supplier_article_number' => null,
            'order_number' => null,
            'customs_tariff_number' => null,
            'country_of_origin' => null,
            'age_grading' => null,
            'safety_notice' => null,
            'warning_text' => null,
            'material_information' => null,
            'usage_restrictions' => null,
            'safety_instructions' => null,
            'additional_notes' => null,
        ]);
    }
}

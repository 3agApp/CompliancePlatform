<?php

namespace Database\Factories;

use App\Enums\ProductRequirement;
use App\Models\ProductCategory;
use App\Models\ProductTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductTemplate>
 */
class ProductTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Every requirement starts off, so a template asks for nothing until a
     * test says otherwise. A product made with one scores a hundred, which
     * keeps the score out of the way of tests that are not about it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_category_id' => ProductCategory::factory(),
            'name' => fake()->unique()->words(2, true),
            ...array_fill_keys(ProductRequirement::columns(), false),
        ];
    }

    /**
     * Indicate that the template asks for the given requirements.
     */
    public function requiring(ProductRequirement ...$requirements): static
    {
        return $this->state(fn (array $attributes) => array_fill_keys(
            array_map(fn (ProductRequirement $requirement) => $requirement->value, $requirements),
            true,
        ));
    }

    /**
     * Indicate that the template asks for what a toy is normally held to.
     *
     * The worked example the completeness score is calibrated against:
     * fifteen points, of which the safety wording is three.
     */
    public function forToys(): static
    {
        return $this->requiring(
            ProductRequirement::TestReport,
            ProductRequirement::DeclarationOfConformity,
            ProductRequirement::Certificate,
            ProductRequirement::SafetyImage,
            ProductRequirement::ManualOrInstructions,
            ProductRequirement::Ean,
            ProductRequirement::CountryOfOrigin,
            ProductRequirement::AgeGrading,
            ProductRequirement::SafetyNotice,
            ProductRequirement::WarningText,
        );
    }
}

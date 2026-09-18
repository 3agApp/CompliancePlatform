<?php

namespace Database\Factories;

use App\Enums\ProductDocumentType;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductDocument>
 */
class ProductDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The row is made without the file it points at. A test that cares about
     * the bytes uploads one through the controller; one that only cares about
     * the listing does not need a disk at all.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(3).'.pdf';

        return [
            'product_id' => Product::factory(),
            'uploaded_by' => User::factory(),
            'type' => fake()->randomElement(ProductDocumentType::cases()),
            'name' => $name,
            'path' => 'product-documents/'.fake()->numberBetween(1, 1000).'/'.fake()->sha1().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 2_000_000),
        ];
    }

    /**
     * Indicate the kind of document this is.
     */
    public function ofType(ProductDocumentType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}

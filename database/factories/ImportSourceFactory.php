<?php

namespace Database\Factories;

use App\Enums\ImportExistingProductAction;
use App\Enums\ImportMatchKey;
use App\Enums\ImportProvider;
use App\Models\ImportSource;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportSource>
 */
class ImportSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'provider' => ImportProvider::Kontor,
            'api_url' => 'https://kontor.example.test/api/v1/kontor',
            'api_key' => 'kontor-test-key-'.fake()->bothify('????####'),
            'file_url_prefix' => 'https://images.example.test/products',
            'match_key' => ImportMatchKey::Sku,
            'on_existing' => ImportExistingProductAction::Update,
            'default_product_template_id' => null,
            'use_ai_for_templates' => false,
        ];
    }
}

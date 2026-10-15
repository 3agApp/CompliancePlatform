<?php

namespace App\Models;

use App\Enums\ImportExistingProductAction;
use App\Enums\ImportMatchKey;
use App\Enums\ImportProvider;
use Carbon\CarbonImmutable;
use Database\Factories\ImportSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * The source a distributor imports its catalogue from, and how it is imported.
 *
 * The key is encrypted at rest and hidden from serialization, exactly like
 * the AI provider's: the page is only ever shown its last few characters.
 *
 * @property int $id
 * @property int $organization_id
 * @property ImportProvider $provider
 * @property string $api_url
 * @property string $api_key
 * @property string|null $file_url_prefix
 * @property ImportMatchKey $match_key
 * @property ImportExistingProductAction $on_existing
 * @property int|null $default_product_template_id
 * @property bool $use_ai_for_templates
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read ProductTemplate|null $defaultTemplate
 * @property-read Collection<int, ImportManufacturer> $manufacturers
 */
#[Fillable([
    'provider',
    'api_url',
    'api_key',
    'file_url_prefix',
    'match_key',
    'on_existing',
    'default_product_template_id',
    'use_ai_for_templates',
])]
class ImportSource extends Model
{
    /** @use HasFactory<ImportSourceFactory> */
    use HasFactory;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['api_key'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => ImportProvider::class,
            'api_key' => 'encrypted',
            'match_key' => ImportMatchKey::class,
            'on_existing' => ImportExistingProductAction::class,
            'use_ai_for_templates' => 'boolean',
        ];
    }

    /**
     * Get the organization that imports through this source.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the template a product falls back to.
     *
     * @return BelongsTo<ProductTemplate, $this>
     */
    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class, 'default_product_template_id');
    }

    /**
     * Get the manufacturers chosen for import.
     *
     * @return HasMany<ImportManufacturer, $this>
     */
    public function manufacturers(): HasMany
    {
        return $this->hasMany(ImportManufacturer::class)->orderBy('name');
    }

    /**
     * The tail of the key, for the page to show in place of the key.
     */
    public function keyHint(): string
    {
        return Str::substr($this->api_key, -4);
    }

    /**
     * Build the address an image file name is fetched from, or null when no
     * prefix is set and images are not imported.
     */
    public function fileUrl(string $file): ?string
    {
        if (blank($this->file_url_prefix)) {
            return null;
        }

        return rtrim($this->file_url_prefix, '/').'/'.ltrim($file, '/');
    }
}

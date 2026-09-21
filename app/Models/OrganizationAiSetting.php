<?php

namespace App\Models;

use App\Enums\AiProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The AI provider an organization has connected, and the key it pays with.
 *
 * The key is encrypted at rest and hidden from serialization, because the
 * only thing the page is ever shown is the last few characters -- enough to
 * recognise which key is stored, useless to anyone who reads it.
 *
 * @property int $id
 * @property int $organization_id
 * @property AiProvider $provider
 * @property string $model
 * @property string $api_key
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 */
#[Fillable(['provider', 'model', 'api_key'])]
class OrganizationAiSetting extends Model
{
    /**
     * The attributes that should be hidden for serialization.
     *
     * The encrypted cast keeps the key out of the database in plaintext; this
     * keeps the decrypted value out of anything handed to a page. Both are
     * wanted: the cast protects the disk, this protects the wire.
     *
     * @var list<string>
     */
    protected $hidden = ['api_key'];

    /**
     * The organization the setting belongs to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The tail of the key, for the page to show in place of the key.
     */
    public function keyHint(): string
    {
        return Str::substr($this->api_key, -4);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'api_key' => 'encrypted',
        ];
    }
}

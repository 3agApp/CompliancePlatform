<?php

namespace App\Models;

use App\Enums\ProductEventType;
use App\Enums\ProductRequirement;
use Carbon\CarbonImmutable;
use Database\Factories\ProductEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a product's history.
 *
 * Written the moment something happens to a product and never touched
 * again. Two organizations edit the same record here, so this is the only
 * place that can answer who filled in a warning text, who filed the test
 * report, and what the distributor said when they sent it back.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $user_id
 * @property int|null $organization_id
 * @property string|null $actor_name
 * @property string|null $actor_organization_name
 * @property ProductEventType $type
 * @property string|null $note
 * @property array<string, array{from: string|null, to: string|null}>|null $changes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Product $product
 * @property-read User|null $user
 * @property-read Organization|null $organization
 */
#[Fillable([
    'user_id',
    'organization_id',
    'actor_name',
    'actor_organization_name',
    'type',
    'note',
    'changes',
])]
class ProductEvent extends Model
{
    /** @use HasFactory<ProductEventFactory> */
    use HasFactory;

    /**
     * The longest a single before or after value is kept at.
     *
     * A history is read, not replayed: nobody needs the whole of a three
     * paragraph safety instruction twice over to see that it changed. The
     * product itself holds the current text, so what is kept here is enough
     * to recognise the change by.
     */
    public const int VALUE_LENGTH = 255;

    /**
     * Get the product the event happened to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the account that did it, while that account still exists.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the organization it was done from, while it still exists.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the name to show for whoever did it.
     *
     * The snapshot is preferred over the live account on purpose: it is what
     * they were called at the time, which is what a history is for.
     */
    public function actorName(): ?string
    {
        return $this->actor_name ?? $this->user?->name;
    }

    /**
     * Get the name to show for the side it was done from.
     */
    public function actorOrganizationName(): ?string
    {
        return $this->actor_organization_name ?? $this->organization?->name;
    }

    /**
     * Get what changed, named the way the form names it.
     *
     * The keys are stored as columns, because a column is what a change is
     * recorded against and labels move. They are turned into labels here, at
     * the one point the history is read.
     *
     * Read through getAttribute(), because $this->changes is Eloquent's own
     * record of unsaved edits and is always empty on a model just loaded.
     *
     * @return array<array{field: string, label: string, from: string|null, to: string|null}>
     */
    public function changedFields(): array
    {
        return collect($this->getAttribute('changes') ?? [])
            ->map(fn (array $change, string $field) => [
                'field' => $field,
                'label' => self::labelForField($field),
                'from' => $change['from'] ?? null,
                'to' => $change['to'] ?? null,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get the label for one of the product's columns.
     *
     * Most of them are already named by the requirement register, which is
     * the one place a product's fields are listed. The rest are the
     * classification -- which no template asks for, because every product
     * has it -- and the papers, which are named rather than diffed.
     */
    public static function labelForField(string $field): string
    {
        return match ($field) {
            'name' => __('Name'),
            'document' => __('Document'),
            'seal_override' => __('Public seal'),
            'supplier_connection_id' => __('Supplier'),
            'product_category_id' => __('Category'),
            'product_template_id' => __('Template'),
            default => ProductRequirement::labelForAttribute($field) ?? $field,
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductEventType::class,
            'changes' => 'array',
        ];
    }
}

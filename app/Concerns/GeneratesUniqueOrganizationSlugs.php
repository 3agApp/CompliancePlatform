<?php

namespace App\Concerns;

use App\Models\Organization;
use Illuminate\Support\Str;

trait GeneratesUniqueOrganizationSlugs
{
    /**
     * Generate a unique slug for the organization.
     *
     * A deleted organization is gone, so its slug is free again. The suffix
     * still counts from the highest one in use rather than the count, so two
     * companies called the same thing never collide while both exist.
     */
    protected static function generateUniqueOrganizationSlug(string $name, ?int $excludeId = null): string
    {
        $defaultSlug = Str::slug($name);

        $query = static::query()
            ->where(function ($query) use ($defaultSlug) {
                $query->where('slug', $defaultSlug)
                    ->orWhere('slug', 'like', $defaultSlug.'-%');
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existingSlugs = $query->pluck('slug');

        $maxSuffix = $existingSlugs
            ->map(function (string $slug) use ($defaultSlug): ?int {
                if ($slug === $defaultSlug) {
                    return 0;
                } elseif (preg_match('/^'.preg_quote($defaultSlug, '/').'-(\d+)$/', $slug, $matches)) {
                    return (int) $matches[1];
                }

                return null;
            })
            ->filter(fn (?int $suffix) => $suffix !== null)
            ->max() ?? 0;

        return $existingSlugs->isEmpty()
            ? $defaultSlug
            : $defaultSlug.'-'.($maxSuffix + 1);
    }
}

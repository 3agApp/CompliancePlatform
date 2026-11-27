<?php

namespace App\Actions\Products;

use App\Enums\Locale;
use App\Enums\TranslationSource;
use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\ProductTranslation;

/**
 * Keep a product's safety texts in the languages it was not written in.
 *
 * Runs after the product itself is saved, so a translation that changes is
 * stamped with the fingerprint of the original as it now stands. One that
 * comes back unchanged keeps its old fingerprint: an edited original then
 * still shows its translations as out of date until somebody corrects them
 * or confirms they still hold.
 */
class SaveProductTranslations
{
    /**
     * Save the submitted translations and say what changed.
     *
     * A language left out of the submission is left as it is. One submitted
     * with every field empty is removed. The language the product is written
     * in never keeps a translation of itself.
     *
     * @param  array<string, array<string, string|null>>  $submitted
     * @return array<string, array{from: string|null, to: string|null}>
     */
    public function handle(Product $product, array $submitted): array
    {
        $product->load('translations');
        $changes = [];

        foreach ($submitted as $code => $fields) {
            $locale = Locale::tryFrom($code);

            if ($locale === null || $locale === $product->text_locale) {
                continue;
            }

            $texts = $this->texts($fields);
            $existing = $product->translations->first(fn (ProductTranslation $translation): bool => $translation->locale === $locale);
            $before = $existing?->texts() ?? array_fill_keys(Product::TRANSLATABLE_FIELDS, null);

            if ($texts === $before) {
                /**
                 * Unchanged, but read again and found still right: it now
                 * answers to the original as it stands.
                 */
                if ($existing !== null && filter_var($fields['confirmed'] ?? false, FILTER_VALIDATE_BOOL)) {
                    $existing->update(['source_hash' => $product->translationSourceHash()]);
                }

                continue;
            }

            if (array_filter($texts) === []) {
                $existing?->delete();
            } else {
                $product->translations()->updateOrCreate(['locale' => $locale], [
                    ...$texts,
                    'source' => TranslationSource::tryFrom((string) ($fields['source'] ?? '')) ?? TranslationSource::Manual,
                    'source_hash' => $product->translationSourceHash(),
                ]);
            }

            foreach ($texts as $field => $text) {
                if ($text !== $before[$field]) {
                    $changes["{$field}.{$locale->value}"] = [
                        'from' => $this->readable($before[$field]),
                        'to' => $this->readable($text),
                    ];
                }
            }
        }

        /**
         * The product is now written in a language it had a translation
         * into, which the original replaces. Gone from the product, so it
         * goes into the history like any other text that was cleared.
         */
        $replaced = $product->translations->first(fn (ProductTranslation $translation): bool => $translation->locale === $product->text_locale);

        if ($replaced !== null) {
            foreach (array_filter($replaced->texts()) as $field => $text) {
                $changes["{$field}.{$replaced->locale->value}"] = ['from' => $this->readable($text), 'to' => null];
            }

            $replaced->delete();
        }

        $product->unsetRelation('translations');

        return $changes;
    }

    /**
     * Read the translatable fields out of one submitted language, an empty
     * field as null.
     *
     * @param  array<string, string|null>  $fields
     * @return array<string, string|null>
     */
    private function texts(array $fields): array
    {
        return collect(Product::TRANSLATABLE_FIELDS)
            ->mapWithKeys(function (string $field) use ($fields): array {
                $text = trim((string) ($fields[$field] ?? ''));

                return [$field => $text === '' ? null : $text];
            })
            ->all();
    }

    /**
     * Shorten a text to what the history keeps of it.
     */
    private function readable(?string $text): ?string
    {
        return $text === null ? null : mb_substr($text, 0, ProductEvent::VALUE_LENGTH);
    }
}

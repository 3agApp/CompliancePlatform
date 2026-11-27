<?php

namespace App\Actions\Products;

use App\Ai\Agents\ProductTextTranslationAgent;
use App\Ai\OrganizationProvider;
use App\Enums\Locale;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Contracts\Encryption\DecryptException;
use Throwable;

/**
 * Ask an organization's own AI provider to translate a product's safety
 * texts.
 *
 * Returns suggestions and saves nothing: they fill the form, and a person
 * reads them and saves. Nothing here throws, so a provider that is missing
 * or failing only ever costs the button, never the page.
 */
class TranslateProductTexts
{
    /**
     * The provider was never reached because the organization has none.
     */
    public const string NOT_CONFIGURED = 'not_configured';

    /**
     * The provider was asked and did not answer usefully.
     */
    public const string UNAVAILABLE = 'unavailable';

    /**
     * How long to wait on the provider, in seconds.
     *
     * Longer than a document kind guess: five paragraphs into three
     * languages is real writing, and somebody is waiting to read it.
     */
    private const int TIMEOUT = 60;

    public function __construct(private OrganizationProvider $provider)
    {
        //
    }

    /**
     * Translate the texts from one language into the others.
     *
     * @param  array<string, string|null>  $texts  The original texts, keyed by field.
     * @param  list<Locale>  $targets
     * @return array{translations: array<string, array<string, string>>, unavailable: string|null}
     */
    public function handle(Organization $organization, Locale $from, array $texts, array $targets): array
    {
        $texts = array_filter(
            array_intersect_key($texts, array_flip(Product::TRANSLATABLE_FIELDS)),
            fn (?string $text): bool => filled($text),
        );
        $targets = array_values(array_filter($targets, fn (Locale $target): bool => $target !== $from));

        if ($texts === [] || $targets === []) {
            return ['translations' => [], 'unavailable' => null];
        }

        $setting = $organization->aiSetting;

        if ($setting === null) {
            return ['translations' => [], 'unavailable' => self::NOT_CONFIGURED];
        }

        try {
            $response = $this->provider->using(
                $setting,
                fn (string $provider): mixed => (new ProductTextTranslationAgent)->prompt(
                    $this->promptFor($from, $texts, $targets),
                    provider: $provider,
                    model: $setting->model,
                    timeout: self::TIMEOUT,
                ),
            );
        } catch (DecryptException) {
            return ['translations' => [], 'unavailable' => self::NOT_CONFIGURED];
        } catch (Throwable $exception) {
            report($exception);

            return ['translations' => [], 'unavailable' => self::UNAVAILABLE];
        }

        return ['translations' => $this->reconcile($texts, $targets, $response['translations'] ?? []), 'unavailable' => null];
    }

    /**
     * Lay the texts out the way the instructions describe.
     *
     * @param  array<string, string>  $texts
     * @param  list<Locale>  $targets
     */
    private function promptFor(Locale $from, array $texts, array $targets): string
    {
        $languages = implode(', ', array_map(fn (Locale $locale): string => "{$locale->value} ({$locale->label()})", $targets));

        $fields = collect($texts)
            ->map(fn (string $text, string $field): string => "[{$field}]\n{$text}")
            ->implode("\n\n");

        return "Original language: {$from->value} ({$from->label()})\nTranslate into: {$languages}\n\n{$fields}";
    }

    /**
     * Keep only the answers to what was asked.
     *
     * Walks the answer, dropping a language or field nobody asked about and
     * an empty text, so a short or confused answer leaves gaps for a person
     * to fill rather than text where it does not belong.
     *
     * @param  array<string, string>  $texts
     * @param  list<Locale>  $targets
     * @param  array<int, mixed>  $rows
     * @return array<string, array<string, string>>
     */
    private function reconcile(array $texts, array $targets, array $rows): array
    {
        $wanted = array_map(fn (Locale $target): string => $target->value, $targets);
        $translations = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $locale = (string) ($row['locale'] ?? '');
            $field = (string) ($row['field'] ?? '');
            $text = trim((string) ($row['text'] ?? ''));

            if (in_array($locale, $wanted, true) && array_key_exists($field, $texts) && $text !== '') {
                $translations[$locale][$field] = $text;
            }
        }

        return $translations;
    }
}

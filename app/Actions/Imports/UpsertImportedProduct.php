<?php

namespace App\Actions\Imports;

use App\Data\ImportArticle;
use App\Enums\ImportExistingProductAction;
use App\Enums\ImportMatchKey;
use App\Enums\ImportOutcome;
use App\Enums\ProductEventType;
use App\Jobs\DownloadImportedProductImages;
use App\Models\ImportManufacturer;
use App\Models\ImportRun;
use App\Models\ImportSource;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Support\ProductChanges;

/**
 * Bring one imported article into the distributor's catalogue.
 *
 * A new article becomes a product filed against its manufacturer's
 * supplier, under the template the manufacturer, the AI or the source's
 * default says. An article that is already a product only ever has the
 * fields the source owns written over: its supplier, brand, family and
 * template are somebody's decision by then, and an import has no business
 * undoing it.
 */
class UpsertImportedProduct
{
    /**
     * What a valid EAN looks like, the same as the product form accepts.
     */
    private const string EAN_PATTERN = '/^(\d{8}|\d{12,14})$/';

    public function __construct(private ResolveImportSupplier $suppliers)
    {
        //
    }

    /**
     * @throws ImportRowException
     */
    public function handle(
        ImportRun $run,
        ImportSource $source,
        ImportManufacturer $manufacturer,
        ImportArticle $article,
        ?ProductTemplate $suggestedTemplate = null,
    ): ImportOutcome {
        $existing = $this->existingProduct($source, $article);

        if ($existing instanceof Product) {
            return $source->on_existing === ImportExistingProductAction::Skip
                ? ImportOutcome::Skipped
                : $this->update($run, $source, $existing, $article);
        }

        return $this->create($run, $source, $manufacturer, $article, $suggestedTemplate);
    }

    /**
     * Find the product the article already is, by the source's match key.
     *
     * An article with no value for the key cannot be matched to anything, so
     * it is new. Two products with the same value cannot be told apart, so
     * neither is touched.
     *
     * @throws ImportRowException
     */
    public function existingProduct(ImportSource $source, ImportArticle $article): ?Product
    {
        $value = $this->normalisedMatchValue($source, $article);

        if ($value === null) {
            return null;
        }

        $matches = $source->organization->products()
            ->where($source->match_key->column(), $value)
            ->limit(2)
            ->get();

        if ($matches->count() > 1) {
            throw new ImportRowException(__('More than one product has the :key :value, so none was changed.', [
                'key' => $source->match_key->label(),
                'value' => $value,
            ]));
        }

        return $matches->first();
    }

    /**
     * Get the value the article is matched by, as it would be stored.
     */
    private function normalisedMatchValue(ImportSource $source, ImportArticle $article): ?string
    {
        $value = $article->valueFor($source->match_key);

        if ($source->match_key === ImportMatchKey::Ean) {
            return self::ean($value);
        }

        return $value;
    }

    /**
     * File a new product for the article.
     *
     * @throws ImportRowException
     */
    private function create(
        ImportRun $run,
        ImportSource $source,
        ImportManufacturer $manufacturer,
        ImportArticle $article,
        ?ProductTemplate $suggestedTemplate,
    ): ImportOutcome {
        $organization = $source->organization;
        $actor = $run->triggeredBy;

        $template = $manufacturer->template ?? $suggestedTemplate ?? $source->defaultTemplate;

        if (! $template instanceof ProductTemplate) {
            throw new ImportRowException(__('No template could be assigned. Choose one for :manufacturer or a default template.', [
                'manufacturer' => $manufacturer->name,
            ]));
        }

        $byAi = $manufacturer->template === null && $suggestedTemplate !== null;

        $supplier = $this->suppliers->handle($manufacturer, $organization, $actor);

        $brand = $supplier->brands()->firstOrCreate(['name' => $manufacturer->name]);

        $product = $organization->products()->create([
            ...$this->erpFields($article),
            'ean' => self::ean($article->ean),
            'supplier_article_number' => $article->mpn,
            'supplier_connection_id' => $supplier->id,
            'brand_id' => $brand->id,
            'product_category_id' => $template->product_category_id,
            'product_template_id' => $template->id,
        ]);

        $note = $byAi
            ? __('Imported from :source. Template suggested by AI from the imported data -- please check it.', ['source' => $source->provider->label()])
            : __('Imported from :source.', ['source' => $source->provider->label()]);

        $product->recordEvent(ProductEventType::Created, $actor, $organization, $note);

        $this->queueImages($run, $source, $product, $article);

        return $byAi ? ImportOutcome::CreatedByAi : ImportOutcome::Created;
    }

    /**
     * Write the source's fields over an existing product.
     */
    private function update(ImportRun $run, ImportSource $source, Product $product, ImportArticle $article): ImportOutcome
    {
        $attributes = $this->erpFields($article);

        /**
         * An identifier the source does not have, or has in a form the product
         * cannot hold, leaves whatever the product already carries. Only
         * the copy and the sale flag are the source's to clear.
         */
        if (($ean = self::ean($article->ean)) !== null) {
            $attributes['ean'] = $ean;
        }

        if ($article->mpn !== null) {
            $attributes['supplier_article_number'] = $article->mpn;
        }

        $product->fill($attributes)->save();

        $changes = ProductChanges::of($product);

        $this->queueImages($run, $source, $product, $article);

        if ($changes === []) {
            return ImportOutcome::Skipped;
        }

        $product->recordEvent(
            ProductEventType::Updated,
            $run->triggeredBy,
            $source->organization,
            __('Updated from :source.', ['source' => $source->provider->label()]),
            $changes,
        );

        return ImportOutcome::Updated;
    }

    /**
     * The fields the source owns outright.
     *
     * @return array<string, mixed>
     */
    private function erpFields(ImportArticle $article): array
    {
        return [
            'name' => mb_substr($article->name, 0, 255),
            'internal_article_number' => $article->sku,
            'short_description' => $article->shortDescription,
            'long_description' => $article->longDescription,
            'is_active' => $article->isActive,
        ];
    }

    /**
     * Put the article's images on the queue, once the product is saved.
     */
    private function queueImages(ImportRun $run, ImportSource $source, Product $product, ImportArticle $article): void
    {
        $urls = collect($article->imageFiles)
            ->map(fn (string $file): ?string => $source->fileUrl($file))
            ->filter()
            ->unique()
            ->values();

        /**
         * Asked here as well as in the job, so a re-import of a catalogue
         * that already has its images does not queue a job per product
         * just to find there is nothing to do.
         */
        $known = $urls->isEmpty() ? [] : $product->documents()
            ->whereIn('source_url', $urls->all())
            ->pluck('source_url')
            ->all();

        $urls = array_values($urls->diff($known)->all());

        if ($urls === []) {
            return;
        }

        DownloadImportedProductImages::dispatch($run, $product, $urls)->afterCommit();
    }

    /**
     * Read an EAN the product can hold, or null.
     */
    private static function ean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\s+/', '', $value) ?? '';

        return preg_match(self::EAN_PATTERN, $digits) === 1 ? $digits : null;
    }
}

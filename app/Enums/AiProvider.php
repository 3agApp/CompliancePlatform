<?php

namespace App\Enums;

use Laravel\Ai\Enums\Lab;

/**
 * The AI providers an organization may connect.
 *
 * A deliberately short list. The platform ships no key of its own -- every
 * organization brings and pays for its own -- so the only providers offered
 * are the two that are straightforward to sign up for and that the SDK
 * supports for text generation.
 */
enum AiProvider: string
{
    case Gemini = 'gemini';
    case OpenAi = 'openai';

    /**
     * Get the display label for the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::Gemini => 'Google Gemini',
            self::OpenAi => 'OpenAI',
        };
    }

    /**
     * Get the SDK provider this maps onto.
     *
     * The values above are the SDK's own provider names, which is what
     * config('ai.providers') is keyed by, so this is a straight cast. It is
     * spelled out anyway so a rename on either side is caught here rather
     * than at the provider.
     */
    public function lab(): Lab
    {
        return match ($this) {
            self::Gemini => Lab::Gemini,
            self::OpenAi => Lab::OpenAI,
        };
    }

    /**
     * Get the models an organization may choose from.
     *
     * Taken from the SDK's own cheapest, default and smartest text models
     * for each provider. Guessing a kind from a filename is a small job, so
     * the cheapest model is genuinely enough and the list leads with it.
     *
     * @return array<array{value: string, label: string}>
     */
    public function models(): array
    {
        return match ($this) {
            self::Gemini => [
                ['value' => 'gemini-3.1-flash-lite', 'label' => 'Gemini 3.1 Flash Lite — fast and cheap'],
                ['value' => 'gemini-3.6-flash', 'label' => 'Gemini 3.6 Flash — recommended'],
            ],
            self::OpenAi => [
                ['value' => 'gpt-5.6-luna', 'label' => 'GPT-5.6 Luna — fast and cheap'],
                ['value' => 'gpt-5.6-terra', 'label' => 'GPT-5.6 Terra — recommended'],
                ['value' => 'gpt-5.6-sol', 'label' => 'GPT-5.6 Sol — most capable'],
            ],
        };
    }

    /**
     * Get the model chosen for the provider when nobody has chosen one.
     */
    public function defaultModel(): string
    {
        return match ($this) {
            self::Gemini => 'gemini-3.1-flash-lite',
            self::OpenAi => 'gpt-5.6-luna',
        };
    }

    /**
     * Get the display label for one of the provider's models.
     *
     * A model that is no longer offered still has to read as something, so a
     * value that has fallen off the list is shown as it is stored.
     */
    public function modelLabel(string $model): string
    {
        foreach ($this->models() as $option) {
            if ($option['value'] === $model) {
                return $option['label'];
            }
        }

        return $model;
    }

    /**
     * Determine whether the given model is one this provider offers.
     */
    public function offersModel(string $model): bool
    {
        return in_array($model, array_column($this->models(), 'value'), true);
    }

    /**
     * Get every model value across every provider.
     *
     * @return array<int, string>
     */
    public static function allModels(): array
    {
        return collect(self::cases())
            ->flatMap(fn (self $provider) => array_column($provider->models(), 'value'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get the providers as options for the settings page.
     *
     * Each option carries its own models so the page can swap the model list
     * when the provider changes without a round trip.
     *
     * @return array<array{value: string, label: string, models: array<array{value: string, label: string}>, default_model: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $provider) => [
                'value' => $provider->value,
                'label' => $provider->label(),
                'models' => $provider->models(),
                'default_model' => $provider->defaultModel(),
            ])
            ->values()
            ->toArray();
    }
}

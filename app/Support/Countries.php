<?php

namespace App\Support;

/**
 * Every country, by its ISO 3166-1 alpha-2 code.
 *
 * Where an organization is based, where a brand makes its products and a
 * product's country of origin are all read from this one list. Only the
 * codes live here: the names come from ICU through PHP's intl extension, or
 * from the browser's Intl.DisplayNames, in whatever language the reader
 * uses, so the list never has to be translated.
 */
class Countries
{
    /**
     * The codes, in alphabetical order.
     *
     * @var list<string>
     */
    private const array CODES = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ',
        'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS',
        'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN',
        'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE',
        'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF',
        'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM',
        'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM',
        'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC',
        'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK',
        'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA',
        'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG',
        'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW',
        'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS',
        'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO',
        'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI',
        'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
    ];

    /**
     * Get every country code.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return self::CODES;
    }

    /**
     * Get a country's name in the given language, or the current one.
     *
     * Without the intl extension there is nothing to read names from, so
     * the code itself is the name rather than an error.
     */
    public static function name(?string $code, ?string $locale = null): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        if (! class_exists(\Locale::class)) {
            return $code;
        }

        $name = \Locale::getDisplayRegion('-'.$code, $locale ?? app()->getLocale());

        return $name === false || $name === '' ? $code : $name;
    }

    /**
     * Get the countries as select options, sorted by name in the reader's
     * language.
     *
     * @param  list<string>|null  $codes  Only these countries; all of them when null.
     * @return list<array{value: string, label: string}>
     */
    public static function options(?array $codes = null): array
    {
        $options = array_map(
            fn (string $code): array => ['value' => $code, 'label' => self::name($code) ?? $code],
            $codes ?? self::CODES,
        );

        usort($options, fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return $options;
    }
}

<?php

namespace App\Enums;

use Collator;
use Locale;

/**
 * Every country in ISO 3166-1, keyed by its alpha-2 code.
 */
enum Country: string
{
    case Afghanistan = 'AF';
    case AlandIslands = 'AX';
    case Albania = 'AL';
    case Algeria = 'DZ';
    case AmericanSamoa = 'AS';
    case Andorra = 'AD';
    case Angola = 'AO';
    case Anguilla = 'AI';
    case Antarctica = 'AQ';
    case AntiguaAndBarbuda = 'AG';
    case Argentina = 'AR';
    case Armenia = 'AM';
    case Aruba = 'AW';
    case Australia = 'AU';
    case Austria = 'AT';
    case Azerbaijan = 'AZ';
    case Bahamas = 'BS';
    case Bahrain = 'BH';
    case Bangladesh = 'BD';
    case Barbados = 'BB';
    case Belarus = 'BY';
    case Belgium = 'BE';
    case Belize = 'BZ';
    case Benin = 'BJ';
    case Bermuda = 'BM';
    case Bhutan = 'BT';
    case Bolivia = 'BO';
    case BosniaAndHerzegovina = 'BA';
    case Botswana = 'BW';
    case BouvetIsland = 'BV';
    case Brazil = 'BR';
    case BritishIndianOceanTerritory = 'IO';
    case BritishVirginIslands = 'VG';
    case Brunei = 'BN';
    case Bulgaria = 'BG';
    case BurkinaFaso = 'BF';
    case Burundi = 'BI';
    case Cambodia = 'KH';
    case Cameroon = 'CM';
    case Canada = 'CA';
    case CapeVerde = 'CV';
    case CaribbeanNetherlands = 'BQ';
    case CaymanIslands = 'KY';
    case CentralAfricanRepublic = 'CF';
    case Chad = 'TD';
    case Chile = 'CL';
    case China = 'CN';
    case ChristmasIsland = 'CX';
    case CocosKeelingIslands = 'CC';
    case Colombia = 'CO';
    case Comoros = 'KM';
    case CongoBrazzaville = 'CG';
    case CongoKinshasa = 'CD';
    case CookIslands = 'CK';
    case CostaRica = 'CR';
    case CoteDIvoire = 'CI';
    case Croatia = 'HR';
    case Cuba = 'CU';
    case Curacao = 'CW';
    case Cyprus = 'CY';
    case Czechia = 'CZ';
    case Denmark = 'DK';
    case Djibouti = 'DJ';
    case Dominica = 'DM';
    case DominicanRepublic = 'DO';
    case Ecuador = 'EC';
    case Egypt = 'EG';
    case ElSalvador = 'SV';
    case EquatorialGuinea = 'GQ';
    case Eritrea = 'ER';
    case Estonia = 'EE';
    case Eswatini = 'SZ';
    case Ethiopia = 'ET';
    case FalklandIslands = 'FK';
    case FaroeIslands = 'FO';
    case Fiji = 'FJ';
    case Finland = 'FI';
    case France = 'FR';
    case FrenchGuiana = 'GF';
    case FrenchPolynesia = 'PF';
    case FrenchSouthernTerritories = 'TF';
    case Gabon = 'GA';
    case Gambia = 'GM';
    case Georgia = 'GE';
    case Germany = 'DE';
    case Ghana = 'GH';
    case Gibraltar = 'GI';
    case Greece = 'GR';
    case Greenland = 'GL';
    case Grenada = 'GD';
    case Guadeloupe = 'GP';
    case Guam = 'GU';
    case Guatemala = 'GT';
    case Guernsey = 'GG';
    case Guinea = 'GN';
    case GuineaBissau = 'GW';
    case Guyana = 'GY';
    case Haiti = 'HT';
    case HeardAndMcDonaldIslands = 'HM';
    case Honduras = 'HN';
    case HongKongSARChina = 'HK';
    case Hungary = 'HU';
    case Iceland = 'IS';
    case India = 'IN';
    case Indonesia = 'ID';
    case Iran = 'IR';
    case Iraq = 'IQ';
    case Ireland = 'IE';
    case IsleOfMan = 'IM';
    case Israel = 'IL';
    case Italy = 'IT';
    case Jamaica = 'JM';
    case Japan = 'JP';
    case Jersey = 'JE';
    case Jordan = 'JO';
    case Kazakhstan = 'KZ';
    case Kenya = 'KE';
    case Kiribati = 'KI';
    case Kuwait = 'KW';
    case Kyrgyzstan = 'KG';
    case Laos = 'LA';
    case Latvia = 'LV';
    case Lebanon = 'LB';
    case Lesotho = 'LS';
    case Liberia = 'LR';
    case Libya = 'LY';
    case Liechtenstein = 'LI';
    case Lithuania = 'LT';
    case Luxembourg = 'LU';
    case MacaoSARChina = 'MO';
    case Madagascar = 'MG';
    case Malawi = 'MW';
    case Malaysia = 'MY';
    case Maldives = 'MV';
    case Mali = 'ML';
    case Malta = 'MT';
    case MarshallIslands = 'MH';
    case Martinique = 'MQ';
    case Mauritania = 'MR';
    case Mauritius = 'MU';
    case Mayotte = 'YT';
    case Mexico = 'MX';
    case Micronesia = 'FM';
    case Moldova = 'MD';
    case Monaco = 'MC';
    case Mongolia = 'MN';
    case Montenegro = 'ME';
    case Montserrat = 'MS';
    case Morocco = 'MA';
    case Mozambique = 'MZ';
    case MyanmarBurma = 'MM';
    case Namibia = 'NA';
    case Nauru = 'NR';
    case Nepal = 'NP';
    case Netherlands = 'NL';
    case NewCaledonia = 'NC';
    case NewZealand = 'NZ';
    case Nicaragua = 'NI';
    case Niger = 'NE';
    case Nigeria = 'NG';
    case Niue = 'NU';
    case NorfolkIsland = 'NF';
    case NorthKorea = 'KP';
    case NorthMacedonia = 'MK';
    case NorthernMarianaIslands = 'MP';
    case Norway = 'NO';
    case Oman = 'OM';
    case Pakistan = 'PK';
    case Palau = 'PW';
    case PalestinianTerritories = 'PS';
    case Panama = 'PA';
    case PapuaNewGuinea = 'PG';
    case Paraguay = 'PY';
    case Peru = 'PE';
    case Philippines = 'PH';
    case PitcairnIslands = 'PN';
    case Poland = 'PL';
    case Portugal = 'PT';
    case PuertoRico = 'PR';
    case Qatar = 'QA';
    case Reunion = 'RE';
    case Romania = 'RO';
    case Russia = 'RU';
    case Rwanda = 'RW';
    case SaintBarthelemy = 'BL';
    case SaintHelena = 'SH';
    case SaintKittsAndNevis = 'KN';
    case SaintLucia = 'LC';
    case SaintMartin = 'MF';
    case SaintPierreAndMiquelon = 'PM';
    case SaintVincentAndGrenadines = 'VC';
    case Samoa = 'WS';
    case SanMarino = 'SM';
    case SaoTomeAndPrincipe = 'ST';
    case SaudiArabia = 'SA';
    case Senegal = 'SN';
    case Serbia = 'RS';
    case Seychelles = 'SC';
    case SierraLeone = 'SL';
    case Singapore = 'SG';
    case SintMaarten = 'SX';
    case Slovakia = 'SK';
    case Slovenia = 'SI';
    case SolomonIslands = 'SB';
    case Somalia = 'SO';
    case SouthAfrica = 'ZA';
    case SouthGeorgiaAndSouthSandwichIslands = 'GS';
    case SouthKorea = 'KR';
    case SouthSudan = 'SS';
    case Spain = 'ES';
    case SriLanka = 'LK';
    case Sudan = 'SD';
    case Suriname = 'SR';
    case SvalbardAndJanMayen = 'SJ';
    case Sweden = 'SE';
    case Switzerland = 'CH';
    case Syria = 'SY';
    case Taiwan = 'TW';
    case Tajikistan = 'TJ';
    case Tanzania = 'TZ';
    case Thailand = 'TH';
    case TimorLeste = 'TL';
    case Togo = 'TG';
    case Tokelau = 'TK';
    case Tonga = 'TO';
    case TrinidadAndTobago = 'TT';
    case Tunisia = 'TN';
    case Turkiye = 'TR';
    case Turkmenistan = 'TM';
    case TurksAndCaicosIslands = 'TC';
    case Tuvalu = 'TV';
    case USOutlyingIslands = 'UM';
    case USVirginIslands = 'VI';
    case Uganda = 'UG';
    case Ukraine = 'UA';
    case UnitedArabEmirates = 'AE';
    case UnitedKingdom = 'GB';
    case UnitedStates = 'US';
    case Uruguay = 'UY';
    case Uzbekistan = 'UZ';
    case Vanuatu = 'VU';
    case VaticanCity = 'VA';
    case Venezuela = 'VE';
    case Vietnam = 'VN';
    case WallisAndFutuna = 'WF';
    case WesternSahara = 'EH';
    case Yemen = 'YE';
    case Zambia = 'ZM';
    case Zimbabwe = 'ZW';

    /**
     * Get the country's name in the current locale.
     */
    public function label(): string
    {
        $label = Locale::getDisplayRegion('-'.$this->value, app()->getLocale());

        return is_string($label) && $label !== '' && $label !== $this->value ? $label : $this->name;
    }

    /**
     * Get the given countries, or all of them, as options sorted by name.
     *
     * @param  iterable<self>|null  $countries
     * @return list<array{value: string, label: string}>
     */
    public static function options(?iterable $countries = null): array
    {
        $collator = new Collator(app()->getLocale());

        $options = collect($countries ?? self::cases())
            ->map(fn (self $country): array => ['value' => $country->value, 'label' => $country->label()])
            ->sort(fn (array $a, array $b): int => (int) $collator->compare($a['label'], $b['label']))
            ->all();

        return array_values($options);
    }
}

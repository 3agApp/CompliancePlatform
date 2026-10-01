import { useCallback, useState } from 'react';

/**
 * The languages the public pages speak. The server reads the same cookie,
 * so the messages it sends back match the page around them.
 */
export type PublicLocale = 'en' | 'de';

const COOKIE = 'public_lang';

const en = {
    productCheck: 'Product check',
    switchTo: 'DE',
    switchLabel: 'Auf Deutsch anzeigen',

    sealPill_verified: 'Verified',
    sealPill_in_progress: 'In review',
    sealPill_not_verified: 'Not verified',
    sealTitle_verified: 'Verified',
    sealTitle_in_progress: 'In progress',
    sealTitle_not_verified: 'Not verified',
    sealDesc_verified: 'This product meets Swiss compliance requirements.',
    sealDesc_in_progress: 'The compliance check has not finished yet.',
    sealDesc_not_verified: 'No completed compliance check yet.',
    approved: 'Approved',
    dataCollected: 'Compliance data collected',
    overridden: 'Set by the distributor rather than by a completed check.',

    noPicture: 'No picture of this article yet',
    showPicture: 'Show picture',
    previousPicture: 'Previous picture',
    nextPicture: 'Next picture',
    ean: 'EAN',
    articleNumber: 'Article no.',

    checkCardTitle: 'Is this product genuine?',
    checkCardIntro:
        'Every box carries its own serial. Check it to see whether it is genuine and whether anyone has checked it before you.',
    serial: 'Serial',
    serialHint: 'Printed beside the QR code on the box',
    captchaLabel: 'Type the code shown',
    captchaAlt: 'Security code',
    captchaRefresh: 'Show a different code',
    captchaPlaceholder: 'Code',
    checkSubmit: 'Check',
    checkNote:
        'Every check is recorded, so whoever checks this code after you can see when it was checked.',

    resultTitle_first_check: 'Genuine product',
    resultTitle_checked_before: 'Genuine product',
    resultTitle_checked_elsewhere: 'Checked before',
    resultTitle_unknown: 'Code not recognised',
    resultTitle_revoked: 'Code withdrawn',
    resultText_first_check:
        'This code was issued for this product, and this is the first time anyone has checked it.',
    resultText_checked_before:
        'This code was issued for this product. It has only been checked from this device before.',
    resultText_checked_elsewhere:
        'This code was issued for this product, but it has already been checked from another device. If that was not you, this may be a used product or a copied label. Be careful.',
    resultText_unknown:
        'This code is not registered for this product. The product may not be genuine. Check the serial was typed correctly.',
    resultText_revoked:
        'The company that places this product on the market has withdrawn this code. Do not rely on this label.',
    historyTitle: 'Earlier checks',
    historyCount: '{count} earlier checks',
    historyCountOne: '1 earlier check',
    historyMore: 'and {count} more',
    thisDevice: 'This device',
    otherDevice: 'Another device',

    safetyTitle: 'Safety information',
    age_grading: 'Age recommendation',
    warning_text: 'Warning',
    safety_notice: 'Safety notice',
    safety_instructions: 'Safety instructions',
    material_information: 'Material information',
    usage_restrictions: 'Usage restrictions',

    documentsTitle: 'Documents',
    documentsIntro:
        'Released for public access by the company that places this product on the market.',
    download: 'Download',
    docType_test_report: 'Test report',
    docType_declaration_of_conformity: 'Declaration of conformity',
    docType_manual_or_instructions: 'Manual or instructions',
    docType_certificate: 'Certificate',
    docType_product_image: 'Product image',
    docType_safety_image: 'Safety image',
    docType_regulatory_document: 'Regulatory document',
    docType_other: 'Document',

    importedBy: 'Placed on the market by',
    footer: 'Compliance information published by the company that places this product on the market.',

    checkTitle: 'Check a product',
    checkIntro:
        "Type the serial printed beside the QR code on the box. It takes you to the product's page, where you can check it.",
    checkButton: 'Check',
};

type Dictionary = Record<keyof typeof en, string>;

const de: Dictionary = {
    productCheck: 'Produktprüfung',
    switchTo: 'EN',
    switchLabel: 'Show in English',

    sealPill_verified: 'Verifiziert',
    sealPill_in_progress: 'In Prüfung',
    sealPill_not_verified: 'Nicht verifiziert',
    sealTitle_verified: 'Verifiziert',
    sealTitle_in_progress: 'In Prüfung',
    sealTitle_not_verified: 'Nicht verifiziert',
    sealDesc_verified:
        'Dieses Produkt erfüllt die Schweizer Compliance-Anforderungen.',
    sealDesc_in_progress:
        'Die Compliance-Prüfung ist noch nicht abgeschlossen.',
    sealDesc_not_verified: 'Noch keine abgeschlossene Compliance-Prüfung.',
    approved: 'Genehmigt',
    dataCollected: 'Erfasste Compliance-Daten',
    overridden:
        'Vom Inverkehrbringer gesetzt, nicht durch eine abgeschlossene Prüfung.',

    noPicture: 'Noch kein Bild dieses Artikels',
    showPicture: 'Bild anzeigen',
    previousPicture: 'Vorheriges Bild',
    nextPicture: 'Nächstes Bild',
    ean: 'EAN',
    articleNumber: 'Artikelnr.',

    checkCardTitle: 'Ist dieses Produkt echt?',
    checkCardIntro:
        'Jede Verpackung trägt eine eigene Seriennummer. Prüfen Sie sie, um zu sehen, ob sie echt ist und ob sie schon vor Ihnen geprüft wurde.',
    serial: 'Seriennummer',
    serialHint: 'Neben dem QR-Code auf der Verpackung',
    captchaLabel: 'Angezeigten Code eingeben',
    captchaAlt: 'Sicherheitscode',
    captchaRefresh: 'Anderen Code anzeigen',
    captchaPlaceholder: 'Code',
    checkSubmit: 'Prüfen',
    checkNote:
        'Jede Prüfung wird gespeichert, damit alle, die diesen Code nach Ihnen prüfen, sehen, wann er geprüft wurde.',

    resultTitle_first_check: 'Echtes Produkt',
    resultTitle_checked_before: 'Echtes Produkt',
    resultTitle_checked_elsewhere: 'Bereits geprüft',
    resultTitle_unknown: 'Code nicht erkannt',
    resultTitle_revoked: 'Code zurückgezogen',
    resultText_first_check:
        'Dieser Code wurde für dieses Produkt ausgegeben und wird hiermit zum ersten Mal geprüft.',
    resultText_checked_before:
        'Dieser Code wurde für dieses Produkt ausgegeben. Er wurde bisher nur von diesem Gerät aus geprüft.',
    resultText_checked_elsewhere:
        'Dieser Code wurde für dieses Produkt ausgegeben, aber bereits von einem anderen Gerät aus geprüft. Falls das nicht Sie waren, handelt es sich möglicherweise um ein gebrauchtes Produkt oder ein kopiertes Etikett. Seien Sie vorsichtig.',
    resultText_unknown:
        'Dieser Code ist für dieses Produkt nicht registriert. Das Produkt ist möglicherweise nicht echt. Bitte prüfen Sie die Eingabe.',
    resultText_revoked:
        'Das Unternehmen, das dieses Produkt in Verkehr bringt, hat diesen Code zurückgezogen. Verlassen Sie sich nicht auf dieses Etikett.',
    historyTitle: 'Frühere Prüfungen',
    historyCount: '{count} frühere Prüfungen',
    historyCountOne: '1 frühere Prüfung',
    historyMore: 'und {count} weitere',
    thisDevice: 'Dieses Gerät',
    otherDevice: 'Anderes Gerät',

    safetyTitle: 'Sicherheitsinformationen',
    age_grading: 'Altersempfehlung',
    warning_text: 'Warnhinweis',
    safety_notice: 'Sicherheitshinweis',
    safety_instructions: 'Sicherheitsanweisungen',
    material_information: 'Materialinformation',
    usage_restrictions: 'Verwendungseinschränkungen',

    documentsTitle: 'Dokumente',
    documentsIntro:
        'Vom Inverkehrbringer zur öffentlichen Einsicht freigegeben.',
    download: 'Herunterladen',
    docType_test_report: 'Prüfbericht',
    docType_declaration_of_conformity: 'Konformitätserklärung',
    docType_manual_or_instructions: 'Bedienungsanleitung',
    docType_certificate: 'Zertifikat',
    docType_product_image: 'Produktbild',
    docType_safety_image: 'Sicherheitsbild',
    docType_regulatory_document: 'Regulatorisches Dokument',
    docType_other: 'Dokument',

    importedBy: 'In Verkehr gebracht von',
    footer: 'Compliance-Informationen, veröffentlicht vom Unternehmen, das dieses Produkt in Verkehr bringt.',

    checkTitle: 'Produkt prüfen',
    checkIntro:
        'Geben Sie die Seriennummer neben dem QR-Code auf der Verpackung ein. Sie gelangen zur Produktseite, wo Sie sie prüfen können.',
    checkButton: 'Prüfen',
};

const DICTIONARIES: Record<PublicLocale, Dictionary> = { en, de };

export type PublicTranslate = (
    key: keyof typeof en,
    replace?: Record<string, string>,
) => string;

/**
 * The language a public page is in, and a way to switch it.
 *
 * Starts from what the server read off the cookie, so the first paint is
 * already in the right language, and writes the cookie back on a switch so
 * the next message the server sends is too.
 */
export function usePublicLocale(initial: string | undefined) {
    const [locale, setLocaleState] = useState<PublicLocale>(
        initial === 'de' ? 'de' : 'en',
    );

    const setLocale = useCallback((next: PublicLocale) => {
        setLocaleState(next);
        document.cookie = `${COOKIE}=${next};path=/;max-age=${60 * 60 * 24 * 365};samesite=lax`;
        document.documentElement.lang = next;
    }, []);

    const t: PublicTranslate = useCallback(
        (key, replace = {}) =>
            Object.entries(replace).reduce(
                (text, [name, value]) => text.replace(`{${name}}`, value),
                DICTIONARIES[locale][key],
            ),
        [locale],
    );

    return { locale, setLocale, t };
}

/**
 * A date the way a reader in that language writes it.
 */
export function formatPublicDate(
    timestamp: string,
    locale: PublicLocale,
    withTime = false,
): string {
    return new Date(timestamp).toLocaleString(
        locale === 'de' ? 'de-CH' : 'en-GB',
        {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
        },
    );
}

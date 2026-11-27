import { useCallback, useState } from 'react';

/**
 * The languages the public pages speak. The server reads the same cookie,
 * so the messages it sends back match the page around them.
 */
export type PublicLocale = 'en' | 'de' | 'fr' | 'it';

/**
 * Every language, each under its own name, so a reader who cannot read the
 * current one still finds theirs.
 */
export const PUBLIC_LOCALES: { value: PublicLocale; label: string }[] = [
    { value: 'en', label: 'English' },
    { value: 'de', label: 'Deutsch' },
    { value: 'fr', label: 'Français' },
    { value: 'it', label: 'Italiano' },
];

const COOKIE = 'public_lang';

const en = {
    productCheck: 'Product check',
    language: 'Language',

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
    turnstileUnavailable:
        'The security check could not load. Reload the page to try again.',
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
    preview: 'Preview',
    previewOf: 'Preview of {name}',
    enlargePicture: 'Enlarge picture',
    openInNewTab: 'Open in a new tab',
    pdfNotShown:
        'This browser cannot show PDFs here. Open it in a new tab instead.',
    docType_test_report: 'Test report',
    docType_declaration_of_conformity: 'Declaration of conformity',
    docType_manual_or_instructions: 'Manual or instructions',
    docType_certificate: 'Certificate',
    docType_product_image: 'Product image',
    docType_safety_image: 'Warning image',
    docType_packaging_closeup: 'Packaging close-up',
    docType_regulatory_document: 'Regulatory document',
    docType_other: 'Document',

    importedBy: 'Placed on the market by',
    companyDetails: 'Company details',
    companyDetailsFor: 'Company details for {name}',
    companyAddress: 'Address',
    companyPhone: 'Phone',
    companyEmail: 'Email',
    companyWebsite: 'Website',
    companyRepresentedBy: 'Represented by',
    companyVatNumber: 'VAT / UID number',
    openInGoogleMaps: 'Open in Google Maps',
    close: 'Close',
    footer: 'Compliance information published by the company that places this product on the market.',

    checkTitle: 'Check a product',
    checkIntro:
        "Type the serial printed beside the QR code on the box. It takes you to the product's page, where you can check it.",
    checkButton: 'Check',
};

type Dictionary = Record<keyof typeof en, string>;

const de: Dictionary = {
    productCheck: 'Produktprüfung',
    language: 'Sprache',

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
    turnstileUnavailable:
        'Die Sicherheitsprüfung konnte nicht geladen werden. Laden Sie die Seite neu, um es erneut zu versuchen.',
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
    preview: 'Ansehen',
    previewOf: 'Vorschau von {name}',
    enlargePicture: 'Bild vergrössern',
    openInNewTab: 'In neuem Tab öffnen',
    pdfNotShown:
        'Dieser Browser kann PDFs hier nicht anzeigen. Öffnen Sie es stattdessen in einem neuen Tab.',
    docType_test_report: 'Prüfbericht',
    docType_declaration_of_conformity: 'Konformitätserklärung',
    docType_manual_or_instructions: 'Bedienungsanleitung',
    docType_certificate: 'Zertifikat',
    docType_product_image: 'Produktbild',
    docType_safety_image: 'Warnbild',
    docType_packaging_closeup: 'Verpackungsdetail',
    docType_regulatory_document: 'Regulatorisches Dokument',
    docType_other: 'Dokument',

    importedBy: 'In Verkehr gebracht von',
    companyDetails: 'Firmenangaben',
    companyDetailsFor: 'Firmenangaben zu {name}',
    companyAddress: 'Adresse',
    companyPhone: 'Telefon',
    companyEmail: 'E-Mail',
    companyWebsite: 'Website',
    companyRepresentedBy: 'Vertreten durch',
    companyVatNumber: 'MWST- / UID-Nummer',
    openInGoogleMaps: 'In Google Maps öffnen',
    close: 'Schliessen',
    footer: 'Compliance-Informationen, veröffentlicht vom Unternehmen, das dieses Produkt in Verkehr bringt.',

    checkTitle: 'Produkt prüfen',
    checkIntro:
        'Geben Sie die Seriennummer neben dem QR-Code auf der Verpackung ein. Sie gelangen zur Produktseite, wo Sie sie prüfen können.',
    checkButton: 'Prüfen',
};

const fr: Dictionary = {
    productCheck: 'Contrôle du produit',
    language: 'Langue',

    sealPill_verified: 'Vérifié',
    sealPill_in_progress: 'En contrôle',
    sealPill_not_verified: 'Non vérifié',
    sealTitle_verified: 'Vérifié',
    sealTitle_in_progress: 'En cours',
    sealTitle_not_verified: 'Non vérifié',
    sealDesc_verified:
        'Ce produit satisfait aux exigences suisses de conformité.',
    sealDesc_in_progress: 'Le contrôle de conformité n’est pas encore terminé.',
    sealDesc_not_verified:
        'Aucun contrôle de conformité terminé pour l’instant.',
    approved: 'Approuvé',
    dataCollected: 'Données de conformité saisies',
    overridden:
        'Défini par la personne qui met le produit sur le marché, et non par un contrôle terminé.',

    noPicture: 'Pas encore d’image de cet article',
    showPicture: 'Afficher l’image',
    previousPicture: 'Image précédente',
    nextPicture: 'Image suivante',
    ean: 'EAN',
    articleNumber: 'N° d’article',

    checkCardTitle: 'Ce produit est-il authentique ?',
    checkCardIntro:
        'Chaque emballage porte son propre numéro de série. Vérifiez-le pour savoir s’il est authentique et si quelqu’un l’a déjà vérifié avant vous.',
    serial: 'Numéro de série',
    serialHint: 'Imprimé à côté du code QR sur l’emballage',
    turnstileUnavailable:
        'Le contrôle de sécurité n’a pas pu se charger. Rechargez la page pour réessayer.',
    checkSubmit: 'Vérifier',
    checkNote:
        'Chaque vérification est enregistrée : quiconque vérifie ce code après vous peut voir quand il a été vérifié.',

    resultTitle_first_check: 'Produit authentique',
    resultTitle_checked_before: 'Produit authentique',
    resultTitle_checked_elsewhere: 'Déjà vérifié',
    resultTitle_unknown: 'Code non reconnu',
    resultTitle_revoked: 'Code retiré',
    resultText_first_check:
        'Ce code a été émis pour ce produit, et c’est la première fois que quelqu’un le vérifie.',
    resultText_checked_before:
        'Ce code a été émis pour ce produit. Il n’a jusqu’ici été vérifié que depuis cet appareil.',
    resultText_checked_elsewhere:
        'Ce code a été émis pour ce produit, mais il a déjà été vérifié depuis un autre appareil. Si ce n’était pas vous, il peut s’agir d’un produit d’occasion ou d’une étiquette copiée. Soyez prudent.',
    resultText_unknown:
        'Ce code n’est pas enregistré pour ce produit. Le produit n’est peut-être pas authentique. Vérifiez que le numéro de série a été saisi correctement.',
    resultText_revoked:
        'L’entreprise qui met ce produit sur le marché a retiré ce code. Ne vous fiez pas à cette étiquette.',
    historyTitle: 'Vérifications précédentes',
    historyCount: '{count} vérifications précédentes',
    historyCountOne: '1 vérification précédente',
    historyMore: 'et {count} de plus',
    thisDevice: 'Cet appareil',
    otherDevice: 'Un autre appareil',

    safetyTitle: 'Informations de sécurité',
    age_grading: 'Âge recommandé',
    warning_text: 'Avertissement',
    safety_notice: 'Avis de sécurité',
    safety_instructions: 'Consignes de sécurité',
    material_information: 'Informations sur les matériaux',
    usage_restrictions: 'Restrictions d’utilisation',

    documentsTitle: 'Documents',
    documentsIntro:
        'Mis à la disposition du public par l’entreprise qui met ce produit sur le marché.',
    preview: 'Aperçu',
    previewOf: 'Aperçu de {name}',
    enlargePicture: 'Agrandir l’image',
    openInNewTab: 'Ouvrir dans un nouvel onglet',
    pdfNotShown:
        'Ce navigateur ne peut pas afficher les PDF ici. Ouvrez-le plutôt dans un nouvel onglet.',
    docType_test_report: 'Rapport d’essai',
    docType_declaration_of_conformity: 'Déclaration de conformité',
    docType_manual_or_instructions: 'Mode d’emploi ou instructions',
    docType_certificate: 'Certificat',
    docType_product_image: 'Image du produit',
    docType_safety_image: 'Image d’avertissement',
    docType_packaging_closeup: 'Détail de l’emballage',
    docType_regulatory_document: 'Document réglementaire',
    docType_other: 'Document',

    importedBy: 'Mis sur le marché par',
    companyDetails: 'Coordonnées de l’entreprise',
    companyDetailsFor: 'Coordonnées de {name}',
    companyAddress: 'Adresse',
    companyPhone: 'Téléphone',
    companyEmail: 'E-mail',
    companyWebsite: 'Site web',
    companyRepresentedBy: 'Représentée par',
    companyVatNumber: 'Numéro TVA / IDE',
    openInGoogleMaps: 'Ouvrir dans Google Maps',
    close: 'Fermer',
    footer: 'Informations de conformité publiées par l’entreprise qui met ce produit sur le marché.',

    checkTitle: 'Vérifier un produit',
    checkIntro:
        'Saisissez le numéro de série imprimé à côté du code QR sur l’emballage. Vous accédez à la page du produit, où vous pouvez le vérifier.',
    checkButton: 'Vérifier',
};

const it: Dictionary = {
    productCheck: 'Verifica del prodotto',
    language: 'Lingua',

    sealPill_verified: 'Verificato',
    sealPill_in_progress: 'In verifica',
    sealPill_not_verified: 'Non verificato',
    sealTitle_verified: 'Verificato',
    sealTitle_in_progress: 'In corso',
    sealTitle_not_verified: 'Non verificato',
    sealDesc_verified:
        'Questo prodotto soddisfa i requisiti svizzeri di conformità.',
    sealDesc_in_progress: 'La verifica di conformità non è ancora conclusa.',
    sealDesc_not_verified: 'Ancora nessuna verifica di conformità completata.',
    approved: 'Approvato',
    dataCollected: 'Dati di conformità raccolti',
    overridden:
        'Impostato da chi immette il prodotto sul mercato, non da una verifica completata.',

    noPicture: 'Ancora nessuna immagine di questo articolo',
    showPicture: 'Mostra immagine',
    previousPicture: 'Immagine precedente',
    nextPicture: 'Immagine successiva',
    ean: 'EAN',
    articleNumber: 'N. articolo',

    checkCardTitle: 'Questo prodotto è originale?',
    checkCardIntro:
        'Ogni confezione ha il proprio numero di serie. Lo verifichi per sapere se è originale e se qualcuno lo ha già verificato prima di Lei.',
    serial: 'Numero di serie',
    serialHint: 'Stampato accanto al codice QR sulla confezione',
    turnstileUnavailable:
        'Non è stato possibile caricare il controllo di sicurezza. Ricarichi la pagina per riprovare.',
    checkSubmit: 'Verifica',
    checkNote:
        'Ogni verifica viene registrata, così chi verifica questo codice dopo di Lei può vedere quando è stato verificato.',

    resultTitle_first_check: 'Prodotto originale',
    resultTitle_checked_before: 'Prodotto originale',
    resultTitle_checked_elsewhere: 'Già verificato',
    resultTitle_unknown: 'Codice non riconosciuto',
    resultTitle_revoked: 'Codice ritirato',
    resultText_first_check:
        'Questo codice è stato emesso per questo prodotto ed è la prima volta che qualcuno lo verifica.',
    resultText_checked_before:
        'Questo codice è stato emesso per questo prodotto. Finora è stato verificato solo da questo dispositivo.',
    resultText_checked_elsewhere:
        'Questo codice è stato emesso per questo prodotto, ma è già stato verificato da un altro dispositivo. Se non è stato Lei, potrebbe trattarsi di un prodotto usato o di un’etichetta copiata. Presti attenzione.',
    resultText_unknown:
        'Questo codice non è registrato per questo prodotto. Il prodotto potrebbe non essere originale. Controlli di aver digitato correttamente il numero di serie.',
    resultText_revoked:
        'L’azienda che immette questo prodotto sul mercato ha ritirato questo codice. Non si affidi a questa etichetta.',
    historyTitle: 'Verifiche precedenti',
    historyCount: '{count} verifiche precedenti',
    historyCountOne: '1 verifica precedente',
    historyMore: 'e altre {count}',
    thisDevice: 'Questo dispositivo',
    otherDevice: 'Un altro dispositivo',

    safetyTitle: 'Informazioni di sicurezza',
    age_grading: 'Età consigliata',
    warning_text: 'Avvertenza',
    safety_notice: 'Avvertenza di sicurezza',
    safety_instructions: 'Istruzioni di sicurezza',
    material_information: 'Informazioni sui materiali',
    usage_restrictions: 'Limitazioni d’uso',

    documentsTitle: 'Documenti',
    documentsIntro:
        'Resi pubblici dall’azienda che immette questo prodotto sul mercato.',
    preview: 'Anteprima',
    previewOf: 'Anteprima di {name}',
    enlargePicture: 'Ingrandisci immagine',
    openInNewTab: 'Apri in una nuova scheda',
    pdfNotShown:
        'Questo browser non può mostrare i PDF qui. Lo apra invece in una nuova scheda.',
    docType_test_report: 'Rapporto di prova',
    docType_declaration_of_conformity: 'Dichiarazione di conformità',
    docType_manual_or_instructions: 'Manuale o istruzioni',
    docType_certificate: 'Certificato',
    docType_product_image: 'Immagine del prodotto',
    docType_safety_image: 'Immagine di avvertenza',
    docType_packaging_closeup: 'Dettaglio dell’imballaggio',
    docType_regulatory_document: 'Documento normativo',
    docType_other: 'Documento',

    importedBy: 'Immesso sul mercato da',
    companyDetails: 'Dati aziendali',
    companyDetailsFor: 'Dati aziendali di {name}',
    companyAddress: 'Indirizzo',
    companyPhone: 'Telefono',
    companyEmail: 'E-mail',
    companyWebsite: 'Sito web',
    companyRepresentedBy: 'Rappresentata da',
    companyVatNumber: 'Numero IVA / IDI',
    openInGoogleMaps: 'Apri in Google Maps',
    close: 'Chiudi',
    footer: 'Informazioni di conformità pubblicate dall’azienda che immette questo prodotto sul mercato.',

    checkTitle: 'Verificare un prodotto',
    checkIntro:
        'Inserisca il numero di serie stampato accanto al codice QR sulla confezione. Arriverà alla pagina del prodotto, dove potrà verificarlo.',
    checkButton: 'Verifica',
};

const DICTIONARIES: Record<PublicLocale, Dictionary> = { en, de, fr, it };

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
        PUBLIC_LOCALES.find((option) => option.value === initial)?.value ??
            'en',
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
 *
 * With a time, it is the reader's own clock -- a buyer in New York sees New
 * York time -- followed by the zone in brackets, because a screenshot of it
 * travels: whoever it is forwarded to, in another country, still knows
 * which clock it was read off. The offset is worked out for that date, so a
 * time from summer reads in summer time.
 */
export function formatPublicDate(
    timestamp: string,
    locale: PublicLocale,
    withTime = false,
): string {
    const date = new Date(timestamp);
    const language = locale === 'en' ? 'en-GB' : `${locale}-CH`;

    const text = date.toLocaleString(language, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    });

    if (!withTime) {
        return text;
    }

    const offset =
        new Intl.DateTimeFormat('en-GB', { timeZoneName: 'shortOffset' })
            .formatToParts(date)
            .find((part) => part.type === 'timeZoneName')?.value ?? '';

    return `${text} (${[readerTimeZone(), offset].filter(Boolean).join(', ')})`;
}

/**
 * The reader's own time zone, as their browser names it.
 */
export function readerTimeZone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

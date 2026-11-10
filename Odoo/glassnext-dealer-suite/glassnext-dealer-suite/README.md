# GlassNext Dealer Suite

Afgeschermde suite voor beheerders en dealers: ruitmaten importeren uit Excel/CSV,
snijplan berekenen en de offerte direct als verkoopofferte in Odoo zetten.

## Vereisten

- **GlassNext Suite 1.9.2 of hoger** (actief). De Dealer Suite gebruikt daarvan:
  - de Odoo-koppeling (URL, database, API-key, UID) en product-ID's;
  - rolbreedte, snijrand, tussenruimte, optimalisatie en prijzen;
  - het aanvragen-overzicht (`GlassNext Suite > Aanvragen`) en het offertenummer.

## Installatie

1. Update eerst de GlassNext Suite naar 1.9.2.
2. Upload `glassnext-dealer-suite.zip` via Plugins > Nieuwe plugin > Plugin uploaden en activeer.
3. Maak een pagina met de shortcode `[glassnext_dealer_suite]`.
   Zet de pagina eventueel op "Privé" in het menu: de shortcode toont zelf een inlogformulier.
4. Dealers aanmaken: Gebruikers > Nieuwe toevoegen, rol **GlassNext dealer**.
5. Instellingen: `GlassNext Suite > Dealer Suite`.

## Toegang

| Gebruiker                | Ziet                                     |
|--------------------------|------------------------------------------|
| Niet ingelogd            | Inlogformulier                           |
| Ingelogd zonder rechten  | Melding "geen toegang"                   |
| GlassNext dealer         | Suite + alleen eigen offertes            |
| Beheerder                | Suite + alle dealerofferte's + Odoo-link |

Toegang loopt via de capability `gn_dealer_access` (beheerders krijgen die bij activeren).

## Importformaat

Gelijk aan het sjabloon van de lokale GlassNext Suite (CSV met `;` of `,`, of XLSX, eerste werkblad):

| Kenmerk | Breedte cm | Hoogte cm | Aantal | Rotatie | Ruimte / verdieping |
|---------|-----------:|----------:|-------:|---------|---------------------|
| R1      | 88         | 68        | 4      | Ja      | Woonkamer           |

- Een titelregel boven de kopregel mag (er wordt in de eerste 10 rijen gezocht).
- Komma als decimaalteken mag (`88,5`).
- Kolom `Breedte mm` / `Hoogte mm` wordt automatisch omgerekend.
- Aantal leeg = 1; Rotatie leeg = standaard uit de instellingen.

## Wat er in Odoo komt

- `res.partner`: eindklant (op e-mail, anders op naam) of de dealer, afhankelijk van de instelling.
- `sale.order` (concept) met referentie = offertenummer + eigen referentie, label (standaard `dealer`).
- Orderregels zoals de websiteflow: GlassShield (m² rolverbruik), aanbrengen, voorsnijden (stuks),
  overige kosten, voorrijkosten (bij aanbrengen door GlassNext). Prijzen komen uit Odoo.
- Chatter-notitie met klantgegevens, dealer, ruitmaten en snijplancijfers.
- Bijlagen (als bestand in Odoo): origineel importbestand, snijplan-PDF, snijplan-CSV.

Mislukt de Odoo-sync, dan blijft de offerte bewaard in WordPress en kan via "Opnieuw" in het
overzicht opnieuw verstuurd worden.

# GlassNext Suite WordPress Plugin

## Installatie

1. Kopieer de `glassnext-suite` map naar `wp-content/plugins/`
2. Activeer de plugin via WordPress admin > Plugins
3. Ga naar **GlassNext Suite > Instellingen** om alle standaardwaarden in te stellen:
   - GlassShield uitgangspunten (rolbreedte, rollengte, etc.)
   - Prijsinstellingen (materiaalprijs, montage, etc.)
   - ROI technische en financiële parameters
   - Offerte instellingen (voorvoegsel, bijv. GNW)
   - E-mail instellingen
4. Plaats het logo bestand als `assets/glassnext-logo.png` (optioneel)
5. Maak een nieuwe pagina en voeg de shortcode `[glassnext_suite]` toe, of kies de page template "GlassNext Suite"

## Gebruik

- **Klant**: Opent de pagina met de tool, vult projectgegevens in, maakt snijplan, calculatie, ROI en vraagt offerte aan
- **Admin**: Ontvangt e-mail notificatie bij nieuwe aanvraag, kan alle aanvragen inzien onder **GlassNext Suite > Aanvragen**
- **JSON download**: Admin kan het projectbestand (.json) downloaden vanuit de aanvraag detail pagina

## Offertenummer

Wordt automatisch gegenereerd bij "Offerte aanvragen":
- Formaat: `{voorvoegsel}-{jaar}-{volgnummer}` (bijv. GNW-2026-001)
- Voorvoegsel instelbaar in admin settings
- Jaar wordt automatisch huidig jaar
- Volgnummer reset bij jaarovergang

## Bestandsstructuur

```
glassnext-suite/
├── glassnext-suite.php              Hoofdplugin
├── assets/
│   ├── glassnext-tool.js            Klant tool JS
│   ├── glassnext-admin.js           Admin options JS
│   ├── glassnext-style.css          CSS styling
│   └── glassnext-logo.png           Logo (optioneel)
├── includes/
│   ├── class-gn-options.php         Admin options page
│   ├── class-gn-submissions.php     Custom post type + AJAX
│   ├── class-gn-email.php           E-mail logica
│   └── class-gn-admin-columns.php   Admin list columns + meta box
└── templates/
    ├── glassnext-tool.php           HTML template klant tool
    └── page-glassnext-suite.php     Page template
```

# GlassNext Dealer Suite

Mobiele webapp (PWA) voor dealers en beheerders: ruitmaten op locatie invoeren of importeren uit
Excel/CSV, snijplan berekenen en de offerte direct als verkoopofferte in Odoo zetten.

- **App:** `https://glassnext.nl/dealers/` (installeerbaar op iOS en Android, volledig scherm, zonder thema)
- **Binnen het thema (optioneel):** shortcode `[glassnext_dealer_suite]` op een pagina

## Vereisten

- **GlassNext Suite 1.9.2 of hoger** (actief; 1.9.3 aanbevolen). De Dealer Suite gebruikt daarvan:
  - de Odoo-koppeling (URL, database, API-key, UID) en product-ID's;
  - rolbreedte, snijrand, tussenruimte, optimalisatie en prijzen;
  - de ROI-uitgangspunten (graaddagen, ketelrendement, CO₂-factoren, standaard gas- en stroomprijs);
  - het aanvragen-overzicht (`GlassNext Suite > Aanvragen`) en het offertenummer.
- HTTPS (vereist voor installeren als app en de service worker).

## Installatie

1. Update eerst de GlassNext Suite naar 1.9.3 (die stuurt ook bij website-aanvragen de ROI-PDF mee naar Odoo).
2. Upload `glassnext-dealer-suite.zip` via Plugins > Nieuwe plugin > Plugin uploaden en activeer.
3. Open `https://glassnext.nl/dealers/` — de app werkt direct (de route wordt automatisch aangemaakt).
   Er mag **geen gewone pagina** met het adres `/dealers/` bestaan; kies anders een ander adres bij
   `GlassNext Suite > Dealer Suite > Adres van de app`.
4. Dealers aanmaken: Gebruikers > Nieuwe toevoegen, rol **GlassNext dealer**.
5. Instellingen: `GlassNext Suite > Dealer Suite` (o.a. eigen app-icoon uit de mediabibliotheek).

### Caching

Sluit `/dealers/` uit van paginacache (WP Rocket, LiteSpeed Cache, W3TC, Cloudflare APO enz.).
De app stuurt zelf `no-cache`-headers, maar sommige caches negeren die.
Uitsluiten: `/dealers/`, `/dealers/sw`, `/dealers/manifest.webmanifest`.

### dealer.glassnext.nl → glassnext.nl/dealers

Het subdomein is alleen een doorverwijzing; alles draait op één WordPress.

1. **DNS:** record `dealer` als CNAME naar `glassnext.nl` (of hetzelfde A-record als glassnext.nl).
2. **SSL:** certificaat voor `dealer.glassnext.nl` aanvragen (Let's Encrypt in het hostingpaneel).
3. **Redirect (301)** naar `https://glassnext.nl/dealers/`, via de redirect-functie van de hosting of
   in `.htaccess` van de webroot (vóór de WordPress-regels):

   ```apache
   RewriteEngine On
   RewriteCond %{HTTP_HOST} ^dealer\.glassnext\.nl$ [NC]
   RewriteRule ^ https://glassnext.nl/dealers/ [R=301,L]
   ```

   Nginx:

   ```nginx
   server { server_name dealer.glassnext.nl; return 301 https://glassnext.nl/dealers/; }
   ```

## App installeren (dealers)

- **iPhone/iPad:** open de link in Safari → Deel → *Zet op beginscherm*.
- **Android:** open de link in Chrome → *App installeren* (of menu ⋮ → *App installeren*).
- In de app staat bij **Account** dezelfde uitleg.

Dealers die "Ingelogd blijven" aanvinken blijven 30 dagen ingelogd (beheerders: WordPress-standaard).

## Werking

| Tab        | Inhoud |
|------------|--------|
| **Nieuw**  | Wizard in 5 stappen: Klant → Maten → Snijplan → ROI → Versturen |
| **Offertes** | Verstuurde offertes met Odoo-status; "Opnieuw versturen" bij een fout |
| **Account** | Uitloggen, app installeren |

- **Foto per ruit:** bij de snelinvoer of per regel één foto (camera of galerij). De foto wordt
  verkleind (max. 1600 px, JPEG) en krijgt de naam van het kenmerk (`R1.jpg`; dubbel kenmerk → `R1_2.jpg`).
  Foto's worden na de offerte één voor één verstuurd (geen last van uploadlimieten) en als bijlage aan
  de Odoo-offerte gehangen. Ze blijven bij concept en wachtrij op het toestel bewaard (IndexedDB).
- **ROI:** zelfde model als de ROI-tab van de GlassNext Suite. Aan te passen: glastype, gasprijs en
  elektriciteitsprijs; de overige uitgangspunten komen uit de instellingen. De ROI-PDF gaat altijd mee.
- **Snelinvoer van maten:** breedte, hoogte, aantal, rotatie, ruimte → *Toevoegen & volgende*;
  het kenmerk (R1, R2…) wordt automatisch opgehoogd, ruimte en rotatie blijven staan.
- **Concept wordt automatisch op het toestel bewaard** (per gebruiker) en hersteld bij openen.
  Na versturen of "Wissen" wordt het concept verwijderd.
- **Offline / wachtrij:** invoeren en snijplan berekenen werkt zonder verbinding. "Versturen" zet de
  offerte dan in de **wachtrij** op het toestel (met snijplan-PDF en importbestand tot 1,5 MB). Ook als
  de verbinding tijdens het versturen wegvalt, komt de offerte in de wachtrij. Weer online: balk
  "Nu versturen" bovenin, en bij **Offertes** per offerte *Versturen* / *Verwijderen*. Elke offerte heeft
  een unieke `clientId`; de server maakt per `clientId` maar één offerte aan, dus opnieuw versturen
  geeft nooit een dubbele offerte in WordPress of Odoo. In de Odoo-chatter staat bij offline gemaakte
  offertes het oorspronkelijke tijdstip.
- **Sessie verlopen:** de app ververst zelf de beveiligingssleutel; is de login verlopen, dan vraagt
  de app opnieuw in te loggen — het concept blijft bewaard.

## Toegang

| Gebruiker                | Ziet                                     |
|--------------------------|------------------------------------------|
| Niet ingelogd            | Inlogscherm                              |
| Ingelogd zonder rechten  | Melding "geen toegang"                   |
| GlassNext dealer         | App + alleen eigen offertes              |
| Beheerder                | App + alle dealerofferte's + Odoo-link   |

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
- Bijlagen: snijplan-PDF, ROI-PDF, snijplan-CSV, foto's per ruit (naam = kenmerk) en het
  origineel importbestand (indien geïmporteerd).
- Chatter-notitie bevat ook de ROI-samenvatting (glastype, energieprijzen, besparing, terugverdientijd).

Mislukt de Odoo-sync, dan blijft de offerte bewaard in WordPress en kan via "Opnieuw versturen"
in het overzicht opnieuw verstuurd worden.

## Technisch

| URL                                  | Wat |
|--------------------------------------|-----|
| `/dealers/`                          | App-shell (zonder thema, `noindex`) |
| `/dealers/manifest.webmanifest`      | Web app manifest |
| `/dealers/sw`                        | Service worker (scope `/dealers/`) — cachet alleen app-bestanden, nooit `admin-ajax.php`/login |
| `/.well-known/assetlinks.json`       | Alleen als Android-package + fingerprint zijn ingevuld |

jsPDF 2.5.1 en SheetJS 0.20.3 worden lokaal meegeleverd (`assets/vendor/`), zodat de app geen
externe CDN nodig heeft en offline werkt.

## Later: App Store en Google Play

De PWA is voorbereid om als "echte" app in de stores te komen:

- **Google Play (Trusted Web Activity):** genereer een Android-project met
  [PWABuilder](https://www.pwabuilder.com/) of Bubblewrap op `https://glassnext.nl/dealers/manifest.webmanifest`.
  Vul daarna bij *Dealer Suite > Android-app* het package-ID en de SHA-256-fingerprint van de
  signing key in; dan wordt `/.well-known/assetlinks.json` geserveerd (controleer dat de server
  `.well-known` doorgeeft aan WordPress en er geen statisch bestand met die naam staat).
  Vereist: Google Play-ontwikkelaarsaccount (eenmalig $25).
- **Apple App Store:** een Capacitor-wrapper die `https://glassnext.nl/dealers/` laadt.
  Vereist: Apple Developer-account (€99/jaar), een Mac met Xcode en de App Review. Apple wijst
  "website in een schil" soms af; de snelinvoer, concept-opslag en offline-werking helpen.

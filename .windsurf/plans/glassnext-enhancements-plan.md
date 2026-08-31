# GlassNext Tool Enhancement Plan

## Overview
This plan covers 6 enhancements to the GlassNext Suite tool (Odoo version, v1.4.0) located at `Odoo/glassnext-suite/glassnext-suite/`.

## Target Files
- `templates/glassnext-tool.php` — HTML structure, tabs, page sections
- `assets/glassnext-tool.js` — Front-end logic (tab navigation, calculations, submission)
- `assets/glassnext-style.css` — Styling (tooltips, new UI elements)
- `includes/class-gn-options.php` — Backend settings (tooltips, voorrijdkosten, laten-opmeten config)
- `includes/class-gn-odoo.php` — Direct Odoo integration (voorrijdkosten line, laten-opmeten order)
- `includes/class-gn-submissions.php` — Offer submission, webhook payload, line items
- `glassnext-suite.php` — Plugin main (localize new config to JS)

---

## 1. Reorder Tabs: Project Tab Before Offerte Tab

**Current order:** Project → Snijplanner → Calculatie → ROI → Offerte → Werkbon → Export

**New order (for "Zelf opmeten" flow):** Snijplanner → Calculatie → ROI → Project → Offerte → Werkbon → Export

### Changes

#### `templates/glassnext-tool.php`
- Reorder tab buttons (lines 6-13): move `project` tab to position 4 (before `offer`)
- Renumber all tab labels: 1. Snijplanner, 2. Calculatie, 3. ROI, 4. Project, 5. Offerte, 6. Werkbon, 7. Export
- Reorder `<section>` elements to match: move `page-project` section to after `page-roi`
- Update all `nav-prev` / `nav-next` `data-target` attributes to reflect new order

#### `assets/glassnext-tool.js`
- Update `PAGES` array (line 48): `["planner","calc","roi","project","offer","workorder","exports"]`
- The `visiblePages()` and `renderNavButtons()` functions already derive from `PAGES` + tab visibility, so they adapt automatically
- Update `activatePage` if needed (project page doesn't trigger special rendering, so no change needed)

---

## 2. New Initial Step: "Laten opmeten" vs "Zelf opmeten"

### 2A. New Landing/Choice Page

#### `templates/glassnext-tool.php`
- Add a new page section `page-choice` as the first visible page (before snijplanner)
- Add a new tab button `data-page="choice"` labeled "1. Start" (or no number, just "Start")
- Content: two large cards/buttons:
  - **"Laten opmeten"** — €149,- (description: "Wij komen bij u langs om de ruiten op te meten")
  - **"Zelf opmeten"** — (description: "U meet zelf de ruiten op en wij maken het snijplan")
- `page-choice` has no `nav-prev`, only a hidden `nav-next` (navigation happens via the choice buttons)

#### `assets/glassnext-tool.js`
- Add `"choice"` as first entry in `PAGES` array
- Add click handlers for the two choice buttons:
  - **"Laten opmeten"**: set `flowMode = "measure"`, show project page with date fields, hide snijplanner/calc/roi tabs, change submit button text to "Inmeten aanvragen"
  - **"Zelf opmeten"**: set `flowMode = "self"`, show snijplanner/calc/roi/project/offer tabs, hide choice tab
- New variable `let flowMode = null;` ("measure" or "self")
- When `flowMode === "measure"`:
  - Hide tabs: snijplanner, calc, roi (via `.hidden` class)
  - Show project page directly (with 3 date fields visible)
  - The submit button on project page says "Inmeten aanvragen" and submits a different AJAX action (or same action with `flowMode=measure`)
  - Price: €149 fixed (configurable in backend)
- When `flowMode === "self"`:
  - Hide choice tab
  - Start at snijplanner
  - Remove installMode radio buttons (always "self" install — but actually the user said "de optie van Laten aanbrengen of zelf aanbrengen mag weg" — this means the montage/install choice is removed; install is always professional/laten aanbrengen since the customer is getting a quote with montage)
  - Hide "Optimale indeling" section and "Stukkenlijst" section
  - The "Volgende" button on snijplanner triggers the snijplan calculation automatically, then navigates to calc

### 2B. "Laten opmeten" Flow — Project Page with Date Fields

#### `templates/glassnext-tool.php`
- Add 3 date input fields to the project page section (inside a div with class `measure-only hidden`):
  - `<label>Voorkeursdatum 1</label><input id="prefDate1" type="date">`
  - `<label>Voorkeursdatum 2</label><input id="prefDate2" type="date">`
  - `<label>Voorkeursdatum 3</label><input id="prefDate3" type="date">`
- These fields are only visible when `flowMode === "measure"`
- The project page submit button text changes dynamically based on flowMode

#### `assets/glassnext-tool.js`
- In `submitOffer()` (or a new `submitMeasure()` function):
  - When `flowMode === "measure"`: validate dates, send AJAX with `action: "gn_submit_measure"` (or `gn_submit_offer` with `flow_mode: "measure"`)
  - Include the 3 preferred dates in the payload
  - Fixed price €149 (from `GN_CONFIG.options.gn_measure_price`)
- Add `prefDate1`, `prefDate2`, `prefDate3` to `collectProject()` and `projectData()`

#### `includes/class-gn-submissions.php`
- Handle `flow_mode === "measure"` in `ajax_submit_offer()`:
  - Save the 3 preferred dates as post meta
  - Include dates in webhook payload
  - Include dates in Odoo sync payload
  - The line item is just "Laten opmeten" at €149 (product ID configurable, default = measure product)

#### `includes/class-gn-odoo.php`
- In `sync_order()`: if `flowMode === "measure"`:
  - Add the 3 preferred dates as a note/description on the sale order
  - Add a single line item: product ID `gn_odoo_product_measure` (configurable, see below) with qty 1
  - If `gn_odoo_create_calendar_event` option is checked: create a calendar event in Odoo (concept) for the first preferred date
  - Otherwise: just put the dates in the order note

#### `includes/class-gn-options.php`
- New default options:
  - `gn_measure_price` => '149'
  - `gn_odoo_product_measure` => '' (product ID for "Laten opmeten" service)
  - `gn_odoo_create_calendar_event` => '' (checkbox: create concept calendar event)
- Register these in `register_settings()`
- Add UI fields in the Odoo settings tab:
  - "Product-ID: Laten opmeten (€149)" — number input
  - "Agenda-afspraak aanmaken in Odoo" — checkbox (when checked, creates a concept calendar event for the first preferred date)
- Add "Laten opmeten" price field in the Offerte settings tab

### 2C. "Zelf opmeten" Flow — Modified Snijplanner

#### `templates/glassnext-tool.php`
- Add class `self-flow-only` to the "Aanbrengen folie" card (installMode section) — this will be hidden
- Add class `self-flow-only` to the "Optimale indeling" card and "Stukkenlijst" card — these will be hidden
- The "Bereken snijplan" button in the "Optimale indeling" card toolbar is removed/hidden
- The snijplanner page's `nav-next` button gets an `id="plannerNext"` and its behavior changes

#### `assets/glassnext-tool.js`
- When `flowMode === "self"`:
  - Hide elements with class `self-flow-only` (installMode card, optimale indeling, stukkenlijst)
  - The `nav-next` button on snijplanner triggers the calculation:
    ```
    if(currentPage === "planner" && flowMode === "self"){
      // run calculatePlan logic, then navigate to calc
      await calculateAndProceed();
    }
    ```
  - New function `calculateAndProceed()`: runs the same optimization as `calculatePlan` click handler, shows a loading state on the "Volgende" button, then navigates to calc page on success
  - Set `installMode` to "professional" always (montage is always included — the user said remove the choice, not remove montage itself)

---

## 3. Remove Duplicate Scenario Analysis Text on Offerte Tab

#### `templates/glassnext-tool.php` (lines 252-256)
- Remove the `<div class="summary-box">` containing the two `<p>` tags about scenarioanalyse
- Keep the consent checkbox (lines 257-259) which has the same text — that stays

---

## 4. Snijplanner Table: Placeholders + Tooltips

### 4A. First Row Placeholders

#### `assets/glassnext-tool.js` (line 120)
- Change `addPaneRow({id:"R1",w:88,h:68,n:4})` to `addPaneRow()` (empty row with placeholders)
- In `addPaneRow()`, the input fields already use `value="${p.w??""}"` etc., so empty values show as empty — but we need to add `placeholder` attributes:
  - `.pid` input: `placeholder="bijv. R1"`
  - `.pw` input: `placeholder="bijv. 88"`
  - `.ph` input: `placeholder="bijv. 68"`
  - `.pn` input: `placeholder="1"` (already has `value="1"` as default, keep)
  - `.proom` input: `placeholder="bijv. Woonkamer"`

### 4B. Column Header Tooltips

#### `templates/glassnext-tool.php` (line 83)
- Replace the `<thead>` with tooltip-enabled headers:
  ```html
  <thead><tr>
    <th><span class="th-label">Kenmerk</span> <span class="tooltip-icon" data-tooltip="kenmerk">ⓘ</span></th>
    <th><span class="th-label">Breedte cm</span> <span class="tooltip-icon" data-tooltip="breedte">ⓘ</span></th>
    ...etc for each column
  </tr></thead>
  ```

#### `assets/glassnext-tool.js`
- In `initConfig()`, load tooltip data from `GN_CONFIG.options` (e.g., `gn_tooltip_kenmerk`, `gn_tooltip_breedte`, etc.)
- Render tooltips dynamically: for each `.tooltip-icon`, build a tooltip popover with text + optional image
- Tooltip behavior: hover (desktop) or tap (mobile) shows a popover with the text and optional image

#### `assets/glassnext-style.css`
- Add tooltip styles:
  - `.tooltip-icon` — small info icon, inline, cursor pointer
  - `.tooltip-popover` — absolutely positioned, max-width 300px, background white, border, shadow, z-index high
  - `.tooltip-popover img` — max-width 100%, border-radius

#### `includes/class-gn-options.php`
- New default options for 6 column tooltips:
  - `gn_tooltip_kenmerk` => ['text' => 'Een unieke naam of nummer voor deze ruit, bijv. "R1" of "Raam woonkamer".', 'image_id' => '']
  - `gn_tooltip_breedte` => ['text' => 'De breedte van de ruit in centimeters, gemeten aan de binnenzijde van de sponning.', 'image_id' => '']
  - `gn_tooltip_hoogte` => ['text' => 'De hoogte van de ruit in centimeters, gemeten aan de binnenzijde van de sponning.', 'image_id' => '']
  - `gn_tooltip_aantal` => ['text' => 'Het aantal ruiten met deze afmetingen.', 'image_id' => '']
  - `gn_tooltip_rotatie` => ['text' => 'Of de ruit 90 graden gedraaid op de rol geplaatst mag worden voor optimale sneding.', 'image_id' => '']
  - `gn_tooltip_ruimte` => ['text' => 'De ruimte of verdieping waar deze ruit zich bevindt, voor de werkbon en planning.', 'image_id' => '']
- Register all tooltip options in `register_settings()`
- Add a new admin settings tab "Snijplanner tooltips" (or add to existing GlassShield tab):
  - For each column: a textarea for tooltip text + a WordPress media picker button for an optional image
  - Reuse the existing media picker pattern from the email logo picker

#### `glassnext-suite.php`
- The `GN_CONFIG.options` already includes all options via `GN_Options::get_all_options()`, so tooltip data is automatically available in JS — no change needed

---

## 5. Voorrijdkosten Line Item (Odoo Product ID 86)

### 5A. Front-end Display

#### `assets/glassnext-tool.js`
- In `calculatePrices()`: add a "Voorrijdkosten" line item with price "Nader te berekenen" (display text, not a numeric value)
- In `renderOffer()`: add a row in the investment table: "Voorrijdkosten — Nader te berekenen"
- The line item doesn't affect the subtotal/total (price is not yet known)
- Add `voorrijdkosten` to `collectProject()` output (as a flag/line item)

### 5B. Make.com Webhook Payload

#### `includes/class-gn-submissions.php`
- In `extract_line_items()`: add a "Voorrijdkosten" line item at the end:
  ```php
  $items[] = [
      'name'        => 'Voorrijdkosten',
      'description' => 'Nader te berekenen',
      'quantity'    => 1,
      'unitPrice'   => 0,  // price to be determined
      'productId'   => 86,
  ];
  ```

### 5C. Direct Odoo Integration

#### `includes/class-gn-odoo.php`
- In `sync_order()`: after adding all other lines, add the voorrijdkosten line:
  ```php
  $product_voorrijd = (int) get_option('gn_odoo_product_voorrijd', 86);
  if ($product_voorrijd > 0) {
      $this->add_fixed_line($order_id, $product_voorrijd, 1);
  }
  ```
- This adds the product with Odoo's own price (which should be set to 0 or "nader te berekenen" in Odoo itself)

#### `includes/class-gn-options.php`
- New default option: `gn_odoo_product_voorrijd` => '86'
- Register in `register_settings()`
- Add UI field in Odoo settings tab: "Product-ID: Voorrijdkosten" — number input with default 86

---

## 6. Non-clickable Tabs (Navigation Only via Buttons)

#### `assets/glassnext-tool.js` (line 84)
- Remove or disable the tab click handler:
  ```js
  // OLD: $$(".tab").forEach(b=>b.onclick=()=>activatePage(b.dataset.page));
  // NEW: tabs are display-only, not clickable
  $$(".tab").forEach(b=>b.onclick=()=>{return false;});
  ```
- Or add `pointer-events: none; cursor: default;` to tabs via CSS

#### `assets/glassnext-style.css`
- Change `.tab` style: remove `cursor:pointer`, add `cursor:default`
- Optionally add `.tab.disabled` style for visual feedback

---

## Implementation Order

1. **Tab reordering** (point 1) — structural, affects everything else
2. **Non-clickable tabs** (point 6) — small, independent change
3. **Remove duplicate text** (point 3) — small, independent change
4. **Snijplanner placeholders + tooltips** (point 4) — medium, involves backend + frontend
5. **Voorrijdkosten** (point 5) — medium, involves backend + Odoo
6. **New initial step / flow modes** (point 2) — largest, depends on tab reordering being done

## Notes

- All changes target the Odoo version at `Odoo/glassnext-suite/glassnext-suite/`
- The `PAGES` array and `visiblePages()` pattern in JS already supports dynamic tab visibility — we leverage this for flow modes
- The `GN_CONFIG.options` localization already passes all options to JS — new options are automatically available
- WordPress media picker for tooltip images reuses the existing `wp_enqueue_media()` call already in `enqueue_admin_assets()`
- The voorrijdkosten line in Odoo uses `add_fixed_line()` which doesn't send a price — Odoo uses the product's own sales price (which should be 0 or "nader te berekenen" in Odoo)

<?php
if (!defined('GN_PLUGIN_URL')) define('GN_PLUGIN_URL', '');
?>

<main class="shell">
<nav class="tabs no-print">
  <button class="tab active" data-page="project">1. Project</button>
  <button class="tab" data-page="planner">2. Snijplanner</button>
  <button class="tab" data-page="calc">3. Calculatie</button>
  <button class="tab" data-page="roi">4. ROI-berekening</button>
  <button class="tab" data-page="offer">5. Offerte</button>
  <button class="tab hidden" data-page="workorder">6. Werkbon</button>
  <button class="tab hidden" data-page="exports">7. Export</button>
</nav>

<section class="page active" id="page-project">
<div class="grid">
  <article class="card span-12">
    <div class="card-head"><h2>Projectgegevens</h2><div class="toolbar no-print"><button class="btn secondary" id="loadJsonBtn">JSON laden</button><input type="file" id="loadJsonInput" accept=".json" style="display:none"></div></div>
    <div class="card-body">
      <div class="fields">
        <div><label>Klant / organisatie naam</label><input id="customerName" placeholder="Uw organisatie naam"></div>
        <div><label>Offertenummer</label><input id="offerNumber" readonly placeholder="wordt automatisch gegenereerd"></div>
        <div><label>Contactpersoon</label><input id="contactName"></div>
        <div><label>E-mailadres</label><input id="email" type="email"></div>
        <div><label>Telefoon</label><input id="phone"></div>
        <div><label>Projectadres</label><input id="address"></div>
        <div><label>Postcode en plaats</label><input id="city"></div>
      </div>
      <div style="margin-top:12px"><label>Projectomschrijving / situatie</label><textarea id="projectDescription" placeholder="Bijvoorbeeld: bestaande beglazing voorzien van Nano-EcoLine Climate GlassShield."></textarea></div>
      <div style="margin-top:12px"><label>Bijzonderheden</label><textarea id="projectNotes" placeholder="Bereikbaarheid, planning, glasconditie, werktijden, aandachtspunten..."></textarea></div>
    </div>
  </article>

  <article class="card span-5 hidden" aria-hidden="true" style="display:none">
    <div class="card-head"><h2>GlassShield uitgangspunten</h2><span class="pill">door beheerder ingesteld</span></div>
    <div class="card-body">
      <div class="fields">
        <div><label>Rolbreedte (mm)</label><input id="rollW" type="number" readonly></div>
        <div><label>Rollengte (mm)</label><input id="rollL" type="number" readonly></div>
        <div><label>Snijrand per zijde (mm)</label><input id="marginSide" type="number" readonly></div>
        <div><label>Tussenruimte (mm)</label><input id="kerf" type="number" readonly></div>
        <div><label>Optimalisatie</label><select id="quality" disabled></select></div>
        <div><label>Rotatie standaard</label><select id="rotateDefault" disabled></select></div>
      </div>
      <div class="summary-box" style="margin-top:14px">
        GlassShield wordt berekend op basis van het werkelijke rolverbruik. Iedere ruit krijgt eerst de ingestelde snijrand rondom. Daarna worden alle afzonderlijke stukken op de rol ingedeeld.
      </div>
    </div>
  </article>
</div>
</section>

<section class="page" id="page-planner">
<div class="grid">
  <article class="card span-12">
    <div class="card-head">
      <h2>Ramen invoeren</h2>
      <div class="toolbar no-print"><button class="btn ghost" id="addPane">+ Ruitmaat</button><button class="btn secondary" id="demoPanes">Demo laden</button></div>
    </div>
    <div class="card-body table-wrap scroll-hint">
      <table id="paneTable"><thead><tr><th>Kenmerk</th><th>Breedte cm</th><th>Hoogte cm</th><th>Aantal</th><th>Rotatie</th><th>Ruimte / verdieping</th><th></th></tr></thead><tbody></tbody></table>
    </div>
  </article>

  <article class="card span-12">
    <div class="card-head"><h2>Optimale indeling</h2>
      <div class="toolbar no-print"><button class="btn" id="calculatePlan">Bereken snijplan</button><button class="btn secondary" id="exportCSVTop">CSV plotter</button><button class="btn secondary" id="planPDF">Snijplan PDF</button></div>
    </div>
    <div class="card-body">
      <div id="planStatus" class="status">Voer ruitmaten in en bereken het snijplan.</div>
      <div class="progress"><div id="progressBar"></div></div>
      <div id="planMetrics" class="metrics" style="margin-top:12px"></div>
      <div id="legend" class="legend"></div>
      <div class="canvas-wrap"><canvas id="planCanvas" width="1000" height="500"></canvas></div>
      <p class="note">De planner probeert meerdere sorteervolgordes en plaatsingscombinaties. Het resultaat is een sterke praktische optimalisatie; bij 2D-nesting kan absolute mathematische optimaliteit niet worden gegarandeerd.</p>
    </div>
  </article>

  <article class="card span-12">
    <div class="card-head"><h2>Stukkenlijst</h2></div>
    <div class="card-body table-wrap scroll-hint" id="pieceReport">Nog geen snijplan berekend.</div>
  </article>
</div>
</section>

<section class="page" id="page-calc">
<div class="grid">
  <article class="card span-5 hidden" aria-hidden="true" style="display:none">
    <div class="card-head"><h2>Prijsinstellingen</h2><span class="pill">door beheerder ingesteld</span></div>
    <div class="card-body">
      <div class="fields">
        <div><label>Materiaalprijs per verbruikte m²</label><input id="materialPrice" type="number" step="0.01" readonly></div>
        <div><label>Voorsnijprijs per stuk</label><input id="cutPrice" type="number" step="0.01" readonly></div>
        <div><label>Materiaalkorting (%)</label><input id="materialDiscount" type="number" step="0.1" readonly></div>
        <div><label>Montagekorting (%)</label><input id="mountDiscount" type="number" step="0.1" readonly></div>
        <div><label>BTW (%)</label><input id="vatRate" type="number" step="0.1" readonly></div>
        <div style="grid-column:1/-1">
          <label>Overige kosten</label>
          <div id="otherCostRows" class="other-costs"></div>
          <p class="note">Deze kostenposten zijn door de beheerder ingesteld.</p>
        </div>
      </div>
      <h3>Montageklasse</h3>
      <div class="mount-rate-line">
        <div><label>Complexiteit montage</label><select id="mountClass" disabled>
          <option value="easy">Eenvoudig</option><option value="average" selected>Gemiddeld</option><option value="complex">Complex</option><option value="very">Zeer complex</option>
        </select></div>
        <div><label>Montagetarief gekozen klasse (€/m²)</label><input id="mountSelectedPrice" type="number" step="0.01" readonly></div>
      </div>
      <div style="margin-top:12px"><label>Montage berekenen over</label><select id="mountAreaBasis" disabled><option value="net" selected>Netto glasoppervlak</option><option value="gross">Bruto snijstukken</option></select></div>
      <p class="note">Standaardtarieven: Eenvoudig € 35/m², Gemiddeld € 45/m², Complex € 60/m² en Zeer complex € 75/m².</p>
    </div>
  </article>

  <article class="card span-12">
    <div class="card-head"><h2>Projectcalculatie</h2><button class="btn no-print" id="recalculate">Bereken prijzen</button></div>
    <div class="card-body">
      <div id="calcWarning" class="status warn">Bereken eerst een snijplan.</div>
      <div id="calcMetrics" class="metrics"></div>
      <div class="table-wrap scroll-hint" style="margin-top:14px">
        <table class="money-table"><thead><tr><th>Post</th><th>Grondslag</th><th>Tarief</th><th>Bedrag</th></tr></thead><tbody id="calcRows"></tbody></table>
      </div>
    </div>
  </article>
</div>
</section>

<section class="page" id="page-roi">
<div class="grid">
  <article class="card span-5 hidden" aria-hidden="true" style="display:none">
    <div class="card-head"><h2>Glastype en technische uitgangspunten</h2><span class="pill">glastype aanpasbaar</span></div>
    <div class="card-body">
      <div class="fields">
        <div><label>Rekenoppervlak</label>
          <select id="roiAreaSource" disabled><option value="planner" selected>Netto glas uit snijplanner</option></select>
        </div>
        <div><label>Glasoppervlak (m²)</label><input id="roiArea" type="number" min="0" step="0.01" value="0" readonly></div>
        <div><label>Berekeningsmethode gas</label>
          <select id="roiGasMethod" disabled><option value="uvalue" selected>Uit U-/Ug-verbetering</option></select>
        </div>
        <div><label>U-/Ug-waarde vóór (W/m²K)</label><input id="roiUBefore" type="number" min="0" step="0.01" readonly></div>
        <div><label>U-/Ug-waarde na GlassShield (W/m²K)</label><input id="roiUAfter" type="number" min="0" step="0.01" readonly></div>
        <div><label>U-/Ug-verbetering (W/m²K)</label><input id="roiUDelta" type="number" step="0.01" value="3.95" readonly></div>
        <div><label>Verbetering volgens TDS (%)</label><input id="roiImprovement" type="number" step="0.1" value="68.1" readonly></div>
        <div><label>U-factor vóór BTU/(h·ft²·°F)</label><input id="roiBTUBefore" type="number" step="0.0001" value="1.0214" readonly></div>
        <div><label>U-factor na BTU/(h·ft²·°F)</label><input id="roiBTUAfter" type="number" step="0.0001" value="0.3258" readonly></div>
        <div><label>Verbetering BTU/(h·ft²·°F)</label><input id="roiBTUDelta" type="number" step="0.0001" value="0.6956" readonly></div>
      </div>
      <h3>Gebouw- en installatieparameters</h3>
      <div class="fields">
        <div><label>Heating Degree Days per jaar</label><input id="roiHDD" type="number" min="0" step="1" readonly></div>
        <div><label>Ketelrendement (%)</label><input id="roiBoilerEff" type="number" min="1" max="100" step="0.1" readonly></div>
        <div><label>Energie-inhoud gas (kWh/m³)</label><input id="roiGasKwh" type="number" min="0.1" step="0.01" readonly></div>
        <div><label>Handmatige gasbesparing (m³/m²/jaar)</label><input id="roiGasSaveM2" type="number" min="0" step="0.0001" readonly></div>
        <div><label>Elektrabesparing koeling (kWh/m²/jaar)</label><input id="roiCoolSaveM2" type="number" min="0" step="0.01" readonly></div>
        <div><label>Correctiefactor gebouw (%)</label><input id="roiBuildingFactor" type="number" min="0" step="1" readonly></div>
      </div>
      <p class="note">De Ug-waarden en verbeteringspercentages in het keuzemenu zijn rechtstreeks overgenomen uit de Nano-EcoLine GlassShield TDS. De BTU-waarden worden omgerekend met 1 W/m²K = 0,1761101838 BTU/(h·ft²·°F).</p>
      <p class="note">De correctiefactor kan worden verlaagd wanneer oriëntatie, bezetting, ventilatie, stookgedrag of het ontbreken van koeling de theoretische besparing beperken.</p>
    </div>
  </article>

  <article class="card span-12">
    <div class="card-head"><h2>Financiële en CO₂-uitgangspunten</h2><button class="btn no-print" id="calculateROI">Bereken ROI</button></div>
    <div class="card-body">
      <div class="fields three">
        <div><label>Te verbeteren glastype</label>
          <select id="roiGlassType">
            <option value="single" selected>4 mm enkelglas</option>
            <option value="double_pre2008">Dubbelglas (voor 2008)</option>
            <option value="triple_air">Driedubbelglas (lucht)</option>
            <option value="double_lowe">Dubbelglas Low-E</option>
            <option value="planitherm_one">Dubbelglas Planitherm One</option>
            <option value="skn176">Dubbelglas SKN 176</option>
            <option value="triple_planitherm">Driedubbelglas met Planitherm One</option>
            <option value="custom">Eigen technische waarden</option>
          </select>
        </div>
        <div><label>Gasprijs (€/m³)</label><input id="roiGasPrice" type="number" min="0" step="0.01"></div>
        <div><label>Elektriciteitsprijs (€/kWh)</label><input id="roiElecPrice" type="number" min="0" step="0.01"></div>
      </div>
      <div class="fields three" style="margin-top:16px">
        <div><label>Investeringsbron</label><select id="roiInvestmentSource" disabled><option value="calculation" selected>Projectcalculatie excl. btw</option></select></div>
        <div><label>Bruto investering excl. btw</label><input id="roiInvestment" type="number" min="0" step="0.01" value="0" readonly></div>
        <div><label>EIA / subsidie netto voordeel (%)</label><input id="roiEiaNet" type="number" min="0" step="0.1" readonly></div>
        <div><label>CO₂-prijs (€/ton)</label><input id="roiCO2Price" type="number" min="0" step="1" readonly></div>
        <div><label>CO₂-factor gas (kg/m³)</label><input id="roiCO2Gas" type="number" min="0" step="0.001" readonly></div>
        <div><label>CO₂-factor stroom (kg/kWh)</label><input id="roiCO2Elec" type="number" min="0" step="0.001" readonly></div>
        <div><label>Analyseperiode (jaar)</label><input id="roiYears" type="number" min="1" step="1" readonly></div>
      </div>
      <div class="toolbar no-print" style="margin-top:14px">
        <button class="btn secondary" id="roiPDF">Maak ROI-PDF</button>
        <label style="display:flex;align-items:center;gap:8px;margin:0"><input id="includeROIInOffer" type="checkbox" style="width:auto" checked> ROI-samenvatting opnemen in offerte</label>
      </div>
      <div id="roiStatus" class="status warn">Bereken eerst een snijplan en projectcalculatie, of voer de waarden handmatig in.</div>
      <div id="roiMetrics" class="metrics" style="margin-top:12px"></div>
    </div>
  </article>

  <article class="card span-12">
    <div class="card-head"><h2>Besparingen en terugverdientijd</h2></div>
    <div class="card-body">
      <div class="table-wrap scroll-hint"><table class="money-table"><thead><tr><th>Onderdeel</th><th>Per m² per jaar</th><th>Project per jaar</th><th>Financiële waarde per jaar</th></tr></thead><tbody id="roiRows"></tbody></table></div>
      <div class="summary-box" style="margin-top:14px"><b>Belangrijke voorwaarde:</b> de uitkomst is een scenarioanalyse. Werkelijke besparingen hangen onder meer af van glasopbouw, geveloriëntatie, klimaatjaar, binnentemperatuur, verwarmings- en koelinstallatie, bezetting, ventilatie en gebruik van het gebouw. De uitkomst is daarom geen gegarandeerde energiebesparing.</div>
    </div>
  </article>
</div>
</section>

<section class="page" id="page-offer">
<div class="grid">
  <article class="card span-12">
    <div class="card-head"><h2>Offerte</h2><div class="toolbar no-print"><button class="btn" id="refreshOffer">Offerte vernieuwen</button><button class="btn secondary" id="offerPDF">Maak offerte-PDF</button><button class="btn secondary" id="printOffer">Afdrukken / bewaren als PDF</button></div></div>
    <div class="card-body"><div id="offerDoc" class="doc"></div></div>
  </article>
  <article class="card span-12">
    <div class="card-head"><h2>Offerte aanvragen</h2></div>
    <div class="card-body">
      <div class="summary-box" style="margin-bottom:14px;line-height:1.6">
        <p>Deze scenarioanalyse is afhankelijk van de werkelijke glasopbouw, gebouwcondities, installaties, energieprijzen en het gebruik. Aan de uitkomst kunnen geen gegarandeerde besparingen worden ontleend.</p>
        <p>Deze offerte is gebaseerd op de ingevoerde ruitmaten en het berekende snijplan. Definitieve maatvoering en geschiktheid van de beglazing worden vóór uitvoering gecontroleerd.</p>
      </div>
      <button class="btn" id="submitOffer" style="font-size:16px;padding:14px 24px;">Offerte aanvragen</button>
      <div id="submitStatus" class="status" style="margin-top:12px"></div>
    </div>
  </article>
</div>
</section>

<section class="page hidden" id="page-workorder">
<div class="grid">
  <article class="card span-12">
    <div class="card-head"><h2>Werkbon</h2><div class="toolbar no-print"><button class="btn" id="refreshWorkorder">Werkbon vernieuwen</button></div></div>
    <div class="card-body"><div id="workDoc" class="doc"></div></div>
  </article>
</div>
</section>

<section class="page hidden" id="page-exports">
<div class="grid">
  <article class="card span-12"><div class="card-head"><h2>Controleoverzicht</h2></div><div class="card-body" id="exportSummary"></div></article>
</div>
</section>
</main>

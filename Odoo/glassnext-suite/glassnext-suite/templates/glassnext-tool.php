<?php
if (!defined('GN_PLUGIN_URL')) define('GN_PLUGIN_URL', '');
?>

<main class="shell">
<nav class="tabs no-print">
  <button class="tab active" data-page="choice">Start</button>
  <button class="tab hidden" data-page="planner">Ruitmaten</button>
  <button class="tab hidden" data-page="calc">Calculatie</button>
  <button class="tab hidden" data-page="roi">ROI-berekening</button>
  <button class="tab hidden" data-page="project">Project</button>
  <button class="tab hidden" data-page="offer">Offerte</button>
  <button class="tab hidden" data-page="workorder">Werkbon</button>
  <button class="tab hidden" data-page="exports">Export</button>
</nav>

<section class="page active" id="page-choice">
<div class="grid">
  <article class="card span-12">
    <div class="card-head"><h2>Welkom bij GlassNext Suite</h2></div>
    <div class="card-body">
      <p class="choice-intro">Wilt u uw beglazing isoleren met Nano-EcoLine Climate GlassShield? Kies hieronder hoe u wilt beginnen.</p>
      <div class="choice-grid">
        <button class="btn choice-btn" id="choiceMeasure" type="button">
          <div class="choice-title">Laten opmeten</div>
          <div class="choice-desc">Wij komen bij u langs om de ruiten professioneel op te meten.</div>
          <div class="choice-price">€ 149,-</div>
        </button>
        <button class="btn secondary choice-btn" id="choiceSelf" type="button">
          <div class="choice-title">Zelf opmeten</div>
          <div class="choice-desc">U meet zelf de ruiten op en wij maken het snijplan en offerte.</div>
          <div class="choice-price">Gratis</div>
        </button>
      </div>
      <div id="installChoice" class="hidden">
        <h3 class="choice-subtitle">Wilt u de folie zelf aanbrengen of door GlassNext laten aanbrengen?</h3>
        <div class="choice-grid">
          <button class="btn choice-btn install-choice-btn" id="choiceInstallPro" type="button">
            <div class="choice-title">Folie laten aanbrengen door GlassNext</div>
            <div class="choice-desc">Wij verzorgen de professionele aanbreng van de GlassShield folie.</div>
          </button>
          <button class="btn secondary choice-btn install-choice-btn" id="choiceInstallSelf" type="button">
            <div class="choice-title">Folie zelf aanbrengen</div>
            <div class="choice-desc">U brengt de folie zelf aan. Wij leveren het snijplan en voorgesneden materiaal.</div>
          </button>
        </div>
      </div>
      <div id="choiceContinue" class="hidden">
        <button class="btn" id="choiceContinueBtn" type="button">Doorgaan</button>
      </div>
    </div>
  </article>
</div>
</section>

<section class="page" id="page-project">
<div class="grid">
  <article class="card span-12">
    <div class="card-head"><h2>Projectgegevens</h2><span class="pill">vul in voor offerte</span></div>
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
      <div class="measure-only hidden" id="measureDates" style="margin-top:16px">
        <h3 style="margin:0 0 10px">Voorkeursdatums voor inmeten</h3>
        <div class="fields three">
          <div><label>Voorkeursdatum 1</label><input id="prefDate1" type="date"></div>
          <div><label>Voorkeursdatum 2</label><input id="prefDate2" type="date"></div>
          <div><label>Voorkeursdatum 3</label><input id="prefDate3" type="date"></div>
        </div>
        <div class="fields three" style="margin-top:10px">
          <div><label>Dagsdeel 1</label><select id="prefDay1"><option value="">Geen voorkeur</option><option value="ochtend">Ochtend</option><option value="middag">Middag</option><option value="avond">Avond</option></select></div>
          <div><label>Dagsdeel 2</label><select id="prefDay2"><option value="">Geen voorkeur</option><option value="ochtend">Ochtend</option><option value="middag">Middag</option><option value="avond">Avond</option></select></div>
          <div><label>Dagsdeel 3</label><select id="prefDay3"><option value="">Geen voorkeur</option><option value="ochtend">Ochtend</option><option value="middag">Middag</option><option value="avond">Avond</option></select></div>
        </div>
        <div class="fields three" style="margin-top:10px">
          <div><label>Tijd 1 (optioneel)</label><input id="prefTime1" type="time"></div>
          <div><label>Tijd 2 (optioneel)</label><input id="prefTime2" type="time"></div>
          <div><label>Tijd 3 (optioneel)</label><input id="prefTime3" type="time"></div>
        </div>
        <p class="note" style="margin-top:8px">Wij plannen de inmeting op basis van uw voorkeursdatums. U ontvangt een bevestiging met de definitieve afspraak.</p>
      </div>
      <div style="margin-top:12px"><label>Projectomschrijving / situatie</label><textarea id="projectDescription" placeholder="Bijvoorbeeld: bestaande beglazing voorzien van Nano-EcoLine Climate GlassShield."></textarea></div>
      <div style="margin-top:12px"><label>Bijzonderheden</label><textarea id="projectNotes" placeholder="Bereikbaarheid, planning, glasconditie, werktijden, aandachtspunten..."></textarea></div>
      <div style="margin-top:16px">
        <label>Foto's van de ruiten (optioneel, maximaal 3)</label>
        <div id="photoUploadArea" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px">
          <div class="photo-slot" data-slot="0">
            <input type="file" id="photoFile0" accept="image/*" class="photo-input" style="display:none">
            <button type="button" class="btn secondary photo-pick-btn" data-slot="0" style="width:120px;height:120px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;border:2px dashed #94a3b8;background:#f7fafc">
              <span style="font-size:28px">+</span><span style="font-size:11px">Foto 1</span>
            </button>
            <div class="photo-preview" id="photoPreview0" style="display:none;width:120px;height:120px;border-radius:8px;overflow:hidden;position:relative">
              <img id="photoImg0" style="width:100%;height:100%;object-fit:cover">
              <button type="button" class="photo-remove-btn" data-slot="0" style="position:absolute;top:2px;right:2px;background:rgba(0,0,0,.6);color:#fff;border:0;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:14px;line-height:1">&times;</button>
            </div>
          </div>
          <div class="photo-slot" data-slot="1">
            <input type="file" id="photoFile1" accept="image/*" class="photo-input" style="display:none">
            <button type="button" class="btn secondary photo-pick-btn" data-slot="1" style="width:120px;height:120px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;border:2px dashed #94a3b8;background:#f7fafc">
              <span style="font-size:28px">+</span><span style="font-size:11px">Foto 2</span>
            </button>
            <div class="photo-preview" id="photoPreview1" style="display:none;width:120px;height:120px;border-radius:8px;overflow:hidden;position:relative">
              <img id="photoImg1" style="width:100%;height:100%;object-fit:cover">
              <button type="button" class="photo-remove-btn" data-slot="1" style="position:absolute;top:2px;right:2px;background:rgba(0,0,0,.6);color:#fff;border:0;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:14px;line-height:1">&times;</button>
            </div>
          </div>
          <div class="photo-slot" data-slot="2">
            <input type="file" id="photoFile2" accept="image/*" class="photo-input" style="display:none">
            <button type="button" class="btn secondary photo-pick-btn" data-slot="2" style="width:120px;height:120px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;border:2px dashed #94a3b8;background:#f7fafc">
              <span style="font-size:28px">+</span><span style="font-size:11px">Foto 3</span>
            </button>
            <div class="photo-preview" id="photoPreview2" style="display:none;width:120px;height:120px;border-radius:8px;overflow:hidden;position:relative">
              <img id="photoImg2" style="width:100%;height:100%;object-fit:cover">
              <button type="button" class="photo-remove-btn" data-slot="2" style="position:absolute;top:2px;right:2px;background:rgba(0,0,0,.6);color:#fff;border:0;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:14px;line-height:1">&times;</button>
            </div>
          </div>
        </div>
        <p class="note" style="margin-top:6px">Voeg eventueel foto's toe van de ruiten of situatie. Dit helpt ons bij de beoordeling en planning.</p>
      </div>
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
<div class="page-nav no-print">
  <button class="btn secondary nav-prev" type="button" data-target="roi">Vorige</button>
  <button class="btn nav-next" id="projectSubmit" type="button" data-target="offer">Volgende</button>
</div>
</section>

<section class="page" id="page-planner">
<div class="grid">
  <article class="card span-12 self-flow-only hidden" id="installModeCard" style="display:none">
    <input type="hidden" id="installModeValue" value="professional">
  </article>

  <article class="card span-12">
    <div class="card-head">
      <h2>Ramen invoeren</h2>
      <div class="toolbar no-print"><button class="btn secondary" id="demoPanes">Demo laden</button></div>
    </div>
    <div class="card-body">
      <div class="table-wrap scroll-hint pane-table-wrap">
        <table id="paneTable"><thead class="no-print"><tr>
          <th colspan="7">
            <div class="pane-header-grid">
              <span class="pane-header-cell">Kenmerk <span class="tooltip-icon" data-tooltip-col="kenmerk">&#9432;</span></span>
              <span class="pane-header-cell">Aantal <span class="tooltip-icon" data-tooltip-col="aantal">&#9432;</span></span>
              <span class="pane-header-cell">Breedte cm <span class="tooltip-icon" data-tooltip-col="breedte">&#9432;</span></span>
              <span class="pane-header-cell">Hoogte cm <span class="tooltip-icon" data-tooltip-col="hoogte">&#9432;</span></span>
              <span class="pane-header-cell">Rotatie <span class="tooltip-icon" data-tooltip-col="rotatie">&#9432;</span></span>
              <span class="pane-header-cell">Ruimte / verdieping <span class="tooltip-icon" data-tooltip-col="ruimte">&#9432;</span></span>
              <span class="pane-header-cell"></span>
            </div>
          </th>
        </tr></thead><tbody></tbody></table>
      </div>
      <div class="pane-add-row no-print">
        <button class="btn" id="addPane">+ Ruitmaat toevoegen</button>
      </div>
    </div>
  </article>

  <article class="card span-12 self-flow-only hidden">
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

  <article class="card span-12 self-flow-only hidden">
    <div class="card-head"><h2>Stukkenlijst</h2></div>
    <div class="card-body table-wrap scroll-hint" id="pieceReport">Nog geen snijplan berekend.</div>
  </article>
</div>
<div class="page-nav no-print">
  <button class="btn secondary nav-prev" type="button" data-target="choice">Vorige</button>
  <button class="btn nav-next" id="plannerNext" type="button" data-target="calc">Volgende</button>
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
<div class="page-nav no-print">
  <button class="btn secondary nav-prev" type="button" data-target="planner">Vorige</button>
  <button class="btn nav-next" type="button" data-target="roi">Volgende</button>
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
<div class="page-nav no-print">
  <button class="btn secondary nav-prev" type="button" data-target="calc">Vorige</button>
  <button class="btn nav-next" type="button" data-target="project">Volgende</button>
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
      <label style="display:flex;align-items:flex-start;gap:10px;margin-bottom:14px;cursor:pointer;font-size:13px;line-height:1.5">
        <input type="checkbox" id="offerConsent" style="width:auto;margin-top:3px">
        <span>Ik begrijp dat deze offerte een scenarioanalyse is afhankelijk van de werkelijke glasopbouw, gebouwcondities, installaties, energieprijzen en het gebruik. Aan de uitkomst kunnen geen gegarandeerde besparingen worden ontleend. Deze offerte is gebaseerd op de ingevoerde ruitmaten en het berekende snijplan. Definitieve maatvoering en geschiktheid van de beglazing worden vóór uitvoering gecontroleerd.</span>
      </label>
      <div id="submitStatus" class="status" style="margin-top:12px"></div>
    </div>
  </article>
</div>
<div class="page-nav no-print">
  <button class="btn secondary nav-prev" type="button" data-target="project">Vorige</button>
  <button class="btn nav-next" id="submitOffer" type="button" data-target="workorder" disabled>Offerte aanvragen</button>
</div>
</section>

<section class="page hidden" id="page-workorder">
<div class="grid">
  <article class="card span-12">
    <div class="card-head"><h2>Werkbon</h2><div class="toolbar no-print"><button class="btn" id="refreshWorkorder">Werkbon vernieuwen</button></div></div>
    <div class="card-body"><div id="workDoc" class="doc"></div></div>
  </article>
</div>
<div class="page-nav no-print">
  <button class="btn secondary nav-prev" type="button" data-target="offer">Vorige</button>
  <button class="btn nav-next" type="button" data-target="exports">Volgende</button>
</div>
</section>

<section class="page hidden" id="page-exports">
<div class="grid">
  <article class="card span-12"><div class="card-head"><h2>Controleoverzicht</h2></div><div class="card-body" id="exportSummary"></div></article>
</div>
<div class="page-nav no-print">
  <button class="btn secondary nav-prev" type="button" data-target="workorder">Vorige</button>
  <button class="btn nav-next" type="button" disabled>Volgende</button>
</div>
</section>
</main>

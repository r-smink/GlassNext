<?php if (!defined('ABSPATH')) exit; $gnd_user = wp_get_current_user(); ?>
<div id="gnd-app">
  <header class="gnd-top">
    <div>
      <div class="gnd-brand">GlassNext Dealer Suite</div>
      <div class="gnd-tag">Ruitmaten importeren → snijplan → offerte in Odoo</div>
    </div>
    <div class="gnd-user">
      <span><?php echo esc_html($gnd_user->display_name); ?></span>
      <a class="gnd-btn gnd-btn-light gnd-small" href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>">Uitloggen</a>
    </div>
  </header>

  <!-- 1. Klant -->
  <section class="gnd-card">
    <div class="gnd-card-head"><h2><span class="gnd-step">1</span> Klant en project</h2></div>
    <div class="gnd-card-body">
      <div class="gnd-fields">
        <div><label for="gndCustomerName">Klant / organisatie <b>*</b></label><input id="gndCustomerName" autocomplete="off"></div>
        <div><label for="gndContactName">Contactpersoon</label><input id="gndContactName" autocomplete="off"></div>
        <div><label for="gndEmail">E-mailadres klant</label><input id="gndEmail" type="email" autocomplete="off"></div>
        <div><label for="gndPhone">Telefoon</label><input id="gndPhone" autocomplete="off"></div>
        <div><label for="gndAddress">Projectadres</label><input id="gndAddress" autocomplete="off"></div>
        <div><label for="gndCity">Postcode en plaats</label><input id="gndCity" autocomplete="off"></div>
        <div><label for="gndReference">Eigen referentie / projectnaam</label><input id="gndReference" placeholder="bijv. inmeting 12-11 Jansen" autocomplete="off"></div>
        <div><label for="gndInstallMode">Aanbrengen</label>
          <select id="gndInstallMode"><option value="professional">Laten aanbrengen door GlassNext</option><option value="self">Zelf aanbrengen (alleen levering)</option></select></div>
        <div class="gnd-mount-only"><label for="gndMountClass">Montageklasse</label>
          <select id="gndMountClass"><option value="easy">Eenvoudig</option><option value="average">Gemiddeld</option><option value="complex">Complex</option><option value="very">Zeer complex</option></select></div>
      </div>
      <div class="gnd-fields gnd-fields-1">
        <div><label for="gndDescription">Projectomschrijving</label><textarea id="gndDescription" rows="2"></textarea></div>
        <div><label for="gndNotes">Bijzonderheden (bereikbaarheid, glasconditie, planning…)</label><textarea id="gndNotes" rows="2"></textarea></div>
      </div>
    </div>
  </section>

  <!-- 2. Ruitmaten -->
  <section class="gnd-card">
    <div class="gnd-card-head"><h2><span class="gnd-step">2</span> Ruitmaten</h2>
      <div class="gnd-toolbar">
        <button type="button" class="gnd-btn gnd-btn-ghost" id="gndAddPane">+ Ruitmaat</button>
        <button type="button" class="gnd-btn gnd-btn-light" id="gndTemplateCsv">CSV-sjabloon</button>
        <button type="button" class="gnd-btn gnd-btn-light" id="gndTemplateXlsx">Excel-sjabloon</button>
      </div>
    </div>
    <div class="gnd-card-body">
      <input type="file" id="gndImportFile" accept=".csv,.xlsx" hidden>
      <div id="gndDrop" class="gnd-drop" tabindex="0" role="button" aria-label="Sleep een CSV- of Excel-bestand hierheen of klik om te kiezen">
        <strong>Sleep je CSV- of Excel-bestand hierheen</strong> of klik om een bestand te kiezen.
        <div class="gnd-note">Kolommen: Kenmerk, Breedte cm, Hoogte cm, Aantal, Rotatie (Ja/Nee), Ruimte / verdieping. Alleen het eerste werkblad wordt gelezen.</div>
      </div>
      <div id="gndImportStatus" class="gnd-status" aria-live="polite"></div>
      <div class="gnd-table-wrap">
        <table class="gnd-table" id="gndPaneTable">
          <thead><tr><th>Kenmerk</th><th>Breedte cm</th><th>Hoogte cm</th><th>Aantal</th><th>Rotatie</th><th>Ruimte / verdieping</th><th></th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="gnd-note" id="gndPaneSummary"></div>
    </div>
  </section>

  <!-- 3. Snijplan -->
  <section class="gnd-card">
    <div class="gnd-card-head"><h2><span class="gnd-step">3</span> Snijplan en calculatie</h2>
      <div class="gnd-toolbar"><button type="button" class="gnd-btn" id="gndCalculate">Bereken snijplan</button></div>
    </div>
    <div class="gnd-card-body">
      <div id="gndPlanStatus" class="gnd-status">Importeer of voer ruitmaten in en bereken het snijplan.</div>
      <div class="gnd-progress"><div id="gndProgress"></div></div>
      <div id="gndPlanMetrics" class="gnd-metrics"></div>
      <div id="gndPlanArea" class="gnd-hidden">
        <div class="gnd-canvas-wrap"><canvas id="gndCanvas" width="1000" height="300"></canvas></div>
        <div class="gnd-split">
          <div>
            <h3>Indicatieve calculatie</h3>
            <table class="gnd-table gnd-money"><tbody id="gndCalcRows"></tbody></table>
            <p class="gnd-note">Indicatief op basis van de tarieven in de GlassNext Suite. In Odoo gelden de verkoopprijzen van de producten.</p>
          </div>
          <div>
            <h3>Overige kosten</h3>
            <div id="gndOtherCosts"></div>
            <button type="button" class="gnd-btn gnd-btn-ghost gnd-small" id="gndAddCost">+ Kostenpost</button>
            <p class="gnd-note">Bijv. steigerhuur, hoogwerker of parkeerkosten. Komen als losse regel op de Odoo-offerte.</p>
          </div>
        </div>
        <details class="gnd-details"><summary>Stukkenlijst</summary><div id="gndPieces" class="gnd-table-wrap"></div></details>
      </div>
    </div>
  </section>

  <!-- 4. Versturen -->
  <section class="gnd-card">
    <div class="gnd-card-head"><h2><span class="gnd-step">4</span> Offerte naar Odoo</h2></div>
    <div class="gnd-card-body">
      <p class="gnd-note">Er wordt een verkoopofferte (concept) in Odoo aangemaakt met de orderregels, de ruitmaten in de chatter en als bijlagen het importbestand, het snijplan (PDF) en de snijplan-CSV.</p>
      <div class="gnd-toolbar">
        <button type="button" class="gnd-btn gnd-btn-big" id="gndSubmit">Verstuur offerte naar Odoo</button>
        <button type="button" class="gnd-btn gnd-btn-light" id="gndReset">Nieuwe offerte</button>
      </div>
      <div id="gndSubmitStatus" class="gnd-status" aria-live="polite"></div>
    </div>
  </section>

  <!-- Overzicht -->
  <section class="gnd-card">
    <div class="gnd-card-head"><h2><?php echo current_user_can('manage_options') ? 'Dealerofferte’s' : 'Mijn offertes'; ?></h2>
      <div class="gnd-toolbar"><button type="button" class="gnd-btn gnd-btn-light gnd-small" id="gndRefreshList">Vernieuwen</button></div>
    </div>
    <div class="gnd-card-body gnd-table-wrap" id="gndList"><span class="gnd-note">Laden…</span></div>
  </section>
</div>

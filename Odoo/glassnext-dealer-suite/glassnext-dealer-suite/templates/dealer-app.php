<?php
if (!defined('ABSPATH')) exit;
$gnd_user  = wp_get_current_user();
$gnd_mode  = isset($gnd_mode) ? $gnd_mode : 'shortcode';
$gnd_admin = current_user_can('manage_options');
?>
<div id="gnd-app" class="gnd-mode-<?php echo esc_attr($gnd_mode); ?>" data-view="new" data-step="1">
  <header class="gnd-top">
    <div class="gnd-top-brand">
      <span class="gnd-logo" aria-hidden="true">GN</span>
      <div><div class="gnd-brand">GlassNext Dealer</div><div class="gnd-tag" id="gndTopTitle">Nieuwe offerte</div></div>
    </div>
    <nav class="gnd-tabs" aria-label="Hoofdmenu">
      <button type="button" class="gnd-tab is-active" data-tab="new"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>Nieuw</span></button>
      <button type="button" class="gnd-tab" data-tab="list"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg><span>Offertes</span></button>
      <button type="button" class="gnd-tab" data-tab="account"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg><span>Account</span></button>
    </nav>
  </header>

  <div class="gnd-offline gnd-hidden" id="gndOffline" role="status">Geen verbinding. Je invoer wordt op dit toestel bewaard; versturen kan zodra je weer online bent.</div>
  <div class="gnd-toast gnd-hidden" id="gndToast" role="status" aria-live="polite"></div>

  <main class="gnd-main">
    <!-- ================= NIEUW ================= -->
    <section class="gnd-view" data-view-pane="new">
      <ol class="gnd-steps" aria-label="Stappen">
        <li><button type="button" data-goto="1" class="is-active"><b>1</b><span>Klant</span></button></li>
        <li><button type="button" data-goto="2"><b>2</b><span>Maten</span></button></li>
        <li><button type="button" data-goto="3"><b>3</b><span>Snijplan</span></button></li>
        <li><button type="button" data-goto="4"><b>4</b><span>Versturen</span></button></li>
      </ol>

      <!-- Stap 1: klant -->
      <div class="gnd-step-pane" data-step-pane="1">
        <div class="gnd-card"><div class="gnd-card-body">
          <h2>Klant en project</h2>
          <div class="gnd-fields">
            <div class="gnd-f-wide"><label for="gndCustomerName">Klant / organisatie <b>*</b></label><input id="gndCustomerName" autocomplete="off" enterkeyhint="next"></div>
            <div><label for="gndContactName">Contactpersoon</label><input id="gndContactName" autocomplete="off" enterkeyhint="next"></div>
            <div><label for="gndPhone">Telefoon</label><input id="gndPhone" type="tel" inputmode="tel" autocomplete="off" enterkeyhint="next"></div>
            <div class="gnd-f-wide"><label for="gndEmail">E-mailadres klant</label><input id="gndEmail" type="email" inputmode="email" autocapitalize="none" autocomplete="off" enterkeyhint="next"></div>
            <div><label for="gndAddress">Projectadres</label><input id="gndAddress" autocomplete="off" enterkeyhint="next"></div>
            <div><label for="gndCity">Postcode en plaats</label><input id="gndCity" autocomplete="off" enterkeyhint="next"></div>
            <div class="gnd-f-wide"><label for="gndReference">Eigen referentie / projectnaam</label><input id="gndReference" placeholder="bijv. inmeting 12-11 Jansen" autocomplete="off" enterkeyhint="next"></div>
            <div><label for="gndInstallMode">Aanbrengen</label>
              <select id="gndInstallMode"><option value="professional">Door GlassNext</option><option value="self">Zelf (alleen levering)</option></select></div>
            <div class="gnd-mount-only"><label for="gndMountClass">Montageklasse</label>
              <select id="gndMountClass"><option value="easy">Eenvoudig</option><option value="average">Gemiddeld</option><option value="complex">Complex</option><option value="very">Zeer complex</option></select></div>
            <div class="gnd-f-wide"><label for="gndDescription">Projectomschrijving</label><textarea id="gndDescription" rows="2"></textarea></div>
            <div class="gnd-f-wide"><label for="gndNotes">Bijzonderheden (bereikbaarheid, glasconditie, planning…)</label><textarea id="gndNotes" rows="2"></textarea></div>
          </div>
        </div></div>
      </div>

      <!-- Stap 2: maten -->
      <div class="gnd-step-pane" data-step-pane="2">
        <div class="gnd-card gnd-quick"><div class="gnd-card-body">
          <h2>Ruit toevoegen</h2>
          <form id="gndQuick" class="gnd-quick-grid" novalidate autocomplete="off">
            <div class="gnd-q-id"><label for="gndQId">Kenmerk</label><input id="gndQId" enterkeyhint="next"></div>
            <div class="gnd-q-w"><label for="gndQW">Breedte <small>cm</small></label><input id="gndQW" inputmode="decimal" enterkeyhint="next"></div>
            <div class="gnd-q-h"><label for="gndQH">Hoogte <small>cm</small></label><input id="gndQH" inputmode="decimal" enterkeyhint="done"></div>
            <div class="gnd-q-n"><label for="gndQN">Aantal</label>
              <div class="gnd-stepper"><button type="button" data-q-step="-1" aria-label="Minder">−</button><input id="gndQN" inputmode="numeric" value="1"><button type="button" data-q-step="1" aria-label="Meer">+</button></div></div>
            <div class="gnd-q-rot"><label for="gndQRot">Rotatie</label><select id="gndQRot"><option value="1">Ja</option><option value="0">Nee</option></select></div>
            <div class="gnd-q-room"><label for="gndQRoom">Ruimte / verdieping</label><input id="gndQRoom" list="gndRoomList" enterkeyhint="done"><datalist id="gndRoomList"></datalist></div>
            <div class="gnd-q-btn"><button type="submit" class="gnd-btn gnd-btn-block">+ Toevoegen &amp; volgende</button></div>
          </form>
          <div id="gndQuickStatus" class="gnd-status" aria-live="polite"></div>
        </div></div>

        <div class="gnd-card"><div class="gnd-card-body">
          <div class="gnd-row-head">
            <h2>Ruiten <span class="gnd-count" id="gndPaneCount">0</span></h2>
            <button type="button" class="gnd-btn gnd-btn-ghost gnd-small" id="gndAddPane">+ Lege regel</button>
          </div>
          <div class="gnd-note" id="gndPaneSummary"></div>
          <div class="gnd-empty" id="gndPaneEmpty">Nog geen ruiten. Voeg ze hierboven toe of importeer een Excel/CSV-bestand.</div>
          <div class="gnd-table-wrap gnd-panes-wrap">
            <table class="gnd-table" id="gndPaneTable">
              <thead><tr><th>Kenmerk</th><th>Breedte cm</th><th>Hoogte cm</th><th>Aantal</th><th>Rotatie</th><th>Ruimte / verdieping</th><th></th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div></div>

        <details class="gnd-card gnd-import" id="gndImportBox">
          <summary>Importeren uit Excel of CSV</summary>
          <div class="gnd-card-body">
            <input type="file" id="gndImportFile" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" hidden>
            <div id="gndDrop" class="gnd-drop" tabindex="0" role="button" aria-label="Kies of sleep een CSV- of Excel-bestand">
              <strong>Bestand kiezen</strong><span class="gnd-hide-touch"> of hierheen slepen</span>
              <div class="gnd-note">Kolommen: Kenmerk, Breedte cm, Hoogte cm, Aantal, Rotatie (Ja/Nee), Ruimte / verdieping. Eerste werkblad.</div>
            </div>
            <div class="gnd-toolbar">
              <button type="button" class="gnd-btn gnd-btn-light gnd-small" id="gndTemplateXlsx">Excel-sjabloon</button>
              <button type="button" class="gnd-btn gnd-btn-light gnd-small" id="gndTemplateCsv">CSV-sjabloon</button>
            </div>
          </div>
        </details>
        <div id="gndImportStatus" class="gnd-status" aria-live="polite"></div>
      </div>

      <!-- Stap 3: snijplan -->
      <div class="gnd-step-pane" data-step-pane="3">
        <div class="gnd-card"><div class="gnd-card-body">
          <div class="gnd-row-head"><h2>Snijplan</h2><button type="button" class="gnd-btn gnd-btn-light gnd-small" id="gndCalculate">Opnieuw berekenen</button></div>
          <div id="gndPlanStatus" class="gnd-status">Het snijplan wordt berekend zodra je deze stap opent.</div>
          <div class="gnd-progress"><div id="gndProgress"></div></div>
          <div id="gndPlanMetrics" class="gnd-metrics"></div>
          <div id="gndPlanArea" class="gnd-hidden">
            <details class="gnd-details" id="gndCanvasBox"><summary>Snijplan tekening</summary>
              <div class="gnd-canvas-wrap"><canvas id="gndCanvas" width="1000" height="300"></canvas></div>
            </details>
            <details class="gnd-details"><summary>Stukkenlijst</summary><div id="gndPieces" class="gnd-table-wrap"></div></details>
          </div>
        </div></div>
        <div class="gnd-card gnd-calc-card gnd-hidden" id="gndCalcCard"><div class="gnd-card-body">
          <h2>Indicatieve calculatie</h2>
          <table class="gnd-table gnd-money"><tbody id="gndCalcRows"></tbody></table>
          <p class="gnd-note">Indicatief op basis van de tarieven in de GlassNext Suite. In Odoo gelden de verkoopprijzen van de producten.</p>
          <h3>Overige kosten</h3>
          <div id="gndOtherCosts"></div>
          <button type="button" class="gnd-btn gnd-btn-ghost gnd-small" id="gndAddCost">+ Kostenpost</button>
          <p class="gnd-note">Bijv. steigerhuur, hoogwerker of parkeerkosten. Komen als losse regel op de Odoo-offerte.</p>
        </div></div>
      </div>

      <!-- Stap 4: versturen -->
      <div class="gnd-step-pane" data-step-pane="4">
        <div class="gnd-card"><div class="gnd-card-body">
          <h2>Controleren en versturen</h2>
          <div id="gndSummary" class="gnd-summary"></div>
          <p class="gnd-note">Er wordt een verkoopofferte (concept) in Odoo aangemaakt met de orderregels, de ruitmaten in de chatter en als bijlagen het snijplan (PDF), de snijplan-CSV en een eventueel importbestand.</p>
          <button type="button" class="gnd-btn gnd-btn-big gnd-btn-block" id="gndSubmit">Verstuur offerte naar GlassNext</button>
          <div id="gndSubmitStatus" class="gnd-status" aria-live="polite"></div>
          <div id="gndDone" class="gnd-done gnd-hidden">
            <button type="button" class="gnd-btn gnd-btn-block" id="gndNewAfterSubmit">Nieuwe offerte</button>
            <button type="button" class="gnd-btn gnd-btn-light gnd-btn-block" data-tab="list">Naar mijn offertes</button>
          </div>
        </div></div>
      </div>

      <div class="gnd-actionbar">
        <button type="button" class="gnd-btn gnd-btn-light" id="gndPrev">Vorige</button>
        <button type="button" class="gnd-btn gnd-btn-light gnd-small gnd-reset" id="gndReset" title="Nieuwe offerte (invoer wissen)">Wissen</button>
        <button type="button" class="gnd-btn" id="gndNext">Volgende</button>
      </div>
    </section>

    <!-- ================= OFFERTES ================= -->
    <section class="gnd-view" data-view-pane="list" hidden>
      <div class="gnd-row-head gnd-pad"><h2><?php echo $gnd_admin ? 'Dealerofferte’s' : 'Mijn offertes'; ?></h2>
        <button type="button" class="gnd-btn gnd-btn-light gnd-small" id="gndRefreshList">Vernieuwen</button></div>
      <div id="gndList" class="gnd-list"><span class="gnd-note">Laden…</span></div>
    </section>

    <!-- ================= ACCOUNT ================= -->
    <section class="gnd-view" data-view-pane="account" hidden>
      <div class="gnd-card"><div class="gnd-card-body">
        <h2><?php echo esc_html($gnd_user->display_name); ?></h2>
        <p class="gnd-note"><?php echo esc_html($gnd_user->user_email); ?><?php echo $gnd_admin ? ' · beheerder' : ' · dealer'; ?></p>
        <a class="gnd-btn gnd-btn-light gnd-btn-block" id="gndLogout" href="<?php echo esc_url(wp_logout_url($gnd_mode === 'app' ? GlassNext_Dealer_Suite::app_url() : get_permalink())); ?>">Uitloggen</a>
      </div></div>
      <div class="gnd-card" id="gndInstallCard"><div class="gnd-card-body">
        <h2>App installeren</h2>
        <div id="gndInstallState">
          <p class="gnd-note" id="gndInstallDone" hidden>De app is geïnstalleerd en draait als app.</p>
          <div id="gndInstallAndroid" hidden>
            <p>Installeer GlassNext Dealer op je beginscherm voor snelle toegang op locatie.</p>
            <button type="button" class="gnd-btn gnd-btn-block" id="gndInstallBtn">App installeren</button>
          </div>
          <div id="gndInstallIos" hidden>
            <ol class="gnd-howto"><li>Open deze pagina in <b>Safari</b>.</li><li>Tik op <b>Deel</b> <span aria-hidden="true">(□↑)</span>.</li><li>Kies <b>Zet op beginscherm</b> en tik op <b>Voeg toe</b>.</li></ol>
          </div>
          <div id="gndInstallOther">
            <p class="gnd-note">Open <a href="<?php echo esc_url(GlassNext_Dealer_Suite::app_url()); ?>"><?php echo esc_html(preg_replace('#^https?://#', '', GlassNext_Dealer_Suite::app_url())); ?></a> op je telefoon. Android (Chrome): menu ⋮ → <b>App installeren</b>. iPhone (Safari): Deel → <b>Zet op beginscherm</b>.</p>
          </div>
        </div>
      </div></div>
      <p class="gnd-note gnd-pad">Versie <?php echo esc_html(GND_VERSION); ?></p>
    </section>
  </main>
</div>

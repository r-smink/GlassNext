/**
 * GlassNext Nieuwsbrief — frontend submit + reCAPTCHA afhandeling.
 * Werkt met meerdere widget-instanties op één pagina.
 */
(function () {
  'use strict';

  var cfg = window.GN_NEWSLETTER || {};
  var v2WidgetIds = new WeakMap();

  // Callback voor v2 expliciete rendering (api.js?onload=gnRecaptchaOnload)
  window.gnRecaptchaOnload = function () {
    if (cfg.version !== 'v2_checkbox' && cfg.version !== 'v2_invisible') return;
    document.querySelectorAll('form.gn-newsletter__form').forEach(function (form) {
      renderV2(form);
    });
  };

  function renderV2(form) {
    if (typeof grecaptcha === 'undefined' || !grecaptcha.render) return;
    if (v2WidgetIds.has(form)) return;

    if (cfg.version === 'v2_checkbox') {
      var holder = form.querySelector('.g-recaptcha');
      if (holder) {
        v2WidgetIds.set(form, grecaptcha.render(holder, { sitekey: cfg.siteKey }));
      }
    } else if (cfg.version === 'v2_invisible') {
      var badge = document.createElement('div');
      form.appendChild(badge);
      v2WidgetIds.set(form, grecaptcha.render(badge, {
        sitekey: cfg.siteKey,
        size: 'invisible',
        callback: function (token) { submitForm(form, token); }
      }));
    }
  }

  function getToken(form) {
    return new Promise(function (resolve, reject) {
      switch (cfg.version) {
        case 'v3':
          if (typeof grecaptcha === 'undefined') return reject(new Error('recaptcha-not-loaded'));
          grecaptcha.ready(function () {
            grecaptcha.execute(cfg.siteKey, { action: cfg.action })
              .then(resolve)
              .catch(function () { reject(new Error('recaptcha-execute-failed')); });
          });
          break;

        case 'v2_checkbox':
          var widgetId = v2WidgetIds.get(form);
          if (widgetId === undefined) return reject(new Error('recaptcha-not-rendered'));
          var response = grecaptcha.getResponse(widgetId);
          if (!response) return reject(new Error('recaptcha-empty'));
          resolve(response);
          break;

        case 'v2_invisible':
          var invId = v2WidgetIds.get(form);
          if (invId === undefined) return reject(new Error('recaptcha-not-rendered'));
          grecaptcha.execute(invId); // callback in renderV2 roept submitForm aan
          resolve(null);             // token komt asynchroon via de callback
          break;

        default:
          resolve('');
      }
    });
  }

  function showMessage(form, text, ok) {
    var box = form.querySelector('.gn-newsletter__message');
    if (!box) return;
    box.textContent = text;
    box.classList.remove('is-success', 'is-error');
    box.classList.add(ok ? 'is-success' : 'is-error');
  }

  function submitForm(form, token) {
    var email = form.querySelector('[name="gn_email"]');
    var name = form.querySelector('[name="gn_name"]');
    var consent = form.querySelector('[name="gn_consent"]');
    var consentText = form.querySelector('.gn-newsletter__consent-text');
    var msgBox = form.querySelector('.gn-newsletter__message');
    var button = form.querySelector('.gn-newsletter__button');

    var body = new URLSearchParams();
    body.set('action', 'gn_newsletter_subscribe');
    body.set('nonce', cfg.nonce || '');
    body.set('gn_email', email ? email.value.trim() : '');
    body.set('gn_name', name ? name.value.trim() : '');
    body.set('gn_consent', consent && consent.checked ? '1' : '0');
    body.set('gn_consent_text', consentText ? consentText.textContent.trim() : '');
    body.set('gn_list_ids', form.getAttribute('data-list-ids') || '');
    body.set('gn_page_url', window.location.href);
    if (token) body.set('gn_recaptcha_token', token);

    if (button) { button.disabled = true; button.classList.add('is-loading'); }

    fetch(cfg.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.success) {
          showMessage(form, (data.data && data.data.message) || (msgBox && msgBox.getAttribute('data-success')) || 'Ingeschreven!', true);
          form.reset();
        } else {
          showMessage(form, (data && data.data && data.data.message) || (msgBox && msgBox.getAttribute('data-error')) || 'Er ging iets mis.', false);
        }
      })
      .catch(function () {
        showMessage(form, (msgBox && msgBox.getAttribute('data-error')) || 'Er ging iets mis.', false);
      })
      .finally(function () {
        if (button) { button.disabled = false; button.classList.remove('is-loading'); }
      });
  }

  function onSubmit(e) {
    e.preventDefault();
    var form = e.currentTarget;
    var msgBox = form.querySelector('.gn-newsletter__message');
    var email = form.querySelector('[name="gn_email"]');
    var consent = form.querySelector('[name="gn_consent"]');
    var name = form.querySelector('[name="gn_name"]');

    // Client-side validatie
    if (name && name.required && !name.value.trim()) {
      showMessage(form, 'Vul je naam in.', false);
      name.focus();
      return;
    }
    if (!email || !email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      showMessage(form, 'Vul een geldig e-mailadres in.', false);
      if (email) email.focus();
      return;
    }
    if (!consent || !consent.checked) {
      showMessage(form, 'Vink de toestemming aan om je in te schrijven.', false);
      return;
    }

    getToken(form)
      .then(function (token) {
        // v2_invisible: token === null → submitForm wordt door de callback aangeroepen
        if (token === null) return;
        submitForm(form, token);
      })
      .catch(function (err) {
        var msg = (err && err.message === 'recaptcha-empty')
          ? 'Vink de reCAPTCHA aan om door te gaan.'
          : 'reCAPTCHA kon niet geladen worden. Vernieuw de pagina en probeer opnieuw.';
        showMessage(form, msg, false);
      });
  }

  function init() {
    document.querySelectorAll('form.gn-newsletter__form').forEach(function (form) {
      if (form.dataset.gnInit) return;
      form.dataset.gnInit = '1';
      form.addEventListener('submit', onSubmit);
      // Als grecaptcha al geladen is (api.js eerder dan DOMContentLoaded), render nu
      if (typeof grecaptcha !== 'undefined' && grecaptcha.render) renderV2(form);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Elementor-editor: init opnieuw als de preview wordt herladen
  if (window.elementorFrontend && window.elementorFrontend.hooks) {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/gn_newsletter_signup.default', function (scope) {
      scope.find('form.gn-newsletter__form').each(function () {
        if (!this.dataset.gnInit) {
          this.dataset.gnInit = '1';
          this.addEventListener('submit', onSubmit);
        }
      });
    });
  }
})();

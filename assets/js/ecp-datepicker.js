/* =====================================================================
 * assets/js/ecp-datepicker.js — Indian date format in date boxes.
 *
 *  - Phones / tablets (touch screen): NOTHING changes. They keep the phone's
 *    own date wheel, which already shows the phone's language format.
 *  - Desktop / laptop: every <input type="date"> (and datetime-local) gets a
 *    picker that SHOWS 06-10-2026 (placeholder "dd-mm-yyyy"). Typing works
 *    too: 06-10-2026, 06/10/2026 and 06.10.2026 are all understood.
 *  - The value the form SENDS is unchanged (2026-10-06 / 2026-10-06T15:45),
 *    so no PHP, API or database code changes.
 *
 * Loaded on every page by: partials/footer.php (public site),
 * app/views/layouts/base.php (doctor panel), app/views/admin/_nav.php (admin),
 * app/views/store_vendor/_layout.php (seller portal).
 * The panel and admin load it from https://eclinicpro.com/assets/js/.
 *
 * Works with Alpine: x-model / :value / :min / :max / @change keep working,
 * and boxes added later (x-if, x-for, modals) are picked up automatically.
 * Opt out for one box: <input type="date" data-native-date>.
 * Library: flatpickr 4.6.13 (cdnjs), loaded only when a page has a date box.
 * ===================================================================== */
(function () {
  'use strict';
  if (window.__ecpDatepicker) return;
  window.__ecpDatepicker = true;

  // Touch-first devices keep their native picker.
  if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return;

  var CDN = 'https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/';
  var SELECTOR = 'input[type="date"]:not([data-native-date]), input[type="datetime-local"]:not([data-native-date])';
  var DATE_ALT = 'd-m-Y';
  var TIME_ALT = 'd-m-Y G:i K';
  var valueProp = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
  var loading = null;

  function load() {
    if (window.flatpickr) return Promise.resolve();
    if (loading) return loading;
    loading = new Promise(function (resolve, reject) {
      var css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = CDN + 'flatpickr.min.css';
      document.head.appendChild(css);
      var style = document.createElement('style');
      style.textContent =
        '.flatpickr-day.selected,.flatpickr-day.selected:hover,.flatpickr-day.startRange,.flatpickr-day.endRange' +
        '{background:#0F9B6E;border-color:#0F9B6E}' +
        '.flatpickr-day.today{border-color:#0F9B6E}' +
        '.flatpickr-calendar{font-family:inherit}' +
        'input.ecp-dp-alt[readonly]{background-color:inherit}';
      document.head.appendChild(style);
      var js = document.createElement('script');
      js.src = CDN + 'flatpickr.min.js';
      js.onload = function () { resolve(); };
      js.onerror = function () { loading = null; reject(new Error('flatpickr failed to load')); };
      document.head.appendChild(js);
    });
    return loading;
  }

  // A DB value ("2026-10-06 00:00:00") given to a date box: keep what the box can hold.
  function normalize(v, withTime) {
    var m = /^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}))?/.exec(String(v || ''));
    if (!m) return v;
    return withTime ? m[1] + 'T' + (m[2] || '00:00') : m[1];
  }

  function enhance(input) {
    if (input._ecpDp || input.closest('.flatpickr-calendar')) return;
    var withTime = input.type === 'datetime-local';
    var attrVal = input.getAttribute('value');
    if (attrVal) valueProp.set.call(input, normalize(attrVal, withTime));
    var altFormat = withTime ? TIME_ALT : DATE_ALT;
    var syncing = false;

    var fp = window.flatpickr(input, {
      dateFormat: withTime ? 'Y-m-d\\TH:i' : 'Y-m-d',
      altInput: true,
      altFormat: altFormat,
      altInputClass: (input.className || '') + ' ecp-dp-alt',
      allowInput: true,
      enableTime: withTime,
      time_24hr: false,
      minDate: input.min || null,
      maxDate: input.max || null,
      disableMobile: true,
      // Accept 06/10/2026 and 06.10.2026 as well as 06-10-2026 when typed.
      parseDate: function (str, format) {
        var s = String(str).trim();
        if (format === DATE_ALT) s = s.replace(/[\/.\s]+/g, '-');
        return window.flatpickr.parseDate(s, format);
      },
      onChange: function () {
        // flatpickr fires "change" BEFORE "input"; a browser fires input first.
        // Send input now so Alpine's x-model holds the new date before any
        // @change handler (e.g. loadSlots) runs.
        input.dispatchEvent(new Event('input', { bubbles: true }));
      },
    });
    if (!fp || !fp.altInput) return;
    input._ecpDp = fp;

    var alt = fp.altInput;
    alt.placeholder = input.placeholder || (withTime ? 'dd-mm-yyyy hh:mm AM' : 'dd-mm-yyyy');
    alt.title = alt.title || (withTime ? 'Date and time (dd-mm-yyyy)' : 'Date (dd-mm-yyyy)');
    alt.disabled = input.disabled;
    alt.required = input.required;
    if (input.id) {           // keep <label for="…"> working
      alt.id = input.id;
      input.removeAttribute('id');
      input.dataset.ecpDpId = alt.id;
    }

    // Code that sets the value directly (Alpine x-model / :value, scripts)
    // must update the visible box too.
    Object.defineProperty(input, 'value', {
      configurable: true,
      get: function () { return valueProp.get.call(this); },
      set: function (v) {
        v = normalize(v, withTime);
        valueProp.set.call(this, v);
        if (syncing) return;
        var cur = fp.selectedDates.length ? fp.formatDate(fp.selectedDates[0], fp.config.dateFormat) : '';
        if (String(v || '') === cur) return;   // flatpickr writing its own value
        syncing = true;
        try { fp.setDate(v || null, false); } finally { syncing = false; }
      },
    });
    var setDate = fp.setDate;
    fp.setDate = function () {
      var was = syncing;
      syncing = true;
      try { return setDate.apply(fp, arguments); } finally { syncing = was; }
    };

    // :min / :max / :disabled / :required bindings that change later.
    new MutationObserver(function () {
      fp.set('minDate', input.getAttribute('min') || null);
      fp.set('maxDate', input.getAttribute('max') || null);
      alt.disabled = input.disabled;
      alt.required = input.required;
    }).observe(input, { attributes: true, attributeFilter: ['min', 'max', 'disabled', 'required'] });
  }

  function scan(root) {
    var list = [];
    if (root.matches && root.matches(SELECTOR)) list.push(root);
    if (root.querySelectorAll) list = list.concat(Array.prototype.slice.call(root.querySelectorAll(SELECTOR)));
    if (!list.length) return;
    load().then(function () { list.forEach(enhance); }).catch(function (e) {
      // CDN blocked: the browser's own date box still works.
      if (window.console) console.warn('[ecp-datepicker]', e.message);
    });
  }

  function start() {
    scan(document);
    new MutationObserver(function (muts) {
      muts.forEach(function (m) {
        Array.prototype.forEach.call(m.addedNodes, function (n) { if (n.nodeType === 1) scan(n); });
      });
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();

/**
 * RAPID front-end helpers
 */
(function () {
  'use strict';

  var PRIMARY = '#091C39';

  if (window.__RAPID_FLASH__ && window.Swal) {
    var flash = window.__RAPID_FLASH__;
    var iconMap = {
      success: 'success',
      error: 'error',
      warning: 'warning',
      info: 'info',
      danger: 'error'
    };
    Swal.fire({
      icon: iconMap[flash.type] || 'info',
      title: flash.type === 'success' ? 'Success' : (flash.type === 'error' || flash.type === 'danger' ? 'Error' : 'Notice'),
      text: flash.message,
      confirmButtonColor: PRIMARY
    });
  }

  document.querySelectorAll('form[data-disable-on-submit]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('[type="submit"]:not([hidden])') || form.querySelector('[type="submit"]');
      if (btn && !btn.disabled) {
        btn.disabled = true;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="rapid-spinner mr-1" role="status" aria-hidden="true"></span> Please wait…';
      }
    });
  });

  /* ----- Dropdowns ----- */
  function closeAllDropdowns(except) {
    document.querySelectorAll('[data-dropdown]').forEach(function (wrap) {
      if (except && wrap === except) return;
      var panel = wrap.querySelector('[data-dropdown-panel]');
      var toggle = wrap.querySelector('[data-dropdown-toggle]');
      if (panel) panel.classList.remove('is-open');
      if (toggle) toggle.setAttribute('aria-expanded', 'false');
    });
  }

  document.querySelectorAll('[data-dropdown]').forEach(function (wrap) {
    var toggle = wrap.querySelector('[data-dropdown-toggle]');
    var panel = wrap.querySelector('[data-dropdown-panel]');
    if (!toggle || !panel) return;
    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var willOpen = !panel.classList.contains('is-open');
      closeAllDropdowns();
      if (willOpen) {
        panel.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.dispatchEvent(new CustomEvent('rapid:dropdown-open', { bubbles: true }));
      }
    });
    panel.addEventListener('click', function (e) {
      e.stopPropagation();
    });
  });

  document.addEventListener('click', function () {
    closeAllDropdowns();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAllDropdowns();
  });

  /* ----- Mobile sidebar ----- */
  (function initSidebar() {
    var sidebar = document.getElementById('appSidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var openBtn = document.getElementById('sidebarToggle');
    var closeBtn = document.getElementById('sidebarClose');
    if (!sidebar) return;

    function isDesktop() {
      return window.matchMedia('(min-width: 1024px)').matches;
    }

    function setOpen(open) {
      if (isDesktop()) open = false;
      sidebar.classList.toggle('is-open', open);
      if (backdrop) {
        backdrop.classList.toggle('is-open', open);
        backdrop.hidden = !open;
      }
      if (openBtn) openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.classList.toggle('overflow-hidden', open);
    }

    if (openBtn) openBtn.addEventListener('click', function () { setOpen(true); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
    if (backdrop) backdrop.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') setOpen(false);
    });
    window.addEventListener('resize', function () {
      if (isDesktop()) setOpen(false);
    });
  })();

  /* ----- Public nav ----- */
  (function initPublicNav() {
    var toggle = document.getElementById('publicNavToggle');
    var mobile = document.getElementById('publicNavMobile');
    if (!toggle || !mobile) return;
    toggle.addEventListener('click', function () {
      var open = mobile.hasAttribute('hidden');
      if (open) {
        mobile.removeAttribute('hidden');
        mobile.classList.add('flex');
      } else {
        mobile.setAttribute('hidden', '');
        mobile.classList.remove('flex');
      }
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  })();

  /* ----- Soft UI: navbar float elevation on scroll ----- */
  (function initNavbarFloat() {
    var nav = document.querySelector('.rapid-navbar');
    if (!nav) return;
    var threshold = 8;
    var ticking = false;

    function sync() {
      ticking = false;
      nav.classList.toggle('is-floating', window.scrollY > threshold);
    }

    function onScroll() {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(sync);
    }

    sync();
    window.addEventListener('scroll', onScroll, { passive: true });
  })();

  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!window.Swal) return;
      e.preventDefault();
      var message = el.getAttribute('data-confirm') || 'Are you sure?';
      var href = el.getAttribute('href');
      var form = el.closest('form');

      Swal.fire({
        title: 'Confirm',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: PRIMARY,
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Yes, continue'
      }).then(function (result) {
        if (!result.isConfirmed) return;
        if (href) {
          window.location.href = href;
        } else if (form) {
          form.submit();
        }
      });
    });
  });

  function toast(message) {
    var existing = document.querySelector('.copied-toast');
    if (existing) existing.remove();
    var el = document.createElement('div');
    el.className = 'copied-toast';
    el.setAttribute('role', 'status');
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 2200);
  }

  function fallbackCopy(text) {
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
      } catch (err) {
        reject(err);
      } finally {
        ta.remove();
      }
    });
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text).catch(function () {
        return fallbackCopy(text);
      });
    }
    return fallbackCopy(text);
  }

  function markCopied(btn) {
    var icon = btn.querySelector('i');
    btn.classList.add('is-copied');
    if (icon) {
      icon.className = 'bi bi-clipboard-check';
      window.setTimeout(function () {
        icon.className = 'bi bi-clipboard';
        btn.classList.remove('is-copied');
      }, 1600);
    } else {
      window.setTimeout(function () {
        btn.classList.remove('is-copied');
      }, 1600);
    }
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-copy-btn]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    var text = btn.getAttribute('data-copy-btn') || '';
    if (!text) return;
    copyText(text).then(function () {
      markCopied(btn);
      toast('Ticket number copied');
    }).catch(function () {
      toast('Copy failed');
    });
  });

  function drawQr() {
    if (typeof QRCode === 'undefined') return;
    document.querySelectorAll('canvas[data-qr]').forEach(function (canvas) {
      var value = canvas.getAttribute('data-qr');
      if (!value) return;
      QRCode.toCanvas(canvas, value, {
        width: 128,
        margin: 1,
        color: { dark: '#091C39', light: '#FFFFFF' }
      }, function (err) {
        if (err && canvas.parentElement) {
          canvas.parentElement.classList.add('is-qr-failed');
        }
      });
    });
  }

  if (document.querySelector('canvas[data-qr]')) {
    var qrScript = document.createElement('script');
    var base = (window.RAPID && window.RAPID.baseUrl) ? window.RAPID.baseUrl : '';
    qrScript.src = base + '/assets/js/qrcode.min.js';
    qrScript.onload = drawQr;
    qrScript.onerror = function () {
      document.querySelectorAll('.ticket-qr-wrap').forEach(function (el) {
        el.classList.add('is-qr-failed');
      });
    };
    document.head.appendChild(qrScript);
  }

  /* ----- Multi-step booking wizard ----- */
  document.querySelectorAll('[data-wizard]').forEach(function (root) {
    var panels = Array.prototype.slice.call(root.querySelectorAll('[data-step]'));
    var stepsUi = Array.prototype.slice.call(root.querySelectorAll('.wizard-steps li'));
    var nextBtn = root.querySelector('[data-wizard-next]');
    var prevBtn = root.querySelector('[data-wizard-prev]');
    var submitBtn = root.querySelector('[data-wizard-submit]');
    var current = 0;

    function requiredOk(panel) {
      var fields = panel.querySelectorAll('[required]');
      for (var i = 0; i < fields.length; i++) {
        if (!fields[i].checkValidity()) {
          fields[i].reportValidity();
          return false;
        }
      }
      return true;
    }

    function syncReview() {
      root.querySelectorAll('[data-review]').forEach(function (el) {
        var id = el.getAttribute('data-review');
        var field = document.getElementById(id);
        el.textContent = field && field.value ? field.value : '—';
      });
    }

    function show(i) {
      current = i;
      panels.forEach(function (p, idx) {
        p.hidden = idx !== current;
      });
      stepsUi.forEach(function (li, idx) {
        li.classList.toggle('is-current', idx === current);
        li.classList.toggle('is-done', idx < current);
      });
      if (prevBtn) prevBtn.hidden = current === 0;
      if (nextBtn) nextBtn.hidden = current === panels.length - 1;
      if (submitBtn) submitBtn.hidden = current !== panels.length - 1;
      if (current === panels.length - 1) syncReview();
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        if (!requiredOk(panels[current])) return;
        if (current < panels.length - 1) show(current + 1);
      });
    }
    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        if (current > 0) show(current - 1);
      });
    }
    show(0);
  });

  /* ----- Drag-and-drop media with angle tags ----- */
  document.querySelectorAll('[data-dropzone]').forEach(function (zone) {
    var input = zone.querySelector('input[type="file"]');
    var previews = zone.querySelector('[data-previews]');
    var idle = zone.querySelector('[data-drop-idle]');
    if (!input || !previews) return;

    var dt = new DataTransfer();
    var angles = [];

    function rebuildInput() {
      input.files = dt.files;
      var hidden = zone.querySelector('[data-angle-fields]');
      if (!hidden) return;
      hidden.innerHTML = '';
      angles.forEach(function (a) {
        var el = document.createElement('input');
        el.type = 'hidden';
        el.name = 'media_angles[]';
        el.value = a;
        hidden.appendChild(el);
      });
    }

    function render() {
      previews.innerHTML = '';
      if (idle) idle.hidden = dt.files.length > 0;
      Array.prototype.forEach.call(dt.files, function (file, idx) {
        var card = document.createElement('div');
        card.className = 'dropzone-card';
        var media;
        if (file.type.indexOf('video/') === 0) {
          media = document.createElement('video');
          media.src = URL.createObjectURL(file);
        } else {
          media = document.createElement('img');
          media.alt = file.name;
          media.src = URL.createObjectURL(file);
        }
        var meta = document.createElement('div');
        meta.className = 'dz-meta';
        var sel = document.createElement('select');
        sel.className = 'form-select form-select-sm';
        ['front', 'back', 'screen', 'other'].forEach(function (v) {
          var o = document.createElement('option');
          o.value = v;
          o.textContent = v.charAt(0).toUpperCase() + v.slice(1);
          if (angles[idx] === v) o.selected = true;
          sel.appendChild(o);
        });
        sel.addEventListener('change', function () {
          angles[idx] = sel.value;
          rebuildInput();
        });
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn-link text-sm text-red-700 mt-1';
        remove.textContent = 'Remove';
        remove.addEventListener('click', function (e) {
          e.stopPropagation();
          var next = new DataTransfer();
          var nextAngles = [];
          Array.prototype.forEach.call(dt.files, function (f, i) {
            if (i === idx) return;
            next.items.add(f);
            nextAngles.push(angles[i] || 'other');
          });
          dt = next;
          angles = nextAngles;
          rebuildInput();
          render();
        });
        meta.appendChild(sel);
        meta.appendChild(remove);
        card.appendChild(media);
        card.appendChild(meta);
        previews.appendChild(card);
      });
      rebuildInput();
    }

    function addFiles(fileList) {
      Array.prototype.forEach.call(fileList, function (file) {
        if (dt.files.length >= 8) return;
        dt.items.add(file);
        var guess = 'other';
        var n = (file.name || '').toLowerCase();
        if (n.indexOf('front') !== -1) guess = 'front';
        else if (n.indexOf('back') !== -1 || n.indexOf('rear') !== -1) guess = 'back';
        else if (n.indexOf('screen') !== -1) guess = 'screen';
        angles.push(guess);
      });
      render();
    }

    zone.addEventListener('click', function (e) {
      if (e.target.closest('select, button')) return;
      input.click();
    });
    input.addEventListener('click', function (e) {
      e.stopPropagation();
    });
    input.addEventListener('change', function () {
      addFiles(input.files);
    });
    ['dragenter', 'dragover'].forEach(function (ev) {
      zone.addEventListener(ev, function (e) {
        e.preventDefault();
        zone.classList.add('is-dragover');
      });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      zone.addEventListener(ev, function (e) {
        e.preventDefault();
        zone.classList.remove('is-dragover');
      });
    });
    zone.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files);
    });
  });

  /* ----- Quote approve / decline ----- */
  document.querySelectorAll('[data-quote-decide]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var decision = btn.getAttribute('data-quote-decide');
      var form = document.getElementById('quoteRespondForm');
      if (!form || !window.Swal) return;
      var approve = decision === 'approved';
      Swal.fire({
        title: approve ? 'Approve this quotation?' : 'Decline this quotation?',
        text: approve
          ? 'Repair work will proceed at the quoted total. This cannot be undone from this screen.'
          : 'The shop will be notified. You can add an optional note.',
        icon: approve ? 'question' : 'warning',
        input: approve ? undefined : 'textarea',
        inputPlaceholder: 'Optional reason…',
        showCancelButton: true,
        confirmButtonColor: approve ? PRIMARY : '#B91C1C',
        cancelButtonColor: '#64748B',
        confirmButtonText: approve ? 'Confirm approval' : 'Confirm decline'
      }).then(function (result) {
        if (!result.isConfirmed) return;
        document.getElementById('quoteDecision').value = decision;
        document.getElementById('quoteNote').value = result.value || '';
        form.submit();
      });
    });
  });

  /* ----- Live notifications ----- */
  (function initNotifications() {
    var list = document.getElementById('notifyList');
    var badge = document.getElementById('notifyBadge');
    var markAll = document.getElementById('notifyMarkAll');
    if (!list || !window.RAPID) return;

    var base = window.RAPID.baseUrl || '';
    var apiUrl = (base === '' ? '' : base) + '/api/notifications';

    function setBadge(n) {
      if (!badge) return;
      if (n > 0) {
        badge.textContent = n > 99 ? '99+' : String(n);
        badge.classList.remove('is-hidden');
      } else {
        badge.classList.add('is-hidden');
      }
    }

    function render(items) {
      if (!items.length) {
        list.innerHTML = '<div class="px-3 py-3 text-rapid-muted text-sm">No notifications yet.</div>';
        return;
      }
      list.innerHTML = items.map(function (n) {
        return (
          '<button type="button" class="notify-item' + (n.is_read ? '' : ' is-unread') + '" data-id="' + n.id + '">' +
            '<div class="font-semibold text-sm">' + escapeHtml(n.title) + '</div>' +
            '<div class="text-sm text-rapid-muted">' + escapeHtml(n.message) + '</div>' +
            '<div class="notify-time">' + escapeHtml(n.created_label) + '</div>' +
          '</button>'
        );
      }).join('');
    }

    function escapeHtml(s) {
      return String(s || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function load() {
      fetch(apiUrl, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.ok) return;
          setBadge(data.unread || 0);
          render(data.items || []);
        })
        .catch(function () {
          list.innerHTML = '<div class="px-3 py-3 text-rapid-muted text-sm">Unable to load notifications.</div>';
        });
    }

    function postAction(payload) {
      return fetch(apiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': window.RAPID.csrf || ''
        },
        body: JSON.stringify(Object.assign({ _csrf: window.RAPID.csrf || '' }, payload))
      }).then(function (r) { return r.json(); });
    }

    list.addEventListener('click', function (e) {
      var item = e.target.closest('.notify-item');
      if (!item) return;
      var id = parseInt(item.getAttribute('data-id'), 10);
      postAction({ action: 'mark_read', id: id }).then(function (data) {
        if (data && data.ok) setBadge(data.unread || 0);
        item.classList.remove('is-unread');
      });
    });

    if (markAll) {
      markAll.addEventListener('click', function () {
        postAction({ action: 'mark_all' }).then(function (data) {
          if (data && data.ok) {
            setBadge(0);
            load();
          }
        });
      });
    }

    var toggle = document.getElementById('notifyToggle');
    if (toggle) {
      toggle.addEventListener('rapid:dropdown-open', load);
    }
    load();
  })();

  /* ----- Technician process modal ----- */
  document.querySelectorAll('[data-process-modal]').forEach(function (root) {
    var panels = Array.prototype.slice.call(root.querySelectorAll('[data-step]'));
    var stepsUi = Array.prototype.slice.call(root.querySelectorAll('.wizard-steps li'));
    var nextBtn = root.querySelector('[data-process-next]');
    var prevBtn = root.querySelector('[data-process-prev]');
    var startId = root.getAttribute('data-start-step') || '';
    var current = 0;

    function indexOfId(id) {
      for (var i = 0; i < panels.length; i++) {
        if (panels[i].getAttribute('data-step') === id) return i;
      }
      return 0;
    }

    function show(i) {
      if (!panels.length) return;
      current = Math.max(0, Math.min(i, panels.length - 1));
      panels.forEach(function (p, idx) {
        p.hidden = idx !== current;
      });
      stepsUi.forEach(function (li, idx) {
        li.classList.toggle('is-current', idx === current);
        li.classList.toggle('is-done', idx < current);
      });
      if (prevBtn) prevBtn.hidden = current === 0;
      if (nextBtn) nextBtn.hidden = current === panels.length - 1;
    }

    var inline = root.hasAttribute('data-inline');

    function open() {
      if (inline) {
        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
        root.focus({ preventScroll: true });
        return;
      }
      root.hidden = false;
      document.body.classList.add('process-modal-open');
      var dialog = root.querySelector('[data-process-dialog]');
      if (dialog && typeof dialog.focus === 'function') dialog.focus();
    }

    function close() {
      if (inline) return;
      root.hidden = true;
      document.body.classList.remove('process-modal-open');
    }

    document.querySelectorAll('[data-process-open]').forEach(function (btn) {
      btn.addEventListener('click', open);
    });

    root.querySelectorAll('[data-process-close]').forEach(function (el) {
      el.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !root.hidden) close();
    });

    window.RAPID = window.RAPID || {};
    window.RAPID.openProcessStep = function (stepId) {
      open();
      show(indexOfId(stepId || 'quote'));
    };

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        if (current > 0) show(current - 1);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        if (current < panels.length - 1) show(current + 1);
      });
    }

    stepsUi.forEach(function (li, idx) {
      li.addEventListener('click', function () { show(idx); });
      li.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          show(idx);
        }
      });
      li.setAttribute('role', 'button');
      li.tabIndex = 0;
    });

    show(indexOfId(startId));
    if (root.getAttribute('data-open') === '1') open();
  });

  window.RAPID = window.RAPID || {};
  window.RAPID.addQuotePart = function () {
    return false;
  };
  window.RAPID.aiSuggestedParts = [];
  window.RAPID.setAiSuggestedParts = function (parts) {
    window.RAPID.aiSuggestedParts = Array.isArray(parts) ? parts : [];
    var has = window.RAPID.aiSuggestedParts.length > 0;
    document.querySelectorAll('[data-add-ai-parts]').forEach(function (btn) {
      btn.hidden = !has;
    });
    document.querySelectorAll('[data-ai-parts-hint]').forEach(function (hint) {
      hint.hidden = !has;
      if (has) {
        var n = window.RAPID.aiSuggestedParts.length;
        hint.textContent =
          n +
          ' AI suggested part' +
          (n === 1 ? '' : 's') +
          ' ready — click “Use AI suggestions” to add them. You can still edit or remove lines.';
      }
    });
  };

  function initQuoteForm(form) {
    var out = form.querySelector('[data-quote-total]');
    var partsOut = form.querySelector('[data-parts-subtotal]');
    var partsList = form.querySelector('[data-quote-parts]');
    var template = form.querySelector('template[id$="quotePartRowTemplate"]') ||
      document.getElementById('quotePartRowTemplate');
    var addAiBtn = form.querySelector('[data-add-ai-parts]');

    function money(n) {
      return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    function applyCatalogSelection(row, fillFromCatalog) {
      if (!row) return;
      var select = row.querySelector('[data-part-catalog]');
      var nameInput = row.querySelector('[data-part-name]');
      var priceInput = row.querySelector('.quote-part-price');
      if (!select || !nameInput) return;
      var opt = select.options[select.selectedIndex];
      var hasCatalog = !!(select.value && opt);
      if (hasCatalog) {
        nameInput.readOnly = true;
        if (fillFromCatalog) {
          nameInput.value = opt.getAttribute('data-name') || '';
          if (priceInput) priceInput.value = opt.getAttribute('data-price') || '0';
        }
      } else {
        nameInput.readOnly = false;
      }
    }

    function syncRemoveButtons() {
      if (!partsList) return;
      var rows = partsList.querySelectorAll('[data-quote-part-row]');
      rows.forEach(function (row) {
        var btn = row.querySelector('[data-remove-part]');
        if (btn) btn.hidden = rows.length <= 1;
      });
    }

    function recalc() {
      var partsTotal = 0;
      if (partsList) {
        partsList.querySelectorAll('[data-quote-part-row]').forEach(function (row) {
          var qty = parseFloat((row.querySelector('.quote-part-qty') || {}).value || '0') || 0;
          var price = parseFloat((row.querySelector('.quote-part-price') || {}).value || '0') || 0;
          var line = qty * price;
          partsTotal += line;
          var lineOut = row.querySelector('[data-part-line]');
          if (lineOut) lineOut.textContent = money(line);
        });
      }
      if (partsOut) partsOut.textContent = money(partsTotal);

      var total = partsTotal;
      form.querySelectorAll('.quote-cost').forEach(function (el) {
        total += parseFloat(el.value || '0') || 0;
      });
      if (out) out.textContent = money(total);
    }

    form.addEventListener('change', function (e) {
      if (e.target.matches('[data-part-catalog]')) {
        applyCatalogSelection(e.target.closest('[data-quote-part-row]'), true);
        recalc();
      }
    });

    form.addEventListener('input', function (e) {
      if (
        e.target.matches('.quote-cost') ||
        e.target.matches('.quote-part-qty') ||
        e.target.matches('.quote-part-price') ||
        e.target.matches('[name="part_name[]"]')
      ) {
        recalc();
      }
    });

    form.addEventListener('click', function (e) {
      var applyTpl = e.target.closest('[data-apply-template]');
      if (applyTpl && form.contains(applyTpl)) {
        var selectTpl = form.querySelector('[data-repair-template-select]');
        var templates = [];
        try {
          templates = JSON.parse(form.getAttribute('data-repair-templates') || '[]') || [];
        } catch (err) {
          templates = [];
        }
        var tid = selectTpl ? String(selectTpl.value || '') : '';
        var tpl = null;
        for (var i = 0; i < templates.length; i++) {
          if (String(templates[i].id) === tid) {
            tpl = templates[i];
            break;
          }
        }
        if (!tpl) {
          if (window.Swal) {
            window.Swal.fire({
              icon: 'info',
              title: 'Pick a template',
              text: 'Choose a repair template first.',
              confirmButtonColor: '#091C39'
            });
          }
          return;
        }

        var laborInput = form.querySelector('#labor_cost');
        var otherInput = form.querySelector('#other_cost');
        var notesInput = form.querySelector('#notes');
        if (laborInput) laborInput.value = tpl.labor_cost != null ? tpl.labor_cost : 0;
        if (otherInput) otherInput.value = tpl.other_cost != null ? tpl.other_cost : 0;
        if (notesInput && tpl.notes) notesInput.value = tpl.notes;

        if (partsList) {
          partsList.querySelectorAll('[data-quote-part-row]').forEach(function (row) {
            row.remove();
          });
        }
        var parts = Array.isArray(tpl.parts) ? tpl.parts : [];
        if (!parts.length) {
          if (template && partsList) {
            partsList.appendChild(template.content.cloneNode(true));
            applyCatalogSelection(partsList.querySelector('[data-quote-part-row]'), false);
          }
        } else {
          parts.forEach(function (sp) {
            window.RAPID.addQuotePart({
              partId: sp.part_id || '',
              name: sp.name || '',
              quantity: sp.quantity || 1,
              unitPrice: sp.unit_price != null ? sp.unit_price : 0,
              openStep: false
            });
          });
        }
        syncRemoveButtons();
        recalc();
        if (window.Swal) {
          window.Swal.fire({
            icon: 'success',
            title: 'Template applied',
            text: 'Review labor and parts, then send the quotation.',
            confirmButtonColor: '#091C39'
          });
        }
        return;
      }

      var addAi = e.target.closest('[data-add-ai-parts]');
      if (addAi && form.contains(addAi)) {
        var suggested = window.RAPID.aiSuggestedParts || [];
        if (!suggested.length) {
          if (window.Swal) {
            window.Swal.fire({
              icon: 'info',
              title: 'No AI parts yet',
              text: 'Run Suggest repair steps on the ticket first, then come back here.',
              confirmButtonColor: '#091C39'
            });
          }
          return;
        }
        var count = 0;
        suggested.forEach(function (sp) {
          if (
            window.RAPID.addQuotePart({
              partId: sp.part_id || '',
              name: sp.name || '',
              quantity: sp.quantity || 1,
              unitPrice: sp.unit_price != null ? sp.unit_price : 0,
              openStep: false
            })
          ) {
            count += 1;
          }
        });
        if (window.Swal) {
          window.Swal.fire({
            icon: count ? 'success' : 'info',
            title: count ? 'Added ' + count + ' AI part' + (count === 1 ? '' : 's') : 'Could not add parts',
            text: count
              ? 'Review qty and prices below. You can edit or remove any line before sending.'
              : 'The quotation form could not be updated.',
            confirmButtonColor: '#091C39'
          });
        }
        return;
      }

      var addBtn = e.target.closest('[data-add-part]');
      if (addBtn && form.contains(addBtn) && partsList && template) {
        var node = template.content.cloneNode(true);
        partsList.appendChild(node);
        var newRow = partsList.querySelector('[data-quote-part-row]:last-child');
        applyCatalogSelection(newRow, false);
        syncRemoveButtons();
        recalc();
        var focusEl = newRow && newRow.querySelector('[data-part-catalog]');
        if (focusEl) focusEl.focus();
        return;
      }

      var removeBtn = e.target.closest('[data-remove-part]');
      if (removeBtn && form.contains(removeBtn)) {
        var row = removeBtn.closest('[data-quote-part-row]');
        if (row && partsList && partsList.querySelectorAll('[data-quote-part-row]').length > 1) {
          row.remove();
          syncRemoveButtons();
          recalc();
        }
      }
    });

    if (partsList) {
      partsList.querySelectorAll('[data-quote-part-row]').forEach(function (row) {
        applyCatalogSelection(row, false);
      });
    }
    syncRemoveButtons();
    recalc();

    if (addAiBtn) {
      addAiBtn.hidden = !(window.RAPID.aiSuggestedParts && window.RAPID.aiSuggestedParts.length);
    }

    window.RAPID.addQuotePart = function (detail) {
      detail = detail || {};
      if (!partsList || !template) return false;

      var rows = partsList.querySelectorAll('[data-quote-part-row]');
      var target = null;
      if (rows.length === 1) {
        var only = rows[0];
        var nameOnly = ((only.querySelector('[data-part-name]') || {}).value || '').trim();
        var idOnly = ((only.querySelector('[data-part-catalog]') || {}).value || '').trim();
        if (!nameOnly && !idOnly) {
          target = only;
        }
      }
      if (!target) {
        partsList.appendChild(template.content.cloneNode(true));
        target = partsList.querySelector('[data-quote-part-row]:last-child');
      }
      if (!target) return false;

      var select = target.querySelector('[data-part-catalog]');
      var nameInput = target.querySelector('[data-part-name]');
      var qtyInput = target.querySelector('.quote-part-qty');
      var priceInput = target.querySelector('.quote-part-price');
      var partId = detail.partId != null && detail.partId !== '' ? String(detail.partId) : '';
      var name = detail.name != null ? String(detail.name) : '';
      var qty = detail.quantity != null ? String(detail.quantity) : '1';
      var price = detail.unitPrice != null ? String(detail.unitPrice) : '0';

      if (select) {
        select.value = partId;
        if (partId && select.value !== partId) {
          select.value = '';
        }
      }
      if (nameInput) {
        nameInput.value = name;
        nameInput.readOnly = !!(select && select.value);
      }
      if (qtyInput) qtyInput.value = qty;
      if (priceInput) priceInput.value = price;

      applyCatalogSelection(target, false);
      if (nameInput && name) nameInput.value = name;
      if (priceInput && detail.unitPrice != null) priceInput.value = price;
      if (nameInput && select && select.value) nameInput.readOnly = true;

      syncRemoveButtons();
      recalc();

      if (detail.openStep !== false && typeof window.RAPID.openProcessStep === 'function') {
        window.RAPID.openProcessStep('quote');
      }

      return true;
    };
  }

  document.querySelectorAll('[data-quote-form]').forEach(initQuoteForm);

  /* ----- Add / edit forms in a modal -----
     Links marked data-modal-form load the target page's POST form into a
     modal. Success (server redirect) navigates like a normal submit;
     validation errors re-render inside the modal. Any failure falls back to
     the full page, so the form pages still work on their own. */
  var formModal = null;
  var formModalOpener = null;

  function closeFormModal() {
    if (!formModal || formModal.hidden) return;
    formModal.hidden = true;
    document.body.classList.remove('process-modal-open');
    formModal.querySelector('[data-form-modal-body]').innerHTML = '';
    if (formModalOpener) formModalOpener.focus();
  }

  function ensureFormModal() {
    if (formModal) return formModal;
    formModal = document.createElement('div');
    formModal.className = 'rapid-modal';
    formModal.hidden = true;
    formModal.innerHTML =
      '<div class="rapid-modal-backdrop" data-form-modal-close></div>' +
      '<div class="rapid-modal-dialog rapid-form-modal" role="dialog" aria-modal="true" aria-labelledby="formModalTitle" tabindex="-1">' +
      '<div class="rapid-modal-header"><div>' +
      '<h2 id="formModalTitle" class="text-xl font-bold text-rapid m-0"></h2>' +
      '<p class="text-sm text-rapid-muted mt-1 mb-0" data-form-modal-lead></p></div>' +
      '<button type="button" class="btn btn-outline-secondary btn-sm" data-form-modal-close aria-label="Close">' +
      '<i class="bi bi-x-lg" aria-hidden="true"></i></button></div>' +
      '<div class="rapid-modal-body" data-form-modal-body></div></div>';
    document.body.appendChild(formModal);
    formModal.addEventListener('click', function (e) {
      if (e.target.closest('[data-form-modal-close]')) closeFormModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeFormModal();
    });
    return formModal;
  }

  function renderFormModal(html, url) {
    var doc = new DOMParser().parseFromString(html, 'text/html');
    var source = doc.querySelector('.app-main form[method="post"]');
    if (!source) return false;

    var m = ensureFormModal();
    var h1 = doc.querySelector('.app-main .page-header h1');
    var lead = doc.querySelector('.app-main .page-header p');
    m.querySelector('#formModalTitle').textContent = h1 ? h1.textContent.trim() : '';
    m.querySelector('[data-form-modal-lead]').textContent = lead ? lead.textContent.trim() : '';

    var body = m.querySelector('[data-form-modal-body]');
    body.innerHTML = '';
    doc.querySelectorAll('.app-main > .alert').forEach(function (alert) {
      body.appendChild(document.importNode(alert, true));
    });

    var form = document.importNode(source, true);
    form.classList.remove('rapid-card');
    form.action = url;
    // Prefix ids so they never collide with the list page behind the modal.
    form.querySelectorAll('[id]').forEach(function (el) { el.id = 'fm-' + el.id; });
    form.querySelectorAll('label[for]').forEach(function (l) { l.htmlFor = 'fm-' + l.htmlFor; });
    // The page's Cancel link becomes a close button.
    form.querySelectorAll('a.btn').forEach(function (a) {
      if (!/cancel/i.test(a.textContent)) return;
      var cancel = document.createElement('button');
      cancel.type = 'button';
      cancel.className = a.className;
      cancel.textContent = a.textContent.trim();
      cancel.setAttribute('data-form-modal-close', '');
      a.replaceWith(cancel);
    });
    body.appendChild(form);

    if (form.matches('[data-quote-form]')) initQuoteForm(form);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      submitFormModal(form, url);
    });

    m.hidden = false;
    document.body.classList.add('process-modal-open');
    var first = form.querySelector('input:not([type="hidden"]), select, textarea');
    (first || m.querySelector('.rapid-modal-dialog')).focus();
    return true;
  }

  function submitFormModal(form, url) {
    var btn = form.querySelector('[type="submit"]');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="rapid-spinner mr-1" role="status" aria-hidden="true"></span> Saving…';
    }
    fetch(url, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
      .then(function (res) {
        if (res.redirected) {
          window.location.href = res.url;
          return;
        }
        return res.text().then(function (html) {
          if (!renderFormModal(html, url)) window.location.href = res.url;
        });
      })
      .catch(function () {
        HTMLFormElement.prototype.submit.call(form);
      });
  }

  document.addEventListener('click', function (e) {
    var link = e.target.closest('a[data-modal-form]');
    if (!link || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    formModalOpener = link;
    fetch(link.href, { credentials: 'same-origin' })
      .then(function (res) {
        return res.text().then(function (html) {
          if (!renderFormModal(html, res.url)) window.location.href = link.href;
        });
      })
      .catch(function () {
        window.location.href = link.href;
      });
  });

  /* ----- Password visibility ----- */
  document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var wrap = btn.closest('.auth-input');
      var input = wrap ? wrap.querySelector('input') : null;
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      var icon = btn.querySelector('i');
      if (icon) {
        icon.classList.toggle('bi-eye', !show);
        icon.classList.toggle('bi-eye-slash', show);
      }
    });
  });

})();

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
      }, function () { /* ignore */ });
    });
  }

  if (document.querySelector('canvas[data-qr]')) {
    var qrScript = document.createElement('script');
    qrScript.src = 'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js';
    qrScript.onload = drawQr;
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

    function open() {
      root.hidden = false;
      document.body.classList.add('process-modal-open');
      var dialog = root.querySelector('[data-process-dialog]');
      if (dialog && typeof dialog.focus === 'function') dialog.focus();
    }

    function close() {
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

  document.querySelectorAll('[data-quote-form]').forEach(function (form) {
    var out = form.querySelector('[data-quote-total]');
    var fields = form.querySelectorAll('.quote-cost');
    function recalc() {
      var total = 0;
      fields.forEach(function (el) {
        total += parseFloat(el.value || '0') || 0;
      });
      if (out) out.textContent = total.toFixed(2);
    }
    fields.forEach(function (el) {
      el.addEventListener('input', recalc);
    });
    recalc();
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

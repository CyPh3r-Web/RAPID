/**
 * Technician AI repair assistant
 */
(function () {
  'use strict';

  var root = document.getElementById('aiAssist');
  if (!root) return;

  var ticketId = parseInt(root.getAttribute('data-ticket-id') || '0', 10);
  var endpoint = root.getAttribute('data-endpoint') || '';
  var configured = root.getAttribute('data-configured') === '1';
  var suggestBtn = document.getElementById('aiSuggestBtn');
  var statusEl = document.getElementById('aiAssistStatus');
  var resultEl = document.getElementById('aiAssistResult');
  var emptyEl = document.getElementById('aiAssistEmpty');
  var errorEl = document.getElementById('aiAssistError');

  function csrf() {
    return (window.RAPID && window.RAPID.csrf) || '';
  }

  function setBusy(busy) {
    if (!suggestBtn) return;
    suggestBtn.disabled = busy;
    if (busy) {
      suggestBtn.dataset.originalHtml = suggestBtn.innerHTML;
      suggestBtn.innerHTML = '<span class="rapid-spinner mr-1" role="status" aria-hidden="true"></span> Thinking…';
    } else if (suggestBtn.dataset.originalHtml) {
      suggestBtn.innerHTML = suggestBtn.dataset.originalHtml;
    }
  }

  function showError(msg) {
    if (!errorEl) return;
    errorEl.hidden = !msg;
    errorEl.textContent = msg || '';
  }

  function el(tag, className, text) {
    var n = document.createElement(tag);
    if (className) n.className = className;
    if (text != null && text !== '') n.textContent = text;
    return n;
  }

  function likelihoodClass(level) {
    if (level === 'high') return 'ai-likelihood is-high';
    if (level === 'low') return 'ai-likelihood is-low';
    return 'ai-likelihood is-medium';
  }

  function render(payload, meta) {
    if (!resultEl || !payload) return;
    resultEl.innerHTML = '';
    resultEl.hidden = false;
    if (emptyEl) emptyEl.hidden = true;

    if (payload.summary) {
      resultEl.appendChild(el('p', 'ai-assist-summary', payload.summary));
    }

    if (payload.safety_notes && payload.safety_notes.length) {
      var safety = el('div', 'ai-assist-safety');
      safety.appendChild(el('h3', '', 'Safety'));
      var ul = document.createElement('ul');
      payload.safety_notes.forEach(function (note) {
        ul.appendChild(el('li', '', note));
      });
      safety.appendChild(ul);
      resultEl.appendChild(safety);
    }

    if (payload.likely_causes && payload.likely_causes.length) {
      resultEl.appendChild(el('h3', 'ai-assist-h', 'Likely causes'));
      payload.likely_causes.forEach(function (c) {
        var item = el('div', 'ai-cause-item');
        var top = el('div', 'ai-cause-top');
        top.appendChild(el('strong', '', c.cause || ''));
        if (c.likelihood) {
          top.appendChild(el('span', likelihoodClass(c.likelihood), c.likelihood));
        }
        item.appendChild(top);
        if (c.why) item.appendChild(el('p', 'ai-assist-why', c.why));
        resultEl.appendChild(item);
      });
    }

    if (payload.tests && payload.tests.length) {
      resultEl.appendChild(el('h3', 'ai-assist-h', 'Tests to run'));
      var ol = el('ol', 'ai-test-list');
      payload.tests.forEach(function (t) {
        var li = document.createElement('li');
        li.appendChild(el('div', 'ai-test-action', t.action || ''));
        if (t.looking_for) {
          li.appendChild(el('div', 'ai-test-look', 'Looking for: ' + t.looking_for));
        }
        ol.appendChild(li);
      });
      resultEl.appendChild(ol);
    }

    if (payload.parts_to_check && payload.parts_to_check.length) {
      resultEl.appendChild(el('h3', 'ai-assist-h', 'Parts / areas to check'));
      var parts = el('ul', 'ai-parts-list');
      payload.parts_to_check.forEach(function (p) {
        parts.appendChild(el('li', '', p));
      });
      resultEl.appendChild(parts);
    }

    var actions = el('div', 'ai-assist-actions');
    if (payload.draft_diagnosis && document.getElementById('diagnosis')) {
      var useDiag = el('button', 'btn btn-rapid-primary btn-sm', 'Use in diagnosis');
      useDiag.type = 'button';
      useDiag.addEventListener('click', function () {
        var field = document.getElementById('diagnosis');
        if (field) field.value = payload.draft_diagnosis;
        if (window.Swal) {
          window.Swal.fire({
            icon: 'success',
            title: 'Draft copied',
            text: 'Review and edit before saving. This is a suggestion, not a confirmed diagnosis.',
            confirmButtonColor: '#091C39'
          });
        }
      });
      actions.appendChild(useDiag);
    }
    if (payload.draft_recommended_action && document.getElementById('recommended_action')) {
      var useAct = el('button', 'btn btn-rapid-outline btn-sm', 'Use recommended action');
      useAct.type = 'button';
      useAct.addEventListener('click', function () {
        var field = document.getElementById('recommended_action');
        if (field) field.value = payload.draft_recommended_action;
      });
      actions.appendChild(useAct);
    }
    if (actions.childNodes.length) {
      resultEl.appendChild(actions);
    }

    if (statusEl) {
      var bits = [];
      if (meta && meta.cached) bits.push('Saved for this ticket');
      if (meta && meta.stale) bits.push('Device details changed — refresh for a new idea');
      if (meta && meta.generated_at) bits.push(meta.generated_at);
      statusEl.textContent = bits.join(' · ');
    }
  }

  function request(force) {
    if (!configured) {
      showError('An admin needs to add a free Gemini API key in Settings first.');
      return;
    }
    showError('');
    setBusy(true);
    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf()
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        _csrf: csrf(),
        ticket_id: ticketId,
        force: !!force
      })
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { res: res, data: data };
        });
      })
      .then(function (pack) {
        var data = pack.data || {};
        if (data.suggestion) {
          render(data.suggestion, {
            cached: !!data.cached,
            stale: !!data.stale,
            generated_at: data.generated_at || ''
          });
        }
        if (!data.ok && data.error) {
          showError(data.error);
        } else if (data.error) {
          showError(data.error);
        }
      })
      .catch(function () {
        showError('Could not reach the assistant. Check your connection and try again.');
      })
      .then(function () {
        setBusy(false);
      });
  }

  if (suggestBtn) {
    suggestBtn.addEventListener('click', function () {
      request(true);
    });
  }

  try {
    var cached = root.getAttribute('data-cached');
    if (cached) {
      var parsed = JSON.parse(cached);
      if (parsed) {
        render(parsed, {
          cached: true,
          stale: root.getAttribute('data-stale') === '1',
          generated_at: root.getAttribute('data-generated-at') || ''
        });
      }
    }
  } catch (e) {
    /* ignore bad cache */
  }
})();

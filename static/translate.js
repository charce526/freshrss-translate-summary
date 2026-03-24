(function () {
  var isBound = false;
  var observer = null;
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function getEndpoint(toolbar, action) {
    if (toolbar && toolbar.dataset.translateEndpoint) {
      if (action === 'summary' && toolbar.dataset.summaryEndpoint) {
        return toolbar.dataset.summaryEndpoint;
      }
      if (action === 'translate') {
        return toolbar.dataset.translateEndpoint;
      }
    }

    if (window.extensions && window.extensions.translateCn && window.extensions.translateCn.endpoint) {
      return window.extensions.translateCn.endpoint;
    }

    if (window.context && window.context.translateCn && window.context.translateCn.endpoint) {
      return window.context.translateCn.endpoint;
    }

      return '?c=TranslateSummary&a=translate';
  }

  function getCsrfToken() {
    if (typeof context !== 'undefined' && context && context.csrf) {
      return context.csrf;
    }

    if (window.context && window.context.csrf) {
      return window.context.csrf;
    }

    if (window.extensions && window.extensions.translateCn && window.extensions.translateCn.csrf) {
      return window.extensions.translateCn.csrf;
    }

    var input = document.querySelector('input[name="_csrf"]');
    if (input && input.value) {
      return input.value;
    }
    return '';
  }

  function findEntryContent(entryEl) {
    if (!entryEl) return null;

    var selectors = [
      '.text',
      '.content',
      '.entry-content',
      '.entry_content',
      '.item-content',
      '.item-content-body',
      '.article',
      '.flux_content .text',
      '.flux_content',
      'article'
    ];
    var contentEl = null;
    for (var i = 0; i < selectors.length; i++) {
      contentEl = entryEl.querySelector(selectors[i]);
      if (contentEl) break;
    }

    if (!contentEl) return null;

    var clone = contentEl.cloneNode(true);
    var toolbar = clone.querySelector('.translate-cn-toolbar');
    if (toolbar) toolbar.remove();
    var result = clone.querySelector('.translate-cn-result');
    if (result) result.remove();

    return clone.innerHTML.trim();
  }

  function findContentContainer(entryEl) {
    if (!entryEl) return null;
    var selectors = [
      '.text',
      '.content',
      '.entry-content',
      '.entry_content',
      '.item-content',
      '.item-content-body',
      '.article',
      '.flux_content .text',
      '.flux_content',
      'article'
    ];
    for (var i = 0; i < selectors.length; i++) {
      var el = entryEl.querySelector(selectors[i]);
      if (el) return el;
    }
    return null;
  }

  function ensureToolbar(entryEl) {
    if (!entryEl) return;
    if (entryEl.querySelector('.translate-cn-toolbar')) return;

    var container = findContentContainer(entryEl);
    if (!container) return;

    var entryId = entryEl.dataset.entry || entryEl.getAttribute('data-entry') || entryEl.id || '';
    var toolbar = document.createElement('div');
    toolbar.className = 'translate-cn-toolbar';
    toolbar.dataset.entryId = entryId;
    toolbar.dataset.translateEndpoint = '?c=TranslateSummary&a=translate';
    toolbar.dataset.summaryEndpoint = '?c=TranslateSummary&a=summary';
    toolbar.innerHTML =
      '<button class="btn translate-cn-button" type="button">Translate</button>' +
      '<button class="btn translate-cn-summary-button" type="button">Summary</button>' +
      '<span class="translate-cn-status" aria-live="polite"></span>';

    var translateResult = document.createElement('div');
    translateResult.className = 'translate-cn-result translate-cn-result-translate';
    translateResult.dataset.entryId = entryId;
    translateResult.dataset.resultType = 'translate';
    translateResult.hidden = true;

    var summaryResult = document.createElement('div');
    summaryResult.className = 'translate-cn-result translate-cn-result-summary';
    summaryResult.dataset.entryId = entryId;
    summaryResult.dataset.resultType = 'summary';
    summaryResult.hidden = true;

    container.insertBefore(toolbar, container.firstChild);
    container.insertBefore(translateResult, toolbar.nextSibling);
    container.insertBefore(summaryResult, translateResult.nextSibling);
  }

  function ensureToolbarsIn(root) {
    if (!root || !root.querySelectorAll) return;
    if (root.matches && (root.matches('.flux') || root.matches('.entry'))) {
      ensureToolbar(root);
    }
    var entries = root.querySelectorAll('.flux, .entry');
    for (var i = 0; i < entries.length; i++) {
      ensureToolbar(entries[i]);
    }
  }

  function setStatus(statusEl, message, state) {
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.dataset.state = state || '';
  }

  function handleClick(event) {
    var button = event.target.closest('.translate-cn-button, .translate-cn-summary-button');
    if (!button) return;

    var toolbar = button.closest('.translate-cn-toolbar');
    if (!toolbar) return;

    var entryEl = button.closest('.entry') || button.closest('.flux');
    var statusEl = toolbar.querySelector('.translate-cn-status');
    var action = button.classList.contains('translate-cn-summary-button') ? 'summary' : 'translate';
    var resultSelector = '.translate-cn-result[data-entry-id="' + toolbar.dataset.entryId + '"][data-result-type="' + action + '"]';
    var resultEl = entryEl ? entryEl.querySelector(resultSelector) : null;
    if (!resultEl && action === 'translate' && entryEl) {
      var legacyEl = entryEl.querySelector('.translate-cn-result[data-entry-id="' + toolbar.dataset.entryId + '"]:not([data-result-type])');
      if (legacyEl) {
        legacyEl.dataset.resultType = 'translate';
        resultEl = legacyEl;
      }
    }

    if (button.dataset.loading === '1') {
      return;
    }

    if (resultEl && (resultEl.dataset.state === 'done' || resultEl.innerHTML.trim() !== '')) {
      resultEl.hidden = !resultEl.hidden;
      return;
    }

    var contentHtml = findEntryContent(entryEl);
    if (!contentHtml) {
      setStatus(statusEl, action === 'summary' ? 'No content to summarize.' : 'No content to translate.', 'error');
      return;
    }

    button.dataset.loading = '1';
    setStatus(statusEl, action === 'summary' ? 'Summarizing...' : 'Translating...', 'loading');

    var payload = { content_html: contentHtml, ajax: true };
    var csrf = getCsrfToken();
    if (!csrf) {
      button.dataset.loading = '';
      setStatus(statusEl, 'Missing CSRF token.', 'error');
      return;
    }
    payload._csrf = csrf;
    payload.csrf = csrf;

    fetch(getEndpoint(toolbar, action), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    }).then(function (response) {
      var contentType = response.headers.get('Content-Type') || '';
      if (contentType.indexOf('application/json') === -1) {
        return response.text().then(function (text) {
          throw new Error('Unexpected response from server.');
        });
      }
      return response.json().then(function (data) {
        return { status: response.status, data: data };
      });
    }).then(function (payload) {
      if (!payload.data || !payload.data.ok) {
        var message = payload.data && payload.data.error ? payload.data.error : (action === 'summary' ? 'Summary failed.' : 'Translation failed.');
        throw new Error(message);
      }

      if (!resultEl && entryEl) {
        resultEl = document.createElement('div');
        resultEl.className = 'translate-cn-result translate-cn-result-' + action;
        resultEl.dataset.entryId = toolbar.dataset.entryId || '';
        resultEl.dataset.resultType = action;
        var container = findContentContainer(entryEl) || entryEl;
        container.appendChild(resultEl);
      }

      if (resultEl) {
        resultEl.innerHTML = payload.data.translated_html;
        resultEl.hidden = false;
        resultEl.dataset.state = 'done';
      }

      setStatus(statusEl, action === 'summary' ? 'Summary ready.' : 'Translation ready.', 'done');
    }).catch(function (error) {
      setStatus(statusEl, error.message || (action === 'summary' ? 'Summary failed.' : 'Translation failed.'), 'error');
    }).finally(function () {
      button.dataset.loading = '';
    });
  }

  function bind() {
    if (isBound) return;
    isBound = true;
    var entries = document.querySelectorAll('.flux, .entry');
    for (var i = 0; i < entries.length; i++) {
      ensureToolbar(entries[i]);
    }
    document.body.addEventListener('click', handleClick);

    var streamRoot = document.getElementById('global') || document.body;
    observer = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var added = mutations[i].addedNodes;
        for (var j = 0; j < added.length; j++) {
          var node = added[j];
          if (!node || node.nodeType !== 1) continue;
          ensureToolbarsIn(node);
        }
      }
    });
    observer.observe(streamRoot, { childList: true, subtree: true });
  }

  ready(bind);

  document.addEventListener('freshrss:globalContextLoaded', function () {
    // Ensure new entries loaded dynamically also work with the same handler.
    var entries = document.querySelectorAll('.flux, .entry');
    for (var i = 0; i < entries.length; i++) {
      ensureToolbar(entries[i]);
    }
    bind();
  });
})();

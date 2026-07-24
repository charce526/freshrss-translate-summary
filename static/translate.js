(function () {
  'use strict';

  var isBound = false;
  var observer = null;

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  }

  function getEndpoint(toolbar, action) {
    if (toolbar) {
      if (action === 'summary' && toolbar.dataset.summaryEndpoint) {
        return toolbar.dataset.summaryEndpoint;
      }
      if (action === 'translate' && toolbar.dataset.translateEndpoint) {
        return toolbar.dataset.translateEndpoint;
      }
    }

    return action === 'summary'
      ? '?c=TranslateSummary&a=summary'
      : '?c=TranslateSummary&a=translate';
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
    return input && input.value ? input.value : '';
  }

  function findContentContainer(entryElement) {
    if (!entryElement) return null;

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

    for (var i = 0; i < selectors.length; i += 1) {
      var element = entryElement.querySelector(selectors[i]);
      if (element) return element;
    }

    return null;
  }

  function findEntryContent(entryElement) {
    var contentElement = findContentContainer(entryElement);
    if (!contentElement) return '';

    var clone = contentElement.cloneNode(true);
    clone.querySelectorAll('.translate-cn-toolbar, .translate-cn-result').forEach(function (element) {
      element.remove();
    });

    return clone.innerHTML.trim();
  }

  function ensureToolbar(entryElement) {
    if (!entryElement || entryElement.querySelector('.translate-cn-toolbar')) return;

    var container = findContentContainer(entryElement);
    if (!container) return;

    var entryId = entryElement.dataset.entry || entryElement.getAttribute('data-entry') || entryElement.id || '';
    var toolbar = document.createElement('div');
    toolbar.className = 'translate-cn-toolbar';
    toolbar.dataset.entryId = entryId;
    toolbar.dataset.translateEndpoint = '?c=TranslateSummary&a=translate';
    toolbar.dataset.summaryEndpoint = '?c=TranslateSummary&a=summary';
    toolbar.innerHTML =
      '<button class="btn translate-cn-button" type="button">翻译</button>' +
      '<button class="btn translate-cn-summary-button" type="button">摘要</button>' +
      '<span class="translate-cn-status" aria-live="polite"></span>';

    var translationResult = document.createElement('div');
    translationResult.className = 'translate-cn-result translate-cn-result-translate';
    translationResult.dataset.entryId = entryId;
    translationResult.dataset.resultType = 'translate';
    translationResult.hidden = true;

    var summaryResult = document.createElement('div');
    summaryResult.className = 'translate-cn-result translate-cn-result-summary';
    summaryResult.dataset.entryId = entryId;
    summaryResult.dataset.resultType = 'summary';
    summaryResult.hidden = true;

    container.insertBefore(toolbar, container.firstChild);
    container.insertBefore(translationResult, toolbar.nextSibling);
    container.insertBefore(summaryResult, translationResult.nextSibling);
  }

  function ensureToolbars(root) {
    if (!root || !root.querySelectorAll) return;

    if (root.matches && (root.matches('.flux') || root.matches('.entry'))) {
      ensureToolbar(root);
    }

    root.querySelectorAll('.flux, .entry').forEach(ensureToolbar);
  }

  function setStatus(element, message, state) {
    if (!element) return;
    element.textContent = message || '';
    element.dataset.state = state || '';
  }

  function parseResponse(response) {
    var contentType = response.headers.get('Content-Type') || '';
    if (contentType.indexOf('application/json') === -1) {
      throw new Error(response.status === 403 ? '请求被拒绝，请刷新页面后重试。' : '服务器返回了无法识别的响应。');
    }

    return response.json().then(function (data) {
      if (!response.ok || !data || !data.ok) {
        throw new Error(data && data.error ? data.error : '请求失败。');
      }
      return data;
    });
  }

  function requestAction(endpoint, contentHtml, csrfToken) {
    var formData = new URLSearchParams();
    formData.set('content_html', contentHtml);
    formData.set('ajax', '1');
    formData.set('_csrf', csrfToken);

    return fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: formData.toString()
    }).then(parseResponse);
  }

  function handleClick(event) {
    var button = event.target.closest('.translate-cn-button, .translate-cn-summary-button');
    if (!button) return;

    var toolbar = button.closest('.translate-cn-toolbar');
    var entryElement = button.closest('.entry') || button.closest('.flux');
    if (!toolbar || !entryElement || button.dataset.loading === '1') return;

    var isSummary = button.classList.contains('translate-cn-summary-button');
    var action = isSummary ? 'summary' : 'translate';
    var statusElement = toolbar.querySelector('.translate-cn-status');
    var resultElement = entryElement.querySelector(
      '.translate-cn-result[data-entry-id="' + toolbar.dataset.entryId + '"][data-result-type="' + action + '"]'
    );

    if (resultElement && resultElement.dataset.state === 'done') {
      resultElement.hidden = !resultElement.hidden;
      return;
    }

    var contentHtml = findEntryContent(entryElement);
    if (!contentHtml) {
      setStatus(statusElement, isSummary ? '没有可生成摘要的内容。' : '没有可翻译的内容。', 'error');
      return;
    }

    var csrfToken = getCsrfToken();
    if (!csrfToken) {
      setStatus(statusElement, '缺少 CSRF 令牌，请刷新页面后重试。', 'error');
      return;
    }

    button.dataset.loading = '1';
    setStatus(statusElement, isSummary ? '正在生成摘要…' : '正在翻译…', 'loading');

    requestAction(getEndpoint(toolbar, action), contentHtml, csrfToken)
      .then(function (data) {
        if (resultElement) {
          resultElement.innerHTML = data.translated_html;
          resultElement.hidden = false;
          resultElement.dataset.state = 'done';
        }
        setStatus(statusElement, isSummary ? '摘要已生成。' : '翻译已完成。', 'done');
      })
      .catch(function (error) {
        setStatus(statusElement, error.message || (isSummary ? '生成摘要失败。' : '翻译失败。'), 'error');
      })
      .finally(function () {
        button.dataset.loading = '';
      });
  }

  function bind() {
    if (isBound) return;
    isBound = true;

    ensureToolbars(document);
    document.body.addEventListener('click', handleClick);

    var streamRoot = document.getElementById('global') || document.body;
    observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node && node.nodeType === 1) ensureToolbars(node);
        });
      });
    });
    observer.observe(streamRoot, { childList: true, subtree: true });
  }

  ready(bind);
  document.addEventListener('freshrss:globalContextLoaded', function () {
    ensureToolbars(document);
    bind();
  });
})();

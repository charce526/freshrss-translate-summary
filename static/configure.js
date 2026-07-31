(function () {
  'use strict';

  function initProfileSettings() {
    var maxProfiles = 20;
    var list = document.getElementById('translate-cn-profile-list');
    var hidden = document.getElementById('translate-cn-api-profiles');
    var addButton = document.getElementById('translate-cn-add-profile');
    var form = document.getElementById('translate-cn-settings-form');

    if (!list || !hidden || !addButton || !form || addButton.dataset.bound === '1') {
      return;
    }
    addButton.dataset.bound = '1';

    var profiles = [];
    try {
      var parsed = JSON.parse(hidden.value || '[]');
      profiles = Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      profiles = [];
    }

    if (profiles.length === 0) {
      profiles.push({
        name: '默认配置',
        base_url: 'https://api.openai.com/v1',
        api_key: '',
        model: 'gpt-3.5-turbo'
      });
    }

    function valueOf(value) {
      return value == null ? '' : String(value);
    }

    function findParent(element, selector) {
      while (element && element !== document) {
        if (element.matches && element.matches(selector)) {
          return element;
        }
        element = element.parentNode;
      }
      return null;
    }

    function createInput(labelText, field, type, placeholder, profile) {
      var label = document.createElement('label');
      var span = document.createElement('span');
      var input = document.createElement('input');

      span.textContent = labelText;
      input.type = type;
      input.value = valueOf(profile[field]);
      input.placeholder = placeholder || '';
      input.autocomplete = field === 'api_key' ? 'new-password' : 'off';
      input.dataset.field = field;

      label.appendChild(span);
      label.appendChild(input);
      return label;
    }

    function syncHidden() {
      hidden.value = JSON.stringify(profiles);
    }

    function render() {
      list.innerHTML = '';

      profiles.forEach(function (profile, index) {
        var card = document.createElement('section');
        var header = document.createElement('div');
        var title = document.createElement('strong');
        var removeButton = document.createElement('button');
        var grid = document.createElement('div');

        card.className = 'translate-cn-profile-card';
        card.dataset.index = String(index);
        header.className = 'translate-cn-profile-card-header';
        title.textContent = 'API 配置 ' + (index + 1);

        removeButton.type = 'button';
        removeButton.className = 'btn translate-cn-profile-remove';
        removeButton.textContent = '删除';
        removeButton.disabled = profiles.length <= 1;
        removeButton.dataset.removeIndex = String(index);

        grid.className = 'translate-cn-profile-grid';
        grid.appendChild(createInput('配置名称', 'name', 'text', '例如：OpenAI 主线路', profile));
        grid.appendChild(createInput('模型名称', 'model', 'text', '例如：gpt-4o', profile));
        grid.appendChild(createInput('API 基础地址', 'base_url', 'url', 'https://api.openai.com/v1', profile));
        grid.appendChild(createInput('API 密钥', 'api_key', 'password', 'sk-...', profile));

        header.appendChild(title);
        header.appendChild(removeButton);
        card.appendChild(header);
        card.appendChild(grid);
        list.appendChild(card);
      });

      addButton.disabled = profiles.length >= maxProfiles;
      syncHidden();
    }

    list.addEventListener('input', function (event) {
      var target = event.target;
      var input = findParent(target, 'input[data-field]');
      var card = findParent(target, '.translate-cn-profile-card');
      if (!input || !card) return;

      var index = Number(card.dataset.index);
      if (!Number.isInteger(index) || !profiles[index]) return;

      profiles[index][input.dataset.field] = input.value;
      syncHidden();
    });

    list.addEventListener('click', function (event) {
      var button = findParent(event.target, '[data-remove-index]');
      if (!button || profiles.length <= 1) return;

      var index = Number(button.dataset.removeIndex);
      if (!Number.isInteger(index) || !profiles[index]) return;

      profiles.splice(index, 1);
      render();
    });

    addButton.addEventListener('click', function () {
      if (profiles.length >= maxProfiles) return;

      var previous = profiles[profiles.length - 1] || {};
      profiles.push({
        name: '配置 ' + (profiles.length + 1),
        base_url: valueOf(previous.base_url) || 'https://api.openai.com/v1',
        api_key: valueOf(previous.api_key),
        model: valueOf(previous.model) || 'gpt-3.5-turbo'
      });
      render();
    });

    form.addEventListener('submit', syncHidden);
    render();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProfileSettings);
  } else {
    initProfileSettings();
  }
})();

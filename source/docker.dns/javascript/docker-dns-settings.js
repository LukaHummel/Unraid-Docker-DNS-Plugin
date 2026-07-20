(function (window, document) {
  'use strict';

  var API = '/plugins/docker.dns/include/Api.php';
  var root = document.getElementById('docker-dns-settings');
  if (!root) return;
  var body = document.querySelector('#docker-dns-containers tbody');
  var status = document.getElementById('docker-dns-status');

  function csrf() { return typeof window.csrf_token === 'string' ? window.csrf_token : ''; }
  function escapeHtml(value) {
    var node = document.createElement('div');
    node.textContent = value == null ? '' : String(value);
    return node.innerHTML;
  }
  function api(payload) {
    return window.fetch(API, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(Object.assign({csrf_token: csrf()}, payload))}).then(function (response) {
      return response.json().then(function (result) {
        if (!response.ok || result.ok === false) throw new Error(result.error || 'Request failed.');
        return result;
      });
    });
  }
  function providerPayload(action) {
    return {
      action: action,
      enabled: document.getElementById('docker-dns-enabled').value === 'true',
      provider: document.getElementById('docker-dns-provider').value,
      base_url: document.getElementById('docker-dns-base-url').value,
      username: document.getElementById('docker-dns-username').value,
      password: document.getElementById('docker-dns-password').value,
      verify_tls: document.getElementById('docker-dns-verify-tls').value === 'true',
      host_ipv4_override: document.getElementById('docker-dns-host-ip').value,
      timeout_seconds: document.getElementById('docker-dns-timeout').value
    };
  }
  function setBusy(busy) {
    root.querySelectorAll('button').forEach(function (button) { button.disabled = busy; });
  }
  function message(text, error) {
    status.textContent = text;
    status.classList.toggle('docker-dns-error', !!error);
  }
  function formatPorts(ports) {
    return (ports || []).map(function (p) { return p.public + '→' + p.private + '/' + p.protocol; }).join(', ');
  }
  function integrationStatus(state) {
    return state.integration_warning ? 'Compatibility warning' : 'No compatibility warning reported';
  }
  function render(data) {
    var settings = data.settings;
    document.getElementById('docker-dns-enabled').value = String(!!settings.enabled);
    document.getElementById('docker-dns-provider').value = settings.provider;
    document.getElementById('docker-dns-base-url').value = settings.base_url || '';
    document.getElementById('docker-dns-username').value = data.credentials.username || '';
    document.getElementById('docker-dns-password').value = '';
    document.getElementById('docker-dns-password').placeholder = data.credentials.password_set ? 'Stored; leave empty to keep it' : 'Required';
    document.getElementById('docker-dns-verify-tls').value = String(!!settings.verify_tls);
    document.getElementById('docker-dns-host-ip').value = settings.host_ipv4_override || '';
    document.getElementById('docker-dns-timeout').value = settings.timeout_seconds || 10;
    toggleProvider();
    var state = data.state;
    var summary = 'Last sync: ' + (state.last_sync || 'never') + '\nLast success: ' + (state.last_success || 'never');
    if (state.last_error) summary += '\nError: ' + state.last_error;
    if (state.integration_warning) summary += '\nMenu integration: ' + state.integration_warning;
    message(summary, !!state.last_error);
    var containers = Object.keys(state.containers || {}).map(function (name) { return state.containers[name]; });
    if (!containers.length) {
      body.innerHTML = '<tr><td colspan="10">No containers with explicit published ports were discovered.</td></tr>';
      return;
    }
    body.innerHTML = containers.map(function (container) {
      var url = container.url_override || '';
      return '<tr data-container="' + escapeHtml(container.name) + '">' +
        '<td><input class="docker-dns-include" type="checkbox" ' + (container.included ? 'checked' : '') + '></td>' +
        '<td>' + escapeHtml(container.name) + '</td><td>' + escapeHtml(formatPorts(container.ports)) + '</td>' +
        '<td>' + escapeHtml(container.hostname) + '</td>' +
        '<td><input class="docker-dns-ip" type="text" value="' + escapeHtml(container.target_status === 'override' ? container.target_ipv4 : '') + '" placeholder="' + escapeHtml(container.target_ipv4 || container.target_status) + '"></td>' +
        '<td><a href="' + escapeHtml(container.automatic_url || '#') + '" target="_blank" rel="noopener">' + escapeHtml(container.automatic_url || 'UDP only') + '</a></td>' +
        '<td><input class="docker-dns-override" type="url" value="' + escapeHtml(url) + '" placeholder="Use automatic URL"></td>' +
        '<td>' + escapeHtml(container.dns_status) + '</td><td>' + escapeHtml(integrationStatus(state)) + '</td>' +
        '<td><button type="button" class="docker-dns-save-container">Save</button></td></tr>';
    }).join('');
  }
  function load() {
    return window.fetch(API + '?action=status', {credentials: 'same-origin', cache: 'no-store'})
      .then(function (response) { return response.json(); })
      .then(function (data) { if (data.ok === false) throw new Error(data.error); render(data); })
      .catch(function (error) { message(error.message, true); });
  }
  function perform(payload, success) {
    setBusy(true);
    message('Working…', false);
    return api(payload).then(function () {
      message(success, false);
      document.dispatchEvent(new CustomEvent('docker-dns:url-saved'));
      return load();
    }).catch(function (error) { message(error.message, true); }).finally(function () { setBusy(false); });
  }
  function toggleProvider() {
    document.getElementById('docker-dns-username-row').style.display =
      document.getElementById('docker-dns-provider').value === 'adguard' ? '' : 'none';
  }
  document.getElementById('docker-dns-provider').addEventListener('change', toggleProvider);
  document.getElementById('docker-dns-save-settings').addEventListener('click', function () { perform(providerPayload('save-settings'), 'Settings saved.'); });
  document.getElementById('docker-dns-test').addEventListener('click', function () { perform(providerPayload('test-connection'), 'Connection succeeded.'); });
  document.getElementById('docker-dns-sync').addEventListener('click', function () { perform({action: 'sync-now'}, 'Synchronization completed.'); });
  document.getElementById('docker-dns-cleanup').addEventListener('click', function () {
    if (window.confirm('Remove every DNS hostname currently managed by Docker DNS?')) perform({action: 'cleanup-all'}, 'Managed DNS records removed.');
  });
  body.addEventListener('click', function (event) {
    if (!event.target.classList.contains('docker-dns-save-container')) return;
    var row = event.target.closest('tr');
    perform({action: 'set-container', container_name: row.dataset.container,
      included: row.querySelector('.docker-dns-include').checked,
      target_ipv4_override: row.querySelector('.docker-dns-ip').value,
      url_override: row.querySelector('.docker-dns-override').value}, 'Container settings saved.');
  });
  load();
})(window, document);

import {beforeEach, describe, expect, it, vi} from 'vitest';
import fs from 'node:fs';

const source = fs.readFileSync('source/traefik.label.manager/javascript/traefik-label-settings.js', 'utf8');
const labelCatalog = [
  {group: 'General', template: 'traefik.enable',
    description: 'Includes or excludes this container from Traefik discovery.', example: 'true'},
  {group: 'HTTP router', template: 'traefik.http.routers.<router_name>.rule',
    description: 'Defines the HTTP rule that matches requests for this router.', example: 'Host(`example.com`)'},
  {group: 'HTTP router', template: 'traefik.http.routers.<router_name>.service',
    description: 'Assigns the named HTTP service to this router.', example: 'myservice'},
  {group: 'HTTP service', template: 'traefik.http.services.<service_name>.loadbalancer.server.port',
    description: 'Selects the container port used by this HTTP service.', example: '8080'},
  {group: 'HTTP middleware', template: 'traefik.http.middlewares.<middleware_name>.<middleware_option>',
    description: 'Defines an HTTP middleware option.', example: 'value'}
];

function response(body, ok = true, status = ok ? 200 : 500) {
  const text = typeof body === 'string' ? body : JSON.stringify(body);
  return Promise.resolve({ok, status, text: () => Promise.resolve(text)});
}

function postedPayload(call) {
  const parameters = new URLSearchParams(call[1].body);
  return {parameters, payload: JSON.parse(parameters.get('payload'))};
}

function container(overrides = {}) {
  return {
    name: 'plex', running: true, status: 'running', template_found: true, pending: true,
    published_ports: [{public_port: 8080, private_port: 80}], default_backend_port: 80,
    labels: [{key: 'traefik.enable', template_value: 'true', active_value: 'false', pending: true}],
    ...overrides
  };
}

async function load(containers = [container()]) {
  document.body.innerHTML = `
    <section id="traefik-label-manager-settings">
      <button id="tlm-refresh"></button><input id="tlm-domain-suffix"><button id="tlm-save-domain"></button>
      <div id="tlm-page-message"></div><div id="tlm-containers"></div>
    </section>
    <div id="tlm-update-modal" hidden><strong id="tlm-update-title"></strong><button id="tlm-update-close"></button>
      <iframe id="tlm-update-frame"></iframe></div>`;
  window.fetch = vi.fn(() => response({ok: true, label_catalog: labelCatalog, containers}));
  window.csrf_token = 'token';
  window.confirm = vi.fn(() => true);
  window.eval(source);
  await new Promise(resolve => window.setTimeout(resolve, 0));
  return document.querySelector('.tlm-container-card');
}

describe('Traefik Label Manager settings page', () => {
  beforeEach(() => {
    window.swal = undefined;
  });

  it('shows template and active values for every container', async () => {
    await load([container(), container({name: 'database', template_found: false, pending: false,
      labels: [{key: 'traefik.enable', template_value: null, active_value: 'true', pending: true}]})]);
    expect([...document.querySelectorAll('.tlm-container-card h3')].map(node => node.textContent)).toEqual(['plex', 'database']);
    expect(document.querySelector('.tlm-label-value').value).toBe('true');
    expect(document.querySelector('.tlm-active-value').textContent).toBe('false');
    expect(document.querySelectorAll('.tlm-badge-pending').length).toBeGreaterThan(0);
    expect(document.querySelectorAll('.tlm-container-card')[1].querySelector('.tlm-label-key').disabled).toBe(true);
  });

  it('identifies a pinned Traefik container in its summary', async () => {
    await load([container({name: 'edge', is_traefik: true, pending: false})]);
    expect(document.querySelector('.tlm-container-header').textContent).toContain('Traefik');
  });

  it('renders containers as compact collapsed sections', async () => {
    const card = await load();
    expect(card.tagName).toBe('DETAILS');
    expect(card.open).toBe(false);
    expect(card.querySelector('summary').textContent).toContain('1 label');
    card.open = true;
    card.dispatchEvent(new Event('toggle'));
    expect(card.querySelector('.tlm-container-content')).not.toBeNull();
    window.fetch.mockImplementationOnce(() => response({ok: true, containers: [container()]}));
    document.getElementById('tlm-refresh').click();
    await new Promise(resolve => window.setTimeout(resolve, 0));
    expect(document.querySelector('.tlm-container-card').open).toBe(true);
  });

  it('starts template values at one row and grows them with their content', async () => {
    const card = await load();
    const value = card.querySelector('.tlm-label-value');
    expect(value.rows).toBe(1);
    Object.defineProperty(value, 'scrollHeight', {configurable: true, value: 96});
    value.dispatchEvent(new Event('input'));
    expect(value.style.height).toBe('96px');
    expect(value.style.overflowY).toBe('hidden');
  });

  it('saves only the edited Traefik labels and CSRF token', async () => {
    const card = await load();
    window.fetch.mockImplementationOnce(() => response({ok: true, container: 'plex', labels: {}}));
    window.fetch.mockImplementationOnce(() => response({ok: true, containers: [container({pending: false})]}));
    card.querySelector('.tlm-label-value').value = 'true';
    card.querySelector('.tlm-save-template').click();
    await new Promise(resolve => window.setTimeout(resolve, 0));
    const call = window.fetch.mock.calls[1];
    const {parameters, payload} = postedPayload(call);
    expect(parameters.get('csrf_token')).toBe('token');
    expect(payload).toEqual({action: 'save', container: 'plex',
      labels: [{key: 'traefik.enable', value: 'true'}]});
  });

  it('rejects arbitrary traefik keys that are not in the Docker label catalog', async () => {
    const card = await load();
    card.querySelector('.tlm-label-key').value = 'traefik.http.routers.plex.not-a-real-option';
    card.querySelector('.tlm-save-template').click();
    await new Promise(resolve => window.setTimeout(resolve, 0));
    expect(window.fetch).toHaveBeenCalledTimes(1);
    expect(document.getElementById('tlm-page-message').textContent).toContain('Traefik Docker label catalog');
  });

  it('only creates catalog labels and explains each label on hover', async () => {
    const card = await load([container({name: 'Plex Media', labels: [], pending: false})]);
    const picker = card.querySelector('.tlm-label-catalog');
    expect([...picker.options].map(option => option.value)).toContain('traefik.http.routers.<router_name>.rule');
    expect([...picker.options].map(option => option.value)).not.toContain('manual.label');
    picker.value = 'traefik.http.routers.<router_name>.rule';
    picker.dispatchEvent(new Event('change'));
    card.querySelector('.tlm-add-label').click();
    const key = card.querySelector('.tlm-label-key');
    expect(key.value).toBe('traefik.http.routers.tlm-plex-media-9b92ff80.rule');
    expect(card.querySelector('.tlm-label-value').value).toBe('Host(`example.com`)');
    const help = card.querySelector('.tlm-label-help');
    expect(help.getAttribute('data-tooltip')).toContain('Example value');
    help.dispatchEvent(new MouseEvent('mouseenter'));
    expect(document.getElementById('tlm-label-tooltip').textContent).toContain('matches requests');
    expect(document.getElementById('tlm-label-tooltip').hidden).toBe(false);
    help.dispatchEvent(new MouseEvent('mouseleave'));
    expect(document.getElementById('tlm-label-tooltip').hidden).toBe(true);
  });

  it('saves and opens Unraid container update for Apply & Restart', async () => {
    const card = await load();
    window.fetch.mockImplementationOnce(() => response({ok: true, container: 'plex', labels: {}}));
    card.querySelector('.tlm-primary').click();
    await new Promise(resolve => window.setTimeout(resolve, 0));
    await new Promise(resolve => window.setTimeout(resolve, 0));
    const {parameters, payload} = postedPayload(window.fetch.mock.calls[1]);
    expect(parameters.get('csrf_token')).toBe('token');
    expect(payload.action).toBe('save');
    expect(document.getElementById('tlm-update-modal').hidden).toBe(false);
    expect(document.getElementById('tlm-update-frame').getAttribute('src')).toContain('CreateDocker.php?updateContainer=true&ct[]=plex');
  });

  it('reports an empty save response without exposing a JSON parser error', async () => {
    const card = await load();
    window.fetch.mockImplementationOnce(() => response('', false, 403));
    card.querySelector('.tlm-primary').click();
    await new Promise(resolve => window.setTimeout(resolve, 0));
    await new Promise(resolve => window.setTimeout(resolve, 0));
    expect(document.getElementById('tlm-page-message').textContent).toContain('server returned an empty response');
    expect(document.getElementById('tlm-page-message').textContent).not.toContain('JSON');
    expect(document.getElementById('tlm-update-modal').hidden).toBe(true);
  });

  it('generates the same default route labels from the Settings page', async () => {
    const card = await load([container({name: 'Plex Media', pending: false, labels: [],
      published_ports: [{public_port: 32400, private_port: 32400}], default_backend_port: 32400})]);
    card.querySelector('.tlm-route-defaults').click();
    const keys = [...card.querySelectorAll('.tlm-label-key')];
    const values = [...card.querySelectorAll('.tlm-label-value')];
    const labels = Object.fromEntries(keys.map((key, index) => [key.value, values[index].value]));
    const id = 'tlm-plex-media-9b92ff80';
    expect(labels).toEqual({
      'traefik.enable': 'true',
      'io.github.lukahummel.traefik-label-manager.router': id,
      'io.github.lukahummel.traefik-label-manager.owns-enable': 'true',
      [`traefik.http.routers.${id}.rule`]: 'Host(`plex-media.home.arpa`)',
      [`traefik.http.routers.${id}.service`]: id,
      [`traefik.http.services.${id}.loadbalancer.server.port`]: '32400'
    });
    expect(document.getElementById('tlm-page-message').textContent).toContain('Default route added');
  });

  it('saves and uses a custom global domain suffix', async () => {
    const card = await load([container({name: 'Plex Media', pending: false, labels: [], default_backend_port: 32400})]);
    document.getElementById('tlm-domain-suffix').value = '.apps.internal';
    window.fetch.mockImplementationOnce(() => response({ok: true, domain_suffix: 'apps.internal'}));
    document.getElementById('tlm-save-domain').click();
    await new Promise(resolve => window.setTimeout(resolve, 0));
    const {parameters, payload} = postedPayload(window.fetch.mock.calls[1]);
    expect(parameters.get('csrf_token')).toBe('token');
    expect(payload).toEqual({action: 'save_settings', domain_suffix: 'apps.internal'});
    card.querySelector('.tlm-route-defaults').click();
    const ruleRow = [...card.querySelectorAll('tbody tr')].find(row => row.querySelector('.tlm-label-key').value.endsWith('.rule'));
    expect(ruleRow.querySelector('.tlm-label-value').value).toBe('Host(`plex-media.apps.internal`)');
  });

  it('renders label contents as text rather than markup', async () => {
    await load([container({labels: [{key: 'traefik.http.routers.test.rule', template_value: '<img src=x onerror=alert(1)>', active_value: null, pending: true}]})]);
    expect(document.querySelector('.tlm-label-value').value).toBe('<img src=x onerror=alert(1)>');
    expect(document.querySelector('.tlm-container-card img')).toBeNull();
  });
});

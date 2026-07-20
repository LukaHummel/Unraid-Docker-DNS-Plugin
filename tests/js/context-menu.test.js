import {beforeEach, describe, expect, it, vi} from 'vitest';
import fs from 'node:fs';

const source = fs.readFileSync('source/docker.dns/javascript/docker-dns-integration.js', 'utf8');

describe('Docker context menu wrapper', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/Docker');
    document.body.innerHTML = '<div id="abc"></div>';
    window.csrf_token = 'token';
    window._ = value => value;
    window.open = vi.fn();
    window.fetch = vi.fn().mockResolvedValue({ok: true, json: () => Promise.resolve({revision: 1, containers: {plex: 'http://plex.home.arpa:32400/web'}})});
  });

  it('preserves native entries and appends a separate link for running containers', async () => {
    let attached;
    const webui = vi.fn();
    const tailscale = vi.fn();
    window.context = {attach: (_selector, options) => { attached = options; }};
    window.addDockerContainerContext = function () {
      window.context.attach('#abc', [{text: 'WebUI', action: webui}, {text: 'Tailscale WebUI', action: tailscale}, {divider: true}, {text: 'Stop'}]);
    };
    window.eval(source);
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await new Promise(resolve => window.setTimeout(resolve, 0));
    window.addDockerContainerContext('plex', '', '', true);
    expect(attached.map(item => item.text || 'divider')).toEqual(['WebUI', 'Tailscale WebUI', 'Docker DNS WebUI', 'divider', 'Stop']);
    expect(attached[0].action).toBe(webui);
    expect(attached[1].action).toBe(tailscale);
    attached[2].action({preventDefault: vi.fn()});
    expect(window.open).toHaveBeenCalledWith('http://plex.home.arpa:32400/web', '_blank', 'noopener');
  });

  it('leaves stopped and unmapped menus unchanged', async () => {
    let attached;
    window.context = {attach: (_selector, options) => { attached = options; }};
    window.addDockerContainerContext = function () { window.context.attach('#abc', [{text: 'Start'}]); };
    window.eval(source);
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await new Promise(resolve => window.setTimeout(resolve, 0));
    window.addDockerContainerContext('plex', '', '', false);
    expect(attached).toEqual([{text: 'Start'}]);
  });
});

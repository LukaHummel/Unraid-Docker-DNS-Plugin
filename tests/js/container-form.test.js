import {beforeEach, describe, expect, it, vi} from 'vitest';
import fs from 'node:fs';

const source = fs.readFileSync('source/docker.dns/javascript/docker-dns-form.js', 'utf8');

describe('container form integration', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/Docker/UpdateContainer');
    document.body.innerHTML = '<form onsubmit="return prepareConfig(this)"><dl><dt>WebUI</dt><dd><input name="contWebUI" value="http://[IP]:[PORT:80]/"></dd></dl>' +
      '<input name="contName" value="plex"><input name="confType[]" value="Port"><input name="confTarget[]" value="80">' +
      '<input name="confValue[]" value="8080"><input name="confMode[]" value="tcp"><input type="submit" value="Apply"><input type="button" onclick="done()" value="Done"></form>';
    window.csrf_token = 'token';
    window.prepareConfig = vi.fn(() => true);
    window.done = vi.fn();
    window.swal = vi.fn();
    window.fetch = vi.fn((_url, options) => Promise.resolve({ok: true, json: () => Promise.resolve(options && options.method === 'POST'
      ? {ok: true} : {url_override: '', automatic_url: 'http://plex.home.arpa:8080/'})}));
  });

  it('injects a nameless plugin-owned input and saves rename metadata', async () => {
    window.eval(source);
    await new Promise(resolve => window.setTimeout(resolve, 0));
    const input = document.getElementById('docker-dns-url');
    expect(input).not.toBeNull();
    expect(input.hasAttribute('name')).toBe(false);
    input.value = 'https://plex.home.arpa/app';
    input.dispatchEvent(new Event('input', {bubbles: true}));
    document.querySelector('[name="contName"]').value = 'plex-new';
    document.getElementById('docker-dns-url-save').click();
    await new Promise(resolve => window.setTimeout(resolve, 0));
    const request = JSON.parse(window.fetch.mock.calls.find(call => call[1] && call[1].method === 'POST')[1].body);
    expect(request.previous_name).toBe('plex');
    expect(request.container_name).toBe('plex-new');
    expect(request.url_override).toBe('https://plex.home.arpa/app');
  });
});

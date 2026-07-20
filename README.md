# Docker DNS for Unraid

Docker DNS is an Unraid 7 plugin that publishes IPv4 `.home.arpa` records for
Docker containers with published ports. It supports one locally hosted
AdGuard Home or Pi-hole v6 instance.

The plugin never edits Docker templates, container labels, or Unraid core
files. A runtime integration adds a separate **Docker DNS WebUI** entry to the
normal Docker and Dashboard context menus. A plugin-owned URL field is also
injected into the Add/Update Container page.

## Installation

Install `docker.dns.plg` from Community Applications or paste its raw GitHub
URL into **Plugins → Install Plugin**.

After installation, open **Settings → Docker DNS**, choose a provider, enter
its local API URL and credentials, test the connection, then enable sync.
Clients must use that AdGuard Home or Pi-hole instance as their DNS resolver.

Pi-hole integration requires Pi-hole v6 and an application password. TLS
certificate validation is enabled by default and should only be disabled for
a trusted local service using a self-signed certificate.

## How records and links are chosen

A container is included only when Docker reports an explicit, non-empty host
port binding. Bridge containers point at the Unraid LAN IPv4 address;
macvlan/ipvlan containers point at their reachable LAN address. A per-container
IPv4 override is available when a container has more than one such address.

Names are normalized to DNS labels below `.home.arpa`, with deterministic hash
suffixes for collisions. URL selection is, in order: the plugin override, a
read-only derivation of `net.unraid.docker.webui`, then the lowest published TCP
port. UDP-only containers still receive an A record but do not get a WebUI
menu item.

The context-menu and container-form additions are runtime browser integrations
loaded by `DockerDnsIntegration.page`. They clone and extend Unraid's menu data
and keep URL inputs outside Docker's submitted form fields. If an Unraid update
changes either interface, the integration fails closed and records a warning
on the settings page.

## Stored data

The only persistent runtime files are under
`/boot/config/plugins/docker.dns/`: `config.json`, `secrets.json`,
`overrides.json`, `state.json`, and `docker.dns.cron`. JSON files are written
atomically with mode `0600`; the directory uses mode `0700`.

## Development

```bash
./test.sh
./build.sh 2026.07.20 1
```

Build output is written to `dist/`. Building requires Docker because the TXZ
is assembled in a Slackware container. The build replaces the development MD5
placeholder in both `dist/docker.dns.plg` and the repository's
`docker.dns.plg`. A `vYYYY.MM.DD` tag runs the release workflow and uploads the
TXZ plus its checksum-bearing plugin file.

PHPUnit covers discovery, address and URL selection, CSRF/override migration,
and both provider reconciliation flows. Vitest/jsdom covers context-menu and
container-form integration. CI additionally runs PHP lint, ShellCheck, XML
validation, and the repository template-write guard.

## Safety guarantees

- No writes below `/boot/config/plugins/dockerMan/`.
- No automatic container recreation or restart.
- DNS records remain while containers are stopped.
- Records are removed when a container disappears or is excluded.
- Provider credentials are stored separately with mode `0600`.

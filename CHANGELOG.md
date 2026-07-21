# Changelog

## 2026.07.21.5

- Add a persistent global domain suffix setting, defaulting to `home.arpa`.
- Show only the editable hostname label in container forms, with the configured
  suffix displayed alongside it.
- Use the configured suffix for routes generated from the Settings page.
- Validate and atomically persist the suffix with CSRF-protected settings updates.
- Replace free-form label creation with Traefik's Docker label catalog and add
  hover/focus help for every catalog entry.
- Fix Save Template and Apply & Restart requests being rejected by Unraid's
  WebGUI CSRF gate, which caused an empty JSON response error.

## 2026.07.21.4

- Detect port and label fields that Unraid adds after the integration loads.
- Restore existing managed-route values from asynchronously loaded fields.
- Explain when a genuinely portless container cannot enable a route.
- Compact and left-align the container-form controls.
- Add a Settings-page Enable Route action with the same automatic hostname,
  router identifier, ownership labels, and preferred backend port as the container form.

## 2026.07.21.3

- Make each container editor expandable and collapsed by default.
- Preserve expanded containers while refreshing saved data.
- Make template value fields compact, full-width, and auto-growing.
- Pin containers using a Traefik image or name above other containers.
- Remove the obsolete identity cleanup and rename repository metadata.

## 2026.07.21.2

- Rename the plugin and package to Traefik Label Manager.
- Add a Network Services page listing every Docker container and its Traefik labels.
- Add validated, atomic editing of Traefik and ownership labels in Unraid templates.
- Show pending differences between saved template labels and active container labels.
- Add Save Template and Apply & Restart actions.

## 2026.07.21.1

- Add opt-in Traefik Docker label management to Unraid container forms.
- Add editable `.home.arpa` hostnames and published backend-port selection.
- Preserve manual labels through plugin-owned routing metadata.

**Traefik Label Manager**

View and edit Traefik Docker labels for every Unraid container.

### Requirements

1. Run Traefik v2 or v3 with its standalone Docker provider.
2. Set `providers.docker.exposedByDefault=false`.
3. Set `providers.docker.useBindPortIP=true`.
4. Give Traefik appropriately secured access to the Docker API.
5. Choose a domain suffix in this plugin's settings and point its wildcard to Traefik's address.
6. Configure Traefik entrypoints, TLS, certificates, redirects, and middlewares as desired.

### Container form

Open an application's **Add/Update Container** form, enable **Traefik route**,
edit only the hostname label shown beside the configured suffix, verify the
backend port, and click **Apply**.

### Network Services page

Open **Settings → Network Services → Traefik Label Manager** to compare the
labels saved in each Unraid template with the labels active on its current
container. Traefik containers are pinned to the top. New labels are selected
from Traefik's Docker-provider catalog; hover or focus the `?` beside a label
for its function and an example. Internal ownership labels are handled by the plugin.

Use **Save Template** for a non-disruptive template update. Use **Apply &
Restart** to save and invoke Unraid's container updater so the labels become
active. A normal Docker restart does not change container labels.

For an unmanaged container, **Enable Route** stages the automatic
`<container>.<domain-suffix>` route and preferred published backend port before save.

Full documentation: https://github.com/LukaHummel/Traefik-Label-Manager#readme

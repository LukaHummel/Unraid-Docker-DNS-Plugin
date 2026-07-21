<?php

declare(strict_types=1);

namespace TraefikLabelManager;

final class LabelCatalog
{
    /** @return list<array{group:string,template:string,description:string,example:string,deprecated?:bool}> */
    public static function definitions(): array
    {
        return [
            self::label('General', 'traefik.enable', 'Includes or excludes this container from Traefik discovery, overriding exposedByDefault.', 'true'),
            self::label('General', 'traefik.docker.allownonrunning', 'Keeps this container discoverable while it is stopped, paused, or exited.', 'true'),
            self::label('General', 'traefik.docker.network', 'Selects the Docker network Traefik uses to connect to this container.', 'proxy'),

            self::label('HTTP router', 'traefik.http.routers.<router_name>.rule', 'Defines the HTTP rule that matches requests for this router.', 'Host(`example.com`)'),
            self::deprecated('HTTP router', 'traefik.http.routers.<router_name>.rulesyntax', 'Selects the router rule syntax. Deprecated by Traefik; rewrite rules using v3 syntax.', 'v3'),
            self::label('HTTP router', 'traefik.http.routers.<router_name>.entrypoints', 'Limits this HTTP router to the named entry points.', 'web,websecure'),
            self::label('HTTP router', 'traefik.http.routers.<router_name>.middlewares', 'Applies the listed HTTP middlewares to this router in order.', 'auth,prefix'),
            self::label('HTTP router', 'traefik.http.routers.<router_name>.service', 'Assigns the named HTTP service to this router.', 'myservice'),
            self::label('HTTP router', 'traefik.http.routers.<router_name>.tls', 'Enables TLS on this HTTP router.', 'true'),
            self::label('HTTP router', 'traefik.http.routers.<router_name>.tls.certresolver', 'Selects the certificate resolver used to obtain TLS certificates.', 'myresolver'),
            self::label('HTTP router', 'traefik.http.routers.<router_name>.tls.domains[n].main', 'Sets the main domain in a TLS certificate request.', 'example.org'),
            self::label('HTTP router', 'traefik.http.routers.<router_name>.tls.domains[n].sans', 'Sets comma-separated subject alternative names for a TLS certificate request.', 'www.example.org,api.example.org'),
            self::label('HTTP router', 'traefik.http.routers.<router_name>.tls.options', 'Selects the TLS options configuration used by this router.', 'default'),
            self::label('HTTP router', 'traefik.http.routers.<router_name>.observability.accesslogs', 'Enables or disables access logs for this router.', 'true'),
            self::label('HTTP router', 'traefik.http.routers.<router_name>.observability.metrics', 'Enables or disables metrics for this router.', 'true'),
            self::label('HTTP router', 'traefik.http.routers.<router_name>.observability.tracing', 'Enables or disables tracing for this router.', 'true'),
            self::label('HTTP router', 'traefik.http.routers.<router_name>.priority', 'Sets the router priority used when multiple HTTP rules match.', '42'),

            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.server.port', 'Selects the container port used by this HTTP service.', '8080'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.server.scheme', 'Overrides the scheme used to connect to this HTTP service.', 'http'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.server.url', 'Sets the complete service URL; it cannot be combined with server port or scheme.', 'http://backend:8080'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.serverstransport', 'Selects a ServersTransport resource for backend connections.', 'transport@file'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.passhostheader', 'Controls whether the client Host header is forwarded to the backend.', 'true'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.healthcheck.headers.<header_name>', 'Adds a request header to active health checks.', 'value'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.healthcheck.hostname', 'Overrides the Host header used for active health checks.', 'example.org'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.healthcheck.interval', 'Sets the interval between active health checks.', '10s'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.healthcheck.unhealthyinterval', 'Sets the health-check interval while the backend is unhealthy.', '10s'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.healthcheck.path', 'Sets the request path used for active health checks.', '/health'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.healthcheck.method', 'Sets the HTTP method used for active health checks.', 'GET'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.healthcheck.status', 'Sets the expected HTTP status code for a healthy response.', '200'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.healthcheck.port', 'Overrides the backend port used for active health checks.', '8080'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.healthcheck.scheme', 'Overrides the scheme used for active health checks.', 'http'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.healthcheck.timeout', 'Sets the maximum duration of an active health check.', '5s'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.healthcheck.followredirects', 'Controls whether active health checks follow redirects.', 'true'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.sticky.cookie', 'Enables cookie-based sticky sessions.', 'true'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.sticky.cookie.httponly', 'Adds the HttpOnly attribute to the sticky-session cookie.', 'true'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.sticky.cookie.name', 'Sets the sticky-session cookie name.', 'traefik_session'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.sticky.cookie.path', 'Sets the Path attribute of the sticky-session cookie.', '/'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.sticky.cookie.secure', 'Adds the Secure attribute to the sticky-session cookie.', 'true'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.sticky.cookie.samesite', 'Sets the SameSite attribute of the sticky-session cookie.', 'none'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.sticky.cookie.maxage', 'Sets the sticky-session cookie lifetime in seconds.', '3600'),
            self::label('HTTP service', 'traefik.http.services.<service_name>.loadbalancer.responseforwarding.flushinterval', 'Sets how often buffered response data is flushed to the client.', '100ms'),
            self::label('HTTP middleware', 'traefik.http.middlewares.<middleware_name>.<middleware_option>', 'Defines an HTTP middleware option. Use a middleware type and option from Traefik’s HTTP middleware reference.', 'value'),

            self::label('TCP router', 'traefik.tcp.routers.<router_name>.entrypoints', 'Limits this TCP router to the named entry points.', 'tcp'),
            self::label('TCP router', 'traefik.tcp.routers.<router_name>.rule', 'Defines the TCP rule that matches connections for this router.', 'HostSNI(`example.com`)'),
            self::deprecated('TCP router', 'traefik.tcp.routers.<router_name>.rulesyntax', 'Selects the TCP router rule syntax. Deprecated by Traefik; rewrite rules using v3 syntax.', 'v3'),
            self::label('TCP router', 'traefik.tcp.routers.<router_name>.service', 'Assigns the named TCP service to this router.', 'myservice'),
            self::label('TCP router', 'traefik.tcp.routers.<router_name>.tls', 'Enables TLS termination on this TCP router.', 'true'),
            self::label('TCP router', 'traefik.tcp.routers.<router_name>.tls.certresolver', 'Selects the certificate resolver used by this TCP router.', 'myresolver'),
            self::label('TCP router', 'traefik.tcp.routers.<router_name>.tls.domains[n].main', 'Sets the main domain in a TCP router TLS certificate request.', 'example.org'),
            self::label('TCP router', 'traefik.tcp.routers.<router_name>.tls.domains[n].sans', 'Sets comma-separated alternative names for a TCP router certificate.', 'www.example.org'),
            self::label('TCP router', 'traefik.tcp.routers.<router_name>.tls.options', 'Selects the TLS options configuration used by this TCP router.', 'default'),
            self::label('TCP router', 'traefik.tcp.routers.<router_name>.tls.passthrough', 'Passes the TLS connection through without terminating it in Traefik.', 'true'),
            self::label('TCP router', 'traefik.tcp.routers.<router_name>.priority', 'Sets the TCP router priority when multiple rules match.', '42'),
            self::label('TCP service', 'traefik.tcp.services.<service_name>.loadbalancer.server.port', 'Selects the container port used by this TCP service.', '423'),
            self::label('TCP service', 'traefik.tcp.services.<service_name>.loadbalancer.server.tls', 'Uses TLS when Traefik connects to the TCP backend.', 'true'),
            self::label('TCP service', 'traefik.tcp.services.<service_name>.loadbalancer.serverstransport', 'Selects a TCP ServersTransport resource for backend connections.', 'transport@file'),
            self::label('TCP middleware', 'traefik.tcp.middlewares.<middleware_name>.<middleware_option>', 'Defines a TCP middleware option from Traefik’s TCP middleware reference.', 'value'),

            self::label('UDP router', 'traefik.udp.routers.<router_name>.entrypoints', 'Limits this UDP router to the named entry points.', 'udp'),
            self::label('UDP router', 'traefik.udp.routers.<router_name>.service', 'Assigns the named UDP service to this router.', 'myservice'),
            self::label('UDP service', 'traefik.udp.services.<service_name>.loadbalancer.server.port', 'Selects the container port used by this UDP service.', '423'),
        ];
    }

    public static function matches(string $key): bool
    {
        if (strlen($key) > 255) return false;
        foreach (self::definitions() as $definition) {
            if (preg_match(self::pattern($definition['template']), $key) === 1) return true;
        }
        return false;
    }

    /** @return array{group:string,template:string,description:string,example:string} */
    private static function label(string $group, string $template, string $description, string $example): array
    {
        return compact('group', 'template', 'description', 'example');
    }

    /** @return array{group:string,template:string,description:string,example:string,deprecated:true} */
    private static function deprecated(string $group, string $template, string $description, string $example): array
    {
        return compact('group', 'template', 'description', 'example') + ['deprecated' => true];
    }

    private static function pattern(string $template): string
    {
        $tokens = [
            '<router_name>' => '[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?',
            '<service_name>' => '[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?',
            '<middleware_name>' => '[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?',
            '<header_name>' => '[a-z0-9](?:[a-z0-9-]*[a-z0-9])?',
            '<middleware_option>' => '[a-z0-9](?:[a-z0-9_.\[\]-]*[a-z0-9\]])?',
            '[n]' => '\\[\d+\\]',
        ];
        $parts = preg_split('/(<(?:router_name|service_name|middleware_name|header_name|middleware_option)>|\[n\])/', $template, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $pattern = '';
        foreach ($parts as $part) $pattern .= $tokens[$part] ?? preg_quote($part, '/');
        return '/^' . $pattern . '$/';
    }
}

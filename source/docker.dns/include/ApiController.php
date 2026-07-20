<?php

declare(strict_types=1);

namespace DockerDns;

use InvalidArgumentException;
use RuntimeException;

final class ApiController
{
    public function __construct(private readonly Config $config, private readonly SyncEngine $sync)
    {
    }

    /** @return array<string,mixed> */
    public function get(string $action, array $query): array
    {
        return match ($action) {
            'context-urls' => $this->contextUrls(),
            'status' => $this->status(true),
            'container-url' => $this->containerUrl((string)($query['container_name'] ?? '')),
            default => throw new InvalidArgumentException('Unknown API action.'),
        };
    }

    /** @return array<string,mixed> */
    public function post(array $input): array
    {
        // Unraid validates and removes the standard form field before this script runs.
        $this->validateCsrf((string)($input['docker_dns_csrf_token'] ?? $input['csrf_token'] ?? ''));
        return match ((string)($input['action'] ?? '')) {
            'save-container-url' => $this->saveContainerUrl($input),
            'set-container' => $this->setContainer($input),
            'save-settings' => $this->saveSettings($input),
            'test-connection' => $this->testConnection($input),
            'sync-now' => ['ok' => true, 'state' => $this->sync->sync(true)],
            'cleanup-all' => $this->cleanupAll(),
            'integration-warning' => $this->integrationWarning((string)($input['message'] ?? '')),
            default => throw new InvalidArgumentException('Unknown API action.'),
        };
    }

    /** @return array<string,mixed> */
    private function contextUrls(): array
    {
        $state = $this->config->state();
        return ['revision' => (int)$state['revision'], 'containers' => (array)$state['context_urls']];
    }

    /** @return array<string,mixed> */
    private function status(bool $refresh): array
    {
        if ($refresh) {
            try {
                $this->sync->preview();
            } catch (\Throwable $error) {
                Logger::warning('Status discovery failed: ' . $error->getMessage());
            }
        }
        $settings = $this->config->settings();
        $secrets = $this->config->secrets();
        return [
            'settings' => $settings,
            'credentials' => [
                'username' => (string)($secrets['username'] ?? ''),
                'password_set' => (string)($secrets['password'] ?? '') !== '',
            ],
            'state' => $this->config->state(),
            'overrides_revision' => (int)($this->config->overrides()['revision'] ?? 0),
        ];
    }

    /** @return array<string,mixed> */
    private function containerUrl(string $name): array
    {
        $state = $this->sync->preview();
        $container = $state['containers'][$name] ?? null;
        $entry = $this->config->overrides()['containers'][$name] ?? [];
        return [
            'container_name' => $name,
            'url_override' => (string)($entry['url_override'] ?? ''),
            'automatic_url' => (string)($container['automatic_url'] ?? ''),
            'hostname' => (string)($container['hostname'] ?? (Hostname::label($name) . '.home.arpa')),
        ];
    }

    /** @return array<string,mixed> */
    private function saveContainerUrl(array $input): array
    {
        $newName = $this->validateContainerName((string)($input['container_name'] ?? ''));
        $previousName = trim((string)($input['previous_name'] ?? ''));
        if ($previousName !== '') {
            $previousName = $this->validateContainerName($previousName);
        }
        $url = Url::validateOverride((string)($input['url_override'] ?? ''));
        $overrides = $this->config->overrides();
        $entries = (array)($overrides['containers'] ?? []);
        $existing = is_array($entries[$previousName ?: $newName] ?? null) ? $entries[$previousName ?: $newName] : [];
        if ($previousName !== '' && $previousName !== $newName) {
            unset($entries[$previousName]);
        }
        if ($url === '') {
            unset($existing['url_override']);
        } else {
            $existing['url_override'] = $url;
        }
        if ($existing === []) {
            unset($entries[$newName]);
        } else {
            $entries[$newName] = $existing;
        }
        $overrides['containers'] = $entries;
        $overrides['revision'] = (int)($overrides['revision'] ?? 0) + 1;
        $this->config->saveOverrides($overrides);
        $state = $this->sync->preview();
        return ['ok' => true, 'revision' => $state['revision'], 'url_override' => $url];
    }

    /** @return array<string,mixed> */
    private function setContainer(array $input): array
    {
        $name = $this->validateContainerName((string)($input['container_name'] ?? ''));
        $overrides = $this->config->overrides();
        $entry = is_array($overrides['containers'][$name] ?? null) ? $overrides['containers'][$name] : [];
        if (array_key_exists('included', $input)) {
            $entry['included'] = filter_var($input['included'], FILTER_VALIDATE_BOOL);
        }
        if (array_key_exists('target_ipv4_override', $input)) {
            $ip = trim((string)$input['target_ipv4_override']);
            if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new InvalidArgumentException('Target override must be a valid IPv4 address.');
            }
            if ($ip === '') {
                unset($entry['target_ipv4_override']);
            } else {
                $entry['target_ipv4_override'] = $ip;
            }
        }
        if (array_key_exists('url_override', $input)) {
            $url = Url::validateOverride((string)$input['url_override']);
            if ($url === '') {
                unset($entry['url_override']);
            } else {
                $entry['url_override'] = $url;
            }
        }
        $overrides['containers'][$name] = $entry;
        $overrides['revision'] = (int)($overrides['revision'] ?? 0) + 1;
        $this->config->saveOverrides($overrides);
        $state = ($this->config->settings()['enabled'] ?? false) ? $this->sync->sync() : $this->sync->preview();
        return ['ok' => true, 'state' => $state];
    }

    /** @return array<string,mixed> */
    private function saveSettings(array $input): array
    {
        [$settings, $secrets] = $this->validatedProviderInput($input);
        $oldSettings = $this->config->settings();
        $oldSecrets = $this->config->secrets();
        $state = $this->config->state();
        if ((array)$state['records'] !== [] && Config::providerIdentity($oldSettings) !== Config::providerIdentity($settings)) {
            $this->sync->cleanup($oldSettings, $oldSecrets);
        }
        $this->config->saveSettings($settings);
        $this->config->saveSecrets($secrets);
        return ['ok' => true, 'status' => $this->status(false)];
    }

    /** @return array<string,mixed> */
    private function testConnection(array $input): array
    {
        [$settings, $secrets] = $this->validatedProviderInput($input);
        $this->sync->testProvider($settings, $secrets);
        return ['ok' => true, 'message' => 'Connection succeeded.'];
    }

    /** @return array<string,mixed> */
    private function cleanupAll(): array
    {
        $this->sync->cleanup($this->config->settings(), $this->config->secrets());
        return ['ok' => true, 'state' => $this->config->state()];
    }

    /** @return array<string,mixed> */
    private function integrationWarning(string $message): array
    {
        $state = $this->config->state();
        $state['integration_warning'] = substr(str_replace(["\r", "\n"], ' ', $message), 0, 500);
        $this->config->saveState($state);
        if ($state['integration_warning'] !== '') {
            Logger::warning('UI compatibility: ' . $state['integration_warning']);
        }
        return ['ok' => true];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function validatedProviderInput(array $input): array
    {
        $currentSettings = $this->config->settings();
        $currentSecrets = $this->config->secrets();
        $provider = (string)($input['provider'] ?? $currentSettings['provider']);
        if (!in_array($provider, ['adguard', 'pihole'], true)) {
            throw new InvalidArgumentException('Provider must be AdGuard Home or Pi-hole.');
        }
        $baseUrl = rtrim(trim((string)($input['base_url'] ?? $currentSettings['base_url'])), '/');
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Provider base URL must be an http(s) URL without credentials.');
        }
        $hostIp = trim((string)($input['host_ipv4_override'] ?? $currentSettings['host_ipv4_override']));
        if ($hostIp !== '' && !filter_var($hostIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new InvalidArgumentException('Unraid override must be a valid IPv4 address.');
        }
        $settings = [
            'schema' => 1,
            'enabled' => filter_var($input['enabled'] ?? $currentSettings['enabled'], FILTER_VALIDATE_BOOL),
            'provider' => $provider,
            'base_url' => $baseUrl,
            'verify_tls' => filter_var($input['verify_tls'] ?? $currentSettings['verify_tls'], FILTER_VALIDATE_BOOL),
            'timeout_seconds' => max(2, min(60, (int)($input['timeout_seconds'] ?? $currentSettings['timeout_seconds']))),
            'host_ipv4_override' => $hostIp,
        ];
        $password = (string)($input['password'] ?? '');
        $secrets = [
            'username' => trim((string)($input['username'] ?? $currentSecrets['username'])),
            'password' => $password !== '' ? $password : (string)$currentSecrets['password'],
        ];
        return [$settings, $secrets];
    }

    private function validateContainerName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 255 || preg_match('/[\x00-\x1F\x7F\/]/', $name)) {
            throw new InvalidArgumentException('Invalid container name.');
        }
        return $name;
    }

    private function validateCsrf(string $submitted): void
    {
        $expected = getenv('DOCKER_DNS_CSRF_TOKEN');
        if ($expected === false) {
            $ini = @parse_ini_file('/var/local/emhttp/var.ini');
            $expected = is_array($ini) ? (string)($ini['csrf_token'] ?? '') : '';
        }
        if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
            throw new CsrfException('CSRF validation failed.');
        }
    }
}

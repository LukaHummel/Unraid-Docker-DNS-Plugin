<?php

declare(strict_types=1);

namespace DockerDns;

use RuntimeException;
use Throwable;

final class SyncEngine
{
    public function __construct(
        private readonly Config $config,
        private readonly DockerDiscovery $discovery = new DockerDiscovery(),
        private readonly ProviderFactory $providers = new ProviderFactory(),
    ) {
    }

    /** @return array<string,mixed> */
    public function preview(): array
    {
        $settings = $this->config->settings();
        $overrides = $this->config->overrides();
        $state = $this->config->state();
        $containers = $this->discovery->discover($settings, $overrides, $state);
        return $this->writeDiscoveryState($state, $containers);
    }

    /** @return array<string,mixed> */
    public function sync(bool $force = false): array
    {
        return $this->withLock(function () use ($force): array {
            $settings = $this->config->settings();
            $secrets = $this->config->secrets();
            $overrides = $this->config->overrides();
            $state = $this->config->state();
            $containers = $this->discovery->discover($settings, $overrides, $state);
            $state = $this->writeDiscoveryState($state, $containers, false);
            $state['last_sync'] = gmdate(DATE_ATOM);
            if (!$force && !($settings['enabled'] ?? false)) {
                $state['last_error'] = '';
                $this->config->saveState($state);
                return $state;
            }
            $desired = [];
            foreach ($containers as $container) {
                if ($container['included'] && filter_var($container['target_ipv4'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $desired[strtolower((string)$container['hostname'])] = (string)$container['target_ipv4'];
                }
            }
            $previous = is_array($state['records'] ?? null) ? $state['records'] : [];
            $remove = array_values(array_diff(array_keys($previous), array_keys($desired)));
            try {
                $provider = $this->providers->create($settings, $secrets);
                $provider->reconcile($desired, $remove);
                $state['records'] = $desired;
                $state['provider_identity'] = Config::providerIdentity($settings);
                $state['last_success'] = gmdate(DATE_ATOM);
                $state['last_error'] = '';
                foreach ($state['containers'] as &$containerState) {
                    if (!($containerState['included'] ?? false)) {
                        $containerState['dns_status'] = 'excluded';
                    } elseif (isset($desired[$containerState['hostname'] ?? ''])) {
                        $containerState['dns_status'] = 'synchronized';
                    } else {
                        $containerState['dns_status'] = (string)($containerState['target_status'] ?? 'waiting for an IPv4 address');
                    }
                }
                unset($containerState);
            } catch (Throwable $error) {
                $state['last_error'] = $error->getMessage();
                $this->config->saveState($state);
                Logger::error($error->getMessage());
                throw $error;
            }
            $this->config->saveState($state);
            return $state;
        });
    }

    public function testProvider(array $settings, array $secrets): void
    {
        $this->providers->create($settings, $secrets)->test();
    }

    public function cleanup(array $settings, array $secrets, bool $clearState = true): void
    {
        $this->withLock(function () use ($settings, $secrets, $clearState): void {
            $state = $this->config->state();
            $records = is_array($state['records'] ?? null) ? $state['records'] : [];
            if ($records !== []) {
                $this->providers->create($settings, $secrets)->reconcile([], array_keys($records));
            }
            if ($clearState) {
                $state['records'] = [];
                $state['last_sync'] = gmdate(DATE_ATOM);
                $state['last_success'] = gmdate(DATE_ATOM);
                $state['last_error'] = '';
                $this->config->saveState($state);
            }
        });
    }

    /** @param list<array<string,mixed>> $containers @return array<string,mixed> */
    private function writeDiscoveryState(array $state, array $containers, bool $save = true): array
    {
        $contextUrls = [];
        $indexed = [];
        foreach ($containers as $container) {
            $indexed[$container['name']] = [
                'name' => $container['name'],
                'running' => $container['running'],
                'included' => $container['included'],
                'ports' => $container['ports'],
                'hostname' => $container['hostname'],
                'target_ipv4' => $container['target_ipv4'],
                'target_status' => $container['target_status'],
                'automatic_url' => $container['automatic_url'],
                'url_override' => $container['url_override'],
                'url' => $container['url'],
                'dns_status' => (string)($state['containers'][$container['name']]['dns_status'] ?? 'pending'),
            ];
            if ($container['running'] && $container['included'] && is_string($container['url']) && $container['url'] !== '') {
                $contextUrls[$container['name']] = $container['url'];
            }
        }
        $state['revision'] = (int)($state['revision'] ?? 0) + 1;
        $state['containers'] = $indexed;
        $state['context_urls'] = $contextUrls;
        if ($save) {
            $this->config->saveState($state);
        }
        return $state;
    }

    /** @template T @param callable():T $operation @return T */
    private function withLock(callable $operation): mixed
    {
        $lockPath = getenv('DOCKER_DNS_LOCK_FILE') ?: '/var/run/docker.dns.sync.lock';
        $handle = fopen($lockPath, 'c');
        if ($handle === false) {
            throw new RuntimeException("Cannot open synchronization lock: $lockPath");
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot acquire synchronization lock.');
            }
            return $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}


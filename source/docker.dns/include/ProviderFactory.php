<?php

declare(strict_types=1);

namespace DockerDns;

use DockerDns\providers\AdGuardProvider;
use DockerDns\providers\DnsProvider;
use DockerDns\providers\PiHoleProvider;
use InvalidArgumentException;

final class ProviderFactory
{
    public function __construct(private readonly HttpClient $http = new HttpClient())
    {
    }

    public function create(array $settings, array $secrets): DnsProvider
    {
        $baseUrl = rtrim(trim((string)($settings['base_url'] ?? '')), '/');
        if (!preg_match('#^https?://[^/]+#i', $baseUrl)) {
            throw new InvalidArgumentException('A valid provider API base URL is required.');
        }
        $verifyTls = (bool)($settings['verify_tls'] ?? true);
        $timeout = max(2, min(60, (int)($settings['timeout_seconds'] ?? 10)));
        return match ((string)($settings['provider'] ?? '')) {
            'adguard' => new AdGuardProvider(
                $this->http,
                $baseUrl,
                (string)($secrets['username'] ?? ''),
                (string)($secrets['password'] ?? ''),
                $verifyTls,
                $timeout,
            ),
            'pihole' => new PiHoleProvider(
                $this->http,
                $baseUrl,
                (string)($secrets['password'] ?? ''),
                $verifyTls,
                $timeout,
            ),
            default => throw new InvalidArgumentException('Unsupported DNS provider.'),
        };
    }
}


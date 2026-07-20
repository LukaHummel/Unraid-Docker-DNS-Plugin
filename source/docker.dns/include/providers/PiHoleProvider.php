<?php

declare(strict_types=1);

namespace DockerDns\providers;

use DockerDns\HttpClient;
use RuntimeException;

final class PiHoleProvider implements DnsProvider
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $baseUrl,
        private readonly string $password,
        private readonly bool $verifyTls,
        private readonly int $timeout,
    ) {
    }

    public function test(): void
    {
        $sid = $this->authenticate();
        try {
            $this->request('GET', '/api/config/dns/hosts', null, $sid);
        } finally {
            $this->logout($sid);
        }
    }

    public function reconcile(array $desired, array $remove): void
    {
        $sid = $this->authenticate();
        try {
            $response = $this->request('GET', '/api/config/dns/hosts', null, $sid);
            $hosts = $response['config']['dns']['hosts'] ?? $response['dns']['hosts'] ?? $response['hosts'] ?? null;
            if (!is_array($hosts)) {
                throw new RuntimeException('Pi-hole returned an invalid dns.hosts response.');
            }
            $targets = array_fill_keys(array_map('strtolower', array_unique(array_merge(array_keys($desired), $remove))), true);
            $updated = [];
            foreach ($hosts as $line) {
                $tokens = preg_split('/\s+/', trim((string)$line)) ?: [];
                if (count($tokens) < 2) {
                    if (trim((string)$line) !== '') {
                        $updated[] = (string)$line;
                    }
                    continue;
                }
                $ip = array_shift($tokens);
                $tokens = array_values(array_filter($tokens, static fn(string $host): bool => !isset($targets[strtolower(rtrim($host, '.'))])));
                if ($tokens !== []) {
                    $updated[] = $ip . ' ' . implode(' ', $tokens);
                }
            }
            foreach ($desired as $hostname => $ip) {
                $updated[] = $ip . ' ' . $hostname;
            }
            $this->request('PATCH', '/api/config', ['config' => ['dns' => ['hosts' => array_values($updated)]]], $sid);
            $verification = $this->request('GET', '/api/config/dns/hosts', null, $sid);
            $verifiedHosts = $verification['config']['dns']['hosts'] ?? $verification['dns']['hosts'] ?? $verification['hosts'] ?? [];
            foreach ($desired as $hostname => $ip) {
                $matches = $this->hostnameMatches((array)$verifiedHosts, $hostname);
                if ($matches !== [$ip]) {
                    throw new RuntimeException("Pi-hole did not retain exactly one $hostname entry with the requested IPv4 address.");
                }
            }
            foreach ($remove as $hostname) {
                if ($this->hostnameMatches((array)$verifiedHosts, $hostname) !== []) {
                    throw new RuntimeException("Pi-hole did not remove $hostname.");
                }
            }
        } finally {
            $this->logout($sid);
        }
    }

    /** @param list<mixed> $lines @return list<string> */
    private function hostnameMatches(array $lines, string $hostname): array
    {
        $matches = [];
        foreach ($lines as $line) {
            $tokens = preg_split('/\s+/', trim((string)$line)) ?: [];
            $ip = array_shift($tokens);
            foreach ($tokens as $candidate) {
                if (strtolower(rtrim($candidate, '.')) === strtolower(rtrim($hostname, '.'))) $matches[] = (string)$ip;
            }
        }
        return $matches;
    }

    private function authenticate(): string
    {
        $response = $this->request('POST', '/api/auth', ['password' => $this->password], null);
        if (!($response['session']['valid'] ?? false) || empty($response['session']['sid'])) {
            throw new RuntimeException('Pi-hole authentication failed.');
        }
        return (string)$response['session']['sid'];
    }

    private function logout(string $sid): void
    {
        try {
            $this->request('DELETE', '/api/auth', null, $sid);
        } catch (RuntimeException) {
            // Session expiry is harmless after the requested operation completed.
        }
    }

    /** @return mixed */
    private function request(string $method, string $path, ?array $payload, ?string $sid): mixed
    {
        $headers = $sid !== null ? ['X-FTL-SID' => $sid] : [];
        return $this->http->request($method, rtrim($this->baseUrl, '/') . $path, $payload, $headers, $this->verifyTls, $this->timeout)['body'];
    }
}

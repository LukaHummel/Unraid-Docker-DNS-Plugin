<?php

declare(strict_types=1);

namespace DockerDns\providers;

use DockerDns\HttpClient;
use RuntimeException;

final class AdGuardProvider implements DnsProvider
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly bool $verifyTls,
        private readonly int $timeout,
    ) {
    }

    public function test(): void
    {
        $response = $this->request('GET', '/control/status');
        if (!is_array($response)) {
            throw new RuntimeException('AdGuard Home returned an invalid status response.');
        }
    }

    public function reconcile(array $desired, array $remove): void
    {
        $listed = $this->request('GET', '/control/rewrite/list');
        if (!is_array($listed)) {
            throw new RuntimeException('AdGuard Home returned an invalid rewrite list.');
        }
        $targets = array_fill_keys(array_unique(array_merge(array_keys($desired), $remove)), true);
        $grouped = [];
        foreach ($listed as $rewrite) {
            $domain = strtolower((string)($rewrite['domain'] ?? ''));
            if (isset($targets[$domain])) $grouped[$domain][] = $rewrite;
        }
        foreach ($targets as $domain => $_) {
            $existing = $grouped[$domain] ?? [];
            if (!isset($desired[$domain])) {
                foreach ($existing as $rewrite) $this->delete($rewrite);
                continue;
            }
            $answer = $desired[$domain];
            $keeper = null;
            foreach ($existing as $index => $rewrite) {
                if ($keeper === null && (string)($rewrite['answer'] ?? '') === $answer && ($rewrite['enabled'] ?? true)) {
                    $keeper = $index;
                }
            }
            if ($keeper !== null) {
                foreach ($existing as $index => $rewrite) if ($index !== $keeper) $this->delete($rewrite);
            } elseif ($existing !== []) {
                $first = array_shift($existing);
                $this->request('PUT', '/control/rewrite/update', [
                    'target' => ['domain' => (string)$first['domain'], 'answer' => (string)($first['answer'] ?? '')],
                    'update' => ['domain' => $domain, 'answer' => $answer, 'enabled' => true],
                ]);
                foreach ($existing as $rewrite) $this->delete($rewrite);
            } else {
                $this->request('POST', '/control/rewrite/add', ['domain' => $domain, 'answer' => $answer, 'enabled' => true]);
            }
        }
        $verify = $this->request('GET', '/control/rewrite/list');
        foreach ($desired as $domain => $answer) {
            $matches = [];
            foreach ((array)$verify as $rewrite) {
                if (strtolower((string)($rewrite['domain'] ?? '')) === $domain) $matches[] = $rewrite;
            }
            if (count($matches) !== 1 || (string)($matches[0]['answer'] ?? '') !== $answer || !($matches[0]['enabled'] ?? true)) {
                throw new RuntimeException("AdGuard Home did not retain exactly one enabled A rewrite for $domain.");
            }
        }
        foreach ($remove as $domain) {
            foreach ((array)$verify as $rewrite) {
                if (strtolower((string)($rewrite['domain'] ?? '')) === strtolower($domain)) {
                    throw new RuntimeException("AdGuard Home did not remove $domain.");
                }
            }
        }
    }

    private function delete(array $rewrite): void
    {
        $this->request('POST', '/control/rewrite/delete', [
            'domain' => (string)$rewrite['domain'],
            'answer' => (string)($rewrite['answer'] ?? ''),
        ]);
    }

    /** @return mixed */
    private function request(string $method, string $path, ?array $payload = null): mixed
    {
        $auth = $this->username . ':' . $this->password;
        return $this->http->request($method, rtrim($this->baseUrl, '/') . $path, $payload, [], $this->verifyTls, $this->timeout, $auth)['body'];
    }
}

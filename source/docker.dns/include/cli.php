#!/usr/bin/php
<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DockerDns\Config;
use DockerDns\DockerDiscovery;
use DockerDns\JsonStore;
use DockerDns\ProviderFactory;
use DockerDns\SyncEngine;

$config = new Config(new JsonStore(docker_dns_config_dir()));
$engine = new SyncEngine($config, new DockerDiscovery(), new ProviderFactory());
$command = $argv[1] ?? 'sync';

try {
    $result = match ($command) {
        'init' => (function () use ($config): array {
            $config->initialize();
            return ['initialized' => true];
        })(),
        'sync' => $engine->sync(false),
        'sync-force' => $engine->sync(true),
        'preview' => $engine->preview(),
        'cleanup' => (function () use ($engine, $config): array {
            $engine->cleanup($config->settings(), $config->secrets());
            return $config->state();
        })(),
        default => throw new InvalidArgumentException("Unknown command: $command"),
    };
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'docker.dns: ' . $error->getMessage() . "\n");
    exit(1);
}

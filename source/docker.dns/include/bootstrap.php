<?php

declare(strict_types=1);

const DOCKER_DNS_ROOT = __DIR__ . '/..';

spl_autoload_register(static function (string $class): void {
    $prefix = 'DockerDns\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = __DIR__ . '/' . $relative . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

function docker_dns_config_dir(): string
{
    return getenv('DOCKER_DNS_CONFIG_DIR') ?: '/boot/config/plugins/docker.dns';
}


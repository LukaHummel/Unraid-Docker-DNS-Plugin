<?php

declare(strict_types=1);

const TRAEFIK_LABEL_MANAGER_ROOT = __DIR__ . '/..';

spl_autoload_register(static function (string $class): void {
    $prefix = 'TraefikLabelManager\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = __DIR__ . '/' . $relative . '.php';
    if (is_file($path)) require_once $path;
});

function traefik_label_manager_template_dir(): string
{
    return getenv('TRAEFIK_LABEL_MANAGER_TEMPLATE_DIR') ?: '/boot/config/plugins/dockerMan/templates-user';
}

function traefik_label_manager_lock_file(): string
{
    return getenv('TRAEFIK_LABEL_MANAGER_LOCK_FILE') ?: '/var/lock/traefik-label-manager.lock';
}

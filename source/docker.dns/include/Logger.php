<?php

declare(strict_types=1);

namespace DockerDns;

final class Logger
{
    public static function info(string $message): void
    {
        self::write('info', $message);
    }

    public static function warning(string $message): void
    {
        self::write('warning', $message);
    }

    public static function error(string $message): void
    {
        self::write('error', $message);
    }

    private static function write(string $level, string $message): void
    {
        $clean = str_replace(["\r", "\n"], ' ', $message);
        if (function_exists('openlog')) {
            openlog('docker.dns', LOG_PID, LOG_DAEMON);
            syslog(match ($level) {
                'error' => LOG_ERR,
                'warning' => LOG_WARNING,
                default => LOG_INFO,
            }, $clean);
            closelog();
        }
        if (PHP_SAPI === 'cli' && getenv('DOCKER_DNS_QUIET') !== '1') {
            fwrite(STDERR, "docker.dns [$level] $clean\n");
        }
    }
}


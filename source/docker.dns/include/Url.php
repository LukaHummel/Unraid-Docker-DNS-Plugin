<?php

declare(strict_types=1);

namespace DockerDns;

use InvalidArgumentException;

final class Url
{
    public static function validateOverride(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('/[\x00-\x1F\x7F]|%(?:0[0-9a-f]|1[0-9a-f]|7f)/i', $url)) {
            throw new InvalidArgumentException('URL contains control characters.');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            throw new InvalidArgumentException('URL must use http or https.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('URL user information and fragments are not allowed.');
        }
        $host = strtolower((string)($parts['host'] ?? ''));
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+home\.arpa$/', $host)) {
            throw new InvalidArgumentException('URL host must be a valid name below .home.arpa.');
        }
        if (isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535)) {
            throw new InvalidArgumentException('URL port must be between 1 and 65535.');
        }
        return $url;
    }

    /** @param list<array{private:int,public:int,protocol:string}> $ports */
    public static function automatic(string $hostname, array $ports, string $webuiLabel = '', bool $useContainerPort = false): ?string
    {
        $tcp = array_values(array_filter($ports, static fn(array $p): bool => strtolower($p['protocol']) === 'tcp'));
        if ($tcp === []) {
            return null;
        }
        usort($tcp, static fn(array $a, array $b): int => $a['public'] <=> $b['public']);
        if ($webuiLabel !== '') {
            $derived = self::fromLabel($webuiLabel, $hostname, $tcp, $useContainerPort);
            if ($derived !== null) {
                return $derived;
            }
        }
        return 'http://' . $hostname . ':' . $tcp[0]['public'];
    }

    /** @param list<array{private:int,public:int,protocol:string}> $ports */
    private static function fromLabel(string $label, string $hostname, array $ports, bool $useContainerPort): ?string
    {
        $label = str_replace('[IP]', $hostname, trim($label));
        $label = preg_replace_callback('/\[PORT:(\d+)\]/', static function (array $match) use ($ports, $useContainerPort): string {
            $private = (int)$match[1];
            if ($useContainerPort) {
                return (string)$private;
            }
            foreach ($ports as $port) {
                if ($port['private'] === $private) {
                    return (string)$port['public'];
                }
            }
            return $match[1];
        }, $label) ?? $label;
        if (!preg_match('#^https?://#i', $label)) {
            return null;
        }
        $parts = parse_url($label);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $scheme = strtolower((string)$parts['scheme']);
        $url = $scheme . '://' . $hostname;
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }
        $url .= (string)($parts['path'] ?? '');
        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }
        try {
            return self::validateOverride($url);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}

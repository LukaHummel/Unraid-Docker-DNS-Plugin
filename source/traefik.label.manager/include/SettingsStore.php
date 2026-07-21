<?php

declare(strict_types=1);

namespace TraefikLabelManager;

use InvalidArgumentException;
use RuntimeException;

final class SettingsStore
{
    public const DEFAULT_DOMAIN_SUFFIX = 'home.arpa';

    public function __construct(
        private readonly string $path,
        private readonly string $lockFile,
    ) {
    }

    public function domainSuffix(): string
    {
        if (!is_file($this->path)) return self::DEFAULT_DOMAIN_SUFFIX;
        $contents = file_get_contents($this->path);
        if ($contents === false) return self::DEFAULT_DOMAIN_SUFFIX;
        try {
            $settings = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return self::DEFAULT_DOMAIN_SUFFIX;
        }
        if (!is_array($settings) || !is_string($settings['domain_suffix'] ?? null)) return self::DEFAULT_DOMAIN_SUFFIX;
        try {
            return self::validateDomainSuffix($settings['domain_suffix']);
        } catch (InvalidArgumentException) {
            return self::DEFAULT_DOMAIN_SUFFIX;
        }
    }

    /** @return array{ok:true,domain_suffix:string} */
    public function saveDomainSuffix(string $domainSuffix): array
    {
        $domainSuffix = self::validateDomainSuffix($domainSuffix);
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create the plugin configuration directory.');
        }
        $lock = fopen($this->lockFile, 'c');
        if ($lock === false) throw new RuntimeException('Cannot open the settings lock.');
        try {
            if (!flock($lock, LOCK_EX)) throw new RuntimeException('Cannot lock the settings file.');
            $temporary = tempnam($directory, '.traefik-label-manager-settings-');
            if ($temporary === false) throw new RuntimeException('Cannot create a temporary settings file.');
            try {
                $contents = json_encode(['domain_suffix' => $domainSuffix], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
                if (file_put_contents($temporary, $contents, LOCK_EX) === false) throw new RuntimeException('Cannot write the settings file.');
                chmod($temporary, 0644);
                if (!rename($temporary, $this->path)) throw new RuntimeException('Cannot replace the settings file.');
            } finally {
                if (is_file($temporary)) @unlink($temporary);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return ['ok' => true, 'domain_suffix' => $domainSuffix];
    }

    public static function validateDomainSuffix(string $domainSuffix): string
    {
        $domainSuffix = trim($domainSuffix);
        if (str_starts_with($domainSuffix, '.')) $domainSuffix = substr($domainSuffix, 1);
        if ($domainSuffix === '' || strlen($domainSuffix) > 253 || strtolower($domainSuffix) !== $domainSuffix) {
            throw new InvalidArgumentException('Domain suffix must contain only lowercase DNS labels.');
        }
        foreach (explode('.', $domainSuffix) as $label) {
            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label)) {
                throw new InvalidArgumentException('Domain suffix must contain only lowercase DNS labels.');
            }
        }
        return $domainSuffix;
    }
}

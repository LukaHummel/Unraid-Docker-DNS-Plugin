<?php

declare(strict_types=1);

namespace DockerDns;

use RuntimeException;

final class JsonStore
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create configuration directory: $directory");
        }
        @chmod($directory, 0700);
    }

    /** @return array<string,mixed> */
    public function read(string $file, array $defaults = []): array
    {
        $path = $this->path($file);
        if (!is_file($path)) {
            return $defaults;
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Cannot read $path");
        }
        $value = json_decode($contents, true);
        if (!is_array($value)) {
            throw new RuntimeException("Invalid JSON in $path");
        }
        return array_replace_recursive($defaults, $value);
    }

    /** @param array<string,mixed> $data */
    public function write(string $file, array $data, int $mode = 0600): void
    {
        $path = $this->path($file);
        $temporary = tempnam($this->directory, '.docker-dns-');
        if ($temporary === false) {
            throw new RuntimeException("Cannot create temporary file in {$this->directory}");
        }
        try {
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (file_put_contents($temporary, $encoded, LOCK_EX) === false) {
                throw new RuntimeException("Cannot write $temporary");
            }
            chmod($temporary, $mode);
            if (!rename($temporary, $path)) {
                throw new RuntimeException("Cannot replace $path");
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function path(string $file): string
    {
        if (!preg_match('/^[a-z0-9_.-]+$/i', $file)) {
            throw new RuntimeException('Invalid store file name');
        }
        return $this->directory . '/' . $file;
    }
}


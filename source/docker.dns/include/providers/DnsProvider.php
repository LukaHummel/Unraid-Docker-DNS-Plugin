<?php

declare(strict_types=1);

namespace DockerDns\providers;

interface DnsProvider
{
    public function test(): void;

    /** @param array<string,string> $desired hostname => IPv4 @param list<string> $remove */
    public function reconcile(array $desired, array $remove): void;
}


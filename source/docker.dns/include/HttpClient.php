<?php

declare(strict_types=1);

namespace DockerDns;

use RuntimeException;

class HttpClient
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:mixed,raw:string}
     */
    public function request(string $method, string $url, ?array $payload, array $headers, bool $verifyTls, int $timeout, ?string $basicAuth = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required.');
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize HTTP client.');
        }
        $headerList = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            $headerList[] = $name . ': ' . $value;
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => $verifyTls,
            CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0,
            CURLOPT_HTTPHEADER => $headerList,
        ];
        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $headerList[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headerList;
        }
        if ($basicAuth !== null) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = $basicAuth;
        }
        curl_setopt_array($handle, $options);
        $raw = curl_exec($handle);
        if ($raw === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException('Provider request failed: ' . $error);
        }
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        $body = $raw === '' ? null : json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $detail = is_array($body) ? json_encode($body) : substr($raw, 0, 500);
            throw new RuntimeException("Provider returned HTTP $status: $detail");
        }
        return ['status' => $status, 'body' => $body, 'raw' => $raw];
    }
}

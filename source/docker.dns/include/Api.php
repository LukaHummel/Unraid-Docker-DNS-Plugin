<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use DockerDns\ApiController;
use DockerDns\Config;
use DockerDns\CsrfException;
use DockerDns\DockerDiscovery;
use DockerDns\JsonStore;
use DockerDns\ProviderFactory;
use DockerDns\SyncEngine;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $config = new Config(new JsonStore(docker_dns_config_dir()));
    $controller = new ApiController($config, new SyncEngine($config, new DockerDiscovery(), new ProviderFactory()));
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $result = $controller->get((string)($_GET['action'] ?? ''), $_GET);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $input = json_decode((string)file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
        } else {
            $input = $_POST;
        }
        if (!is_array($input)) {
            throw new InvalidArgumentException('A JSON object is required.');
        }
        $result = $controller->post($input);
    } else {
        http_response_code(405);
        $result = ['ok' => false, 'error' => 'Method not allowed.'];
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code($error instanceof CsrfException ? 403 : ($error instanceof InvalidArgumentException ? 400 : 500));
    echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_SLASHES);
}

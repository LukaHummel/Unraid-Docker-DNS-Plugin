<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use TraefikLabelManager\CsrfException;
use TraefikLabelManager\LabelCatalog;
use TraefikLabelManager\LabelManager;
use TraefikLabelManager\SettingsStore;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $manager = new LabelManager(traefik_label_manager_template_dir(), traefik_label_manager_lock_file());
    $settings = new SettingsStore(traefik_label_manager_settings_file(), traefik_label_manager_lock_file());
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ((string)($_GET['action'] ?? '') !== 'containers') throw new InvalidArgumentException('Unknown API action.');
        $result = [
            'ok' => true,
            'domain_suffix' => $settings->domainSuffix(),
            'label_catalog' => LabelCatalog::definitions(),
            'containers' => $manager->containers(),
        ];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
            $input = json_decode((string)($_POST['payload'] ?? ''), true, flags: JSON_THROW_ON_ERROR);
        } else {
            $input = json_decode((string)file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
        }
        if (!is_array($input)) throw new InvalidArgumentException('A JSON object is required.');
        $submitted = (string)($_POST['csrf_token'] ?? $input['csrf_token'] ?? $input['traefik_label_manager_csrf_token'] ?? '');
        $expected = getenv('TRAEFIK_LABEL_MANAGER_CSRF_TOKEN');
        if ($expected === false) {
            $ini = @parse_ini_file('/var/local/emhttp/var.ini');
            $expected = is_array($ini) ? (string)($ini['csrf_token'] ?? '') : '';
        }
        if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
            throw new CsrfException('CSRF validation failed.');
        }
        $action = (string)($input['action'] ?? '');
        if ($action === 'save') {
            $labels = $input['labels'] ?? null;
            if (!is_array($labels)) throw new InvalidArgumentException('Labels must be an array.');
            $result = $manager->save((string)($input['container'] ?? ''), $labels);
        } elseif ($action === 'save_settings') {
            $result = $settings->saveDomainSuffix((string)($input['domain_suffix'] ?? ''));
        } else {
            throw new InvalidArgumentException('Unknown API action.');
        }
    } else {
        http_response_code(405);
        $result = ['ok' => false, 'error' => 'Method not allowed.'];
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code($error instanceof CsrfException ? 403 : ($error instanceof InvalidArgumentException ? 400 : 500));
    echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_SLASHES);
}

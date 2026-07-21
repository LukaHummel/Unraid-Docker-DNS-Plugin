<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use TraefikLabelManager\CsrfException;
use TraefikLabelManager\LabelManager;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $manager = new LabelManager(traefik_label_manager_template_dir(), traefik_label_manager_lock_file());
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ((string)($_GET['action'] ?? '') !== 'containers') throw new InvalidArgumentException('Unknown API action.');
        $result = ['ok' => true, 'containers' => $manager->containers()];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new InvalidArgumentException('A JSON object is required.');
        $submitted = (string)($input['traefik_label_manager_csrf_token'] ?? '');
        $expected = getenv('TRAEFIK_LABEL_MANAGER_CSRF_TOKEN');
        if ($expected === false) {
            $ini = @parse_ini_file('/var/local/emhttp/var.ini');
            $expected = is_array($ini) ? (string)($ini['csrf_token'] ?? '') : '';
        }
        if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
            throw new CsrfException('CSRF validation failed.');
        }
        if ((string)($input['action'] ?? '') !== 'save') throw new InvalidArgumentException('Unknown API action.');
        $labels = $input['labels'] ?? null;
        if (!is_array($labels)) throw new InvalidArgumentException('Labels must be an array.');
        $result = $manager->save((string)($input['container'] ?? ''), $labels);
    } else {
        http_response_code(405);
        $result = ['ok' => false, 'error' => 'Method not allowed.'];
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code($error instanceof CsrfException ? 403 : ($error instanceof InvalidArgumentException ? 400 : 500));
    echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_SLASHES);
}

<?php
/**
 * Valida JSONs monoliticos e o espelho modular.
 *
 * Uso recomendado antes de commit/push:
 *   php scripts/validar_armazenamento_modular.php
 */

require_once __DIR__ . '/../public/reformulacao/storage_modular.php';

$root = dirname(__DIR__);
$errors = [];
$checked = 0;

$skipParts = [
    '/data/backups/',
    '/data/reformulacao/_backups/',
    '/_backups/',
    '/public/reformulacao/calibradora/',
    '/public/reformulacao/calibradora_V2/',
    '/data/reformulacao/calibradora/',
    '/data/reformulacao/calibradora_V2/',
];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'json') {
        continue;
    }

    $path = rf_modular_normalize_path($file->getPathname());
    $relative = '/' . rf_modular_relative_path($path);
    $skip = false;
    foreach ($skipParts as $part) {
        if (str_contains($relative, $part)) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    $checked++;
    json_decode((string)file_get_contents($path), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = rf_modular_relative_path($path) . ': ' . json_last_error_msg();
    }
}

$eventErrors = 0;
$eventsRoot = rf_modular_root() . '/eventos';
if (is_dir($eventsRoot)) {
    $events = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($eventsRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($events as $eventFile) {
        if (!$eventFile->isFile() || strtolower($eventFile->getExtension()) !== 'jsonl') {
            continue;
        }
        $lineNo = 0;
        foreach (file($eventFile->getPathname(), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $lineNo++;
            if (trim($line) === '') {
                continue;
            }
            json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $eventErrors++;
                $errors[] = rf_modular_relative_path($eventFile->getPathname()) . ':' . $lineNo . ': ' . json_last_error_msg();
            }
        }
    }
}

if (!empty($errors)) {
    echo json_encode([
        'status' => 'error',
        'checked_json_files' => $checked,
        'event_errors' => $eventErrors,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

echo json_encode([
    'status' => 'success',
    'checked_json_files' => $checked,
    'event_errors' => 0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

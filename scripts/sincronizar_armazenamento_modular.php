<?php
/**
 * Gera o espelho modular em data/modular a partir dos JSONs ativos.
 *
 * Nao toca nas pastas/arquivos da calibradora.
 */

require_once __DIR__ . '/../public/reformulacao/storage_modular.php';

$root = dirname(__DIR__);
$sources = [
    'cadastros_basicos' => $root . '/data/ativos/cadastros_basicos.json',
    'fluxo' => $root . '/data/ativos/fluxo_teste02.json',
    'arvore_estrutura' => $root . '/data/ativos/arvore_estrutura.json',
];

$exported = [];
foreach ($sources as $source => $path) {
    if (!is_file($path)) {
        echo "Ignorado: {$path} nao encontrado." . PHP_EOL;
        continue;
    }

    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "JSON invalido em {$path}: " . json_last_error_msg() . PHP_EOL);
        exit(1);
    }

    rf_modular_export_source($source, $data);
    rf_modular_append_event($source, 'snapshot.sincronizado', [
        'source_file' => rf_modular_relative_path($path),
        'counts' => rf_modular_counts($source, $data),
    ]);
    $exported[$source] = rf_modular_counts($source, $data);
}

echo json_encode([
    'status' => 'success',
    'modular_root' => rf_modular_relative_path(rf_modular_root()),
    'exported' => $exported,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

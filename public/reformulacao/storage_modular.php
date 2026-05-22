<?php
/**
 * Espelho modular dos JSONs ativos da Reformulacao.
 *
 * Mantem os arquivos monoliticos como fonte de verdade para compatibilidade,
 * mas gera snapshots menores e um log JSONL para facilitar merge via Git.
 */

function rf_modular_root(): string {
    return dirname(__DIR__, 2) . '/data/modular';
}

function rf_modular_normalize_path(string $path): string {
    return str_replace('\\', '/', $path);
}

function rf_modular_skip_path(string $path): bool {
    $path = strtolower(rf_modular_normalize_path($path));
    return str_contains($path, '/calibradora/') || str_contains($path, '/calibradora_v2/') || str_contains($path, 'calibradora.json');
}

function rf_modular_source_name(string $path): string {
    $base = basename(rf_modular_normalize_path($path));
    return match ($base) {
        'cadastros_basicos.json' => 'cadastros_basicos',
        'fluxo_teste02.json' => 'fluxo',
        'arvore_estrutura.json' => 'arvore_estrutura',
        default => '',
    };
}

function rf_modular_after_json_write(string $path, array $data): void {
    if (rf_modular_skip_path($path)) {
        return;
    }

    $source = rf_modular_source_name($path);
    if ($source === '') {
        return;
    }

    rf_modular_export_source($source, $data);
    rf_modular_append_event($source, 'snapshot.atualizado', [
        'source_file' => rf_modular_relative_path($path),
        'counts' => rf_modular_counts($source, $data),
    ]);
}

function rf_modular_relative_path(string $path): string {
    $root = rf_modular_normalize_path(dirname(__DIR__, 2));
    $path = rf_modular_normalize_path($path);
    return str_starts_with($path, $root . '/') ? substr($path, strlen($root) + 1) : $path;
}

function rf_modular_counts(string $source, array $data): array {
    if ($source === 'cadastros_basicos') {
        return rf_modular_count_keys($data, ['setores', 'linhas', 'postos', 'atividades_padrao', 'unidades', 'tipos_embalagem']);
    }
    if ($source === 'fluxo') {
        return [
            'setores' => count($data['setores'] ?? []),
            'cronoanalises' => count($data['cronoanalises'] ?? []),
            'fluxos' => count(rf_modular_flux_rows($data)),
        ];
    }
    if ($source === 'arvore_estrutura') {
        return rf_modular_count_keys($data, ['tabela_itens', 'tabela_arvores', 'tabela_arvore_composicao', 'tabela_item_conversoes']);
    }
    return [];
}

function rf_modular_count_keys(array $data, array $keys): array {
    $counts = [];
    foreach ($keys as $key) {
        $counts[$key] = is_array($data[$key] ?? null) ? count($data[$key]) : 0;
    }
    return $counts;
}

function rf_modular_export_source(string $source, array $data): void {
    rf_modular_write_json(rf_modular_root() . '/manifest.json', rf_modular_manifest($source, $data));

    if ($source === 'cadastros_basicos') {
        rf_modular_export_catalogs($data);
    } elseif ($source === 'fluxo') {
        rf_modular_export_fluxo($data);
    } elseif ($source === 'arvore_estrutura') {
        rf_modular_export_arvore($data);
    }
}

function rf_modular_manifest(string $source, array $data): array {
    $path = rf_modular_root() . '/manifest.json';
    $manifest = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
    if (!is_array($manifest)) {
        $manifest = [];
    }
    $manifest['schema_version'] = 1;
    $manifest['updated_at'] = date('c');
    $manifest['sources'][$source] = [
        'updated_at' => date('c'),
        'counts' => rf_modular_counts($source, $data),
    ];
    return $manifest;
}

function rf_modular_export_catalogs(array $data): void {
    $catalogs = ['setores', 'linhas', 'postos', 'atividades_padrao', 'unidades', 'tipos_embalagem'];
    foreach ($catalogs as $catalog) {
        foreach (($data[$catalog] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = rf_modular_row_id($row, $catalog . '_' . $index);
            rf_modular_write_record('cadastros/' . $catalog, $id, $row + ['schema_version' => 1]);
        }
    }
}

function rf_modular_export_fluxo(array $data): void {
    foreach (($data['cronoanalises'] ?? []) as $index => $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = rf_modular_row_id($row, 'crono_' . $index);
        rf_modular_write_record('cronoanalises', $id, $row + ['schema_version' => 1]);
    }

    foreach (rf_modular_flux_rows($data) as $flux) {
        rf_modular_write_record('fluxos', $flux['id'], $flux);
    }
}

function rf_modular_flux_rows(array $data): array {
    $rows = [];
    foreach (($data['setores'] ?? []) as $setor) {
        foreach (($setor['linhas'] ?? []) as $linha) {
            if (!is_array($linha) || empty($linha['drawflow_data'])) {
                continue;
            }
            $id = rf_modular_safe_filename((string)($linha['id'] ?? ('linha_' . count($rows))));
            $rows[] = [
                'schema_version' => 1,
                'id' => $id,
                'linha_id' => $linha['id'] ?? '',
                'linha_nome' => $linha['nome'] ?? '',
                'setor_id' => $setor['id'] ?? '',
                'setor_nome' => $setor['nome'] ?? '',
                'updated_at' => $linha['updated_at'] ?? ($data['updated_at'] ?? ''),
                'drawflow_data' => $linha['drawflow_data'],
            ];
        }
    }
    return $rows;
}

function rf_modular_export_arvore(array $data): void {
    $tables = [
        'tabela_itens' => 'arvore_estrutura/itens',
        'tabela_arvores' => 'arvore_estrutura/arvores',
        'tabela_arvore_composicao' => 'arvore_estrutura/composicoes',
        'tabela_item_conversoes' => 'arvore_estrutura/conversoes',
    ];
    foreach ($tables as $key => $dir) {
        foreach (($data[$key] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = rf_modular_row_id($row, $key . '_' . $index);
            rf_modular_write_record($dir, $id, $row + ['schema_version' => 1]);
        }
    }
}

function rf_modular_row_id(array $row, string $fallback): string {
    return rf_modular_safe_filename((string)($row['id'] ?? $row['codigo'] ?? $fallback));
}

function rf_modular_safe_filename(string $value): string {
    $value = trim($value);
    if ($value === '') {
        $value = 'registro_' . date('YmdHis');
    }
    $value = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $value);
    return trim((string)$value, '._') ?: 'registro_' . date('YmdHis');
}

function rf_modular_write_record(string $dir, string $id, array $row): void {
    rf_modular_write_json(rf_modular_root() . '/' . trim($dir, '/') . '/' . $id . '.json', $row);
}

function rf_modular_write_json(string $path, array $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        throw new RuntimeException('Falha ao gerar JSON modular: ' . json_last_error_msg());
    }
    $tmp = tempnam($dir, '.tmp_modular_');
    if ($tmp === false || file_put_contents($tmp, $encoded . PHP_EOL, LOCK_EX) === false) {
        if ($tmp !== false) {
            @unlink($tmp);
        }
        throw new RuntimeException('Falha ao gravar JSON modular.');
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Falha ao substituir JSON modular.');
    }
}

function rf_modular_append_event(string $source, string $type, array $payload): void {
    $dir = rf_modular_root() . '/eventos/' . date('Y');
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $event = [
        'id' => 'evt_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)),
        'schema_version' => 1,
        'source' => $source,
        'type' => $type,
        'created_at' => date('c'),
        'host' => gethostname() ?: '',
        'payload' => $payload,
    ];
    $line = json_encode($event, JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        throw new RuntimeException('Falha ao gerar evento modular: ' . json_last_error_msg());
    }
    file_put_contents($dir . '/' . date('Y-m') . '.jsonl', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

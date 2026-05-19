<?php
/**
 * Persistencia segura para arquivos JSON da Reformulacao.
 *
 * Regras:
 * - nunca sobrescrever JSON existente sem backup;
 * - escrever primeiro em arquivo temporario;
 * - usar lock para evitar duas gravacoes simultaneas;
 * - validar o JSON gravado antes de substituir o arquivo oficial.
 */

function cod_12_05_safe_backup_path($path) {
    $dir = dirname($path);
    $parent = dirname($dir);
    $backup_dir = basename($dir) === 'ativos' ? $parent . '/backups' : $dir . '/_backups';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }

    $base = pathinfo($path, PATHINFO_FILENAME);
    return $backup_dir . '/' . $base . '_' . date('Ymd_His') . '_' . random_int(1000, 9999) . '.json';
}

function cod_12_05_safe_legacy_candidates($path) {
    $dir = dirname($path);
    if (basename($dir) !== 'ativos') {
        return [];
    }

    $data_root = dirname($dir);
    $base = basename($path);
    $name = pathinfo($path, PATHINFO_FILENAME);
    $candidates = [];

    foreach ([
        $data_root . '/reformulacao/' . $base,
        $data_root . '/backups/' . $name . '_*.json',
        $data_root . '/reformulacao/_backups/' . $name . '_*.json',
    ] as $pattern) {
        foreach (glob($pattern) ?: [] as $candidate) {
            if (is_file($candidate)) {
                $candidates[$candidate] = $candidate;
            }
        }
    }

    return array_values($candidates);
}

function cod_12_05_safe_candidate_score($candidate, $data) {
    $score = filesize($candidate) ?: 0;
    foreach (['setores', 'cronoanalises', 'tabela_itens', 'tabela_arvores', 'tabela_arvore_composicao'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) {
            $score += count($data[$key]) * 1000000;
        }
    }
    return $score;
}

function cod_12_05_safe_restore_from_legacy($path, callable $validator) {
    $best = null;
    $best_score = -1;

    foreach (cod_12_05_safe_legacy_candidates($path) as $candidate) {
        $json = file_get_contents($candidate);
        $data = json_decode((string)$json, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE || !$validator($data)) {
            continue;
        }

        $score = cod_12_05_safe_candidate_score($candidate, $data);
        if ($score > $best_score) {
            $best = $data;
            $best_score = $score;
        }
    }

    if ($best !== null) {
        cod_12_05_safe_write_json($path, $best);
    }

    return $best;
}

function cod_12_05_safe_write_json($path, $data) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        throw new RuntimeException('Falha ao converter dados para JSON: ' . json_last_error_msg());
    }

    json_decode($encoded, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('JSON gerado invalido: ' . json_last_error_msg());
    }

    $lock_path = $path . '.lock';
    $lock = fopen($lock_path, 'c');
    if ($lock === false) {
        throw new RuntimeException('Nao foi possivel criar trava de escrita.');
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Nao foi possivel bloquear a escrita.');
        }

        if (file_exists($path) && filesize($path) > 0) {
            copy($path, cod_12_05_safe_backup_path($path));
        }

        $tmp = tempnam($dir, '.tmp_json_');
        if ($tmp === false) {
            throw new RuntimeException('Nao foi possivel criar arquivo temporario.');
        }

        if (file_put_contents($tmp, $encoded, LOCK_EX) === false) {
            @unlink($tmp);
            throw new RuntimeException('Falha ao gravar arquivo temporario.');
        }

        $check = json_decode((string)file_get_contents($tmp), true);
        if (!is_array($check) || json_last_error() !== JSON_ERROR_NONE) {
            @unlink($tmp);
            throw new RuntimeException('Arquivo temporario gerou JSON invalido.');
        }

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Falha ao substituir arquivo oficial.');
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function cod_12_05_safe_load_json($path, callable $default_factory, callable $validator) {
    if (!file_exists($path)) {
        $restored = cod_12_05_safe_restore_from_legacy($path, $validator);
        if (is_array($restored)) {
            return $restored;
        }

        $data = $default_factory();
        cod_12_05_safe_write_json($path, $data);
        return $data;
    }

    $json = file_get_contents($path);
    $data = json_decode((string)$json, true);
    if (is_array($data) && $validator($data)) {
        return $data;
    }

    $corrupt_path = cod_12_05_safe_backup_path($path) . '.corrupt';
    copy($path, $corrupt_path);

    $data = $default_factory();
    cod_12_05_safe_write_json($path, $data);
    return $data;
}

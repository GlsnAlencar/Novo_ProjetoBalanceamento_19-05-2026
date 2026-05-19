<?php
/**
 * Persistência Segura - Módulo Calibradora (ISOLADO)
 * 
 * Armazenamento JSON seguro APENAS para dados da calibradora.
 * Completamente independente do restante do sistema.
 * 
 * Regras:
 * - Nunca sobrescrever JSON existente sem backup
 * - Escrever primeiro em arquivo temporário
 * - Usar lock para evitar gravações simultâneas
 * - Validar o JSON gravado antes de substituir o arquivo oficial
 */

/**
 * Criar caminho de backup com timestamp único
 */
function calibradora_safe_backup_path($path) {
    $backup_dir = dirname($path) . '/_backups';
    if (!is_dir($backup_dir)) {
        @mkdir($backup_dir, 0777, true);
    }

    $base = pathinfo($path, PATHINFO_FILENAME);
    return $backup_dir . '/' . $base . '_' . date('Ymd_His') . '_' . random_int(1000, 9999) . '.json';
}

/**
 * Gravar JSON de forma segura com backup e lock
 */
function calibradora_safe_write_json($path, $data) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    // Codificar para JSON
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        throw new RuntimeException('Falha ao converter dados para JSON: ' . json_last_error_msg());
    }

    // Validar JSON gerado
    json_decode($encoded, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('JSON gerado inválido: ' . json_last_error_msg());
    }

    // Lock file para evitar escrita simultânea
    $lock_path = $path . '.lock';
    $lock = @fopen($lock_path, 'c');
    if ($lock === false) {
        throw new RuntimeException('Não foi possível criar trava de escrita.');
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Não foi possível bloquear a escrita.');
        }

        // Backup se arquivo existe
        if (file_exists($path) && filesize($path) > 0) {
            @copy($path, calibradora_safe_backup_path($path));
        }

        // Escrever em arquivo temporário
        $tmp = tempnam($dir, '.tmp_json_');
        if ($tmp === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário.');
        }

        if (file_put_contents($tmp, $encoded, LOCK_EX) === false) {
            @unlink($tmp);
            throw new RuntimeException('Falha ao gravar arquivo temporário.');
        }

        // Validar arquivo temporário
        $check = json_decode((string)file_get_contents($tmp), true);
        if (!is_array($check) || json_last_error() !== JSON_ERROR_NONE) {
            @unlink($tmp);
            throw new RuntimeException('Arquivo temporário gerou JSON inválido.');
        }

        // Substituir arquivo oficial
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Falha ao substituir arquivo oficial.');
        }
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

/**
 * Carregar JSON de forma segura com fallback para padrão
 */
function calibradora_safe_load_json($path, callable $default_factory, callable $validator) {
    // Se arquivo não existe, criar com padrão
    if (!file_exists($path)) {
        $data = $default_factory();
        calibradora_safe_write_json($path, $data);
        return $data;
    }

    // Carregar e validar JSON existente
    $json = @file_get_contents($path);
    $data = json_decode((string)$json, true);
    if (is_array($data) && $validator($data)) {
        return $data;
    }

    // JSON corrompido: fazer backup e restaurar padrão
    $corrupt_path = calibradora_safe_backup_path($path) . '.corrupt';
    @copy($path, $corrupt_path);

    $data = $default_factory();
    calibradora_safe_write_json($path, $data);
    return $data;
}

/**
 * Funções de tipo (para validação de entrada)
 */
function calibradora_number($value, $default = 0) {
    if (is_string($value)) {
        $value = str_replace(',', '.', $value);
    }
    return is_numeric($value) ? (float)$value : $default;
}

function calibradora_int($value, $default = 0) {
    return is_numeric($value) ? (int)$value : $default;
}

function calibradora_string($value, $default = '') {
    return is_string($value) ? trim($value) : $default;
}

/**
 * Validadores de estrutura
 */
function calibradora_validate_faixa($data) {
    return isset($data['id'], $data['descricao'], $data['peso_inicial'], $data['peso_final']);
}

function calibradora_validate_configuracao($data) {
    return isset($data['id'], $data['nome'], $data['faixa_peso_id']);
}

function calibradora_validate_lote($data) {
    return isset($data['id'], $data['controle'], $data['configuracao_embalamento_id']);
}

function calibradora_validate_distribuicao($data) {
    return isset($data['id'], $data['lote_id'], $data['configuracao_embalamento_id']);
}

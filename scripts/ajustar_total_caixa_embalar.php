<?php

$root = dirname(__DIR__);
$path = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ativos' . DIRECTORY_SEPARATOR . 'fluxo_teste02.json';
$backupDir = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'backups';

if (!is_file($path)) {
    fwrite(STDERR, "Arquivo ativo nao encontrado.\n");
    exit(1);
}

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

$raw = file_get_contents($path);
$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "JSON invalido no arquivo ativo.\n");
    exit(1);
}

$backup = $backupDir . DIRECTORY_SEPARATOR . 'fluxo_teste02_pre_total_caixa_embalar_' . date('Ymd_His') . '.json';
copy($path, $backup);

$count = 0;
$now = date('Y-m-d H:i:s');

$num = static function ($value, float $fallback = 0.0): float {
    if (is_string($value)) {
        $value = str_replace(',', '.', $value);
    }
    return is_numeric($value) ? (float)$value : $fallback;
};

$isEmbalar = static function (array $atividade): bool {
    $name = trim((string)($atividade['atividade'] ?? $atividade['descricao'] ?? ''));
    return strtolower($name) === 'embalar';
};

$ajustar = function (array &$atividade) use (&$count, $now, $num, $isEmbalar): void {
    if (!$isEmbalar($atividade)) {
        return;
    }

    $tempo = $num($atividade['tempo_total_s'] ?? $atividade['tempo_total'] ?? $atividade['tempo_s'] ?? 0);
    $ritmo = $num($atividade['fator_ritmo'] ?? 1, 1);
    $fatorTolerancia = $num($atividade['fator_tolerancia'] ?? 1, 1);
    $tr = $tempo;
    $tn = $tr * $ritmo;
    $tp = $tn * $fatorTolerancia;

    $atividade['quantidade_ref'] = 1;
    $atividade['qtd_ref'] = 1;
    $atividade['numero_frutos'] = 1;
    $atividade['num_frutos'] = 1;
    $atividade['peso_fruto_g'] = 0;
    $atividade['tr'] = $tr;
    $atividade['tn'] = $tn;
    $atividade['tp'] = $tp;
    $atividade['tempo_unitario'] = $tp;
    $atividade['tempo_unitario_utilizado'] = $tp;
    $atividade['tempo_operacao_s'] = $tp;
    $atividade['atualizado_em'] = $now;
    $count++;
};

if (isset($data['cronoanalises']) && is_array($data['cronoanalises'])) {
    foreach ($data['cronoanalises'] as &$atividade) {
        if (is_array($atividade)) {
            $ajustar($atividade);
        }
    }
    unset($atividade);
}

if (isset($data['setores']) && is_array($data['setores'])) {
    foreach ($data['setores'] as &$setor) {
        foreach (($setor['linhas'] ?? []) as &$linha) {
            foreach (($linha['atividades_por_posto'] ?? []) as &$atividades) {
                if (!is_array($atividades)) {
                    continue;
                }
                foreach ($atividades as &$atividade) {
                    if (is_array($atividade)) {
                        $ajustar($atividade);
                    }
                }
                unset($atividade);
            }
            unset($atividades);
        }
        unset($linha);
    }
    unset($setor);
}

$data['updated_at'] = $now;
$encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($encoded === false) {
    fwrite(STDERR, "Falha ao codificar JSON.\n");
    exit(1);
}

file_put_contents($path, $encoded . PHP_EOL, LOCK_EX);

echo "Atualizados: {$count}\n";
echo "Backup: {$backup}\n";

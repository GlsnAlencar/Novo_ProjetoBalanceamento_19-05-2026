<?php
require_once __DIR__ . '/../public/reformulacao/safe_storage.php';

$root = dirname(__DIR__);
$fluxoPath = $root . '/data/ativos/fluxo_teste02.json';
$cadastrosPath = $root . '/data/ativos/cadastros_basicos.json';
$sourceSetorNames = ['setor 1', 'máquina grande', 'maquina grande'];
$targetSetorId = 'cod_12_05_setor_1';
$targetSetorName = 'Máquina Grande';
$packingLineId = 'cod_12_05_linha_fluxo_packing_completo';

function agr_load_json(string $path): array {
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) {
        throw new RuntimeException("JSON invalido: {$path}");
    }
    return $data;
}

function agr_norm(string $value): string {
    $value = trim($value);
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    return strtolower($converted !== false ? $converted : $value);
}

function agr_node_count($drawflow): int {
    $nodes = $drawflow['drawflow']['Home']['data'] ?? [];
    return is_array($nodes) ? count($nodes) : 0;
}

function agr_flow_signature($drawflow): string {
    return json_encode($drawflow, JSON_UNESCAPED_UNICODE) ?: '';
}

function agr_pick_better_line(array $current, array $candidate): array {
    $currentNodes = agr_node_count($current['drawflow_data'] ?? null);
    $candidateNodes = agr_node_count($candidate['drawflow_data'] ?? null);
    if ($candidateNodes > $currentNodes) {
        return $candidate;
    }
    if ($candidateNodes === $currentNodes) {
        $currentTs = strtotime((string)($current['updated_at'] ?? '')) ?: 0;
        $candidateTs = strtotime((string)($candidate['updated_at'] ?? '')) ?: 0;
        if ($candidateTs >= $currentTs) {
            return $candidate;
        }
    }
    return $current;
}

function agr_best_backup_flow(string $root, string $linhaId): ?array {
    $best = null;
    foreach (glob($root . '/data/backups/fluxo_teste02_*.json') ?: [] as $path) {
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            continue;
        }
        foreach (($data['setores'] ?? []) as $setor) {
            foreach (($setor['linhas'] ?? []) as $linha) {
                if (($linha['id'] ?? '') !== $linhaId) {
                    continue;
                }
                $flow = $linha['drawflow_data'] ?? null;
                $nodes = agr_node_count($flow);
                if ($nodes <= 0) {
                    continue;
                }
                $candidate = [
                    'drawflow_data' => $flow,
                    'nodes' => $nodes,
                    'source' => basename($path),
                    'timestamp' => max(strtotime((string)($linha['updated_at'] ?? '')) ?: 0, filemtime($path)),
                ];
                if (!$best || $candidate['nodes'] > $best['nodes'] || ($candidate['nodes'] === $best['nodes'] && $candidate['timestamp'] >= $best['timestamp'])) {
                    $best = $candidate;
                }
            }
        }
    }
    return $best;
}

function agr_unique_line_name(array $lines, string $name): string {
    $used = [];
    foreach ($lines as $line) {
        $used[agr_norm((string)($line['nome'] ?? ''))] = true;
    }
    if (!isset($used[agr_norm($name)])) {
        return $name;
    }
    $base = $name !== '' ? $name : 'Linha agrupada';
    for ($i = 2; $i < 100; $i++) {
        $candidate = $base . ' (' . $i . ')';
        if (!isset($used[agr_norm($candidate)])) {
            return $candidate;
        }
    }
    return $base . ' (' . date('His') . ')';
}

$fluxo = agr_load_json($fluxoPath);
$cadastros = agr_load_json($cadastrosPath);
$now = date('Y-m-d H:i:s');

$bestPacking = agr_best_backup_flow($root, $packingLineId);
$targetSetor = null;
$mergedLines = [];
$renamedLines = 0;
$mergedSetores = 0;

foreach (($fluxo['setores'] ?? []) as $setor) {
    $nomeNorm = agr_norm((string)($setor['nome'] ?? ''));
    $id = (string)($setor['id'] ?? '');
    $shouldMerge = $id === $targetSetorId || in_array($nomeNorm, $sourceSetorNames, true);
    if (!$shouldMerge) {
        continue;
    }

    $mergedSetores++;
    if (!$targetSetor) {
        $targetSetor = $setor;
        $targetSetor['id'] = $targetSetorId;
        $targetSetor['nome'] = $targetSetorName;
        $targetSetor['updated_at'] = $now;
    }

    foreach (($setor['linhas'] ?? []) as $line) {
        $line['setor_id'] = $targetSetorId;
        if (($line['id'] ?? '') === $packingLineId && agr_node_count($line['drawflow_data'] ?? null) === 0 && $bestPacking) {
            $line['drawflow_data'] = $bestPacking['drawflow_data'];
            $line['updated_at'] = $now;
        }

        $lineId = (string)($line['id'] ?? '');
        if ($lineId === '') {
            $lineId = 'linha_agrupada_' . count($mergedLines) . '_' . date('His');
            $line['id'] = $lineId;
        }

        if (!isset($mergedLines[$lineId])) {
            $mergedLines[$lineId] = $line;
            continue;
        }

        $existing = $mergedLines[$lineId];
        $existingSig = agr_flow_signature($existing['drawflow_data'] ?? null);
        $candidateSig = agr_flow_signature($line['drawflow_data'] ?? null);
        if ($existingSig === $candidateSig || agr_node_count($existing['drawflow_data'] ?? null) === 0 || agr_node_count($line['drawflow_data'] ?? null) === 0) {
            $mergedLines[$lineId] = agr_pick_better_line($existing, $line);
            continue;
        }

        $line['id'] = $lineId . '_agrupado_' . (++$renamedLines);
        $line['nome'] = agr_unique_line_name(array_values($mergedLines), (string)($line['nome'] ?? 'Linha agrupada'));
        $line['updated_at'] = $now;
        $mergedLines[$line['id']] = $line;
    }
}

if ($targetSetor) {
    $targetSetor['linhas'] = array_values($mergedLines);
    $setores = [];
    $inserted = false;
    foreach (($fluxo['setores'] ?? []) as $setor) {
        $nomeNorm = agr_norm((string)($setor['nome'] ?? ''));
        $id = (string)($setor['id'] ?? '');
        $shouldMerge = $id === $targetSetorId || in_array($nomeNorm, $sourceSetorNames, true);
        if ($shouldMerge) {
            if (!$inserted) {
                $setores[] = $targetSetor;
                $inserted = true;
            }
            continue;
        }
        $setores[] = $setor;
    }
    $fluxo['setores'] = $setores;
    $fluxo['updated_at'] = $now;
    cod_12_05_safe_write_json($fluxoPath, $fluxo);
}

$cadSetores = [];
$seenTarget = false;
foreach (($cadastros['setores'] ?? []) as $setor) {
    $nomeNorm = agr_norm((string)($setor['nome'] ?? ''));
    $id = (string)($setor['id'] ?? '');
    $shouldMerge = $id === $targetSetorId || in_array($nomeNorm, $sourceSetorNames, true);
    if ($shouldMerge) {
        if (!$seenTarget) {
            $setor['id'] = $targetSetorId;
            $setor['nome'] = $targetSetorName;
            $setor['descricao'] = $targetSetorName;
            $setor['ativo'] = 1;
            $setor['updated_at'] = $now;
            $cadSetores[] = $setor;
            $seenTarget = true;
        }
        continue;
    }
    $cadSetores[] = $setor;
}
$cadastros['setores'] = $cadSetores;
foreach (($cadastros['linhas'] ?? []) as $idx => $linha) {
    if (($linha['setor_id'] ?? '') === $targetSetorId || in_array(agr_norm((string)($linha['setor_nome'] ?? '')), $sourceSetorNames, true)) {
        $cadastros['linhas'][$idx]['setor_id'] = $targetSetorId;
        $cadastros['linhas'][$idx]['updated_at'] = $now;
    }
}
$cadastros['updated_at'] = $now;
cod_12_05_safe_write_json($cadastrosPath, $cadastros);

echo json_encode([
    'status' => 'success',
    'setor' => $targetSetorName,
    'setores_agrupados' => $mergedSetores,
    'linhas_no_setor' => $targetSetor ? count($targetSetor['linhas']) : 0,
    'linhas_renomeadas_por_conflito' => $renamedLines,
    'fluxo_packing_recuperado_de' => $bestPacking['source'] ?? null,
    'fluxo_packing_nos' => $bestPacking['nodes'] ?? 0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

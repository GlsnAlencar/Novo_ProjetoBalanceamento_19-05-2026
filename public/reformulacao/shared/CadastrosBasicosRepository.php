<?php
require_once __DIR__ . '/../safe_storage.php';

function cb_now() {
    return date('Y-m-d H:i:s');
}

function cb_data_path() {
    return __DIR__ . '/../../../data/ativos/cadastros_basicos.json';
}

function cb_id($prefix) {
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$prefix);
    return ($safe ?: 'cad') . '_' . date('YmdHis') . '_' . random_int(1000, 9999);
}

function cb_default_data() {
    return [
        'version' => 1,
        'module_context' => [
            'scope' => 'CADASTROS_BASICOS_COMPARTILHADOS',
            'storage' => 'data/ativos/cadastros_basicos.json',
            'shared_with' => ['editor_bpm', 'arvore_estrutura'],
            'future_ready_for' => ['calibradora', 'cronoanalise', 'balanceamento']
        ],
        'updated_at' => cb_now(),
        'setores' => [],
        'linhas' => [],
        'postos' => [],
        'maquinas' => [],
        'tipos_embalagem' => [],
        'unidades' => [],
        'produtos_itens' => [],
        'faixas_calibradora' => [],
        'atividades_padrao' => []
    ];
}

function cb_catalog_config() {
    return [
        'setores' => ['label' => 'Setores', 'prefix' => 'setor'],
        'linhas' => ['label' => 'Linhas', 'prefix' => 'linha'],
        'postos' => ['label' => 'Postos', 'prefix' => 'posto'],
        'maquinas' => ['label' => 'Maquinas', 'prefix' => 'maq'],
        'tipos_embalagem' => ['label' => 'Tipos de Embalagem', 'prefix' => 'emb'],
        'unidades' => ['label' => 'Unidades', 'prefix' => 'un'],
        'produtos_itens' => ['label' => 'Produtos / Itens', 'prefix' => 'item'],
        'faixas_calibradora' => ['label' => 'Faixas da Calibradora', 'prefix' => 'faixa'],
        'atividades_padrao' => ['label' => 'Atividades Padrao', 'prefix' => 'ativ']
    ];
}

function cb_normalize_row($row, $catalog, $fallback_name = '') {
    $row = is_array($row) ? $row : [];
    $prefix = cb_catalog_config()[$catalog]['prefix'] ?? 'cad';
    $codigo = trim((string)($row['codigo'] ?? ''));
    $nome = trim((string)($row['nome'] ?? ($row['descricao'] ?? $fallback_name)));

    return array_merge($row, [
        'id' => trim((string)($row['id'] ?? '')) !== '' ? (string)$row['id'] : cb_id($prefix),
        'codigo' => $codigo !== '' ? $codigo : strtoupper($prefix) . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'nome' => $nome,
        'descricao' => trim((string)($row['descricao'] ?? $nome)),
        'ativo' => isset($row['ativo']) ? (int)(bool)$row['ativo'] : 1,
        'created_at' => $row['created_at'] ?? ($row['criado_em'] ?? cb_now()),
        'updated_at' => cb_now()
    ]);
}

function cb_load() {
    $default = cb_default_data();
    $data = cod_12_05_safe_load_json(
        cb_data_path(),
        'cb_default_data',
        fn($candidate) => is_array($candidate)
    );

    foreach ($default as $key => $value) {
        if (!isset($data[$key])) {
            $data[$key] = $value;
        }
    }
    foreach (array_keys(cb_catalog_config()) as $catalog) {
        $rows = [];
        foreach (($data[$catalog] ?? []) as $row) {
            $rows[] = cb_normalize_row($row, $catalog);
        }
        $data[$catalog] = $rows;
    }
    $data['module_context'] = $default['module_context'];
    return $data;
}

function cb_save($data) {
    $data['updated_at'] = cb_now();
    cod_12_05_safe_write_json(cb_data_path(), $data);
}

function cb_find_index($data, $catalog, $id) {
    foreach (($data[$catalog] ?? []) as $idx => $row) {
        if (($row['id'] ?? '') === $id) {
            return $idx;
        }
    }
    return -1;
}

function cb_find_by_name($data, $catalog, $nome) {
    $nome = strtolower(trim((string)$nome));
    foreach (($data[$catalog] ?? []) as $row) {
        if (strtolower(trim((string)($row['nome'] ?? ''))) === $nome) {
            return $row;
        }
    }
    return null;
}

function cb_upsert($catalog, $payload) {
    $data = cb_load();
    if (!isset($data[$catalog])) {
        return null;
    }
    [$data, $row, $changed] = cb_upsert_in_data($data, $catalog, $payload);
    if ($changed) {
        cb_save($data);
    }
    return $row;
}

function cb_upsert_in_data($data, $catalog, $payload) {
    $incoming_id = trim((string)($payload['id'] ?? ''));
    $idx = $incoming_id !== '' ? cb_find_index($data, $catalog, $incoming_id) : -1;
    if ($idx < 0 && trim((string)($payload['nome'] ?? '')) !== '') {
        $existing = cb_find_by_name($data, $catalog, $payload['nome']);
        if ($existing) {
            $idx = cb_find_index($data, $catalog, $existing['id']);
            $payload['id'] = $existing['id'];
        }
    }

    $base = $idx >= 0 ? $data[$catalog][$idx] : [];
    if ($idx >= 0 && trim((string)($payload['codigo'] ?? '')) === '' && trim((string)($base['codigo'] ?? '')) !== '') {
        unset($payload['codigo']);
    }
    $row = cb_normalize_row(array_merge($base, $payload), $catalog);
    if ($idx >= 0) {
        $candidate = array_merge($data[$catalog][$idx], $row);
        $changed = cb_row_signature($candidate) !== cb_row_signature($data[$catalog][$idx]);
        if ($changed) {
            $candidate['updated_at'] = cb_now();
            $data[$catalog][$idx] = $candidate;
            $row = $candidate;
        } else {
            $row = $data[$catalog][$idx];
        }
    } else {
        $data[$catalog][] = $row;
        $changed = true;
    }
    return [$data, $row, $changed];
}

function cb_row_signature($row) {
    $copy = $row;
    unset($copy['updated_at']);
    ksort($copy);
    return json_encode($copy, JSON_UNESCAPED_UNICODE);
}

function cb_import_many($catalog, $payloads) {
    $data = cb_load();
    $changed = false;
    foreach ($payloads as $payload) {
        [$data, $row, $row_changed] = cb_upsert_in_data($data, $catalog, $payload);
        $changed = $changed || $row_changed;
    }
    if ($changed) {
        cb_save($data);
    }
}

function cb_set_active($catalog, $id, $active) {
    $data = cb_load();
    $idx = cb_find_index($data, $catalog, $id);
    if ($idx < 0) {
        return false;
    }
    $data[$catalog][$idx]['ativo'] = $active ? 1 : 0;
    $data[$catalog][$idx]['updated_at'] = cb_now();
    cb_save($data);
    return true;
}

function cb_list($catalog, $active_only = false) {
    $data = cb_load();
    $rows = $data[$catalog] ?? [];
    if ($active_only) {
        $rows = array_values(array_filter($rows, fn($row) => (int)($row['ativo'] ?? 1) === 1));
    }
    usort($rows, fn($a, $b) => strnatcasecmp((string)($a['nome'] ?? ''), (string)($b['nome'] ?? '')));
    return $rows;
}

function cb_import_fluxo_data($fluxo_data) {
    $setor_rows = [];
    $linha_rows = [];
    $posto_rows = [];
    foreach (($fluxo_data['setores'] ?? []) as $setor) {
        $setor_id = $setor['id'] ?? '';
        $setor_rows[] = [
            'id' => $setor['id'] ?? '',
            'codigo' => $setor['codigo'] ?? '',
            'nome' => $setor['nome'] ?? '',
            'ativo' => 1,
            'origem' => 'editor_bpm'
        ];
        foreach (($setor['linhas'] ?? []) as $linha) {
            $linha_rows[] = [
                'id' => $linha['id'] ?? '',
                'codigo' => $linha['codigo'] ?? '',
                'nome' => $linha['nome'] ?? '',
                'setor_id' => $setor_id,
                'ativo' => 1,
                'origem' => 'editor_bpm'
            ];
            $nodes = $linha['drawflow_data']['drawflow']['Home']['data'] ?? [];
            foreach ($nodes as $node) {
                $node_data = $node['data'] ?? [];
                if (($node_data['type'] ?? 'node') !== 'node') {
                    continue;
                }
                $posto_rows[] = [
                    'id' => $node_data['id'] ?? '',
                    'nome' => $node_data['name'] ?? '',
                    'tempo_ciclo' => $node_data['tc'] ?? 0,
                    'icone' => $node_data['icon'] ?? 'fa-industry',
                    'ativo' => 1,
                    'origem' => 'editor_bpm'
                ];
            }
        }
    }
    cb_import_many('setores', $setor_rows);
    cb_import_many('linhas', $linha_rows);
    cb_import_many('postos', $posto_rows);
}

function cb_import_arvore_data($arvore_data) {
    $unidade_rows = [];
    $tipo_rows = [];
    $item_rows = [];
    foreach (($arvore_data['cadastro_unidades_base'] ?? []) as $unidade) {
        $unidade_rows[] = ['codigo' => (string)$unidade, 'nome' => (string)$unidade, 'ativo' => 1, 'origem' => 'arvore_estrutura'];
    }
    foreach (($arvore_data['cadastro_tipos_item'] ?? []) as $tipo) {
        $tipo_rows[] = ['codigo' => (string)$tipo, 'nome' => (string)$tipo, 'ativo' => 1, 'origem' => 'arvore_estrutura'];
    }
    foreach (($arvore_data['tabela_itens'] ?? []) as $item) {
        $item_rows[] = [
            'id' => $item['id'] ?? '',
            'codigo' => $item['codigo'] ?? '',
            'nome' => $item['nome'] ?? '',
            'descricao' => $item['observacao'] ?? ($item['nome'] ?? ''),
            'tipo_item' => $item['tipo_item'] ?? '',
            'unidade_base' => $item['unidade_base'] ?? '',
            'categoria' => $item['categoria'] ?? '',
            'grupo' => $item['grupo'] ?? '',
            'ativo' => $item['ativo'] ?? 1,
            'origem' => 'arvore_estrutura'
        ];
    }
    cb_import_many('unidades', $unidade_rows);
    cb_import_many('tipos_embalagem', $tipo_rows);
    cb_import_many('produtos_itens', $item_rows);
}

function cb_linhas_por_setor($setor_id) {
    return array_values(array_filter(cb_list('linhas', true), fn($row) => ($row['setor_id'] ?? '') === $setor_id));
}

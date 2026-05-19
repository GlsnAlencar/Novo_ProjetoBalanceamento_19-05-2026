<?php
/**
 * Arvore de Estrutura - base BOM/MRP isolada da Reformulacao.
 *
 * Persistencia relacional em JSON:
 *   data/ativos/arvore_estrutura.json
 */
require_once __DIR__ . '/safe_storage.php';
require_once __DIR__ . '/module_routes.php';
require_once __DIR__ . '/shared/CadastrosBasicosRepository.php';

date_default_timezone_set('America/Sao_Paulo');

const AE_SCOPE = 'REFORMULACAO_ARVORE_ESTRUTURA';
const AE_MAX_TREE_DEPTH = 30;

function ae_data_path() {
    $path = rf_route('arvore_estrutura', 'storage');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $path;
}

function ae_now() {
    return date('Y-m-d H:i:s');
}

function ae_id($prefix) {
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $prefix);
    return ($safe ?: 'ae') . '_' . date('YmdHis') . '_' . random_int(1000, 9999);
}

function ae_item_code($data) {
    $max = 0;
    foreach (($data['tabela_itens'] ?? []) as $item) {
        if (preg_match('/^ITM-(\d+)$/', (string)($item['codigo'] ?? ''), $matches)) {
            $max = max($max, (int)$matches[1]);
        }
    }
    return 'ITM-' . str_pad((string)($max + 1), 5, '0', STR_PAD_LEFT);
}

function ae_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ae_num($value, $default = 0) {
    if (is_string($value)) {
        $value = str_replace(',', '.', $value);
    }
    return is_numeric($value) ? (float)$value : $default;
}

function ae_csv_value($row, $map, $field, $default = '') {
    if (!isset($map[$field])) {
        return $default;
    }
    $idx = $map[$field];
    return array_key_exists($idx, $row) ? $row[$idx] : $default;
}

function ae_fmt($value, $decimals = 2) {
    return number_format((float)$value, $decimals, ',', '.');
}

function ae_default_data() {
    return [
        'version' => 2,
        'module_context' => [
            'scope' => AE_SCOPE,
            'storage' => 'data/ativos/arvore_estrutura.json',
            'isolated_from' => ['fluxo', 'postos', 'balanceamento', 'cronoanalise'],
            'future_ready_for' => ['mrp', 'cronoanalise', 'fluxo', 'postos', 'balanceamento']
        ],
        'updated_at' => ae_now(),
        'tabela_itens' => [],
        'tabela_arvores' => [],
        'tabela_arvore_composicao' => [],
        'tabela_item_parametros' => [],
        'tabela_item_conversoes' => [],
        'cadastro_tipos_item' => ['Produto', 'Embalagem', 'Insumo', 'Servico', 'Recurso', 'Componente'],
        'cadastro_unidades_base' => ['un', 'kg', 'g', 'l', 'ml', 'ctt', 'pallet', 'operacao'],
        'historico' => []
    ];
}

function ae_normalize_data($data) {
    $default = ae_default_data();
    if (!is_array($data)) {
        return $default;
    }

    foreach ($default as $key => $value) {
        if (!isset($data[$key])) {
            $data[$key] = $value;
        }
    }

    if (!empty($data['itens']) && empty($data['tabela_itens'])) {
        foreach ($data['itens'] as $item) {
            $data['tabela_itens'][] = [
                'id' => $item['id'] ?? ae_id('item'),
                'codigo' => $item['codigo'] ?? '',
                'nome' => $item['nome'] ?? '',
                'codigo_rm' => $item['codigo_rm'] ?? '',
                'produto_rm' => $item['produto_rm'] ?? '',
                'peso_tara' => $item['peso_tara'] ?? null,
                'tipo_item' => $item['tipo_item'] ?? '',
                'categoria' => $item['categoria'] ?? '',
                'grupo' => $item['grupo'] ?? '',
                'unidade_base' => $item['unidade_base'] ?? '',
                'fator_conversao_padrao' => $item['fator_conversao_padrao'] ?? 1,
                'ativo' => $item['ativo'] ?? 1,
                'observacao' => $item['observacao'] ?? '',
                'criado_em' => $item['created_at'] ?? ($item['criado_em'] ?? ae_now()),
                'atualizado_em' => $item['updated_at'] ?? ($item['atualizado_em'] ?? ae_now())
            ];
        }
    }

    if (!empty($data['composicoes']) && empty($data['tabela_arvore_composicao']) && !empty($data['tabela_itens'])) {
        $root_id = $data['composicoes'][0]['item_pai_id'] ?? $data['tabela_itens'][0]['id'];
        $tree_id = ae_id('arvore');
        $root = ae_find_item($data, $root_id);
        $data['tabela_arvores'][] = [
            'id' => $tree_id,
            'codigo' => 'ARV-001',
            'nome' => $root ? ($root['nome'] ?? 'Arvore inicial') : 'Arvore inicial',
            'item_raiz_id' => $root_id,
            'descricao' => 'Arvore migrada da estrutura inicial.',
            'ativo' => 1,
            'criado_em' => ae_now(),
            'atualizado_em' => ae_now()
        ];
        foreach ($data['composicoes'] as $idx => $row) {
            $data['tabela_arvore_composicao'][] = [
                'id' => $row['id'] ?? ae_id('comp'),
                'arvore_id' => $tree_id,
                'item_pai_id' => $row['item_pai_id'] ?? '',
                'item_filho_id' => $row['item_filho_id'] ?? '',
                'quantidade' => $row['quantidade'] ?? 1,
                'unidade' => $row['unidade'] ?? '',
                'fator_conversao' => $row['fator_conversao'] ?? 1,
                'percentual' => $row['percentual'] ?? null,
                'nivel' => ae_calc_level($data, $tree_id, $row['item_pai_id'] ?? '') + 1,
                'ordem_exibicao' => $idx + 1,
                'observacao' => $row['observacao'] ?? '',
                'ativo' => 1,
                'criado_em' => $row['created_at'] ?? ae_now(),
                'atualizado_em' => $row['updated_at'] ?? ae_now()
            ];
        }
    }

    unset($data['itens'], $data['composicoes']);
    $data['version'] = 2;
    $data['module_context'] = $default['module_context'];
    $data['cadastro_tipos_item'] = ae_unique_clean($data['cadastro_tipos_item'] ?? $default['cadastro_tipos_item']);
    $data['cadastro_unidades_base'] = ae_unique_clean($data['cadastro_unidades_base'] ?? $default['cadastro_unidades_base']);
    if (empty($data['cadastro_tipos_item'])) {
        $data['cadastro_tipos_item'] = $default['cadastro_tipos_item'];
    }
    if (empty($data['cadastro_unidades_base'])) {
        $data['cadastro_unidades_base'] = $default['cadastro_unidades_base'];
    }
    foreach ($data['tabela_itens'] as $idx => $item) {
        $data['tabela_itens'][$idx]['codigo_rm'] = $item['codigo_rm'] ?? '';
        $data['tabela_itens'][$idx]['produto_rm'] = $item['produto_rm'] ?? '';
        $data['tabela_itens'][$idx]['peso_tara'] = array_key_exists('peso_tara', $item) ? $item['peso_tara'] : null;
    }
    return $data;
}

function ae_unique_clean($values) {
    $clean = [];
    foreach ($values as $value) {
        $value = trim((string)$value);
        if ($value !== '' && !in_array($value, $clean, true)) {
            $clean[] = $value;
        }
    }
    sort($clean, SORT_NATURAL | SORT_FLAG_CASE);
    return $clean;
}

function ae_load() {
    $data = cod_12_05_safe_load_json(
        ae_data_path(),
        'ae_default_data',
        fn($candidate) => is_array($candidate)
    );
    $normalized = ae_normalize_data($data);
    if ($normalized !== $data) {
        ae_save($normalized);
    }
    return ae_sync_shared_catalogs($normalized);
}

function ae_save($data) {
    $data['updated_at'] = ae_now();
    cb_import_arvore_data($data);
    cod_12_05_safe_write_json(ae_data_path(), $data);
}

function ae_is_active($row) {
    return (int)($row['ativo'] ?? 1) === 1;
}

function ae_status_summary($data) {
    $labels = [
        'tabela_itens' => 'Itens',
        'tabela_arvores' => 'Arvores',
        'tabela_arvore_composicao' => 'Vinculos'
    ];
    $summary = [];
    foreach ($labels as $table => $label) {
        $active = 0;
        $inactive = 0;
        foreach (($data[$table] ?? []) as $row) {
            if (ae_is_active($row)) {
                $active++;
            } else {
                $inactive++;
            }
        }
        $summary[$table] = [
            'label' => $label,
            'active' => $active,
            'inactive' => $inactive,
            'total' => $active + $inactive
        ];
    }
    return $summary;
}

function ae_backup_paths() {
    $storage = ae_data_path();
    $data_root = dirname(dirname($storage));
    $name = pathinfo($storage, PATHINFO_FILENAME);
    $paths = [];
    foreach ([
        $data_root . '/backups/' . $name . '_*.json',
        $data_root . '/reformulacao/_backups/' . $name . '_*.json',
        $data_root . '/reformulacao/' . basename($storage),
    ] as $pattern) {
        foreach (glob($pattern) ?: [] as $path) {
            if (is_file($path) && realpath($path) !== realpath($storage)) {
                $paths[realpath($path) ?: $path] = $path;
            }
        }
    }
    ksort($paths);
    return array_values($paths);
}

function ae_load_backup_json($path) {
    $json = @file_get_contents($path);
    if ($json === false) {
        return null;
    }
    $data = json_decode($json, true);
    return is_array($data) && json_last_error() === JSON_ERROR_NONE ? $data : null;
}

function ae_backup_active_id_sets() {
    $sets = [
        'tabela_itens' => [],
        'tabela_arvores' => [],
        'tabela_arvore_composicao' => []
    ];
    $files_read = 0;
    $files_invalid = 0;
    foreach (ae_backup_paths() as $path) {
        $backup = ae_load_backup_json($path);
        if (!$backup) {
            $files_invalid++;
            continue;
        }
        $files_read++;
        foreach (array_keys($sets) as $table) {
            foreach (($backup[$table] ?? []) as $row) {
                $id = trim((string)($row['id'] ?? ''));
                if ($id !== '' && ae_is_active($row)) {
                    $sets[$table][$id] = true;
                }
            }
        }
    }
    return [$sets, $files_read, $files_invalid];
}

function ae_collect_all_branch_ids($data, $tree_id, $item_id, &$item_ids, &$comp_ids, $visited = [], $depth = 0) {
    if ($tree_id === '' || $item_id === '' || $depth > AE_MAX_TREE_DEPTH || isset($visited[$item_id])) {
        return;
    }
    $visited[$item_id] = true;
    $item_ids[$item_id] = true;
    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        if (($comp['arvore_id'] ?? '') !== $tree_id || ($comp['item_pai_id'] ?? '') !== $item_id) {
            continue;
        }
        $comp_id = trim((string)($comp['id'] ?? ''));
        $child_id = trim((string)($comp['item_filho_id'] ?? ''));
        if ($comp_id !== '') {
            $comp_ids[$comp_id] = true;
        }
        if ($child_id !== '') {
            ae_collect_all_branch_ids($data, $tree_id, $child_id, $item_ids, $comp_ids, $visited, $depth + 1);
        }
    }
}

function ae_deleted_guard_sets($data) {
    $guard = [
        'tabela_itens' => [],
        'tabela_arvores' => [],
        'tabela_arvore_composicao' => []
    ];
    foreach (($data['historico'] ?? []) as $log) {
        $action = strtolower((string)($log['acao'] ?? ''));
        if (strpos($action, 'exclu') === false) {
            continue;
        }

        $item_id = trim((string)($log['item_id'] ?? ''));
        $tree_id = trim((string)($log['arvore_id'] ?? ''));
        $comp_id = trim((string)($log['composicao_id'] ?? ''));
        if ($item_id !== '') {
            $guard['tabela_itens'][$item_id] = true;
        }
        if ($tree_id !== '' && strpos($action, 'arvore') !== false) {
            $guard['tabela_arvores'][$tree_id] = true;
        }
        if ($comp_id !== '') {
            $guard['tabela_arvore_composicao'][$comp_id] = true;
        }
        if ($tree_id !== '' && $item_id !== '') {
            $branch_items = [];
            $branch_comps = [];
            ae_collect_all_branch_ids($data, $tree_id, $item_id, $branch_items, $branch_comps);
            foreach ($branch_items as $id => $_) {
                $guard['tabela_itens'][$id] = true;
            }
            foreach ($branch_comps as $id => $_) {
                $guard['tabela_arvore_composicao'][$id] = true;
            }
        }
    }
    return $guard;
}

function ae_backup_recovery_preview($data) {
    [$backup_sets, $files_read, $files_invalid] = ae_backup_active_id_sets();
    $deleted_guard = ae_deleted_guard_sets($data);
    $preview = [
        'files_read' => $files_read,
        'files_invalid' => $files_invalid,
        'tables' => [],
        'total_restorable' => 0,
        'backup_only_ignored' => 0,
        'deleted_ignored' => 0
    ];
    foreach ($backup_sets as $table => $ids) {
        $current_ids = [];
        $restorable = 0;
        $deleted_ignored = 0;
        foreach (($data[$table] ?? []) as $row) {
            $id = trim((string)($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $current_ids[$id] = true;
            if (!ae_is_active($row) && isset($ids[$id])) {
                if (isset($deleted_guard[$table][$id])) {
                    $deleted_ignored++;
                } else {
                    $restorable++;
                }
            }
        }
        $backup_only = count(array_diff_key($ids, $current_ids));
        $preview['tables'][$table] = [
            'restorable' => $restorable,
            'backup_only_ignored' => $backup_only,
            'deleted_ignored' => $deleted_ignored
        ];
        $preview['total_restorable'] += $restorable;
        $preview['backup_only_ignored'] += $backup_only;
        $preview['deleted_ignored'] += $deleted_ignored;
    }
    return $preview;
}

function ae_restore_backup_inactive(&$data) {
    $preview = ae_backup_recovery_preview($data);
    [$backup_sets] = ae_backup_active_id_sets();
    $deleted_guard = ae_deleted_guard_sets($data);
    $restored = [
        'tabela_itens' => 0,
        'tabela_arvores' => 0,
        'tabela_arvore_composicao' => 0
    ];
    foreach (array_keys($restored) as $table) {
        foreach (($data[$table] ?? []) as $idx => $row) {
            $id = trim((string)($row['id'] ?? ''));
            if ($id !== '' && !ae_is_active($row) && isset($backup_sets[$table][$id]) && !isset($deleted_guard[$table][$id])) {
                $data[$table][$idx]['ativo'] = 1;
                $data[$table][$idx]['atualizado_em'] = ae_now();
                $restored[$table]++;
            }
        }
    }
    $preview['restored'] = $restored;
    $preview['total_restored'] = array_sum($restored);
    return $preview;
}

function ae_sync_shared_catalogs($data) {
    cb_import_arvore_data($data);
    $shared = cb_load();

    $shared_units = array_map(fn($row) => (string)($row['nome'] ?? ''), array_filter($shared['unidades'] ?? [], fn($row) => (int)($row['ativo'] ?? 1) === 1));
    $shared_types = array_map(fn($row) => (string)($row['nome'] ?? ''), array_filter($shared['tipos_embalagem'] ?? [], fn($row) => (int)($row['ativo'] ?? 1) === 1));
    $data['cadastro_unidades_base'] = ae_unique_clean(array_merge($data['cadastro_unidades_base'] ?? [], $shared_units));
    $data['cadastro_tipos_item'] = ae_unique_clean(array_merge($data['cadastro_tipos_item'] ?? [], $shared_types));

    $local_by_id = [];
    foreach (($data['tabela_itens'] ?? []) as $idx => $item) {
        $local_by_id[$item['id'] ?? ''] = $idx;
    }

    foreach (($shared['produtos_itens'] ?? []) as $shared_item) {
        if ((int)($shared_item['ativo'] ?? 1) !== 1 && !isset($local_by_id[$shared_item['id'] ?? ''])) {
            continue;
        }

        $payload = [
            'id' => $shared_item['id'] ?? ae_id('item'),
            'codigo' => $shared_item['codigo'] ?? '',
            'nome' => $shared_item['nome'] ?? '',
            'codigo_rm' => $shared_item['codigo_rm'] ?? '',
            'produto_rm' => $shared_item['produto_rm'] ?? '',
            'peso_tara' => $shared_item['peso_tara'] ?? null,
            'tipo_item' => $shared_item['tipo_item'] ?? '',
            'categoria' => $shared_item['categoria'] ?? '',
            'grupo' => $shared_item['grupo'] ?? '',
            'unidade_base' => $shared_item['unidade_base'] ?? '',
            'fator_conversao_padrao' => $shared_item['fator_conversao_padrao'] ?? 1,
            'ativo' => $shared_item['ativo'] ?? 1,
            'observacao' => $shared_item['descricao'] ?? ($shared_item['observacao'] ?? ''),
            'criado_em' => $shared_item['created_at'] ?? ae_now(),
            'atualizado_em' => $shared_item['updated_at'] ?? ae_now()
        ];

        $idx = $local_by_id[$payload['id']] ?? -1;
        if ($idx >= 0) {
            $data['tabela_itens'][$idx] = array_merge($data['tabela_itens'][$idx], $payload);
        } else {
            $data['tabela_itens'][] = $payload;
        }
    }

    $data['module_context']['shared_catalogs'] = [
        'produtos_itens' => 'cadastros_basicos.produtos_itens',
        'unidades' => 'cadastros_basicos.unidades',
        'tipos_embalagem' => 'cadastros_basicos.tipos_embalagem'
    ];
    return $data;
}

function ae_log(&$data, $acao, $item_id = '', $arvore_id = '', $composicao_id = '', $detalhe = '') {
    $data['historico'][] = [
        'id' => ae_id('hist'),
        'acao' => $acao,
        'item_id' => $item_id,
        'arvore_id' => $arvore_id,
        'composicao_id' => $composicao_id,
        'detalhe' => $detalhe,
        'criado_em' => ae_now()
    ];
}

function ae_find_item_index($data, $id) {
    foreach ($data['tabela_itens'] as $idx => $item) {
        if (($item['id'] ?? '') === $id) {
            return $idx;
        }
    }
    return -1;
}

function ae_find_item($data, $id) {
    $idx = ae_find_item_index($data, $id);
    return $idx >= 0 ? $data['tabela_itens'][$idx] : null;
}

function ae_find_item_by_code($data, $codigo, $active_only = false) {
    $codigo = strtolower(trim($codigo));
    foreach ($data['tabela_itens'] as $item) {
        if ($active_only && (int)($item['ativo'] ?? 1) !== 1) {
            continue;
        }
        if (strtolower(trim($item['codigo'] ?? '')) === $codigo) {
            return $item;
        }
    }
    return null;
}

function ae_find_tree($data, $id) {
    foreach ($data['tabela_arvores'] as $tree) {
        if (($tree['id'] ?? '') === $id) {
            return $tree;
        }
    }
    return null;
}

function ae_find_comp_index($data, $id) {
    foreach ($data['tabela_arvore_composicao'] as $idx => $comp) {
        if (($comp['id'] ?? '') === $id) {
            return $idx;
        }
    }
    return -1;
}

function ae_find_comp($data, $id) {
    $idx = ae_find_comp_index($data, $id);
    return $idx >= 0 ? $data['tabela_arvore_composicao'][$idx] : null;
}

function ae_children($data, $arvore_id, $item_pai_id) {
    $rows = [];
    foreach ($data['tabela_arvore_composicao'] as $comp) {
        if (($comp['ativo'] ?? 1)
            && ($comp['arvore_id'] ?? '') === $arvore_id
            && ($comp['item_pai_id'] ?? '') === $item_pai_id) {
            $rows[] = $comp;
        }
    }
    usort($rows, fn($a, $b) => ((int)($a['ordem_exibicao'] ?? 0)) <=> ((int)($b['ordem_exibicao'] ?? 0)));
    return $rows;
}

function ae_parent_comp($data, $arvore_id, $item_id) {
    foreach ($data['tabela_arvore_composicao'] as $comp) {
        if (($comp['ativo'] ?? 1)
            && ($comp['arvore_id'] ?? '') === $arvore_id
            && ($comp['item_filho_id'] ?? '') === $item_id) {
            return $comp;
        }
    }
    return null;
}

function ae_calc_level($data, $arvore_id, $item_id) {
    $tree = ae_find_tree($data, $arvore_id);
    if (!$tree || ($tree['item_raiz_id'] ?? '') === $item_id) {
        return 0;
    }
    $parent = ae_parent_comp($data, $arvore_id, $item_id);
    return $parent ? (int)($parent['nivel'] ?? 1) : 0;
}

function ae_has_path($data, $arvore_id, $from_item_id, $to_item_id, $visited = [], $depth = 0) {
    if ($from_item_id === $to_item_id) {
        return true;
    }
    if ($depth >= AE_MAX_TREE_DEPTH) {
        return false;
    }
    if (isset($visited[$from_item_id])) {
        return false;
    }
    $visited[$from_item_id] = true;
    foreach (ae_children($data, $arvore_id, $from_item_id) as $child) {
        if (ae_has_path($data, $arvore_id, $child['item_filho_id'] ?? '', $to_item_id, $visited, $depth + 1)) {
            return true;
        }
    }
    return false;
}

function ae_param_index($data, $item_id) {
    foreach ($data['tabela_item_parametros'] as $idx => $row) {
        if (($row['item_id'] ?? '') === $item_id) {
            return $idx;
        }
    }
    return -1;
}

function ae_param($data, $item_id) {
    $idx = ae_param_index($data, $item_id);
    return $idx >= 0 ? $data['tabela_item_parametros'][$idx] : [
        'id' => '',
        'item_id' => $item_id,
        'unidade_compra' => '',
        'unidade_producao' => '',
        'lead_time_compra' => 0,
        'lead_time_producao' => 0,
        'lote_compra' => '',
        'lote_producao' => '',
        'estoque_seguranca' => 0,
        'tipo_planejamento' => '',
        'observacao' => ''
    ];
}

function ae_conversions($data, $item_id) {
    return array_values(array_filter($data['tabela_item_conversoes'], fn($row) => ($row['ativo'] ?? 1) && ($row['item_id'] ?? '') === $item_id));
}

function ae_tree_rows(&$rows, $data, $arvore_id, $item_id, $nivel = 0, $parent_code = '', $parent_name = '', $comp = null, $visited = []) {
    $item = ae_find_item($data, $item_id);
    if (!$item || isset($visited[$item_id]) || $nivel > AE_MAX_TREE_DEPTH) {
        return;
    }
    $visited[$item_id] = true;
    $children = ae_children($data, $arvore_id, $item_id);
    $rows[] = [
        'item' => $item,
        'comp' => $comp,
        'nivel' => $nivel,
        'has_children' => count($children) > 0,
        'parent_code' => $parent_code,
        'parent_name' => $parent_name
    ];
    foreach ($children as $child_comp) {
        $child_item = ae_find_item($data, $child_comp['item_filho_id'] ?? '');
        ae_tree_rows(
            $rows,
            $data,
            $arvore_id,
            $child_comp['item_filho_id'] ?? '',
            $nivel + 1,
            $item['codigo'] ?? '',
            $item['nome'] ?? '',
            $child_comp,
            $visited
        );
    }
}

function ae_tree_flat($data, $tree) {
    $rows = [];
    if ($tree && !empty($tree['item_raiz_id'])) {
        ae_tree_rows($rows, $data, $tree['id'], $tree['item_raiz_id']);
    }
    return $rows;
}

function ae_missing_tara_groups($tree_rows) {
    $groups = [];
    $stack = [];
    foreach ($tree_rows as $row) {
        $nivel = (int)($row['nivel'] ?? 0);
        $item = $row['item'] ?? [];
        $stack[$nivel] = $item;
        foreach (array_keys($stack) as $level_key) {
            if ($level_key > $nivel) {
                unset($stack[$level_key]);
            }
        }

        $peso = $item['peso_tara'] ?? null;
        if ($peso !== null && $peso !== '') {
            continue;
        }

        $parent = $nivel > 0 ? ($stack[$nivel - 1] ?? null) : null;
        $parent_label = $parent
            ? (($parent['codigo'] ?? '') . ' - ' . ($parent['nome'] ?? ''))
            : 'Item raiz';
        if (!isset($groups[$parent_label])) {
            $groups[$parent_label] = [];
        }
        $groups[$parent_label][] = [
            'codigo' => $item['codigo'] ?? '',
            'nome' => $item['nome'] ?? '',
            'nivel' => $nivel
        ];
    }
    return $groups;
}

function ae_leaf_totals(&$totals, $data, $arvore_id, $item_id, $total_quantidade = 1, $unidade = '', $nivel = 0, $visited = []) {
    $item = ae_find_item($data, $item_id);
    if (!$item || isset($visited[$item_id]) || $nivel > AE_MAX_TREE_DEPTH) {
        return;
    }

    $visited[$item_id] = true;
    $children = ae_children($data, $arvore_id, $item_id);
    if (empty($children)) {
        if (!isset($totals[$item_id])) {
            $totals[$item_id] = [
                'item' => $item,
                'quantidade_total' => 0,
                'unidade' => $unidade !== '' ? $unidade : ($item['unidade_base'] ?? ''),
                'nivel_relativo' => $nivel
            ];
        }
        $totals[$item_id]['quantidade_total'] += $total_quantidade;
        $totals[$item_id]['nivel_relativo'] = max((int)$totals[$item_id]['nivel_relativo'], $nivel);
        return;
    }

    foreach ($children as $child_comp) {
        $child_id = $child_comp['item_filho_id'] ?? '';
        $child = ae_find_item($data, $child_id);
        $child_qty = max(0.000001, ae_num($child_comp['quantidade'] ?? 1, 1));
        ae_leaf_totals(
            $totals,
            $data,
            $arvore_id,
            $child_id,
            $total_quantidade * $child_qty,
            $child_comp['unidade'] ?? ($child['unidade_base'] ?? ''),
            $nivel + 1,
            $visited
        );
    }
}

function ae_selected_leaf_totals($data, $arvore_id, $item_id) {
    $totals = [];
    ae_leaf_totals($totals, $data, $arvore_id, $item_id);
    uasort($totals, function ($a, $b) {
        return strnatcasecmp(
            (string)(($a['item']['codigo'] ?? '') . ' ' . ($a['item']['nome'] ?? '')),
            (string)(($b['item']['codigo'] ?? '') . ' ' . ($b['item']['nome'] ?? ''))
        );
    });
    return array_values($totals);
}

function ae_next_order($data, $arvore_id, $item_pai_id) {
    $max = 0;
    foreach (ae_children($data, $arvore_id, $item_pai_id) as $child) {
        $max = max($max, (int)($child['ordem_exibicao'] ?? 0));
    }
    return $max + 1;
}

function ae_link_exists($data, $arvore_id, $item_pai_id, $item_filho_id) {
    foreach ($data['tabela_arvore_composicao'] as $comp) {
        if (($comp['ativo'] ?? 1)
            && ($comp['arvore_id'] ?? '') === $arvore_id
            && ($comp['item_pai_id'] ?? '') === $item_pai_id
            && ($comp['item_filho_id'] ?? '') === $item_filho_id) {
            return true;
        }
    }
    return false;
}

function ae_find_subtree_source_tree_id($data, $item_id, $target_tree_id) {
    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        if (!($tree['ativo'] ?? 1) || ($tree['id'] ?? '') === $target_tree_id) {
            continue;
        }
        if (($tree['item_raiz_id'] ?? '') === $item_id) {
            return $tree['id'];
        }
    }

    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        if (!($tree['ativo'] ?? 1) || ($tree['id'] ?? '') === $target_tree_id) {
            continue;
        }
        if (count(ae_children($data, $tree['id'], $item_id)) > 0) {
            return $tree['id'];
        }
    }

    return '';
}

function ae_find_item_root_tree_id($data, $item_id, $prefer_not_tree_id = '') {
    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        if (!($tree['ativo'] ?? 1) || ($tree['id'] ?? '') === $prefer_not_tree_id) {
            continue;
        }
        if (($tree['item_raiz_id'] ?? '') === $item_id) {
            return $tree['id'];
        }
    }
    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        if (($tree['ativo'] ?? 1) && ($tree['item_raiz_id'] ?? '') === $item_id) {
            return $tree['id'];
        }
    }
    return '';
}

function ae_tree_reuse_usages($data, $source_tree_id, $exclude_tree_id = '') {
    $source_tree = ae_find_tree($data, $source_tree_id);
    $root_item_id = $source_tree['item_raiz_id'] ?? '';
    $usages = [];
    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        if (!($comp['ativo'] ?? 1) || ($comp['arvore_id'] ?? '') === $exclude_tree_id) {
            continue;
        }
        $is_linked = ($comp['reuso_arvore_id'] ?? '') === $source_tree_id;
        $is_root_use = $root_item_id !== '' && ($comp['item_filho_id'] ?? '') === $root_item_id;
        if (!$is_linked && !$is_root_use) {
            continue;
        }
        $tree = ae_find_tree($data, $comp['arvore_id'] ?? '');
        $parent = ae_find_item($data, $comp['item_pai_id'] ?? '');
        $child = ae_find_item($data, $comp['item_filho_id'] ?? '');
        $usages[] = [
            'arvore_id' => $comp['arvore_id'] ?? '',
            'arvore_label' => trim(($tree['codigo'] ?? '') . ' - ' . ($tree['nome'] ?? '')),
            'pai_label' => trim(($parent['codigo'] ?? '') . ' - ' . ($parent['nome'] ?? '')),
            'item_label' => trim(($child['codigo'] ?? '') . ' - ' . ($child['nome'] ?? '')),
            'comp_id' => $comp['id'] ?? '',
            'modo' => $comp['reuso_modo'] ?? ($is_root_use ? 'vinculo_item' : '')
        ];
    }
    return $usages;
}

function ae_mark_reuse_branch(&$data, $arvore_id, $item_id, $source_tree_id, $reuse_batch_id, $mode = 'vinculado', $visited = [], $depth = 0) {
    if ($arvore_id === '' || $item_id === '' || isset($visited[$item_id]) || $depth >= AE_MAX_TREE_DEPTH) {
        return 0;
    }
    $visited[$item_id] = true;
    $marked = 0;
    foreach ($data['tabela_arvore_composicao'] as $idx => $comp) {
        if (!($comp['ativo'] ?? 1)
            || ($comp['arvore_id'] ?? '') !== $arvore_id
            || ($comp['item_pai_id'] ?? '') !== $item_id) {
            continue;
        }
        $data['tabela_arvore_composicao'][$idx]['reuso_arvore_id'] = $source_tree_id;
        $data['tabela_arvore_composicao'][$idx]['reuso_lote_id'] = $reuse_batch_id;
        $data['tabela_arvore_composicao'][$idx]['reuso_modo'] = $mode;
        $data['tabela_arvore_composicao'][$idx]['atualizado_em'] = ae_now();
        $marked++;
        $marked += ae_mark_reuse_branch($data, $arvore_id, $comp['item_filho_id'] ?? '', $source_tree_id, $reuse_batch_id, $mode, $visited, $depth + 1);
    }
    return $marked;
}

function ae_detach_reuse_branch(&$data, $arvore_id, $item_id, $independent_id, $visited = [], $depth = 0) {
    if ($arvore_id === '' || $item_id === '' || isset($visited[$item_id]) || $depth >= AE_MAX_TREE_DEPTH) {
        return 0;
    }
    $visited[$item_id] = true;
    $detached = 0;
    foreach ($data['tabela_arvore_composicao'] as $idx => $comp) {
        if (!($comp['ativo'] ?? 1)
            || ($comp['arvore_id'] ?? '') !== $arvore_id
            || ($comp['item_pai_id'] ?? '') !== $item_id) {
            continue;
        }
        $data['tabela_arvore_composicao'][$idx]['reuso_modo'] = 'independente';
        $data['tabela_arvore_composicao'][$idx]['id_verificador_independente'] = $independent_id;
        $data['tabela_arvore_composicao'][$idx]['reuso_arvore_id'] = '';
        $data['tabela_arvore_composicao'][$idx]['atualizado_em'] = ae_now();
        $detached++;
        $detached += ae_detach_reuse_branch($data, $arvore_id, $comp['item_filho_id'] ?? '', $independent_id, $visited, $depth + 1);
    }
    return $detached;
}

function ae_deactivate_item_branch(&$data, $arvore_id, $item_pai_id, $visited = [], $depth = 0) {
    if ($arvore_id === '' || $item_pai_id === '' || $depth >= AE_MAX_TREE_DEPTH || isset($visited[$item_pai_id])) {
        return 0;
    }

    $visited[$item_pai_id] = true;
    $removed = 0;
    foreach ($data['tabela_arvore_composicao'] as $idx => $comp) {
        if (!($comp['ativo'] ?? 1)
            || ($comp['arvore_id'] ?? '') !== $arvore_id
            || ($comp['item_pai_id'] ?? '') !== $item_pai_id) {
            continue;
        }

        $data['tabela_arvore_composicao'][$idx]['ativo'] = 0;
        $data['tabela_arvore_composicao'][$idx]['atualizado_em'] = ae_now();
        $removed++;
        $removed += ae_deactivate_item_branch(
            $data,
            $arvore_id,
            $comp['item_filho_id'] ?? '',
            $visited,
            $depth + 1
        );
    }

    return $removed;
}

function ae_collect_branch_item_ids(&$items, $data, $arvore_id, $item_id, $visited = [], $depth = 0) {
    if ($arvore_id === '' || $item_id === '' || $depth >= AE_MAX_TREE_DEPTH || isset($visited[$item_id])) {
        return;
    }

    $visited[$item_id] = true;
    $items[$item_id] = true;
    foreach (ae_children($data, $arvore_id, $item_id) as $comp) {
        ae_collect_branch_item_ids($items, $data, $arvore_id, $comp['item_filho_id'] ?? '', $visited, $depth + 1);
    }
}

function ae_item_has_active_usage($data, $item_id) {
    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        if (($tree['ativo'] ?? 1) && ($tree['item_raiz_id'] ?? '') === $item_id) {
            return true;
        }
    }
    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        if (($comp['ativo'] ?? 1)
            && (($comp['item_pai_id'] ?? '') === $item_id || ($comp['item_filho_id'] ?? '') === $item_id)) {
            return true;
        }
    }
    return false;
}

function ae_deactivate_unused_items(&$data, $item_ids) {
    $removed = 0;
    foreach (array_keys($item_ids) as $item_id) {
        if (ae_item_has_active_usage($data, $item_id)) {
            continue;
        }
        $idx = ae_find_item_index($data, $item_id);
        if ($idx >= 0 && ($data['tabela_itens'][$idx]['ativo'] ?? 1)) {
            $data['tabela_itens'][$idx]['ativo'] = 0;
            $data['tabela_itens'][$idx]['atualizado_em'] = ae_now();
            $removed++;
        }
    }
    return $removed;
}

function ae_import_item_subtree(&$data, $source_tree_id, $target_tree_id, $parent_item_id, $parent_level, $visited = [], $depth = 0, $reuse_batch_id = '', $reuse_mode = '') {
    if ($source_tree_id === '' || $target_tree_id === '' || $depth >= AE_MAX_TREE_DEPTH || isset($visited[$parent_item_id])) {
        return 0;
    }

    $visited[$parent_item_id] = true;
    $imported = 0;
    foreach (ae_children($data, $source_tree_id, $parent_item_id) as $source_comp) {
        $child_id = $source_comp['item_filho_id'] ?? '';
        $child = ae_find_item($data, $child_id);
        if (!$child || $child_id === '' || $parent_item_id === $child_id) {
            continue;
        }
        if ($parent_level + 1 > AE_MAX_TREE_DEPTH || ae_has_path($data, $target_tree_id, $child_id, $parent_item_id)) {
            continue;
        }

        if (!ae_link_exists($data, $target_tree_id, $parent_item_id, $child_id)) {
            $data['tabela_arvore_composicao'][] = [
                'id' => ae_id('comp'),
                'arvore_id' => $target_tree_id,
                'item_pai_id' => $parent_item_id,
                'item_filho_id' => $child_id,
                'quantidade' => max(0.000001, ae_num($source_comp['quantidade'] ?? 1, 1)),
                'unidade' => trim($source_comp['unidade'] ?? ($child['unidade_base'] ?? '')),
                'fator_conversao' => max(0.000001, ae_num($source_comp['fator_conversao'] ?? 1, 1)),
                'percentual' => ($source_comp['percentual'] ?? '') === '' || ($source_comp['percentual'] ?? null) === null ? null : ae_num($source_comp['percentual']),
                'nivel' => $parent_level + 1,
                'ordem_exibicao' => ae_next_order($data, $target_tree_id, $parent_item_id),
                'observacao' => trim($source_comp['observacao'] ?? ''),
                'ativo' => 1,
                'criado_em' => ae_now(),
                'atualizado_em' => ae_now()
            ];
            if ($reuse_batch_id !== '') {
                $last_idx = count($data['tabela_arvore_composicao']) - 1;
                $data['tabela_arvore_composicao'][$last_idx]['reuso_arvore_id'] = $source_tree_id;
                $data['tabela_arvore_composicao'][$last_idx]['reuso_lote_id'] = $reuse_batch_id;
                $data['tabela_arvore_composicao'][$last_idx]['reuso_modo'] = $reuse_mode !== '' ? $reuse_mode : 'vinculado';
            }
            $imported++;
        }

        $imported += ae_import_item_subtree(
            $data,
            $source_tree_id,
            $target_tree_id,
            $child_id,
            $parent_level + 1,
            $visited,
            $depth + 1,
            $reuse_batch_id,
            $reuse_mode
        );
    }

    return $imported;
}

function ae_unique_tree_code($data, $base_code) {
    $base_code = trim((string)$base_code);
    if ($base_code === '') {
        $base_code = 'ARVORE';
    }
    $candidate_base = $base_code . '-COPIA';
    $used = [];
    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        $code = trim((string)($tree['codigo'] ?? ''));
        if ($code !== '') {
            $used[strtolower($code)] = true;
        }
    }
    $candidate = $candidate_base;
    $suffix = 2;
    while (isset($used[strtolower($candidate)])) {
        $candidate = $candidate_base . '-' . $suffix;
        $suffix++;
    }
    return $candidate;
}

function ae_clone_tree_item(&$data, $source_tree_id, $source_item_id, &$item_map, $visited = [], $depth = 0) {
    if ($source_item_id === '' || isset($item_map[$source_item_id]) || isset($visited[$source_item_id]) || $depth >= AE_MAX_TREE_DEPTH) {
        return;
    }

    $item = ae_find_item($data, $source_item_id);
    if (!$item) {
        return;
    }

    $visited[$source_item_id] = true;
    $clone = $item;
    $clone['id'] = ae_id('item');
    $clone['codigo'] = ae_item_code($data);
    $clone['ativo'] = 1;
    $clone['criado_em'] = ae_now();
    $clone['atualizado_em'] = ae_now();
    $data['tabela_itens'][] = $clone;
    $item_map[$source_item_id] = $clone['id'];

    foreach (ae_children($data, $source_tree_id, $source_item_id) as $child_comp) {
        ae_clone_tree_item($data, $source_tree_id, $child_comp['item_filho_id'] ?? '', $item_map, $visited, $depth + 1);
    }
}

function ae_clone_tree_links(&$data, $source_tree_id, $target_tree_id, $source_parent_id, $parent_level, $item_map, $visited = [], $depth = 0) {
    if ($source_tree_id === '' || $target_tree_id === '' || $source_parent_id === '' || isset($visited[$source_parent_id]) || $depth >= AE_MAX_TREE_DEPTH) {
        return 0;
    }

    $visited[$source_parent_id] = true;
    $copied = 0;
    foreach (ae_children($data, $source_tree_id, $source_parent_id) as $source_comp) {
        $source_child_id = $source_comp['item_filho_id'] ?? '';
        if (!isset($item_map[$source_parent_id], $item_map[$source_child_id])) {
            continue;
        }

        $child = ae_find_item($data, $item_map[$source_child_id]);
        $data['tabela_arvore_composicao'][] = [
            'id' => ae_id('comp'),
            'arvore_id' => $target_tree_id,
            'item_pai_id' => $item_map[$source_parent_id],
            'item_filho_id' => $item_map[$source_child_id],
            'quantidade' => max(0.000001, ae_num($source_comp['quantidade'] ?? 1, 1)),
            'unidade' => trim($source_comp['unidade'] ?? ($child['unidade_base'] ?? '')),
            'fator_conversao' => max(0.000001, ae_num($source_comp['fator_conversao'] ?? 1, 1)),
            'percentual' => ($source_comp['percentual'] ?? '') === '' || ($source_comp['percentual'] ?? null) === null ? null : ae_num($source_comp['percentual']),
            'nivel' => $parent_level + 1,
            'ordem_exibicao' => (int)($source_comp['ordem_exibicao'] ?? ae_next_order($data, $target_tree_id, $item_map[$source_parent_id])),
            'observacao' => trim($source_comp['observacao'] ?? ''),
            'ativo' => 1,
            'criado_em' => ae_now(),
            'atualizado_em' => ae_now()
        ];
        $copied++;
        $copied += ae_clone_tree_links($data, $source_tree_id, $target_tree_id, $source_child_id, $parent_level + 1, $item_map, $visited, $depth + 1);
    }

    return $copied;
}

function ae_upsert_item(&$data, $prefix = '') {
    $codigo = trim($_POST[$prefix . 'codigo'] ?? '');
    $nome = trim($_POST[$prefix . 'nome'] ?? '');
    $auto_codigo = $codigo === '';
    if ($codigo === '') {
        $codigo = ae_item_code($data);
    }
    if ($nome === '') {
        return [null, 'Nome do item e obrigatorio.'];
    }
    $tipo_item = trim($_POST[$prefix . 'tipo_item'] ?? '');
    $unidade_base = trim($_POST[$prefix . 'unidade_base'] ?? '');
    $peso_tara_input = trim($_POST[$prefix . 'peso_tara'] ?? '');
    $peso_tara = $peso_tara_input === '' ? null : max(0, ae_num($peso_tara_input));
    if ($tipo_item !== '') {
        $data['cadastro_tipos_item'] = ae_unique_clean(array_merge($data['cadastro_tipos_item'] ?? [], [$tipo_item]));
    }
    if ($unidade_base !== '') {
        $data['cadastro_unidades_base'] = ae_unique_clean(array_merge($data['cadastro_unidades_base'] ?? [], [$unidade_base]));
    }
    $existing = $auto_codigo ? null : ae_find_item_by_code($data, $codigo, true);
    if ($existing) {
        return [null, 'Ja existe outro item ativo com este codigo.'];
    }

    $item = [
        'id' => ae_id('item'),
        'codigo' => $codigo,
        'nome' => $nome,
        'codigo_rm' => trim($_POST[$prefix . 'codigo_rm'] ?? ''),
        'produto_rm' => trim($_POST[$prefix . 'produto_rm'] ?? ''),
        'peso_tara' => $peso_tara,
        'tipo_item' => $tipo_item,
        'categoria' => trim($_POST[$prefix . 'categoria'] ?? ''),
        'grupo' => trim($_POST[$prefix . 'grupo'] ?? ''),
        'unidade_base' => $unidade_base,
        'fator_conversao_padrao' => ae_num($_POST[$prefix . 'fator_conversao_padrao'] ?? 1, 1),
        'ativo' => isset($_POST[$prefix . 'ativo']) ? 1 : 0,
        'observacao' => trim($_POST[$prefix . 'observacao'] ?? ''),
        'criado_em' => ae_now(),
        'atualizado_em' => ae_now()
    ];
    $data['tabela_itens'][] = $item;
    ae_log($data, 'item criado', $item['id'], '', '', 'Item cadastrado.');
    return [$item, null];
}

function ae_redirect($params = []) {
    $query = $params ? '?' . http_build_query($params) : '';
    header('Location: arvore_estrutura.php' . $query);
    exit;
}

function ae_json_response($payload) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

$data = ae_load();
$message = '';
$message_type = 'success';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $tree = ae_find_tree($data, $_GET['arvore_id'] ?? '');
    if (!$tree) {
        http_response_code(404);
        exit('Arvore nao encontrada.');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="arvore_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $tree['codigo'] ?? 'estrutura') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['nivel', 'codigo_pai', 'item_pai', 'codigo_filho', 'item_filho', 'quantidade', 'unidade', 'fator_conversao', 'percentual', 'observacao'], ';', '"', '');
    foreach (ae_tree_flat($data, $tree) as $row) {
        if (!$row['comp']) {
            continue;
        }
        fputcsv($out, [
            $row['nivel'],
            $row['parent_code'],
            $row['parent_name'],
            $row['item']['codigo'] ?? '',
            $row['item']['nome'] ?? '',
            $row['comp']['quantidade'] ?? '',
            $row['comp']['unidade'] ?? '',
            $row['comp']['fator_conversao'] ?? '',
            $row['comp']['percentual'] ?? '',
            $row['comp']['observacao'] ?? ''
        ], ';', '"', '');
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $selected_tree_id = $_POST['arvore_id'] ?? ($_GET['arvore_id'] ?? '');

    if ($action === 'catalog_add' || $action === 'catalog_remove') {
        $catalog = ($_POST['catalog'] ?? '') === 'unidade' ? 'cadastro_unidades_base' : 'cadastro_tipos_item';
        $value = trim($action === 'catalog_add' ? ($_POST['catalog_new'] ?? '') : ($_POST['catalog_existing'] ?? ''));
        if ($value !== '') {
            if ($action === 'catalog_add') {
                $data[$catalog] = ae_unique_clean(array_merge($data[$catalog] ?? [], [$value]));
                ae_log($data, 'cadastro auxiliar criado', '', $selected_tree_id, '', $value);
            } else {
                $data[$catalog] = array_values(array_filter($data[$catalog] ?? [], fn($item) => strcasecmp((string)$item, $value) !== 0));
                ae_log($data, 'cadastro auxiliar removido', '', $selected_tree_id, '', $value);
            }
            ae_save($data);
        }
        ae_redirect(['arvore_id' => $selected_tree_id, 'selected_item_id' => $_POST['selected_item_id'] ?? '', 'msg' => 'catalogo']);
    }

    if ($action === 'save_root') {
        [$item, $error] = ae_upsert_item($data, 'root_');
        if ($error) {
            $message = $error;
            $message_type = 'error';
        } else {
            $tree_id = trim($_POST['tree_id'] ?? '');
            $tree_codigo = trim($_POST['tree_codigo'] ?? '');
            $tree_payload = [
                'codigo' => $tree_codigo !== '' ? $tree_codigo : ($item['codigo'] ?? ''),
                'nome' => $item['nome'] ?? '',
                'item_raiz_id' => $item['id'],
                'descricao' => trim($_POST['tree_descricao'] ?? ''),
                'ativo' => isset($_POST['tree_ativo']) ? 1 : 0,
                'atualizado_em' => ae_now()
            ];
            if ($tree_id && ae_find_tree($data, $tree_id)) {
                foreach ($data['tabela_arvores'] as $idx => $tree) {
                    if (($tree['id'] ?? '') === $tree_id) {
                        $data['tabela_arvores'][$idx] = array_merge($tree, $tree_payload);
                        break;
                    }
                }
                ae_log($data, 'arvore editada', $item['id'], $tree_id, '', 'Dados da arvore atualizados.');
            } else {
                $tree_id = ae_id('arvore');
                $data['tabela_arvores'][] = array_merge(['id' => $tree_id, 'criado_em' => ae_now()], $tree_payload);
                ae_log($data, 'arvore criada', $item['id'], $tree_id, '', 'Arvore criada com item raiz.');
            }
            ae_save($data);
            ae_redirect(['arvore_id' => $tree_id, 'selected_item_id' => $item['id'], 'msg' => 'arvore_salva']);
        }
    }

    if ($action === 'add_child') {
        $tree = ae_find_tree($data, $selected_tree_id);
        $parent_id = trim($_POST['parent_item_id'] ?? '');
        $child_id = trim($_POST['child_item_id'] ?? '');
        $parent_level = max(0, (int)ae_num($_POST['parent_level'] ?? ae_calc_level($data, $selected_tree_id, $parent_id)));
        if (!$tree || !$parent_id) {
            $message = 'Selecione uma arvore e um item pai.';
            $message_type = 'error';
        } else {
            $is_existing_child = $child_id !== '__new__';
            if ($child_id === '__new__') {
                [$child, $error] = ae_upsert_item($data, 'child_');
                if ($error) {
                    $message = $error;
                    $message_type = 'error';
                    $child = null;
                }
            } else {
                $child = ae_find_item($data, $child_id);
            }
            if ($message_type !== 'error') {
                if (!$child) {
                    $message = 'Item filho nao encontrado.';
                    $message_type = 'error';
                } elseif ($parent_id === $child['id'] || ae_has_path($data, $tree['id'], $child['id'], $parent_id)) {
                    $message = 'Vinculo bloqueado para evitar ciclo na arvore.';
                    $message_type = 'error';
                } elseif ($parent_level + 1 > AE_MAX_TREE_DEPTH) {
                    $message = 'Limite seguro de niveis atingido para evitar loop na arvore.';
                    $message_type = 'error';
                } else {
                    $comp = [
                        'id' => ae_id('comp'),
                        'arvore_id' => $tree['id'],
                        'item_pai_id' => $parent_id,
                        'item_filho_id' => $child['id'],
                        'quantidade' => max(0.000001, ae_num($_POST['quantidade'] ?? 1, 1)),
                        'unidade' => trim($_POST['unidade'] ?? ($child['unidade_base'] ?? '')),
                        'fator_conversao' => max(0.000001, ae_num($_POST['fator_conversao'] ?? 1, 1)),
                        'percentual' => ($_POST['percentual'] ?? '') === '' ? null : ae_num($_POST['percentual']),
                        'nivel' => $parent_level + 1,
                        'ordem_exibicao' => ae_next_order($data, $tree['id'], $parent_id),
                        'observacao' => trim($_POST['observacao'] ?? ''),
                        'ativo' => 1,
                        'criado_em' => ae_now(),
                        'atualizado_em' => ae_now()
                    ];
                    $data['tabela_arvore_composicao'][] = $comp;
                    ae_log($data, 'vinculo criado', $child['id'], $tree['id'], $comp['id'], 'Filho adicionado a arvore.');
                    $subtree_imported = 0;
                    if ($is_existing_child) {
                        $source_tree_id = ae_find_subtree_source_tree_id($data, $child['id'], $tree['id']);
                        if ($source_tree_id !== '') {
                            $reuse_batch_id = ae_id('reuso');
                            $comp['reuso_arvore_id'] = $source_tree_id;
                            $comp['reuso_lote_id'] = $reuse_batch_id;
                            $comp['reuso_modo'] = 'vinculado';
                            $data['tabela_arvore_composicao'][count($data['tabela_arvore_composicao']) - 1] = $comp;
                            $subtree_imported = ae_import_item_subtree($data, $source_tree_id, $tree['id'], $child['id'], $parent_level + 1, [], 0, $reuse_batch_id, 'vinculado');
                            if ($subtree_imported > 0) {
                                ae_log($data, 'subarvore importada', $child['id'], $tree['id'], $comp['id'], $subtree_imported . ' vinculos importados do item existente.');
                            }
                        }
                    }
                    ae_save($data);
                    ae_redirect(['arvore_id' => $tree['id'], 'selected_item_id' => $child['id'], 'selected_comp_id' => $comp['id'], 'msg' => 'filho_salvo', 'subtree' => $subtree_imported]);
                }
            }
        }
    }

    if ($action === 'edit_selected') {
        $item_id = trim($_POST['edit_item_id'] ?? '');
        $comp_id = trim($_POST['edit_comp_id'] ?? '');
        $idx = ae_find_item_index($data, $item_id);
        if ($idx < 0) {
            $message = 'Item selecionado nao encontrado.';
            $message_type = 'error';
        } else {
            $codigo = $data['tabela_itens'][$idx]['codigo'] ?? ae_item_code($data);
            if ($codigo === '') {
                $codigo = ae_item_code($data);
            }
            $old_unidade_base = $data['tabela_itens'][$idx]['unidade_base'] ?? '';
            $data['tabela_itens'][$idx]['codigo'] = $codigo;
                $data['tabela_itens'][$idx]['nome'] = trim($_POST['edit_nome'] ?? '');
                $data['tabela_itens'][$idx]['codigo_rm'] = trim($_POST['edit_codigo_rm'] ?? '');
                $data['tabela_itens'][$idx]['produto_rm'] = trim($_POST['edit_produto_rm'] ?? '');
                $edit_peso_tara = trim($_POST['edit_peso_tara'] ?? '');
                $data['tabela_itens'][$idx]['peso_tara'] = $edit_peso_tara === '' ? null : max(0, ae_num($edit_peso_tara));
                $data['tabela_itens'][$idx]['tipo_item'] = trim($_POST['edit_tipo_item'] ?? '');
                $data['tabela_itens'][$idx]['categoria'] = trim($_POST['edit_categoria'] ?? '');
                $data['tabela_itens'][$idx]['grupo'] = trim($_POST['edit_grupo'] ?? '');
                $data['tabela_itens'][$idx]['unidade_base'] = trim($_POST['edit_unidade_base'] ?? '');
                $data['tabela_itens'][$idx]['fator_conversao_padrao'] = ae_num($_POST['edit_fator_conversao_padrao'] ?? 1, 1);
                $data['tabela_itens'][$idx]['ativo'] = isset($_POST['edit_ativo']) ? 1 : 0;
                $data['tabela_itens'][$idx]['observacao'] = trim($_POST['edit_item_observacao'] ?? '');
                $data['tabela_itens'][$idx]['atualizado_em'] = ae_now();
                ae_log($data, 'item editado', $item_id, $selected_tree_id, $comp_id, 'Dados cadastrais do item alterados.');

                foreach ($data['tabela_arvores'] as $tree_idx => $tree_row) {
                    if (($tree_row['item_raiz_id'] ?? '') === $item_id) {
                        $data['tabela_arvores'][$tree_idx]['nome'] = $data['tabela_itens'][$idx]['nome'];
                        $data['tabela_arvores'][$tree_idx]['atualizado_em'] = ae_now();
                    }
                }

                $comp_idx = $comp_id ? ae_find_comp_index($data, $comp_id) : -1;
                if ($comp_idx < 0) {
                    foreach ($data['tabela_arvore_composicao'] as $candidate_idx => $candidate_comp) {
                        if (($candidate_comp['ativo'] ?? 1)
                            && ($candidate_comp['arvore_id'] ?? '') === $selected_tree_id
                            && ($candidate_comp['item_filho_id'] ?? '') === $item_id) {
                            $comp_idx = $candidate_idx;
                            $comp_id = $candidate_comp['id'] ?? $comp_id;
                            break;
                        }
                    }
                }
                if ($comp_idx >= 0) {
                    $old_comp_unidade = $data['tabela_arvore_composicao'][$comp_idx]['unidade'] ?? '';
                    $new_item_unidade = trim($_POST['edit_unidade_base'] ?? '');
                    $new_comp_unidade = trim($_POST['edit_unidade'] ?? '');
                    $posted_original_comp_unidade = trim($_POST['edit_unidade_original'] ?? $old_comp_unidade);
                    if ($new_comp_unidade === '' || ($new_comp_unidade === $posted_original_comp_unidade && $new_item_unidade !== '')) {
                        $new_comp_unidade = $new_item_unidade;
                    }
                    $data['tabela_arvore_composicao'][$comp_idx]['quantidade'] = max(0.000001, ae_num($_POST['edit_quantidade'] ?? 1, 1));
                    $data['tabela_arvore_composicao'][$comp_idx]['unidade'] = $new_comp_unidade;
                    $data['tabela_arvore_composicao'][$comp_idx]['fator_conversao'] = max(0.000001, ae_num($_POST['edit_fator_conversao'] ?? 1, 1));
                    $data['tabela_arvore_composicao'][$comp_idx]['percentual'] = ($_POST['edit_percentual'] ?? '') === '' ? null : ae_num($_POST['edit_percentual']);
                    $data['tabela_arvore_composicao'][$comp_idx]['observacao'] = trim($_POST['edit_comp_observacao'] ?? '');
                    $data['tabela_arvore_composicao'][$comp_idx]['atualizado_em'] = ae_now();
                    if ($new_comp_unidade !== '') {
                        $data['cadastro_unidades_base'] = ae_unique_clean(array_merge($data['cadastro_unidades_base'] ?? [], [$new_comp_unidade]));
                    }
                    ae_log($data, 'composicao alterada', $item_id, $selected_tree_id, $comp_id, 'Dados do vinculo alterados.');
                }
                ae_save($data);
                ae_redirect(['arvore_id' => $selected_tree_id, 'selected_item_id' => $item_id, 'selected_comp_id' => $comp_id, 'msg' => 'editado']);
        }
    }

    if ($action === 'duplicate_tree') {
        $source_tree = ae_find_tree($data, $selected_tree_id);
        $source_root_id = $source_tree['item_raiz_id'] ?? '';
        if ($source_tree && $source_root_id !== '') {
            $item_map = [];
            ae_clone_tree_item($data, $source_tree['id'], $source_root_id, $item_map);
            $new_root_id = $item_map[$source_root_id] ?? '';
            if ($new_root_id !== '') {
                $tree_id = ae_id('arvore');
                $data['tabela_arvores'][] = [
                    'id' => $tree_id,
                    'codigo' => ae_unique_tree_code($data, $source_tree['codigo'] ?? ''),
                    'nome' => trim((string)($source_tree['nome'] ?? '')) . ' - Copia',
                    'item_raiz_id' => $new_root_id,
                    'descricao' => trim((string)($source_tree['descricao'] ?? '')),
                    'ativo' => 1,
                    'criado_em' => ae_now(),
                    'atualizado_em' => ae_now()
                ];
                $copied = ae_clone_tree_links($data, $source_tree['id'], $tree_id, $source_root_id, 0, $item_map);
                ae_log($data, 'arvore duplicada para edicao', $new_root_id, $tree_id, '', 'Nova arvore criada a partir de ' . ($source_tree['codigo'] ?? '') . ' com ' . $copied . ' vinculos copiados.');
                ae_save($data);
                ae_redirect(['arvore_id' => $tree_id, 'selected_item_id' => $new_root_id, 'msg' => 'arvore_duplicada', 'total' => $copied]);
            }
        }
        ae_redirect(['arvore_id' => $selected_tree_id, 'msg' => 'erro_arvore_duplicada']);
    }

    if ($action === 'make_independent_tree') {
        $item_id = trim($_POST['independent_item_id'] ?? '');
        $source_tree = ae_find_tree($data, $selected_tree_id);
        $item = ae_find_item($data, $item_id);
        if ($source_tree && $item) {
            $existing_tree_id = ae_find_subtree_source_tree_id($data, $item_id, $selected_tree_id);
            if ($existing_tree_id !== '') {
                ae_redirect(['arvore_id' => $existing_tree_id, 'selected_item_id' => $item_id, 'msg' => 'arvore_existente']);
            }

            $tree_id = ae_id('arvore');
            $data['tabela_arvores'][] = [
                'id' => $tree_id,
                'codigo' => $item['codigo'] ?? '',
                'nome' => $item['nome'] ?? '',
                'item_raiz_id' => $item_id,
                'descricao' => 'Arvore independente criada a partir de uma ramificacao reaproveitada.',
                'ativo' => 1,
                'criado_em' => ae_now(),
                'atualizado_em' => ae_now()
            ];
            $copied = ae_import_item_subtree($data, $selected_tree_id, $tree_id, $item_id, 0);
            ae_log($data, 'arvore independente criada', $item_id, $tree_id, '', $copied . ' vinculos copiados da arvore de origem.');
            ae_save($data);
            ae_redirect(['arvore_id' => $tree_id, 'selected_item_id' => $item_id, 'msg' => 'arvore_independente', 'total' => $copied]);
        }
        ae_redirect(['arvore_id' => $selected_tree_id, 'msg' => 'erro_arvore_independente']);
    }

    if ($action === 'detach_reuse') {
        $item_id = trim($_POST['detach_item_id'] ?? '');
        $independent_id = ae_id('independente');
        $detached = ae_detach_reuse_branch($data, $selected_tree_id, $item_id, $independent_id);
        $comp_id = trim($_POST['detach_comp_id'] ?? '');
        $comp_idx = $comp_id ? ae_find_comp_index($data, $comp_id) : -1;
        if ($comp_idx >= 0) {
            $data['tabela_arvore_composicao'][$comp_idx]['reuso_modo'] = 'independente';
            $data['tabela_arvore_composicao'][$comp_idx]['id_verificador_independente'] = $independent_id;
            $data['tabela_arvore_composicao'][$comp_idx]['reuso_arvore_id'] = '';
            $data['tabela_arvore_composicao'][$comp_idx]['atualizado_em'] = ae_now();
            $detached++;
        }
        ae_log($data, 'reuso desassociado', $item_id, $selected_tree_id, $comp_id, 'Ramificacao independente: ' . $independent_id . ' (' . $detached . ' vinculos).');
        ae_save($data);
        ae_redirect(['arvore_id' => $selected_tree_id, 'selected_item_id' => $item_id, 'selected_comp_id' => $comp_id, 'msg' => 'reuso_desassociado']);
    }

    if ($action === 'remove_link') {
        $comp_id = trim($_POST['remove_comp_id'] ?? '');
        $comp_idx = ae_find_comp_index($data, $comp_id);
        if ($comp_idx >= 0) {
            $removed_child_id = $data['tabela_arvore_composicao'][$comp_idx]['item_filho_id'] ?? '';
            $data['tabela_arvore_composicao'][$comp_idx]['ativo'] = 0;
            $data['tabela_arvore_composicao'][$comp_idx]['atualizado_em'] = ae_now();
            $branch_removed = ae_deactivate_item_branch($data, $selected_tree_id, $removed_child_id);
            ae_log($data, 'item desvinculado da arvore', $removed_child_id, $selected_tree_id, $comp_id, 'Vinculo removido com ' . $branch_removed . ' descendentes. Cadastro dos itens mantido.');
            ae_save($data);
            if (($_POST['ajax'] ?? '') === '1') {
                ae_json_response(['status' => 'success', 'comp_id' => $comp_id, 'branch_removed' => $branch_removed]);
            }
        } elseif (($_POST['ajax'] ?? '') === '1') {
            ae_json_response(['status' => 'error', 'message' => 'Vinculo nao encontrado.']);
        }
        ae_redirect(['arvore_id' => $selected_tree_id, 'msg' => 'removido']);
    }

    if ($action === 'delete_link') {
        $comp_id = trim($_POST['delete_comp_id'] ?? '');
        $comp_idx = ae_find_comp_index($data, $comp_id);
        if ($comp_idx >= 0) {
            $deleted_child_id = $data['tabela_arvore_composicao'][$comp_idx]['item_filho_id'] ?? '';
            $branch_items = [];
            ae_collect_branch_item_ids($branch_items, $data, $selected_tree_id, $deleted_child_id);

            $data['tabela_arvore_composicao'][$comp_idx]['ativo'] = 0;
            $data['tabela_arvore_composicao'][$comp_idx]['atualizado_em'] = ae_now();
            $branch_removed = ae_deactivate_item_branch($data, $selected_tree_id, $deleted_child_id);
            $items_removed = ae_deactivate_unused_items($data, $branch_items);
            ae_log($data, 'ramificacao excluida localmente', $deleted_child_id, $selected_tree_id, $comp_id, 'Removidos ' . ($branch_removed + 1) . ' vinculos locais e ' . $items_removed . ' itens sem uso.');
            ae_save($data);
            if (($_POST['ajax'] ?? '') === '1') {
                ae_json_response(['status' => 'success', 'comp_id' => $comp_id, 'branch_removed' => $branch_removed, 'items_removed' => $items_removed]);
            }
        } elseif (($_POST['ajax'] ?? '') === '1') {
            ae_json_response(['status' => 'error', 'message' => 'Vinculo nao encontrado.']);
        }
        ae_redirect(['arvore_id' => $selected_tree_id, 'msg' => 'excluido']);
    }

    if ($action === 'save_params') {
        $item_id = trim($_POST['param_item_id'] ?? '');
        $payload = [
            'item_id' => $item_id,
            'unidade_compra' => trim($_POST['unidade_compra'] ?? ''),
            'unidade_producao' => trim($_POST['unidade_producao'] ?? ''),
            'lead_time_compra' => ae_num($_POST['lead_time_compra'] ?? 0),
            'lead_time_producao' => ae_num($_POST['lead_time_producao'] ?? 0),
            'lote_compra' => trim($_POST['lote_compra'] ?? ''),
            'lote_producao' => trim($_POST['lote_producao'] ?? ''),
            'estoque_seguranca' => ae_num($_POST['estoque_seguranca'] ?? 0),
            'tipo_planejamento' => trim($_POST['tipo_planejamento'] ?? ''),
            'observacao' => trim($_POST['param_observacao'] ?? '')
        ];
        $idx = ae_param_index($data, $item_id);
        if ($idx >= 0) {
            $data['tabela_item_parametros'][$idx] = array_merge($data['tabela_item_parametros'][$idx], $payload);
        } else {
            $data['tabela_item_parametros'][] = array_merge(['id' => ae_id('param')], $payload);
        }
        ae_log($data, 'parametros alterados', $item_id, $selected_tree_id, '', 'Parametros do item atualizados.');
        ae_save($data);
        ae_redirect(['arvore_id' => $selected_tree_id, 'selected_item_id' => $item_id, 'tab' => 'parametros', 'msg' => 'parametros']);
    }

    if ($action === 'add_conversion') {
        $item_id = trim($_POST['conv_item_id'] ?? '');
        $data['tabela_item_conversoes'][] = [
            'id' => ae_id('conv'),
            'item_id' => $item_id,
            'unidade_origem' => trim($_POST['unidade_origem'] ?? ''),
            'unidade_destino' => trim($_POST['unidade_destino'] ?? ''),
            'fator' => max(0.000001, ae_num($_POST['fator'] ?? 1, 1)),
            'descricao' => trim($_POST['descricao'] ?? ''),
            'ativo' => 1
        ];
        ae_log($data, 'conversao criada', $item_id, $selected_tree_id, '', 'Conversao do item cadastrada.');
        ae_save($data);
        ae_redirect(['arvore_id' => $selected_tree_id, 'selected_item_id' => $item_id, 'tab' => 'conversoes', 'msg' => 'conversao']);
    }

    if ($action === 'save_observation') {
        $item_id = trim($_POST['obs_item_id'] ?? '');
        $idx = ae_find_item_index($data, $item_id);
        if ($idx >= 0) {
            $data['tabela_itens'][$idx]['observacao'] = trim($_POST['observacao_livre'] ?? '');
            $data['tabela_itens'][$idx]['atualizado_em'] = ae_now();
            ae_log($data, 'observacao alterada', $item_id, $selected_tree_id, '', 'Observacoes do item atualizadas.');
            ae_save($data);
        }
        ae_redirect(['arvore_id' => $selected_tree_id, 'selected_item_id' => $item_id, 'tab' => 'observacoes', 'msg' => 'observacao']);
    }

    if ($action === 'archive_tree' || $action === 'reactivate_tree') {
        $tree_id = trim($_POST['arvore_id'] ?? '');
        $tree_idx = -1;
        foreach ($data['tabela_arvores'] as $idx => $row) {
            if (($row['id'] ?? '') === $tree_id) {
                $tree_idx = $idx;
                break;
            }
        }
        if ($tree_idx >= 0) {
            $active = $action === 'reactivate_tree' ? 1 : 0;
            $data['tabela_arvores'][$tree_idx]['ativo'] = $active;
            $data['tabela_arvores'][$tree_idx]['atualizado_em'] = ae_now();
            ae_log($data, $active ? 'arvore reativada' : 'arvore arquivada', $data['tabela_arvores'][$tree_idx]['item_raiz_id'] ?? '', $tree_id, '', $active ? 'Arvore voltou para uso.' : 'Arvore arquivada sem apagar estrutura.');
            ae_save($data);
            ae_redirect(['arvore_id' => $tree_id, 'msg' => $active ? 'arvore_reativada' : 'arvore_arquivada']);
        }
        ae_redirect(['arvore_id' => $selected_tree_id, 'msg' => 'erro_arvore_arquivo']);
    }

    if ($action === 'restore_backup_inactive') {
        $result = ae_restore_backup_inactive($data);
        if (($result['total_restored'] ?? 0) > 0) {
            ae_log(
                $data,
                'inativos reativados por backup',
                '',
                $selected_tree_id,
                '',
                'Reativados ' . (int)$result['total_restored'] . ' registros ainda existentes no JSON ativo. Registros presentes apenas nos backups foram ignorados.'
            );
            ae_save($data);
        }
        ae_redirect([
            'arvore_id' => $selected_tree_id,
            'msg' => 'backup_inativos_reativados',
            'total' => (int)($result['total_restored'] ?? 0),
            'itens' => (int)($result['restored']['tabela_itens'] ?? 0),
            'arvores' => (int)($result['restored']['tabela_arvores'] ?? 0),
            'vinculos' => (int)($result['restored']['tabela_arvore_composicao'] ?? 0),
            'ignorados' => (int)($result['backup_only_ignored'] ?? 0),
            'excluidos' => (int)($result['deleted_ignored'] ?? 0)
        ]);
    }

    if ($action === 'import_csv') {
        $tree = ae_find_tree($data, $selected_tree_id);
        $imported = 0;
        if ($tree && isset($_FILES['arquivo_csv']) && is_uploaded_file($_FILES['arquivo_csv']['tmp_name'])) {
            $handle = fopen($_FILES['arquivo_csv']['tmp_name'], 'r');
            $header = $handle ? fgetcsv($handle, 0, ';') : false;
            if ($header && count($header) === 1) {
                rewind($handle);
                $header = fgetcsv($handle, 0, ',');
                $delimiter = ',';
            } else {
                $delimiter = ';';
            }
            $map = [];
            foreach (($header ?: []) as $idx => $name) {
                $map[strtolower(trim($name))] = $idx;
            }
            while ($handle && (($row = fgetcsv($handle, 0, $delimiter)) !== false)) {
                $codigo_pai = trim(ae_csv_value($row, $map, 'codigo_pai'));
                $codigo_filho = trim(ae_csv_value($row, $map, 'codigo_filho'));
                if ($codigo_pai === '' || $codigo_filho === '') {
                    continue;
                }
                $parent = ae_find_item_by_code($data, $codigo_pai);
                $child = ae_find_item_by_code($data, $codigo_filho);
                if (!$parent || !$child || $parent['id'] === $child['id'] || ae_has_path($data, $tree['id'], $child['id'], $parent['id'])) {
                    continue;
                }
                $data['tabela_arvore_composicao'][] = [
                    'id' => ae_id('comp'),
                    'arvore_id' => $tree['id'],
                    'item_pai_id' => $parent['id'],
                    'item_filho_id' => $child['id'],
                    'quantidade' => ae_num(ae_csv_value($row, $map, 'quantidade', 1), 1),
                    'unidade' => trim(ae_csv_value($row, $map, 'unidade', $child['unidade_base'] ?? '')),
                    'fator_conversao' => ae_num(ae_csv_value($row, $map, 'fator_conversao', 1), 1),
                    'percentual' => null,
                    'nivel' => ae_calc_level($data, $tree['id'], $parent['id']) + 1,
                    'ordem_exibicao' => ae_next_order($data, $tree['id'], $parent['id']),
                    'observacao' => trim(ae_csv_value($row, $map, 'observacao')),
                    'ativo' => 1,
                    'criado_em' => ae_now(),
                    'atualizado_em' => ae_now()
                ];
                $imported++;
            }
            if ($handle) {
                fclose($handle);
            }
            ae_log($data, 'importacao csv', '', $tree['id'], '', $imported . ' vinculos importados.');
            ae_save($data);
        }
        ae_redirect(['arvore_id' => $selected_tree_id, 'msg' => 'importado', 'total' => $imported]);
    }
}

$messages = [
    'arvore_salva' => 'Arvore e item raiz salvos.',
    'filho_salvo' => 'Filho adicionado a estrutura.',
    'editado' => 'Dados atualizados.',
    'removido' => 'Ramificacao desvinculada da arvore. O cadastro dos itens foi mantido.',
    'excluido' => 'Ramificacao excluida localmente. Outras arvores foram preservadas.',
    'parametros' => 'Parametros salvos.',
    'conversao' => 'Conversao cadastrada.',
    'observacao' => 'Observacoes salvas.',
    'importado' => 'Importacao concluida.',
    'catalogo' => 'Lista auxiliar atualizada.',
    'arvore_existente' => 'Este item ja possui arvore independente. Ela foi aberta para edicao.',
    'arvore_independente' => 'Arvore independente criada.',
    'erro_arvore_independente' => 'Nao foi possivel criar a arvore independente.',
    'arvore_duplicada' => 'Arvore criada como nova copia para edicao.',
    'erro_arvore_duplicada' => 'Nao foi possivel criar a copia da arvore.',
    'arvore_arquivada' => 'Arvore arquivada. A estrutura foi preservada.',
    'arvore_reativada' => 'Arvore reativada.',
    'erro_arvore_arquivo' => 'Nao foi possivel alterar o status da arvore.',
    'reuso_desassociado' => 'Reuso desassociado. Esta ramificacao agora tem identificador independente.',
    'backup_inativos_reativados' => 'Reativacao segura concluida.'
];
if (isset($_GET['msg'])) {
    $message = $messages[$_GET['msg']] ?? '';
    if ($_GET['msg'] === 'importado') {
        $message .= ' Vinculos lidos: ' . (int)($_GET['total'] ?? 0) . '.';
    } elseif ($_GET['msg'] === 'filho_salvo' && (int)($_GET['subtree'] ?? 0) > 0) {
        $message .= ' Subarvore importada: ' . (int)$_GET['subtree'] . ' vinculos.';
    } elseif ($_GET['msg'] === 'arvore_independente') {
        $message .= ' Vinculos copiados: ' . (int)($_GET['total'] ?? 0) . '.';
    } elseif ($_GET['msg'] === 'arvore_duplicada') {
        $message .= ' Vinculos copiados: ' . (int)($_GET['total'] ?? 0) . '.';
    } elseif ($_GET['msg'] === 'backup_inativos_reativados') {
        $message .= ' Total: ' . (int)($_GET['total'] ?? 0)
            . ' | Itens: ' . (int)($_GET['itens'] ?? 0)
            . ' | Arvores: ' . (int)($_GET['arvores'] ?? 0)
            . ' | Vinculos: ' . (int)($_GET['vinculos'] ?? 0)
            . ' | Somente em backup ignorados: ' . (int)($_GET['ignorados'] ?? 0)
            . ' | Excluidos ignorados: ' . (int)($_GET['excluidos'] ?? 0) . '.';
    }
}

$trees = $data['tabela_arvores'];
usort($trees, fn($a, $b) => strcmp((string)($a['codigo'] ?? ''), (string)($b['codigo'] ?? '')));
$creating_new_tree = ($_GET['novo'] ?? '') === '1';
$active_trees = array_values(array_filter($trees, fn($tree) => $tree['ativo'] ?? 1));
$selected_tree_id = $creating_new_tree ? '' : ($_GET['arvore_id'] ?? ($active_trees[0]['id'] ?? ($trees[0]['id'] ?? '')));
$tree = ae_find_tree($data, $selected_tree_id);
$tree_is_active = !$tree || ($tree['ativo'] ?? 1);
$root_item = $tree ? ae_find_item($data, $tree['item_raiz_id'] ?? '') : null;
$root_form_item = $creating_new_tree ? null : $root_item;
$items = $data['tabela_itens'];
usort($items, fn($a, $b) => strcmp((string)($a['codigo'] ?? ''), (string)($b['codigo'] ?? '')));
$tree_rows = $tree ? ae_tree_flat($data, $tree) : [];
$missing_tara_groups = ae_missing_tara_groups($tree_rows);
$tree_has_missing_tara = !empty($missing_tara_groups);
$selected_item_id = $_GET['selected_item_id'] ?? ($tree['item_raiz_id'] ?? '');
$selected_comp_id = $_GET['selected_comp_id'] ?? '';
$selected_item = ae_find_item($data, $selected_item_id);
$selected_comp = $selected_comp_id ? ae_find_comp($data, $selected_comp_id) : ae_parent_comp($data, $selected_tree_id, $selected_item_id);
$selected_level = $selected_item ? ae_calc_level($data, $selected_tree_id, $selected_item_id) : 0;
$selected_param = $selected_item ? ae_param($data, $selected_item_id) : ae_param($data, '');
$selected_conversions = $selected_item ? ae_conversions($data, $selected_item_id) : [];
$selected_independent_tree_id = $selected_item ? ae_find_subtree_source_tree_id($data, $selected_item_id, $selected_tree_id) : '';
$selected_root_tree_id = $selected_item ? ae_find_item_root_tree_id($data, $selected_item_id, $selected_tree_id) : '';
$selected_shared_usages = $selected_root_tree_id ? ae_tree_reuse_usages($data, $selected_root_tree_id, $selected_tree_id) : [];
$selected_is_reuse = $selected_comp && (($selected_comp['reuso_arvore_id'] ?? '') !== '' || ($selected_root_tree_id !== '' && ($selected_comp['item_filho_id'] ?? '') === $selected_item_id));
$selected_leaf_totals = ($tree && $selected_item) ? ae_selected_leaf_totals($data, $selected_tree_id, $selected_item_id) : [];
$active_tab = $_GET['tab'] ?? 'geral';
$tipos_item = ae_unique_clean($data['cadastro_tipos_item'] ?? []);
$unidades_base = ae_unique_clean($data['cadastro_unidades_base'] ?? []);
$status_summary = ae_status_summary($data);
$backup_recovery_preview = ae_backup_recovery_preview($data);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arvore de Estrutura</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { background: #f4f7fb; color: #0f1f3a; }
        body.ae-sidebar-collapsed .sidebar { transform: translateX(calc(-1 * var(--sidebar-width))); }
        body.ae-sidebar-collapsed .content { margin-left: 0; width: 100%; }
        .sidebar { transition: transform .22s ease; }
        .content { padding: 18px 22px 28px; transition: margin-left .22s ease, width .22s ease; }
        .ae-topbar { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; min-height: 42px; }
        .ae-menu-icon { position: fixed; top: 14px; left: calc(var(--sidebar-width) + 18px); z-index: 1600; width: 44px; height: 44px; border: 1px solid #d8e1ef; border-radius: 8px; display: grid; place-items: center; color: #0068ff; background: #fff; font-weight: 800; box-shadow: 0 8px 20px rgba(15,31,58,.12); cursor: pointer; transition: left .22s ease, transform .16s ease, box-shadow .16s ease; }
        .ae-menu-icon:hover { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(15,31,58,.16); }
        body.ae-sidebar-collapsed .ae-menu-icon { left: 18px; }
        .ae-crumb { color: #0068ff; font-size: 20px; font-weight: 750; letter-spacing: 0; }
        .ae-crumb span { color: #8a97ac; font-weight: 500; margin: 0 10px; }
        .ae-card { background: #fff; border: 1px solid #dce5f1; border-radius: 8px; box-shadow: 0 10px 28px rgba(15,31,58,.045); }
        .ae-card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px 8px; }
        .ae-card-title { margin: 0; font-size: 16px; color: #0f1f3a; font-weight: 750; letter-spacing: 0; }
        .ae-card-body { padding: 16px 20px 20px; }
        .ae-root-card .ae-card-head { padding-bottom: 18px; }
        .ae-root-card .ae-card-body { display: block; }
        body.ae-root-collapsed .ae-root-card .ae-card-body { display: none; }
        .ae-grid { display: grid; grid-template-columns: repeat(5, minmax(150px, 1fr)); gap: 14px 18px; }
        .ae-grid-4 { grid-template-columns: repeat(4, minmax(150px, 1fr)); }
        .ae-field label { display: block; margin-bottom: 6px; font-size: 13px; color: #17264a; font-weight: 600; }
        .ae-field .req::after { content: " *"; color: #e11d48; }
        .ae-field input, .ae-field select, .ae-field textarea { height: 42px; border: 1px solid #cfd8e6; border-radius: 6px; color: #122243; background: #fff; font-size: 14px; transition: border-color .18s ease, box-shadow .18s ease, background .18s ease; }
        .ae-field input:hover, .ae-field select:hover, .ae-field textarea:hover { border-color: #b8c6d9; background: #fbfdff; }
        .ae-field input:focus, .ae-field select:focus, .ae-field textarea:focus { border-color: #006eff; box-shadow: 0 0 0 3px rgba(0,110,255,.12); background: #fff; }
        .ae-code-preview { height: 42px; display: flex; align-items: center; padding: 0 12px; border: 1px dashed #b8c6d9; border-radius: 6px; background: #f8fbff; color: #52627a; font-weight: 700; }
        .ae-field textarea { min-height: 78px; height: auto; }
        .ae-toggle { display: flex; align-items: center; height: 42px; gap: 8px; }
        .ae-toggle input { width: 20px; height: 20px; }
        .ae-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .ae-btn { display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 14px; border-radius: 6px; border: 1px solid #cfd8e6; background: #fff; color: #11254a; text-decoration: none; font-size: 13px; font-weight: 600; cursor: pointer; box-shadow: 0 1px 3px rgba(15,31,58,.04); transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease, background .16s ease; }
        .ae-btn:hover { transform: translateY(-1px); border-color: #b9c8dc; box-shadow: 0 8px 18px rgba(15,31,58,.08); }
        .ae-btn.primary { background: linear-gradient(180deg, #0b7cff 0%, #0068ef 100%); color: #fff; border-color: #006eff; box-shadow: 0 8px 18px rgba(0,110,255,.18); }
        .ae-btn.danger { color: #e11d48; border-color: #ffd2dc; background: #fff7f9; }
        .ae-btn:disabled { opacity: .45; cursor: not-allowed; }
        .ae-btn:disabled:hover { transform: none; box-shadow: none; }
        .ae-main { display: grid; grid-template-columns: minmax(680px, 1fr) 430px; gap: 16px; margin-top: 16px; align-items: stretch; }
        body.ae-details-collapsed .ae-main { grid-template-columns: minmax(680px, 1fr) 54px; }
        .ae-toolbar { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 15px 18px; border-bottom: 1px solid #e5ebf4; background: linear-gradient(180deg, #fff 0%, #fbfdff 100%); border-radius: 8px 8px 0 0; }
        .ae-table-wrap { overflow-y: auto; overflow-x: hidden; min-height: 390px; max-height: 58vh; scrollbar-gutter: stable; }
        .ae-table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .ae-table th { position: sticky; top: 0; z-index: 2; color: #52627a; background: #fbfdff; border-bottom: 1px solid #e5ebf4; padding: 12px 10px; white-space: nowrap; font-weight: 750; font-size: 12px; text-transform: uppercase; letter-spacing: .02em; }
        .ae-table td { border-bottom: 1px solid #e8eef6; padding: 11px 10px; white-space: nowrap; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; }
        .ae-table th:nth-child(1), .ae-table td:nth-child(1) { width: 28%; }
        .ae-table th:nth-child(2), .ae-table td:nth-child(2) { width: 10%; }
        .ae-table th:nth-child(3), .ae-table td:nth-child(3) { width: 22%; }
        .ae-table th:nth-child(4), .ae-table td:nth-child(4) { width: 9%; }
        .ae-table th:nth-child(5), .ae-table td:nth-child(5) { width: 10%; }
        .ae-table th:nth-child(6), .ae-table td:nth-child(6) { width: 10%; }
        .ae-table th:nth-child(7), .ae-table td:nth-child(7) { width: 6%; }
        .ae-table th:nth-child(8), .ae-table td:nth-child(8) { width: 5%; }
        .ae-table tr.selected { background: #dceeff; box-shadow: inset 3px 0 0 #006eff; }
        .ae-table tr:hover { background: #eef6ff; }
        .ae-table tr.tara-missing { background: #fff7d6; }
        .ae-table tr.tara-missing.selected { background: #ffeeb3; box-shadow: inset 3px 0 0 #f59e0b; }
        .ae-tara-alert { margin: 0 18px 12px; padding: 10px 12px; border: 1px solid #fde68a; background: #fffbeb; color: #92400e; border-radius: 7px; font-size: 13px; font-weight: 700; }
        .ae-tree-cell { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .ae-indent { display: inline-block; width: calc(var(--level) * 24px); min-width: calc(var(--level) * 24px); border-left: 1px dotted #a9b7c8; height: 24px; }
        .ae-expander { width: 22px; height: 22px; border: 1px solid transparent; border-radius: 5px; background: transparent; color: #17335f; cursor: pointer; }
        .ae-expander:hover { background: #e8f2ff; border-color: #c9ddf7; }
        .ae-type-icon { width: 19px; height: 19px; border: 1px solid #0aa36f; border-radius: 5px; color: #0aa36f; display: inline-grid; place-items: center; font-size: 11px; background: #f0fdf4; }
        .ae-type-icon.insumo { border-color: #f59e0b; color: #f59e0b; }
        .ae-type-icon.produto { border-color: #006eff; color: #006eff; background: #eff6ff; }
        .ae-empty { min-height: 280px; display: grid; place-items: center; text-align: center; color: #52627a; }
        .ae-side-card { min-height: 620px; }
        .ae-side-card { overflow: hidden; transition: width .2s ease, min-height .2s ease; }
        body.ae-details-collapsed .ae-side-card { min-height: 620px; }
        body.ae-details-collapsed .ae-side-card .ae-card-body,
        body.ae-details-collapsed .ae-side-card .ae-card-title { display: none; }
        body.ae-details-collapsed .ae-side-card .ae-card-head { height: 100%; padding: 12px 8px; justify-content: center; align-items: flex-start; }
        .ae-collapse-btn { width: 34px; height: 34px; border: 1px solid #cfd8e6; border-radius: 7px; background: #fff; color: #17335f; cursor: pointer; display: inline-grid; place-items: center; font-weight: 800; }
        .ae-collapse-btn:hover { background: #eef6ff; border-color: #bcd3f2; }
        body.ae-details-collapsed .ae-detail-toggle { writing-mode: vertical-rl; width: 38px; height: auto; min-height: 180px; padding: 10px 0; }
        body.ae-details-collapsed .ae-detail-toggle::after { content: "Detalhes"; font-size: 12px; font-weight: 700; color: #17335f; margin-top: 8px; }
        .ae-item-summary { display: grid; grid-template-columns: 44px 1fr; gap: 12px; border: 1px solid #e2e8f2; border-radius: 8px; padding: 16px; background: linear-gradient(180deg, #fff 0%, #f8fbff 100%); }
        .ae-cube { width: 38px; height: 38px; border-radius: 9px; background: linear-gradient(145deg, #0b7cff, #005bd4); color: #fff; display: grid; place-items: center; font-weight: 800; box-shadow: 0 10px 18px rgba(0,110,255,.22); }
        .ae-tabs { display: flex; gap: 18px; border-bottom: 1px solid #e5ebf4; margin-top: 16px; }
        .ae-tab { padding: 12px 0; color: #52627a; text-decoration: none; font-size: 13px; border-bottom: 2px solid transparent; font-weight: 600; }
        .ae-tab.active { color: #006eff; border-bottom-color: #006eff; font-weight: 700; }
        .ae-tab-panel { padding-top: 16px; }
        .ae-detail-row { display: grid; grid-template-columns: 150px 1fr; gap: 12px; margin-bottom: 11px; font-size: 13px; }
        .ae-detail-row span:first-child { color: #465873; }
        .ae-pill { display: inline-flex; padding: 4px 10px; border-radius: 999px; font-size: 12px; background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .ae-pill.warn { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
        .ae-missing-list { border: 1px solid #fde68a; background: #fffbeb; border-radius: 7px; padding: 0; margin-top: 12px; color: #78350f; overflow: hidden; }
        .ae-missing-list summary { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; font-size: 13px; font-weight: 750; cursor: pointer; list-style: none; }
        .ae-missing-list summary::-webkit-details-marker { display: none; }
        .ae-missing-list summary::after { content: ">"; font-size: 14px; color: #92400e; transition: transform .16s ease; }
        .ae-missing-list[open] summary::after { transform: rotate(90deg); }
        .ae-missing-content { padding: 0 12px 10px; }
        .ae-missing-content strong { display: block; margin: 9px 0 6px; }
        .ae-missing-list ul { margin: 0 0 10px 18px; padding: 0; }
        .ae-leaf-totals { margin-top: 16px; border: 1px solid #dbe5f2; border-radius: 8px; overflow: hidden; background: #fff; }
        .ae-leaf-totals h4 { margin: 0; padding: 11px 12px; font-size: 13px; color: #17264a; background: #f8fbff; border-bottom: 1px solid #e6edf7; }
        .ae-leaf-totals table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .ae-leaf-totals th, .ae-leaf-totals td { padding: 9px 10px; border-bottom: 1px solid #edf2f7; text-align: left; vertical-align: top; }
        .ae-leaf-totals th { color: #52627a; font-weight: 750; background: #fbfdff; }
        .ae-leaf-totals tr:last-child td { border-bottom: 0; }
        .ae-leaf-totals .qty { text-align: right; font-weight: 750; color: #0f1f3a; white-space: nowrap; }
        .ae-shared-alert { margin-top: 14px; border: 1px solid #bfdbfe; background: #eff6ff; color: #17335f; border-radius: 8px; padding: 11px 12px; font-size: 13px; }
        details.ae-shared-alert { margin: 0 18px 12px; padding: 0; overflow: hidden; }
        details.ae-shared-alert summary { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; font-weight: 750; cursor: pointer; list-style: none; }
        details.ae-shared-alert summary::-webkit-details-marker { display: none; }
        details.ae-shared-alert summary::after { content: ">"; font-size: 14px; color: #17335f; transition: transform .16s ease; }
        details.ae-shared-alert[open] summary::after { transform: rotate(90deg); }
        .ae-shared-content { padding: 0 12px 12px; }
        .ae-shared-alert strong { display: block; margin-bottom: 6px; }
        .ae-shared-alert ul { margin: 7px 0 0 18px; padding: 0; }
        .ae-shared-alert .ae-actions { margin-top: 10px; }
        .ae-status { margin-bottom: 14px; padding: 12px 14px; border: 1px solid #bbf7d0; background: #f0fdf4; color: #166534; border-radius: 7px; box-shadow: 0 4px 12px rgba(22,101,52,.06); }
        .ae-status.error { border-color: #fecdd3; background: #fff1f2; color: #be123c; }
        .ae-recovery-card { margin-bottom: 14px; }
        .ae-recovery-card .ae-card-head { padding-bottom: 14px; }
        .ae-recovery-grid { display: grid; grid-template-columns: repeat(3, minmax(160px, 1fr)); gap: 10px; }
        .ae-recovery-stat { border: 1px solid #e1e8f2; border-radius: 7px; padding: 10px 12px; background: #fbfdff; }
        .ae-recovery-stat strong { display: block; color: #10213f; font-size: 13px; margin-bottom: 7px; }
        .ae-recovery-stat span { display: inline-flex; margin-right: 8px; color: #475569; font-size: 12px; }
        .ae-recovery-note { color: #64748b; font-size: 12px; line-height: 1.45; }
        .ae-recovery-note b { color: #0f1f3a; }
        .ae-modal { display: none; position: fixed; inset: 0; z-index: 2000; background: rgba(15, 23, 42, .52); place-items: center; padding: 24px; }
        .ae-modal.show { display: grid; }
        .ae-modal-box { width: min(760px, 96vw); max-height: 92vh; overflow: auto; background: #fff; border-radius: 8px; box-shadow: 0 24px 70px rgba(0,0,0,.28); border: 1px solid rgba(255,255,255,.45); }
        .ae-modal-head { display: flex; justify-content: space-between; align-items: center; padding: 16px 18px; border-bottom: 1px solid #e5ebf4; background: #fbfdff; border-radius: 8px 8px 0 0; }
        .ae-modal-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 18px; border-top: 1px solid #e5ebf4; }
        .ae-close { border: 0; background: transparent; font-size: 22px; cursor: pointer; color: #64748b; }
        .ae-hidden { display: none !important; }
        .ae-legend { display: flex; gap: 10px; padding: 14px 18px; border-top: 1px solid #e5ebf4; background: #fbfdff; border-radius: 0 0 8px 8px; }
        .ae-legend span { border: 1px solid #d8e1ef; border-radius: 6px; padding: 5px 12px; font-size: 12px; font-weight: 600; background: #fff; }
        .ae-catalog { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 14px; padding: 12px; border: 1px solid #e5ebf4; border-radius: 8px; background: #fbfdff; }
        .ae-catalog form { display: grid; grid-template-columns: 110px 1fr auto auto; gap: 8px; align-items: end; }
        .ae-catalog .ae-field input, .ae-catalog .ae-field select { height: 36px; }
        .ae-catalog .ae-btn { height: 36px; padding: 0 11px; }
        .ae-inline-list { display: grid; grid-template-columns: 1fr auto auto; gap: 6px; align-items: center; margin-top: 6px; }
        .ae-mini-btn { width: 34px; height: 34px; border: 1px solid #cfd8e6; border-radius: 6px; background: #fff; color: #17335f; cursor: pointer; font-weight: 800; }
        .ae-mini-btn.add { color: #006eff; border-color: #b9d7ff; }
        .ae-mini-btn.remove { color: #e11d48; border-color: #ffd2dc; }
        .ae-combo { position: relative; }
        .ae-tree-combo { width: 280px; max-width: min(280px, 100%); }
        .ae-combo-toggle { width: 100%; height: 42px; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 0 12px; border: 1px solid #cfd8e6; border-radius: 6px; background: #fff; color: #122243; font-size: 14px; cursor: pointer; }
        .ae-combo-toggle span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ae-combo.open .ae-combo-toggle { border-color: #006eff; box-shadow: 0 0 0 3px rgba(0,110,255,.12); }
        .ae-combo-panel { display: none; position: absolute; z-index: 1900; top: calc(100% + 6px); left: 0; right: 0; padding: 10px; border: 1px solid #d9e1ec; border-radius: 8px; background: #fff; box-shadow: 0 18px 36px rgba(15,31,58,.16); }
        .ae-combo.open .ae-combo-panel { display: block; }
        .ae-combo-options { max-height: 190px; overflow: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 6px; }
        .ae-combo-option { padding: 9px 10px; cursor: pointer; border-bottom: 1px solid #edf2f7; }
        .ae-combo-option:last-child { border-bottom: 0; }
        .ae-combo-option:hover, .ae-combo-option.selected { background: #eef6ff; color: #005bd4; font-weight: 700; }
        .ae-suggest-wrap { position: relative; }
        .ae-suggest-panel { display: none; position: absolute; z-index: 2100; top: calc(100% + 6px); left: 0; right: 0; max-height: 190px; overflow: auto; border: 1px solid #d9e1ec; border-radius: 8px; background: #fff; box-shadow: 0 18px 36px rgba(15,31,58,.16); }
        .ae-suggest-panel.open { display: block; }
        .ae-suggest-option { padding: 9px 10px; cursor: pointer; border-bottom: 1px solid #edf2f7; font-size: 13px; }
        .ae-suggest-option:last-child { border-bottom: 0; }
        .ae-suggest-option:hover { background: #eef6ff; color: #005bd4; font-weight: 700; }
        .ae-suggest-empty { padding: 9px 10px; color: #64748b; font-size: 13px; }
        .ae-native-select-hidden { display: none; }
        @media (max-width: 1300px) {
            .ae-main, .ae-grid, .ae-grid-4, .ae-recovery-grid { grid-template-columns: 1fr; }
            body.ae-details-collapsed .ae-main { grid-template-columns: 1fr; }
            .ae-side-card { min-height: auto; }
            .ae-catalog { grid-template-columns: 1fr; }
            .ae-catalog form { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/menu.php'; ?>

<div class="content">
    <div class="ae-topbar">
        <button class="ae-menu-icon" type="button" onclick="toggleSidebar()" title="Recolher menu">=</button>
        <div class="ae-crumb">Arvore de Estrutura <span>&gt;</span> Cadastro de Produtos / Estrutura</div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="ae-status <?php echo ae_h($message_type); ?>"><?php echo ae_h($message); ?></div>
    <?php endif; ?>

    <section class="ae-card ae-recovery-card">
        <div class="ae-card-head">
            <div>
                <h2 class="ae-card-title">Backups e status dos dados</h2>
                <div class="ae-recovery-note">
                    Reativa somente registros <b>inativos que ainda existem no JSON ativo</b>. Registros presentes apenas em backups sao considerados excluidos e ficam ignorados.
                </div>
            </div>
            <form method="post" class="ae-actions" onsubmit="return confirm('Reativar apenas registros inativos ainda existentes no JSON ativo? Dados que existem somente em backups nao serao restaurados.');">
                <input type="hidden" name="action" value="restore_backup_inactive">
                <input type="hidden" name="arvore_id" value="<?php echo ae_h($selected_tree_id); ?>">
                <button class="ae-btn primary" type="submit" <?php echo (int)($backup_recovery_preview['total_restorable'] ?? 0) === 0 ? 'disabled' : ''; ?>>
                    Reativar inativos seguros
                </button>
            </form>
        </div>
        <div class="ae-card-body">
            <div class="ae-recovery-grid">
                <?php foreach ($status_summary as $table => $row): ?>
                    <div class="ae-recovery-stat">
                        <strong><?php echo ae_h($row['label']); ?></strong>
                        <span>Ativos: <?php echo (int)$row['active']; ?></span>
                        <span>Inativos: <?php echo (int)$row['inactive']; ?></span>
                        <span>Recuperaveis: <?php echo (int)($backup_recovery_preview['tables'][$table]['restorable'] ?? 0); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="ae-recovery-note" style="margin-top:10px;">
                Backups lidos: <?php echo (int)($backup_recovery_preview['files_read'] ?? 0); ?>.
                Backups invalidos: <?php echo (int)($backup_recovery_preview['files_invalid'] ?? 0); ?>.
                Registros ativos encontrados somente em backup e ignorados: <?php echo (int)($backup_recovery_preview['backup_only_ignored'] ?? 0); ?>.
                Registros marcados no historico como excluidos e bloqueados: <?php echo (int)($backup_recovery_preview['deleted_ignored'] ?? 0); ?>.
            </div>
        </div>
    </section>

    <section class="ae-card ae-root-card">
        <div class="ae-card-head">
            <h2 class="ae-card-title"><?php echo $creating_new_tree || !$tree ? 'Nova Arvore de Estrutura' : 'Dados do Item'; ?></h2>
            <div class="ae-actions">
                <form method="get" class="ae-actions">
                    <select name="arvore_id" id="treeSelect" onchange="this.form.submit()" class="ae-native-select-hidden">
                        <option value="">Nova arvore</option>
                        <?php foreach ($trees as $option_tree): ?>
                            <?php if (!($option_tree['ativo'] ?? 1) && ($option_tree['id'] ?? '') !== $selected_tree_id) { continue; } ?>
                            <option value="<?php echo ae_h($option_tree['id']); ?>" <?php echo ($option_tree['id'] ?? '') === $selected_tree_id ? 'selected' : ''; ?>>
                                <?php echo ae_h(($option_tree['codigo'] ?? '') . ' - ' . ($option_tree['nome'] ?? '') . (!($option_tree['ativo'] ?? 1) ? ' [arquivada]' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="ae-combo ae-tree-combo" data-select-id="treeSelect">
                        <button type="button" class="ae-combo-toggle" onclick="toggleCombo(this)"><span></span><b>v</b></button>
                        <div class="ae-combo-panel">
                            <input type="search" placeholder="Pesquisar arvore..." oninput="filterCombo(this)">
                            <div class="ae-combo-options"></div>
                        </div>
                    </div>
                    <a class="ae-btn primary" href="arvore_estrutura.php?novo=1" title="Iniciar criacao de uma nova arvore de estrutura">Nova</a>
                    <a class="ae-btn" href="<?php echo rf_route('arvore_estrutura', 'table'); ?><?php echo $selected_tree_id !== '' ? '?' . http_build_query(['arvore_id' => $selected_tree_id]) : ''; ?>" title="Abrir tabela de arvores">Tabela de Arvores</a>
                    <a class="ae-btn" href="cadastros-basicos/index.php?tipo=produtos_itens" title="Abrir cadastro mestre de produtos e itens">+ Novo Mestre</a>
                </form>
                <button class="ae-collapse-btn" type="button" onclick="toggleRootCard()" title="Recolher dados do item" aria-label="Recolher dados do item">&gt;</button>
            </div>
        </div>
        <div class="ae-card-body">
            <form method="post">
                <input type="hidden" name="action" id="rootFormAction" value="save_root">
                <input type="hidden" name="arvore_id" value="<?php echo ae_h($selected_tree_id); ?>">
                <input type="hidden" name="tree_id" value="<?php echo ae_h($tree['id'] ?? ''); ?>">
                <input type="hidden" name="root_codigo" value="<?php echo ae_h($root_form_item['codigo'] ?? ''); ?>">
                <div class="ae-grid">
                    <div class="ae-field">
                        <label>Codigo</label>
                        <div class="ae-code-preview"><?php echo ae_h($root_form_item['codigo'] ?? 'Automatico'); ?></div>
                    </div>
                    <div class="ae-field">
                        <label class="req">Nome</label>
                        <input name="root_nome" required value="<?php echo ae_h($root_form_item['nome'] ?? ''); ?>">
                    </div>
                    <div class="ae-field">
                        <label>Ativo</label>
                        <div class="ae-toggle"><input type="checkbox" name="root_ativo" <?php echo !isset($root_form_item['ativo']) || $root_form_item['ativo'] ? 'checked' : ''; ?>></div>
                    </div>
                    <div class="ae-field" style="grid-column: span 2;"></div>
                    <div class="ae-field">
                        <label>Codigo RM</label>
                        <input name="root_codigo_rm" value="<?php echo ae_h($root_form_item['codigo_rm'] ?? ''); ?>">
                    </div>
                    <div class="ae-field">
                        <label>Produto RM</label>
                        <input name="root_produto_rm" value="<?php echo ae_h($root_form_item['produto_rm'] ?? ''); ?>">
                    </div>
                    <div class="ae-field">
                        <label>Peso tara</label>
                        <input name="root_peso_tara" type="number" min="0" step="0.000001" value="<?php echo ae_h($root_form_item['peso_tara'] ?? ''); ?>">
                    </div>
                    <div class="ae-field" style="grid-column: span 2;"></div>
                    <div class="ae-field">
                        <label class="req">Tipo de Item</label>
                        <select name="root_tipo_item" id="rootTypeSelect" required class="ae-native-select-hidden">
                            <?php foreach ($tipos_item as $type): ?>
                                <option <?php echo ($root_form_item['tipo_item'] ?? '') === $type ? 'selected' : ''; ?>><?php echo ae_h($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="ae-combo" data-select-id="rootTypeSelect">
                            <button type="button" class="ae-combo-toggle" onclick="toggleCombo(this)"><span></span><b>v</b></button>
                            <div class="ae-combo-panel">
                                <input type="search" placeholder="Pesquisar tipo..." oninput="filterCombo(this)">
                                <div class="ae-combo-options"></div>
                                <div class="ae-inline-list"><input id="rootNewTypeValue" placeholder="Novo tipo"><button class="ae-mini-btn add" type="button" onclick="addListOption('rootTypeSelect','rootNewTypeValue'); syncCombos();">+</button><button class="ae-mini-btn remove" type="button" onclick="removeListOption('rootTypeSelect'); syncCombos();">-</button></div>
                            </div>
                        </div>
                    </div>
                    <div class="ae-field">
                        <label class="req">Unidade base</label>
                        <select name="root_unidade_base" id="rootUnitSelect" required class="ae-native-select-hidden">
                            <?php foreach ($unidades_base as $unit): ?>
                                <option <?php echo ($root_form_item['unidade_base'] ?? 'un') === $unit ? 'selected' : ''; ?>><?php echo ae_h($unit); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="ae-combo" data-select-id="rootUnitSelect">
                            <button type="button" class="ae-combo-toggle" onclick="toggleCombo(this)"><span></span><b>v</b></button>
                            <div class="ae-combo-panel">
                                <input type="search" placeholder="Pesquisar unidade..." oninput="filterCombo(this)">
                                <div class="ae-combo-options"></div>
                                <div class="ae-inline-list"><input id="rootNewUnitValue" placeholder="Nova unidade"><button class="ae-mini-btn add" type="button" onclick="addListOption('rootUnitSelect','rootNewUnitValue'); syncCombos();">+</button><button class="ae-mini-btn remove" type="button" onclick="removeListOption('rootUnitSelect'); syncCombos();">-</button></div>
                            </div>
                        </div>
                    </div>
                    <div class="ae-field">
                        <label>Categoria</label>
                        <input name="root_categoria" value="<?php echo ae_h($root_form_item['categoria'] ?? ''); ?>">
                    </div>
                    <div class="ae-field">
                        <label>Grupo</label>
                        <input name="root_grupo" value="<?php echo ae_h($root_form_item['grupo'] ?? ''); ?>">
                    </div>
                    <div class="ae-field">
                        <label>Fator de conversao</label>
                        <input name="root_fator_conversao_padrao" type="number" step="0.000001" value="<?php echo ae_h($root_form_item['fator_conversao_padrao'] ?? '1'); ?>">
                    </div>
                    <input type="hidden" name="tree_codigo" value="<?php echo ae_h($tree['codigo'] ?? ($root_form_item['codigo'] ?? '')); ?>">
                </div>
                <input type="hidden" name="root_observacao" value="<?php echo ae_h($root_form_item['observacao'] ?? ''); ?>">
                <input type="hidden" name="tree_descricao" value="<?php echo ae_h($tree['descricao'] ?? ''); ?>">
                <div class="ae-actions" style="justify-content:flex-end; margin-top:18px;">
                    <label class="ae-toggle" style="height:40px;"><input type="checkbox" name="tree_ativo" <?php echo !isset($tree['ativo']) || ($tree['ativo'] ?? 1) ? 'checked' : ''; ?>> Arvore ativa</label>
                    <button class="ae-btn primary" type="submit" onclick="document.getElementById('rootFormAction').value='save_root'"><?php echo $creating_new_tree || !$tree ? 'Criar nova arvore' : 'Salvar Item'; ?></button>
                    <?php if ($tree): ?>
                        <button class="ae-btn <?php echo $tree_is_active ? 'danger' : 'primary'; ?>" type="submit" onclick="if (!confirm('<?php echo $tree_is_active ? 'Arquivar esta arvore sem apagar sua estrutura?' : 'Reativar esta arvore?'; ?>')) return false; document.getElementById('rootFormAction').value='<?php echo $tree_is_active ? 'archive_tree' : 'reactivate_tree'; ?>';">
                            <?php echo $tree_is_active ? 'Arquivar Arvore' : 'Reativar Arvore'; ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <div class="ae-main">
        <section class="ae-card">
            <div class="ae-toolbar">
                <h2 class="ae-card-title">Arvore de Estrutura</h2>
                <div class="ae-actions">
                    <button class="ae-btn primary" type="button" onclick="openAddChild()" <?php echo !$tree || !$selected_item || !$tree_is_active ? 'disabled' : ''; ?>>+ Adicionar Filho</button>
                    <button class="ae-btn" id="editActionBtn" type="button" onclick="openEdit()" <?php echo !$selected_item || !$tree_is_active ? 'disabled' : ''; ?>>Editar</button>
                    <button class="ae-btn" id="editTreeActionBtn" type="button" onclick="openIndependentTree()" <?php echo $selected_independent_tree_id === '' ? 'disabled' : ''; ?>>Editar Arvore</button>
                    <button class="ae-btn" id="makeIndependentActionBtn" type="button" onclick="makeIndependentTree()" <?php echo !$tree || !$selected_item || !$tree_is_active ? 'disabled' : ''; ?>>Arvore Independente</button>
                    <button class="ae-btn danger" id="removeActionBtn" type="button" onclick="confirmRemove()" <?php echo !$selected_comp || !$tree_is_active ? 'disabled' : ''; ?>>Desvincular</button>
                    <button class="ae-btn danger" id="deleteActionBtn" type="button" onclick="confirmDelete()" <?php echo !$selected_comp || !$tree_is_active ? 'disabled' : ''; ?>>Excluir</button>
                    <button class="ae-btn" type="button" onclick="expandAll()">Expandir Tudo</button>
                    <button class="ae-btn" type="button" onclick="collapseAll()">Recolher Tudo</button>
                    <button class="ae-btn" type="button" onclick="openImport()" <?php echo !$tree ? 'disabled' : ''; ?>>Importar</button>
                    <?php if ($tree): ?>
                        <a class="ae-btn" href="arvore_estrutura.php?export=csv&arvore_id=<?php echo ae_h($tree['id']); ?>">Exportar</a>
                    <?php else: ?>
                        <button class="ae-btn" type="button" disabled>Exportar</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($tree_has_missing_tara): ?>
                <div class="ae-tara-alert">Atencao: esta arvore possui itens sem peso de tara cadastrado.</div>
            <?php endif; ?>
            <?php if ($selected_root_tree_id || $selected_is_reuse): ?>
                <details class="ae-shared-alert">
                    <summary>Arvore reutilizavel detectada</summary>
                    <div class="ae-shared-content">
                        <?php if ($selected_root_tree_id): ?>
                            <span>Este item possui arvore propria. Usos encontrados: <?php echo count($selected_shared_usages); ?>.</span>
                            <?php if (!empty($selected_shared_usages)): ?>
                                <ul>
                                    <?php foreach (array_slice($selected_shared_usages, 0, 4) as $usage): ?>
                                        <li><?php echo ae_h(($usage['arvore_label'] ?? '') . ' / pai: ' . ($usage['pai_label'] ?? '')); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="ae-actions">
                            <?php if ($selected_root_tree_id): ?>
                                <a class="ae-btn" href="arvore_estrutura.php?<?php echo http_build_query(['arvore_id' => $selected_root_tree_id, 'selected_item_id' => $selected_item_id]); ?>">Editar arvore compartilhada</a>
                            <?php endif; ?>
                            <?php if ($selected_comp && $selected_is_reuse): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="detach_reuse">
                                    <input type="hidden" name="arvore_id" value="<?php echo ae_h($selected_tree_id); ?>">
                                    <input type="hidden" name="detach_item_id" value="<?php echo ae_h($selected_item_id); ?>">
                                    <input type="hidden" name="detach_comp_id" value="<?php echo ae_h($selected_comp['id'] ?? ''); ?>">
                                    <button class="ae-btn" type="submit" onclick="return confirm('Desassociar esta ramificacao do reuso compartilhado?')">Desassociar reuso</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
            <?php endif; ?>
            <div class="ae-table-wrap">
                <?php if (empty($tree_rows)): ?>
                    <div class="ae-empty">
                        <div>
                            <div style="font-size:42px; color:#8aa0bd;">[]</div>
                            <strong>Nenhum item cadastrado.</strong><br>
                            <span>Clique em "Salvar Item" para criar a raiz da arvore.</span>
                        </div>
                    </div>
                <?php else: ?>
                    <table class="ae-table" id="treeTable">
                        <thead>
                            <tr>
                                <th>Estrutura</th>
                                <th>Codigo</th>
                                <th>Descricao</th>
                                <th>Unidade</th>
                                <th>Quantidade</th>
                                <th>Fator Conv.</th>
                                <th>%</th>
                                <th>Nivel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tree_rows as $row): ?>
                                <?php
                                    $item = $row['item'];
                                    $comp = $row['comp'];
                                    $item_id = $item['id'] ?? '';
                                    $comp_id = $comp['id'] ?? '';
                                    $nivel = (int)$row['nivel'];
                                    $tipo_class = strtolower($item['tipo_item'] ?? 'produto');
                                    $is_selected = $item_id === $selected_item_id && (($selected_comp_id === '') || $selected_comp_id === $comp_id);
                                    $tara_missing = ($item['peso_tara'] ?? null) === null || ($item['peso_tara'] ?? '') === '';
                                    $independent_tree_id = ae_find_subtree_source_tree_id($data, $item_id, $selected_tree_id);
                                ?>
                                <tr class="<?php echo trim(($is_selected ? 'selected ' : '') . ($tara_missing ? 'tara-missing' : '')); ?>" data-level="<?php echo $nivel; ?>" data-item-id="<?php echo ae_h($item_id); ?>" data-comp-id="<?php echo ae_h($comp_id); ?>" data-independent-tree-id="<?php echo ae_h($independent_tree_id); ?>" data-label="<?php echo ae_h(($item['codigo'] ?? '') . ' - ' . ($item['nome'] ?? '')); ?>" onclick="selectRow(this, true)">
                                    <td>
                                        <div class="ae-tree-cell" style="--level:<?php echo $nivel; ?>">
                                            <span class="ae-indent"></span>
                                            <button class="ae-expander" type="button" onclick="event.stopPropagation(); selectRow(this.closest('tr')); toggleBranch(this)"> <?php echo $row['has_children'] ? 'v' : '-'; ?> </button>
                                            <span class="ae-type-icon <?php echo ae_h($tipo_class); ?>">□</span>
                                        </div>
                                    </td>
                                    <td><strong><?php echo ae_h($item['codigo'] ?? ''); ?></strong></td>
                                    <td><?php echo ae_h($item['nome'] ?? ''); ?></td>
                                    <td><?php echo ae_h($comp['unidade'] ?? ($item['unidade_base'] ?? '')); ?></td>
                                    <td><?php echo $comp ? ae_fmt($comp['quantidade'] ?? 0) : ae_fmt(1); ?></td>
                                    <td><?php echo ae_fmt($comp['fator_conversao'] ?? ($item['fator_conversao_padrao'] ?? 1)); ?></td>
                                    <td><?php echo ($comp && ($comp['percentual'] ?? '') !== null) ? ae_fmt($comp['percentual'], 2) : '-'; ?></td>
                                    <td><?php echo $nivel; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="ae-legend">
                <span style="color:#006eff;">Produto</span>
                <span style="color:#0aa36f;">Embalagem</span>
                <span style="color:#f59e0b;">Insumo</span>
                <span style="color:#6d28d9;">Servico</span>
                <span style="color:#64748b;">Recurso</span>
            </div>
        </section>

        <aside class="ae-card ae-side-card">
            <div class="ae-card-head">
                <h2 class="ae-card-title">Detalhes do Item Selecionado</h2>
                <button class="ae-collapse-btn ae-detail-toggle" type="button" onclick="toggleDetails()" title="Recolher detalhes">&gt;</button>
            </div>
            <div class="ae-card-body">
                <?php if (!$selected_item): ?>
                    <p>Nenhum item selecionado.</p>
                <?php else: ?>
                    <div class="ae-item-summary">
                        <div class="ae-cube">□</div>
                        <div>
                            <h3 style="margin:0 0 4px;"><?php echo ae_h($selected_item['codigo'] ?? ''); ?></h3>
                            <strong><?php echo ae_h($selected_item['nome'] ?? ''); ?></strong>
                        </div>
                    </div>
                    <div class="ae-detail-row" style="margin-top:14px;"><span>Tipo:</span><strong><?php echo ae_h($selected_item['tipo_item'] ?? ''); ?></strong></div>
                    <div class="ae-detail-row"><span>Unidade base:</span><strong><?php echo ae_h($selected_item['unidade_base'] ?? ''); ?></strong></div>
                    <div class="ae-detail-row"><span>Peso tara:</span><strong><?php echo (($selected_item['peso_tara'] ?? null) === null || ($selected_item['peso_tara'] ?? '') === '') ? '<span class="ae-pill warn">Sem peso</span>' : ae_h(ae_fmt($selected_item['peso_tara'], 2)); ?></strong></div>
                    <div class="ae-detail-row"><span>Nivel:</span><strong><?php echo $selected_level; ?></strong></div>
                    <div class="ae-tabs">
                        <?php foreach (['geral' => 'Geral', 'parametros' => 'Parametros', 'conversoes' => 'Conversoes', 'observacoes' => 'Observacoes', 'historico' => 'Historico'] as $tab => $label): ?>
                            <a class="ae-tab <?php echo $active_tab === $tab ? 'active' : ''; ?>" href="arvore_estrutura.php?<?php echo http_build_query(['arvore_id' => $selected_tree_id, 'selected_item_id' => $selected_item_id, 'selected_comp_id' => $selected_comp['id'] ?? '', 'tab' => $tab]); ?>"><?php echo ae_h($label); ?></a>
                        <?php endforeach; ?>
                    </div>

                    <div class="ae-tab-panel">
                        <?php if ($active_tab === 'geral'): ?>
                            <?php foreach ([
                                'Codigo RM' => $selected_item['codigo_rm'] ?? '',
                                'Produto RM' => $selected_item['produto_rm'] ?? '',
                                'Categoria' => $selected_item['categoria'] ?? '',
                                'Grupo' => $selected_item['grupo'] ?? '',
                                'Peso tara' => (($selected_item['peso_tara'] ?? null) === null || ($selected_item['peso_tara'] ?? '') === '') ? 'Sem peso' : ae_fmt($selected_item['peso_tara'], 2),
                                'Fator de conversao' => ae_fmt($selected_item['fator_conversao_padrao'] ?? 1),
                                'Ativo' => !empty($selected_item['ativo']) ? '<span class="ae-pill">Sim</span>' : 'Nao'
                            ] as $label => $value): ?>
                                <div class="ae-detail-row"><span><?php echo ae_h($label); ?>:</span><strong><?php echo $label === 'Ativo' ? $value : ae_h($value); ?></strong></div>
                            <?php endforeach; ?>
                            <div class="ae-leaf-totals">
                                <h4>Quantidades totais no menor nivel</h4>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Codigo</th>
                                            <th>Item</th>
                                            <th class="qty">Total</th>
                                            <th>Unidade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($selected_leaf_totals as $leaf_total): ?>
                                            <?php $leaf_item = $leaf_total['item'] ?? []; ?>
                                            <tr>
                                                <td><?php echo ae_h($leaf_item['codigo'] ?? ''); ?></td>
                                                <td><?php echo ae_h($leaf_item['nome'] ?? ''); ?></td>
                                                <td class="qty"><?php echo ae_fmt($leaf_total['quantidade_total'] ?? 0); ?></td>
                                                <td><?php echo ae_h($leaf_total['unidade'] ?? ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="ae-detail-row"><span>Criado em (Brasilia):</span><strong><?php echo ae_h($selected_item['criado_em'] ?? ''); ?></strong></div>
                            <div class="ae-detail-row"><span>Atualizado em (Brasilia):</span><strong><?php echo ae_h($selected_item['atualizado_em'] ?? ''); ?></strong></div>
                            <?php if ($tree_has_missing_tara): ?>
                                <details class="ae-missing-list">
                                    <summary>Itens sem peso de tara nesta arvore</summary>
                                    <div class="ae-missing-content">
                                        <?php foreach ($missing_tara_groups as $parent_label => $children): ?>
                                            <strong><?php echo ae_h($parent_label); ?></strong>
                                            <ul>
                                                <?php foreach ($children as $missing): ?>
                                                    <li><?php echo ae_h(($missing['codigo'] ?? '') . ' - ' . ($missing['nome'] ?? '') . ' | nivel ' . ($missing['nivel'] ?? '')); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        <?php elseif ($active_tab === 'parametros'): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="save_params">
                                <input type="hidden" name="arvore_id" value="<?php echo ae_h($selected_tree_id); ?>">
                                <input type="hidden" name="param_item_id" value="<?php echo ae_h($selected_item_id); ?>">
                                <div class="ae-grid ae-grid-4">
                                    <?php foreach ([
                                        'unidade_compra' => 'Unidade compra',
                                        'unidade_producao' => 'Unidade producao',
                                        'lead_time_compra' => 'Lead time compra',
                                        'lead_time_producao' => 'Lead time producao',
                                        'lote_compra' => 'Lote compra',
                                        'lote_producao' => 'Lote producao',
                                        'estoque_seguranca' => 'Estoque seguranca',
                                        'tipo_planejamento' => 'Tipo planejamento'
                                    ] as $field => $label): ?>
                                        <div class="ae-field"><label><?php echo ae_h($label); ?></label><input name="<?php echo ae_h($field); ?>" value="<?php echo ae_h($selected_param[$field] ?? ''); ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="ae-actions" style="justify-content:flex-end; margin-top:12px;"><button class="ae-btn primary">Salvar parametros</button></div>
                            </form>
                        <?php elseif ($active_tab === 'conversoes'): ?>
                            <table class="ae-table">
                                <thead><tr><th>Origem</th><th>Destino</th><th>Fator</th><th>Descricao</th></tr></thead>
                                <tbody>
                                <?php foreach ($selected_conversions as $conv): ?>
                                    <tr><td><?php echo ae_h($conv['unidade_origem'] ?? ''); ?></td><td><?php echo ae_h($conv['unidade_destino'] ?? ''); ?></td><td><?php echo ae_fmt($conv['fator'] ?? 1); ?></td><td><?php echo ae_h($conv['descricao'] ?? ''); ?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <form method="post" style="margin-top:12px;">
                                <input type="hidden" name="action" value="add_conversion">
                                <input type="hidden" name="arvore_id" value="<?php echo ae_h($selected_tree_id); ?>">
                                <input type="hidden" name="conv_item_id" value="<?php echo ae_h($selected_item_id); ?>">
                                <div class="ae-grid ae-grid-4">
                                    <div class="ae-field"><label>Un. origem</label><input name="unidade_origem"></div>
                                    <div class="ae-field"><label>Un. destino</label><input name="unidade_destino"></div>
                                    <div class="ae-field"><label>Fator</label><input name="fator" type="number" step="0.000001" value="1"></div>
                                    <div class="ae-field"><label>Descricao</label><input name="descricao"></div>
                                </div>
                                <div class="ae-actions" style="justify-content:flex-end; margin-top:12px;"><button class="ae-btn primary">Nova conversao</button></div>
                            </form>
                        <?php elseif ($active_tab === 'observacoes'): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="save_observation">
                                <input type="hidden" name="arvore_id" value="<?php echo ae_h($selected_tree_id); ?>">
                                <input type="hidden" name="obs_item_id" value="<?php echo ae_h($selected_item_id); ?>">
                                <div class="ae-field"><label>Observacoes</label><textarea name="observacao_livre"><?php echo ae_h($selected_item['observacao'] ?? ''); ?></textarea></div>
                                <div class="ae-actions" style="justify-content:flex-end; margin-top:12px;"><button class="ae-btn primary">Salvar observacoes</button></div>
                            </form>
                        <?php else: ?>
                            <?php foreach (array_reverse($data['historico']) as $hist): ?>
                                <?php if (($hist['item_id'] ?? '') === $selected_item_id || ($hist['arvore_id'] ?? '') === $selected_tree_id): ?>
                                    <div class="ae-detail-row"><span><?php echo ae_h($hist['criado_em'] ?? ''); ?></span><strong><?php echo ae_h(($hist['acao'] ?? '') . ' - ' . ($hist['detalhe'] ?? '')); ?></strong></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<div class="ae-modal" id="addChildModal">
    <div class="ae-modal-box">
        <form method="post">
            <input type="hidden" name="action" value="add_child">
            <input type="hidden" name="arvore_id" value="<?php echo ae_h($selected_tree_id); ?>">
            <input type="hidden" name="parent_item_id" id="addParentItemId" value="<?php echo ae_h($selected_item_id); ?>">
            <input type="hidden" name="parent_level" id="addParentLevel" value="<?php echo ae_h($selected_level); ?>">
            <div class="ae-modal-head"><h2 class="ae-card-title">Adicionar Filho</h2><button class="ae-close" type="button" onclick="closeModals()">x</button></div>
            <div class="ae-card-body">
                <div class="ae-grid ae-grid-4">
                    <div class="ae-field" style="grid-column: span 2;"><label>Item pai</label><input id="addParentLabel" readonly value="<?php echo ae_h(($selected_item['codigo'] ?? '') . ' - ' . ($selected_item['nome'] ?? '')); ?>"></div>
                    <div class="ae-field" style="grid-column: span 2;">
                        <label>Item filho</label>
                        <select name="child_item_id" id="childSelect" onchange="toggleNewChild(this)" class="ae-native-select-hidden">
                            <option value="__new__" data-unit="un" selected>Cadastrar novo item</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo ae_h($item['id']); ?>" data-unit="<?php echo ae_h($item['unidade_base'] ?? ''); ?>"><?php echo ae_h(($item['codigo'] ?? '') . ' - ' . ($item['nome'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="ae-combo" data-select-id="childSelect">
                            <button type="button" class="ae-combo-toggle" onclick="toggleCombo(this)"><span></span><b>v</b></button>
                            <div class="ae-combo-panel">
                                <input type="search" placeholder="Pesquisar item existente..." oninput="filterCombo(this)">
                                <div class="ae-combo-options"></div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="child_codigo" value="">
                    <div class="ae-field new-child"><label>Codigo</label><div class="ae-code-preview">Automatico</div></div>
                    <div class="ae-field new-child">
                        <label>Nome novo</label>
                        <div class="ae-suggest-wrap">
                            <input name="child_nome" id="childNameInput" autocomplete="off" oninput="showChildNameSuggestions(this)" onfocus="showChildNameSuggestions(this)">
                            <div class="ae-suggest-panel" id="childNameSuggestions"></div>
                        </div>
                    </div>
                    <div class="ae-field new-child" style="grid-column: span 2;"></div>
                    <div class="ae-field new-child"><label>Codigo RM</label><input name="child_codigo_rm"></div>
                    <div class="ae-field new-child"><label>Produto RM</label><input name="child_produto_rm"></div>
                    <div class="ae-field new-child"><label>Peso tara</label><input name="child_peso_tara" type="number" min="0" step="0.000001"></div>
                    <div class="ae-field new-child"></div>
                    <div class="ae-field new-child">
                        <label>Tipo</label>
                        <select name="child_tipo_item" id="childTypeSelect" class="ae-native-select-hidden">
                            <?php foreach ($tipos_item as $type): ?>
                                <option <?php echo $type === 'Componente' ? 'selected' : ''; ?>><?php echo ae_h($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="ae-combo" data-select-id="childTypeSelect">
                            <button type="button" class="ae-combo-toggle" onclick="toggleCombo(this)"><span></span><b>⌄</b></button>
                            <div class="ae-combo-panel">
                                <input type="search" placeholder="Pesquisar tipo..." oninput="filterCombo(this)">
                                <div class="ae-combo-options"></div>
                                <div class="ae-inline-list"><input id="newTypeValue" placeholder="Novo tipo"><button class="ae-mini-btn add" type="button" onclick="addListOption('childTypeSelect','newTypeValue')">+</button><button class="ae-mini-btn remove" type="button" onclick="removeListOption('childTypeSelect')">-</button></div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="child_unidade_base" id="childUnidadeBase" value="un">
                    <input type="hidden" name="child_ativo" value="1">
                    <div class="ae-field"><label class="req">Quantidade</label><input name="quantidade" type="number" step="0.000001" required></div>
                    <div class="ae-field">
                        <label class="req">Unidade</label>
                        <select name="unidade" id="childUnitSelect" required onchange="setChildUnit(this.value)" class="ae-native-select-hidden">
                            <?php foreach ($unidades_base as $unit): ?>
                                <option <?php echo $unit === 'un' ? 'selected' : ''; ?>><?php echo ae_h($unit); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="ae-combo" data-select-id="childUnitSelect">
                            <button type="button" class="ae-combo-toggle" onclick="toggleCombo(this)"><span></span><b>⌄</b></button>
                            <div class="ae-combo-panel">
                                <input type="search" placeholder="Pesquisar unidade..." oninput="filterCombo(this)">
                                <div class="ae-combo-options"></div>
                                <div class="ae-inline-list"><input id="newUnitValue" placeholder="Nova unidade"><button class="ae-mini-btn add" type="button" onclick="addListOption('childUnitSelect','newUnitValue')">+</button><button class="ae-mini-btn remove" type="button" onclick="removeListOption('childUnitSelect')">-</button></div>
                            </div>
                        </div>
                    </div>
                    <div class="ae-field"><label>Fator conversao</label><input name="fator_conversao" type="number" step="0.000001" value="1"></div>
                    <div class="ae-field"><label>Percentual</label><input name="percentual" type="number" step="0.000001"></div>
                    <div class="ae-field" style="grid-column: span 4;"><label>Observacao</label><input name="observacao"></div>
                </div>
            </div>
            <div class="ae-modal-foot"><button class="ae-btn" type="button" onclick="closeModals()">Cancelar</button><button class="ae-btn primary">Salvar</button></div>
        </form>
    </div>
</div>

<div class="ae-modal <?php echo ($_GET['open_edit'] ?? '') === '1' ? 'show' : ''; ?>" id="editModal">
    <div class="ae-modal-box">
        <form method="post">
            <input type="hidden" name="action" value="edit_selected">
            <input type="hidden" name="arvore_id" value="<?php echo ae_h($selected_tree_id); ?>">
            <input type="hidden" name="edit_item_id" id="editItemId" value="<?php echo ae_h($selected_item_id); ?>">
            <input type="hidden" name="edit_comp_id" id="editCompId" value="<?php echo ae_h($selected_comp['id'] ?? ''); ?>">
            <div class="ae-modal-head"><h2 class="ae-card-title">Editar item e composicao</h2><button class="ae-close" type="button" onclick="closeModals()">x</button></div>
            <div class="ae-card-body">
                <h3>Dados cadastrais do item</h3>
                <?php if ($selected_root_tree_id || $selected_is_reuse): ?>
                    <div class="ae-shared-alert">
                        <strong>Escopo da alteracao</strong>
                        <?php echo $selected_root_tree_id ? 'Este item tem arvore propria/reutilizavel. Para alterar a estrutura compartilhada, abra a arvore compartilhada.' : 'Esta ocorrencia veio de reaproveitamento.'; ?>
                        <?php if ($selected_root_tree_id): ?>
                            <div class="ae-actions"><a class="ae-btn" href="arvore_estrutura.php?<?php echo http_build_query(['arvore_id' => $selected_root_tree_id, 'selected_item_id' => $selected_item_id]); ?>">Abrir arvore compartilhada</a></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="ae-grid ae-grid-4">
                    <div class="ae-field"><label>Codigo</label><div class="ae-code-preview"><?php echo ae_h($selected_item['codigo'] ?? 'Automatico'); ?></div></div>
                    <div class="ae-field"><label>Nome</label><input name="edit_nome" value="<?php echo ae_h($selected_item['nome'] ?? ''); ?>"></div>
                    <div class="ae-field"><label>Codigo RM</label><input name="edit_codigo_rm" value="<?php echo ae_h($selected_item['codigo_rm'] ?? ''); ?>"></div>
                    <div class="ae-field"><label>Produto RM</label><input name="edit_produto_rm" value="<?php echo ae_h($selected_item['produto_rm'] ?? ''); ?>"></div>
                    <div class="ae-field"><label>Peso tara</label><input name="edit_peso_tara" type="number" min="0" step="0.000001" value="<?php echo ae_h($selected_item['peso_tara'] ?? ''); ?>"></div>
                    <div class="ae-field"><label>Tipo</label><select name="edit_tipo_item"><?php foreach ($tipos_item as $type): ?><option <?php echo ($selected_item['tipo_item'] ?? '') === $type ? 'selected' : ''; ?>><?php echo ae_h($type); ?></option><?php endforeach; ?></select></div>
                    <div class="ae-field"><label>Unidade do item</label><select name="edit_unidade_base" onchange="syncEditTreeUnit(this.value)"><?php foreach ($unidades_base as $unit): ?><option <?php echo ($selected_item['unidade_base'] ?? '') === $unit ? 'selected' : ''; ?>><?php echo ae_h($unit); ?></option><?php endforeach; ?></select></div>
                    <div class="ae-field"><label>Categoria</label><input name="edit_categoria" value="<?php echo ae_h($selected_item['categoria'] ?? ''); ?>"></div>
                    <div class="ae-field"><label>Grupo</label><input name="edit_grupo" value="<?php echo ae_h($selected_item['grupo'] ?? ''); ?>"></div>
                    <div class="ae-field"><label>Fator padrao</label><input name="edit_fator_conversao_padrao" type="number" step="0.000001" value="<?php echo ae_h($selected_item['fator_conversao_padrao'] ?? '1'); ?>"></div>
                    <div class="ae-field"><label>Ativo</label><div class="ae-toggle"><input type="checkbox" name="edit_ativo" <?php echo !isset($selected_item['ativo']) || $selected_item['ativo'] ? 'checked' : ''; ?>></div></div>
                    <div class="ae-field" style="grid-column: span 4;"><label>Observacao do item</label><input name="edit_item_observacao" value="<?php echo ae_h($selected_item['observacao'] ?? ''); ?>"></div>
                </div>
                <h3>Dados do vinculo pai-filho</h3>
                <div class="ae-grid ae-grid-4">
                    <input type="hidden" name="edit_unidade_original" value="<?php echo ae_h($selected_comp['unidade'] ?? ($selected_item['unidade_base'] ?? '')); ?>">
                    <div class="ae-field"><label>Quantidade</label><input name="edit_quantidade" type="number" step="0.000001" value="<?php echo ae_h($selected_comp['quantidade'] ?? '1'); ?>"></div>
                    <div class="ae-field"><label>Unidade na arvore</label><input name="edit_unidade" id="editTreeUnit" value="<?php echo ae_h($selected_comp['unidade'] ?? ($selected_item['unidade_base'] ?? '')); ?>"></div>
                    <div class="ae-field"><label>Fator conversao</label><input name="edit_fator_conversao" type="number" step="0.000001" value="<?php echo ae_h($selected_comp['fator_conversao'] ?? '1'); ?>"></div>
                    <div class="ae-field"><label>Percentual</label><input name="edit_percentual" type="number" step="0.000001" value="<?php echo ae_h($selected_comp['percentual'] ?? ''); ?>"></div>
                    <div class="ae-field" style="grid-column: span 4;"><label>Observacao do vinculo</label><input name="edit_comp_observacao" value="<?php echo ae_h($selected_comp['observacao'] ?? ''); ?>"></div>
                </div>
            </div>
            <div class="ae-modal-foot"><button class="ae-btn" type="button" onclick="closeModals()">Cancelar</button><button class="ae-btn primary">Salvar</button></div>
        </form>
    </div>
</div>

<div class="ae-modal" id="importModal">
    <div class="ae-modal-box">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import_csv">
            <input type="hidden" name="arvore_id" value="<?php echo ae_h($selected_tree_id); ?>">
            <div class="ae-modal-head"><h2 class="ae-card-title">Importar Excel/CSV</h2><button class="ae-close" type="button" onclick="closeModals()">x</button></div>
            <div class="ae-card-body">
                <p>Envie um CSV com as colunas: <strong>codigo_pai, codigo_filho, quantidade, unidade, fator_conversao, observacao</strong>.</p>
                <div class="ae-field"><label>Arquivo CSV</label><input type="file" name="arquivo_csv" accept=".csv,text/csv" required></div>
            </div>
            <div class="ae-modal-foot"><button class="ae-btn" type="button" onclick="closeModals()">Cancelar</button><button class="ae-btn primary">Importar</button></div>
        </form>
    </div>
</div>

<form method="post" id="removeForm">
    <input type="hidden" name="action" value="remove_link">
    <input type="hidden" name="arvore_id" value="<?php echo ae_h($selected_tree_id); ?>">
    <input type="hidden" name="remove_comp_id" id="removeCompId" value="<?php echo ae_h($selected_comp['id'] ?? ''); ?>">
</form>
<form method="post" id="deleteForm">
    <input type="hidden" name="action" value="delete_link">
    <input type="hidden" name="arvore_id" value="<?php echo ae_h($selected_tree_id); ?>">
    <input type="hidden" name="delete_comp_id" id="deleteCompId" value="<?php echo ae_h($selected_comp['id'] ?? ''); ?>">
</form>
<form method="post" id="independentTreeForm">
    <input type="hidden" name="action" value="make_independent_tree">
    <input type="hidden" name="arvore_id" value="<?php echo ae_h($selected_tree_id); ?>">
    <input type="hidden" name="independent_item_id" id="independentItemId" value="<?php echo ae_h($selected_item_id); ?>">
</form>

<script>
const AE_SERVER_SELECTED_ITEM_ID = '<?php echo ae_h($selected_item_id); ?>';
const AE_SERVER_SELECTED_COMP_ID = '<?php echo ae_h($selected_comp['id'] ?? ''); ?>';
const AE_TREE_ACTIVE = <?php echo $tree_is_active ? 'true' : 'false'; ?>;
let aeSelectedIndependentTreeId = '<?php echo ae_h($selected_independent_tree_id); ?>';
let aeSelectedItemId = AE_SERVER_SELECTED_ITEM_ID;
let aeSelectedCompId = AE_SERVER_SELECTED_COMP_ID;

function applyPanelState() {
    if (localStorage.getItem('aeSidebarCollapsed') === '1') {
        document.body.classList.add('ae-sidebar-collapsed');
    }
    if (localStorage.getItem('aeDetailsCollapsed') === '1') {
        document.body.classList.add('ae-details-collapsed');
    }
    if (localStorage.getItem('aeRootCollapsed') === '1') {
        document.body.classList.add('ae-root-collapsed');
    }
}
function toggleSidebar() {
    document.body.classList.toggle('ae-sidebar-collapsed');
    localStorage.setItem('aeSidebarCollapsed', document.body.classList.contains('ae-sidebar-collapsed') ? '1' : '0');
}
function toggleDetails() {
    document.body.classList.toggle('ae-details-collapsed');
    localStorage.setItem('aeDetailsCollapsed', document.body.classList.contains('ae-details-collapsed') ? '1' : '0');
}
function toggleRootCard() {
    document.body.classList.toggle('ae-root-collapsed');
    localStorage.setItem('aeRootCollapsed', document.body.classList.contains('ae-root-collapsed') ? '1' : '0');
}
function selectRow(row, syncDetails = false) {
    document.querySelectorAll('#treeTable tbody tr.selected').forEach(item => item.classList.remove('selected'));
    row.classList.add('selected');

    const itemId = row.dataset.itemId || '';
    const compId = row.dataset.compId || '';
    const label = row.dataset.label || '';
    const level = row.dataset.level || '0';
    const independentTreeId = row.dataset.independentTreeId || '';
    aeSelectedItemId = itemId;
    aeSelectedCompId = compId;
    aeSelectedIndependentTreeId = independentTreeId;

    const addParentItem = document.getElementById('addParentItemId');
    const addParentLevel = document.getElementById('addParentLevel');
    const addParentLabel = document.getElementById('addParentLabel');
    const editItem = document.getElementById('editItemId');
    const editComp = document.getElementById('editCompId');
    const removeComp = document.getElementById('removeCompId');
    const deleteComp = document.getElementById('deleteCompId');
    const removeBtn = document.getElementById('removeActionBtn');
    const deleteBtn = document.getElementById('deleteActionBtn');
    const editTreeBtn = document.getElementById('editTreeActionBtn');
    const makeIndependentBtn = document.getElementById('makeIndependentActionBtn');
    const independentItem = document.getElementById('independentItemId');

    if (addParentItem) addParentItem.value = itemId;
    if (addParentLevel) addParentLevel.value = level;
    if (addParentLabel) addParentLabel.value = label;
    if (editItem) editItem.value = itemId;
    if (editComp) editComp.value = compId;
    if (removeComp) removeComp.value = compId;
    if (deleteComp) deleteComp.value = compId;
    if (removeBtn) removeBtn.disabled = compId === '' || !AE_TREE_ACTIVE;
    if (deleteBtn) deleteBtn.disabled = compId === '' || !AE_TREE_ACTIVE;
    if (editTreeBtn) editTreeBtn.disabled = independentTreeId === '';
    if (makeIndependentBtn) makeIndependentBtn.disabled = itemId === '' || !AE_TREE_ACTIVE;
    if (independentItem) independentItem.value = itemId;

    if (syncDetails && (itemId !== AE_SERVER_SELECTED_ITEM_ID || compId !== AE_SERVER_SELECTED_COMP_ID)) {
        rememberScroll();
        const params = new URLSearchParams(window.location.search);
        params.set('arvore_id', '<?php echo ae_h($selected_tree_id); ?>');
        params.set('selected_item_id', itemId);
        if (compId) params.set('selected_comp_id', compId); else params.delete('selected_comp_id');
        params.delete('open_edit');
        window.location.href = 'arvore_estrutura.php?' + params.toString();
    }
}
function openAddChild() { document.getElementById('addChildModal').classList.add('show'); }
function openIndependentTree() {
    if (!aeSelectedIndependentTreeId) return;
    rememberScroll();
    const params = new URLSearchParams();
    params.set('arvore_id', aeSelectedIndependentTreeId);
    params.set('selected_item_id', aeSelectedItemId);
    window.location.href = 'arvore_estrutura.php?' + params.toString();
}
function makeIndependentTree() {
    const form = document.getElementById('independentTreeForm');
    if (!form || !aeSelectedItemId) return;
    if (aeSelectedIndependentTreeId) {
        openIndependentTree();
        return;
    }
    if (confirm('Criar uma arvore independente a partir desta ramificacao?')) {
        rememberScroll();
        form.submit();
    }
}
function openEdit() {
    if (aeSelectedItemId !== AE_SERVER_SELECTED_ITEM_ID || aeSelectedCompId !== AE_SERVER_SELECTED_COMP_ID) {
        rememberScroll();
        const params = new URLSearchParams(window.location.search);
        params.set('arvore_id', '<?php echo ae_h($selected_tree_id); ?>');
        params.set('selected_item_id', aeSelectedItemId);
        if (aeSelectedCompId) params.set('selected_comp_id', aeSelectedCompId); else params.delete('selected_comp_id');
        params.set('open_edit', '1');
        window.location.href = 'arvore_estrutura.php?' + params.toString();
        return;
    }
    document.getElementById('editModal').classList.add('show');
}
function syncEditTreeUnit(unit) {
    const treeUnit = document.getElementById('editTreeUnit');
    if (treeUnit) treeUnit.value = unit || '';
}
function openImport() { document.getElementById('importModal').classList.add('show'); }
function closeModals() { document.querySelectorAll('.ae-modal').forEach(modal => modal.classList.remove('show')); }
function setChildUnit(unit) {
    const unitSelect = document.getElementById('childUnitSelect');
    const childBase = document.getElementById('childUnidadeBase');
    if (!unitSelect || !unit) return;
    const exists = Array.from(unitSelect.options).some(option => option.value === unit || option.text === unit);
    if (exists) {
        unitSelect.value = unit;
    }
    if (childBase) childBase.value = unitSelect.value;
}
function filterSelectOptions(input, select, keepValue) {
    if (!input || !select) return;
    const term = input.value.trim().toLowerCase();
    Array.from(select.options).forEach(option => {
        if (keepValue && option.value === keepValue) {
            option.hidden = false;
            return;
        }
        option.hidden = term !== '' && !option.text.toLowerCase().includes(term);
    });
}
function addListOption(selectId, inputId) {
    const select = document.getElementById(selectId);
    const input = document.getElementById(inputId);
    if (!select || !input) return;
    const value = input.value.trim();
    if (value === '') return;

    const existing = Array.from(select.options).find(option => option.text.toLowerCase() === value.toLowerCase());
    if (existing) {
        select.value = existing.value;
    } else {
        const option = new Option(value, value, false, true);
        select.add(option);
        select.value = value;
    }
    if (selectId === 'childUnitSelect') {
        setChildUnit(select.value);
    }
    input.value = '';
    syncCombos();
}
function removeListOption(selectId) {
    const select = document.getElementById(selectId);
    if (!select || select.selectedIndex < 0) return;
    if (!confirm('Remover esta opcao da lista desta tela?')) return;
    select.remove(select.selectedIndex);
    if (select.options.length > 0) {
        select.selectedIndex = 0;
    }
    if (selectId === 'childUnitSelect') {
        setChildUnit(select.value);
    }
    syncCombos();
}
function syncCombos() {
    document.querySelectorAll('.ae-combo').forEach(combo => {
        const select = document.getElementById(combo.dataset.selectId || '');
        const label = combo.querySelector('.ae-combo-toggle span');
        const optionsBox = combo.querySelector('.ae-combo-options');
        if (!select || !label || !optionsBox) return;

        label.textContent = select.options[select.selectedIndex]?.text || 'Selecione uma opcao';
        optionsBox.innerHTML = '';
        Array.from(select.options).forEach(option => {
            const div = document.createElement('div');
            div.className = 'ae-combo-option' + (option.selected ? ' selected' : '');
            div.textContent = option.text;
            div.dataset.value = option.value;
            div.onclick = () => {
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                combo.classList.remove('open');
                syncCombos();
            };
            optionsBox.appendChild(div);
        });
    });
}
function toggleCombo(button) {
    const combo = button.closest('.ae-combo');
    document.querySelectorAll('.ae-combo.open').forEach(openCombo => {
        if (openCombo !== combo) openCombo.classList.remove('open');
    });
    combo.classList.toggle('open');
    const input = combo.querySelector('.ae-combo-panel input[type="search"]');
    if (combo.classList.contains('open') && input) {
        input.value = '';
        filterCombo(input);
        setTimeout(() => input.focus(), 0);
    }
}
function filterCombo(input) {
    const term = input.value.trim().toLowerCase();
    const combo = input.closest('.ae-combo');
    combo.querySelectorAll('.ae-combo-option').forEach(option => {
        option.hidden = term !== '' && !option.textContent.toLowerCase().includes(term);
    });
}
function showChildNameSuggestions(input) {
    const panel = document.getElementById('childNameSuggestions');
    const select = document.getElementById('childSelect');
    if (!panel || !select) return;

    const term = input.value.trim().toLowerCase();
    panel.innerHTML = '';
    if (term.length < 2) {
        panel.classList.remove('open');
        return;
    }

    const matches = Array.from(select.options)
        .filter(option => option.value !== '__new__' && option.text.toLowerCase().includes(term))
        .slice(0, 8);

    if (matches.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'ae-suggest-empty';
        empty.textContent = 'Nenhum item cadastrado encontrado';
        panel.appendChild(empty);
        panel.classList.add('open');
        return;
    }

    matches.forEach(option => {
        const div = document.createElement('div');
        div.className = 'ae-suggest-option';
        div.textContent = option.text;
        div.onclick = () => selectExistingChildSuggestion(option.value);
        panel.appendChild(div);
    });
    panel.classList.add('open');
}
function selectExistingChildSuggestion(value) {
    const select = document.getElementById('childSelect');
    const panel = document.getElementById('childNameSuggestions');
    if (!select) return;
    select.value = value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    if (panel) panel.classList.remove('open');
    syncCombos();
}
function toggleNewChild(select) {
    const value = select.value;
    const selectedOption = select.options[select.selectedIndex];
    document.querySelectorAll('.new-child').forEach(el => el.classList.toggle('ae-hidden', value !== '__new__'));
    const suggestions = document.getElementById('childNameSuggestions');
    if (suggestions) suggestions.classList.remove('open');
    setChildUnit(selectedOption ? selectedOption.dataset.unit : 'un');
}
function initSearchableSelects() {
    const childSearch = document.getElementById('childSearch');
    const childSelect = document.getElementById('childSelect');
    const rootTypeSearch = document.getElementById('rootTypeSearch');
    const rootTypeSelect = document.getElementById('rootTypeSelect');
    const rootUnitSearch = document.getElementById('rootUnitSearch');
    const rootUnitSelect = document.getElementById('rootUnitSelect');
    const typeSearch = document.getElementById('childTypeSearch');
    const typeSelect = document.getElementById('childTypeSelect');
    const unitSearch = document.getElementById('childUnitSearch');
    const unitSelect = document.getElementById('childUnitSelect');

    if (childSearch && childSelect) {
        childSearch.addEventListener('input', () => filterSelectOptions(childSearch, childSelect, '__new__'));
    }
    if (rootTypeSearch && rootTypeSelect) {
        rootTypeSearch.addEventListener('input', () => filterSelectOptions(rootTypeSearch, rootTypeSelect, ''));
    }
    if (rootUnitSearch && rootUnitSelect) {
        rootUnitSearch.addEventListener('input', () => filterSelectOptions(rootUnitSearch, rootUnitSelect, ''));
    }
    if (typeSearch && typeSelect) {
        typeSearch.addEventListener('input', () => filterSelectOptions(typeSearch, typeSelect, ''));
    }
    if (unitSearch && unitSelect) {
        unitSearch.addEventListener('input', () => filterSelectOptions(unitSearch, unitSelect, ''));
    }
}
function rememberScroll() {
    sessionStorage.setItem('aeScrollY', String(window.scrollY || 0));
}
function restoreScroll() {
    const saved = sessionStorage.getItem('aeScrollY');
    if (saved !== null) {
        sessionStorage.removeItem('aeScrollY');
        requestAnimationFrame(() => window.scrollTo(0, parseInt(saved, 10) || 0));
    }
}
async function confirmRemove() {
    if (confirm('Deseja desvincular esta ramificacao desta arvore? O cadastro dos itens e arvores independentes sera mantido.')) {
        const form = document.getElementById('removeForm');
        const formData = new FormData(form);
        formData.append('ajax', '1');
        try {
            const response = await fetch('arvore_estrutura.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'fetch' }
            });
            const result = await response.json();
            if (result.status !== 'success') {
                alert(result.message || 'Nao foi possivel remover o item.');
                return;
            }
            removeSelectedTreeRows();
        } catch (error) {
            rememberScroll();
            form.submit();
        }
    }
}
async function confirmDelete() {
    if (confirm('Deseja excluir esta ramificacao somente desta arvore? Itens usados em outras arvores serao preservados.')) {
        const form = document.getElementById('deleteForm');
        const formData = new FormData(form);
        formData.append('ajax', '1');
        try {
            const response = await fetch('arvore_estrutura.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'fetch' }
            });
            const result = await response.json();
            if (result.status !== 'success') {
                alert(result.message || 'Nao foi possivel excluir a ramificacao.');
                return;
            }
            removeSelectedTreeRows();
        } catch (error) {
            rememberScroll();
            form.submit();
        }
    }
}
function removeSelectedTreeRows() {
    const table = document.getElementById('treeTable');
    const selected = table ? table.querySelector('tbody tr.selected') : null;
    if (!selected) return;

    const level = parseInt(selected.dataset.level || '0', 10);
    let next = selected.nextElementSibling;
    const toRemove = [selected];
    while (next && parseInt(next.dataset.level || '0', 10) > level) {
        toRemove.push(next);
        next = next.nextElementSibling;
    }

    const fallback = selected.previousElementSibling || next;
    toRemove.forEach(row => row.remove());

    if (fallback && fallback.isConnected) {
        selectRow(fallback);
    } else {
        const first = table ? table.querySelector('tbody tr') : null;
        if (first) {
            selectRow(first);
        } else {
            const removeBtn = document.getElementById('removeActionBtn');
            const deleteBtn = document.getElementById('deleteActionBtn');
            if (removeBtn) removeBtn.disabled = true;
            if (deleteBtn) deleteBtn.disabled = true;
        }
    }
}
function toggleBranch(button) {
    const row = button.closest('tr');
    const level = parseInt(row.dataset.level || '0', 10);
    const isExpanded = button.textContent.trim() === 'v';
    setBranchExpanded(row, !isExpanded);
}
function setBranchExpanded(row, expand) {
    const level = parseInt(row.dataset.level || '0', 10);
    const button = row.querySelector('.ae-expander');
    if (button && button.textContent.trim() !== '-') {
        button.textContent = expand ? 'v' : '>';
    }

    let next = row.nextElementSibling;
    while (next && parseInt(next.dataset.level || '0', 10) > level) {
        const childLevel = parseInt(next.dataset.level || '0', 10);
        if (!expand) {
            next.style.display = 'none';
        } else if (childLevel === level + 1) {
            next.style.display = '';
            const childButton = next.querySelector('.ae-expander');
            if (childButton && childButton.textContent.trim() === 'v') {
                setBranchExpanded(next, true);
            }
        }
        next = next.nextElementSibling;
    }
}
function expandAll() {
    document.querySelectorAll('#treeTable tbody tr').forEach(row => row.style.display = '');
    document.querySelectorAll('.ae-expander').forEach(btn => { if (btn.textContent.trim() === '>') btn.textContent = 'v'; });
}
function collapseAll() {
    document.querySelectorAll('#treeTable tbody tr').forEach(row => {
        const level = parseInt(row.dataset.level || '0', 10);
        row.style.display = level <= 1 ? '' : 'none';
    });
    document.querySelectorAll('.ae-expander').forEach(btn => { if (btn.textContent.trim() === 'v') btn.textContent = '>'; });
    document.querySelectorAll('#treeTable tbody tr[data-level="0"] .ae-expander').forEach(btn => { if (btn.textContent.trim() === '>') btn.textContent = 'v'; });
}
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeModals();
});
document.addEventListener('submit', event => {
    if (event.target && event.target.id !== 'removeForm' && event.target.id !== 'deleteForm') {
        rememberScroll();
    }
});
applyPanelState();
restoreScroll();
initSearchableSelects();
syncCombos();
document.addEventListener('click', event => {
    if (!event.target.closest('.ae-combo')) {
        document.querySelectorAll('.ae-combo.open').forEach(combo => combo.classList.remove('open'));
    }
    if (!event.target.closest('.ae-suggest-wrap')) {
        document.querySelectorAll('.ae-suggest-panel.open').forEach(panel => panel.classList.remove('open'));
    }
});
</script>
</body>
</html>

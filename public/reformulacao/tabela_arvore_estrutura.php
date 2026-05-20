<?php
/**
 * Lista de arvores com detalhamento da estrutura selecionada.
 */
require_once __DIR__ . '/safe_storage.php';
require_once __DIR__ . '/module_routes.php';

function ae_tabela_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ae_tabela_lower($value) {
    $value = (string)$value;
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function ae_tabela_fmt($value, $decimals = 4) {
    if ($value === null || $value === '') {
        return '';
    }
    return number_format((float)$value, $decimals, ',', '.');
}

function ae_tabela_load() {
    return cod_12_05_safe_load_json(
        rf_route('arvore_estrutura', 'storage'),
        fn() => [
            'tabela_arvores' => [],
            'tabela_itens' => [],
            'tabela_arvore_composicao' => [],
        ],
        fn($data) => is_array($data)
    );
}

function ae_tabela_index_by_id($rows) {
    $indexed = [];
    foreach (($rows ?? []) as $row) {
        $id = (string)($row['id'] ?? '');
        if ($id !== '') {
            $indexed[$id] = $row;
        }
    }
    return $indexed;
}

function ae_tabela_children($data, $tree_id, $parent_id) {
    $children = [];
    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        if (($comp['ativo'] ?? 1)
            && (string)($comp['arvore_id'] ?? '') === (string)$tree_id
            && (string)($comp['item_pai_id'] ?? '') === (string)$parent_id) {
            $children[] = $comp;
        }
    }
    usort($children, fn($a, $b) => ((int)($a['ordem_exibicao'] ?? 0)) <=> ((int)($b['ordem_exibicao'] ?? 0)));
    return $children;
}

function ae_tabela_row_key($tree_id, $item_id, $comp = null) {
    $comp_id = is_array($comp) ? (string)($comp['id'] ?? '') : '';
    return implode('|', [(string)$tree_id, (string)$item_id, $comp_id]);
}

function ae_tabela_duplicate_warning_key($tree_id, $parent_id, $child_id) {
    return implode('|', [(string)$tree_id, (string)$parent_id, (string)$child_id]);
}

function ae_tabela_add_duplicate_warning(&$warnings, $tree_id, $parent, $child, $level) {
    if (!is_array($warnings)) {
        return;
    }
    $key = ae_tabela_duplicate_warning_key($tree_id, $parent['id'] ?? '', $child['id'] ?? '');
    if (isset($warnings[$key])) {
        return;
    }
    $warnings[$key] = [
        'tree_id' => (string)$tree_id,
        'parent_label' => trim(($parent['codigo'] ?? '') . ' - ' . ($parent['nome'] ?? '')),
        'child_label' => trim(($child['codigo'] ?? '') . ' - ' . ($child['nome'] ?? '')),
        'level' => (int)$level,
    ];
}

function ae_tabela_flatten_tree(&$rows, $data, $items_by_id, $tree_id, $item_id, $level = 0, $parent = null, $comp = null, $visited = [], $options = [], &$warnings = null) {
    $tree_id = (string)$tree_id;
    $item_id = (string)$item_id;
    $visit_key = $tree_id . ':' . $item_id;
    if ($item_id === '' || isset($visited[$visit_key]) || $level > 50) {
        return;
    }
    $item = $items_by_id[$item_id] ?? null;
    if (!$item) {
        return;
    }

    $row_key = ae_tabela_row_key($tree_id, $item_id, $comp);
    if (!isset($visited['row:' . $row_key])) {
        $rows[] = [
            'level' => $level,
            'item' => $item,
            'parent' => $parent,
            'comp' => $comp,
            'tree_id' => $tree_id,
            'row_key' => $row_key,
        ];
        $visited['row:' . $row_key] = true;
    }

    $visited[$visit_key] = true;
    $seen_siblings = [];
    foreach (ae_tabela_children($data, $tree_id, $item_id) as $child_comp) {
        $child_id = (string)($child_comp['item_filho_id'] ?? '');
        if (($options['hide_duplicate_siblings'] ?? false) && $child_id !== '') {
            if (isset($seen_siblings[$child_id])) {
                ae_tabela_add_duplicate_warning($warnings, $tree_id, $item, $items_by_id[$child_id] ?? [], $level + 1);
                continue;
            }
            $seen_siblings[$child_id] = true;
        }

        ae_tabela_flatten_tree(
            $rows,
            $data,
            $items_by_id,
            $tree_id,
            $child_id,
            $level + 1,
            $item,
            $child_comp,
            $visited,
            $options,
            $warnings
        );
    }
}

function ae_tabela_tree_rows($data, $items_by_id, $tree, $options = [], &$warnings = null) {
    $rows = [];
    $tree_id = (string)($tree['id'] ?? '');
    ae_tabela_flatten_tree($rows, $data, $items_by_id, $tree_id, (string)($tree['item_raiz_id'] ?? ''), 0, null, null, [], $options, $warnings);
    return $rows;
}

function ae_tabela_subtree_rows($data, $items_by_id, $tree_id, $item_id, $options = [], &$warnings = null) {
    $rows = [];
    ae_tabela_flatten_tree($rows, $data, $items_by_id, (string)$tree_id, (string)$item_id, 0, null, null, [], $options, $warnings);
    return $rows;
}

function ae_tabela_subtrees($data, $items_by_id, $tree, $detail_rows, $exclude_root_id = '', $options = []) {
    $tree_id = (string)($tree['id'] ?? '');
    $root_id = $exclude_root_id !== '' ? (string)$exclude_root_id : (string)($tree['item_raiz_id'] ?? '');
    $subtrees = [];
    $used_items = [];

    foreach ($detail_rows as $row) {
        $item = $row['item'] ?? [];
        $item_id = (string)($item['id'] ?? '');
        if ($item_id === '' || $item_id === $root_id || isset($used_items[$item_id])) {
            continue;
        }

        if (empty(ae_tabela_children($data, $tree_id, $item_id))) {
            continue;
        }

        $subtree_warnings = [];
        $rows = ae_tabela_subtree_rows($data, $items_by_id, $tree_id, $item_id, $options, $subtree_warnings);
        if (count($rows) <= 1) {
            continue;
        }

        $used_items[$item_id] = true;
        $subtrees[] = [
            'root' => $item,
            'rows' => $rows,
            'count' => count($rows),
        ];
    }

    return $subtrees;
}

function ae_tabela_leaf_totals(&$totals, $data, $items_by_id, $tree_id, $item_id, $total_quantidade = 1, $unidade = '', $level = 0, $visited = []) {
    $tree_id = (string)$tree_id;
    $item_id = (string)$item_id;
    $visit_key = $tree_id . ':' . $item_id;
    if ($item_id === '' || isset($visited[$visit_key]) || $level > 50) {
        return;
    }
    $item = $items_by_id[$item_id] ?? null;
    if (!$item) {
        return;
    }

    $visited[$visit_key] = true;
    $children = ae_tabela_children($data, $tree_id, $item_id);
    if (empty($children)) {
        if (!isset($totals[$item_id])) {
            $totals[$item_id] = [
                'item' => $item,
                'quantidade_total' => 0,
                'unidade' => $unidade !== '' ? $unidade : ($item['unidade_base'] ?? ''),
                'nivel_relativo' => $level,
            ];
        }
        $totals[$item_id]['quantidade_total'] += $total_quantidade;
        $totals[$item_id]['nivel_relativo'] = max((int)$totals[$item_id]['nivel_relativo'], $level);
        return;
    }

    foreach ($children as $child_comp) {
        $child_id = (string)($child_comp['item_filho_id'] ?? '');
        $child = $items_by_id[$child_id] ?? [];
        $child_qty = max(0.000001, (float)($child_comp['quantidade'] ?? 1));
        ae_tabela_leaf_totals(
            $totals,
            $data,
            $items_by_id,
            $tree_id,
            $child_id,
            $total_quantidade * $child_qty,
            $child_comp['unidade'] ?? ($child['unidade_base'] ?? ''),
            $level + 1,
            $visited
        );
    }
}

function ae_tabela_tree_leaf_totals($data, $items_by_id, $tree) {
    $totals = [];
    $tree_id = (string)($tree['id'] ?? '');
    ae_tabela_leaf_totals($totals, $data, $items_by_id, $tree_id, (string)($tree['item_raiz_id'] ?? ''));
    uasort($totals, function ($a, $b) {
        return strnatcasecmp(
            (string)(($a['item']['codigo'] ?? '') . ' ' . ($a['item']['nome'] ?? '')),
            (string)(($b['item']['codigo'] ?? '') . ' ' . ($b['item']['nome'] ?? ''))
        );
    });
    return array_values($totals);
}

function ae_tabela_active_items($items_by_id) {
    $items = array_values(array_filter($items_by_id, fn($item) => (int)($item['ativo'] ?? 1) === 1));
    usort($items, fn($a, $b) => strnatcasecmp(
        (string)(($a['codigo'] ?? '') . ' ' . ($a['nome'] ?? '')),
        (string)(($b['codigo'] ?? '') . ' ' . ($b['nome'] ?? ''))
    ));
    return $items;
}

function ae_tabela_item_tree_usages($data, $items_by_id, $trees, $status, $busca = '', $hide_duplicate_siblings = true) {
    $usages = [];
    $tree_index = ae_tabela_index_by_id($trees);

    foreach ($trees as $tree) {
        $active = (int)($tree['ativo'] ?? 1) === 1;
        if ($status === 'ativas' && !$active) {
            continue;
        }
        if ($status === 'arquivadas' && $active) {
            continue;
        }

        $tree_id = (string)($tree['id'] ?? '');
        $root_id = (string)($tree['item_raiz_id'] ?? '');
        if ($tree_id === '') {
            continue;
        }

        if ($root_id !== '' && isset($items_by_id[$root_id])) {
            $usages[$root_id][$tree_id] = [
                'tree_id' => $tree_id,
                'label' => trim(($tree['codigo'] ?? '') . ' - ' . ($tree['nome'] ?? '')),
                'root' => trim(($items_by_id[$root_id]['codigo'] ?? '') . ' - ' . ($items_by_id[$root_id]['nome'] ?? '')),
                'roles' => ['Raiz'],
                'active' => $active,
                'href' => 'tabela_arvore_estrutura.php?' . http_build_query(array_filter([
                    'arvore_id' => $tree_id,
                    'status' => $status,
                    'busca' => $busca,
                    'ocultar_irmaos_duplicados' => $hide_duplicate_siblings ? '' : '0',
                ], fn($value) => $value !== '')),
            ];
        }
    }

    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        if (!($comp['ativo'] ?? 1)) {
            continue;
        }

        $tree_id = (string)($comp['arvore_id'] ?? '');
        $tree = $tree_index[$tree_id] ?? null;
        if (!$tree) {
            continue;
        }

        $active = (int)($tree['ativo'] ?? 1) === 1;
        if ($status === 'ativas' && !$active) {
            continue;
        }
        if ($status === 'arquivadas' && $active) {
            continue;
        }

        foreach ([
            (string)($comp['item_pai_id'] ?? '') => 'Pai',
            (string)($comp['item_filho_id'] ?? '') => 'Filho',
        ] as $item_id => $role) {
            if ($item_id === '' || !isset($items_by_id[$item_id])) {
                continue;
            }

            if (!isset($usages[$item_id][$tree_id])) {
                $root = $items_by_id[(string)($tree['item_raiz_id'] ?? '')] ?? [];
                $usages[$item_id][$tree_id] = [
                    'tree_id' => $tree_id,
                    'label' => trim(($tree['codigo'] ?? '') . ' - ' . ($tree['nome'] ?? '')),
                    'root' => trim(($root['codigo'] ?? '') . ' - ' . ($root['nome'] ?? '')),
                    'roles' => [],
                    'active' => $active,
                    'href' => 'tabela_arvore_estrutura.php?' . http_build_query(array_filter([
                        'arvore_id' => $tree_id,
                        'status' => $status,
                        'busca' => $busca,
                        'ocultar_irmaos_duplicados' => $hide_duplicate_siblings ? '' : '0',
                    ], fn($value) => $value !== '')),
                ];
            }

            if (!in_array($role, $usages[$item_id][$tree_id]['roles'], true)) {
                $usages[$item_id][$tree_id]['roles'][] = $role;
            }
        }
    }

    foreach ($usages as $item_id => $rows) {
        uasort($rows, fn($a, $b) => strnatcasecmp((string)$a['label'], (string)$b['label']));
        $usages[$item_id] = array_values($rows);
    }

    return $usages;
}

function ae_tabela_tree_search_text($tree, $root) {
    return ae_tabela_lower(implode(' ', [
        $tree['codigo'] ?? '',
        $tree['nome'] ?? '',
        $root['codigo'] ?? '',
        $root['nome'] ?? '',
    ]));
}

function ae_tabela_subtree_search_text($tree, $root, $subtree_root) {
    return ae_tabela_lower(implode(' ', [
        $tree['codigo'] ?? '',
        $tree['nome'] ?? '',
        $root['codigo'] ?? '',
        $root['nome'] ?? '',
        $subtree_root['codigo'] ?? '',
        $subtree_root['nome'] ?? '',
    ]));
}

function ae_tabela_rows_have_item($rows, $item_id) {
    $item_id = (string)$item_id;
    if ($item_id === '') {
        return true;
    }
    foreach ($rows as $row) {
        if ((string)($row['item']['id'] ?? '') === $item_id) {
            return true;
        }
    }
    return false;
}

function ae_tabela_nav_entries($data, $items_by_id, $trees, $status, $busca = '', $options = [], $filter_item_id = '') {
    $entries = [];
    $needle = ae_tabela_lower(trim($busca));
    $filter_item_id = (string)$filter_item_id;

    foreach ($trees as $tree) {
        $active = (int)($tree['ativo'] ?? 1) === 1;
        if ($status === 'ativas' && !$active) {
            continue;
        }
        if ($status === 'arquivadas' && $active) {
            continue;
        }

        $tree_id = (string)($tree['id'] ?? '');
        if ($tree_id === '') {
            continue;
        }

        $root = $items_by_id[(string)($tree['item_raiz_id'] ?? '')] ?? [];
        $nav_warnings = [];
        $tree_rows = ae_tabela_tree_rows($data, $items_by_id, $tree, $options, $nav_warnings);
        $subtrees = ae_tabela_subtrees($data, $items_by_id, $tree, $tree_rows, '', $options);
        $tree_search = ae_tabela_tree_search_text($tree, $root);
        $tree_matches = $needle === '' || str_contains($tree_search, $needle);
        $tree_has_item = ae_tabela_rows_have_item($tree_rows, $filter_item_id);

        if ($tree_matches && $tree_has_item) {
            $entries[] = [
                'type' => 'tree',
                'tree' => $tree,
                'root' => $root,
                'rows_count' => count($tree_rows),
                'subtrees_count' => count($subtrees),
                'search' => $tree_search,
            ];
        }

        foreach ($subtrees as $subtree) {
            $subtree_root = $subtree['root'] ?? [];
            $subtree_search = ae_tabela_subtree_search_text($tree, $root, $subtree_root);
            $subtree_has_item = ae_tabela_rows_have_item($subtree['rows'] ?? [], $filter_item_id);
            if (!$subtree_has_item) {
                continue;
            }
            if ($needle !== '' && !$tree_matches && !str_contains($subtree_search, $needle)) {
                continue;
            }

            $entries[] = [
                'type' => 'subtree',
                'tree' => $tree,
                'root' => $root,
                'subtree' => $subtree,
                'rows_count' => (int)($subtree['count'] ?? 0),
                'search' => $subtree_search,
            ];
        }
    }

    return $entries;
}

$data = ae_tabela_load();
$items_by_id = ae_tabela_index_by_id($data['tabela_itens'] ?? []);
$trees = array_values($data['tabela_arvores'] ?? []);
usort($trees, fn($a, $b) => strcmp((string)($a['codigo'] ?? ''), (string)($b['codigo'] ?? '')));

$busca = trim($_GET['busca'] ?? '');
$status = $_GET['status'] ?? 'ativas';
$filter_item_id = trim($_GET['item_id'] ?? '');
$filter_item = $filter_item_id !== '' ? ($items_by_id[$filter_item_id] ?? null) : null;
if (!$filter_item) {
    $filter_item_id = '';
}
$hide_duplicate_siblings = ($_GET['ocultar_irmaos_duplicados'] ?? '1') !== '0';
$view_options = ['hide_duplicate_siblings' => $hide_duplicate_siblings];

$nav_entries = ae_tabela_nav_entries($data, $items_by_id, $trees, $status, $busca, $view_options, $filter_item_id);
$nav_total_entries = count(ae_tabela_nav_entries($data, $items_by_id, $trees, $status, '', $view_options, $filter_item_id));

$selected_tree_id = trim($_GET['arvore_id'] ?? '');
$selected_subtree_item_id = trim($_GET['subtree_item_id'] ?? '');
$selected_entry_found = false;
foreach ($nav_entries as $entry) {
    $entry_tree_id = (string)($entry['tree']['id'] ?? '');
    $entry_subtree_item_id = (string)($entry['subtree']['root']['id'] ?? '');
    if ($entry_tree_id === $selected_tree_id && ($selected_subtree_item_id === '' || $selected_subtree_item_id === $entry_subtree_item_id)) {
        $selected_entry_found = true;
        break;
    }
}
if (!$selected_entry_found) {
    $selected_tree_id = (string)($nav_entries[0]['tree']['id'] ?? '');
    $selected_subtree_item_id = (string)($nav_entries[0]['subtree']['root']['id'] ?? '');
}

$selected_tree = null;
foreach ($trees as $tree) {
    if ((string)($tree['id'] ?? '') === $selected_tree_id) {
        $selected_tree = $tree;
        break;
    }
}

$selected_root = $selected_tree ? ($items_by_id[(string)($selected_tree['item_raiz_id'] ?? '')] ?? null) : null;
$selected_subtree_root = $selected_subtree_item_id !== '' ? ($items_by_id[$selected_subtree_item_id] ?? null) : null;
$duplicate_warnings = [];
if ($selected_tree && $selected_subtree_root && !empty(ae_tabela_children($data, $selected_tree_id, $selected_subtree_item_id))) {
    $detail_rows = ae_tabela_subtree_rows($data, $items_by_id, $selected_tree_id, $selected_subtree_item_id, $view_options, $duplicate_warnings);
} else {
    $selected_subtree_item_id = '';
    $selected_subtree_root = null;
    $detail_rows = $selected_tree ? ae_tabela_tree_rows($data, $items_by_id, $selected_tree, $view_options, $duplicate_warnings) : [];
}
$subtrees = $selected_tree ? ae_tabela_subtrees($data, $items_by_id, $selected_tree, $detail_rows, $selected_subtree_item_id, $view_options) : [];
if ($selected_tree && $selected_subtree_item_id !== '') {
    $leaf_totals = [];
    ae_tabela_leaf_totals($leaf_totals, $data, $items_by_id, $selected_tree_id, $selected_subtree_item_id);
    $leaf_totals = array_values($leaf_totals);
} else {
    $leaf_totals = $selected_tree ? ae_tabela_tree_leaf_totals($data, $items_by_id, $selected_tree) : [];
}
$search_items = ae_tabela_active_items($items_by_id);
$item_tree_usages = ae_tabela_item_tree_usages($data, $items_by_id, $trees, $status, $busca, $hide_duplicate_siblings);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arvores de Estrutura</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { background: #f4f7fb; color: #0f1f3a; }
        .content { padding: 20px 24px 32px; }
        .ae-page-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 16px; }
        .ae-title { color: #0068ff; font-size: 24px; font-weight: 750; margin: 0; border: 0; padding: 0; }
        .ae-subtitle { color: #52627a; font-size: 13px; margin-top: 4px; }
        .ae-btn { display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 13px; border-radius: 6px; border: 1px solid #cfd8e6; background: #fff; color: #11254a; text-decoration: none; font-size: 13px; font-weight: 700; cursor: pointer; }
        .ae-btn.primary { background: #006eff; border-color: #006eff; color: #fff; }
        .ae-layout { display: grid; grid-template-columns: minmax(360px, 440px) minmax(620px, 1fr); gap: 16px; align-items: start; }
        .ae-card { background: #fff; border: 1px solid #dce5f1; border-radius: 8px; box-shadow: 0 10px 28px rgba(15,31,58,.045); overflow: hidden; }
        .ae-card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px; border-bottom: 1px solid #e5ebf4; background: #fbfdff; }
        .ae-card-title { margin: 0; font-size: 15px; color: #0f1f3a; font-weight: 800; }
        .ae-count { color: #52627a; font-size: 12px; font-weight: 800; white-space: nowrap; }
        .ae-filters { display: grid; grid-template-columns: 1fr 135px auto; gap: 10px; padding: 14px 18px; border-bottom: 1px solid #e5ebf4; }
        .ae-field label { display: block; color: #465873; font-size: 12px; font-weight: 700; margin-bottom: 5px; }
        .ae-field input, .ae-field select { width: 100%; height: 38px; border: 1px solid #cfd8e6; border-radius: 6px; color: #122243; background: #fff; padding: 0 10px; }
        .ae-check-field { grid-column: 1 / -1; display: inline-flex; align-items: center; gap: 8px; color: #465873; font-size: 12px; font-weight: 800; }
        .ae-check-field input { width: 16px; height: 16px; accent-color: #006eff; }
        .ae-item-finder { padding: 14px 18px; border-bottom: 1px solid #e5ebf4; background: #fff; }
        .ae-combo-search { position: relative; }
        .ae-dropdown { position: absolute; z-index: 20; left: 0; right: 0; top: calc(100% + 5px); max-height: 230px; overflow: auto; border: 1px solid #cfd8e6; border-radius: 8px; background: #fff; box-shadow: 0 16px 34px rgba(15,31,58,.14); display: none; }
        .ae-dropdown.open { display: block; }
        .ae-dropdown-option { width: 100%; display: block; border: 0; border-bottom: 1px solid #eef2f7; background: #fff; color: #102348; padding: 9px 10px; text-align: left; cursor: pointer; }
        .ae-dropdown-option:hover { background: #eef6ff; }
        .ae-dropdown-option strong { display: block; font-size: 12px; }
        .ae-dropdown-option span { display: block; margin-top: 2px; color: #64748b; font-size: 11px; }
        .ae-item-results { display: none; margin-top: 10px; border: 1px solid #dbe5f2; border-radius: 8px; overflow: hidden; }
        .ae-item-results.open { display: block; }
        .ae-item-result-head { padding: 9px 11px; background: #f8fbff; color: #17264a; font-size: 12px; font-weight: 850; border-bottom: 1px solid #e6edf7; }
        .ae-item-result-link { display: block; padding: 9px 11px; color: #102348; text-decoration: none; border-bottom: 1px solid #eef2f7; }
        .ae-item-result-link:hover { background: #eef6ff; }
        .ae-item-result-link strong { display: block; font-size: 12px; }
        .ae-item-result-link span { display: block; margin-top: 2px; color: #64748b; font-size: 11px; }
        .ae-item-result-empty { padding: 10px 11px; color: #64748b; font-size: 12px; }
        .ae-tree-list { max-height: calc(100vh - 260px); overflow: auto; }
        .ae-tree-row { display: grid; grid-template-columns: 1fr auto; gap: 10px; padding: 13px 16px; border-bottom: 1px solid #e8eef6; color: inherit; text-decoration: none; }
        .ae-tree-row:hover { background: #eef6ff; }
        .ae-tree-row.active { background: #e8f2ff; box-shadow: inset 3px 0 0 #006eff; }
        .ae-tree-row.subtree { padding-left: 28px; background: #fbfdff; }
        .ae-tree-row.subtree.active { background: #e8f2ff; }
        .ae-tree-name { font-weight: 800; color: #102348; margin-bottom: 3px; }
        .ae-tree-name .kind { display: inline-flex; margin-right: 6px; padding: 2px 6px; border-radius: 999px; border: 1px solid #bfdbfe; background: #eff6ff; color: #1d4ed8; font-size: 10px; font-weight: 900; vertical-align: middle; }
        .ae-tree-meta { color: #64748b; font-size: 12px; line-height: 1.45; }
        .ae-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 26px; height: 24px; padding: 0 8px; border-radius: 999px; font-size: 12px; background: #dcfce7; color: #15803d; border: 1px solid #86efac; font-weight: 800; }
        .ae-pill.off { background: #f1f5f9; color: #64748b; border-color: #cbd5e1; }
        .ae-detail-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; padding: 18px; border-bottom: 1px solid #e5ebf4; }
        .ae-detail-title { margin: 0 0 4px; font-size: 20px; color: #0f1f3a; }
        .ae-detail-meta { display: flex; flex-wrap: wrap; gap: 8px 14px; color: #52627a; font-size: 13px; }
        .ae-detail-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .ae-detail-actions form { margin: 0; }
        .ae-warning { margin: 14px 18px; padding: 11px 13px; border: 1px solid #fde68a; border-radius: 8px; background: #fffbeb; color: #92400e; font-size: 12px; line-height: 1.45; }
        .ae-warning strong { color: #78350f; }
        .ae-warning ul { margin: 6px 0 0; padding-left: 18px; }
        .ae-summary { display: grid; grid-template-columns: repeat(4, minmax(130px, 1fr)); gap: 12px; padding: 14px 18px; border-bottom: 1px solid #e5ebf4; background: #fbfdff; }
        .ae-summary div { color: #64748b; font-size: 12px; }
        .ae-summary strong { display: block; color: #102348; font-size: 15px; margin-top: 3px; }
        .ae-section-title { margin: 18px 18px 10px; color: #102348; font-size: 15px; font-weight: 850; }
        .ae-table-wrap { overflow: auto; max-height: calc(100vh - 360px); }
        .ae-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .ae-table th { position: sticky; top: 0; z-index: 2; color: #52627a; background: #fff; border-bottom: 1px solid #dce5f1; padding: 11px 10px; white-space: nowrap; font-weight: 800; font-size: 11px; text-transform: uppercase; text-align: left; }
        .ae-table td { border-bottom: 1px solid #e8eef6; padding: 10px; white-space: nowrap; vertical-align: middle; }
        .ae-table tbody tr:hover { background: #eef6ff; }
        .ae-level { font-weight: 800; color: #0068ff; }
        .ae-item-cell { padding-left: calc(10px + (var(--level, 0) * 18px)) !important; }
        .ae-num { text-align: right; font-variant-numeric: tabular-nums; }
        .ae-subtrees { display: grid; gap: 12px; margin: 16px 18px 0; }
        .ae-subtree { border: 1px solid #dbe5f2; border-radius: 8px; overflow: hidden; background: #fff; }
        .ae-subtree-head { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 11px 13px; background: #f8fbff; border: 0; border-bottom: 1px solid #e6edf7; color: #17264a; text-align: left; cursor: pointer; }
        .ae-subtree-head:hover { background: #eef6ff; }
        .ae-subtree-head strong { font-size: 13px; }
        .ae-subtree-head span { color: #64748b; font-size: 12px; font-weight: 800; white-space: nowrap; }
        .ae-subtree-head .chevron { color: #0068ff; font-size: 14px; font-weight: 900; }
        .ae-subtree.collapsed .ae-subtree-body { display: none; }
        .ae-subtree .ae-table-wrap { max-height: none; }
        .ae-subtree .ae-table th { top: 0; }
        .ae-leaf-totals { margin: 16px 0 0; border: 1px solid #dbe5f2; border-radius: 8px; overflow: hidden; background: #fff; }
        .ae-leaf-totals h3 { margin: 0; padding: 12px 14px; font-size: 14px; color: #17264a; background: #f8fbff; border-bottom: 1px solid #e6edf7; }
        .ae-leaf-totals table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .ae-leaf-totals th, .ae-leaf-totals td { padding: 10px 12px; border-bottom: 1px solid #edf2f7; text-align: left; vertical-align: top; }
        .ae-leaf-totals th { color: #52627a; font-weight: 800; background: #fbfdff; }
        .ae-leaf-totals tr:last-child td { border-bottom: 0; }
        .ae-leaf-totals .qty { text-align: right; font-weight: 800; color: #0f1f3a; white-space: nowrap; }
        .ae-empty { min-height: 240px; display: grid; place-items: center; color: #52627a; padding: 24px; text-align: center; }
        @media (max-width: 1200px) {
            .ae-layout { grid-template-columns: 1fr; }
            .ae-tree-list, .ae-table-wrap { max-height: none; }
        }
        @media (max-width: 760px) {
            .ae-page-head, .ae-detail-head { flex-direction: column; align-items: stretch; }
            .ae-filters, .ae-summary { grid-template-columns: 1fr; }
            .ae-filters .ae-btn { width: 100%; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/menu.php'; ?>

<main class="content">
    <div class="ae-page-head">
        <div>
            <h1 class="ae-title">Arvores de Estrutura</h1>
            <div class="ae-subtitle">Lista de arvores cadastradas com detalhamento da estrutura selecionada.</div>
        </div>
        <a class="ae-btn" href="<?php echo rf_route('arvore_estrutura', 'api'); ?>?acao=<?php echo rf_route('arvore_estrutura', 'read_action'); ?>" target="_blank" rel="noopener">Ver JSON</a>
    </div>

    <div class="ae-layout">
        <section class="ae-card">
            <div class="ae-card-head">
                <h2 class="ae-card-title">Arvores</h2>
                <div class="ae-count"><span id="visibleTreeCount"><?php echo count($nav_entries); ?></span> de <?php echo $nav_total_entries; ?></div>
            </div>
            <form class="ae-filters" method="get">
                <div class="ae-field">
                    <label for="busca">Pesquisar arvore/subarvore</label>
                    <input id="busca" name="busca" value="<?php echo ae_tabela_h($busca); ?>" placeholder="Digite o nome da arvore ou subarvore" autocomplete="off">
                </div>
                <div class="ae-field">
                    <label for="status">Status</label>
                    <select id="status" name="status" onchange="this.form.submit()">
                        <option value="ativas" <?php echo $status === 'ativas' ? 'selected' : ''; ?>>Ativas</option>
                        <option value="todas" <?php echo $status === 'todas' ? 'selected' : ''; ?>>Todas</option>
                        <option value="arquivadas" <?php echo $status === 'arquivadas' ? 'selected' : ''; ?>>Arquivadas</option>
                    </select>
                </div>
                <a class="ae-btn" href="tabela_arvore_estrutura.php">Limpar</a>
                <label class="ae-check-field">
                    <input type="hidden" name="ocultar_irmaos_duplicados" value="0">
                    <input type="checkbox" name="ocultar_irmaos_duplicados" value="1" <?php echo $hide_duplicate_siblings ? 'checked' : ''; ?> onchange="this.form.submit()">
                    Nao listar o mesmo item duas vezes como irmao
                </label>
            </form>

            <div class="ae-item-finder">
                <div class="ae-field ae-combo-search">
                    <label for="itemSearch">Buscar item nas arvores</label>
                    <input id="itemSearch" type="search" placeholder="Digite codigo ou descricao do item" autocomplete="off" value="<?php echo ae_tabela_h($filter_item ? trim(($filter_item['codigo'] ?? '') . ' - ' . ($filter_item['nome'] ?? '')) : ''); ?>">
                    <div class="ae-dropdown" id="itemDropdown"></div>
                </div>
                <?php if ($filter_item): ?>
                    <div class="ae-item-results open">
                        <div class="ae-item-result-head"><?php echo count($nav_entries); ?> arvore/subarvore com este item exato</div>
                        <a class="ae-item-result-link" href="tabela_arvore_estrutura.php?<?php echo http_build_query(array_filter(['busca' => $busca, 'status' => $status, 'ocultar_irmaos_duplicados' => $hide_duplicate_siblings ? '' : '0'], fn($value) => $value !== '')); ?>"><strong>Limpar filtro de item</strong><span><?php echo ae_tabela_h(trim(($filter_item['codigo'] ?? '') . ' - ' . ($filter_item['nome'] ?? ''))); ?></span></a>
                    </div>
                <?php endif; ?>
                <div class="ae-item-results" id="itemTreeResults"></div>
            </div>

            <div class="ae-tree-list" id="treeList">
                <?php if (empty($nav_entries)): ?>
                    <div class="ae-empty">Nenhuma arvore ou subarvore encontrada.</div>
                <?php else: ?>
                    <?php foreach ($nav_entries as $entry): ?>
                        <?php
                            $tree_option = $entry['tree'] ?? [];
                            $root = $entry['root'] ?? [];
                            $is_subtree_entry = ($entry['type'] ?? '') === 'subtree';
                            $subtree_root = $entry['subtree']['root'] ?? [];
                            $active = (int)($tree_option['ativo'] ?? 1) === 1;
                            $label = trim(($tree_option['codigo'] ?? '') . ' - ' . ($tree_option['nome'] ?? ''));
                            $query = ['arvore_id' => $tree_option['id'] ?? '', 'status' => $status];
                            if ($is_subtree_entry) $query['subtree_item_id'] = $subtree_root['id'] ?? '';
                            if ($busca !== '') $query['busca'] = $busca;
                            if ($filter_item_id !== '') $query['item_id'] = $filter_item_id;
                            if (!$hide_duplicate_siblings) $query['ocultar_irmaos_duplicados'] = '0';
                            $is_active_entry = (string)($tree_option['id'] ?? '') === $selected_tree_id
                                && ($is_subtree_entry
                                    ? (string)($subtree_root['id'] ?? '') === $selected_subtree_item_id
                                    : $selected_subtree_item_id === '');
                            $entry_label = $is_subtree_entry
                                ? trim(($subtree_root['codigo'] ?? '') . ' - ' . ($subtree_root['nome'] ?? ''))
                                : $label;
                        ?>
                        <a class="ae-tree-row <?php echo $is_subtree_entry ? 'subtree ' : ''; ?><?php echo $is_active_entry ? 'active' : ''; ?>" href="tabela_arvore_estrutura.php?<?php echo http_build_query($query); ?>" data-search="<?php echo ae_tabela_h($entry['search'] ?? ''); ?>">
                            <div>
                                <div class="ae-tree-name">
                                    <?php if ($is_subtree_entry): ?><span class="kind">SUB</span><?php endif; ?>
                                    <?php echo ae_tabela_h($entry_label !== ' - ' ? $entry_label : 'Arvore sem nome'); ?>
                                </div>
                                <div class="ae-tree-meta">
                                    <?php if ($is_subtree_entry): ?>
                                        Em: <?php echo ae_tabela_h($label ?: 'Arvore sem nome'); ?><br>
                                    <?php else: ?>
                                        Raiz: <?php echo ae_tabela_h(trim(($root['codigo'] ?? '') . ' - ' . ($root['nome'] ?? '')) ?: 'Nao definida'); ?><br>
                                    <?php endif; ?>
                                    <?php echo (int)($entry['rows_count'] ?? 0); ?> itens na estrutura
                                </div>
                            </div>
                            <span class="ae-pill <?php echo $active ? '' : 'off'; ?>"><?php echo $active ? 'Ativa' : 'Arquivada'; ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="ae-card">
            <?php if (!$selected_tree): ?>
                <div class="ae-empty">Selecione uma arvore para ver o detalhamento.</div>
            <?php else: ?>
                <?php $selected_active = (int)($selected_tree['ativo'] ?? 1) === 1; ?>
                <div class="ae-detail-head">
                    <div>
                        <h2 class="ae-detail-title">
                            <?php if ($selected_subtree_root): ?>
                                Subarvore: <?php echo ae_tabela_h(($selected_subtree_root['codigo'] ?? '') . ' - ' . ($selected_subtree_root['nome'] ?? '')); ?>
                            <?php else: ?>
                                <?php echo ae_tabela_h(($selected_tree['codigo'] ?? '') . ' - ' . ($selected_tree['nome'] ?? '')); ?>
                            <?php endif; ?>
                        </h2>
                        <div class="ae-detail-meta">
                            <?php if ($selected_subtree_root): ?>
                                <span>Arvore: <?php echo ae_tabela_h(trim(($selected_tree['codigo'] ?? '') . ' - ' . ($selected_tree['nome'] ?? '')) ?: 'Nao definida'); ?></span>
                                <span>Raiz da subarvore: <?php echo ae_tabela_h(trim(($selected_subtree_root['codigo'] ?? '') . ' - ' . ($selected_subtree_root['nome'] ?? '')) ?: 'Nao definida'); ?></span>
                            <?php else: ?>
                                <span>Raiz: <?php echo ae_tabela_h(trim(($selected_root['codigo'] ?? '') . ' - ' . ($selected_root['nome'] ?? '')) ?: 'Nao definida'); ?></span>
                            <?php endif; ?>
                            <span>Status: <?php echo $selected_active ? 'Ativa' : 'Arquivada'; ?></span>
                            <span>Atualizada em: <?php echo ae_tabela_h($selected_tree['atualizado_em'] ?? ''); ?></span>
                        </div>
                    </div>
                    <div class="ae-detail-actions">
                        <form method="post" action="<?php echo rf_route('arvore_estrutura', 'page'); ?>" onsubmit="return confirm('Criar uma nova arvore editavel a partir desta estrutura?');">
                            <input type="hidden" name="action" value="duplicate_tree">
                            <input type="hidden" name="arvore_id" value="<?php echo ae_tabela_h($selected_tree_id); ?>">
                            <button class="ae-btn" type="submit">Editar como Nova Arvore</button>
                        </form>
                        <a class="ae-btn primary" href="<?php echo rf_route('arvore_estrutura', 'page'); ?>?<?php echo http_build_query(['arvore_id' => $selected_tree_id, 'selected_item_id' => $selected_subtree_item_id !== '' ? $selected_subtree_item_id : ($selected_tree['item_raiz_id'] ?? '')]); ?>">Editar Arvore</a>
                    </div>
                </div>

                <div class="ae-summary">
                    <div>Codigo<strong><?php echo ae_tabela_h($selected_tree['codigo'] ?? ''); ?></strong></div>
                    <div><?php echo $selected_subtree_root ? 'Subarvore' : 'Nome'; ?><strong><?php echo ae_tabela_h($selected_subtree_root ? ($selected_subtree_root['nome'] ?? '') : ($selected_tree['nome'] ?? '')); ?></strong></div>
                    <div>Total de itens<strong><?php echo count($detail_rows); ?></strong></div>
                    <div>Subarvores<strong><?php echo count($subtrees); ?></strong></div>
                </div>

                <?php if ($hide_duplicate_siblings && !empty($duplicate_warnings)): ?>
                    <div class="ae-warning">
                        <strong>Itens irmaos duplicados ocultados.</strong>
                        O mesmo item continua permitido em outro ramo ou nivel, como tio e sobrinho.
                        <ul>
                            <?php foreach (array_slice(array_values($duplicate_warnings), 0, 8) as $warning): ?>
                                <li><?php echo ae_tabela_h($warning['child_label'] ?: 'Item sem nome'); ?> repetido sob <?php echo ae_tabela_h($warning['parent_label'] ?: 'pai nao identificado'); ?>.</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (empty($detail_rows)): ?>
                    <div class="ae-empty">Esta arvore ainda nao tem itens vinculados.</div>
                <?php else: ?>
                    <div class="ae-table-wrap">
                        <table class="ae-table">
                            <thead>
                                <tr>
                                    <th>Nivel</th>
                                    <th>Codigo</th>
                                    <th>Item</th>
                                    <th>Unidade</th>
                                    <th class="ae-num">Quantidade</th>
                                    <th class="ae-num">Fator</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detail_rows as $row): ?>
                                    <?php
                                        $item = $row['item'];
                                        $parent = $row['parent'] ?? [];
                                        $comp = $row['comp'] ?? [];
                                        $level = (int)($row['level'] ?? 0);
                                    ?>
                                    <tr>
                                        <td class="ae-level"><?php echo $level; ?></td>
                                        <td><?php echo ae_tabela_h($item['codigo'] ?? ''); ?></td>
                                        <td class="ae-item-cell" style="--level:<?php echo $level; ?>"><?php echo ae_tabela_h($item['nome'] ?? ''); ?></td>
                                        <td><?php echo ae_tabela_h($comp['unidade'] ?? ($item['unidade_base'] ?? '')); ?></td>
                                        <td class="ae-num"><?php echo $level === 0 ? '-' : ae_tabela_fmt($comp['quantidade'] ?? 1); ?></td>
                                        <td class="ae-num"><?php echo $level === 0 ? '-' : ae_tabela_fmt($comp['fator_conversao'] ?? 1); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($leaf_totals)): ?>
                        <div class="ae-leaf-totals">
                            <h3>Quantidades totais no menor nivel</h3>
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
                                    <?php foreach ($leaf_totals as $leaf_total): ?>
                                        <?php $leaf_item = $leaf_total['item'] ?? []; ?>
                                        <tr>
                                            <td><?php echo ae_tabela_h($leaf_item['codigo'] ?? ''); ?></td>
                                            <td><?php echo ae_tabela_h($leaf_item['nome'] ?? ''); ?></td>
                                            <td class="qty"><?php echo ae_tabela_fmt($leaf_total['quantidade_total'] ?? 0, 2); ?></td>
                                            <td><?php echo ae_tabela_h($leaf_total['unidade'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($subtrees)): ?>
                        <h3 class="ae-section-title">Subarvores da estrutura</h3>
                        <div class="ae-subtrees">
                            <?php foreach ($subtrees as $idx => $subtree): ?>
                                <?php $subtree_root = $subtree['root'] ?? []; ?>
                                <section class="ae-subtree collapsed">
                                    <button class="ae-subtree-head" type="button" onclick="toggleSubtree(this)" aria-expanded="false">
                                        <strong><?php echo ae_tabela_h(trim(($subtree_root['codigo'] ?? '') . ' - ' . ($subtree_root['nome'] ?? ''))); ?></strong>
                                        <span><span class="chevron">&gt;</span> <?php echo (int)($subtree['count'] ?? 0); ?> itens</span>
                                    </button>
                                    <div class="ae-table-wrap ae-subtree-body">
                                        <table class="ae-table">
                                            <thead>
                                                <tr>
                                                    <th>Nivel</th>
                                                    <th>Codigo</th>
                                                    <th>Item</th>
                                                    <th>Unidade</th>
                                                    <th class="ae-num">Quantidade</th>
                                                    <th class="ae-num">Fator</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (($subtree['rows'] ?? []) as $subtree_row): ?>
                                                    <?php
                                                        $subtree_item = $subtree_row['item'] ?? [];
                                                        $subtree_parent = $subtree_row['parent'] ?? [];
                                                        $subtree_comp = $subtree_row['comp'] ?? [];
                                                        $subtree_level = (int)($subtree_row['level'] ?? 0);
                                                    ?>
                                                    <tr>
                                                        <td class="ae-level"><?php echo $subtree_level; ?></td>
                                                        <td><?php echo ae_tabela_h($subtree_item['codigo'] ?? ''); ?></td>
                                                        <td class="ae-item-cell" style="--level:<?php echo $subtree_level; ?>"><?php echo ae_tabela_h($subtree_item['nome'] ?? ''); ?></td>
                                                        <td><?php echo ae_tabela_h($subtree_comp['unidade'] ?? ($subtree_item['unidade_base'] ?? '')); ?></td>
                                                        <td class="ae-num"><?php echo $subtree_level === 0 ? '-' : ae_tabela_fmt($subtree_comp['quantidade'] ?? 1); ?></td>
                                                        <td class="ae-num"><?php echo $subtree_level === 0 ? '-' : ae_tabela_fmt($subtree_comp['fator_conversao'] ?? 1); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
const AE_ITEM_SEARCH_ITEMS = <?php echo json_encode(array_map(fn($item) => [
    'id' => (string)($item['id'] ?? ''),
    'codigo' => (string)($item['codigo'] ?? ''),
    'nome' => (string)($item['nome'] ?? ''),
    'unidade' => (string)($item['unidade_base'] ?? ''),
    'label' => trim(($item['codigo'] ?? '') . ' - ' . ($item['nome'] ?? '')),
    'search' => ae_tabela_lower(trim(($item['codigo'] ?? '') . ' ' . ($item['nome'] ?? '') . ' ' . ($item['unidade_base'] ?? ''))),
], $search_items), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const AE_ITEM_TREE_USAGES = <?php echo json_encode($item_tree_usages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function toggleSubtree(button) {
    const section = button.closest('.ae-subtree');
    if (!section) return;
    const collapsed = section.classList.toggle('collapsed');
    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    const chevron = button.querySelector('.chevron');
    if (chevron) chevron.textContent = collapsed ? '>' : 'v';
}

(function setupItemFinder() {
    const input = document.getElementById('itemSearch');
    const dropdown = document.getElementById('itemDropdown');
    const results = document.getElementById('itemTreeResults');
    if (!input || !dropdown || !results) return;

    function closeDropdown() {
        dropdown.classList.remove('open');
    }

    function renderOptions() {
        const term = input.value.trim().toLowerCase();
        dropdown.innerHTML = '';
        if (term.length < 2) {
            closeDropdown();
            return;
        }

        const matches = AE_ITEM_SEARCH_ITEMS
            .filter((item) => (item.search || '').includes(term))
            .slice(0, 40);

        if (matches.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'ae-item-result-empty';
            empty.textContent = 'Nenhum item encontrado';
            dropdown.appendChild(empty);
            dropdown.classList.add('open');
            return;
        }

        matches.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ae-dropdown-option';
            button.innerHTML = '<strong></strong><span></span>';
            button.querySelector('strong').textContent = item.label || 'Item sem descricao';
            button.querySelector('span').textContent = item.unidade ? 'Unidade: ' + item.unidade : 'Unidade nao informada';
            button.addEventListener('click', () => selectItem(item));
            dropdown.appendChild(button);
        });
        dropdown.classList.add('open');
    }

    function selectItem(item) {
        input.value = item.label || '';
        closeDropdown();
        const params = new URLSearchParams(window.location.search);
        params.set('item_id', item.id || '');
        params.delete('arvore_id');
        params.delete('subtree_item_id');
        window.location.href = 'tabela_arvore_estrutura.php?' + params.toString();
    }

    function renderResults(item) {
        const usages = AE_ITEM_TREE_USAGES[item.id] || [];
        results.innerHTML = '';
        results.classList.add('open');

        const head = document.createElement('div');
        head.className = 'ae-item-result-head';
        head.textContent = usages.length + ' arvore' + (usages.length === 1 ? '' : 's') + ' com este item';
        results.appendChild(head);

        if (usages.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'ae-item-result-empty';
            empty.textContent = 'Este item nao esta incluso nas arvores do filtro atual.';
            results.appendChild(empty);
            return;
        }

        usages.forEach((usage) => {
            const link = document.createElement('a');
            link.className = 'ae-item-result-link';
            link.href = usage.href || '#';
            const roles = Array.isArray(usage.roles) && usage.roles.length ? usage.roles.join(', ') : 'Incluso';
            link.innerHTML = '<strong></strong><span></span>';
            link.querySelector('strong').textContent = usage.label || 'Arvore sem nome';
            link.querySelector('span').textContent = roles + ' | Raiz: ' + (usage.root || 'Nao definida');
            results.appendChild(link);
        });
    }

    input.addEventListener('input', () => {
        results.classList.remove('open');
        results.innerHTML = '';
        renderOptions();
    });
    input.addEventListener('focus', renderOptions);
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.ae-item-finder')) {
            closeDropdown();
        }
    });
})();

(function setupTreeSearch() {
    const input = document.getElementById('busca');
    const list = document.getElementById('treeList');
    const rows = Array.from(document.querySelectorAll('.ae-tree-row'));
    const count = document.getElementById('visibleTreeCount');
    if (!input || rows.length === 0) return;

    rows.forEach((row) => {
        row.addEventListener('click', () => {
            if (list) sessionStorage.setItem('aeTreeListScrollTop', String(list.scrollTop || 0));
            sessionStorage.setItem('aeWindowScrollY', String(window.scrollY || 0));
        });
    });

    function applyFilter() {
        const term = input.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach((row) => {
            const match = term === '' || (row.dataset.search || '').includes(term);
            row.hidden = !match;
            if (match) visible += 1;
        });
        if (count) count.textContent = String(visible);
    }

    input.addEventListener('input', applyFilter);
    applyFilter();

    const savedListScroll = sessionStorage.getItem('aeTreeListScrollTop');
    const savedWindowScroll = sessionStorage.getItem('aeWindowScrollY');
    if (savedListScroll !== null || savedWindowScroll !== null) {
        sessionStorage.removeItem('aeTreeListScrollTop');
        sessionStorage.removeItem('aeWindowScrollY');
        requestAnimationFrame(() => {
            if (list && savedListScroll !== null) {
                list.scrollTop = parseInt(savedListScroll, 10) || 0;
            }
            if (savedWindowScroll !== null) {
                window.scrollTo(0, parseInt(savedWindowScroll, 10) || 0);
            }
        });
    }
})();
</script>
</body>
</html>

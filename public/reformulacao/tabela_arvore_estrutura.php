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

function ae_tabela_flatten_tree(&$rows, $data, $items_by_id, $tree_id, $item_id, $level = 0, $parent = null, $comp = null, $visited = []) {
    if ($item_id === '' || isset($visited[$item_id]) || $level > 50) {
        return;
    }
    $item = $items_by_id[$item_id] ?? null;
    if (!$item) {
        return;
    }

    $rows[] = [
        'level' => $level,
        'item' => $item,
        'parent' => $parent,
        'comp' => $comp,
    ];

    $visited[$item_id] = true;
    foreach (ae_tabela_children($data, $tree_id, $item_id) as $child_comp) {
        ae_tabela_flatten_tree(
            $rows,
            $data,
            $items_by_id,
            $tree_id,
            (string)($child_comp['item_filho_id'] ?? ''),
            $level + 1,
            $item,
            $child_comp,
            $visited
        );
    }
}

function ae_tabela_tree_rows($data, $items_by_id, $tree) {
    $rows = [];
    ae_tabela_flatten_tree($rows, $data, $items_by_id, (string)($tree['id'] ?? ''), (string)($tree['item_raiz_id'] ?? ''));
    return $rows;
}

function ae_tabela_leaf_totals(&$totals, $data, $items_by_id, $tree_id, $item_id, $total_quantidade = 1, $unidade = '', $level = 0, $visited = []) {
    if ($item_id === '' || isset($visited[$item_id]) || $level > 50) {
        return;
    }
    $item = $items_by_id[$item_id] ?? null;
    if (!$item) {
        return;
    }

    $visited[$item_id] = true;
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
    ae_tabela_leaf_totals($totals, $data, $items_by_id, (string)($tree['id'] ?? ''), (string)($tree['item_raiz_id'] ?? ''));
    uasort($totals, function ($a, $b) {
        return strnatcasecmp(
            (string)(($a['item']['codigo'] ?? '') . ' ' . ($a['item']['nome'] ?? '')),
            (string)(($b['item']['codigo'] ?? '') . ' ' . ($b['item']['nome'] ?? ''))
        );
    });
    return array_values($totals);
}

$data = ae_tabela_load();
$items_by_id = ae_tabela_index_by_id($data['tabela_itens'] ?? []);
$trees = array_values($data['tabela_arvores'] ?? []);
usort($trees, fn($a, $b) => strcmp((string)($a['codigo'] ?? ''), (string)($b['codigo'] ?? '')));

$busca = trim($_GET['busca'] ?? '');
$status = $_GET['status'] ?? 'ativas';

$filtered_trees = array_values(array_filter($trees, function ($tree) use ($items_by_id, $busca, $status) {
    $active = (int)($tree['ativo'] ?? 1) === 1;
    if ($status === 'ativas' && !$active) {
        return false;
    }
    if ($status === 'arquivadas' && $active) {
        return false;
    }
    if ($busca === '') {
        return true;
    }

    $root = $items_by_id[(string)($tree['item_raiz_id'] ?? '')] ?? [];
    $haystack = ae_tabela_lower(implode(' ', [
        $tree['codigo'] ?? '',
        $tree['nome'] ?? '',
        $root['codigo'] ?? '',
        $root['nome'] ?? '',
    ]));
    return str_contains($haystack, ae_tabela_lower($busca));
}));

$selected_tree_id = trim($_GET['arvore_id'] ?? '');
if ($selected_tree_id === '' || !array_filter($filtered_trees, fn($tree) => (string)($tree['id'] ?? '') === $selected_tree_id)) {
    $selected_tree_id = (string)($filtered_trees[0]['id'] ?? '');
}

$selected_tree = null;
foreach ($trees as $tree) {
    if ((string)($tree['id'] ?? '') === $selected_tree_id) {
        $selected_tree = $tree;
        break;
    }
}

$selected_root = $selected_tree ? ($items_by_id[(string)($selected_tree['item_raiz_id'] ?? '')] ?? null) : null;
$detail_rows = $selected_tree ? ae_tabela_tree_rows($data, $items_by_id, $selected_tree) : [];
$leaf_totals = $selected_tree ? ae_tabela_tree_leaf_totals($data, $items_by_id, $selected_tree) : [];
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
        .ae-tree-list { max-height: calc(100vh - 260px); overflow: auto; }
        .ae-tree-row { display: grid; grid-template-columns: 1fr auto; gap: 10px; padding: 13px 16px; border-bottom: 1px solid #e8eef6; color: inherit; text-decoration: none; }
        .ae-tree-row:hover { background: #eef6ff; }
        .ae-tree-row.active { background: #e8f2ff; box-shadow: inset 3px 0 0 #006eff; }
        .ae-tree-name { font-weight: 800; color: #102348; margin-bottom: 3px; }
        .ae-tree-meta { color: #64748b; font-size: 12px; line-height: 1.45; }
        .ae-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 26px; height: 24px; padding: 0 8px; border-radius: 999px; font-size: 12px; background: #dcfce7; color: #15803d; border: 1px solid #86efac; font-weight: 800; }
        .ae-pill.off { background: #f1f5f9; color: #64748b; border-color: #cbd5e1; }
        .ae-detail-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; padding: 18px; border-bottom: 1px solid #e5ebf4; }
        .ae-detail-title { margin: 0 0 4px; font-size: 20px; color: #0f1f3a; }
        .ae-detail-meta { display: flex; flex-wrap: wrap; gap: 8px 14px; color: #52627a; font-size: 13px; }
        .ae-detail-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .ae-detail-actions form { margin: 0; }
        .ae-summary { display: grid; grid-template-columns: repeat(4, minmax(130px, 1fr)); gap: 12px; padding: 14px 18px; border-bottom: 1px solid #e5ebf4; background: #fbfdff; }
        .ae-summary div { color: #64748b; font-size: 12px; }
        .ae-summary strong { display: block; color: #102348; font-size: 15px; margin-top: 3px; }
        .ae-table-wrap { overflow: auto; max-height: calc(100vh - 360px); }
        .ae-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .ae-table th { position: sticky; top: 0; z-index: 2; color: #52627a; background: #fff; border-bottom: 1px solid #dce5f1; padding: 11px 10px; white-space: nowrap; font-weight: 800; font-size: 11px; text-transform: uppercase; text-align: left; }
        .ae-table td { border-bottom: 1px solid #e8eef6; padding: 10px; white-space: nowrap; vertical-align: middle; }
        .ae-table tbody tr:hover { background: #eef6ff; }
        .ae-level { font-weight: 800; color: #0068ff; }
        .ae-item-cell { padding-left: calc(10px + (var(--level, 0) * 18px)) !important; }
        .ae-num { text-align: right; font-variant-numeric: tabular-nums; }
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
                <div class="ae-count"><span id="visibleTreeCount"><?php echo count($filtered_trees); ?></span> de <?php echo count($trees); ?></div>
            </div>
            <form class="ae-filters" method="get">
                <div class="ae-field">
                    <label for="busca">Buscar</label>
                    <input id="busca" name="busca" value="<?php echo ae_tabela_h($busca); ?>" placeholder="Codigo ou nome da arvore" autocomplete="off">
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
            </form>

            <div class="ae-tree-list" id="treeList">
                <?php if (empty($filtered_trees)): ?>
                    <div class="ae-empty">Nenhuma arvore encontrada.</div>
                <?php else: ?>
                    <?php foreach ($filtered_trees as $tree_option): ?>
                        <?php
                            $root = $items_by_id[(string)($tree_option['item_raiz_id'] ?? '')] ?? [];
                            $tree_rows = ae_tabela_tree_rows($data, $items_by_id, $tree_option);
                            $active = (int)($tree_option['ativo'] ?? 1) === 1;
                            $label = trim(($tree_option['codigo'] ?? '') . ' - ' . ($tree_option['nome'] ?? ''));
                            $query = ['arvore_id' => $tree_option['id'] ?? '', 'status' => $status];
                            if ($busca !== '') $query['busca'] = $busca;
                        ?>
                        <a class="ae-tree-row <?php echo (string)($tree_option['id'] ?? '') === $selected_tree_id ? 'active' : ''; ?>" href="tabela_arvore_estrutura.php?<?php echo http_build_query($query); ?>" data-search="<?php echo ae_tabela_h(ae_tabela_lower($label . ' ' . ($root['codigo'] ?? '') . ' ' . ($root['nome'] ?? ''))); ?>">
                            <div>
                                <div class="ae-tree-name"><?php echo ae_tabela_h($label !== ' - ' ? $label : 'Arvore sem nome'); ?></div>
                                <div class="ae-tree-meta">
                                    Raiz: <?php echo ae_tabela_h(trim(($root['codigo'] ?? '') . ' - ' . ($root['nome'] ?? '')) ?: 'Nao definida'); ?><br>
                                    <?php echo count($tree_rows); ?> itens na estrutura
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
                        <h2 class="ae-detail-title"><?php echo ae_tabela_h(($selected_tree['codigo'] ?? '') . ' - ' . ($selected_tree['nome'] ?? '')); ?></h2>
                        <div class="ae-detail-meta">
                            <span>Raiz: <?php echo ae_tabela_h(trim(($selected_root['codigo'] ?? '') . ' - ' . ($selected_root['nome'] ?? '')) ?: 'Nao definida'); ?></span>
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
                        <a class="ae-btn primary" href="<?php echo rf_route('arvore_estrutura', 'page'); ?>?<?php echo http_build_query(['arvore_id' => $selected_tree_id, 'selected_item_id' => $selected_tree['item_raiz_id'] ?? '']); ?>">Editar Arvore</a>
                    </div>
                </div>

                <div class="ae-summary">
                    <div>Codigo<strong><?php echo ae_tabela_h($selected_tree['codigo'] ?? ''); ?></strong></div>
                    <div>Nome<strong><?php echo ae_tabela_h($selected_tree['nome'] ?? ''); ?></strong></div>
                    <div>Total de itens<strong><?php echo count($detail_rows); ?></strong></div>
                    <div>Descricao<strong><?php echo ae_tabela_h($selected_tree['descricao'] ?? ''); ?></strong></div>
                </div>

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
                                    <th>Pai</th>
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
                                        <td><?php echo ae_tabela_h(trim(($parent['codigo'] ?? '') . ' - ' . ($parent['nome'] ?? '')) ?: 'Raiz'); ?></td>
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
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
(function setupTreeSearch() {
    const input = document.getElementById('busca');
    const rows = Array.from(document.querySelectorAll('.ae-tree-row'));
    const count = document.getElementById('visibleTreeCount');
    if (!input || rows.length === 0) return;

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
})();
</script>
</body>
</html>

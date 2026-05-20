<?php
/**
 * Relatorio simples de itens da Arvore de Estrutura.
 *
 * Lista o cadastro de itens sem distinguir uso como pai ou filho.
 */
require_once __DIR__ . '/module_routes.php';
require_once __DIR__ . '/safe_storage.php';
require_once __DIR__ . '/shared/CadastrosBasicosRepository.php';
require_once rf_route_path('arvore_estrutura', 'api');

function ae_itens_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ae_itens_lower($value) {
    $value = (string)$value;
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function ae_itens_match_key($value) {
    return preg_replace('/\s+/', '', ae_itens_lower(trim((string)$value)));
}

function ae_itens_find_index($data, $id) {
    foreach (($data['tabela_itens'] ?? []) as $idx => $row) {
        if ((string)($row['id'] ?? '') === (string)$id) {
            return $idx;
        }
    }
    return -1;
}

function ae_itens_find_by_code($data, $codigo, $ignore_id = '', $active_only = true) {
    $codigo = ae_itens_match_key($codigo);
    foreach (($data['tabela_itens'] ?? []) as $row) {
        if ((string)($row['id'] ?? '') === (string)$ignore_id) {
            continue;
        }
        if ($active_only && (int)($row['ativo'] ?? 1) !== 1) {
            continue;
        }
        if (ae_itens_match_key($row['codigo'] ?? '') === $codigo) {
            return $row;
        }
    }
    return null;
}

function ae_itens_find_duplicate($data, $codigo, $nome, $ignore_id = '', $active_only = true) {
    $codigo_key = ae_itens_match_key($codigo);
    $nome_key = ae_itens_match_key($nome);
    foreach (($data['tabela_itens'] ?? []) as $row) {
        if ((string)($row['id'] ?? '') === (string)$ignore_id) {
            continue;
        }
        if ($active_only && (int)($row['ativo'] ?? 1) !== 1) {
            continue;
        }
        if ($codigo_key !== '' && ae_itens_match_key($row['codigo'] ?? '') === $codigo_key) {
            return ['field' => 'codigo', 'item' => $row];
        }
        if ($nome_key !== '' && ae_itens_match_key($row['nome'] ?? '') === $nome_key) {
            return ['field' => 'descricao', 'item' => $row];
        }
    }
    return null;
}

function ae_itens_id($prefix) {
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$prefix);
    return ($safe ?: 'ae') . '_' . date('YmdHis') . '_' . random_int(1000, 9999);
}

function ae_itens_find_tree_by_root($data, $item_id, $active_only = false) {
    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        if ((string)($tree['item_raiz_id'] ?? '') !== (string)$item_id) {
            continue;
        }
        if ($active_only && (int)($tree['ativo'] ?? 1) !== 1) {
            continue;
        }
        return $tree;
    }
    return null;
}

function ae_itens_active_tree_ids($data) {
    $active_tree_ids = [];
    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        $tree_id = (string)($tree['id'] ?? '');
        if ($tree_id !== '' && (int)($tree['ativo'] ?? 1) === 1) {
            $active_tree_ids[$tree_id] = true;
        }
    }
    return $active_tree_ids;
}

function ae_itens_usage_tree_ids($data, $item_id) {
    $item_id = (string)$item_id;
    $active_tree_ids = ae_itens_active_tree_ids($data);
    $tree_ids = [];

    if ($item_id === '') {
        return $tree_ids;
    }

    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        $tree_id = (string)($tree['id'] ?? '');
        if (!isset($active_tree_ids[$tree_id])) {
            continue;
        }
        if ((string)($tree['item_raiz_id'] ?? '') === $item_id) {
            $tree_ids[$tree_id] = true;
        }
    }

    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        if ((int)($comp['ativo'] ?? 1) !== 1) {
            continue;
        }
        $tree_id = (string)($comp['arvore_id'] ?? '');
        if (!isset($active_tree_ids[$tree_id])) {
            continue;
        }
        if ((string)($comp['item_pai_id'] ?? '') === $item_id || (string)($comp['item_filho_id'] ?? '') === $item_id) {
            $tree_ids[$tree_id] = true;
        }
    }

    return $tree_ids;
}

function ae_itens_tree_counts($data) {
    $counts = [];
    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        if ((int)($tree['ativo'] ?? 1) !== 1) {
            continue;
        }
        $tree_id = (string)($tree['id'] ?? '');
        $root_id = (string)($tree['item_raiz_id'] ?? '');
        if ($tree_id !== '' && $root_id !== '') {
            $counts[$root_id][$tree_id] = true;
        }
    }

    $active_tree_ids = ae_itens_active_tree_ids($data);
    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        if ((int)($comp['ativo'] ?? 1) !== 1) {
            continue;
        }
        $tree_id = (string)($comp['arvore_id'] ?? '');
        if ($tree_id === '' || !isset($active_tree_ids[$tree_id])) {
            continue;
        }
        foreach (['item_pai_id', 'item_filho_id'] as $field) {
            $item_id = (string)($comp[$field] ?? '');
            if ($item_id !== '') {
                $counts[$item_id][$tree_id] = true;
            }
        }
    }

    $totals = [];
    foreach ($counts as $item_id => $tree_ids) {
        $totals[$item_id] = count($tree_ids);
    }
    return $totals;
}

function ae_itens_save_data($data) {
    $data['updated_at'] = date('Y-m-d H:i:s');
    cb_import_arvore_data($data);
    cod_12_05_safe_write_json(rf_route('arvore_estrutura', 'storage'), $data);
}

function ae_itens_sort_link($column, $label, $current_sort, $current_dir, $params) {
    $next_dir = ($current_sort === $column && $current_dir === 'asc') ? 'desc' : 'asc';
    $params['sort'] = $column;
    $params['dir'] = $next_dir;
    $class = 'ae-sort-link' . ($current_sort === $column ? ' active' : '');
    $indicator = $current_sort === $column ? ($current_dir === 'asc' ? '^' : 'v') : '';
    return '<a class="' . ae_itens_h($class) . '" href="relatorio_itens_arvore.php?' . ae_itens_h(http_build_query($params)) . '">'
        . ae_itens_h($label)
        . '<span>' . ae_itens_h($indicator) . '</span>'
        . '</a>';
}

$data = ae_api_load_data();
$tree_counts_by_item = ae_itens_tree_counts($data);
$message = '';
$message_type = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'archive_item' || $action === 'reactivate_item') {
        $item_id = trim($_POST['item_id'] ?? '');
        $idx = ae_itens_find_index($data, $item_id);
        if ($idx < 0) {
            $message = 'Item nao encontrado.';
            $message_type = 'error';
        } elseif ($action === 'reactivate_item' && ae_itens_find_duplicate($data, $data['tabela_itens'][$idx]['codigo'] ?? '', $data['tabela_itens'][$idx]['nome'] ?? '', $item_id, true)) {
            $message = 'Nao foi possivel reativar: ja existe item ativo equivalente, ignorando espacos e maiusculas/minusculas.';
            $message_type = 'error';
        } elseif ($action === 'archive_item' && count(ae_itens_usage_tree_ids($data, $item_id)) > 0) {
            $message = 'Nao foi possivel arquivar: este item esta sendo utilizado em arvore/subarvore ativa.';
            $message_type = 'error';
        } else {
            $data['tabela_itens'][$idx]['ativo'] = $action === 'reactivate_item' ? 1 : 0;
            $data['tabela_itens'][$idx]['atualizado_em'] = date('Y-m-d H:i:s');
            $data['historico'][] = [
                'id' => ae_itens_id('hist'),
                'acao' => $action === 'reactivate_item' ? 'item reativado' : 'item arquivado',
                'item_id' => $item_id,
                'arvore_id' => '',
                'composicao_id' => '',
                'detalhe' => 'Alterado pelo relatorio de itens. Vinculos da arvore foram preservados.',
                'criado_em' => date('Y-m-d H:i:s')
            ];
            ae_itens_save_data($data);
            $params = ['msg' => $action === 'reactivate_item' ? 'item_reativado' : 'item_arquivado'];
            if (trim($_POST['busca'] ?? '') !== '') {
                $params['busca'] = trim($_POST['busca'] ?? '');
            }
            if (trim($_POST['status'] ?? '') !== '') {
                $params['status'] = trim($_POST['status'] ?? '');
            }
            if (trim($_POST['sort'] ?? '') !== '') {
                $params['sort'] = trim($_POST['sort'] ?? '');
            }
            if (trim($_POST['dir'] ?? '') !== '') {
                $params['dir'] = trim($_POST['dir'] ?? '');
            }
            header('Location: relatorio_itens_arvore.php?' . http_build_query($params));
            exit;
        }
    }

    if ($action === 'edit_item') {
        $item_id = trim($_POST['item_id'] ?? '');
        $idx = ae_itens_find_index($data, $item_id);
        $codigo = trim($_POST['codigo'] ?? '');
        $nome = trim($_POST['nome'] ?? '');
        $unidade_base = trim($_POST['unidade_base'] ?? '');
        $duplicate = ae_itens_find_duplicate($data, $codigo, $nome, $item_id, true);

        if ($idx < 0) {
            $message = 'Item nao encontrado para edicao.';
            $message_type = 'error';
        } elseif ($codigo === '' || $nome === '') {
            $message = 'Codigo e descricao sao obrigatorios.';
            $message_type = 'error';
        } elseif ($duplicate) {
            $message = $duplicate['field'] === 'codigo'
                ? 'Ja existe outro item ativo com este codigo, ignorando espacos e maiusculas/minusculas.'
                : 'Ja existe outro item ativo com esta descricao, ignorando espacos e maiusculas/minusculas.';
            $message_type = 'error';
        } elseif (!isset($_POST['ativo']) && count(ae_itens_usage_tree_ids($data, $item_id)) > 0) {
            $message = 'Nao foi possivel arquivar: este item esta sendo utilizado em arvore/subarvore ativa.';
            $message_type = 'error';
        } else {
            $data['tabela_itens'][$idx]['codigo'] = $codigo;
            $data['tabela_itens'][$idx]['nome'] = $nome;
            $data['tabela_itens'][$idx]['unidade_base'] = $unidade_base;
            $data['tabela_itens'][$idx]['ativo'] = isset($_POST['ativo']) ? 1 : 0;
            $data['tabela_itens'][$idx]['atualizado_em'] = date('Y-m-d H:i:s');

            foreach (($data['tabela_arvores'] ?? []) as $tree_idx => $tree) {
                if ((string)($tree['item_raiz_id'] ?? '') === $item_id) {
                    $data['tabela_arvores'][$tree_idx]['codigo'] = $codigo;
                    $data['tabela_arvores'][$tree_idx]['nome'] = $nome;
                    $data['tabela_arvores'][$tree_idx]['atualizado_em'] = date('Y-m-d H:i:s');
                }
            }

            ae_itens_save_data($data);
            $params = [];
            if (trim($_POST['busca'] ?? '') !== '') {
                $params['busca'] = trim($_POST['busca'] ?? '');
            }
            if (trim($_POST['status'] ?? '') !== '') {
                $params['status'] = trim($_POST['status'] ?? '');
            }
            if (trim($_POST['sort'] ?? '') !== '') {
                $params['sort'] = trim($_POST['sort'] ?? '');
            }
            if (trim($_POST['dir'] ?? '') !== '') {
                $params['dir'] = trim($_POST['dir'] ?? '');
            }
            $params['edit'] = $item_id;
            $params['msg'] = 'item_salvo';
            header('Location: relatorio_itens_arvore.php?' . http_build_query($params));
            exit;
        }
    }

    if ($action === 'create_tree_from_item') {
        $item_id = trim($_POST['item_id'] ?? '');
        $idx = ae_itens_find_index($data, $item_id);

        if ($idx < 0) {
            $message = 'Item nao encontrado para gerar arvore.';
            $message_type = 'error';
        } else {
            $item = $data['tabela_itens'][$idx];
            $existing_tree = ae_itens_find_tree_by_root($data, $item_id, false);

            if ($existing_tree) {
                if ((int)($existing_tree['ativo'] ?? 1) !== 1) {
                    foreach (($data['tabela_arvores'] ?? []) as $tree_idx => $tree) {
                        if ((string)($tree['id'] ?? '') === (string)($existing_tree['id'] ?? '')) {
                            $data['tabela_arvores'][$tree_idx]['ativo'] = 1;
                            $data['tabela_arvores'][$tree_idx]['atualizado_em'] = date('Y-m-d H:i:s');
                            $existing_tree = $data['tabela_arvores'][$tree_idx];
                            ae_itens_save_data($data);
                            break;
                        }
                    }
                }
                header('Location: arvore_estrutura.php?' . http_build_query([
                    'arvore_id' => $existing_tree['id'] ?? '',
                    'selected_item_id' => $item_id,
                    'msg' => 'arvore_existente'
                ]));
                exit;
            }

            $tree_id = ae_itens_id('arvore');
            $data['tabela_arvores'][] = [
                'id' => $tree_id,
                'codigo' => $item['codigo'] ?? '',
                'nome' => $item['nome'] ?? '',
                'item_raiz_id' => $item_id,
                'descricao' => 'Arvore criada a partir do relatorio de itens para cadastrar descendentes.',
                'ativo' => 1,
                'criado_em' => date('Y-m-d H:i:s'),
                'atualizado_em' => date('Y-m-d H:i:s')
            ];
            $data['historico'][] = [
                'id' => ae_itens_id('hist'),
                'acao' => 'arvore criada a partir de item',
                'item_id' => $item_id,
                'arvore_id' => $tree_id,
                'composicao_id' => '',
                'detalhe' => 'Item promovido a raiz para cadastrar descendentes.',
                'criado_em' => date('Y-m-d H:i:s')
            ];

            ae_itens_save_data($data);
            header('Location: arvore_estrutura.php?' . http_build_query([
                'arvore_id' => $tree_id,
                'selected_item_id' => $item_id,
                'msg' => 'arvore_salva'
            ]));
            exit;
        }
    }
}

if (($_GET['msg'] ?? '') === 'item_salvo') {
    $message = 'Item atualizado.';
} elseif (($_GET['msg'] ?? '') === 'item_arquivado') {
    $message = 'Item arquivado. Os vinculos nas arvores foram preservados.';
} elseif (($_GET['msg'] ?? '') === 'item_reativado') {
    $message = 'Item reativado.';
}

$busca = trim($_GET['busca'] ?? $_POST['busca'] ?? '');
$status = $_GET['status'] ?? $_POST['status'] ?? 'ativos';
$allowed_sorts = ['codigo', 'nome', 'unidade', 'arvores', 'status'];
$requested_sort = $_GET['sort'] ?? $_POST['sort'] ?? 'codigo';
$sort = in_array($requested_sort, $allowed_sorts, true) ? $requested_sort : 'codigo';
$dir = ($_GET['dir'] ?? $_POST['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$rows = array_values(array_filter($data['tabela_itens'] ?? [], function ($row) use ($status) {
    $active = (int)($row['ativo'] ?? 1) === 1;
    if ($status === 'ativos') {
        return $active;
    }
    if ($status === 'arquivados') {
        return !$active;
    }
    return true;
}));

if ($busca !== '') {
    $needle = ae_itens_lower($busca);
    $rows = array_values(array_filter($rows, function ($row) use ($needle) {
        $fields = [
            $row['codigo'] ?? '',
            $row['nome'] ?? '',
            $row['unidade_base'] ?? ''
        ];

        foreach ($fields as $field) {
            if (str_contains(ae_itens_lower($field), $needle)) {
                return true;
            }
        }
        return false;
    }));
}

usort($rows, function ($a, $b) use ($sort, $dir, $tree_counts_by_item) {
    $a_id = (string)($a['id'] ?? '');
    $b_id = (string)($b['id'] ?? '');
    if ($sort === 'arvores') {
        $cmp = ((int)($tree_counts_by_item[$a_id] ?? 0)) <=> ((int)($tree_counts_by_item[$b_id] ?? 0));
    } elseif ($sort === 'status') {
        $cmp = ((int)($b['ativo'] ?? 1)) <=> ((int)($a['ativo'] ?? 1));
    } else {
        $field = $sort === 'unidade' ? 'unidade_base' : $sort;
        $cmp = strnatcasecmp((string)($a[$field] ?? ''), (string)($b[$field] ?? ''));
    }

    if ($cmp === 0) {
        $cmp = strnatcasecmp((string)($a['codigo'] ?? ''), (string)($b['codigo'] ?? ''));
    }

    return $dir === 'desc' ? -$cmp : $cmp;
});

$sort_params = array_filter([
    'busca' => $busca,
    'status' => $status,
], fn($value) => $value !== '');

$edit_id = trim($_GET['edit'] ?? ($_POST['item_id'] ?? ''));
$edit_row = null;
if ($edit_id !== '') {
    $idx = ae_itens_find_index($data, $edit_id);
    $edit_row = $idx >= 0 ? $data['tabela_itens'][$idx] : null;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatorio de Itens da Arvore</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { background: #f4f7fb; color: #0f1f3a; }
        .content { padding: 20px 24px 32px; }
        .ae-page-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 16px; }
        .ae-title { color: #0068ff; font-size: 24px; font-weight: 750; margin: 0; border: 0; padding: 0; }
        .ae-subtitle { color: #52627a; font-size: 13px; margin-top: 4px; }
        .ae-card { background: #fff; border: 1px solid #dce5f1; border-radius: 8px; box-shadow: 0 10px 28px rgba(15,31,58,.045); }
        .ae-toolbar { display: flex; justify-content: space-between; align-items: end; gap: 14px; padding: 16px 18px; border-bottom: 1px solid #e5ebf4; background: #fbfdff; border-radius: 8px 8px 0 0; }
        .ae-filters { display: grid; grid-template-columns: minmax(260px, 420px) auto auto; gap: 10px; align-items: end; }
        .ae-field label { display: block; color: #465873; font-size: 12px; font-weight: 700; margin-bottom: 5px; }
        .ae-field input, .ae-field select { width: 100%; height: 38px; border: 1px solid #cfd8e6; border-radius: 6px; color: #122243; background: #fff; padding: 0 10px; }
        .ae-toggle-field { display: inline-flex; align-items: center; gap: 8px; height: 38px; color: #465873; font-size: 12px; font-weight: 800; }
        .ae-toggle-field input { width: 16px; height: 16px; accent-color: #006eff; }
        .ae-btn { display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 13px; border-radius: 6px; border: 1px solid #cfd8e6; background: #fff; color: #11254a; text-decoration: none; font-size: 13px; font-weight: 700; cursor: pointer; }
        .ae-btn.primary { background: #006eff; border-color: #006eff; color: #fff; }
        .ae-btn.danger { border-color: #fecdd3; color: #be123c; background: #fff1f2; }
        .ae-btn:disabled { opacity: .55; cursor: not-allowed; }
        .ae-btn.small { height: 30px; padding: 0 10px; font-size: 12px; }
        .ae-row-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .ae-inline-form { display: inline-flex; margin: 0; }
        .ae-edit-card { margin-bottom: 14px; padding: 16px 18px; }
        .ae-edit-grid { display: grid; grid-template-columns: 180px minmax(260px, 1fr) 160px auto auto; gap: 10px; align-items: end; }
        .ae-status { margin-bottom: 14px; padding: 11px 14px; border-radius: 8px; border: 1px solid #bbf7d0; background: #f0fdf4; color: #166534; font-weight: 700; }
        .ae-status.error { border-color: #fecdd3; background: #fff1f2; color: #be123c; }
        .ae-count { color: #52627a; font-size: 13px; font-weight: 700; white-space: nowrap; }
        .ae-table-wrap { overflow: auto; max-height: calc(100vh - 230px); }
        .ae-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .ae-table th { position: sticky; top: 0; z-index: 2; color: #52627a; background: #fbfdff; border-bottom: 1px solid #dce5f1; padding: 11px 10px; white-space: nowrap; font-weight: 800; font-size: 11px; text-transform: uppercase; text-align: left; }
        .ae-sort-link { display: inline-flex; align-items: center; gap: 6px; color: inherit; text-decoration: none; }
        .ae-sort-link span { display: inline-flex; align-items: center; justify-content: center; min-width: 10px; color: #006eff; font-size: 11px; }
        .ae-sort-link.active { color: #123f7c; }
        .ae-table td { border-bottom: 1px solid #e8eef6; padding: 10px; white-space: nowrap; vertical-align: middle; }
        .ae-table tbody tr:hover { background: #eef6ff; }
        .ae-empty { min-height: 240px; display: grid; place-items: center; color: #52627a; }
        @media (max-width: 900px) {
            .ae-page-head, .ae-toolbar { flex-direction: column; align-items: stretch; }
            .ae-filters, .ae-edit-grid { grid-template-columns: 1fr; }
            .ae-table-wrap { max-height: none; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/menu.php'; ?>

<main class="content">
    <div class="ae-page-head">
        <div>
            <h1 class="ae-title">Relatorio de Itens da Arvore</h1>
            <div class="ae-subtitle">Lista os itens cadastrados na Arvore de Estrutura sem excluir registros.</div>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="ae-status <?php echo ae_itens_h($message_type); ?>"><?php echo ae_itens_h($message); ?></div>
    <?php endif; ?>

    <?php if ($edit_row): ?>
        <section class="ae-card ae-edit-card">
            <form method="post" class="ae-edit-grid">
                <input type="hidden" name="action" value="edit_item">
                <input type="hidden" name="item_id" value="<?php echo ae_itens_h($edit_row['id'] ?? ''); ?>">
                <input type="hidden" name="busca" value="<?php echo ae_itens_h($busca); ?>">
                <input type="hidden" name="status" value="<?php echo ae_itens_h($status); ?>">
                <input type="hidden" name="sort" value="<?php echo ae_itens_h($sort); ?>">
                <input type="hidden" name="dir" value="<?php echo ae_itens_h($dir); ?>">
                <div class="ae-field">
                    <label for="editCodigo">Cod item</label>
                    <input id="editCodigo" name="codigo" required value="<?php echo ae_itens_h($edit_row['codigo'] ?? ''); ?>">
                </div>
                <div class="ae-field">
                    <label for="editNome">Descricao item</label>
                    <input id="editNome" name="nome" required value="<?php echo ae_itens_h($edit_row['nome'] ?? ''); ?>">
                </div>
                <div class="ae-field">
                    <label for="editUnidade">Unidade</label>
                    <input id="editUnidade" name="unidade_base" value="<?php echo ae_itens_h($edit_row['unidade_base'] ?? ''); ?>">
                </div>
                <label class="ae-toggle-field"><input type="checkbox" name="ativo" <?php echo (int)($edit_row['ativo'] ?? 1) === 1 ? 'checked' : ''; ?>> Item ativo</label>
                <button class="ae-btn primary" type="submit">Salvar Item</button>
                <a class="ae-btn" href="relatorio_itens_arvore.php?<?php echo http_build_query(array_filter(['busca' => $busca, 'status' => $status, 'sort' => $sort, 'dir' => $dir], fn($value) => $value !== '')); ?>">Cancelar</a>
            </form>
        </section>
    <?php endif; ?>

    <section class="ae-card">
        <form class="ae-toolbar" method="get">
            <div class="ae-filters">
                <div class="ae-field">
                    <label for="busca">Buscar</label>
                    <input id="busca" name="busca" value="<?php echo ae_itens_h($busca); ?>" placeholder="Codigo, descricao ou unidade">
                </div>
                <input type="hidden" name="sort" value="<?php echo ae_itens_h($sort); ?>">
                <input type="hidden" name="dir" value="<?php echo ae_itens_h($dir); ?>">
                <div class="ae-field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="ativos" <?php echo $status === 'ativos' ? 'selected' : ''; ?>>Ativos</option>
                        <option value="todos" <?php echo $status === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="arquivados" <?php echo $status === 'arquivados' ? 'selected' : ''; ?>>Arquivados</option>
                    </select>
                </div>
                <button class="ae-btn primary" type="submit">Filtrar</button>
                <a class="ae-btn" href="relatorio_itens_arvore.php">Limpar</a>
            </div>
            <div class="ae-count"><?php echo count($rows); ?> itens <?php echo ae_itens_h($status); ?></div>
        </form>

        <?php if (empty($rows)): ?>
            <div class="ae-empty">Nenhum item encontrado.</div>
        <?php else: ?>
            <div class="ae-table-wrap">
                <table class="ae-table">
                    <thead>
                        <tr>
                            <th><?php echo ae_itens_sort_link('codigo', 'cod item', $sort, $dir, $sort_params); ?></th>
                            <th><?php echo ae_itens_sort_link('nome', 'descricao item', $sort, $dir, $sort_params); ?></th>
                            <th><?php echo ae_itens_sort_link('unidade', 'unidade', $sort, $dir, $sort_params); ?></th>
                            <th><?php echo ae_itens_sort_link('arvores', 'arvores', $sort, $dir, $sort_params); ?></th>
                            <th><?php echo ae_itens_sort_link('status', 'status', $sort, $dir, $sort_params); ?></th>
                            <th>edicao</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $item_id = (string)($row['id'] ?? '');
                                $usage_count = (int)($tree_counts_by_item[$item_id] ?? 0);
                                $tree = ae_itens_find_tree_by_root($data, $item_id, true);
                                $is_active_item = (int)($row['ativo'] ?? 1) === 1;
                                $archive_blocked = $is_active_item && $usage_count > 0;
                            ?>
                            <tr>
                                <td><?php echo ae_itens_h($row['codigo'] ?? ''); ?></td>
                                <td><?php echo ae_itens_h($row['nome'] ?? ''); ?></td>
                                <td><?php echo ae_itens_h($row['unidade_base'] ?? ''); ?></td>
                                <td><?php echo $usage_count; ?></td>
                                <td><?php echo $is_active_item ? 'Ativo' : 'Arquivado'; ?></td>
                                <td>
                                    <div class="ae-row-actions">
                                        <a class="ae-btn small" href="relatorio_itens_arvore.php?<?php echo http_build_query(array_filter(['busca' => $busca, 'status' => $status, 'sort' => $sort, 'dir' => $dir, 'edit' => $row['id'] ?? ''], fn($value) => $value !== '')); ?>">Editar</a>
                                        <form method="post" class="ae-inline-form" onsubmit="return <?php echo $archive_blocked ? 'false' : "confirm('" . ($is_active_item ? 'Arquivar este item?' : 'Reativar este item?') . "')" ; ?>;">
                                            <input type="hidden" name="action" value="<?php echo $is_active_item ? 'archive_item' : 'reactivate_item'; ?>">
                                            <input type="hidden" name="item_id" value="<?php echo ae_itens_h($row['id'] ?? ''); ?>">
                                            <input type="hidden" name="busca" value="<?php echo ae_itens_h($busca); ?>">
                                            <input type="hidden" name="status" value="<?php echo ae_itens_h($status); ?>">
                                            <input type="hidden" name="sort" value="<?php echo ae_itens_h($sort); ?>">
                                            <input type="hidden" name="dir" value="<?php echo ae_itens_h($dir); ?>">
                                            <button class="ae-btn small <?php echo $is_active_item ? 'danger' : 'primary'; ?>" type="submit" <?php echo $archive_blocked ? 'disabled title="Item utilizado em arvore/subarvore ativa."' : ''; ?>><?php echo $is_active_item ? 'Arquivar' : 'Reativar'; ?></button>
                                        </form>
                                        <?php if ($tree): ?>
                                            <a class="ae-btn small primary" href="arvore_estrutura.php?<?php echo http_build_query(['arvore_id' => $tree['id'] ?? '', 'selected_item_id' => $row['id'] ?? '']); ?>">Abrir arvore</a>
                                        <?php else: ?>
                                            <form method="post" class="ae-inline-form">
                                                <input type="hidden" name="action" value="create_tree_from_item">
                                                <input type="hidden" name="item_id" value="<?php echo ae_itens_h($row['id'] ?? ''); ?>">
                                                <input type="hidden" name="busca" value="<?php echo ae_itens_h($busca); ?>">
                                                <input type="hidden" name="status" value="<?php echo ae_itens_h($status); ?>">
                                                <input type="hidden" name="sort" value="<?php echo ae_itens_h($sort); ?>">
                                                <input type="hidden" name="dir" value="<?php echo ae_itens_h($dir); ?>">
                                                <button class="ae-btn small primary" type="submit">Gerar arvore</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>

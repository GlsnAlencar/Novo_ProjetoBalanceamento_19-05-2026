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

function ae_itens_find_index($data, $id) {
    foreach (($data['tabela_itens'] ?? []) as $idx => $row) {
        if ((string)($row['id'] ?? '') === (string)$id) {
            return $idx;
        }
    }
    return -1;
}

function ae_itens_find_by_code($data, $codigo, $ignore_id = '', $active_only = true) {
    $codigo = ae_itens_lower(trim($codigo));
    foreach (($data['tabela_itens'] ?? []) as $row) {
        if ((string)($row['id'] ?? '') === (string)$ignore_id) {
            continue;
        }
        if ($active_only && (int)($row['ativo'] ?? 1) !== 1) {
            continue;
        }
        if (ae_itens_lower(trim($row['codigo'] ?? '')) === $codigo) {
            return $row;
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

function ae_itens_save_data($data) {
    $data['updated_at'] = date('Y-m-d H:i:s');
    cb_import_arvore_data($data);
    cod_12_05_safe_write_json(rf_route('arvore_estrutura', 'storage'), $data);
}

$data = ae_api_load_data();
$message = '';
$message_type = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'edit_item') {
        $item_id = trim($_POST['item_id'] ?? '');
        $idx = ae_itens_find_index($data, $item_id);
        $codigo = trim($_POST['codigo'] ?? '');
        $nome = trim($_POST['nome'] ?? '');
        $unidade_base = trim($_POST['unidade_base'] ?? '');

        if ($idx < 0) {
            $message = 'Item nao encontrado para edicao.';
            $message_type = 'error';
        } elseif ($codigo === '' || $nome === '') {
            $message = 'Codigo e descricao sao obrigatorios.';
            $message_type = 'error';
        } elseif (ae_itens_find_by_code($data, $codigo, $item_id, true)) {
            $message = 'Ja existe outro item ativo com este codigo.';
            $message_type = 'error';
        } else {
            $data['tabela_itens'][$idx]['codigo'] = $codigo;
            $data['tabela_itens'][$idx]['nome'] = $nome;
            $data['tabela_itens'][$idx]['unidade_base'] = $unidade_base;
            $data['tabela_itens'][$idx]['ativo'] = 1;
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
}

$rows = array_values(array_filter($data['tabela_itens'] ?? [], fn($row) => (int)($row['ativo'] ?? 1) === 1));

$busca = trim($_GET['busca'] ?? '');
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

usort($rows, fn($a, $b) => strnatcasecmp((string)($a['codigo'] ?? ''), (string)($b['codigo'] ?? '')));

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
        .ae-field input { width: 100%; height: 38px; border: 1px solid #cfd8e6; border-radius: 6px; color: #122243; background: #fff; padding: 0 10px; }
        .ae-btn { display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 13px; border-radius: 6px; border: 1px solid #cfd8e6; background: #fff; color: #11254a; text-decoration: none; font-size: 13px; font-weight: 700; cursor: pointer; }
        .ae-btn.primary { background: #006eff; border-color: #006eff; color: #fff; }
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
            <div class="ae-subtitle">Lista todos os itens ativos cadastrados na Arvore de Estrutura.</div>
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
                <button class="ae-btn primary" type="submit">Salvar Item</button>
                <a class="ae-btn" href="relatorio_itens_arvore.php?<?php echo http_build_query($busca !== '' ? ['busca' => $busca] : []); ?>">Cancelar</a>
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
                <button class="ae-btn primary" type="submit">Filtrar</button>
                <a class="ae-btn" href="relatorio_itens_arvore.php">Limpar</a>
            </div>
            <div class="ae-count"><?php echo count($rows); ?> itens ativos</div>
        </form>

        <?php if (empty($rows)): ?>
            <div class="ae-empty">Nenhum item ativo encontrado.</div>
        <?php else: ?>
            <div class="ae-table-wrap">
                <table class="ae-table">
                    <thead>
                        <tr>
                            <th>cod item</th>
                            <th>descricao item</th>
                            <th>unidade</th>
                            <th>edicao</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $tree = ae_itens_find_tree_by_root($data, $row['id'] ?? '', true); ?>
                            <tr>
                                <td><?php echo ae_itens_h($row['codigo'] ?? ''); ?></td>
                                <td><?php echo ae_itens_h($row['nome'] ?? ''); ?></td>
                                <td><?php echo ae_itens_h($row['unidade_base'] ?? ''); ?></td>
                                <td>
                                    <div class="ae-row-actions">
                                        <a class="ae-btn small" href="relatorio_itens_arvore.php?<?php echo http_build_query(array_filter(['busca' => $busca, 'edit' => $row['id'] ?? ''], fn($value) => $value !== '')); ?>">Editar</a>
                                        <?php if ($tree): ?>
                                            <a class="ae-btn small primary" href="arvore_estrutura.php?<?php echo http_build_query(['arvore_id' => $tree['id'] ?? '', 'selected_item_id' => $row['id'] ?? '']); ?>">Abrir arvore</a>
                                        <?php else: ?>
                                            <form method="post" class="ae-inline-form">
                                                <input type="hidden" name="action" value="create_tree_from_item">
                                                <input type="hidden" name="item_id" value="<?php echo ae_itens_h($row['id'] ?? ''); ?>">
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

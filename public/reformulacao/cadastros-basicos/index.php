<?php
require_once __DIR__ . '/../shared/CadastrosBasicosRepository.php';

$configs = cb_catalog_config();
$catalog = $_GET['tipo'] ?? 'setores';
if (!isset($configs[$catalog])) {
    $catalog = 'setores';
}

function cb_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = trim($_POST['id'] ?? '');

    if ($action === 'save') {
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            $message = 'Nome/descricao e obrigatorio.';
            $message_type = 'error';
        } else {
            cb_upsert($catalog, [
                'id' => $id,
                'codigo' => trim($_POST['codigo'] ?? ''),
                'nome' => $nome,
                'descricao' => trim($_POST['descricao'] ?? $nome),
                'ativo' => isset($_POST['ativo']) ? 1 : 0
            ]);
            $message = 'Cadastro salvo.';
        }
    }

    if ($action === 'delete' && $id !== '') {
        cb_set_active($catalog, $id, 0);
        $message = 'Registro marcado como inativo.';
    }
}

$search = trim($_GET['q'] ?? '');
$rows = cb_list($catalog);
if ($search !== '') {
    $needle = strtolower($search);
    $rows = array_values(array_filter($rows, function ($row) use ($needle) {
        return strpos(strtolower((string)($row['codigo'] ?? '')), $needle) !== false
            || strpos(strtolower((string)($row['nome'] ?? '')), $needle) !== false
            || strpos(strtolower((string)($row['descricao'] ?? '')), $needle) !== false;
    }));
}

$edit_id = $_GET['edit'] ?? '';
$edit_row = null;
foreach ($rows as $row) {
    if (($row['id'] ?? '') === $edit_id) {
        $edit_row = $row;
        break;
    }
}
$title = $configs[$catalog]['label'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastros Basicos - <?php echo cb_h($title); ?></title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        body { background: #f4f7fb; }
        .cb-shell { margin-left: var(--sidebar-width); padding: 22px; }
        .cb-header { display: flex; justify-content: space-between; gap: 14px; align-items: center; margin-bottom: 18px; }
        .cb-title h1 { margin: 0; color: #0f4f9c; font-size: 24px; border: 0; padding: 0; }
        .cb-title span { color: #5f6f85; font-size: 13px; font-weight: 700; }
        .cb-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
        .cb-tab { padding: 8px 11px; border: 1px solid #cfd8e6; border-radius: 5px; background: #fff; color: #17335f; text-decoration: none; font-size: 13px; font-weight: 700; }
        .cb-tab.active { background: #0b70d7; color: #fff; border-color: #0b70d7; }
        .cb-card { background: #fff; border: 1px solid #dce5f1; border-radius: 8px; box-shadow: 0 4px 18px rgba(15,31,58,.06); padding: 18px; margin-bottom: 16px; }
        .cb-form { display: grid; grid-template-columns: 150px minmax(220px, 1fr) 140px auto; gap: 12px; align-items: end; }
        .cb-field label { display: block; margin-bottom: 5px; font-size: 12px; color: #42536b; font-weight: 800; text-transform: uppercase; }
        .cb-field input { height: 40px; }
        .cb-status { display: flex; align-items: center; gap: 8px; height: 40px; font-weight: 700; }
        .cb-status input { width: 18px; height: 18px; }
        .cb-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .cb-filter { display: flex; gap: 10px; align-items: end; }
        .cb-filter input { max-width: 360px; height: 40px; }
        .cb-table { box-shadow: none; margin-top: 10px; }
        .cb-table td:last-child, .cb-table th:last-child { width: 190px; text-align: right; }
        .cb-badge { display: inline-flex; padding: 4px 9px; border-radius: 999px; font-size: 12px; font-weight: 800; }
        .cb-badge.on { background: #dff7ea; color: #147d52; }
        .cb-badge.off { background: #f8d7da; color: #842029; }
        .cb-message { padding: 10px 12px; border-radius: 6px; margin-bottom: 14px; font-weight: 700; }
        .cb-message.success { background: #d1e7dd; color: #0f5132; border: 1px solid #a3cfbb; }
        .cb-message.error { background: #f8d7da; color: #842029; border: 1px solid #f1aeb5; }
        @media (max-width: 980px) {
            .cb-shell { margin-left: 0; }
            .cb-form { grid-template-columns: 1fr; }
            .cb-header { align-items: stretch; flex-direction: column; }
        }
    </style>
</head>
<body>
<?php $rf_menu_href_prefix = '../'; include __DIR__ . '/../menu.php'; ?>
<main class="cb-shell">
    <div class="cb-header">
        <div class="cb-title">
            <span>Cadastros Basicos</span>
            <h1><?php echo cb_h($title); ?></h1>
        </div>
        <form method="get" class="cb-filter">
            <input type="hidden" name="tipo" value="<?php echo cb_h($catalog); ?>">
            <div class="cb-field">
                <label>Pesquisar/Filtro</label>
                <input name="q" value="<?php echo cb_h($search); ?>" placeholder="Codigo, nome ou descricao">
            </div>
            <button class="btn btn-primary" type="submit">Pesquisar</button>
            <a class="btn btn-secondary" href="?tipo=<?php echo cb_h($catalog); ?>">Limpar</a>
        </form>
    </div>

    <nav class="cb-tabs">
        <?php foreach ($configs as $key => $config): ?>
            <a class="cb-tab <?php echo $key === $catalog ? 'active' : ''; ?>" href="?tipo=<?php echo cb_h($key); ?>"><?php echo cb_h($config['label']); ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($message): ?><div class="cb-message <?php echo cb_h($message_type); ?>"><?php echo cb_h($message); ?></div><?php endif; ?>

    <section class="cb-card">
        <form method="post" class="cb-form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo cb_h($edit_row['id'] ?? ''); ?>">
            <div class="cb-field">
                <label>Codigo</label>
                <input name="codigo" value="<?php echo cb_h($edit_row['codigo'] ?? ''); ?>" placeholder="Automatico">
            </div>
            <div class="cb-field">
                <label>Nome/Descricao</label>
                <input name="nome" required value="<?php echo cb_h($edit_row['nome'] ?? ''); ?>" placeholder="Novo registro">
                <input type="hidden" name="descricao" value="<?php echo cb_h($edit_row['descricao'] ?? ''); ?>">
            </div>
            <label class="cb-status">
                <input type="checkbox" name="ativo" <?php echo (int)($edit_row['ativo'] ?? 1) === 1 ? 'checked' : ''; ?>>
                Ativo
            </label>
            <div class="cb-actions">
                <button class="btn btn-success" type="submit"><?php echo $edit_row ? 'Salvar' : 'Novo'; ?></button>
                <?php if ($edit_row): ?><a class="btn btn-secondary" href="?tipo=<?php echo cb_h($catalog); ?>">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="cb-card">
        <table class="cb-table">
            <thead>
                <tr><th>Codigo</th><th>Nome/Descricao</th><th>Status</th><th>Acoes</th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="4" class="text-center text-muted">Nenhum registro encontrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo cb_h($row['codigo'] ?? ''); ?></td>
                    <td><?php echo cb_h($row['nome'] ?? ''); ?></td>
                    <td><span class="cb-badge <?php echo (int)($row['ativo'] ?? 1) === 1 ? 'on' : 'off'; ?>"><?php echo (int)($row['ativo'] ?? 1) === 1 ? 'Ativo' : 'Inativo'; ?></span></td>
                    <td>
                        <a class="btn btn-info btn-sm" href="?tipo=<?php echo cb_h($catalog); ?>&edit=<?php echo cb_h($row['id'] ?? ''); ?>">Editar</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Inativar este registro?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo cb_h($row['id'] ?? ''); ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>

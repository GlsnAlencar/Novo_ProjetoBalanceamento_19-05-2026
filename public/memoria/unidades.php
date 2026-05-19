<?php
// Gerenciamento de Unidades
session_start();
include 'data_store.php';
$_SESSION['unidades'] = load_json_data('unidades');

// Adicionar unidade
if (isset($_POST['adicionar_unidade']) && !empty(trim($_POST['nome_unidade']))) {
    $nome = trim($_POST['nome_unidade']);
    $peso_padrao = isset($_POST['peso_padrao']) ? floatval($_POST['peso_padrao']) : 0;
    $observacao = trim($_POST['observacao'] ?? '');
    
    if ($peso_padrao <= 0) {
        $erro = "❌ Peso padrão deve ser um número maior que zero!";
    } else {
        $existe = false;
        foreach ($_SESSION['unidades'] as $unidade) {
            if ($unidade['nome'] === $nome) {
                $existe = true;
                break;
            }
        }
        if (!$existe) {
            $_SESSION['unidades'][] = [
                'nome' => $nome,
                'peso_padrao' => $peso_padrao,
                'observacao' => $observacao
            ];
            save_json_data('unidades', $_SESSION['unidades']);
            $sucesso = true;
        } else {
            $erro = "⚠️ Unidade já existe!";
        }
    }
}

// Atualizar unidade
if (isset($_POST['atualizar_unidade']) && isset($_POST['unidade_index'])) {
    $index = (int)$_POST['unidade_index'];
    $peso_padrao = isset($_POST['peso_padrao_edit']) ? floatval($_POST['peso_padrao_edit']) : 0;
    $observacao = trim($_POST['observacao_edit'] ?? '');
    
    if (isset($_SESSION['unidades'][$index])) {
        if ($peso_padrao <= 0) {
            $erro = "❌ Peso padrão deve ser um número maior que zero!";
        } else {
            $_SESSION['unidades'][$index] = [
                'nome' => trim($_POST['nome_unidade_edit'] ?? ''),
                'peso_padrao' => $peso_padrao,
                'observacao' => $observacao
            ];
            save_json_data('unidades', $_SESSION['unidades']);
            $sucesso = true;
        }
    }
}

// Remover unidade
if (isset($_GET['remover_unidade']) && is_numeric($_GET['remover_unidade'])) {
    $index = (int)$_GET['remover_unidade'];
    if (isset($_SESSION['unidades'][$index])) {
        unset($_SESSION['unidades'][$index]);
        $_SESSION['unidades'] = array_values($_SESSION['unidades']);
        save_json_data('unidades', $_SESSION['unidades']);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Unidades</title>
    <?php include 'menu.php'; ?>
</head>
<body>
    <div class="content">
        <div class="container">
            <h1>📦 Gerenciamento de Unidades <span class="badge badge-primary"><?php echo count($_SESSION['unidades'] ?? []); ?></span></h1>

            <?php if (isset($sucesso) && $sucesso): ?>
                <div class="success-message">
                    ✅ Operação realizada com sucesso!
                </div>
            <?php endif; ?>

            <?php if (isset($erro)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <!-- Seção Adicionar -->
            <div class="form-section">
                <h3>➕ Adicionar Nova Unidade</h3>
                <form method="post" class="form-grid full-width">
                    <div class="form-group">
                        <label for="nome_unidade" class="required">Nome da Unidade</label>
                        <input type="text" id="nome_unidade" name="nome_unidade" placeholder="Ex: Caixa, Contentor, Palet" required>
                    </div>

                    <div class="form-group">
                        <label for="peso_padrao" class="required">Peso Padrão (kg)</label>
                        <input type="number" id="peso_padrao" name="peso_padrao" step="0.01" min="0.01" placeholder="Ex: 2.5" required>
                    </div>

                    <div class="form-group">
                        <label for="observacao">Observação (opcional)</label>
                        <textarea id="observacao" name="observacao" rows="3" placeholder="Informações adicionais sobre a unidade..."></textarea>
                    </div>

                    <button type="submit" name="adicionar_unidade" class="btn btn-primary btn-lg">➕ Adicionar Unidade</button>
                </form>
            </div>

            <!-- Listagem -->
            <h2 style="margin-top: 40px;">📋 Unidades Cadastradas</h2>
            <?php if (empty($_SESSION['unidades'])): ?>
                <div class="empty-message">
                    📭 Nenhuma unidade cadastrada ainda.<br>
                    Adicione uma na seção acima para começar! 🚀
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th width="30%">Nome</th>
                            <th width="20%">Peso Padrão</th>
                            <th width="30%">Observação</th>
                            <th width="20%">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['unidades'] as $index => $unidade): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($unidade['nome']); ?></strong></td>
                            <td><span class="badge badge-info"><?php echo number_format($unidade['peso_padrao'], 2, ',', '.'); ?> kg</span></td>
                            <td><?php echo htmlspecialchars($unidade['observacao']); ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="unidade_index" value="<?php echo $index; ?>">
                                    <input type="hidden" name="nome_unidade_edit" value="<?php echo htmlspecialchars($unidade['nome']); ?>">
                                    <input type="hidden" name="peso_padrao_edit" value="<?php echo htmlspecialchars($unidade['peso_padrao']); ?>">
                                    <input type="hidden" name="observacao_edit" value="<?php echo htmlspecialchars($unidade['observacao']); ?>">
                                    <button type="submit" name="atualizar_unidade" class="btn btn-sm btn-info" title="Atualizar">✏️</button>
                                </form>
                                <a href="?remover_unidade=<?php echo $index; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Remover esta unidade?')" title="Remover">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div style="margin-top: 40px;">
                <a href="index.php" class="btn btn-secondary">← Voltar ao Fluxo</a>
            </div>
        </div>
    </div>
</body>
</html>
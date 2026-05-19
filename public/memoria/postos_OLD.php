<?php
// Gerenciamento de Postos
session_start();
include 'data_store.php';
$_SESSION['postos'] = load_json_data('postos');

// Adicionar posto
if (isset($_POST['adicionar_posto']) && !empty(trim($_POST['nome_posto']))) {
    $nome = trim($_POST['nome_posto']);
    $existe = false;
    foreach ($_SESSION['postos'] as $posto) {
        if ($posto['nome'] === $nome) {
            $existe = true;
            break;
        }
    }
    if (!$existe) {
        $_SESSION['postos'][] = ['nome' => $nome, 'configs' => [], 'detalhes' => []];
        save_json_data('postos', $_SESSION['postos']);
    }
}

// Atualizar posto
if (isset($_POST['atualizar_posto']) && isset($_POST['posto_index'])) {
    $index = (int)$_POST['posto_index'];
    if (isset($_SESSION['postos'][$index])) {
        $_SESSION['postos'][$index]['nome'] = trim($_POST['nome_posto_edit'] ?? '');
        save_json_data('postos', $_SESSION['postos']);
    }
}

// Remover posto
if (isset($_GET['remover_posto']) && is_numeric($_GET['remover_posto'])) {
    $index = (int)$_GET['remover_posto'];
    if (isset($_SESSION['postos'][$index])) {
        unset($_SESSION['postos'][$index]);
        $_SESSION['postos'] = array_values($_SESSION['postos']);
        save_json_data('postos', $_SESSION['postos']);
    }
    header('Location: postos.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Postos</title>
    <?php include 'menu.php'; ?>
</head>
<body>
    <div class="content">
        <h1>Gerenciamento de Postos</h1>
        <form method="post">
            <label for="nome_posto">Nome do Posto:</label>
            <input type="text" id="nome_posto" name="nome_posto" required>
            <button type="submit" name="adicionar_posto">Adicionar Posto</button>
        </form>

        <h2>Postos Cadastrados</h2>
        <?php if (empty($_SESSION['postos'])): ?>
            <p>Nenhum posto cadastrado ainda.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_SESSION['postos'] as $index => $posto): ?>
                        <tr>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="posto_index" value="<?php echo $index; ?>">
                                    <input type="text" name="nome_posto_edit" value="<?php echo htmlspecialchars($posto['nome']); ?>" required style="width: 200px;">
                                    <button type="submit" name="atualizar_posto">Atualizar</button>
                                </form>
                            </td>
                            <td><a href="?remover_posto=<?php echo $index; ?>" onclick="return confirm('Remover este posto?')">Remover</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
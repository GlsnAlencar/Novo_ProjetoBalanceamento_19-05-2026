<?php
// Gerenciamento de Recursos/Pessoas por Posto
session_start();
include 'data_store.php';

// Carregar dados
$linhas_json = load_json_data('linhas');

// Parâmetros
$linha_id = isset($_GET['linha']) ? $_GET['linha'] : null;
$post_index = isset($_GET['post']) ? (int)$_GET['post'] : null;  // Novo: índice do posto específico
$back_page = isset($_GET['back']) ? $_GET['back'] : 'postos'; // Página anterior (padrão: postos)

// Encontrar linha
$linha_selecionada = null;
$linha_key = null;
foreach ($linhas_json as $key => $linha) {
    if ($linha['id'] === $linha_id) {
        $linha_selecionada = $linha;
        $linha_key = $key;
        break;
    }
}

if ($linha_selecionada === null) {
    die('❌ Linha não encontrada');
}

// ========== ADICIONAR/ATUALIZAR NÚMERO DE PESSOAS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_pessoas'])) {
    $post_idx = (int)($_POST['post_index'] ?? -1);
    $num_pessoas = isset($_POST['num_pessoas']) ? (int)$_POST['num_pessoas'] : 0;
    
    if ($post_idx >= 0 && $post_idx < count($linha_selecionada['postos']) && $num_pessoas >= 1) {
        if (!isset($linhas_json[$linha_key]['postos'][$post_idx]['recursos'])) {
            $linhas_json[$linha_key]['postos'][$post_idx]['recursos'] = [];
        }
        
        $linhas_json[$linha_key]['postos'][$post_idx]['recursos']['num_pessoas'] = $num_pessoas;
        save_json_data('linhas', $linhas_json);
        
        $redirect_url = 'recursos.php?linha=' . urlencode($linha_id) . '&sucesso=1';
        if ($post_index !== null) {
            $redirect_url .= '&post=' . urlencode($post_index);
        }
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Determinar quais postos exibir
$postos_para_exibir = [];
if ($post_index !== null && isset($linha_selecionada['postos'][$post_index])) {
    // Se um post específico foi solicitado
    $postos_para_exibir[$post_index] = $linha_selecionada['postos'][$post_index];
    $modo_exibicao = 'single';
} else {
    // Todos os postos da linha
    $postos_para_exibir = $linha_selecionada['postos'];
    $modo_exibicao = 'all';
}

$sucesso = isset($_GET['sucesso']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recursos - Alocação de Pessoas</title>
    <?php include 'menu.php'; ?>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f2f5;
            color: #333;
        }

        .content {
            margin-left: 290px;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
        }

        .header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 24px;
        }

        .header p {
            margin: 5px 0 0 0;
            color: #6c757d;
            font-size: 14px;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            margin-top: 20px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        .inline-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .inline-form input {
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 13px;
            width: 100px;
        }

        .inline-form button {
            padding: 8px 15px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
        }

        .inline-form button:hover {
            background-color: #218838;
        }

        .empty-message {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            text-align: center;
            color: #6c757d;
        }

        .back-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .back-button {
            padding: 10px 15px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
            display: inline-block;
        }

        .back-button:hover {
            background-color: #5a6268;
        }

        .back-button.primary {
            background-color: #007bff;
        }

        .back-button.primary:hover {
            background-color: #0056b3;
        }

        h2 {
            color: #2c3e50;
            font-size: 18px;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }

        .info-box {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
        }
    </style>
</head>

<body>
    <div class="content">
        <div class="container">
            <div class="header">
                <h1>👥 Recursos - Alocação de Pessoas</h1>
                <p>Linha: <strong><?php echo htmlspecialchars($linha_selecionada['nome']); ?></strong>
                <?php if ($modo_exibicao === 'single' && isset($linha_selecionada['postos'][$post_index])): ?>
                    | Posto: <strong><?php echo htmlspecialchars($linha_selecionada['postos'][$post_index]['nome']); ?></strong>
                <?php endif; ?>
                </p>
            </div>

            <?php if ($sucesso): ?>
            <div class="success-message">
                ✓ Número de pessoas atualizado com sucesso!
            </div>
            <?php endif; ?>

            <div class="info-box">
                💡 Configure o número de pessoas alocadas <?php echo ($modo_exibicao === 'single') ? 'neste posto' : 'em cada posto'; ?> para cálculos de produtividade e balanceamento.
            </div>

            <h2>📋 Alocação de Pessoas <?php echo ($modo_exibicao === 'single') ? 'do Posto' : 'por Posto'; ?></h2>

            <?php if (empty($postos_para_exibir)): ?>
                <div class="empty-message">
                    Nenhum posto cadastrado ainda. Crie postos primeiro! 🚀
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nome do Posto</th>
                            <th>Número de Pessoas</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($postos_para_exibir as $idx => $posto): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($posto['nome']); ?></strong></td>
                            <td>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="post_index" value="<?php echo $idx; ?>">
                                    <input type="number" name="num_pessoas" 
                                           value="<?php echo htmlspecialchars($posto['recursos']['num_pessoas'] ?? 1); ?>" 
                                           min="1" max="50" required>
                                    <button type="submit" name="atualizar_pessoas">✓ Salvar</button>
                                </form>
                            </td>
                            <td>
                                <span style="color: #6c757d; font-size: 13px;">
                                    <?php 
                                    $num = $posto['recursos']['num_pessoas'] ?? 0;
                                    if ($num >= 1) {
                                        echo "👤 " . $num . " pessoa" . ($num > 1 ? "s" : "");
                                    } else {
                                        echo "⚠️ Não definido";
                                    }
                                    ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="back-buttons">
                <a href="<?php 
                    $back_url = htmlspecialchars($back_page) . '.php?linha=' . urlencode($linha_id);
                    if ($post_index !== null && $back_page === 'atividades_posto') {
                        $back_url .= '&post=' . urlencode($post_index);
                    }
                    echo $back_url;
                ?>" class="back-button">
                    ← Voltar
                </a>
                <a href="index.php?linha=<?php echo urlencode($linha_id); ?>&back=recursos" class="back-button primary">
                    ↑ Voltar ao Fluxo
                </a>
            </div>
        </div>
    </div>
</body>
</html>

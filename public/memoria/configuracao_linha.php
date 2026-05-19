<?php
session_start();
include 'data_store.php';

// Carregar dados das linhas
$linhas_json = load_json_data('linhas');

// Parâmetro de linha
$linha_id = isset($_GET['linha']) ? $_GET['linha'] : 'linha1';

// Encontrar linha selecionada
$linha_selecionada = null;
foreach ($linhas_json as $linha) {
    if ($linha['id'] === $linha_id) {
        $linha_selecionada = $linha;
        break;
    }
}

// Usar primeira linha se não encontrar
if ($linha_selecionada === null && !empty($linhas_json)) {
    $linha_selecionada = $linhas_json[0];
    $linha_id = $linhas_json[0]['id'];
}

// Verificar se existe linha
if ($linha_selecionada === null) {
    die('Nenhuma linha cadastrada');
}

// Preparar dados dos postos com número de pessoas
$dados_postos = [];
$total_pessoas = 0;

if (isset($linha_selecionada['postos']) && is_array($linha_selecionada['postos'])) {
    foreach ($linha_selecionada['postos'] as $idx => $posto) {
        $num_pessoas = isset($posto['recursos']['num_pessoas']) ? $posto['recursos']['num_pessoas'] : 0;
        $total_pessoas += $num_pessoas;
        
        $dados_postos[] = [
            'indice' => $idx,
            'nome' => $posto['nome'],
            'num_pessoas' => $num_pessoas
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração da Linha</title>
    <?php include 'menu.php'; ?>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        
        .content {
            margin-left: 290px;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            background-color: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        h1 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 24px;
        }

        .linha-info {
            color: #666;
            font-size: 13px;
            margin-bottom: 20px;
        }

        h2 {
            margin: 20px 0 15px 0;
            color: #555;
            font-size: 16px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        .info-box {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #2196F3;
            margin-bottom: 20px;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background-color: #2196F3;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ccc;
        }

        td {
            padding: 10px 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tbody tr:hover {
            background-color: #f0f8ff;
        }

        .pessoas-cell {
            background-color: #fffbea;
            font-weight: bold;
            font-size: 15px;
            color: #cc6600;
            text-align: center;
        }

        .empty-message {
            background-color: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            color: #856404;
            text-align: center;
        }

        .link {
            color: #2196F3;
            text-decoration: none;
            font-size: 12px;
        }

        .link:hover {
            text-decoration: underline;
        }

        .back-button {
            display: inline-block;
            padding: 10px 15px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
            margin-right: 10px;
            transition: background-color 0.3s ease;
        }

        .back-button:hover {
            background-color: #5a6268;
        }

        .back-button.primary {
            background-color: #2196F3;
        }

        .back-button.primary:hover {
            background-color: #1976D2;
        }

        .summary {
            background-color: #f0f0f0;
            padding: 10px 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="container">
            <h1>⚙️ Configuração da Linha</h1>
            <div class="linha-info">
                Linha: <strong><?php echo htmlspecialchars($linha_selecionada['nome']); ?></strong>
            </div>

            <div class="info-box">
                <strong>ℹ️ Informação:</strong> Os dados abaixo são recebidos automaticamente da tela "Fluxo de Processo". 
                Para alterar o número de pessoas, acesse o Fluxo de Processo.
            </div>

            <h2>📊 Alocação de Pessoas por Posto</h2>

            <?php if (empty($dados_postos)): ?>
                <div class="empty-message">
                    Nenhum posto cadastrado nesta linha. Crie postos no Fluxo de Processo.
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th width="50%">Nome do Posto</th>
                            <th width="25%">👥 Pessoas</th>
                            <th width="25%">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados_postos as $posto): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($posto['nome']); ?></strong>
                            </td>
                            <td class="pessoas-cell">
                                <?php echo $posto['num_pessoas']; ?>
                            </td>
                            <td>
                                <a href="index.php?linha=<?php echo urlencode($linha_id); ?>" class="link">Ver no Fluxo</a>
                                |
                                <a href="recursos.php?linha=<?php echo urlencode($linha_id); ?>&post=<?php echo $posto['indice']; ?>" class="link">Editar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="summary">TOTAL DE PESSOAS</td>
                            <td class="summary pessoas-cell"><?php echo $total_pessoas; ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>

            <h2>Navegação</h2>
            <div>
                <a href="index.php?linha=<?php echo urlencode($linha_id); ?>" class="back-button primary">📈 Fluxo de Processo</a>
                <a href="postos.php" class="back-button">📍 Gerenciar Postos</a>
                <a href="menu.php" class="back-button">↑ Menu Principal</a>
            </div>
        </div>
    </div>
</body>
</html>

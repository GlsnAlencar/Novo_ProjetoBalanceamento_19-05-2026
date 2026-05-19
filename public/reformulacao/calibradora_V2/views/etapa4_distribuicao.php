<?php
/**
 * ETAPA 4: Distribuição do Lote
 * 
 * Tela isolada para registrar os gramas/percentuais produzidos pela calibradora.
 */

require_once __DIR__ . '/../bootstrap.php';
$service = new CalbradoraService($data_dir);
$controller = new CalbradoraController($service);

$message = '';
$message_type = '';

// Obter lotes e configurações
$result = $controller->processarRequisicao('obter_lotes');
$lotes = $result['dados'] ?? [];

$result = $controller->processarRequisicao('obter_configuracoes');
$configuracoes = $result['dados'] ?? [];

$distribuicao_selecionada = null;
$lote_selecionado = null;
$config_selecionada = null;

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'criar_distribuicao') {
        $lote_id = (int)($_POST['lote_id'] ?? 0);
        $config_id = (int)($_POST['configuracao_embalamento_id'] ?? 0);

        $result = $controller->processarRequisicao('criar_distribuicao', [
            'lote_id' => $lote_id,
            'configuracao_embalamento_id' => $config_id
        ]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';

        if ($result['sucesso']) {
            header('Location: ?lote_id=' . $lote_id);
            exit;
        }
    } elseif ($action === 'atualizar_distribuicao') {
        $dist_id = (int)($_POST['distribuicao_id'] ?? 0);

        // Processar itens
        $itens = [];
        $grs = $_POST['gr'] ?? [];
        $descricoes = $_POST['descricao'] ?? [];
        $faixas = $_POST['faixa_peso'] ?? [];
        $produtos = $_POST['produto_operacional'] ?? [];
        $gramas_list = $_POST['gramas'] ?? [];

        foreach ($grs as $idx => $gr) {
            if (!empty($gr)) {
                $itens[] = [
                    'gr' => (int)$gr,
                    'descricao' => trim($descricoes[$idx] ?? ''),
                    'faixa_peso' => trim($faixas[$idx] ?? ''),
                    'produto_operacional' => trim($produtos[$idx] ?? ''),
                    'gramas' => (float)($gramas_list[$idx] ?? 0),
                    'percentual' => 0.0
                ];
            }
        }

        $result = $controller->processarRequisicao('atualizar_distribuicao', [
            'id' => $dist_id,
            'itens' => $itens
        ]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    } elseif ($action === 'salvar_distribuicao') {
        $result = $controller->processarRequisicao('salvar_distribuicao', [
            'id' => (int)($_POST['id'] ?? 0)
        ]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    }
}

// Selecionar lote se houver parâmetro
if (isset($_GET['lote_id'])) {
    $lote_id = (int)$_GET['lote_id'];
    $result = $controller->processarRequisicao('obter_lote', ['id' => $lote_id]);
    if ($result['sucesso']) {
        $lote_selecionado = $result['dados'];

        // Obter distribuição deste lote
        $result = $controller->processarRequisicao('obter_distribuicao', ['id' => $lote_id]);
        if ($result['sucesso']) {
            $distribuicao_selecionada = $result['dados'];
        }

        // Obter configuração associada
        if ($lote_selecionado['configuracao_embalamento_id'] > 0) {
            $result = $controller->processarRequisicao('obter_configuracao', ['id' => $lote_selecionado['configuracao_embalamento_id']]);
            if ($result['sucesso']) {
                $config_selecionada = $result['dados'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribuição do Lote - Calibradora</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h1 {
            color: #1f6feb;
            margin-bottom: 20px;
            border-bottom: 2px solid #1f6feb;
            padding-bottom: 10px;
        }

        h2 {
            color: #333;
            margin-top: 20px;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .message {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: none;
        }

        .message.success {
            display: block;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            display: block;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        select:focus {
            outline: none;
            border-color: #1f6feb;
            box-shadow: 0 0 4px rgba(31, 111, 235, 0.3);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        button {
            padding: 10px 20px;
            background-color: #1f6feb;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }

        button:hover {
            background-color: #0d47a1;
        }

        button.save {
            background-color: #28a745;
        }

        button.save:hover {
            background-color: #218838;
        }

        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #1f6feb;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        thead {
            background-color: #f8f9fa;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            font-weight: bold;
            color: #333;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        input[type="number"] {
            width: 100%;
        }

        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }

        .breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
        }

        .breadcrumb a {
            color: #1f6feb;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }
    </style>
    <script>
        function atualizarPercentuais() {
            const inputs = document.querySelectorAll('.gramas-input');
            let total = 0;

            // Calcular total
            inputs.forEach(input => {
                total += parseFloat(input.value || 0);
            });

            // Atualizar percentuais
            inputs.forEach(input => {
                const row = input.closest('tr');
                const percentualCell = row.querySelector('.percentual-cell');
                const percentual = total > 0 ? ((parseFloat(input.value || 0) / total) * 100) : 0;
                percentualCell.textContent = percentual.toFixed(2) + '%';
            });

            // Atualizar total
            document.getElementById('total-gramas').textContent = total.toFixed(2);

            // Verificar se suma 100%
            let totalPercentual = 0;
            document.querySelectorAll('.percentual-cell').forEach(cell => {
                totalPercentual += parseFloat(cell.textContent || 0);
            });
            document.getElementById('total-percentual').textContent = totalPercentual.toFixed(2);

            // Avisar se não soma 100%
            const alertDiv = document.getElementById('percentual-alert');
            if (Math.abs(totalPercentual - 100.0) > 0.01) {
                alertDiv.style.display = 'block';
            } else {
                alertDiv.style.display = 'none';
            }
        }
    </script>
</head>
<body>
<div class="container">
    <div class="breadcrumb">
        <a href="../">← Voltar para Calibradora</a>
    </div>

    <h1>Distribuição do Lote</h1>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <h2>Selecionar Lote</h2>
    <div class="form-row">
        <form method="GET" action="">
            <div class="form-group">
                <label for="lote_id">Lote *</label>
                <select id="lote_id" name="lote_id" onchange="this.form.submit();">
                    <option value="">-- Selecione um lote --</option>
                    <?php foreach ($lotes as $l): ?>
                        <option value="<?php echo (int)$l['id']; ?>" <?php echo (isset($_GET['lote_id']) && (int)$_GET['lote_id'] === (int)$l['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($l['controle']); ?> - <?php echo htmlspecialchars($l['programa'] ?: 'Sem programa'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($lote_selecionado): ?>
        <div class="info-box">
            <strong>Lote:</strong> <?php echo htmlspecialchars($lote_selecionado['controle']); ?><br>
            <strong>Programa:</strong> <?php echo htmlspecialchars($lote_selecionado['programa'] ?: '-'); ?><br>
            <strong>Partida:</strong> <?php echo htmlspecialchars($lote_selecionado['partida'] ?: '-'); ?>
        </div>

        <?php if ($config_selecionada && !$distribuicao_selecionada): ?>
            <h2>Criar Distribuição</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="criar_distribuicao">
                <input type="hidden" name="lote_id" value="<?php echo (int)$lote_selecionado['id']; ?>">
                <input type="hidden" name="configuracao_embalamento_id" value="<?php echo (int)$config_selecionada['id']; ?>">

                <p>Configuração associada: <strong><?php echo htmlspecialchars($config_selecionada['nome']); ?></strong></p>

                <button type="submit">Criar Distribuição</button>
            </form>
        <?php elseif ($distribuicao_selecionada && $config_selecionada): ?>
            <h2>Distribuição do Lote</h2>

            <div id="percentual-alert" style="background-color: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; margin-bottom: 15px; display: none;">
                <strong>⚠️ Aviso:</strong> Os percentuais não somam 100%. Verifique os valores.
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="atualizar_distribuicao">
                <input type="hidden" name="distribuicao_id" value="<?php echo (int)$distribuicao_selecionada['id']; ?>">

                <table>
                    <thead>
                    <tr>
                        <th style="width: 60px;">GR</th>
                        <th>Descrição</th>
                        <th>Faixa Peso</th>
                        <th>Produto Operacional</th>
                        <th style="width: 120px;">Gramas</th>
                        <th style="width: 100px;">Percentual</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $itens = $distribuicao_selecionada['itens'] ?? [];
                    if (empty($itens) && $config_selecionada) {
                        // Carregar itens da configuração
                        foreach ($config_selecionada['mapeamentos'] as $map) {
                            $itens[] = [
                                'gr' => $map['gr'],
                                'descricao' => $map['descricao'],
                                'faixa_peso' => '0-0',
                                'produto_operacional' => $map['produto_operacional'],
                                'gramas' => 0,
                                'percentual' => 0
                            ];
                        }
                    }

                    foreach ($itens as $idx => $item):
                        ?>
                        <tr>
                            <td><input type="number" name="gr[]" value="<?php echo (int)$item['gr']; ?>" readonly style="border: none; background: transparent;"></td>
                            <td><input type="text" name="descricao[]" value="<?php echo htmlspecialchars($item['descricao']); ?>" readonly style="border: none; background: transparent;"></td>
                            <td><input type="text" name="faixa_peso[]" value="<?php echo htmlspecialchars($item['faixa_peso']); ?>" readonly style="border: none; background: transparent;"></td>
                            <td><input type="text" name="produto_operacional[]" value="<?php echo htmlspecialchars($item['produto_operacional']); ?>" readonly style="border: none; background: transparent;"></td>
                            <td><input type="number" name="gramas[]" value="<?php echo (float)$item['gramas']; ?>" step="0.01" class="gramas-input" onchange="atualizarPercentuais();" onkeyup="atualizarPercentuais();"></td>
                            <td class="percentual-cell"><?php echo number_format((float)$item['percentual'], 2, '.', ''); ?>%</td>
                        </tr>
                    <?php endforeach; ?>

                    <tr class="total-row">
                        <td colspan="4" style="text-align: right;">TOTAL:</td>
                        <td><span id="total-gramas">0.00</span> g</td>
                        <td><span id="total-percentual">0.00</span>%</td>
                    </tr>
                    </tbody>
                </table>

                <div style="margin-top: 20px;">
                    <button type="submit">Atualizar Distribuição</button>
                    <button type="button" class="save" onclick="if(Math.abs(parseFloat(document.getElementById('total-percentual').textContent) - 100) < 0.01) { document.querySelector('input[name=\"action\"]').value = 'salvar_distribuicao'; this.form.submit(); } else { alert('Distribuição deve somar 100%'); }">Salvar Distribuição</button>
                </div>
            </form>

            <script>
                // Atualizar ao carregar página
                window.addEventListener('load', atualizarPercentuais);
            </script>
        <?php elseif (!$config_selecionada): ?>
            <p style="color: #dc3545;">O lote não tem uma configuração de embalamento associada. Configure o lote antes de criar a distribuição.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>

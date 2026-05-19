<?php
/**
 * ETAPA 5: Resultado Operacional
 * 
 * Tela isolada para gerar o perfil operacional do lote.
 */

require_once __DIR__ . '/../bootstrap.php';

// Obter todas as distribuições
$result = $controller->processarRequisicao('obter_lotes');
$lotes = $result['dados'] ?? [];

$resultado_operacional = null;
$distribuicao_selecionada = null;
$lote_selecionado = null;

// Se houver parâmetro GET, carregar a distribuição
if (isset($_GET['dist_id'])) {
    $dist_id = (int)$_GET['dist_id'];
    $result = $controller->processarRequisicao('obter_distribuicao', ['id' => $dist_id]);
    if ($result['sucesso']) {
        $distribuicao_selecionada = $result['dados'];

        // Gerar resultado operacional
        $result = $controller->processarRequisicao('resultado_operacional', ['distribuicao_id' => $dist_id]);
        if ($result['sucesso']) {
            $resultado_operacional = $result['dados'];
        }

        // Obter lote associado
        if ($distribuicao_selecionada['lote_id'] > 0) {
            $lote_result = $controller->processarRequisicao('obter_lote', ['id' => $distribuicao_selecionada['lote_id']]);
            if ($lote_result['sucesso']) {
                $lote_selecionado = $lote_result['dados'];
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
    <title>Resultado Operacional - Calibradora</title>
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
            max-width: 1200px;
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

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        select:focus {
            outline: none;
            border-color: #1f6feb;
            box-shadow: 0 0 4px rgba(31, 111, 235, 0.3);
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

        .percentual-cell {
            text-align: right;
        }

        .chart-container {
            margin-top: 40px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .bar {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .bar-label {
            min-width: 200px;
            font-weight: bold;
        }

        .bar-fill {
            flex: 1;
            background-color: #1f6feb;
            height: 30px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-weight: bold;
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

        .no-data {
            text-align: center;
            color: #999;
            padding: 40px 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="breadcrumb">
        <a href="../">← Voltar para Calibradora</a>
    </div>

    <h1>Resultado Operacional</h1>

    <h2>Selecionar Distribuição</h2>
    <form method="GET" action="">
        <div class="form-group">
            <label for="dist_id">Distribuição *</label>
            <select id="dist_id" name="dist_id" onchange="this.form.submit();">
                <option value="">-- Selecione uma distribuição --</option>
                <?php
                // Listar todas as distribuições salvas
                $dist_repo = new \CalbradoraModule\Repositories\DistribuicaoLoteRepository($data_dir);
                $todas_dist = $dist_repo->getAll();
                foreach ($todas_dist as $d):
                    // Obter lote associado para exibir nome amigável
                    $lote_ref = null;
                    foreach ($lotes as $l) {
                        if ($l['id'] === $d->lote_id) {
                            $lote_ref = $l;
                            break;
                        }
                    }
                    $display_name = $lote_ref ? htmlspecialchars($lote_ref['controle']) : 'Lote ID: ' . $d->lote_id;
                    ?>
                    <option value="<?php echo (int)$d->id; ?>" <?php echo (isset($_GET['dist_id']) && (int)$_GET['dist_id'] === (int)$d->id) ? 'selected' : ''; ?>>
                        <?php echo $display_name; ?> (Status: <?php echo ucfirst($d->status); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($distribuicao_selecionada && $lote_selecionado): ?>
        <div class="info-box">
            <strong>Lote:</strong> <?php echo htmlspecialchars($lote_selecionado['controle']); ?><br>
            <strong>Programa:</strong> <?php echo htmlspecialchars($lote_selecionado['programa'] ?: '-'); ?><br>
            <strong>Status da Distribuição:</strong> <span style="text-transform: capitalize;"><?php echo $distribuicao_selecionada['status']; ?></span><br>
            <strong>Total de Gramas:</strong> <?php echo number_format($distribuicao_selecionada['total_gramas'], 2, '.', ','); ?> g
        </div>

        <h2>Detalhes da Distribuição</h2>
        <table>
            <thead>
            <tr>
                <th>GR</th>
                <th>Descrição</th>
                <th>Faixa Peso</th>
                <th>Produto Operacional</th>
                <th>Gramas</th>
                <th>Percentual</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $total_percentual = 0;
            foreach ($distribuicao_selecionada['itens'] as $item):
                $total_percentual += (float)$item['percentual'];
                ?>
                <tr>
                    <td><?php echo (int)$item['gr']; ?></td>
                    <td><?php echo htmlspecialchars($item['descricao']); ?></td>
                    <td><?php echo htmlspecialchars($item['faixa_peso']); ?></td>
                    <td><strong><?php echo htmlspecialchars($item['produto_operacional']); ?></strong></td>
                    <td><?php echo number_format((float)$item['gramas'], 2, '.', ','); ?> g</td>
                    <td class="percentual-cell"><?php echo number_format((float)$item['percentual'], 2, '.', ','); ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($resultado_operacional): ?>
            <div class="chart-container">
                <h2>Perfil Operacional</h2>
                <p style="margin-bottom: 20px; color: #666;">Resumo de produtos gerados pela calibradora:</p>

                <?php foreach ($resultado_operacional as $produto => $percentual): ?>
                    <div class="bar">
                        <div class="bar-label"><?php echo htmlspecialchars($produto); ?></div>
                        <div class="bar-fill" style="width: <?php echo min(100, max(0, $percentual)); ?>%;">
                            <?php echo number_format($percentual, 2, '.', ','); ?>%
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2 style="margin-top: 40px;">Resumo Operacional</h2>
            <table>
                <thead>
                <tr>
                    <th>Produto Operacional</th>
                    <th style="text-align: right;">Percentual</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($resultado_operacional as $produto => $percentual): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($produto); ?></strong></td>
                        <td class="percentual-cell"><?php echo number_format($percentual, 2, '.', ','); ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                <p>Nenhum resultado operacional disponível para esta distribuição.</p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="no-data">
            <p>Selecione uma distribuição para visualizar o resultado operacional.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>

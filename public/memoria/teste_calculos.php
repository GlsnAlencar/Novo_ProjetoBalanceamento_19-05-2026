<?php
// Teste dos cálculos de indicadores
session_start();
include 'data_store.php';

$linhas_json = load_json_data('linhas');
$unidades_json = load_json_data('unidades');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Cálculos</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .linha-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .linha-title {
            font-weight: bold;
            color: #0056b3;
            font-size: 16px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            margin: 10px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        .metric {
            display: inline-block;
            background-color: #e7f3ff;
            padding: 10px 15px;
            border-radius: 4px;
            margin: 5px 10px 5px 0;
            border-left: 4px solid #007bff;
        }
        .metric strong {
            color: #0056b3;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Teste de Cálculos de Indicadores</h1>
        <p>Validação dos cálculos de tempo de ciclo, taxa de produção e alocação de pessoas.</p>
    </div>

    <?php
    foreach ($linhas_json as $linha_index => $linha) {
        $tempo_ciclo = 0;
        $total_kg_produzido = 0;
        $total_pessoas = 0;
        $total_tempo_consolidado = 0;
        $total_atividades = 0;

        echo '<div class="container">';
        echo '<div class="linha-section">';
        echo '<div class="linha-title">📍 ' . htmlspecialchars($linha['nome']) . '</div>';

        if (empty($linha['postos'])) {
            echo '<p style="color: #6c757d;">Nenhum posto configurado.</p>';
        } else {
            echo '<table>';
            echo '<thead><tr>';
            echo '<th>Posto</th>';
            echo '<th>Atividade</th>';
            echo '<th>Quantidade</th>';
            echo '<th>Unidade</th>';
            echo '<th>Peso (kg)</th>';
            echo '<th>Tempo Total (s)</th>';
            echo '<th>Total Kg</th>';
            echo '<th>Pessoas</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($linha['postos'] as $post_idx => $posto) {
                $num_pessoas = $posto['recursos']['num_pessoas'] ?? 0;
                $total_pessoas += $num_pessoas;

                if (empty($posto['atividades'])) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($posto['nome']) . '</strong></td>';
                    echo '<td colspan="6" style="color: #6c757d; font-style: italic;">Nenhuma atividade</td>';
                    echo '<td>' . $num_pessoas . '</td>';
                    echo '</tr>';
                } else {
                    $primeiro = true;
                    foreach ($posto['atividades'] as $at_idx => $atividade) {
                        $quantidade = $atividade['quantidade'];
                        $unidade = $atividade['unidade'];
                        $peso_unidade = floatval($atividade['peso_unidade']);
                        $tempo_total = floatval($atividade['tempo_total']);
                        $tempo_por_unidade = floatval($atividade['tempo_por_unidade']);
                        $tempo_por_peso = floatval($atividade['tempo_por_peso']);

                        $kg_atividade = $quantidade * $peso_unidade;
                        $total_kg_produzido += $kg_atividade;
                        $total_tempo_consolidado += $tempo_total;
                        $total_atividades++;

                        if ($tempo_total > $tempo_ciclo) {
                            $tempo_ciclo = $tempo_total;
                        }

                        echo '<tr>';
                        if ($primeiro) {
                            echo '<td rowspan="' . count($posto['atividades']) . '"><strong>' . htmlspecialchars($posto['nome']) . '</strong></td>';
                            $primeiro = false;
                        }
                        echo '<td>' . htmlspecialchars($atividade['descricao']) . '</td>';
                        echo '<td>' . $quantidade . '</td>';
                        echo '<td>' . htmlspecialchars($unidade) . '</td>';
                        echo '<td>' . number_format($peso_unidade, 3, ',', '.') . ' kg</td>';
                        echo '<td>' . number_format($tempo_total, 2, ',', '.') . ' s</td>';
                        echo '<td>' . number_format($kg_atividade, 2, ',', '.') . ' kg</td>';
                        if ($primeiro) {
                            echo '<td rowspan="' . count($posto['atividades']) . '">' . $num_pessoas . '</td>';
                        }
                        echo '</tr>';
                    }
                }
            }

            echo '</tbody>';
            echo '</table>';
        }

        $taxa_producao_kg_min = ($tempo_ciclo > 0) ? round(($total_kg_produzido / $tempo_ciclo) * 60, 2) : 0;
        $taxa_producao_kg_hora = $taxa_producao_kg_min * 60;

        echo '<div style="margin-top: 15px;">';
        echo '<div class="metric"><strong>Total de Atividades:</strong> ' . $total_atividades . '</div>';
        echo '<div class="metric"><strong>Tempo de Ciclo (máximo):</strong> ' . number_format($tempo_ciclo, 2, ',', '.') . ' s</div>';
        echo '<div class="metric"><strong>Total de Kg Produzido:</strong> ' . number_format($total_kg_produzido, 2, ',', '.') . ' kg</div>';
        echo '<div class="metric"><strong>Taxa de Produção:</strong> ' . number_format($taxa_producao_kg_min, 2, ',', '.') . ' kg/min</div>';
        echo '<div class="metric"><strong>Taxa de Produção (hora):</strong> ' . number_format($taxa_producao_kg_hora, 2, ',', '.') . ' kg/h</div>';
        echo '<div class="metric"><strong>Total de Pessoas Alocadas:</strong> ' . $total_pessoas . ' 👥</div>';
        echo '<div class="metric"><strong>Tempo Consolidado (soma):</strong> ' . number_format($total_tempo_consolidado, 2, ',', '.') . ' s</div>';
        echo '</div>';

        if ($total_pessoas === 0 && !empty($linha['postos'])) {
            echo '<div class="warning">⚠️ Nenhuma pessoa alocada nesta linha. Configure em <strong>Recursos/Pessoas</strong>.</div>';
        }

        echo '</div>';
        echo '</div>';
    }
    ?>

    <div class="container">
        <h2>📊 Dados de Unidades Carregadas</h2>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Peso Padrão (kg)</th>
                    <th>Observação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($unidades_json as $unidade): ?>
                <tr>
                    <td><?php echo htmlspecialchars($unidade['nome']); ?></td>
                    <td><?php echo number_format($unidade['peso_padrao'], 3, ',', '.'); ?> kg</td>
                    <td><?php echo htmlspecialchars($unidade['observacao'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

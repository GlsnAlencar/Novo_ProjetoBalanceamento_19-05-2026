<?php
// Gerenciador de Atividades do Posto - Estrutura em Tabela com Cálculos Automáticos
session_start();
include 'data_store.php';

// Carregar dados
$linhas_json = load_json_data('linhas');
$unidades_json = load_json_data('unidades');

// Parâmetros
$linha_id = isset($_GET['linha']) ? $_GET['linha'] : null;
$post_index = isset($_GET['post']) ? (int)$_GET['post'] : null;
$back_page = isset($_GET['back']) ? $_GET['back'] : 'postos'; // Página anterior (padrão: postos)

// Validações
if (!$linha_id || $post_index === null) {
    die('❌ Parâmetros inválidos: linha e post são obrigatórios');
}

$linha_selecionada = null;
$linha_key = null;
foreach ($linhas_json as $key => $linha) {
    if ($linha['id'] === $linha_id) {
        $linha_selecionada = $linha;
        $linha_key = $key;
        break;
    }
}

if ($linha_selecionada === null || !isset($linha_selecionada['postos'][$post_index])) {
    die('❌ Posto não encontrado');
}

$posto = &$linhas_json[$linha_key]['postos'][$post_index];

// Inicializar atividades se não existir
if (!isset($posto['atividades']) || !is_array($posto['atividades'])) {
    $posto['atividades'] = [];
}

// ========== ADICIONAR ATIVIDADE ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_atividade'])) {
    $descricao = trim($_POST['descricao'] ?? '');
    $unidade_nome = trim($_POST['unidade'] ?? '');
    $quantidade = isset($_POST['quantidade']) ? floatval($_POST['quantidade']) : 0;
    $tempo_total = isset($_POST['tempo_total']) ? floatval($_POST['tempo_total']) : 0;

    if (!empty($descricao) && !empty($unidade_nome) && $quantidade > 0 && $tempo_total > 0) {
        // Buscar peso da unidade
        $peso_unidade = 0;
        foreach ($unidades_json as $un) {
            if ($un['nome'] === $unidade_nome) {
                $peso_unidade = floatval($un['peso_padrao'] ?? 0);
                break;
            }
        }

        // Cálculos
        $tempo_por_unidade = $tempo_total / $quantidade;
        $tempo_por_peso = $peso_unidade > 0 ? $tempo_por_unidade / $peso_unidade : 0;

        // Adicionar atividade
        $posto['atividades'][] = [
            'descricao' => $descricao,
            'unidade' => $unidade_nome,
            'quantidade' => $quantidade,
            'peso_unidade' => $peso_unidade,
            'tempo_total' => $tempo_total,
            'tempo_por_unidade' => round($tempo_por_unidade, 2),
            'tempo_por_peso' => round($tempo_por_peso, 4)
        ];

        save_json_data('linhas', $linhas_json);
        header('Location: atividades_posto.php?linha=' . urlencode((string)$linha_id) . '&post=' . $post_index . '&sucesso=1');
        exit;
    }
}

// ========== REMOVER ATIVIDADE ==========
if (isset($_GET['remover_atividade']) && is_numeric($_GET['remover_atividade'])) {
    $atividade_index = (int)$_GET['remover_atividade'];
    if (isset($posto['atividades'][$atividade_index])) {
        unset($posto['atividades'][$atividade_index]);
        $posto['atividades'] = array_values($posto['atividades']);
        save_json_data('linhas', $linhas_json);
        header('Location: atividades_posto.php?linha=' . urlencode($linha_id) . '&post=' . $post_index);
        exit;
    }
}

// ========== CALCULAR RESUMO DO POSTO ==========
$tempo_total_consolidado = 0;
$tempo_por_peso_consolidado = 0;
$quantidade_total = 0;

foreach ($posto['atividades'] as $atividade) {
    $tempo_total_consolidado += $atividade['tempo_total'];
    $tempo_por_peso_consolidado += $atividade['tempo_por_peso'] * $atividade['quantidade'];
    $quantidade_total += $atividade['quantidade'];
}

$tempo_por_peso_medio = $quantidade_total > 0 ? $tempo_por_peso_consolidado / $quantidade_total : 0;

// Buscar peso do contentor em unidades para calcular tempo/contentor
$peso_contentor = 0;
foreach ($unidades_json as $un) {
    if (strtolower(trim($un['nome'])) === 'contentor') {
        $peso_contentor = floatval($un['peso_padrao'] ?? 0);
        break;
    }
}
// tempo/contentor = tempo médio por kg × peso do contentor (válido para qualquer unidade)
$tempo_por_contentor = ($tempo_por_peso_medio > 0 && $peso_contentor > 0)
    ? $tempo_por_peso_medio * $peso_contentor
    : 0;

$sucesso = isset($_GET['sucesso']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atividades do Posto - <?php echo htmlspecialchars($posto['nome']); ?></title>
    <?php include 'menu.php'; ?>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f2f5;
            color: #333;
        }

        .container {
            margin-left: 290px;
            padding: 20px;
        }

        .header {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0 0 5px 0;
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

        .form-section {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .form-section h2 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 18px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }

        form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        form.full-width {
            grid-template-columns: 1fr;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #495057;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            font-family: Arial, sans-serif;
            box-sizing: border-box;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #0056b3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            margin-top: 0;
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

        tbody tr:last-child td {
            border-bottom: none;
        }

        .btn-remover {
            padding: 6px 10px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-remover:hover {
            background-color: #c82333;
        }

        .resumo {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .resumo h2 {
            margin-top: 0;
            font-size: 18px;
            border-bottom: 2px solid rgba(255,255,255,0.3);
            padding-bottom: 10px;
        }

        .resumo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .resumo-item {
            background-color: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid rgba(255,255,255,0.5);
        }

        .resumo-item strong {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            opacity: 0.9;
        }

        .resumo-item span {
            display: block;
            font-size: 24px;
            font-weight: bold;
        }

        .resumo-item small {
            display: block;
            font-size: 12px;
            margin-top: 8px;
            opacity: 0.8;
        }

        .empty-message {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            text-align: center;
            color: #6c757d;
            grid-column: 1 / -1;
        }

        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        .back-button:hover {
            background-color: #5a6268;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Cabeçalho -->
        <div class="header">
            <h1>⚙️ Atividades do Posto</h1>
            <p>Posto: <strong><?php echo htmlspecialchars($posto['nome']); ?></strong> 
               | Linha: <strong><?php echo htmlspecialchars($linha_selecionada['nome']); ?></strong></p>
        </div>

        <?php if ($sucesso): ?>
        <div class="success-message">
            ✓ Atividade adicionada com sucesso!
        </div>
        <?php endif; ?>

        <!-- Formulário para Adicionar Atividade -->
        <div class="form-section">
            <h2>➕ Adicionar Nova Atividade</h2>
            <form method="POST" class="full-width">
                <div>
                    <label for="descricao">Descrição da Atividade:</label>
                    <input type="text" id="descricao" name="descricao" placeholder="Ex: Empacotar itens" required>
                </div>

                <div>
                    <label for="unidade">Unidade:</label>
                    <select id="unidade" name="unidade" required>
                        <option value="">-- Selecione uma unidade --</option>
                        <?php foreach ($unidades_json as $un): ?>
                            <option value="<?php echo htmlspecialchars($un['nome']); ?>" 
                                    data-peso="<?php echo htmlspecialchars($un['peso_padrao'] ?? 0); ?>">
                                <?php echo htmlspecialchars($un['nome']); ?> 
                                (<?php echo htmlspecialchars($un['peso_padrao'] ?? 0); ?> kg)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="quantidade">Quantidade:</label>
                    <input type="number" id="quantidade" name="quantidade" step="0.01" min="0.01" placeholder="Ex: 100" required>
                </div>

                <div>
                    <label for="tempo_total">Tempo Total (segundos):</label>
                    <input type="number" id="tempo_total" name="tempo_total" step="0.1" min="0.1" placeholder="Ex: 3600" required>
                </div>

                <div class="form-actions">
                    <button type="submit" name="adicionar_atividade">➕ Adicionar Atividade</button>
                </div>
            </form>
        </div>

        <!-- Tabela de Atividades -->
        <div class="form-section">
            <h2>📋 Atividades do Posto</h2>

            <?php if (empty($posto['atividades'])): ?>
                <div class="empty-message">
                    Nenhuma atividade cadastrada ainda. Adicione uma acima! 🚀
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Unidade</th>
                            <th>Quantidade</th>
                            <th>Peso Unit.</th>
                            <th>Tempo Total (s)</th>
                            <th>Tempo/Unit. (s)</th>
                            <th>Tempo/Peso</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posto['atividades'] as $idx => $atividade): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($atividade['descricao']); ?></td>
                            <td><?php echo htmlspecialchars($atividade['unidade']); ?></td>
                            <td><?php echo number_format($atividade['quantidade'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($atividade['peso_unidade'], 2, ',', '.'); ?> kg</td>
                            <td><?php echo number_format($atividade['tempo_total'], 1, ',', '.'); ?></td>
                            <td><?php echo number_format($atividade['tempo_por_unidade'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($atividade['tempo_por_peso'], 4, ',', '.'); ?></td>
                            <td>
                                <a href="?linha=<?php echo urlencode($linha_id); ?>&post=<?php echo $post_index; ?>&remover_atividade=<?php echo $idx; ?>"
                                   class="btn-remover"
                                   onclick="return confirm('❌ Remover esta atividade?')">
                                   🗑️ Remover
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Resumo Consolidado do Posto -->
        <?php if (!empty($posto['atividades'])): ?>
        <div class="resumo">
            <h2>📊 Resumo Consolidado do Posto</h2>
            <div class="resumo-grid">
                <div class="resumo-item">
                    <strong>Total de Atividades</strong>
                    <span><?php echo count($posto['atividades']); ?></span>
                    <small>atividades cadastradas</small>
                </div>
                <div class="resumo-item">
                    <strong>Quantidade Total Processada</strong>
                    <span><?php echo number_format($quantidade_total, 2, ',', '.'); ?></span>
                    <small>unidades</small>
                </div>
                <div class="resumo-item">
                    <strong>Tempo Total Consolidado</strong>
                    <span><?php echo number_format($tempo_total_consolidado, 1, ',', '.'); ?> s</span>
                    <small>segundos</small>
                </div>
                <div class="resumo-item">
                    <strong>Tempo Médio por Peso</strong>
                    <span><?php echo number_format($tempo_por_peso_medio, 4, ',', '.'); ?></span>
                    <small>segundos por kg</small>
                </div>
                <div class="resumo-item">
                    <strong>Tempo/Contentor</strong>
                    <span><?php echo $tempo_por_contentor > 0 ? number_format($tempo_por_contentor, 2, ',', '.') . ' s' : '—'; ?></span>
                    <small>s/cont · base: <?php echo $peso_contentor > 0 ? number_format($peso_contentor, 0, ',', '.') . ' kg/cont' : 'contentor não cadastrado'; ?></small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <a href="<?php echo htmlspecialchars($back_page); ?>.php?<?php echo ($back_page === 'postos') ? 'setor_id' : 'linha'; ?>=<?php echo urlencode($linha_id); ?>" class="back-button" style="background-color: #6c757d;">
                ← Voltar
            </a>
            <a href="recursos.php?linha=<?php echo urlencode($linha_id); ?>&post=<?php echo $post_index; ?>&back=atividades_posto" class="back-button" style="background-color: #17a2b8;">
                👥 Configurar Recursos
            </a>
            <a href="index.php?linha=<?php echo urlencode($linha_id); ?>&back=atividades_posto" class="back-button">
                ↑ Voltar ao Fluxo
            </a>
        </div>
    </div>
</body>
</html>

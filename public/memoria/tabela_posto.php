<?php
// Tabela do Posto - Cadastro detalhado (Integrado com linhas.json)
session_start();
include 'data_store.php';

// Carregar dados das linhas
$linhas_json = load_json_data('linhas');
$unidades = load_json_data('unidades');
$categorias_atividade = load_json_data('categorias_atividade');
$tipos_item = load_json_data('tipos_item');

// Validar parâmetros
$linha_id = isset($_GET['linha']) ? $_GET['linha'] : 'linha1';
$post_index = isset($_GET['post']) ? (int)$_GET['post'] : null;

// Encontrar a linha selecionada
$linha_selecionada = null;
$linha_key = null;
foreach ($linhas_json as $key => $linha) {
    if ($linha['id'] === $linha_id) {
        $linha_selecionada = $linha;
        $linha_key = $key;
        break;
    }
}

// Validações
if ($linha_selecionada === null) {
    die('❌ Linha não encontrada: ' . htmlspecialchars($linha_id));
}

if ($post_index === null || !isset($linha_selecionada['postos'][$post_index])) {
    die('❌ Posto não encontrado no índice: ' . $post_index);
}

$posto = &$linhas_json[$linha_key]['postos'][$post_index];

// Inicializar detalhes se não existir
if (!isset($posto['detalhes']) || !is_array($posto['detalhes'])) {
    $posto['detalhes'] = [
        'unidade_tipica' => '',
        'quantidade_item' => '',
        'tempo_total' => '',
        'tempo_por_item' => '',
        'fator_correlacao' => '',
        'observacao' => '',
        'categoria_atividade' => '',
        'tipo_item' => ''
    ];
}

// ========== PROCESSAR FORMULÁRIO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['limpar_detalhes'])) {
    $quantidade_item = isset($_POST['quantidade_item']) ? floatval($_POST['quantidade_item']) : 0;
    $tempo_total = isset($_POST['tempo_total']) ? floatval($_POST['tempo_total']) : 0;
    $tempo_por_item = $quantidade_item > 0 ? round($tempo_total / $quantidade_item, 2) : '';

    $posto['detalhes'] = [
        'unidade_tipica' => trim($_POST['unidade_tipica'] ?? ''),
        'quantidade_item' => $quantidade_item !== 0 ? $quantidade_item : '',
        'tempo_total' => $tempo_total !== 0 ? $tempo_total : '',
        'tempo_por_item' => $tempo_por_item,
        'fator_correlacao' => trim($_POST['fator_correlacao'] ?? ''),
        'observacao' => trim($_POST['observacao'] ?? ''),
        'categoria_atividade' => trim($_POST['categoria_atividade'] ?? ''),
        'tipo_item' => trim($_POST['tipo_item'] ?? '')
    ];

    // Salvar em linhas.json
    save_json_data('linhas', $linhas_json);
    
    // Redirecionar com sucesso
    header('Location: tabela_posto.php?post=' . $post_index . '&linha=' . urlencode($linha_id) . '&sucesso=1');
    exit;
}

// ========== LIMPAR DETALHES ==========
if (isset($_POST['limpar_detalhes'])) {
    $posto['detalhes'] = [
        'unidade_tipica' => '',
        'quantidade_item' => '',
        'tempo_total' => '',
        'tempo_por_item' => '',
        'fator_correlacao' => '',
        'observacao' => '',
        'categoria_atividade' => '',
        'tipo_item' => ''
    ];
    save_json_data('linhas', $linhas_json);
    
    header('Location: tabela_posto.php?post=' . $post_index . '&linha=' . urlencode($linha_id) . '&sucesso=1');
    exit;
}

$sucesso = isset($_GET['sucesso']) ? true : false;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Posto - <?php echo htmlspecialchars($posto['nome'] ?? 'Desconhecido'); ?></title>
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
            max-width: 800px;
            margin: 20px auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-left: 290px;
        }

        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
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

        form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-block {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 20px;
        }

        .form-block h2 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 18px;
            color: #2c3e50;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
        }

        label:first-child {
            margin-top: 0;
        }

        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            font-family: Arial, sans-serif;
            box-sizing: border-box;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        button {
            padding: 12px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        button:hover {
            background-color: #0056b3;
        }

        button[name="limpar_detalhes"] {
            background-color: #dc3545;
        }

        button[name="limpar_detalhes"]:hover {
            background-color: #c82333;
        }

        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background-color: #5a6268;
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
    <div class="container">
        <div class="header">
            <h1>✏️ Editar Detalhes do Posto</h1>
            <p>Posto: <strong><?php echo htmlspecialchars($posto['nome'] ?? 'Desconhecido'); ?></strong> 
               | Linha: <strong><?php echo htmlspecialchars($linha_selecionada['nome']); ?></strong></p>
        </div>

        <?php if ($sucesso): ?>
        <div class="success-message">
            ✓ Detalhes salvos com sucesso!
        </div>
        <?php endif; ?>

        <div class="info-box">
            💡 Preencha os dados operacionais do posto para cálculos de balanceamento.
        </div>

        <form method="POST">
            <div class="form-block">
                <h2>📋 Configuração Principal</h2>

                <label for="unidade_tipica">Unidade Típica do Posto: <span style="color: #dc3545;">*</span></label>
                <select id="unidade_tipica" name="unidade_tipica">
                    <option value="">-- Selecione uma unidade --</option>
                    <?php
                    $unidade_selecionada = $posto['detalhes']['unidade_tipica'] ?? '';
                    foreach ($unidades as $unidade) {
                        $nome_unidade = $unidade['nome'] ?? '';
                        $selected = ($unidade_selecionada === $nome_unidade) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($nome_unidade) . '" ' . $selected . '>' . 
                             htmlspecialchars($nome_unidade) . '</option>';
                    }
                    ?>
                </select>

                <label for="quantidade_item">Quantidade do Item (unidades):</label>
                <input type="number" id="quantidade_item" name="quantidade_item" step="0.01" min="0" 
                       value="<?php echo htmlspecialchars((string)($posto['detalhes']['quantidade_item'] ?? '')); ?>" 
                       placeholder="Ex: 100">

                <label for="tempo_total">Tempo Total (minutos):</label>
                <input type="number" id="tempo_total" name="tempo_total" step="0.01" min="0" 
                       value="<?php echo htmlspecialchars((string)($posto['detalhes']['tempo_total'] ?? '')); ?>" 
                       placeholder="Ex: 50">

                <label for="tempo_por_item">Tempo por Item (min) - <em>Calculado automaticamente</em>:</label>
                <input type="text" id="tempo_por_item" name="tempo_por_item" 
                       value="<?php echo htmlspecialchars((string)($posto['detalhes']['tempo_por_item'] ?? '')); ?>" 
                       readonly style="background-color: #e9ecef;">

                <label for="fator_correlacao">Fator de Correlação (peso em kg):</label>
                <input type="number" id="fator_correlacao" name="fator_correlacao" step="0.01" min="0" 
                       value="<?php echo htmlspecialchars((string)($posto['detalhes']['fator_correlacao'] ?? '')); ?>" 
                       placeholder="Ex: 2.5">
            </div>

            <div class="form-block">
                <h2>📝 Informações Complementares</h2>

                <label for="observacao">Observação:</label>
                <textarea id="observacao" name="observacao" placeholder="Digite aqui informações adicionais sobre este posto..."><?php 
                    echo htmlspecialchars($posto['detalhes']['observacao'] ?? ''); 
                ?></textarea>

                <label for="categoria_atividade">Categoria da Atividade:</label>
                <select id="categoria_atividade" name="categoria_atividade">
                    <option value="">-- Selecione uma categoria --</option>
                    <?php
                    $categoria_selecionada = $posto['detalhes']['categoria_atividade'] ?? '';
                    foreach ($categorias_atividade as $categoria) {
                        $nome_categoria = $categoria['nome'] ?? '';
                        $selected = ($categoria_selecionada === $nome_categoria) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($nome_categoria) . '" ' . $selected . '>' . 
                             htmlspecialchars($nome_categoria) . '</option>';
                    }
                    ?>
                </select>

                <label for="tipo_item">Tipo de Item:</label>
                <select id="tipo_item" name="tipo_item">
                    <option value="">-- Selecione um tipo --</option>
                    <?php
                    $tipo_selecionado = $posto['detalhes']['tipo_item'] ?? '';
                    foreach ($tipos_item as $tipo) {
                        $nome_tipo = $tipo['nome'] ?? '';
                        $selected = ($tipo_selecionado === $nome_tipo) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($nome_tipo) . '" ' . $selected . '>' . 
                             htmlspecialchars($nome_tipo) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit">💾 Salvar Detalhes</button>
                <button type="submit" name="limpar_detalhes" onclick="return confirm('Tem certeza que deseja limpar todos os detalhes deste posto?')">
                    🗑️ Limpar Detalhes
                </button>
            </div>
        </form>

        <a href="index.php?linha=<?php echo urlencode($linha_id); ?>" class="back-button">
            ← Voltar ao Fluxo
        </a>
    </div>
</body>
</html>

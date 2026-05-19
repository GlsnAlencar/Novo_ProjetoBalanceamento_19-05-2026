<?php
// Tabela do Posto - Cadastro detalhado
session_start();
include 'data_store.php';
$_SESSION['postos'] = load_json_data('postos');
$_SESSION['unidades'] = load_json_data('unidades');
$_SESSION['categorias_atividade'] = load_json_data('categorias_atividade');
$_SESSION['tipos_item'] = load_json_data('tipos_item');

$posto_index = isset($_GET['posto']) ? (int)$_GET['posto'] : null;
if ($posto_index === null || !isset($_SESSION['postos'][$posto_index])) {
    die('Posto não encontrado.');
}

$posto = &$_SESSION['postos'][$posto_index];

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

    save_json_data('postos', $_SESSION['postos']);
    header('Location: tabela_posto.php?posto=' . $posto_index);
    exit;
}

// Limpar detalhes (Delete)
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
    save_json_data('postos', $_SESSION['postos']);
    header('Location: tabela_posto.php?posto=' . $posto_index);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabela do Posto - <?php echo htmlspecialchars($posto['nome']); ?></title>
    <?php include 'menu.php'; ?>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #007bff;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .form-block {
            background-color: #fafbfc;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
        }
        .form-block h2 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 18px;
            color: #2c3e50;
        }
        label {
            margin-top: 15px;
            font-weight: bold;
        }
        input, textarea, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        textarea {
            resize: vertical;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        button {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .back-link {
            display: inline-block;
            margin-top: 10px;
            color: #007bff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="content">
        <h1>Tabela do Posto: <?php echo htmlspecialchars($posto['nome'] ?? ''); ?></h1>
        <form method="post">
            <div class="form-block">
                <h2>Configuração Principal</h2>

                <label for="unidade_tipica">Unidade Típica do Posto:</label>
                <select id="unidade_tipica" name="unidade_tipica" required>
                    <option value="">Selecione uma unidade</option>
                    <?php
                    $unidades = $_SESSION['unidades'] ?? [];
                    $unidade_selecionada = $posto['detalhes']['unidade_tipica'] ?? '';
                    foreach ($unidades as $unidade) {
                        $nome_unidade = $unidade['nome'] ?? '';
                        $selected = ($unidade_selecionada === $nome_unidade) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($nome_unidade) . '" ' . $selected . '>' . htmlspecialchars($nome_unidade) . '</option>';
                    }
                    ?>
                </select>

                <label for="quantidade_item">Quantidade do Item:</label>
                <input type="number" id="quantidade_item" name="quantidade_item" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($posto['detalhes']['quantidade_item'] ?? '')); ?>" required>

                <label for="tempo_total">Tempo Total (min):</label>
                <input type="number" id="tempo_total" name="tempo_total" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($posto['detalhes']['tempo_total'] ?? '')); ?>" required>

                <label for="tempo_por_item">Tempo por Item (calculado):</label>
                <input type="text" id="tempo_por_item" name="tempo_por_item" value="<?php echo htmlspecialchars((string)($posto['detalhes']['tempo_por_item'] ?? '')); ?>" readonly>

                <label for="fator_correlacao">Fator de Correlação (peso em kg):</label>
                <input type="number" id="fator_correlacao" name="fator_correlacao" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($posto['detalhes']['fator_correlacao'] ?? '')); ?>" required>
            </div>

            <div class="form-block">
                <h2>Informações Complementares</h2>

                <label for="observacao">Observação (opcional):</label>
                <textarea id="observacao" name="observacao" rows="3"><?php echo htmlspecialchars($posto['detalhes']['observacao'] ?? ''); ?></textarea>

                <label for="categoria_atividade">Categoria da Atividade:</label>
                <select id="categoria_atividade" name="categoria_atividade">
                    <option value="">Selecione uma categoria</option>
                    <?php
                    $categorias = $_SESSION['categorias_atividade'] ?? [];
                    $categoria_selecionada = $posto['detalhes']['categoria_atividade'] ?? '';
                    foreach ($categorias as $categoria) {
                        $nome_categoria = $categoria['nome'] ?? '';
                        $selected = ($categoria_selecionada === $nome_categoria) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($nome_categoria) . '" ' . $selected . '>' . htmlspecialchars($nome_categoria) . '</option>';
                    }
                    ?>
                </select>

                <label for="tipo_item">Tipo de Item:</label>
                <select id="tipo_item" name="tipo_item">
                    <option value="">Selecione um tipo</option>
                    <?php
                    $tipos = $_SESSION['tipos_item'] ?? [];
                    $tipo_selecionado = $posto['detalhes']['tipo_item'] ?? '';
                    foreach ($tipos as $tipo) {
                        $nome_tipo = $tipo['nome'] ?? '';
                        $selected = ($tipo_selecionado === $nome_tipo) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($nome_tipo) . '" ' . $selected . '>' . htmlspecialchars($nome_tipo) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit">Salvar Detalhes</button>
                <button type="submit" name="limpar_detalhes" onclick="return confirm('Limpar todos os detalhes?')">Limpar Detalhes</button>
            </div>
        </form>
        <a href="index.php" class="back-link">Voltar ao Fluxo</a>
    </div>
</body>
</html>
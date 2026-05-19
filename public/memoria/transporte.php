<?php
// Tabela de Transporte - Movimentação
session_start();
include 'data_store.php';
$_SESSION['transportes'] = load_json_data('transporte');
$_SESSION['unidades'] = load_json_data('unidades');

$editar_index = isset($_GET['editar']) && is_numeric($_GET['editar']) ? (int)$_GET['editar'] : null;
$transporte_edit = null;
if ($editar_index !== null && isset($_SESSION['transportes'][$editar_index])) {
    $transporte_edit = $_SESSION['transportes'][$editar_index];
}

function calcular_velocidade($distancia, $tempo_total) {
    $distancia = floatval($distancia);
    $tempo_total = floatval($tempo_total);
    return $tempo_total > 0 ? round($distancia / $tempo_total, 2) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_transporte'])) {
    $descricao = trim($_POST['descricao'] ?? '');
    $tipo = trim($_POST['tipo'] ?? '');
    $unidade = trim($_POST['unidade'] ?? '');
    $quantidade = isset($_POST['quantidade']) ? floatval($_POST['quantidade']) : 0;
    $tempo_total = isset($_POST['tempo_total']) ? floatval($_POST['tempo_total']) : 0;
    $distancia = isset($_POST['distancia']) ? floatval($_POST['distancia']) : 0;
    $velocidade = calcular_velocidade($distancia, $tempo_total);

    $_SESSION['transportes'][] = [
        'descricao' => $descricao,
        'tipo' => $tipo,
        'unidade' => $unidade,
        'quantidade' => $quantidade !== 0 ? $quantidade : '',
        'tempo_total' => $tempo_total !== 0 ? $tempo_total : '',
        'distancia' => $distancia !== 0 ? $distancia : '',
        'velocidade' => $velocidade
    ];

    save_json_data('transporte', $_SESSION['transportes']);
    header('Location: transporte.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_transporte']) && isset($_POST['transporte_index'])) {
    $index = (int)$_POST['transporte_index'];
    if (isset($_SESSION['transportes'][$index])) {
        $descricao = trim($_POST['descricao'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $unidade = trim($_POST['unidade'] ?? '');
        $quantidade = isset($_POST['quantidade']) ? floatval($_POST['quantidade']) : 0;
        $tempo_total = isset($_POST['tempo_total']) ? floatval($_POST['tempo_total']) : 0;
        $distancia = isset($_POST['distancia']) ? floatval($_POST['distancia']) : 0;
        $velocidade = calcular_velocidade($distancia, $tempo_total);

        $_SESSION['transportes'][$index] = [
            'descricao' => $descricao,
            'tipo' => $tipo,
            'unidade' => $unidade,
            'quantidade' => $quantidade !== 0 ? $quantidade : '',
            'tempo_total' => $tempo_total !== 0 ? $tempo_total : '',
            'distancia' => $distancia !== 0 ? $distancia : '',
            'velocidade' => $velocidade
        ];
        save_json_data('transporte', $_SESSION['transportes']);
    }
    header('Location: transporte.php');
    exit;
}

if (isset($_GET['remover_transporte']) && is_numeric($_GET['remover_transporte'])) {
    $index = (int)$_GET['remover_transporte'];
    if (isset($_SESSION['transportes'][$index])) {
        unset($_SESSION['transportes'][$index]);
        $_SESSION['transportes'] = array_values($_SESSION['transportes']);
        save_json_data('transporte', $_SESSION['transportes']);
    }
    header('Location: transporte.php');
    exit;
}

$transportes = $_SESSION['transportes'];
$unidades = $_SESSION['unidades'];
$tipos_transporte = ['paleteira manual', 'paleteira elétrica'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transporte - Registro de Movimentação</title>
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
            padding: 30px 20px;
            min-height: calc(100vh - 60px);
            background-color: #f0f2f5;
        }
        .content-wrapper {
            max-width: 1100px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #007bff;
            margin-bottom: 20px;
        }
        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }
        form .full-width {
            grid-column: 1 / -1;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #495057;
            font-size: 14px;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
            font-family: Arial, sans-serif;
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
            border: none;
            border-radius: 4px;
            background-color: #007bff;
            color: white;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #0056b3;
        }
        h1 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #495057;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
        }
        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .action-links a {
            margin-right: 10px;
            color: #007bff;
            text-decoration: none;
        }
        .action-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="content-wrapper">
            <h1>🚚 Transporte - Registro de Movimentação</h1>
        <form method="post">
            <div>
                <label for="descricao">Descrição</label>
                <input type="text" id="descricao" name="descricao" required value="<?php echo htmlspecialchars($transporte_edit['descricao'] ?? ''); ?>">
            </div>
            <div>
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" required>
                    <option value="">Selecione o tipo</option>
                    <?php foreach ($tipos_transporte as $tipo): ?>
                        <?php $selected = (isset($transporte_edit['tipo']) && $transporte_edit['tipo'] === $tipo) ? 'selected' : ''; ?>
                        <option value="<?php echo htmlspecialchars($tipo); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($tipo); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="unidade">Unidade</label>
                <select id="unidade" name="unidade" required>
                    <option value="">Selecione uma unidade</option>
                    <?php foreach ($unidades as $unidade): ?>
                        <?php $nome_unidade = $unidade['nome'] ?? ''; ?>
                        <?php $selected = (isset($transporte_edit['unidade']) && $transporte_edit['unidade'] === $nome_unidade) ? 'selected' : ''; ?>
                        <option value="<?php echo htmlspecialchars($nome_unidade); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($nome_unidade); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="quantidade">Quantidade</label>
                <input type="number" id="quantidade" name="quantidade" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($transporte_edit['quantidade'] ?? '')); ?>" required>
            </div>
            <div>
                <label for="tempo_total">Tempo Total (segundos)</label>
                <input type="number" id="tempo_total" name="tempo_total" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($transporte_edit['tempo_total'] ?? '')); ?>" required>
            </div>
            <div>
                <label for="distancia">Distância (metros)</label>
                <input type="number" id="distancia" name="distancia" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($transporte_edit['distancia'] ?? '')); ?>" required>
            </div>
            <div>
                <label for="velocidade">Velocidade (m/s)</label>
                <input type="text" id="velocidade" name="velocidade" value="<?php echo htmlspecialchars((string)($transporte_edit['velocidade'] ?? '')); ?>" readonly>
            </div>

            <?php if ($transporte_edit !== null): ?>
                <input type="hidden" name="transporte_index" value="<?php echo $editar_index; ?>">
            <?php endif; ?>

            <div class="form-actions full-width">
                <?php if ($transporte_edit !== null): ?>
                    <button type="submit" name="atualizar_transporte">Atualizar Transporte</button>
                    <a href="transporte.php" style="padding: 10px 15px; background-color: #6c757d; color: white; border-radius: 4px; text-decoration: none;">Cancelar</a>
                <?php else: ?>
                    <button type="submit" name="adicionar_transporte">Adicionar Transporte</button>
                <?php endif; ?>
            </div>
        </form>

        <h2>Registros de Transporte</h2>
        <?php if (empty($transportes)): ?>
            <p>Nenhum registro de transporte cadastrado ainda.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Tipo</th>
                        <th>Unidade</th>
                        <th>Quantidade</th>
                        <th>Tempo Total (s)</th>
                        <th>Distância (m)</th>
                        <th>Velocidade (m/s)</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transportes as $index => $registro): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($registro['descricao'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($registro['tipo'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($registro['unidade'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars((string)($registro['quantidade'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($registro['tempo_total'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($registro['distancia'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($registro['velocidade'] ?? '')); ?></td>
                            <td class="action-links">
                                <a href="transporte.php?editar=<?php echo $index; ?>">✏️ Editar</a>
                                <a href="transporte.php?remover_transporte=<?php echo $index; ?>" onclick="return confirm('🗑️ Remover este registro de transporte?')">🗑️ Remover</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <a href="index.php" class="back-button" style="background-color: #6c757d; padding: 10px 15px; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">
                ← Voltar
            </a>
        </div>
        </div>
    </div>
</body>
</html>

<?php
/**
 * ETAPA 2: Configuração de Embalamento por Faixa
 * 
 * Tela isolada para definir o Produto Operacional gerado por cada faixa.
 */

require_once __DIR__ . '/../bootstrap.php';
$service = new CalbradoraService($data_dir);
$controller = new CalbradoraController($service);

$message = '';
$message_type = '';

// Obter todas as faixas
$result = $controller->processarRequisicao('obter_faixas');
$todas_faixas = $result['dados'] ?? [];

// Obter todas as configurações
$result = $controller->processarRequisicao('obter_configuracoes');
$configuracoes = $result['dados'] ?? [];

$faixa_selecionada = null;
$config_selecionada = null;

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'criar_configuracao') {
        $faixa_id = (int)($_POST['faixa_peso_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');

        if ($nome && $faixa_id > 0) {
            $result = $controller->processarRequisicao('criar_configuracao', [
                'nome' => $nome,
                'faixa_peso_id' => $faixa_id
            ]);
            $message = $result['mensagem'];
            $message_type = $result['sucesso'] ? 'success' : 'error';

            if ($result['sucesso']) {
                header('Location: ?');
                exit;
            }
        } else {
            $message = 'Nome e faixa são obrigatórios';
            $message_type = 'error';
        }
    } elseif ($action === 'atualizar_configuracao') {
        $id = (int)($_POST['id'] ?? 0);
        $mapeamentos = [];

        // Processar mapeamentos
        $grs = $_POST['gr'] ?? [];
        $produtos = $_POST['produto_operacional'] ?? [];

        foreach ($grs as $idx => $gr) {
            if (!empty($gr) && !empty($produtos[$idx])) {
                $mapeamentos[] = [
                    'gr' => (int)$gr,
                    'descricao' => trim($_POST['descricao'][$idx] ?? ''),
                    'produto_operacional' => trim($produtos[$idx])
                ];
            }
        }

        $result = $controller->processarRequisicao('atualizar_configuracao', [
            'id' => $id,
            'mapeamentos' => $mapeamentos
        ]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    }
}

// Selecionar configuração se houver parâmetro
if (isset($_GET['config_id'])) {
    $config_id = (int)$_GET['config_id'];
    $result = $controller->processarRequisicao('obter_configuracao', ['id' => $config_id]);
    if ($result['sucesso']) {
        $config_selecionada = $result['dados'];
        // Obter faixa associada
        foreach ($todas_faixas as $f) {
            if ($f['id'] === $config_selecionada['faixa_peso_id']) {
                $faixa_selecionada = $f;
                break;
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
    <title>Configuração de Embalamento - Calibradora</title>
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

        .config-list {
            list-style: none;
        }

        .config-list li {
            padding: 10px;
            border: 1px solid #ddd;
            margin-bottom: 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        .config-list li:hover {
            background-color: #f8f9fa;
            border-color: #1f6feb;
        }

        .config-list li.active {
            background-color: #e7f3ff;
            border-color: #1f6feb;
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
    </style>
</head>
<body>
<div class="container">
    <div class="breadcrumb">
        <a href="../">← Voltar para Calibradora</a>
    </div>

    <h1>Configuração de Embalamento por Faixa</h1>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="form-row">
        <div>
            <h2>Criar Nova Configuração</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="criar_configuracao">

                <div class="form-group">
                    <label for="nome">Nome da Configuração *</label>
                    <input type="text" id="nome" name="nome" required placeholder="Ex: Exportação 4KG Palmer">
                </div>

                <div class="form-group">
                    <label for="faixa_peso_id">Faixa de Peso Base *</label>
                    <select id="faixa_peso_id" name="faixa_peso_id" required>
                        <option value="">-- Selecione uma faixa --</option>
                        <?php foreach ($todas_faixas as $f): ?>
                            <option value="<?php echo (int)$f['id']; ?>">
                                <?php echo htmlspecialchars($f['descricao']); ?> (<?php echo (float)$f['peso_inicial']; ?>-<?php echo (float)$f['peso_final']; ?>g)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit">Criar Configuração</button>
            </form>
        </div>

        <div>
            <h2>Configurações Existentes</h2>
            <?php if (!empty($configuracoes)): ?>
                <ul class="config-list">
                    <?php foreach ($configuracoes as $c): ?>
                        <li <?php echo ($config_selecionada && $config_selecionada['id'] === $c['id']) ? 'class="active"' : ''; ?>>
                            <a href="?config_id=<?php echo (int)$c['id']; ?>" style="color: inherit; text-decoration: none; display: block;">
                                <strong><?php echo htmlspecialchars($c['nome']); ?></strong>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p style="color: #999;">Nenhuma configuração criada ainda.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($config_selecionada && $faixa_selecionada): ?>
        <hr style="margin: 30px 0; border: 1px solid #ddd;">

        <h2>Editar Configuração: <?php echo htmlspecialchars($config_selecionada['nome']); ?></h2>

        <div class="info-box">
            <strong>Faixa Base:</strong> <?php echo htmlspecialchars($faixa_selecionada['descricao']); ?><br>
            <strong>Intervalo:</strong> <?php echo (float)$faixa_selecionada['peso_inicial']; ?>g - <?php echo (float)$faixa_selecionada['peso_final']; ?>g
        </div>

        <form method="POST" action="">
            <input type="hidden" name="action" value="atualizar_configuracao">
            <input type="hidden" name="id" value="<?php echo (int)$config_selecionada['id']; ?>">

            <table>
                <thead>
                <tr>
                    <th>GR</th>
                    <th>Descrição</th>
                    <th>Produto Operacional</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $mapeamentos = $config_selecionada['mapeamentos'] ?? [];
                $gr_counter = 1;

                // Exibir mapeamentos existentes
                foreach ($mapeamentos as $map) {
                    echo '<tr>';
                    echo '<td><input type="number" name="gr[]" value="' . (int)$map['gr'] . '" style="width: 60px;"></td>';
                    echo '<td><input type="text" name="descricao[]" value="' . htmlspecialchars($map['descricao']) . '" style="width: 100%;"></td>';
                    echo '<td><input type="text" name="produto_operacional[]" value="' . htmlspecialchars($map['produto_operacional']) . '" style="width: 100%;"></td>';
                    echo '</tr>';
                    $gr_counter++;
                }

                // Adicionar 3 linhas em branco para novas entradas
                for ($i = 0; $i < 3; $i++) {
                    echo '<tr>';
                    echo '<td><input type="number" name="gr[]" style="width: 60px;"></td>';
                    echo '<td><input type="text" name="descricao[]" style="width: 100%;"></td>';
                    echo '<td><input type="text" name="produto_operacional[]" style="width: 100%;"></td>';
                    echo '</tr>';
                }
                ?>
                </tbody>
            </table>

            <div style="margin-top: 20px;">
                <button type="submit">Salvar Configuração</button>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>

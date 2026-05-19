<?php
/**
 * ETAPA 3: Registro do Lote
 * 
 * Tela isolada para registrar um lote processado pela calibradora.
 */

require_once __DIR__ . '/../bootstrap.php';

$data_dir = __DIR__ . '/../../../../data/reformulacao/calibradora';
$service = new CalbradoraService($data_dir);
$controller = new CalbradoraController($service);

$message = '';
$message_type = '';

// Obter configurações
$result = $controller->processarRequisicao('obter_configuracoes');
$configuracoes = $result['dados'] ?? [];

// Obter lotes
$result = $controller->processarRequisicao('obter_lotes');
$lotes = $result['dados'] ?? [];

$lote_selecionado = null;

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'criar_lote') {
        $result = $controller->processarRequisicao('criar_lote', [
            'controle' => trim($_POST['controle'] ?? ''),
            'configuracao_embalamento_id' => (int)($_POST['configuracao_embalamento_id'] ?? 0),
            'programa' => trim($_POST['programa'] ?? ''),
            'partida' => trim($_POST['partida'] ?? ''),
            'produtor' => trim($_POST['produtor'] ?? ''),
            'variedade' => trim($_POST['variedade'] ?? ''),
            'classe' => trim($_POST['classe'] ?? ''),
            'observacoes' => trim($_POST['observacoes'] ?? '')
        ]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';

        if ($result['sucesso']) {
            header('Location: ?');
            exit;
        }
    } elseif ($action === 'salvar_lote') {
        $result = $controller->processarRequisicao('salvar_lote', [
            'id' => (int)($_POST['id'] ?? 0)
        ]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    } elseif ($action === 'deletar_lote') {
        $result = $controller->processarRequisicao('deletar_lote', [
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
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro do Lote - Calibradora</title>
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
        select,
        textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: Arial, sans-serif;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #1f6feb;
            box-shadow: 0 0 4px rgba(31, 111, 235, 0.3);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .required {
            color: #dc3545;
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

        button.delete {
            background-color: #dc3545;
            padding: 6px 12px;
            font-size: 12px;
        }

        button.delete:hover {
            background-color: #c82333;
        }

        button.save {
            background-color: #28a745;
        }

        button.save:hover {
            background-color: #218838;
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

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-rascunho {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-salvo {
            background-color: #d4edda;
            color: #155724;
        }

        .action-cell {
            text-align: center;
            white-space: nowrap;
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

    <h1>Registro do Lote</h1>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <h2>Criar Novo Lote</h2>
    <form method="POST" action="">
        <input type="hidden" name="action" value="criar_lote">

        <div class="form-row">
            <div class="form-group">
                <label for="controle">Controle <span class="required">*</span></label>
                <input type="text" id="controle" name="controle" required placeholder="Ex: CTRL-2026-001">
            </div>

            <div class="form-group">
                <label for="configuracao_embalamento_id">Configuração de Embalamento</label>
                <select id="configuracao_embalamento_id" name="configuracao_embalamento_id">
                    <option value="">-- Selecione uma configuração --</option>
                    <?php foreach ($configuracoes as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>">
                            <?php echo htmlspecialchars($c['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="programa">Programa</label>
                <input type="text" id="programa" name="programa" placeholder="Ex: MANGO PALMER">
            </div>

            <div class="form-group">
                <label for="partida">Partida</label>
                <input type="text" id="partida" name="partida" placeholder="Ex: Partida 001">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="produtor">Produtor</label>
                <input type="text" id="produtor" name="produtor" placeholder="Ex: João Silva">
            </div>

            <div class="form-group">
                <label for="variedade">Variedade</label>
                <input type="text" id="variedade" name="variedade" placeholder="Ex: Palmer">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="classe">Classe</label>
                <input type="text" id="classe" name="classe" placeholder="Ex: Extra">
            </div>
        </div>

        <div class="form-group">
            <label for="observacoes">Observações</label>
            <textarea id="observacoes" name="observacoes" placeholder="Notas adicionais sobre o lote..."></textarea>
        </div>

        <button type="submit">Criar Lote</button>
    </form>

    <h2 style="margin-top: 40px;">Lotes Registrados</h2>
    <?php if (!empty($lotes)): ?>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Controle</th>
                <th>Programa</th>
                <th>Partida</th>
                <th>Produtor</th>
                <th>Status</th>
                <th>Criado em</th>
                <th>Ação</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lotes as $lote): ?>
                <tr>
                    <td><?php echo (int)$lote['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($lote['controle']); ?></strong></td>
                    <td><?php echo htmlspecialchars($lote['programa'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($lote['partida'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($lote['produtor'] ?: '-'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $lote['status']; ?>">
                            <?php echo ucfirst($lote['status']); ?>
                        </span>
                    </td>
                    <td><?php echo substr($lote['created_at'], 0, 10); ?></td>
                    <td class="action-cell">
                        <a href="?lote_id=<?php echo (int)$lote['id']; ?>" style="color: #1f6feb; text-decoration: none; margin-right: 10px;">Ver</a>
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="action" value="deletar_lote">
                            <input type="hidden" name="id" value="<?php echo (int)$lote['id']; ?>">
                            <button type="submit" class="delete" onclick="return confirm('Confirmar exclusão?')">Deletar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center; color: #999; margin-top: 20px;">Nenhum lote registrado ainda.</p>
    <?php endif; ?>
</div>
</body>
</html>

<?php
/**
 * ETAPA 1: Cadastro de Faixas de Peso
 * 
 * Tela isolada para cadastro das faixas da calibradora.
 * Permite cadastrar múltiplas faixas por configuração com validação de sobreposição.
 */

require_once __DIR__ . '/../bootstrap.php';

$message = '';
$message_type = '';
$nome_config_selecionado = $_GET['config'] ?? '';

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'criar_faixa') {
        $result = $controller->processarRequisicao('criar_faixa', $_POST);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    } elseif ($action === 'atualizar_faixa') {
        $result = $controller->processarRequisicao('atualizar_faixa', $_POST);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    } elseif ($action === 'deletar_faixa') {
        $result = $controller->processarRequisicao('deletar_faixa', ['id' => $_POST['id'] ?? 0]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    }
}

// Obter todas as configurações únicas
$all_faixas = $controller->processarRequisicao('obter_faixas')['dados'] ?? [];
$configuracoes = [];
foreach ($all_faixas as $faixa) {
    if (!in_array($faixa['nome_configuracao'], $configuracoes)) {
        $configuracoes[] = $faixa['nome_configuracao'];
    }
}
sort($configuracoes);

// Obter faixas da configuração selecionada
$faixas = [];
if (!empty($nome_config_selecionado)) {
    $result = $controller->processarRequisicao('obter_faixas_config', ['nome_configuracao' => $nome_config_selecionado]);
    $faixas = $result['dados'] ?? [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Faixas de Peso - Calibradora</title>
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

        /* Seção de Seleção de Configuração */
        .config-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #1f6feb;
        }

        .config-group {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 15px;
            margin-bottom: 15px;
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
            padding: 10px 12px;
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

        button {
            padding: 10px 20px;
            background-color: #1f6feb;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: background-color 0.2s;
        }

        button:hover {
            background-color: #0d47a1;
        }

        button.secondary {
            background-color: #6c757d;
        }

        button.secondary:hover {
            background-color: #5a6268;
        }

        button.delete {
            background-color: #dc3545;
            padding: 6px 12px;
            font-size: 12px;
        }

        button.delete:hover {
            background-color: #c82333;
        }

        button.edit {
            background-color: #17a2b8;
            padding: 6px 12px;
            font-size: 12px;
        }

        button.edit:hover {
            background-color: #138496;
        }

        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        button:disabled:hover {
            background-color: #ccc;
        }

        /* Tabela de Faixas */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        thead {
            background-color: #f8f9fa;
            border-top: 2px solid #dee2e6;
            border-bottom: 2px solid #dee2e6;
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #333;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        .action-cell {
            text-align: center;
            white-space: nowrap;
        }

        .action-cell form {
            display: inline;
        }

        .action-cell button {
            margin: 0 2px;
        }

        /* Seção de Adição de Faixa */
        .add-faixa-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            border-left: 4px solid #28a745;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }

        .empty-message {
            text-align: center;
            color: #999;
            padding: 40px 20px;
            font-style: italic;
        }

        .config-exists {
            color: #155724;
            font-size: 13px;
            margin-top: 3px;
        }

        .config-new {
            color: #0c5460;
            font-size: 13px;
            margin-top: 3px;
        }

        .field-group {
            display: flex;
            flex-direction: column;
        }

        .field-info {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="breadcrumb">
        <a href="../">← Voltar para Calibradora</a>
    </div>

    <h1>Cadastro de Faixas de Peso</h1>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Seção de Seleção/Criação de Configuração -->
    <div class="config-section">
        <h2>Nome/Descrição da Configuração</h2>
        <form method="GET" action="">
            <div class="config-group">
                <div class="form-group">
                    <label for="config_select">Selecione uma configuração existente ou crie uma nova:</label>
                    <input type="text" 
                           id="config_select" 
                           name="config" 
                           placeholder="Ex: EXP, MI, 6 - 4 KG TESTE EXP" 
                           value="<?php echo htmlspecialchars($nome_config_selecionado); ?>"
                           list="config_list"
                           required>
                    <datalist id="config_list">
                        <?php foreach ($configuracoes as $cfg): ?>
                            <option value="<?php echo htmlspecialchars($cfg); ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <?php if ($nome_config_selecionado && in_array($nome_config_selecionado, $configuracoes)): ?>
                        <div class="config-exists">✓ Configuração existente</div>
                    <?php elseif (!empty($nome_config_selecionado)): ?>
                        <div class="config-new">+ Nova configuração</div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit">Carregar Configuração</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Seção de Adição de Faixa (sempre visível) -->
    <div class="add-faixa-section">
        <h2>Adicionar Nova Faixa</h2>
        <?php if (empty($nome_config_selecionado)): ?>
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <strong style="color: #856404;">⚠️ Atenção:</strong> Selecione uma configuração acima para adicionar faixas.
            </div>
        <?php endif; ?>
        <form method="POST" action="" id="form_adicionar_faixa" <?php if (empty($nome_config_selecionado)): ?>style="opacity: 0.6; pointer-events: none;"<?php endif; ?>>
            <input type="hidden" name="action" value="criar_faixa">
            <input type="hidden" name="nome_configuracao" value="<?php echo htmlspecialchars($nome_config_selecionado); ?>">

            <div class="form-row">
                <div class="field-group">
                    <label for="seq">Sequência *</label>
                    <input type="number" id="seq" name="seq" min="1" required placeholder="Ex: 1, 2, 3...">
                    <div class="field-info">Ordem da faixa (1, 2, 3...)</div>
                </div>
                <div class="field-group">
                    <label for="calibre">Calibre *</label>
                    <input type="text" id="calibre" name="calibre" required placeholder="Ex: REFUGO, 14, 05 EMBALAGEM...">
                    <div class="field-info">Número ou descrição do calibre</div>
                </div>
                <div class="field-group">
                    <label for="peso_inicial">Peso Inicial *</label>
                    <input type="number" id="peso_inicial" name="peso_inicial" step="1" min="0" required placeholder="Ex: 0050">
                    <div class="field-info">Em gramas (0050, 0150...)</div>
                </div>
                <div class="field-group">
                    <label for="peso_final">Peso Final *</label>
                    <input type="number" id="peso_final" name="peso_final" step="1" min="0" required placeholder="Ex: 0150">
                    <div class="field-info">Em gramas (0150, 0270...)</div>
                </div>
            </div>

            <button type="submit" <?php if (empty($nome_config_selecionado)): ?>disabled<?php endif; ?>>Adicionar Faixa</button>
        </form>
    </div>

    <?php if (!empty($nome_config_selecionado)): ?>
        <!-- Tabela de Faixas Cadastradas -->
        <h2>Faixas Cadastradas (<?php echo count($faixas); ?> linhas)</h2>
        <?php if (!empty($faixas)): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">Seq</th>
                        <th style="width: 200px;">Calibre</th>
                        <th style="width: 150px;">Peso Inicial</th>
                        <th style="width: 150px;">Peso Final</th>
                        <th style="width: 120px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($faixas as $faixa): ?>
                        <tr>
                            <td><?php echo (int)$faixa['seq']; ?></td>
                            <td><?php echo htmlspecialchars($faixa['calibre']); ?></td>
                            <td><?php echo str_pad((int)$faixa['peso_inicial'], 4, '0', STR_PAD_LEFT); ?> g</td>
                            <td><?php echo str_pad((int)$faixa['peso_final'], 4, '0', STR_PAD_LEFT); ?> g</td>
                            <td class="action-cell">
                                <button type="button" class="edit" onclick="editarFaixa(<?php echo (int)$faixa['id']; ?>)">Editar</button>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="action" value="deletar_faixa">
                                    <input type="hidden" name="id" value="<?php echo (int)$faixa['id']; ?>">
                                    <button type="submit" class="delete" onclick="return confirm('Confirmar exclusão?')">Deletar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">Nenhuma faixa cadastrada para esta configuração.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    function editarFaixa(id) {
        // Para implementação futura - edição inline
        alert('Edição de faixa não implementada ainda. Use deletar e adicionar novamente.');
    }

    // Validação do formulário ao enviar
    document.getElementById('form_adicionar_faixa')?.addEventListener('submit', function(e) {
        const nomeConfig = document.querySelector('input[name="nome_configuracao"]').value.trim();
        
        if (!nomeConfig) {
            e.preventDefault();
            alert('Erro: Selecione uma configuração antes de adicionar faixas!');
            return false;
        }

        const pesoInicial = parseFloat(document.getElementById('peso_inicial').value);
        const pesoFinal = parseFloat(document.getElementById('peso_final').value);

        if (pesoInicial >= pesoFinal) {
            e.preventDefault();
            alert('Erro: Peso inicial deve ser menor que peso final!');
            return false;
        }

        // Validar sobreposição com faixas existentes
        const faixasExistentes = <?php echo json_encode($faixas); ?>;
        const temSobreposicao = faixasExistentes.some(f => {
            // Verificar se a nova faixa se sobrepõe com uma existente
            return !(pesoFinal <= f.peso_inicial || pesoInicial >= f.peso_final);
        });

        if (temSobreposicao) {
            e.preventDefault();
            alert('Erro: Esta faixa de peso se sobrepõe com uma faixa existente!');
            return false;
        }
    });
</script>
</body>
</html>

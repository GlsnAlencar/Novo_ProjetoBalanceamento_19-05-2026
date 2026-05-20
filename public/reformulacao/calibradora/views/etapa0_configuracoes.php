<?php
/**
 * ETAPA 0: Gerenciamento de Configurações de Calibradora
 * 
 * Tela para criar, editar e gerenciar configurações/programas de classificação
 * com suas respectivas faixas de peso.
 */

require_once __DIR__ . '/../bootstrap.php';

$message = '';
$message_type = '';
$config_selecionada_id = (int)($_GET['id'] ?? 0);
$modo_criacao = $_GET['novo'] ?? false;
$config_selecionada = null;
$faixas = [];

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'criar_configuracao_com_faixas') {
        // Criar configuração primeiro
        $result = $controller->processarRequisicao('criar_configuracao_calibradora', $_POST);
        
        if ($result['sucesso']) {
            $config_id = $result['dados']['id'];
            $message = 'Configuração criada com sucesso!';
            $message_type = 'success';
            
            // Agora criar as faixas
            $sequencias = $_POST['seq'] ?? [];
            $descricoes = $_POST['desc'] ?? [];
            $pesos_ini = $_POST['peso_ini'] ?? [];
            $pesos_fim = $_POST['peso_fim'] ?? [];
            
            $erros_faixas = [];
            for ($i = 0; $i < count($sequencias); $i++) {
                if (!empty($descricoes[$i])) {
                    $result_faixa = $controller->processarRequisicao('criar_faixa_configuracao', [
                        'configuracao_id' => $config_id,
                        'sequencia_grupo' => $sequencias[$i],
                        'descricao' => $descricoes[$i],
                        'peso_inicial' => $pesos_ini[$i],
                        'peso_final' => $pesos_fim[$i]
                    ]);
                    
                    if (!$result_faixa['sucesso']) {
                        $erros_faixas[] = "Linha " . ($i + 1) . ": " . $result_faixa['mensagem'];
                    }
                }
            }
            
            if (!empty($erros_faixas)) {
                $message .= " Faixas: " . implode("; ", $erros_faixas);
                $message_type = 'warning';
            } else {
                $message .= " Todas as faixas foram salvas com sucesso!";
            }
            
            $config_selecionada_id = $config_id;
            $modo_criacao = false;
        } else {
            $message = $result['mensagem'];
            $message_type = 'error';
        }
    } elseif ($action === 'atualizar_configuracao') {
        $result = $controller->processarRequisicao('atualizar_configuracao_calibradora', $_POST);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    } elseif ($action === 'deletar_configuracao') {
        $result = $controller->processarRequisicao('deletar_configuracao_calibradora', $_POST);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
        if ($result['sucesso']) {
            $config_selecionada_id = 0;
            $modo_criacao = false;
        }
    } elseif ($action === 'atualizar_faixa') {
        $result = $controller->processarRequisicao('atualizar_faixa_configuracao', $_POST);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    } elseif ($action === 'deletar_faixa') {
        $result = $controller->processarRequisicao('deletar_faixa_configuracao', $_POST);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    }
}

// Obter todas as configurações
$result = $controller->processarRequisicao('obter_configuracoes_calibradora');
$configuracoes = $result['dados'] ?? [];

// Obter configuração selecionada (apenas para edição)
if ($config_selecionada_id > 0 && !$modo_criacao) {
    $result = $controller->processarRequisicao('obter_configuracao_calibradora', ['id' => $config_selecionada_id]);
    if ($result['sucesso']) {
        $config_selecionada = $result['dados'];
        
        // Obter faixas dessa configuração
        $result = $controller->processarRequisicao('obter_faixas_configuracao', ['configuracao_id' => $config_selecionada_id]);
        $faixas = $result['dados'] ?? [];
    }
}
?>
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Configurações - Calibradora</title>
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
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            color: #1f6feb;
            margin-bottom: 20px;
            font-size: 28px;
        }

        h2 {
            color: #333;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: bold;
            border-bottom: 2px solid #1f6feb;
            padding-bottom: 10px;
        }

        .section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
            font-size: 13px;
        }

        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }

        textarea {
            resize: vertical;
            min-height: 60px;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #1f6feb;
            box-shadow: 0 0 3px rgba(31, 111, 235, 0.3);
        }

        button {
            padding: 8px 16px;
            background-color: #1f6feb;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }

        button:hover {
            background-color: #0d47a1;
        }

        button.delete {
            background-color: #dc3545;
            padding: 4px 8px;
            font-size: 11px;
        }

        button.delete:hover {
            background-color: #c82333;
        }

        button.edit {
            background-color: #17a2b8;
            padding: 4px 8px;
            font-size: 11px;
        }

        button.edit:hover {
            background-color: #138496;
        }

        button.add {
            background-color: #28a745;
        }

        button.add:hover {
            background-color: #218838;
        }

        .config-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        .config-card {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 12px;
            cursor: pointer;
        }

        .config-card:hover {
            border-color: #1f6feb;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .config-card.active {
            border: 2px solid #1f6feb;
            background-color: #e7f1ff;
        }

        .config-card-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .config-card-desc {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }

        .config-card-status {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .config-card-status.ativo {
            background-color: #d4edda;
            color: #155724;
        }

        .config-card-status.inativo {
            background-color: #f8d7da;
            color: #721c24;
        }

        .config-card-actions {
            display: flex;
            gap: 5px;
        }

        .config-card-actions button {
            flex: 1;
            padding: 4px 8px;
            font-size: 11px;
        }

        /* Tabelas */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        thead {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-bottom: 2px solid #dee2e6;
        }

        th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
            font-size: 13px;
        }

        td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        .action-cell {
            text-align: center;
            white-space: nowrap;
        }

        .action-cell button {
            margin: 0 2px;
        }

        .editable-row input {
            width: 100%;
            padding: 4px 6px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 13px;
        }

        .empty-message {
            text-align: center;
            color: #999;
            padding: 30px 20px;
            font-style: italic;
        }

        .config-header {
            background: #e7f1ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #1f6feb;
        }

        .config-header h3 {
            margin-bottom: 5px;
            color: #1f6feb;
        }

        .config-header p {
            color: #666;
            font-size: 13px;
            margin: 2px 0;
        }

        .form-row {
            display: grid;
            grid-template-columns: auto 2fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }

        .form-row-actions {
            display: flex;
            align-items: flex-end;
            gap: 5px;
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Gerenciamento de Configurações da Calibradora</h1>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- SEÇÃO 1: CONFIGURAÇÕES -->
        <div class="section">
            <h2>1. Minhas Configurações</h2>

            <?php if (!empty($configuracoes)): ?>
                <div class="config-cards">
                    <?php foreach ($configuracoes as $conf): ?>
                        <div class="config-card <?php echo $conf['id'] === $config_selecionada_id ? 'active' : ''; ?>">
                            <div class="config-card-title"><?php echo htmlspecialchars($conf['nome']); ?></div>
                            <div class="config-card-desc"><?php echo htmlspecialchars($conf['descricao'] ?? ''); ?></div>
                            <span class="config-card-status <?php echo $conf['ativo'] ? 'ativo' : 'inativo'; ?>">
                                <?php echo $conf['ativo'] ? 'Ativo' : 'Inativo'; ?>
                            </span>
                            <div class="config-card-actions">
                                <button class="edit" onclick="window.location.href='?id=<?php echo htmlspecialchars($conf['id']); ?>'">Abrir</button>
                                <form method="POST" style="flex: 1;" onsubmit="return confirm('Deletar essa configuração e todas suas faixas?');">
                                    <input type="hidden" name="action" value="deletar_configuracao">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($conf['id']); ?>">
                                    <button type="submit" class="delete" style="width: 100%;">Deletar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h2 style="margin-top: 20px;">Criar Nova Configuração</h2>
            <form method="POST" style="max-width: 600px;">
                <input type="hidden" name="action" value="criar_configuracao">

                <div class="form-group">
                    <label>Nome <span style="color: #d32f2f;">*</span></label>
                    <input type="text" name="nome" required placeholder="Ex: Exportação, Mercado Interno, 4KG EXP">
                </div>

                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" placeholder="Descrição opcional..."></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="ativo" value="1" checked> Ativo
                    </label>
                </div>

                <button type="submit" class="add">✓ Criar Configuração</button>
            </form>
        </div>

        <!-- SEÇÃO 2: FAIXAS -->
        <?php if ($config_selecionada): ?>
        <div class="section">
            <h2>2. Faixas de Peso</h2>

            <div class="config-header">
                <h3><?php echo htmlspecialchars($config_selecionada['nome']); ?></h3>
                <p><?php echo htmlspecialchars($config_selecionada['descricao'] ?? '(sem descrição)'); ?></p>
            </div>

            <!-- Tabela de Faixas Existentes -->
            <?php if (!empty($faixas)): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">Gr.</th>
                        <th>Descripción</th>
                        <th style="width: 100px;">Peso Inicial</th>
                        <th style="width: 100px;">Peso Final</th>
                        <th style="width: 80px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($faixas as $faixa): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($faixa['sequencia_grupo']); ?></td>
                        <td><?php echo htmlspecialchars($faixa['descricao']); ?></td>
                        <td><?php echo number_format($faixa['peso_inicial'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($faixa['peso_final'], 0, ',', '.'); ?></td>
                        <td class="action-cell">
                            <button class="edit" onclick="editarFaixa(<?php echo htmlspecialchars(json_encode($faixa)); ?>)">Editar</button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Deletar?');">
                                <input type="hidden" name="action" value="deletar_faixa">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($faixa['id']); ?>">
                                <button type="submit" class="delete">Deletar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-message">Nenhuma faixa cadastrada. Adicione as primeiras faixas abaixo.</div>
            <?php endif; ?>

            <!-- Tabela para Adicionar Faixas -->
            <h2 style="margin-top: 20px;">Adicionar Novas Faixas</h2>
            <form method="POST" id="faixasForm">
                <input type="hidden" name="action" value="criar_faixa">
                <input type="hidden" name="configuracao_id" value="<?php echo htmlspecialchars($config_selecionada['id']); ?>">

                <table id="faixasTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Gr.</th>
                            <th>Descripción</th>
                            <th style="width: 100px;">Peso Inicial</th>
                            <th style="width: 100px;">Peso Final</th>
                            <th style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody id="faixasBody">
                        <!-- Linhas dinâmicas serão adicionadas aqui -->
                    </tbody>
                </table>

                <div style="margin-top: 15px;">
                    <button type="button" class="add" onclick="adicionarLinhaFaixa()">+ Adicionar Linha</button>
                    <button type="button" class="add" onclick="salvarVariasFaixas()" style="margin-left: 10px;">✓ Salvar Faixas</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script>
        let proximaSeq = <?php echo !empty($faixas) ? max(array_column($faixas, 'sequencia_grupo')) + 1 : 1; ?>;

        function adicionarLinhaFaixa() {
            const tbody = document.getElementById('faixasBody');
            const tr = document.createElement('tr');
            tr.className = 'editable-row';
            tr.innerHTML = `
                <td><input type="number" name="seq[]" value="${proximaSeq}" min="1"></td>
                <td><input type="text" name="desc[]" placeholder="Ex: REFUGO, 12, 10" required></td>
                <td><input type="number" name="peso_ini[]" step="0.01" min="0" placeholder="50" required></td>
                <td><input type="number" name="peso_fim[]" step="0.01" min="0" placeholder="150" required></td>
                <td class="action-cell">
                    <button type="button" class="delete" onclick="this.parentElement.parentElement.remove(); proximaSeq--;">✕</button>
                </td>
            `;
            tbody.appendChild(tr);
            proximaSeq++;
        }

        function salvarVariasFaixas() {
            const tbody = document.getElementById('faixasBody');
            const rows = tbody.querySelectorAll('tr');

            if (rows.length === 0) {
                alert('Adicione pelo menos uma faixa');
                return;
            }

            // Validações básicas
            let erros = [];
            rows.forEach((row, idx) => {
                const seq = row.querySelector('input[name="seq[]"]').value;
                const desc = row.querySelector('input[name="desc[]"]').value;
                const pesoIni = parseFloat(row.querySelector('input[name="peso_ini[]"]').value);
                const pesoFim = parseFloat(row.querySelector('input[name="peso_fim[]"]').value);

                if (!desc) erros.push(`Linha ${idx + 1}: Descripción é obrigatória`);
                if (isNaN(pesoIni) || isNaN(pesoFim)) erros.push(`Linha ${idx + 1}: Pesos inválidos`);
                if (pesoIni >= pesoFim) erros.push(`Linha ${idx + 1}: Peso Inicial deve ser menor que Peso Final`);
            });

            if (erros.length > 0) {
                alert('Erros encontrados:\n\n' + erros.join('\n'));
                return;
            }

            // Salvar linha por linha
            let rowIdx = 0;
            function salvarProxima() {
                if (rowIdx >= rows.length) {
                    alert('Faixas salvas com sucesso!');
                    location.reload();
                    return;
                }

                const row = rows[rowIdx];
                const seq = row.querySelector('input[name="seq[]"]').value;
                const desc = row.querySelector('input[name="desc[]"]').value;
                const pesoIni = row.querySelector('input[name="peso_ini[]"]').value;
                const pesoFim = row.querySelector('input[name="peso_fim[]"]').value;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="criar_faixa">
                    <input type="hidden" name="configuracao_id" value="${document.querySelector('input[name="configuracao_id"]').value}">
                    <input type="hidden" name="sequencia_grupo" value="${seq}">
                    <input type="hidden" name="descricao" value="${desc}">
                    <input type="hidden" name="peso_inicial" value="${pesoIni}">
                    <input type="hidden" name="peso_final" value="${pesoFim}">
                `;

                // Simular submit
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    rowIdx++;
                    salvarProxima();
                };

                const formData = new FormData(form);
                const params = new URLSearchParams(formData).toString();
                xhr.send(params);
            }

            salvarProxima();
        }

        function editarFaixa(faixa) {
            const desc = prompt('Descripción:', faixa.descricao);
            if (desc === null) return;

            const pesoIni = parseFloat(prompt('Peso Inicial:', faixa.peso_inicial));
            if (isNaN(pesoIni)) return;

            const pesoFim = parseFloat(prompt('Peso Final:', faixa.peso_final));
            if (isNaN(pesoFim)) return;

            const seq = parseInt(prompt('Sequência:', faixa.sequencia_grupo));
            if (isNaN(seq)) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="atualizar_faixa">
                <input type="hidden" name="id" value="${faixa.id}">
                <input type="hidden" name="descricao" value="${desc}">
                <input type="hidden" name="peso_inicial" value="${pesoIni}">
                <input type="hidden" name="peso_final" value="${pesoFim}">
                <input type="hidden" name="sequencia_grupo" value="${seq}">
                <input type="hidden" name="configuracao_id" value="${faixa.configuracao_id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Adicionar primeira linha ao carregar
        window.addEventListener('load', function() {
            if (document.getElementById('faixasBody') && document.getElementById('faixasBody').children.length === 0) {
                adicionarLinhaFaixa();
            }
        });
    </script>
</body>
</html>

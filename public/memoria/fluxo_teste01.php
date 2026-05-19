<?php
/**
 * FLUXO_TESTE01.php - Editor de Fluxo com Drawflow, Setores e Linhas
 * 
 * Objetivo: Fornecer uma interface para criar, editar e visualizar fluxos de trabalho
 * (linhas de produção) organizados por setores, utilizando a biblioteca Drawflow.
 * Os dados são persistidos no backend PHP (linhas.json) via AJAX.
 * 
 * Data: 2024-05-15
 */

session_start();
include 'data_store.php';

// Carregar dados dos setores e linhas
$setores_json = load_json_data('setores');
$linhas_json = load_json_data('linhas');

// Determinar setor ativo
$setor_id_ativo = isset($_GET['setor_id']) ? $_GET['setor_id'] : null;
if (!$setor_id_ativo && !empty($setores_json)) {
    $setor_id_ativo = $setores_json[0]['id'];
}
$setor_ativo = find_setor_by_id($setores_json, $setor_id_ativo);

// Determinar linha ativa
$linhas_do_setor = [];
if ($setor_ativo) {
    $linhas_do_setor = get_linhas_by_setor($linhas_json, $setor_ativo['id']);
}

$linha_id_ativo = isset($_GET['linha_id']) ? $_GET['linha_id'] : null;
if (!$linha_id_ativo && !empty($linhas_do_setor)) {
    $linha_id_ativo = $linhas_do_setor[0]['id'];
}
$linha_selecionada = find_linha_by_id($linhas_json, $linha_id_ativo);

// Se não há setores ou linhas, exibir mensagem
if (empty($setores_json) || empty($linhas_do_setor) || !$linha_selecionada) {
    $no_data_message = "Nenhum setor ou linha configurada. Por favor, crie um setor e uma linha primeiro.";
    if (empty($setores_json)) $no_data_message = "Nenhum setor cadastrado. <a href='setores.php'>Crie um setor</a>.";
    else if (empty($linhas_do_setor)) $no_data_message = "Nenhuma linha para o setor selecionado. <a href='linhas.php?setor_id=" . urlencode($setor_id_ativo) . "'>Crie uma linha</a>.";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fluxo Teste01 - CRUD Local</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.css">
    <script src="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        /* Sistema de Abas Estilo Excel */
        .excel-tabs-container {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .sector-selector-bar {
            padding: 10px 20px;
            background: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #eee;
        }
        .sector-selector-bar select {
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid #ccd0d5;
            font-weight: 600;
        }
        .line-tabs-bar {
            display: flex;
            padding: 0 20px;
            gap: 2px;
            background: #e9ecef;
        }
        .excel-tab {
            padding: 8px 20px;
            background: #dee2e6;
            border: 1px solid #ccc;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            text-decoration: none;
            color: #495057;
            font-size: 13px;
            transition: all 0.2s;
            margin-top: 5px;
        }
        .excel-tab:hover { background: #ced4da; }
        .excel-tab.active {
            background: #fff;
            font-weight: bold;
            color: #007bff;
            border-top: 3px solid #007bff;
        }

        .toolbar {
            background-color: #333;
            padding: 10px;
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
            color: white;
        }
        .top-bar {
            background-color: #444;
            padding: 8px 10px;
            display: flex;
            gap: 15px;
            align-items: center;
            color: white;
            font-size: 14px;
            border-bottom: 1px solid #555;
        }
        .top-bar select {
            padding: 5px 8px;
            border-radius: 4px;
            border: 1px solid #666;
            background-color: #555;
            color: white;
        }
        .line-tabs {
            display: flex;
            background-color: #555;
            padding: 0 10px;
        }
        .line-tabs a {
            padding: 8px 15px;
            color: #ccc;
            text-decoration: none;
            border-bottom: 3px solid transparent;
        }
        .line-tabs a.active {
            color: white;
            border-bottom: 3px solid #007bff;
        }
        .toolbar button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        .toolbar button:hover {
            background-color: #0056b3;
        }
        #drawflow {
            flex-grow: 1; /* Ocupa o espaço restante */
            width: 100%;
            height: 100%; /* Será ajustado pelo flex-grow */
            background-color: #fff;
            border: 1px solid #ccc;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background-image: 
                linear-gradient(rgba(0,0,0,0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,0,0,0.05) 1px, transparent 1px);
            background-size: 20px 20px; /* Grid sutil */
        }
        .no-data-message {
            text-align: center;
            padding: 50px;
            font-size: 1.2em;
            color: #666;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            margin: 20px;
        }
        .drawflow-node {
            background-color: #e0f7fa !important;
            border: 1px solid #00bcd4 !important;
            border-radius: 5px !important;
            padding: 5px !important; /* Reduz padding para mais info */
            text-align: center !important;
            font-weight: bold;
            color: #00838f;
            min-width: 150px;
            min-height: 80px;
        }
        .drawflow-node.selected {
            border-color: #ffc107 !important;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.3) !important;
        }
        .drawflow-node .outputs, .drawflow-node .inputs {
            background-color: #00bcd4 !important;
        }
        .node-content {
            padding: 5px;
            font-size: 12px;
        }
        .node-content strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        .node-icon {
            font-size: 20px;
            color: #00bcd4;
            margin-bottom: 8px;
        }
        .node-actions {
            margin-top: 5px;
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        .node-actions button {
            padding: 3px 8px;
            font-size: 10px;
            border-radius: 3px;
            cursor: pointer;
        }
        .node-actions .edit-btn { background-color: #28a745; color: white; border: none; }
        .node-actions .delete-btn { background-color: #dc3545; color: white; border: none; }
        .node-actions .add-derived-btn { background-color: #007bff; color: white; border: none; }
        
        .node-actions button i {
            pointer-events: none; /* Garante que o clique registre no botão */
            font-size: 10px;
        }

        /* Bizagi-like Gateway Styles */
        .drawflow-node.decision_exclusive, .drawflow-node.decision_parallel {
            min-width: 115px !important; /* Ajustado para conter o losango rotacionado */
            min-height: 115px !important; /* Ajustado para conter o losango rotacionado */
            background-color: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important; /* Remove padding do nó para o losango preencher */
        }
        

        /* Ajuste dos pontos de conexão para tocarem os vértices do losango */
        .drawflow-node.decision_exclusive .inputs, .drawflow-node.decision_parallel .inputs,
        .drawflow-node.decision_exclusive .outputs, .drawflow-node.decision_parallel .outputs {
            top: 50% !important;
            transform: translateY(-50%) !important;
            z-index: 5;
        }
        .drawflow-node.decision_exclusive .inputs, .drawflow-node.decision_parallel .inputs { left: 2px !important; }
        .drawflow-node.decision_exclusive .outputs, .drawflow-node.decision_parallel .outputs { right: 2px !important; }

        .gateway-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%; /* Ocupa toda a altura do nó */
            width: 100%; /* Ocupa toda a largura do nó */
            position: relative; /* Para posicionamento absoluto dos filhos */
        }

        .gateway-diamond {
            width: 80px; /* Tamanho do quadrado antes da rotação */
            height: 80px; /* Tamanho do quadrado antes da rotação */
            background-color: #fff9c4;
            border: 2px solid #fbc02d;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.1);
            /* Posicionamento absoluto para centralizar perfeitamente */
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(45deg);
        }

        .gateway-diamond i {
            transform: rotate(-45deg);
            font-size: 18px;
            color: #f57f17;
        }

        .decision_parallel .gateway-diamond {
            background-color: #e8f5e9;
            border-color: #4caf50;
        }

        .decision_parallel .gateway-diamond i {
            color: #2e7d32;
        }

        .gateway-label {
            font-size: 11px;
            max-width: 100px; /* Largura máxima para a label */
            text-align: center;
            word-wrap: break-word;
            color: #333;
            /* Posicionamento absoluto abaixo do losango */
            position: absolute;
            top: calc(50% + 40px); /* 50% (centro) + metade da altura do losango (40px) */
            left: 50%;
            transform: translateX(-50%);
        }
        
        .drawflow-node.decision_exclusive .node-actions,
        .drawflow-node.decision_parallel .node-actions {
            /* Posicionamento absoluto na parte inferior do nó */
            position: absolute;
            bottom: 5px;
            left: 50%;
            transform: translateX(-50%);
            width: 100%; /* Para garantir que o justify-content: center funcione */
        }

        /* Sobrescrever posição dos inputs/outputs para gateways tocarem os vértices */
        .drawflow-node.decision_exclusive .inputs,
        .drawflow-node.decision_parallel .inputs,
        .drawflow-node.decision_exclusive .outputs,
        .drawflow-node.decision_parallel .outputs {
            top: 50% !important;
            transform: translateY(-50%) !important;
            z-index: 5;
        }
        .drawflow-node.decision_exclusive .inputs, .drawflow-node.decision_parallel .inputs { left: 2px !important; }
        .drawflow-node.decision_exclusive .outputs, .drawflow-node.decision_parallel .outputs { right: 2px !important; }

        /* Modal Customizado */
        #inputModal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-content {
            background-color: #2d2d44;
            margin: auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { transform: translate(-50%, -60%); opacity: 0; }
            to { transform: translate(-50%, -50%); opacity: 1; }
        }
        .modal-content label {
            display: block;
            color: #fff;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 16px;
        }
        .modal-content input {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: none;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
            background-color: #f0f0f0;
        }
        .modal-content input:focus {
            outline: none;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .modal-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        .modal-buttons .btn-ok {
            background-color: #ff1744;
            color: white;
        }
        .modal-buttons .btn-ok:hover {
            background-color: #f50057;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 23, 68, 0.3);
        }
        .modal-buttons .btn-cancel {
            background-color: #4a4a6a;
            color: white;
        }
        .modal-buttons .btn-cancel:hover {
            background-color: #5a5a7a;
            transform: translateY(-2px);
        }

    </style>
</head>

<body>
    <div class="excel-tabs-container">
        <div class="sector-selector-bar">
            <strong>🏢 Setor:</strong>
            <select id="setor_select" onchange="window.location.href='?setor_id=' + this.value;">
                <?php foreach ($setores_json as $s): ?>
                    <option value="<?php echo htmlspecialchars($s['id']); ?>" <?php echo ($s['id'] === $setor_id_ativo) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button onclick="crudAction('add_setor')" class="btn-small" style="background:#28a745; color:#fff; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;">+</button>
            <button onclick="crudAction('delete_setor')" class="btn-small" style="background:#dc3545; color:#fff; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;">🗑️</button>
        </div>
        <div class="line-tabs-bar">
            <?php foreach ($linhas_do_setor as $l): ?>
                <div style="display:flex; align-items:center;" class="tab-wrapper">
                    <a href="?setor_id=<?php echo urlencode($setor_id_ativo); ?>&linha_id=<?php echo urlencode($l['id']); ?>" 
                       class="excel-tab <?php echo ($l['id'] === $linha_id_ativo) ? 'active' : ''; ?>">
                        📦 <?php echo htmlspecialchars($l['nome']); ?>
                    </a>
                    <?php if ($l['id'] === $linha_id_ativo): ?>
                        <span style="background:#fff; margin-top:5px; border-top:1px solid #ccc; padding: 0 5px; display:flex; gap:3px;">
                            <i class="fa-solid fa-pen" style="font-size:10px; cursor:pointer;" onclick="crudAction('rename_linha', '<?php echo $l['id']; ?>')"></i>
                            <i class="fa-solid fa-trash" style="font-size:10px; cursor:pointer; color:red;" onclick="crudAction('delete_linha', '<?php echo $l['id']; ?>')"></i>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button onclick="crudAction('add_linha')" class="excel-tab" style="color: #28a745; cursor:pointer; border-style:dashed;">➕ Nova Linha</button>
        </div>
    </div>

    <div class="toolbar">
        <button onclick="addNodeToFlow()">➕ Adicionar Posto</button>
        <button onclick="saveFlowState()" style="background-color: #28a745;">💾 Salvar Fluxo</button>
        <button onclick="clearFlow()">🗑️ Limpar Fluxo</button>
        <span id="saveStatus" style="margin-left: auto; margin-right: 15px; font-size: 12px; color: #4caf50; opacity: 0; transition: opacity 0.3s; font-weight: bold;">✅ Sincronizado</span>
        <span>Nós no fluxo: <span id="nodeCount">0</span></span>
    </div>

    <div id="drawflow"></div>
    <script>
        // Variáveis globais inicializadas do PHP
        const currentSetorId = '<?php echo htmlspecialchars($setor_id_ativo ?? ''); ?>';
        const currentLinhaId = '<?php echo htmlspecialchars($linha_id_ativo ?? ''); ?>';

        var editor = null;
        var mockPostos = []; 
        var mockConnections = [];
        var nextNodeId = 1;
        var isSaving = false;
        var isLoading = false;
        var isDirty = false;

        window.onload = initDrawflow;

        async function initDrawflow() {
            const container = document.getElementById('drawflow');
            if (!container) {
                console.error('❌ Elemento #drawflow não encontrado!');
                return;
            }

            editor = new Drawflow(container);
            editor.reroute = true;
            editor.zoom_max = 2;
            editor.zoom_min = 0.3;
            editor.start();

            console.log('✅ Drawflow inicializado');

            try {
                // Tentar carregar dados existentes
                if (currentLinhaId) {
                    const response = await fetch('api_drawflow.php?action=load_drawflow_data&linha_id=' + currentLinhaId);
                    const data = await response.json();

                    if (data.status === 'success' && data.drawflow_data) {
                        const flowData = typeof data.drawflow_data === 'string' 
                            ? JSON.parse(data.drawflow_data) 
                            : data.drawflow_data;

                        if (flowData?.drawflow?.Home?.data && Object.keys(flowData.drawflow.Home.data).length > 0) {
                            editor.import(flowData);
                            console.log('✅ Fluxo carregado do servidor');
                            setupDrawflowEvents();
                            updateNodeCount();
                            return;
                        }
                    }
                }

                // Se vazio, criar fluxo de teste
                console.log('🆕 Criando fluxo inicial de teste...');
                await addSimpleNode('Posto 1: Início', 10, 100, 150);
                await addSimpleNode('Posto 2: Processamento', 8, 400, 150);
                await addSimpleNode('Posto 3: Conclusão', 7, 700, 150);
                
                // Conectar os postos sequencialmente
                if (mockPostos.length >= 3) {
                    editor.addConnection(mockPostos[0].drawflow_id, mockPostos[1].drawflow_id, 'output_1', 'input_1');
                    editor.addConnection(mockPostos[1].drawflow_id, mockPostos[2].drawflow_id, 'output_1', 'input_1');
                    console.log('✅ Conexões criadas');
                }

                // Salvar o fluxo inicial
                await saveFlowState();

            } catch (e) {
                console.error('❌ Erro na inicialização:', e);
            }

            setupDrawflowEvents();
            updateNodeCount();
            console.log('🎉 Inicialização completa');

            window.onbeforeunload = function () {
                // Avisar se houver mudanças não salvas
            };
        }

        // Gera o HTML do nó para exibição no fluxo
        function generateNodeHtml(dados) {
            const { id, name, type, tc = 0 } = dados;
            
            if (type === 'decision_exclusive') {
                return `
                    <div class="gateway-container">
                        <div class="gateway-diamond">
                            <i class="fa-solid fa-xmark"></i>
                        </div>
                        <div class="gateway-label" style="margin-top: 60px; font-size: 11px; font-weight: bold; color: #f57f17; text-align: center;">
                            <strong>${name}</strong>
                        </div>
                    </div>
                `;
            } else if (type === 'decision_parallel') {
                return `
                    <div class="gateway-container">
                        <div class="gateway-diamond" style="background-color: #e8f5e9; border-color: #4caf50;">
                            <i class="fa-solid fa-plus" style="color: #4caf50;"></i>
                        </div>
                        <div class="gateway-label" style="margin-top: 60px; font-size: 11px; font-weight: bold; color: #4caf50; text-align: center;">
                            <strong>${name}</strong>
                        </div>
                    </div>
                `;
            } else {
                // Nó simples (tipo 'node')
                return `
                    <div class="node-content" id="node-content-${id}">
                        <div class="node-icon">
                            <i class="fa-solid fa-industry"></i>
                        </div>
                        <strong>${name}</strong>
                        <div style="font-size: 11px; margin-top: 4px;">
                            ⏱️ <span class="tc-value">${tc}</span>s
                        </div>
                        <div class="node-actions">
                            <button class="edit-btn" onclick="editNodeInFlow('${id}')" title="Editar"><i class="fa-solid fa-pen"></i></button>
                            <button class="delete-btn" onclick="removeNodeFromFlow('${id}')" title="Remover"><i class="fa-solid fa-trash"></i></button>
                            <button class="add-derived-btn" onclick="showDeriveOptions('${id}')" title="Derivar"><i class="fa-solid fa-sitemap"></i></button>
                        </div>
                    </div>
                `;
            }
        }

        // Função simplificada para adicionar nó
        async function addSimpleNode(name, tc, x, y) {
            try {
                const formData = new FormData();
                formData.append('action', 'generate_id');
                const response = await fetch('api_drawflow.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.status === 'success') {
                    const uniqueId = data.id;
                    const htmlContent = generateNodeHtml({ id: uniqueId, name, tc });

                    const drawflowId = editor.addNode(
                        'node', 1, 1, x, y, 'node',
                        { id: uniqueId, name, tc },
                        htmlContent
                    );

                    mockPostos.push({
                        id: uniqueId,
                        name,
                        tc,
                        drawflow_id: drawflowId,
                        x, y,
                        type: 'node'
                    });

                    console.log('✅ Nó adicionado:', name);
                    return uniqueId;
                }
            } catch (error) {
                console.error('❌ Erro ao adicionar nó:', error);
            }
            return null;
        }

        // Função original refatorada
        async function addNodeToFlow(name = null, type = 'node', parentId = null, initialX = null, initialY = null) {
            let nodeName = name;
            if (!nodeName) {
                nodeName = await showInputDialog('Nome do novo posto:');
                if (!nodeName) return null;
            }

            let suggestedX = initialX || 100;
            let suggestedY = initialY || 150;

            if (parentId) {
                const parentPosto = mockPostos.find(p => p.id == parentId);
                if (parentPosto) {
                    suggestedX = (parentPosto.x || 100) + 300;
                    suggestedY = (parentPosto.y || 150);
                }
            } else if (mockPostos.length > 0) {
                const rightmost = mockPostos.reduce((prev, current) => 
                    (prev.x > current.x ? prev : current));
                suggestedX = (rightmost.x || 100) + 300;
                suggestedY = (rightmost.y || 150);
            }

            try {
                const formData = new FormData();
                formData.append('action', 'generate_id');
                const response = await fetch('api_drawflow.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.status === 'success') {
                    const uniqueId = data.id;
                    let tc = 0;
                    if (type === 'node') {
                        const nodeTc = await showInputDialog('Tempo de Ciclo (s):', '10');
                        tc = parseFloat(nodeTc) || 0;
                    }

                    const htmlContent = generateNodeHtml({
                        id: uniqueId,
                        name: nodeName,
                        type,
                        tc
                    });

                    const drawflowId = editor.addNode(
                        type, 1, 1, suggestedX, suggestedY, type,
                        { id: uniqueId, name: nodeName, tc },
                        htmlContent
                    );

                    const newPosto = {
                        id: uniqueId,
                        name: nodeName,
                        type,
                        tc,
                        drawflow_id: drawflowId,
                        x: suggestedX,
                        y: suggestedY
                    };

                    mockPostos.push(newPosto);
                    console.log('✅ Nó criado:', uniqueId);

                    // Conectar ao pai se existir
                    if (parentId) {
                        const parentPosto = mockPostos.find(p => p.id == parentId);
                        if (parentPosto?.drawflow_id) {
                            editor.addConnection(parentPosto.drawflow_id, drawflowId, 'output_1', 'input_1');
                            mockConnections.push({ 
                                sourceDfId: parentPosto.drawflow_id, 
                                targetDfId: drawflowId 
                            });
                            console.log('✅ Conexão criada');
                        }
                    }

                    updateNodeCount();
                    await saveFlowState();
                    return uniqueId;
                }
            } catch (error) {
                console.error('❌ Erro ao adicionar nó:', error);
            }
            return null;
        }

        // CRUD: Update
        async function editNodeInFlow(id) {
            const posto = mockPostos.find(p => p.id == id); // Usar == para comparar string/number
            if (posto) {
                const newName = await showInputDialog('Novo nome para "' + posto.name + '":', posto.name);
                const newTc = await showInputDialog('Novo Tempo de Ciclo (s):', posto.tc.toString());

                if (newName && newName.trim()) {
                    posto.name = newName.trim();
                    posto.tc = parseFloat(newTc) || 0;
                    // Atualiza os dados do nó Drawflow e o conteúdo HTML
                    editor.updateNodeDataFromId(posto.drawflow_id, { name: posto.name, tc: posto.tc });
                    const nodeEl = document.getElementById('node-' + posto.drawflow_id);
                    if (nodeEl) {
                        const strongTag = nodeEl.querySelector('.node-content strong, .gateway-label strong');
                        const tcSpan = nodeEl.querySelector('.tc-value');
                        
                        if (strongTag) strongTag.textContent = posto.name;
                        if (tcSpan) tcSpan.textContent = posto.tc;
                    }
                    isDirty = true; // Marca como sujo antes de salvar
                    saveFlowState(); 
                }
            }
        }

        // CRUD: Delete
        function removeNodeFromFlow(id) {
            if (confirm('Tem certeza que deseja remover este posto?')) {
                const posto = mockPostos.find(p => p.id == id);
                if (posto?.drawflow_id) {
                    editor.removeNode(posto.drawflow_id);
                    mockPostos = mockPostos.filter(p => p.id !== id); // Usar !== para garantir remoção
                    mockConnections = mockConnections.filter(conn => 
                        conn.sourceDfId != posto.drawflow_id && conn.targetDfId != posto.drawflow_id);
                    isDirty = true;
                    saveFlowState();
                    updateNodeCount();
                }
            }
        }

        // Limpar todos os nós
        function clearFlow() {
            if (confirm('Tem certeza que deseja limpar todo o fluxo?')) {
                editor.clear();
                mockPostos = [];
                mockConnections = [];
                nextPostId = 1;
                isDirty = true;
                saveFlowState();
                updateNodeCount();
            }
        }

        // Adiciona uma conexão entre dois postos (IDs do mockPostos)
        function addConnection(sourceId, targetId) {
            const sourcePosto = mockPostos.find(p => p.id == sourceId); // Comparação flexível
            const targetPosto = mockPostos.find(p => p.id == targetId);
            if (sourcePosto && targetPosto && sourcePosto.drawflow_id && targetPosto.drawflow_id) {
                // Verifica se a conexão já existe no Drawflow
                const dfNode = editor.getNodeFromId(sourcePosto.drawflow_id);
                const existsInDf = dfNode && dfNode.outputs && dfNode.outputs.output_1 &&
                                   dfNode.outputs.output_1.connections.some(c => parseInt(c.node) === targetPosto.drawflow_id);
                
                if (!existsInDf) {
                    editor.addConnection(sourcePosto.drawflow_id, targetPosto.drawflow_id, 'output_1', 'input_1');
                    // mockConnections.push({ sourceDfId: sourcePosto.drawflow_id, targetDfId: targetPosto.drawflow_id }); // Removido, pois connectionCreated já faz isso
                    isDirty = true;
                    saveFlowState();
                }
            }
        }

        // Salva o estado completo do Drawflow e mostra feedback visual
        function saveFlowState() {
            if (!currentLinhaId || !editor || !editor.export) {
                console.warn('Não é possível salvar: Nenhuma linha selecionada ou editor não inicializado.', {
                    currentLinhaId,
                    editor: !!editor,
                    exportFunc: !!(editor && editor.export)
                });
                return;
            }

            // Proteção contra múltiplas requisições simultâneas
            if (isSaving) {
                console.warn('⏳ Salvamento já em andamento, pulando esta requisição');
                return;
            }

            if (!isLoading) { // Só salva se não estiver em processo de carregamento inicial
                const exportData = editor.export();
                const formData = new FormData();
                formData.append('action', 'save_drawflow_data');
                formData.append('linha_id', currentLinhaId);
                formData.append('drawflow_data', JSON.stringify(exportData));

                console.log('💾 Iniciando salvamento para linha:', currentLinhaId, 'com', Object.keys(exportData.drawflow.Home.data).length, 'nós');

                isSaving = true; // Marca como salvando
                
                fetch('api_drawflow.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Salva o estado da câmera (zoom e pan) no localStorage, específico para a linha
                            localStorage.setItem('flow_teste_zoom_' + currentLinhaId, editor.zoom);
                            localStorage.setItem('flow_teste_x_' + currentLinhaId, editor.canvas_x);
                            localStorage.setItem('flow_teste_y_' + currentLinhaId, editor.canvas_y);

                            const status = document.getElementById('saveStatus');
                            if (status) { status.style.opacity = '1'; setTimeout(() => { status.style.opacity = '0'; }, 1000); }
                            isDirty = false; // Marca o fluxo como limpo após salvar
                            console.log('✅ Fluxo salvo com sucesso para linha ' + currentLinhaId);
                        } else {
                            console.error('❌ Erro ao salvar fluxo:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('❌ Erro na requisição de salvamento:', error);
                    })
                    .finally(() => {
                        isSaving = false; // Marca como não salvando
                    });
            } else {
                console.log('⏳ isLoading=true, salvamento será disparado no evento apropriado');
            }
        }

        // Ações CRUD para Setores e Linhas
        async function crudAction(type, id = null) {
            const formData = new FormData();
            let nome = '';
            
            if (type === 'add_setor') {
                nome = await showInputDialog('Nome do novo Setor:');
                if (!nome) return;
                formData.append('action', 'add_setor');
                formData.append('nome', nome);
            } else if (type === 'delete_setor') {
                if (!confirm('Excluir este setor e TODAS as suas linhas?')) return;
                formData.append('action', 'delete_setor');
                formData.append('setor_id', '<?php echo $setor_id_ativo; ?>');
            } else if (type === 'add_linha') {
                nome = await showInputDialog('Nome da nova Linha:');
                if (!nome) return;
                formData.append('action', 'add_linha');
                formData.append('nome', nome);
                formData.append('setor_id', '<?php echo $setor_id_ativo; ?>');
            } else if (type === 'rename_linha') {
                nome = await showInputDialog('Novo nome para a linha:');
                if (!nome) return;
                formData.append('action', 'rename_linha');
                formData.append('linha_id', id);
                formData.append('nome', nome);
            } else if (type === 'delete_linha') {
                if (!confirm('Excluir esta linha permanentemente?')) return;
                formData.append('action', 'delete_linha');
                formData.append('linha_id', id);
            }

            fetch('api_drawflow.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.reload();
                    } else {
                        alert('Erro: ' + data.message);
                    }
                });
        }

        // Exibe opções para derivar novos balões
        function showDeriveOptions(parentId) {
            const posto = mockPostos.find(p => p.id == parentId); // Usar == para flexibilidade de tipo
            if (!posto || !editor) return; // Adiciona verificação do editor

            const nodeEl = document.getElementById('node-' + posto.drawflow_id);
            if (nodeEl) {
                const existingOptions = nodeEl.querySelector('.derive-options');
                if (existingOptions) {
                    existingOptions.remove();
                } else {
                    const escapedParentId = JSON.stringify(parentId); // Escapa o parentId
                    const optionsHtml = `
                        <div class="derive-options" style="padding: 10px; background: #fff; border: 1px solid #ccc; border-radius: 5px; margin-top: 10px; position: absolute; z-index: 100; min-width: 220px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); left: 50%; transform: translateX(-50%);">
                            <strong style="font-size: 11px; display: block; margin-bottom: 8px; color: #333;">Derivar de: ${posto.name}</strong>
                            <div style="display: flex; gap: 5px;">
                                <button onclick="addNodeToFlow(null, 'node', ${escapedParentId})" style="padding: 4px 8px; font-size: 10px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 3px;"><i class="fa-solid fa-industry"></i> Posto</button>
                                <button onclick="addNodeToFlow(null, 'decision_exclusive', ${escapedParentId})" style="padding: 4px 8px; font-size: 10px; cursor: pointer; background: #fbc02d; color: #000; border: none; border-radius: 3px;"><i class="fa-solid fa-xmark"></i> Decisão</button>
                                <button onclick="addNodeToFlow(null, 'decision_parallel', ${escapedParentId})" style="padding: 4px 8px; font-size: 10px; cursor: pointer; background: #4caf50; color: white; border: none; border-radius: 3px;"><i class="fa-solid fa-plus"></i> Paralelo</button>
                            </div>
                        </div>
                    `;
                    const div = document.createElement('div');
                    div.innerHTML = optionsHtml;
                    nodeEl.querySelector('.drawflow_content_node').appendChild(div.firstElementChild);
                }
            }
        }

        // Remove as opções de derivação quando o nó é deselecionado
        function removeDeriveOptions(drawflowId) {
            const nodeEl = document.getElementById('node-' + drawflowId);
            if (nodeEl) {
                const existingOptions = nodeEl.querySelector('.derive-options');
                if (existingOptions) {
                    existingOptions.remove();
                }
            }
        }
        // Atualizar contador de nós
        function updateNodeCount() {
            const exportData = editor.export();
            const nodeCount = Object.keys(exportData.drawflow.Home.data).length;
            document.getElementById('nodeCount').textContent = nodeCount;
        }

        // Configura os eventos do Drawflow
        function setupDrawflowEvents() {
            editor.on('connectionCreated', function(info) {
                if (isLoading) return; // Impede que eventos durante a carga inicial disparem salvamento
                mockConnections.push({ sourceDfId: parseInt(info.output_id), targetDfId: parseInt(info.input_id) }); // Atualiza nosso mock
                isDirty = true;
                saveFlowState();
            });

            editor.on('connectionRemoved', function(info) {
                if (isLoading) return;
                mockConnections = mockConnections.filter(conn => 
                    !(conn.sourceDfId === parseInt(info.output_id) && conn.targetDfId === parseInt(info.input_id))
                );
                isDirty = true;
                saveFlowState();
            });

            editor.on('nodeMoved', function(id) {
                if (isLoading) return;
                const node = editor.getNodeFromId(id);
                // Usa == para comparar string/number com segurança
                const posto = mockPostos.find(p => p.drawflow_id == id);
                if (posto && node) {
                    posto.x = parseFloat(node.pos_x); // Atualiza nosso mock
                    posto.y = parseFloat(node.pos_y); // Atualiza nosso mock
                    isDirty = true;
                    saveFlowState();
                }
            });

            // Salvar estado da câmera (Zoom e Pan)
            editor.on('zoom', function(zoom) {
                if (isLoading) return;
                isDirty = true;
                saveFlowState();
            });

            editor.on('translate', function(pos) {
                if (isLoading) return;
                isDirty = true;
                saveFlowState();
            });

            editor.on('nodeSelected', function(id) {
                // Oculta opções de derivação de outros nós
                mockPostos.forEach(p => { if (p.drawflow_id !== parseInt(id)) removeDeriveOptions(p.drawflow_id); });
            });
            // Outros eventos do Drawflow podem ser adicionados aqui
        }

        // Função para mostrar um diálogo modal customizado
        function showInputDialog(title, defaultValue = '') {
            return new Promise((resolve) => {
                const modal = document.getElementById('inputModal');
                const input = document.getElementById('modalInput');
                const okBtn = document.getElementById('modalOkBtn');
                const cancelBtn = document.getElementById('modalCancelBtn');
                const label = document.getElementById('modalLabel');

                label.textContent = title;
                input.value = defaultValue;
                modal.style.display = 'block';
                input.focus();
                input.select();

                const handleOk = () => {
                    const value = input.value.trim();
                    cleanupDialog();
                    resolve(value);
                };

                const handleCancel = () => {
                    cleanupDialog();
                    resolve(null);
                };

                const handleKeyDown = (e) => {
                    if (e.key === 'Enter') handleOk();
                    else if (e.key === 'Escape') handleCancel();
                };

                const cleanupDialog = () => {
                    modal.style.display = 'none';
                    okBtn.removeEventListener('click', handleOk);
                    cancelBtn.removeEventListener('click', handleCancel);
                    input.removeEventListener('keydown', handleKeyDown);
                };

                okBtn.addEventListener('click', handleOk);
                cancelBtn.addEventListener('click', handleCancel);
                input.addEventListener('keydown', handleKeyDown);
            });
        }

        // Override global prompt para usar o modal customizado
        const originalPrompt = window.prompt;
        window.prompt = function(title, defaultValue = '') {
            // Usar async/await com tratamento correto
            return showInputDialog(title, defaultValue);
        };

    </script>
    
    <!-- Modal Customizado -->
    <div id="inputModal">
        <div class="modal-content">
            <label id="modalLabel">Nome do novo posto:</label>
            <input type="text" id="modalInput" placeholder="">
            <div class="modal-buttons">
                <button class="btn-cancel" id="modalCancelBtn">Cancelar</button>
                <button class="btn-ok" id="modalOkBtn">OK</button>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// Sistema de Balanceamento - Fluxo da Linha com Abas
session_start();
include 'data_store.php';

// Garantir que estrutura de linhas existe
$linhas_json = load_json_data('linhas');
if (empty($linhas_json) || !is_array($linhas_json)) {
    $linhas_json = [
        ['id' => 'linha1', 'nome' => 'Linha 1', 'postos' => []],
        ['id' => 'linha2', 'nome' => 'Linha 2', 'postos' => []],
        ['id' => 'linha3', 'nome' => 'Linha 3', 'postos' => []]
    ];
    save_json_data('linhas', $linhas_json);
}

// Linha ativa (padrão: linha1)
$linha_ativa = isset($_GET['linha']) ? $_GET['linha'] : 'linha1';

// Validar se linha existe
$linha_selecionada = null;
foreach ($linhas_json as &$linha) {
    if ($linha['id'] === $linha_ativa) {
        $linha_selecionada = &$linha;
        break;
    }
}
if ($linha_selecionada === null && !empty($linhas_json)) {
    $linha_selecionada = &$linhas_json[0];
    $linha_ativa = $linhas_json[0]['id'];
}

// Adicionar novo posto à linha ativa
if (isset($_POST['adicionar_posto']) && !empty(trim($_POST['novo_posto']))) {
    $novo_posto = trim($_POST['novo_posto']);
    $existe = false;
    
    if ($linha_selecionada !== null) {
        foreach ($linha_selecionada['postos'] as $posto) {
            if ($posto['nome'] === $novo_posto) {
                $existe = true;
                break;
            }
        }
        if (!$existe) {
            $linha_selecionada['postos'][] = ['nome' => $novo_posto, 'configs' => [], 'detalhes' => []];
            save_json_data('linhas', $linhas_json);
        }
    }
}

// Remover posto da linha ativa
if (isset($_GET['remover_posto']) && is_numeric($_GET['remover_posto']) && $linha_selecionada !== null) {
    $index = (int)$_GET['remover_posto'];
    if (isset($linha_selecionada['postos'][$index])) {
        unset($linha_selecionada['postos'][$index]);
        $linha_selecionada['postos'] = array_values($linha_selecionada['postos']);
        save_json_data('linhas', $linhas_json);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Balanceamento - Fluxo da Linha</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.css">
    <script src="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.js"></script>
    <?php include 'menu.php'; ?>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f2f5;
            color: #333;
            overflow: hidden;
        }
        .header {
            background-color: #ffffff;
            border-bottom: 1px solid #e1e5e9;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-left: 270px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #2c3e50;
        }
        .indicadores {
            display: flex;
            gap: 30px;
        }
        .indicador {
            background-color: #f8f9fa;
            padding: 10px 20px;
            border-radius: 6px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        .indicador h3 {
            margin: 0 0 5px 0;
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .indicador p {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
            color: #495057;
        }
        .tabs-container {
            background-color: #ffffff;
            border-bottom: 1px solid #e1e5e9;
            padding: 0 30px;
            margin-left: 270px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .tabs {
            display: flex;
            gap: 5px;
            flex: 1;
        }
        .tab {
            padding: 12px 20px;
            background-color: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-bottom: none;
            border-radius: 6px 6px 0 0;
            cursor: pointer;
            font-weight: 500;
            color: #495057;
            transition: all 0.3s ease;
        }
        .main-content {
            display: flex;
            height: calc(100vh - 80px);
            margin-left: 270px;
        }
        .canvas-area {
            flex: 1;
            background-color: #ffffff;
            border-right: 1px solid #e1e5e9;
            position: relative;
        }
        #drawflow {
            width: 100%;
            height: 100%;
            background-color: #f8f9fa;
            background-image: 
                linear-gradient(rgba(0,0,0,0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,0,0,0.05) 1px, transparent 1px);
            background-size: 20px 20px;
        }
        .properties-panel {
            width: 350px;
            background-color: #ffffff;
            border-left: 1px solid #e1e5e9;
            padding: 20px;
            overflow-y: auto;
            box-shadow: -2px 0 4px rgba(0,0,0,0.1);
        }
        .properties-panel h2 {
            margin-top: 0;
            font-size: 18px;
            color: #2c3e50;
            border-bottom: 1px solid #e1e5e9;
            padding-bottom: 10px;
        }
        .properties-panel .property {
            margin-bottom: 15px;
        }
        .properties-panel label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #495057;
        }
        .properties-panel input, .properties-panel select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        .properties-panel button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            width: 100%;
        }
        .properties-panel button:hover {
            background-color: #0056b3;
        }
        .toolbar {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: #ffffff;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            padding: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .toolbar button {
            margin-right: 5px;
            padding: 8px 12px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .toolbar button:hover {
            background-color: #5a6268;
        }
        .drawflow-node {
            background-color: #ffffff !important;
            border: 2px solid #007bff !important;
            border-radius: 8px !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
            min-width: 150px !important;
            min-height: 80px !important;
        }
        .drawflow-node.selected {
            border-color: #28a745 !important;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.3) !important;
        }
        .drawflow-node .drawflow_content_node {
            padding: 10px !important;
            text-align: center !important;
        }
        .drawflow-node .title-box {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .drawflow-node .outputs, .drawflow-node .inputs {
            width: 10px !important;
            height: 10px !important;
            background-color: #007bff !important;
            border: 1px solid #ffffff !important;
        }
        .drawflow .connection .main-path {
            stroke: #007bff !important;
            stroke-width: 3 !important;
        }
        .drawflow .connection:hover .main-path {
            stroke: #28a745 !important;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sistema de Balanceamento - Modelagem de Processo</h1>
        <div class="indicadores">
            <div class="indicador">
                <h3>Postos</h3>
                <p><?php echo count($linha_selecionada['postos'] ?? []); ?></p>
            </div>
            <div class="indicador">
                <h3>Tempo de Ciclo</h3>
                <p>10 min</p>
            </div>
            <div class="indicador">
                <h3>Taxa de Produção</h3>
                <p>100 u/h</p>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="canvas-area">
            <div class="toolbar">
                <button onclick="addNode()">Adicionar Posto</button>
                <button onclick="clearCanvas()">Limpar</button>
                <div style="margin-top: 8px; padding: 8px; background-color: #f0f0f0; border-radius: 4px; font-size: 12px;">
                    Nodes: <span id="nodeCount">0</span>
                </div>
            </div>
            <div id="drawflow"></div>
        </div>

        <div class="properties-panel">
            <h2>Propriedades do Posto</h2>
            <div id="properties-content">
                <p>Selecione um posto no fluxo para ver suas propriedades.</p>
            </div>
        </div>
    </div>

    <script>
        // Handler global de erros
        window.addEventListener('error', function(event) {
            console.error('Erro global:', event.error);
        });
        
        <?php
        // Passar dados da linha ativa para JS
        $postos_js = [];
        if ($linha_selecionada !== null) {
            foreach ($linha_selecionada['postos'] as $index => $posto) {
                $postos_js[] = [
                    'index' => $index,
                    'nome' => $posto['nome'],
                    'detalhes' => $posto['detalhes'] ?? []
                ];
            }
        }
        ?>

        console.log('=== INICIANDO DRAWFLOW ===');
        console.log('Dados PHP:', {
            linha_ativa: '<?php echo htmlspecialchars($linha_ativa); ?>',
            total_postos: <?php echo count($postos_js); ?>
        });
        
        var drawflowElement = document.getElementById("drawflow");
        if (!drawflowElement) {
            console.error('ERRO: elemento "drawflow" não encontrado!');
            throw new Error('Elemento drawflow não encontrado');
        }
        
        console.log('Elemento Drawflow encontrado:', drawflowElement);
        console.log('Dimensões do container:', drawflowElement.offsetWidth, 'x', drawflowElement.offsetHeight);
        console.log('Classe do container:', drawflowElement.className);
        
        var editor = new Drawflow(drawflowElement);
        console.log('Instância Drawflow criada:', editor);
        
        editor.start();
        console.log('Drawflow iniciado');
        console.log('Modo do editor:', editor.mode);
        console.log('Zoom do editor:', editor.zoom);

        var postos = <?php echo json_encode($postos_js); ?>;
        var nodeIds = [];
        var selectedNodeId = null;

        console.log('Postos carregados do PHP:', postos);
        console.log('Total de postos:', postos.length);
        console.log('JSON bruto:', JSON.stringify(postos, null, 2));

        // Função para adicionar node
        function addNode() {
            var nome = prompt("Digite o nome do posto:");
            if (nome) {
                // Adicionar via form submit
                var form = document.createElement('form');
                form.method = 'post';
                form.action = '';
                var inputNome = document.createElement('input');
                inputNome.type = 'hidden';
                inputNome.name = 'novo_posto';
                inputNome.value = nome;
                var inputBtn = document.createElement('input');
                inputBtn.type = 'hidden';
                inputBtn.name = 'adicionar_posto';
                inputBtn.value = '1';
                form.appendChild(inputNome);
                form.appendChild(inputBtn);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Função para limpar canvas
        function clearCanvas() {
            if (confirm('Limpar todo o fluxo?')) {
                editor.clear();
                nodeIds = [];
                selectedNodeId = null;
                document.getElementById('properties-content').innerHTML = '<p>Selecione um posto no fluxo para ver suas propriedades.</p>';
            }
        }

        // Carregar nodes existentes quando o DOM estiver pronto
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM carregado, renderizando nodes...');
                renderNodes();
            });
        } else {
            // DOM já está pronto, renderizar imediatamente
            console.log('DOM já pronto, renderizando nodes...');
            renderNodes();
        }

        function renderNodes() {
            console.log('=== RENDERIZANDO NODES ===');
            console.log('Quantidade de postos a renderizar:', postos.length);
            
            if (postos.length === 0) {
                console.log('Nenhum posto para renderizar');
                document.getElementById('nodeCount').textContent = '0';
                return;
            }
            
            // Limpar nodes anteriores se existirem
            nodeIds = [];
            try {
                editor.clear();
            } catch(e) {
                console.log('Info: clear() não disponível');
            }
            
            try {
                // Carregar nodes existentes
                postos.forEach(function(posto, idx) {
                    console.log('Criando node para posto:', posto.nome, 'Index:', posto.index, 'Idx:', idx);
                    
                    // HTML muito simples do node
                    var html = '<div style="padding: 15px;"><strong>' + htmlEscape(posto.nome) + '</strong></div>';
                    
                    // Posicionar nodes em linha horizontal
                    var xPos = 150 + idx * 250;
                    var yPos = 150;
                    
                    console.log('Posição:', xPos, 'x', yPos);
                    
                    // Adicionar o node ao editor
                    var nodeId = editor.addNode('posto', 1, 1, xPos, yPos, 'posto', {index: posto.index}, html);
                    nodeIds.push({id: nodeId, postoIndex: posto.index});
                    
                    console.log('✓ Node criado - ID:', nodeId);
                });

                // Conectar nodes em sequência
                for (var i = 0; i < nodeIds.length - 1; i++) {
                    try {
                        editor.addConnection(nodeIds[i].id, nodeIds[i+1].id, "output_1", "input_1");
                        console.log('✓ Conexão:', nodeIds[i].id, '→', nodeIds[i+1].id);
                    } catch(e) {
                        console.log('Info ao conectar:', e.message);
                    }
                }
                
                console.log('Total de nodes:', nodeIds.length);
                document.getElementById('nodeCount').textContent = nodeIds.length;
                
            } catch(e) {
                console.error('✗ ERRO ao renderizar:', e.message);
                console.error('Stack:', e.stack);
            }
        }
        
        // Função auxiliar para escapar HTML
        function htmlEscape(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        }, 500);

        // Evento para seleção de node
        editor.on('nodeSelected', function(id) {
            selectedNodeId = id;
            var nodeData = editor.getNodeFromId(id);
            var postoIndex = nodeData.data.index;
            var posto = postos.find(p => p.index == postoIndex);
            if (posto) {
                var detalhes = posto.detalhes || {};
                var content = '<div class="property">' +
                              '<label>Nome:</label>' +
                              '<input type="text" value="' + posto.nome + '" readonly>' +
                              '</div>' +
                              '<div class="property">' +
                              '<label>Unidade Típica:</label>' +
                              '<input type="text" value="' + (detalhes.unidade_tipica || '') + '" readonly>' +
                              '</div>' +
                              '<div class="property">' +
                              '<label>Quantidade por Item:</label>' +
                              '<input type="text" value="' + (detalhes.quantidade_item || '') + '" readonly>' +
                              '</div>' +
                              '<div class="property">' +
                              '<label>Tempo Total:</label>' +
                              '<input type="text" value="' + (detalhes.tempo_total || '') + ' min" readonly>' +
                              '</div>' +
                              '<div class="property">' +
                              '<label>Tempo por Item:</label>' +
                              '<input type="text" value="' + (detalhes.tempo_por_item || '') + ' min" readonly>' +
                              '</div>' +
                              '<div class="property">' +
                              '<label>Fator de Correlação:</label>' +
                              '<input type="text" value="' + (detalhes.fator_correlacao || '') + '" readonly>' +
                              '</div>' +
                              '<button onclick="editPosto(' + postoIndex + ')">Editar Detalhes</button>';
                document.getElementById('properties-content').innerHTML = content;
            }
        });

        // Função para editar posto
        function editPosto(index) {
            window.location.href = 'tabela_posto.php?posto=' + index + '&linha=<?php echo htmlspecialchars($linha_ativa); ?>';
        }

        // Desmarcar seleção
        editor.on('nodeUnselected', function() {
            selectedNodeId = null;
            document.getElementById('properties-content').innerHTML = '<p>Selecione um posto no fluxo para ver suas propriedades.</p>';
        });
    </script>
    </script>
</body>
</html>

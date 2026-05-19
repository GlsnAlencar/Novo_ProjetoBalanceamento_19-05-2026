<?php
/**
 * Sistema de Balanceamento - Fluxo da Linha
 * Suporta múltiplas linhas e fluxos com Drawflow
 */
session_start();
include 'data_store.php';

// Carregar dados
$unidades_json = load_json_data('unidades');
$setores_json = load_json_data('setores');
$linhas_json = load_json_data('linhas');

// Garantir que há pelo menos um setor
if (empty($setores_json)) {
    $setores_json = [
        create_default_setor('Setor 1', 'Fluxo Principal'),
        create_default_setor('Setor 2', 'Fluxo Secundário')
    ];
    save_json_data('setores', $setores_json);
}

// Garantir que há pelo menos uma linha para cada setor sem linhas
foreach ($setores_json as $setor) {
    $tem_linha = false;
    foreach ($linhas_json as $linha) {
        if ($linha['setor_id'] === $setor['id']) {
            $tem_linha = true;
            break;
        }
    }
    if (!$tem_linha) {
        $nova_linha = create_default_linha($setor['nome'], $setor['id']);
        $linhas_json[] = $nova_linha;
        if (!isset($setor['linhas_ids'])) {
            $setor['linhas_ids'] = [];
        }
        $setor['linhas_ids'][] = $nova_linha['id'];
    }
}
if (count($linhas_json) > 0) {
    save_json_data('linhas', $linhas_json);
    save_json_data('setores', $setores_json);
}

// Determinar setor/linha ativa
$setor_id = $_GET['setor_id'] ?? $_GET['setor'] ?? ($setores_json[0]['id'] ?? null);
$linha_id = $_GET['linha_id'] ?? null;

// Encontrar linha selecionada
$linha_selecionada = null;
if ($setor_id) {
    foreach ($linhas_json as $l) {
        if (isset($l['setor_id']) && $l['setor_id'] === $setor_id) {
            $linha_selecionada = $l;
            $linha_id = $l['id'];
            break;
        }
    }
}

$setor_atual = find_setor_by_id($setores_json, $setor_id);
$linha_ativa = $linha_selecionada['nome'] ?? 'Linha não identificada';

// ========== ATUALIZAR UNIDADE BÁSICA ==========
if (isset($_POST['definir_unidade_basica']) && isset($_POST['linha_id']) && isset($_POST['unidade_basica'])) {
    $unidade_basica = trim($_POST['unidade_basica']);
    
    $linha_ref = find_linha_by_id($linhas_json, $_POST['linha_id']);
    if ($linha_ref !== null) {
        $linha_ref['unidade_basica'] = ($unidade_basica !== '') ? $unidade_basica : null;
        save_json_data('linhas', $linhas_json);
        header('Location: ?setor_id=' . urlencode($setor_id) . '&linha_id=' . urlencode($linha_id));
        exit;
    }
}

// ========== CÁLCULOS DE INDICADORES ==========
$tempo_ciclo = 0;
$total_kg_produzido = 0;
$total_pessoas = 0;
$total_tempo_consolidado = 0;
$tempo_ciclo_por_posto = [];
$tempo_por_unidade_basica_por_posto = [];
$tempo_por_contentor_por_posto = [];

// Buscar peso do contentor nas unidades cadastradas
$peso_contentor = 0;
foreach ($unidades_json as $un) {
    if (strtolower(trim($un['nome'])) === 'contentor') {
        $peso_contentor = floatval($un['peso_padrao'] ?? 0);
        break;
    }
}

if ($linha_selecionada !== null && !empty($linha_selecionada['postos'])) {
    foreach ($linha_selecionada['postos'] as $idx => $posto) {
        $num_pessoas = $posto['recursos']['num_pessoas'] ?? 1;
        $total_pessoas += $num_pessoas;

        $tempo_ciclo_posto = 0;
        $tempo_por_unidade_basica = 0;
        $encontrou_unidade_basica = false;
        $tempo_por_peso_consolidado_posto = 0;
        $quantidade_total_posto = 0;

        if (!empty($posto['atividades'])) {
            foreach ($posto['atividades'] as $atividade) {
                if ($atividade['tempo_total'] > $tempo_ciclo_posto) {
                    $tempo_ciclo_posto = $atividade['tempo_total'];
                }
                if ($atividade['tempo_total'] > $tempo_ciclo) {
                    $tempo_ciclo = $atividade['tempo_total'];
                }

                $kg_por_atividade = $atividade['quantidade'] * $atividade['peso_unidade'];
                $total_kg_produzido += $kg_por_atividade;
                $total_tempo_consolidado += $atividade['tempo_total'];

                // Acumular para tempo/contentor
                $tempo_por_peso_consolidado_posto += ($atividade['tempo_por_peso'] ?? 0) * $atividade['quantidade'];
                $quantidade_total_posto += $atividade['quantidade'];

                if (!empty($linha_selecionada['unidade_basica']) && isset($atividade['unidade']) && $atividade['unidade'] === $linha_selecionada['unidade_basica']) {
                    $tempo_por_unidade_basica = $atividade['tempo_por_unidade'] ?? $tempo_por_unidade_basica;
                    $encontrou_unidade_basica = true;
                }
            }
        }

        $tempo_ciclo_por_posto[$idx] = $tempo_ciclo_posto;
        $tempo_por_unidade_basica_por_posto[$idx] = $encontrou_unidade_basica ? $tempo_por_unidade_basica : 0;

        // Tempo/Contentor = tempo médio por kg × peso do contentor
        $tempo_por_peso_medio_posto = $quantidade_total_posto > 0 ? $tempo_por_peso_consolidado_posto / $quantidade_total_posto : 0;
        $tempo_por_contentor_por_posto[$idx] = ($peso_contentor > 0 && $tempo_por_peso_medio_posto > 0)
            ? round($tempo_por_peso_medio_posto * $peso_contentor, 2)
            : 0;
    }
}

// Taxa de produção: (total_kg / tempo_ciclo) em kg por minuto (se tempo_ciclo > 0)
$taxa_producao = ($tempo_ciclo > 0) ? round(($total_kg_produzido / $tempo_ciclo) * 60, 2) : 0; // kg/min

// ========== SALVAR CONFIGURAÇÃO DE POSTOS ==========
if (isset($_POST['salvar']) && isset($_POST['salvar_postos'])) {
    $dados_postos = json_decode($_POST['salvar_postos'], true);
    
    if (is_array($dados_postos) && $linha_selecionada) {
        foreach ($linhas_json as &$linha) {
            if ($linha['id'] === $linha_id) {
                $linha['postos'] = $dados_postos;
                break;
            }
        }
        unset($linha);
        
        save_json_data('linhas', $linhas_json);
        
        // Retornar JSON com sucesso
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Configuração salva']);
        exit;
    }
}

// Adicionar novo posto à linha ativa
if (isset($_POST['adicionar_posto']) && !empty(trim($_POST['novo_posto']))) {
    $novo_posto = trim($_POST['novo_posto']);
    $existe = false;
    $posicao_apos = isset($_POST['posicao_apos']) ? intval($_POST['posicao_apos']) : null;
    $paralelo_com = isset($_POST['paralelo_com']) ? intval($_POST['paralelo_com']) : null;
    
    if ($linha_selecionada !== null) {
        // Verificar se o posto já existe
        foreach ($linha_selecionada['postos'] as $posto) {
            if (strtolower($posto['nome']) === strtolower($novo_posto)) {
                $existe = true;
                break;
            }
        }
        
        // Adicionar apenas se não existe
        if (!$existe) {
            $novo_post_obj = create_default_posto($novo_posto);
            $novo_post_obj['recursos']['num_pessoas'] = 0;
            
            // Se é paralelo a outro, adicionar estrutura de paralelismo
            if ($paralelo_com !== null && isset($linha_selecionada['postos'][$paralelo_com])) {
                // Inicializar campo de paralelo se não existir
                if (!isset($linha_selecionada['postos'][$paralelo_com]['paralelo_com'])) {
                    $linha_selecionada['postos'][$paralelo_com]['paralelo_com'] = [];
                }
                // Adicionar como paralelo ao final (novo índice será count)
                $novo_post_obj['paralelo_de'] = $paralelo_com;
                $linha_selecionada['postos'][$paralelo_com]['paralelo_com'][] = count($linha_selecionada['postos']);
            }
            
            // Se é após um posto específico, inserir na posição correta
            if ($posicao_apos !== null && isset($linha_selecionada['postos'][$posicao_apos])) {
                // Inserir após a posição especificada
                $postos_array = array_values($linha_selecionada['postos']);
                array_splice($postos_array, $posicao_apos + 1, 0, [$novo_post_obj]);
                $linha_selecionada['postos'] = $postos_array;
            } else {
                // Adicionar ao final
                $linha_selecionada['postos'][] = $novo_post_obj;
            }
            
            // Atualizar a referência no array principal
            foreach ($linhas_json as $key => $linha) {
                if ($linha['id'] === $linha_id) {
                    $linhas_json[$key] = $linha_selecionada;
                    break;
                }
            }
            
            save_json_data('linhas', $linhas_json);
            
            // Redirecionar para evitar resubmissão de formulário
            header('Location: ?setor_id=' . urlencode($setor_id) . '&linha_id=' . urlencode($linha_id));
            exit;
        }
    }
}

// ========== ATUALIZAR NÚMERO DE PESSOAS (AJAX) ==========
if (isset($_POST['atualizar_pessoas']) && isset($_POST['post_index']) && isset($_POST['num_pessoas'])) {
    $post_index = (int)$_POST['post_index'];
    $num_pessoas = (int)$_POST['num_pessoas'];
    $linha_id_ajax = $_POST['linha_id'] ?? $linha_id;

    // Encontrar a chave da linha no array principal (seguro para várias cópias)
    $linha_key = null;
    foreach ($linhas_json as $k => $l) {
        if (isset($l['id']) && $l['id'] === $linha_id) {
            $linha_key = $k;
            break;
        }
    }

    // Validar e atualizar
    if ($linha_key !== null && isset($linhas_json[$linha_key]['postos'][$post_index])) {
        if (!isset($linhas_json[$linha_key]['postos'][$post_index]['recursos'])) {
            $linhas_json[$linha_key]['postos'][$post_index]['recursos'] = [];
        }

        $linhas_json[$linha_key]['postos'][$post_index]['recursos']['num_pessoas'] = max(0, $num_pessoas);
        save_json_data('linhas', $linhas_json);

        header('Content-Type: application/json');
        echo json_encode(['sucesso' => true, 'num_pessoas' => $num_pessoas]);
        exit;
    } else {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['sucesso' => false, 'erro' => 'Linha ou posto não encontrado']);
        exit;
    }
}

// ========== SALVAR CONEXÕES (AJAX) ==========
if (isset($_POST['salvar_conexoes']) && isset($_POST['linha_id'])) {
    $conexoes_dados = json_decode($_POST['salvar_conexoes'], true);
    $linha_id_ajax = $_POST['linha_id'];

    if (is_array($conexoes_dados)) {
        foreach ($linhas_json as &$linha) {
            if ($linha['id'] === $linha_id_ajax) {
                $linha['conexoes'] = $conexoes_dados;
                break;
            }
        }
        save_json_data('linhas', $linhas_json);
        header('Content-Type: application/json');
        echo json_encode(['sucesso' => true]);
        exit;
    }
}

// Remover posto da linha ativa
if (isset($_GET['remover_posto']) && is_numeric($_GET['remover_posto']) && $linha_selecionada !== null) {
    $index = (int)$_GET['remover_posto'];
    
    if (isset($linha_selecionada['postos'][$index])) {
        $postoARemover = $linha_selecionada['postos'][$index];
        
        // Limpar referências de paralelismo
        // Se o posto a remover tem um pai paralelo, removê-lo da lista de paralelos do pai
        if (isset($postoARemover['paralelo_de'])) {
            $idxPai = $postoARemover['paralelo_de'];
            if (isset($linha_selecionada['postos'][$idxPai]['paralelo_com'])) {
                $linha_selecionada['postos'][$idxPai]['paralelo_com'] = array_filter(
                    $linha_selecionada['postos'][$idxPai]['paralelo_com'],
                    function($idx) use ($index) { return $idx !== $index; }
                );
            }
        }
        
        // Se o posto a remover tem filhos em paralelo, removê-los também ou desvinculá-los
        if (isset($postoARemover['paralelo_com'])) {
            foreach ($postoARemover['paralelo_com'] as $idxFilho) {
                if (isset($linha_selecionada['postos'][$idxFilho])) {
                    unset($linha_selecionada['postos'][$idxFilho]['paralelo_de']);
                }
            }
        }
        
        // Remover o posto
        unset($linha_selecionada['postos'][$index]);
        
        // Reindexar array e ajustar índices de paralelo
        $postos_antigos = $linha_selecionada['postos'];
        $linha_selecionada['postos'] = array_values($postos_antigos);
        
        // Ajustar índices nas referências de paralelismo
        $mapa_indices = [];
        $novo_idx = 0;
        foreach ($postos_antigos as $idx_antigo => $posto) {
            $mapa_indices[$idx_antigo] = $novo_idx;
            $novo_idx++;
        }
        
        // Recalcular índices de paralelismo
        foreach ($linha_selecionada['postos'] as &$posto) {
            if (isset($posto['paralelo_de']) && isset($mapa_indices[$posto['paralelo_de']])) {
                $posto['paralelo_de'] = $mapa_indices[$posto['paralelo_de']];
            }
            if (isset($posto['paralelo_com'])) {
                $posto['paralelo_com'] = array_map(function($idx) use ($mapa_indices) {
                    return isset($mapa_indices[$idx]) ? $mapa_indices[$idx] : null;
                }, $posto['paralelo_com']);
                $posto['paralelo_com'] = array_filter($posto['paralelo_com'], function($x) { return $x !== null; });
            }
        }
        unset($posto);
        
        // Atualizar a referência no array principal
        foreach ($linhas_json as $key => $linha) {
            if ($linha['id'] === $linha_id) {
                $linhas_json[$key] = $linha_selecionada;
                break;
            }
        }
        
        save_json_data('linhas', $linhas_json);
        
        // Redirecionar para atualizar a página
        header('Location: ?linha=' . urlencode($linha_ativa));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Balanceamento - Fluxo da Linha</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.css">
    <script src="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.js"></script>
    <?php include 'menu.php'; ?>
    <style>
        /* Ajustes específicos para o layout do Drawflow que não estão no styles.css */
        .main-content {
            display: flex; 
            height: calc(100vh - 180px); /* Ajustado para compensar header e abas */
            margin-left: var(--sidebar-width);
            overflow: hidden;
        }
        
        .canvas-area {
            flex: 1;
            border-right: 1px solid #e1e5e9;
            position: relative;
            overflow: hidden;
        }
        
        #drawflow {
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(0,0,0,0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,0,0,0.05) 1px, transparent 1px);
            background-size: 20px 20px;
        }
        
        /* Painel de Propriedades Refatorado */
        .properties-panel {
            width: 350px;
            border-left: 1px solid #e1e5e9;
            padding: 15px;
            overflow-y: auto;
            box-shadow: -2px 0 4px rgba(0,0,0,0.1);
            transition: width 0.3s ease, padding 0.3s ease;
        }

        .properties-panel.collapsed {
            width: 50px;
            padding: 10px 5px;
            overflow: hidden;
        }
        
        .properties-panel h2 {
            margin-top: 0;
            font-size: 18px;
            color: var(--primary-color);
            border-bottom: 1px solid #e1e5e9;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
        }

        .properties-panel.collapsed h2 {
            writing-mode: vertical-lr;
            transform: rotate(180deg);
            font-size: 14px;
            white-space: nowrap;
            border: none;
            padding: 0;
        }

        .toggle-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #6c757d;
            padding: 0 5px;
        }

        .toggle-btn:hover {
            color: #2c3e50;
        }

        .properties-content {
            transition: opacity 0.3s ease;
        }

        .properties-panel.collapsed .properties-content {
            display: none;
        }
        
        .properties-panel input, 
        .properties-panel select, 
        .properties-panel textarea {
            width: 100%;
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
        
        .toolbar {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px;
            box-shadow: var(--card-shadow);
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .drawflow-node {
            border: 2px solid var(--primary-color) !important;
            min-width: 240px !important;
            min-height: 180px !important;
        }
        
        .drawflow-node .drawflow_content_node {
            padding: 8px !important;
            text-align: center !important;
            color: #2c3e50 !important;
        }
        
        .node-header {
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 8px 12px;
            font-weight: bold;
            border-radius: 8px 8px 0 0;
            text-align: left;
            font-size: 13px;
            color: #34495e;
            display: flex;
            justify-content: space-between;
        }
        
        .node-body {
            padding: 12px;
            text-align: left;
        }
        
        .drawflow-node.selected {
            border: 2px solid #3498db !important;
            box-shadow: 0 0 15px rgba(52, 152, 219, 0.3) !important;
        }
        
        .drawflow-node .inputs, .drawflow-node .outputs {
            background: #3498db !important;
        }
        
        .drawflow-node strong {
            display: block;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .drawflow-node input[type="number"] {
            padding: 4px 6px !important;
            border: 1px solid #ced4da !important;
            border-radius: 4px !important;
            font-size: 12px !important;
            background-color: #ffffff !important;
        }
        
        .drawflow-node input[type="number"]:focus {
            outline: none !important;
            border-color: #007bff !important;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25) !important;
        }

        .btn-grupo-acao {
            display: inline-block;
            margin: 3px 2px;
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            font-weight: bold;
            transition: all 0.2s;
        }

        .btn-inserir-pos {
            background-color: #28a745;
            color: white;
        }

        .btn-inserir-pos:hover {
            background-color: #218838;
            transform: scale(1.05);
        }

        .btn-inserir-par {
            background-color: #ffc107;
            color: black;
        }

        .btn-inserir-par:hover {
            background-color: #e0a800;
            transform: scale(1.05);
        }

        .info-conexoes {
            font-size: 10px;
            color: #666;
            margin-top: 4px;
            padding: 4px;
            background-color: #f0f0f0;
            border-radius: 3px;
            border-left: 2px solid #007bff;
        }

        .drawflow-node.paralelo-ativo {
            border-color: #ffc107 !important;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.3) !important;
        }

        .drawflow-node.convergencia-ativo {
            border-color: #17a2b8 !important;
            box-shadow: 0 0 0 3px rgba(23, 162, 184, 0.3) !important;
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
                <h3>T.C.</h3>
                <p><?php echo number_format($tempo_ciclo, 1, ',', '.'); ?> s</p>
            </div>
            <div class="indicador">
                <h3>Taxa de Produção</h3>
                <p><?php echo number_format($taxa_producao, 2, ',', '.'); ?> kg/min</p>
            </div>
            <div class="indicador">
                <h3>Pessoas Alocadas</h3>
                <p><?php echo $total_pessoas; ?> 👥</p>
            </div>
        </div>
    </div>

    <!-- Sistema de Abas com Seleção de Setor e Linhas -->
    <div class="excel-tabs-container">
        <!-- Barra Superior: Seleção de Setor -->
        <div class="sector-selector-bar">
            <label style="font-weight: 700; color: #2c3e50; margin: 0;">🏢 Setor Ativo:</label>
            <select id="setor_select" onchange="window.location.href='?setor_id=' + this.value;" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #ccd0d5; background-color: #f0f2f5; cursor: pointer; font-weight: 600;">
                <?php foreach ($setores_json as $s): ?>
                    <option value="<?php echo htmlspecialchars($s['id']); ?>" <?php echo ($s['id'] === $setor_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Barra Inferior: Abas das Linhas -->
        <div class="line-tabs-bar">
            <?php 
            $linhas_do_setor = get_linhas_by_setor($linhas_json, $setor_id);
            foreach ($linhas_do_setor as $l): ?>
                <a href="?linha_id=<?php echo urlencode($l['id']); ?>" 
                   class="excel-tab <?php echo ($l['id'] === $linha_id) ? 'active' : ''; ?>" style="cursor: pointer;">
                    📦 <?php echo htmlspecialchars($l['nome']); ?> 
                    <span style="font-size: 10px; opacity: 0.7;">(<?php echo count($l['postos']); ?>)</span>
                </a>
            <?php endforeach; ?>
            
            <a href="linhas.php?setor_id=<?php echo urlencode($setor_id); ?>" class="excel-tab" style="background: #e8f5e9; color: #388e3c; cursor: pointer;">
                ➕ Nova Linha
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="canvas-area">
            <div class="toolbar">
                <button onclick="addPost()" class="btn btn-sm btn-primary">➕ Novo Posto</button>
                <button onclick="clearFluxo()" class="btn btn-sm btn-secondary">🗑️ Limpar</button>
                
                <div style="height: 24px; border-left: 1px solid #ddd; margin: 0 5px;"></div>

                <form method="POST" style="display: flex; align-items: center; gap: 10px;">
                    <input type="hidden" name="linha_id" value="<?php echo htmlspecialchars($linha_id); ?>">
                    <select name="unidade_basica" class="btn-sm" style="width: auto;">
                        <option value="">-- Nenhuma --</option>
                        <?php foreach ($unidades_json as $un): ?>
                            <option value="<?php echo htmlspecialchars($un['nome']); ?>" <?php echo (($linha_selecionada['unidade_basica'] ?? '') === $un['nome']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($un['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="definir_unidade_basica" class="btn btn-sm btn-info">Definir Unidade Base</button>
                </form>
            </div>
            <div id="drawflow"></div>
        </div>

        <div class="properties-panel" id="properties-panel">
            <h2 onclick="togglePropertiesPanel()">
                <span>🛠️ Propriedades</span>
                <button type="button" class="modal-close" style="font-size: 20px;">&times;</button>
            </h2>
            <div class="properties-content" id="properties-content" style="padding-top: 20px;">
                <p class="text-muted" style="text-align: center;">🖱️ Selecione um posto no fluxo para gerenciar detalhes.</p>
            </div>
        </div>
    </div>

    <script>
        // Variáveis globais
        var editor = null;
        var nodeIds = {}; // Mapa: índice => nodeId
        var selectedNodeId = null;
        var flowStructure = {}; // Rastrear relacionamentos: {node_id: {paralelos: [], proximos: []}}
        
        // Dados dos postos da linha ativa
        var postos = <?php echo json_encode(
            isset($linha_selecionada['postos']) ? array_values($linha_selecionada['postos']) : []
        ); ?>;
        var conexoes = <?php echo json_encode(
            isset($linha_selecionada['conexoes']) ? $linha_selecionada['conexoes'] : []
        ); ?>;

        var tempoCicloPorPosto = <?php echo json_encode($tempo_ciclo_por_posto); ?>;
        var tempoUnidadeBasicaPorPosto = <?php echo json_encode($tempo_por_unidade_basica_por_posto); ?>;
        var tempoContenitorPorPosto = <?php echo json_encode($tempo_por_contentor_por_posto); ?>;
        var unidadeBasicaLinha = '<?php echo htmlspecialchars($linha_selecionada['unidade_basica'] ?? ''); ?>';
        var linhaId = '<?php echo htmlspecialchars($linha_id ?? ''); ?>';

        console.log('📊 Dados carregados:', {
            linha_ativa: '<?php echo htmlspecialchars($linha_ativa); ?>',
            total_postos: postos.length,
            unidade_basica: unidadeBasicaLinha,
            postos: postos,
            tempoCicloPorPosto: tempoCicloPorPosto,
            tempoUnidadeBasicaPorPosto: tempoUnidadeBasicaPorPosto
        });

        // ========== INICIALIZAÇÃO ==========
        function initDrawflow() {
            try {
                var container = document.getElementById('drawflow');
                if (!container) {
                    console.error('❌ Elemento #drawflow não encontrado!');
                    return false;
                }

                editor = new Drawflow(container);
                editor.start();
                
                // Restaurar Zoom e Posição da Câmera
                const savedZoom = localStorage.getItem('df_zoom_' + linhaId);
                const savedX = localStorage.getItem('df_x_' + linhaId);
                const savedY = localStorage.getItem('df_y_' + linhaId);
                
                if (savedZoom) editor.zoom = parseFloat(savedZoom);
                if (savedX) editor.canvas_x = parseFloat(savedX);
                if (savedY) editor.canvas_y = parseFloat(savedY);
                editor.updateRoot();

                console.log('✓ Drawflow inicializado com sucesso');
                renderNodes();
                setupEventHandlers();
                
                return true;
            } catch (e) {
                console.error('❌ Erro ao inicializar Drawflow:', e.message);
                return false;
            }
        }

        function togglePropertiesPanel() {
            var panel = document.getElementById('properties-panel');
            panel.classList.toggle('active');
        }

        function updateNumPessoas(index, value, nodeId) {
            var num = parseInt(value);
            if (isNaN(num)) num = 0;
            if (postos[index]) {
                if (!postos[index].recursos) {
                    postos[index].recursos = {};
                }
                postos[index].recursos.num_pessoas = num;
                console.log(`✓ Pessoas atualizado para posto ${index}: ${num}`);
                
                // Salvar automaticamente
                salvarConfiguracao();
                
                // Recalcular ritmo visualmente sem renderizar tudo
                var tempoContenitor = tempoContenitorPorPosto[index] || 0;
                var ritmo = (tempoContenitor > 0 && num > 0) ? (tempoContenitor / num).toFixed(2) : '—';
                
                var nodeEl = document.querySelector(`#node-${nodeId}`);
                if (nodeEl) {
                    nodeEl.querySelector('.ritmo-value').textContent = ritmo;
                }
            }
        }

        function salvarConfiguracao() {
            // Criar form dinamicamente para salvar
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '?linha=<?php echo htmlspecialchars($linha_ativa); ?>';
            form.style.display = 'none';

            var inputPostos = document.createElement('input');
            inputPostos.type = 'hidden';
            inputPostos.name = 'salvar_postos';
            inputPostos.value = JSON.stringify(postos);

            var inputSubmit = document.createElement('input');
            inputSubmit.type = 'hidden';
            inputSubmit.name = 'salvar';
            inputSubmit.value = '1';

            form.appendChild(inputPostos);
            form.appendChild(inputSubmit);
            document.body.appendChild(form);
            
            // Submeter sem recarregar página
            var xhr = new XMLHttpRequest();
            xhr.open('POST', form.action, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                console.log('✓ Configuração salva com sucesso');
            };
            xhr.onerror = function() {
                console.warn('⚠️  Erro ao salvar configuração');
            };
            xhr.send('salvar_postos=' + encodeURIComponent(inputPostos.value) + '&salvar=1');
            
            form.remove();
        }

        function atualizarIndicadores() {
            // Recalcular indicadores na tela
            var totalPessoas = 0;
            for (var i = 0; i < postos.length; i++) {
                totalPessoas += (postos[i].recursos && postos[i].recursos.num_pessoas) ? postos[i].recursos.num_pessoas : 0;
            }
            var indicadorPessoas = document.querySelector('.indicador p') || {};
            console.log('👥 Total de pessoas atualizado: ' + totalPessoas);
        }

        function renderNodes() {
            console.log('🔄 Renderizando postos ativos...');
            
            nodeIds = {};
            var postToNodeId = {};
            let visibleNodes = [];
            
            if (postos.length === 0) {
                if(document.getElementById('nodeCount')) document.getElementById('nodeCount').textContent = '0';
                return;
            }

            try {
                if (editor) editor.clear();

                let nextDefaultX = 150;
                let nextDefaultY = 200;

                for (var i = 0; i < postos.length; i++) {
                    var posto = postos[i];
                    if (posto.status === 'arquivado') continue;

                    let posX = (posto.posicao && typeof posto.posicao.x !== 'undefined') ? posto.posicao.x : nextDefaultX;
                    let posY = (posto.posicao && typeof posto.posicao.y !== 'undefined') ? posto.posicao.y : nextDefaultY;
                    
                    if (!(posto.posicao && typeof posto.posicao.x !== 'undefined')) {
                        nextDefaultX += 400;
                    }

                    var nodeId = renderizarNode(i, posX, posY);
                    if (posto.id) postToNodeId[posto.id] = nodeId;
                    visibleNodes.push(i);
                }
                
                // Reestabelecer conexões SALVAS usando IDs únicos
                conexoes.forEach(conn => {
                    const sourceId = postToNodeId[conn.origem];
                    const targetId = postToNodeId[conn.destino];
                    if (sourceId && targetId) {
                        try {
                            editor.addConnection(sourceId, targetId, 'output_1', 'input_1');
                        } catch(e) {
                            console.warn('⚠️ Erro ao conectar nodes:', e.message);
                        }
                    }
                });

                console.log('✓ Renderização concluída com sucesso');
                if(document.getElementById('nodeCount')) document.getElementById('nodeCount').textContent = visibleNodes.length;
            } catch (e) { console.error('❌ Erro na renderização:', e.message); }
        }

        function renderizarNode(idx, posX, posY) {
            var posto = postos[idx];
            if (!posto || !posto.nome) {
                console.warn('⚠️  Posto inválido:', idx, posto);
                return;
            }
            var tempoCiclo = tempoCicloPorPosto[idx] || 0;
            var tempoContenitor = tempoContenitorPorPosto[idx] || 0;
            var numPessoas = (posto.recursos && posto.recursos.num_pessoas) ? posto.recursos.num_pessoas : 0;
            var ritmo = (tempoContenitor > 0 && numPessoas > 0) ? (tempoContenitor / numPessoas).toFixed(2) : '—';
            var tempoUnidadeBasica = tempoUnidadeBasicaPorPosto[idx] || 0;

            var html = '<div style="text-align: center; font-size: 12px; padding: 4px;">' +
                       '<strong style="font-size: 13px;">' + escapeHtml(posto.nome) + '</strong><br>' +
                       '<div style="margin-top: 8px; padding: 8px; background-color: #f0f0f0; border-radius: 4px;">' +
                       '<div><strong>⏱️ Tempo de Ciclo:</strong> ' + tempoCiclo.toFixed(2) + 's</div>' +
                       '<div style="margin-top: 4px;"><strong>📦 Tempo/Contentor:</strong> ' + (tempoContenitor > 0 ? tempoContenitor.toFixed(2) + 's' : '—') + '</div>';

            if (unidadeBasicaLinha && tempoUnidadeBasica > 0) {
                html += '<div style="margin-top: 4px; padding: 4px; background-color: #fff3cd; border-radius: 3px;">' +
                       '<strong>📦 Tempo/' + escapeHtml(unidadeBasicaLinha) + ':</strong> ' + tempoUnidadeBasica.toFixed(2) + 's</div>';
            }

            html += '<div style="margin-top: 6px;">' +
                   '<label>👥 Pessoas:</label><br>' +
                   '<input type="number" id="pessoas_' + idx + '" value="' + numPessoas + '" min="0" max="100" style="width: 60px; padding: 4px;" onchange="updateNumPessoas(' + idx + ', this.value)">' +
                   '</div>' +
                   '<div style="margin-top: 6px; padding-top: 6px; border-top: 1px solid #ddd;"><strong>⚡ Ritmo:</strong> ' + ritmo + ' s/cont·pessoa</div>' +
                   
                   // ATALHOS DE AÇÃO
                   '<div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #ddd; display: flex; gap: 4px; justify-content: center; flex-wrap: wrap;">' +
                   '<button type="button" class="btn-grupo-acao btn-inserir-pos" onclick="addPostApos(' + idx + ')">➕ Após</button>' +
                   '<button type="button" class="btn-grupo-acao btn-inserir-par" onclick="addPostParalelo(' + idx + ')">⇄ Paralelo</button>' +
                   '<a href="atividades_posto.php?post=' + idx + '&linha=' + encodeURIComponent(linhaId) + '&back=index" target="_blank" class="btn-grupo-acao" style="text-decoration: none; background-color: #17a2b8; padding: 6px 10px; border-radius: 3px; font-size: 12px;">⚙️ Atividades</a>' +
                   '</div>' +
                   
                   '</div>' +
                   '</div>';

            var nodeId = editor.addNode(
                'posto',
                2, 2,
                posX,
                posY,
                'posto',
                { index: idx, nome: posto.nome, id: posto.id },
                html
            );

            nodeIds[idx] = nodeId;
            return nodeId;
        }

        // ========== EVENT HANDLERS ==========
        function setupEventHandlers() {
            editor.on('nodeSelected', function(nodeId) {
                selectedNodeId = nodeId;
                showNodeProperties(nodeId);
            });

            editor.on('nodeUnselected', function() {
                selectedNodeId = null;
                document.getElementById('properties-content').innerHTML = 
                    '<p>Selecione um posto no fluxo para ver suas propriedades.</p>';
            });

            // Salvar Zoom e Posição da Câmera ao interagir
            editor.on('zoom', function(zoom) {
                localStorage.setItem('df_zoom_' + linhaId, zoom);
            });

            editor.on('translate', function(pos) {
                localStorage.setItem('df_x_' + linhaId, pos.x);
                localStorage.setItem('df_y_' + linhaId, pos.y);
            });

            // Salvar posição individual do balão ao mover
            editor.on('nodeMoved', function(nodeId) {
                var node = editor.getNodeFromId(nodeId);
                var idx = node.data.index;
                if (postos[idx]) {
                    postos[idx].posicao = { x: node.pos_x, y: node.pos_y };
                    salvarConfiguracao(); // Persiste no linhas.json
                }
            });

            // Salvar conexões ao criar/remover na interface
            editor.on('connectionCreated', function(info) {
                persistirConexoes();
            });
            editor.on('connectionRemoved', function(info) {
                persistirConexoes();
            });
        }

        function persistirConexoes() {
            const exportData = editor.export();
            const dfNodes = exportData.drawflow.Home.data;
            let novasConexoes = [];
            
            Object.keys(dfNodes).forEach(nodeId => {
                const node = dfNodes[nodeId];
                const sourcePostId = node.data.id;
                
                if (node.outputs && node.outputs.output_1) {
                    node.outputs.output_1.connections.forEach(conn => {
                        const targetNodeId = conn.node;
                        const targetPostId = dfNodes[targetNodeId].data.id;
                        
                        if (sourcePostId && targetPostId) {
                            novasConexoes.push({
                                id: 'conexao_' + Date.now() + '_' + Math.floor(Math.random() * 1000),
                                origem: sourcePostId,
                                destino: targetPostId,
                                tipo: 'serie'
                            });
                        }
                    });
                }
            });

            var formData = new FormData();
            formData.append('salvar_conexoes', JSON.stringify(novasConexoes));
            formData.append('linha_id', linhaId);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(r => console.log('✓ Conexões persistidas no servidor'))
            .catch(e => console.error('Erro ao persistir conexões:', e));
        }

        function showNodeProperties(nodeId) {
            try {
                var idx = null;
                // Procurar índice do posto que corresponde a este nodeId
                for (var i = 0; i < postos.length; i++) {
                    if (nodeIds[i] === nodeId) {
                        idx = i;
                        break;
                    }
                }

                if (idx === null || !postos[idx]) {
                    return;
                }

                var posto = postos[idx];
                var detalhes = posto.detalhes || {};
                var recursos = posto.recursos || {};
                var num_pessoas = recursos.num_pessoas || '—';

                var html = '<div class="property">' +
                          '<label>Nome do Posto:</label>' +
                          '<input type="text" value="' + escapeHtml(posto.nome) + '" readonly>' +
                          '</div>' +
                          '<div class="property">' +
                          '<label>👥 Número de Pessoas:</label>' +
                          '<input type="text" value="' + num_pessoas + '" readonly>' +
                          '</div>';

                if (detalhes.unidade_tipica) {
                    html += '<div class="property">' +
                           '<label>Unidade Típica:</label>' +
                           '<input type="text" value="' + escapeHtml(detalhes.unidade_tipica) + '" readonly>' +
                           '</div>';
                }

                if (detalhes.tempo_por_item) {
                    html += '<div class="property">' +
                           '<label>Tempo por Item:</label>' +
                           '<input type="text" value="' + detalhes.tempo_por_item + ' min" readonly>' +
                           '</div>';
                }

                if (detalhes.fator_correlacao) {
                    html += '<div class="property">' +
                           '<label>Fator de Correlação:</label>' +
                           '<input type="text" value="' + detalhes.fator_correlacao + '" readonly>' +
                           '</div>';
                }

                // Informações de paralelismo
                if (posto.paralelo_de !== undefined) {
                    var nomePaiParalelo = postos[posto.paralelo_de] ? postos[posto.paralelo_de].nome : 'Desconhecido';
                    html += '<div class="info-conexoes" style="background-color: #fff3cd; border-left-color: #ffc107;">' +
                           '⇄ <strong>Paralelo com:</strong><br>' + escapeHtml(nomePaiParalelo) +
                           '</div>';
                }

                if (posto.paralelo_com && posto.paralelo_com.length > 0) {
                    html += '<div class="info-conexoes" style="background-color: #d4edda; border-left-color: #28a745;">' +
                           '⇄ <strong>Postos em paralelo:</strong><br>';
                    for (var k = 0; k < posto.paralelo_com.length; k++) {
                        var idxParalelo = posto.paralelo_com[k];
                        if (postos[idxParalelo]) {
                            html += '• ' + escapeHtml(postos[idxParalelo].nome) + '<br>';
                        }
                    }
                    html += '</div>';
                }

                html += '<button onclick="editPost(' + idx + ')">⚙️ Atividades</button>' +
                       '<button onclick="editRecursos(' + idx + ')" style="background-color: #17a2b8; margin-top: 5px;">👥 Recursos</button>' +
                       '<button onclick="removePost(' + idx + ')" style="background-color: #dc3545; margin-top: 5px;">🗑️ Remover</button>';

                document.getElementById('properties-content').innerHTML = html;

            } catch (e) {
                console.error('Erro ao exibir propriedades:', e);
            }
        }

        // ========== AÇÕES DO USUÁRIO ==========
        function addPost() {
            var nome = prompt('Digite o nome do novo posto:');
            if (nome && nome.trim()) {
                // Criar form dinamicamente
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '?linha=<?php echo htmlspecialchars($linha_ativa); ?>';
                form.style.display = 'none';

                var inputNome = document.createElement('input');
                inputNome.type = 'hidden';
                inputNome.name = 'novo_posto';
                inputNome.value = nome.trim();

                var inputSubmit = document.createElement('input');
                inputSubmit.type = 'hidden';
                inputSubmit.name = 'adicionar_posto';
                inputSubmit.value = '1';

                form.appendChild(inputNome);
                form.appendChild(inputSubmit);
                document.body.appendChild(form);
                
                console.log('📝 Adicionando novo posto:', nome.trim());
                form.submit();
            }
        }

        // Inserir posto após um posto existente
        function addPostApos(postoIndex) {
            var nomePostoAtual = postos[postoIndex] ? postos[postoIndex].nome : 'Desconhecido';
            var nome = prompt('Inserir novo posto SEQUENCIAL após "' + nomePostoAtual + '":\nDigite o nome do novo posto:');
            if (nome && nome.trim()) {
                // Criar form dinamicamente
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '?linha=<?php echo htmlspecialchars($linha_ativa); ?>';
                form.style.display = 'none';

                var inputNome = document.createElement('input');
                inputNome.type = 'hidden';
                inputNome.name = 'novo_posto';
                inputNome.value = nome.trim();

                var inputPosicao = document.createElement('input');
                inputPosicao.type = 'hidden';
                inputPosicao.name = 'posicao_apos';
                inputPosicao.value = postoIndex;

                var inputSubmit = document.createElement('input');
                inputSubmit.type = 'hidden';
                inputSubmit.name = 'adicionar_posto';
                inputSubmit.value = '1';

                form.appendChild(inputNome);
                form.appendChild(inputPosicao);
                form.appendChild(inputSubmit);
                document.body.appendChild(form);
                
                console.log('📝 Adicionando novo posto sequencial após:', nomePostoAtual);
                form.submit();
            }
        }

        // Inserir post em paralelo (cria novo ramo que sai do mesmo pai)
        function addPostParalelo(postoIndex) {
            var nomePostoAtual = postos[postoIndex] ? postos[postoIndex].nome : 'Desconhecido';
            var nome = prompt('Inserir novo post em PARALELO com "' + nomePostoAtual + '":\nDigite o nome do novo post:');
            if (nome && nome.trim()) {
                // Criar form dinamicamente
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '?linha=<?php echo htmlspecialchars($linha_ativa); ?>';
                form.style.display = 'none';

                var inputNome = document.createElement('input');
                inputNome.type = 'hidden';
                inputNome.name = 'novo_posto';
                inputNome.value = nome.trim();

                var inputParalelo = document.createElement('input');
                inputParalelo.type = 'hidden';
                inputParalelo.name = 'paralelo_com';
                inputParalelo.value = postoIndex;

                var inputSubmit = document.createElement('input');
                inputSubmit.type = 'hidden';
                inputSubmit.name = 'adicionar_posto';
                inputSubmit.value = '1';

                form.appendChild(inputNome);
                form.appendChild(inputParalelo);
                form.appendChild(inputSubmit);
                document.body.appendChild(form);
                
                console.log('📝 Adicionando novo post em paralelo com:', nomePostoAtual);
                form.submit();
            }
        }

        function removePost(index) {
            var confirmacao = confirm('Deseja remover este posto? Esta ação não pode ser desfeita.');
            if (confirmacao) {
                console.log('🗑️  Removendo posto no índice:', index);
                window.location.href = '?remover_posto=' + encodeURIComponent(index) + '&linha=<?php echo htmlspecialchars($linha_id); ?>';
            }
        }

        function editPost(index) {
            console.log('✏️  Editando atividades do posto no índice:', index);
            window.location.href = 'atividades_posto.php?post=' + encodeURIComponent(index) + '&linha=<?php echo htmlspecialchars($linha_id); ?>&back=index';
        }

        function editRecursos(index) {
            console.log('👥 Editando recursos do posto no índice:', index);
            window.location.href = 'recursos.php?linha=<?php echo htmlspecialchars($linha_id); ?>&post=' + encodeURIComponent(index) + '&back=index';
        }

        function clearFluxo() {
            if (confirm('⚠️  Limpar todo o fluxo? Todos os postos serão removidos.')) {
                console.log('🧹 Limpando fluxo...');
                if (editor) {
                    editor.clear();
                }
                nodeIds = {};
                selectedNodeId = null;
                document.getElementById('nodeCount').textContent = '0';
                document.getElementById('properties-content').innerHTML = 
                    '<p>Selecione um posto no fluxo para ver suas propriedades.</p>';
            }
        }

        // ========== ATUALIZAR NÚMERO DE PESSOAS ==========
        function updateNumPessoas(postIndex, numPessoas) {
            console.log('🔄 Atualizando número de pessoas para posto', postIndex, ':', numPessoas);
            
            numPessoas = parseInt(numPessoas) || 0;
            
            // Atualizar objeto local
            if (postos[postIndex]) {
                if (!postos[postIndex].recursos) {
                    postos[postIndex].recursos = {};
                }
                postos[postIndex].recursos.num_pessoas = numPessoas;
            }

            // Enviar para servidor para salvar
            var formData = new FormData();
            formData.append('atualizar_pessoas', '1');
            formData.append('post_index', postIndex);
            formData.append('num_pessoas', numPessoas);
            formData.append('linha_id', '<?php echo htmlspecialchars($linha_id); ?>');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                if (response.ok) {
                    console.log('✓ Número de pessoas salvo com sucesso');
                    // Re-renderizar apenas para atualizar ritmo
                    renderNodes();
                } else {
                    console.error('❌ Erro ao salvar número de pessoas');
                }
            })
            .catch(function(error) {
                console.error('❌ Erro de conexão:', error);
            });
        }

        // ========== UTILITÁRIOS ==========
        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // INICIALIZAÇÃO E RESTAURAÇÃO DE ESTADO
        // ========== INICIALIZAÇÃO AUTOMÁTICA ==========
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDrawflow);
        } else {
            initDrawflow();
        }
        
        // Restaurar estado do painel
        if (localStorage.getItem('panel_collapsed') === 'true') togglePropertiesPanel();
    </script>
</body>
</html>
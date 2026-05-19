<?php
// Configuração da Linha - Tabela Simples com Suporte a Atividades em Paralelo
session_start();
include 'data_store.php';

// Carregar dados
$linhas_json = load_json_data('linhas');

// Parâmetros
$linha_id = isset($_GET['linha']) ? $_GET['linha'] : 'linha1';
$acao = isset($_POST['acao']) ? $_POST['acao'] : '';

// ========== SALVAR NÚMERO DE PESSOAS NOS POSTOS ==========
if (isset($_POST['atualizar_pessoas']) && $_POST['atualizar_pessoas'] == '1') {
    foreach ($linhas_json as &$linha) {
        if ($linha['id'] === $linha_id) {
            foreach ($linha['postos'] as $post_idx => &$posto) {
                if (!isset($posto['recursos'])) {
                    $posto['recursos'] = [];
                }
                
                $campo_pessoas = 'pessoas_' . $post_idx;
                if (isset($_POST[$campo_pessoas])) {
                    $num_pessoas = intval($_POST[$campo_pessoas]);
                    $posto['recursos']['num_pessoas'] = max(0, $num_pessoas);
                }
            }
            unset($posto);
            break;
        }
    }
    unset($linha);
    
    // Salvar dados atualizados
    save_json_data('linhas', $linhas_json);
    
    // Recarregar página para refletir mudanças
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Encontrar linha selecionada
$linha_selecionada = null;
$idx_linha_selecionada = null;
foreach ($linhas_json as $idx => $linha) {
    if ($linha['id'] === $linha_id) {
        $linha_selecionada = $linha;
        $idx_linha_selecionada = $idx;
        break;
    }
}

// Se não encontrou, usar primeira linha
if ($linha_selecionada === null && !empty($linhas_json)) {
    $linha_selecionada = $linhas_json[0];
    $linha_id = $linhas_json[0]['id'];
    $idx_linha_selecionada = 0;
}

// Validar dados
if ($linha_selecionada === null) {
    die('Nenhuma linha cadastrada');
}

// PROCESSAR REQUISIÇÃO: Atualizar grupos de paralelo
if ($acao === 'atualizar_paralelo' && isset($_POST['posto_idx'])) {
    $post_idx = intval($_POST['posto_idx']);
    $grupos_paralelo = isset($_POST['grupos_paralelo']) ? json_decode($_POST['grupos_paralelo'], true) : [];
    
    if (isset($linhas_json[$idx_linha_selecionada]['postos'][$post_idx])) {
        // Inicializar campos se não existirem
        if (!isset($linhas_json[$idx_linha_selecionada]['postos'][$post_idx]['paralelo'])) {
            $linhas_json[$idx_linha_selecionada]['postos'][$post_idx]['paralelo'] = [];
        }
        
        // Atualizar grupos de paralelo
        $linhas_json[$idx_linha_selecionada]['postos'][$post_idx]['paralelo'] = $grupos_paralelo;
        
        // Salvar dados
        save_json_data('linhas', $linhas_json);
        
        // Recarregar para refletir mudanças
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Função para calcular tempo de ciclo considerando paralelismo
function calcular_tempo_ciclo_com_paralelo($atividades, $grupos_paralelo = []) {
    if (empty($atividades)) {
        return 0;
    }
    
    // Se não há definição de paralelo, somar todos os tempos
    if (empty($grupos_paralelo)) {
        $tempo_total = 0;
        foreach ($atividades as $atividade) {
            $tempo_total += $atividade['tempo_total'] ?? 0;
        }
        return $tempo_total;
    }
    
    // Mapear atividades por grupo
    $grupos = [];
    foreach ($grupos_paralelo as $grupo_id => $atividade_indices) {
        if (is_array($atividade_indices)) {
            $grupos[$grupo_id] = $atividade_indices;
        }
    }
    
    // Calcular tempo considerando paralelo
    $tempo_sequencial = 0;
    
    foreach ($grupos as $grupo) {
        // Tempo paralelo = máximo dentro do grupo
        $tempo_grupo = 0;
        foreach ($grupo as $idx) {
            if (isset($atividades[$idx])) {
                $tempo_grupo = max($tempo_grupo, $atividades[$idx]['tempo_total'] ?? 0);
            }
        }
        $tempo_sequencial += $tempo_grupo;
    }
    
    // Atividades não agrupadas (executadas sequencialmente)
    $atividades_agrupadas = [];
    foreach ($grupos as $grupo) {
        foreach ($grupo as $idx) {
            $atividades_agrupadas[] = $idx;
        }
    }
    
    foreach ($atividades as $idx => $atividade) {
        if (!in_array($idx, $atividades_agrupadas)) {
            $tempo_sequencial += $atividade['tempo_total'] ?? 0;
        }
    }
    
    return $tempo_sequencial;
}

// Calcular totais da linha
$total_postos = count($linha_selecionada['postos'] ?? []);
$total_pessoas = 0;
$total_tempo_ciclo = 0;
$total_kg = 0;
$total_atividades = 0;
$detalhes_postos = [];

foreach (($linha_selecionada['postos'] ?? []) as $idx => $posto) {
    $num_pessoas = $posto['recursos']['num_pessoas'] ?? 0;
    $total_pessoas += $num_pessoas;
    
    $grupos_paralelo = $posto['paralelo'] ?? [];
    $tempo_ciclo_posto = calcular_tempo_ciclo_com_paralelo($posto['atividades'] ?? [], $grupos_paralelo);
    
    $kg_posto = 0;
    $atividades_posto = 0;
    
    if (!empty($posto['atividades'])) {
        foreach ($posto['atividades'] as $atividade) {
            $kg_posto += $atividade['quantidade'] * $atividade['peso_unidade'];
            $atividades_posto++;
        }
    }
    
    $total_tempo_ciclo = max($total_tempo_ciclo, $tempo_ciclo_posto);
    $total_kg += $kg_posto;
    $total_atividades += $atividades_posto;
    
    $detalhes_postos[] = [
        'index' => $idx,
        'nome' => $posto['nome'],
        'num_pessoas' => $num_pessoas,
        'tempo_ciclo' => $tempo_ciclo_posto,
        'total_kg' => $kg_posto,
        'total_atividades' => $atividades_posto,
        'grupos_paralelo' => $grupos_paralelo
    ];
}

$taxa_producao = ($total_tempo_ciclo > 0) ? round(($total_kg / $total_tempo_ciclo) * 60, 2) : 0;
$produtividade = ($total_pessoas > 0) ? round($total_kg / $total_pessoas, 2) : 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração da Linha</title>
    <?php include 'menu.php'; ?>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }

        .content {
            margin-left: 290px;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            background-color: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        h1 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 24px;
        }

        h2 {
            margin: 30px 0 15px 0;
            color: #555;
            font-size: 16px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background-color: #007bff;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ccc;
        }

        td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tbody tr:hover {
            background-color: #f0f0f0;
        }

        .empty-message {
            background-color: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            color: #856404;
            text-align: center;
        }

        .link {
            color: #007bff;
            text-decoration: none;
            font-size: 12px;
        }

        .link:hover {
            text-decoration: underline;
        }

        .back-button {
            display: inline-block;
            padding: 10px 15px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
            margin-top: 20px;
        }

        .back-button:hover {
            background-color: #5a6268;
        }

        .back-button.primary {
            background-color: #007bff;
        }

        .back-button.primary:hover {
            background-color: #0056b3;
        }

        .info-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            background-color: #f0f8ff;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        .modal-title {
            font-size: 18px;
            font-weight: bold;
        }

        .close-btn {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }

        .close-btn:hover {
            color: #000;
        }

        .paralelo-group {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
        }

        .paralelo-group-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #007bff;
        }

        .atividade-checkbox {
            display: block;
            margin-bottom: 8px;
            padding: 8px;
            background-color: white;
            border-radius: 4px;
        }

        .atividade-checkbox input {
            margin-right: 10px;
        }

        .atividade-checkbox label {
            cursor: pointer;
        }

        .btn-add-grupo {
            background-color: #28a745;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-add-grupo:hover {
            background-color: #218838;
        }

        .btn-salvar-paralelo {
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 20px;
            width: 100%;
        }

        .btn-salvar-paralelo:hover {
            background-color: #0056b3;
        }

        .btn-config-paralelo {
            background-color: #ffc107;
            color: black;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-config-paralelo:hover {
            background-color: #e0a800;
        }

        .paralelo-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            padding: 8px;
            background-color: #e8f4f8;
            border-left: 3px solid #007bff;
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="container">
            <h1>Configuração da Linha: <?php echo htmlspecialchars($linha_selecionada['nome']); ?></h1>

            <!-- Modal para Configurar Paralelismo -->
            <div id="modalParalelo" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="modal-title">Configurar Atividades em Paralelo</div>
                        <button class="close-btn" onclick="fecharModalParalelo()">&times;</button>
                    </div>
                    <div id="modalCorpo">
                        <!-- Preenchido via JavaScript -->
                    </div>
                    <form id="formParalelo" method="POST">
                        <input type="hidden" name="acao" value="atualizar_paralelo">
                        <input type="hidden" name="posto_idx" id="postoIdxInput">
                        <input type="hidden" name="grupos_paralelo" id="gruposParaleloInput">
                        <button type="submit" class="btn-salvar-paralelo">Salvar Configuração</button>
                    </form>
                </div>
            </div>

            <!-- Resumo Principal -->
            <div class="info-row">
                <div class="info-item">
                    <div class="info-label">Total de Postos</div>
                    <div class="info-value"><?php echo $total_postos; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Pessoas Alocadas</div>
                    <div class="info-value"><?php echo $total_pessoas; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Tempo de Ciclo (s)</div>
                    <div class="info-value"><?php echo number_format($total_tempo_ciclo, 2, ',', '.'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Total (kg)</div>
                    <div class="info-value"><?php echo number_format($total_kg, 2, ',', '.'); ?></div>
                </div>
            </div>

            <!-- Tabela de Postos -->
            <h2>Detalhes dos Postos</h2>
            <?php if ($total_postos === 0): ?>
                <div class="empty-message">
                    Nenhum posto cadastrado nesta linha
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th width="20%">Nome do Posto</th>
                            <th width="12%">Pessoas</th>
                            <th width="12%">Tempo (s)</th>
                            <th width="12%">Total (kg)</th>
                            <th width="10%">Atividades</th>
                            <th width="22%">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalhes_postos as $detalhe): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($detalhe['nome']); ?></strong></td>
                            <td><?php echo $detalhe['num_pessoas']; ?></td>
                            <td><?php echo number_format($detalhe['tempo_ciclo'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($detalhe['total_kg'], 2, ',', '.'); ?></td>
                            <td><?php echo $detalhe['total_atividades']; ?></td>
                            <td>
                                <button type="button" class="btn-config-paralelo" onclick="abrirModalParalelo(<?php echo $detalhe['index']; ?>)">Paralelo</button>
                                <a href="atividades_posto.php?linha=<?php echo urlencode($linha_id); ?>&post=<?php echo $detalhe['index']; ?>" class="link">Atividades</a>
                                |
                                <a href="recursos.php?linha=<?php echo urlencode($linha_id); ?>&post=<?php echo $detalhe['index']; ?>" class="link">Pessoas</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Visualização de Paralelo Configurado -->
            <h2>Configuração de Atividades em Paralelo</h2>
            <?php if ($total_postos === 0): ?>
                <div class="empty-message">
                    Nenhum posto cadastrado nesta linha
                </div>
            <?php else: ?>
                <?php 
                $tem_paralelo = false;
                foreach ($detalhes_postos as $detalhe) {
                    if (!empty($detalhe['grupos_paralelo'])) {
                        $tem_paralelo = true;
                        break;
                    }
                }
                ?>
                
                <?php if (!$tem_paralelo): ?>
                    <div class="empty-message">
                        Nenhuma configuração de paralelismo definida. Todas as atividades executam sequencialmente.
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th width="25%">Posto</th>
                                <th width="75%">Configuração de Paralelo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalhes_postos as $detalhe): ?>
                                <?php if (!empty($detalhe['grupos_paralelo']) && isset($linha_selecionada['postos'][$detalhe['index']])): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($detalhe['nome']); ?></strong></td>
                                        <td>
                                            <?php 
                                            $post = $linha_selecionada['postos'][$detalhe['index']];
                                            $atividades = $post['atividades'] ?? [];
                                            $grupos = $detalhe['grupos_paralelo'];
                                            
                                            foreach ($grupos as $group_idx => $ativ_indices): ?>
                                                <div style="margin-bottom: 8px; padding: 8px; background-color: #e8f4f8; border-radius: 4px; border-left: 3px solid #007bff;">
                                                    <strong>Grupo <?php echo $group_idx + 1; ?> (Paralelo):</strong>
                                                    <?php 
                                                    $tempo_grupo = 0;
                                                    $descricoes = [];
                                                    foreach ($ativ_indices as $idx) {
                                                        if (isset($atividades[$idx])) {
                                                            $ativ = $atividades[$idx];
                                                            $descricoes[] = htmlspecialchars($ativ['descricao'] ?? 'Atividade') . ' (' . $ativ['tempo_total'] . 's)';
                                                            $tempo_grupo = max($tempo_grupo, $ativ['tempo_total']);
                                                        }
                                                    }
                                                    echo implode(', ', $descricoes);
                                                    ?>
                                                    <br>
                                                    <span style="font-size: 12px; color: #666;">⏱ Tempo: <?php echo number_format($tempo_grupo, 2, ',', '.'); ?>s (máximo do grupo)</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- TABELA DE POSTOS COM TEMPO DE CICLO E RITMO -->
            <h2>📊 Configuração de Postos</h2>
            <form method="POST" action="?linha=<?php echo urlencode($linha_id); ?>">
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <thead>
                        <tr style="background-color: #f0f0f0; border-bottom: 2px solid #333;">
                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Descrição do Posto</th>
                            <th style="padding: 10px; text-align: center; border: 1px solid #ddd;">Tempo de Ciclo (s)</th>
                            <th style="padding: 10px; text-align: center; border: 1px solid #ddd;">Pessoas</th>
                            <th style="padding: 10px; text-align: center; border: 1px solid #ddd;">Ritmo (s/pessoa)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linha_selecionada['postos'] as $post_idx => $posto): ?>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 10px; border: 1px solid #ddd;">
                                    <strong><?php echo htmlspecialchars($posto['nome'] ?? 'Posto sem nome'); ?></strong>
                                </td>
                                <td style="padding: 10px; text-align: center; border: 1px solid #ddd;">
                                    <?php 
                                        $tempo_ciclo = calcular_tempo_ciclo_com_paralelo(
                                            $posto['atividades'] ?? [], 
                                            $posto['paralelo'] ?? []
                                        );
                                        echo number_format($tempo_ciclo, 2, ',', '.');
                                    ?>
                                </td>
                                <td style="padding: 10px; text-align: center; border: 1px solid #ddd;">
                                    <input type="number" 
                                           name="pessoas_<?php echo $post_idx; ?>" 
                                           value="<?php echo $posto['recursos']['num_pessoas'] ?? 0; ?>" 
                                           min="0" 
                                           max="100"
                                           style="width: 60px; padding: 5px; text-align: center;"
                                           onchange="document.querySelector('input[name=\'atualizar_pessoas\']').value = '1';">
                                </td>
                                <td style="padding: 10px; text-align: center; border: 1px solid #ddd;">
                                    <?php 
                                        $num_pessoas = $posto['recursos']['num_pessoas'] ?? 0;
                                        if ($num_pessoas > 0 && $tempo_ciclo > 0) {
                                            $ritmo = $tempo_ciclo / $num_pessoas;
                                            echo number_format($ritmo, 2, ',', '.');
                                        } else {
                                            echo '—';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <input type="hidden" name="atualizar_pessoas" value="0">
                <button type="submit" style="padding: 8px 16px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    💾 Salvar Alterações
                </button>
            </form>

            <h2>Resumo da Linha</h2>
            <table>
                <tbody>
                    <tr>
                        <td><strong>Unidade Básica Configurada:</strong></td>
                        <td>
                            <?php 
                            if (!empty($linha_selecionada['unidade_basica'])) {
                                echo htmlspecialchars($linha_selecionada['unidade_basica']);
                            } else {
                                echo 'Não definida';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Total de Atividades:</strong></td>
                        <td><?php echo $total_atividades; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Taxa de Produção:</strong></td>
                        <td><?php echo number_format($taxa_producao, 2, ',', '.'); ?> kg/min</td>
                    </tr>
                    <tr>
                        <td><strong>Produtividade por Pessoa:</strong></td>
                        <td><?php echo number_format($produtividade, 2, ',', '.'); ?> kg/pessoa</td>
                    </tr>
                </tbody>
            </table>

            <!-- Botões de Navegação -->
            <div>
                <a href="index.php?linha=<?php echo urlencode($linha_id); ?>" class="back-button primary">Fluxo da Linha</a>
                <a href="postos.php" class="back-button">Gerenciar Postos</a>
                <a href="menu.php" class="back-button">Menu Principal</a>
            </div>
        </div>
    </div>

    <script>
        // Dados dos postos com atividades
        const dadosPostos = <?php echo json_encode($linha_selecionada['postos'] ?? []); ?>;

        // Abrir modal para configurar paralelo
        function abrirModalParalelo(postoIdx) {
            const posto = dadosPostos[postoIdx];
            if (!posto) return;

            const modal = document.getElementById('modalParalelo');
            const modalCorpo = document.getElementById('modalCorpo');
            
            // Limpar corpo anterior
            modalCorpo.innerHTML = '';

            // Verificar se há atividades
            const atividades = posto.atividades || [];
            if (atividades.length === 0) {
                modalCorpo.innerHTML = '<p style="color: #999;">Nenhuma atividade cadastrada para este posto.</p>';
                modal.classList.add('show');
                document.getElementById('postoIdxInput').value = postoIdx;
                return;
            }

            // Construir interface de grupos
            const gruposParalelo = posto.paralelo || {};
            let html = '<div class="paralelo-info">Defina quais atividades devem ser executadas em paralelo. Atividades no mesmo grupo rodarão simultaneamente.</div>';

            // Determinar máximo de grupos necessários
            let maxGrupos = Object.keys(gruposParalelo).length || 1;

            // Criar interface de grupos
            for (let grupo = 0; grupo < Math.max(maxGrupos, 1); grupo++) {
                const atividades_grupo = gruposParalelo[grupo] || [];
                html += `<div class="paralelo-group">
                    <div class="paralelo-group-title">Grupo ${grupo + 1} (Executar em Paralelo)</div>`;
                
                atividades.forEach((ativ, idx) => {
                    const checked = atividades_grupo.includes(idx) ? 'checked' : '';
                    html += `
                        <label class="atividade-checkbox">
                            <input type="checkbox" name="ativ_grupo_${grupo}" value="${idx}" ${checked}>
                            <span>${ativ.descricao || 'Atividade ' + (idx + 1)} (${ativ.tempo_total}s)</span>
                        </label>
                    `;
                });
                
                html += '</div>';
            }

            html += '<button type="button" class="btn-add-grupo" onclick="adicionarGrupoParalelo()">+ Adicionar Novo Grupo</button>';

            modalCorpo.innerHTML = html;
            
            // Preparar índice do posto
            document.getElementById('postoIdxInput').value = postoIdx;
            
            // Mostrar modal
            modal.classList.add('show');
        }

        // Fechar modal
        function fecharModalParalelo() {
            const modal = document.getElementById('modalParalelo');
            modal.classList.remove('show');
        }

        // Adicionar novo grupo de paralelo
        function adicionarGrupoParalelo() {
            const modalCorpo = document.getElementById('modalCorpo');
            const gruposExistentes = modalCorpo.querySelectorAll('.paralelo-group');
            const novoGrupo = gruposExistentes.length;

            // Obter atividades
            const postoIdx = document.getElementById('postoIdxInput').value;
            const posto = dadosPostos[postoIdx];
            const atividades = posto.atividades || [];

            // Criar novo grupo
            let html = `<div class="paralelo-group">
                <div class="paralelo-group-title">Grupo ${novoGrupo + 1} (Executar em Paralelo)</div>`;
            
            atividades.forEach((ativ, idx) => {
                html += `
                    <label class="atividade-checkbox">
                        <input type="checkbox" name="ativ_grupo_${novoGrupo}" value="${idx}">
                        <span>${ativ.descricao || 'Atividade ' + (idx + 1)} (${ativ.tempo_total}s)</span>
                    </label>
                `;
            });
            
            html += '</div>';

            // Inserir novo grupo antes do botão
            const botao = modalCorpo.querySelector('.btn-add-grupo');
            const novoGrupoEl = document.createElement('div');
            novoGrupoEl.innerHTML = html;
            botao.parentNode.insertBefore(novoGrupoEl.firstChild, botao);
        }

        // Ao enviar o formulário, preparar dados dos grupos
        document.getElementById('formParalelo').addEventListener('submit', function(e) {
            e.preventDefault();

            const modalCorpo = document.getElementById('modalCorpo');
            const gruposParalelo = {};
            
            // Coletar seleções de cada grupo
            const grupos = modalCorpo.querySelectorAll('.paralelo-group');
            grupos.forEach((grupo, groupIdx) => {
                const checkboxes = grupo.querySelectorAll('input[type="checkbox"]:checked');
                if (checkboxes.length > 0) {
                    gruposParalelo[groupIdx] = Array.from(checkboxes).map(cb => parseInt(cb.value));
                }
            });

            // Salvar dados em campo oculto
            document.getElementById('gruposParaleloInput').value = JSON.stringify(gruposParalelo);
            
            // Enviar formulário
            this.submit();
        });

        // Fechar modal ao clicar fora
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('modalParalelo');
            if (event.target === modal) {
                modal.classList.remove('show');
            }
        });
    </script>
</body>
</html>

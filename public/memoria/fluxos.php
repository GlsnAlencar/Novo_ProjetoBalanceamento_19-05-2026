<?php
/**
 * Gerenciamento de Fluxos e Conexões entre Postos
 * Permite criar conexões série/paralelo e definir propriedades de fluxo
 */
session_start();
include 'data_store.php';

// Carregar dados
$linhas = load_json_data('linhas');

// Parâmetros
$linha_id = (string)($_GET['linha_id'] ?? '');

// Encontrar linha
$linha = find_linha_by_id($linhas, $linha_id);
if (empty($linha_id) || $linha === null) {
    die('❌ Linha não encontrada: ' . htmlspecialchars((string)$linha_id));
}

// ========== ADICIONAR CONEXÃO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_conexao'])) {
    $origem_id = trim($_POST['origem_id'] ?? '');
    $destino_id = trim($_POST['destino_id'] ?? '');
    $tipo = trim($_POST['tipo_conexao'] ?? 'serie');
    
    if (!empty($origem_id) && !empty($destino_id) && $origem_id !== $destino_id) {
        // Verificar se postos existem
        $origem = find_posto_in_linha($linha, $origem_id);
        $destino = find_posto_in_linha($linha, $destino_id);
        
        if ($origem !== null && $destino !== null) {
            // Verificar se conexão já existe
            $existe = false;
            if (isset($linha['conexoes'])) {
                foreach ($linha['conexoes'] as $conexao) {
                    if ($conexao['origem'] === $origem_id && $conexao['destino'] === $destino_id) {
                        $existe = true;
                        break;
                    }
                }
            }
            
            if (!$existe) {
                // Adicionar conexão
                add_conexao($linha, $origem_id, $destino_id, $tipo);
                
                // Marcar postos como paralelo se necessário
                if ($tipo === 'paralelo') {
                    $origem['paralelo'] = true;
                    $destino['paralelo'] = true;
                }
                
                save_json_data('linhas', $linhas);
                header('Location: fluxos.php?linha_id=' . urlencode($linha_id) . '&sucesso=1');
                exit;
            } else {
                $erro = 'Conexão já existe entre estes postos';
            }
        } else {
            $erro = 'Um ou ambos os postos não foram encontrados';
        }
    } else {
        $erro = 'Dados inválidos ou postos iguais';
    }
}

// ========== REMOVER CONEXÃO ==========
if (isset($_GET['remover_conexao']) && isset($_GET['linha_id'])) {
    $conexao_id = $_GET['remover_conexao'];
    
    if (remove_conexao($linha, $conexao_id)) {
        save_json_data('linhas', $linhas);
        header('Location: fluxos.php?linha_id=' . urlencode($linha_id) . '&sucesso=1');
        exit;
    }
}

// ========== ATUALIZAR TIPO DE FLUXO DE POSTO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_tipo_fluxo'])) {
    $posto_id = trim($_POST['posto_id'] ?? '');
    $novo_tipo = trim($_POST['tipo_fluxo'] ?? 'node');
    
    if (!empty($posto_id)) {
        $posto = find_posto_in_linha($linha, $posto_id);
        if ($posto !== null) {
            $posto['tipo'] = $novo_tipo;
            save_json_data('linhas', $linhas);
            header('Location: fluxos.php?linha_id=' . urlencode($linha_id) . '&sucesso=1');
            exit;
        }
    }
}

// ========== MARCAR/DESMARCAR PARALELO ==========
if (isset($_GET['toggle_paralelo']) && isset($_GET['posto_id'])) {
    $posto_id = $_GET['posto_id'];
    $posto = find_posto_in_linha($linha, $posto_id);
    
    if ($posto !== null) {
        $posto['paralelo'] = !($posto['paralelo'] ?? false);
        save_json_data('linhas', $linhas);
        header('Location: fluxos.php?linha_id=' . urlencode($linha_id) . '&sucesso=1');
        exit;
    }
}

$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']) ? $_GET['erro'] : null;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Fluxos</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .fluxo-section {
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .postos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        .posto-item {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            background: white;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .posto-item-name {
            font-weight: 600;
            color: #333;
        }
        .tipo-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            width: fit-content;
        }
        .tipo-node { background: #e3f2fd; color: #1565c0; }
        .tipo-parallel { background: #f3e5f5; color: #7b1fa2; }
        .tipo-merge { background: #e8f5e9; color: #2e7d32; }
        .conexoes-list {
            margin-top: 20px;
        }
        .conexao-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .conexao-flow {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .conexao-arrow {
            color: #666;
            font-weight: bold;
        }
        .tipo-conexao-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            background: #fffbea;
            color: #ff6f00;
        }
        .tipo-paralelo-badge {
            background: #f3e5f5;
            color: #7b1fa2;
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="container">
            <?php include 'menu.php'; ?>

            <h1>🔗 Gerenciamento de Fluxos</h1>
            <h2>Linha: <?php echo htmlspecialchars($linha['nome']); ?></h2>
            
            <?php if ($sucesso): ?>
                <div class="success-message">✅ Operação realizada com sucesso!</div>
            <?php endif; ?>
            
            <?php if ($erro): ?>
                <div class="error-message">❌ Erro: <?php echo htmlspecialchars($erro); ?></div>
            <?php endif; ?>
            
            <!-- Seção de Postos -->
            <div class="fluxo-section">
                <h3>📍 Postos da Linha</h3>
                
                <?php if (empty($linha['postos'])): ?>
                    <div class="empty-message">
                        Nenhum posto nesta linha. <a href="postos.php?linha_id=<?php echo urlencode($linha_id); ?>">Adicione um primeiro</a>
                    </div>
                <?php else: ?>
                    <div class="postos-grid">
                        <?php foreach ($linha['postos'] as $posto): ?>
                            <div class="posto-item">
                                <div class="posto-item-name"><?php echo htmlspecialchars($posto['nome']); ?></div>
                                <div>
                                    <span class="tipo-badge tipo-<?php echo htmlspecialchars($posto['tipo'] ?? 'node'); ?>">
                                        <?php echo htmlspecialchars(strtoupper($posto['tipo'] ?? 'node')); ?>
                                    </span>
                                </div>
                                <div style="font-size: 11px; color: #999;">ID: <?php echo htmlspecialchars($posto['id']); ?></div>
                                
                                <?php if ($posto['paralelo'] ?? false): ?>
                                    <div style="color: #ff6f00; font-size: 11px; font-weight: 600;">🔀 PARALELO</div>
                                <?php endif; ?>
                                
                                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                    <a href="fluxos.php?toggle_paralelo=1&posto_id=<?php echo urlencode($posto['id']); ?>&linha_id=<?php echo urlencode($linha_id); ?>" class="btn-small" style="flex: 1; text-align: center;">
                                        <?php echo ($posto['paralelo'] ?? false) ? '🔀 Desmarcar Paralelo' : '⚡ Marcar Paralelo'; ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Seção de Conexões -->
            <div class="fluxo-section">
                <h3>🔗 Criar Conexão entre Postos</h3>
                
                <?php if (empty($linha['postos']) || count($linha['postos']) < 2): ?>
                    <div class="empty-message">
                        É necessário ter pelo menos 2 postos para criar conexões.
                    </div>
                <?php else: ?>
                    <form method="post" class="form-grid" style="background: white; padding: 15px; border-radius: 4px;">
                        <input type="hidden" name="linha_id" value="<?php echo htmlspecialchars($linha_id); ?>">
                        
                        <div class="form-group">
                            <label for="origem_id" class="required">Posto de Origem</label>
                            <select id="origem_id" name="origem_id" required>
                                <option value="">-- Selecione --</option>
                                <?php foreach ($linha['postos'] as $posto): ?>
                                    <option value="<?php echo htmlspecialchars($posto['id']); ?>">
                                        <?php echo htmlspecialchars($posto['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo_conexao" class="required">Tipo de Fluxo</label>
                            <select id="tipo_conexao" name="tipo_conexao" required>
                                <option value="serie">📊 Série (Sequencial)</option>
                                <option value="paralelo">🔀 Paralelo (Simultâneo)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="destino_id" class="required">Posto de Destino</label>
                            <select id="destino_id" name="destino_id" required>
                                <option value="">-- Selecione --</option>
                                <?php foreach ($linha['postos'] as $posto): ?>
                                    <option value="<?php echo htmlspecialchars($posto['id']); ?>">
                                        <?php echo htmlspecialchars($posto['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="adicionar_conexao" class="btn btn-primary">➕ Adicionar Conexão</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Seção de Conexões Existentes -->
            <div class="fluxo-section">
                <h3>📋 Conexões Configuradas (<?php echo count($linha['conexoes'] ?? []); ?>)</h3>
                
                <?php if (empty($linha['conexoes'])): ?>
                    <div class="empty-message">
                        Nenhuma conexão configurada entre postos.
                    </div>
                <?php else: ?>
                    <div class="conexoes-list">
                        <?php foreach ($linha['conexoes'] as $conexao): ?>
                            <?php
                            $origem = find_posto_in_linha($linha, $conexao['origem']);
                            $destino = find_posto_in_linha($linha, $conexao['destino']);
                            ?>
                            <div class="conexao-item">
                                <div class="conexao-flow">
                                    <strong><?php echo $origem ? htmlspecialchars($origem['nome']) : '?'; ?></strong>
                                    <span class="conexao-arrow">
                                        <?php echo $conexao['tipo'] === 'paralelo' ? '🔀' : '→'; ?>
                                    </span>
                                    <strong><?php echo $destino ? htmlspecialchars($destino['nome']) : '?'; ?></strong>
                                    <span class="tipo-conexao-badge <?php echo $conexao['tipo'] === 'paralelo' ? 'tipo-paralelo-badge' : ''; ?>">
                                        <?php echo strtoupper($conexao['tipo']); ?>
                                    </span>
                                </div>
                                <a href="fluxos.php?remover_conexao=<?php echo urlencode($conexao['id']); ?>&linha_id=<?php echo urlencode($linha_id); ?>" class="btn-small btn-small-danger" onclick="return confirm('Tem certeza que deseja remover esta conexão?')">
                                    🗑️ Remover
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <hr>
            <div style="margin-top: 20px;">
                <a href="postos.php?linha_id=<?php echo urlencode($linha_id); ?>" class="btn btn-default">⬅️ Voltar para Postos</a>
            </div>
        </div>
    </div>
</body>
</html>

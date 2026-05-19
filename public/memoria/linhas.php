<?php
/**
 * Gerenciamento de Linhas por Setor
 * Permite criar, editar, remover e organizar linhas de trabalho
 */
session_start();
include 'data_store.php';

// Carregar dados
$setores = load_json_data('setores');
$linhas = load_json_data('linhas');

// Determinar setor ativo
$setor_id_ativo = isset($_GET['setor_id']) ? $_GET['setor_id'] : (isset($_POST['setor_id']) ? $_POST['setor_id'] : null);

// Se nenhum setor ativo, redirecionar para o primeiro
if (!$setor_id_ativo && !empty($setores)) {
    header('Location: linhas.php?setor_id=' . urlencode($setores[0]['id']));
    exit;
}

// Verificar se setor existe
$setor_atual = find_setor_by_id($setores, $setor_id_ativo);
if ($setor_atual === null && !empty($setores)) {
    header('Location: linhas.php?setor_id=' . urlencode($setores[0]['id']));
    exit;
}

// ========== ADICIONAR NOVA LINHA ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_linha'])) {
    $nome = trim($_POST['nome_linha'] ?? '');
    $descricao = trim($_POST['descricao_linha'] ?? '');
    
    if (!empty($nome) && $setor_id_ativo) {
        // Criar nova linha
        $nova_linha = create_default_linha($nome, $setor_id_ativo);
        if (!empty($descricao)) {
            $nova_linha['descricao'] = $descricao;
        }
        
        // Adicionar à lista de linhas
        $linhas[] = $nova_linha;
        save_json_data('linhas', $linhas);
        
        // Atualizar setor para vincular a linha
        if ($setor_atual !== null) {
            if (!isset($setor_atual['linhas_ids'])) {
                $setor_atual['linhas_ids'] = [];
            }
            if (!in_array($nova_linha['id'], $setor_atual['linhas_ids'])) {
                $setor_atual['linhas_ids'][] = $nova_linha['id'];
                save_json_data('setores', $setores);
            }
        }
        
        header('Location: linhas.php?setor_id=' . urlencode($setor_id_ativo) . '&sucesso=1');
        exit;
    }
}

// ========== ATUALIZAR LINHA ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_linha'])) {
    $linha_id = $_POST['linha_id'] ?? '';
    $novo_nome = trim($_POST['nome_linha'] ?? '');
    
    if (!empty($novo_nome) && !empty($linha_id)) {
        $linha_ref = find_linha_by_id($linhas, $linha_id);
        if ($linha_ref !== null) {
            $linha_ref['nome'] = $novo_nome;
            $linha_ref['descricao'] = trim($_POST['descricao_linha'] ?? '');
            save_json_data('linhas', $linhas);
            
            header('Location: linhas.php?setor_id=' . urlencode($setor_id_ativo) . '&sucesso=1');
            exit;
        }
    }
}

// ========== REMOVER LINHA ==========
if (isset($_GET['remover_linha']) && isset($_GET['setor_id'])) {
    $linha_id = $_GET['remover_linha'];
    $setor_id = $_GET['setor_id'];
    
    // Remover da lista de linhas
    $linhas = array_filter($linhas, function($l) use ($linha_id) {
        return $l['id'] !== $linha_id;
    });
    $linhas = array_values($linhas);
    save_json_data('linhas', $linhas);
    
    // Remover do setor
    $setor = find_setor_by_id($setores, $setor_id);
    if ($setor !== null && isset($setor['linhas_ids'])) {
        $setor['linhas_ids'] = array_filter($setor['linhas_ids'], function($id) use ($linha_id) {
            return $id !== $linha_id;
        });
        $setor['linhas_ids'] = array_values($setor['linhas_ids']);
        save_json_data('setores', $setores);
    }
    
    header('Location: linhas.php?setor_id=' . urlencode($setor_id) . '&sucesso=1');
    exit;
}

// Obter linhas do setor ativo
$linhas_do_setor = [];
if ($setor_id_ativo) {
    $linhas_do_setor = get_linhas_by_setor($linhas, $setor_id_ativo);
}

$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']) ? $_GET['erro'] : null;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Linhas</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .linha-card {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 12px;
            background: #f9f9f9;
            transition: all 0.3s ease;
        }
        .linha-card:hover {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .linha-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .linha-card-title {
            font-weight: 600;
            font-size: 16px;
            color: #333;
        }
        .linha-card-id {
            font-size: 12px;
            color: #999;
            font-family: monospace;
        }
        .linha-actions {
            display: flex;
            gap: 8px;
        }
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            background: white;
            transition: all 0.2s;
        }
        .btn-small:hover {
            background: #f0f0f0;
        }
        .btn-small-danger {
            color: #d32f2f;
            border-color: #d32f2f;
        }
        .btn-small-danger:hover {
            background: #ffebee;
        }
        .btn-small-primary {
            color: #1976d2;
            border-color: #1976d2;
        }
        .btn-small-primary:hover {
            background: #e3f2fd;
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="container">
            <?php include 'menu.php'; ?>

            <h1>📦 Gerenciamento de Linhas</h1>
            
            <?php if (empty($setores)): ?>
                <div class="empty-message">
                    ❌ Nenhum setor cadastrado. <a href="setores.php">Crie um setor primeiro</a>
                </div>
            <?php elseif ($setor_atual === null): ?>
                <div class="empty-message">
                    ❌ Setor não encontrado.
                </div>
            <?php else: ?>
                <h2>Setor: <?php echo htmlspecialchars($setor_atual['nome']); ?></h2>
                
                <?php if ($sucesso): ?>
                    <div class="success-message">✅ Operação realizada com sucesso!</div>
                <?php endif; ?>
                
                <?php if ($erro): ?>
                    <div class="error-message">❌ Erro: <?php echo htmlspecialchars($erro); ?></div>
                <?php endif; ?>
                
                <!-- Formulário para adicionar linha -->
                <div class="form-section">
                    <h3>➕ Adicionar Nova Linha</h3>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="setor_id" value="<?php echo htmlspecialchars($setor_id_ativo); ?>">
                        <div class="form-group">
                            <label for="nome_linha" class="required">Nome da Linha</label>
                            <input type="text" id="nome_linha" name="nome_linha" placeholder="Ex: Linha 1, Linha de Embalagem" required>
                        </div>
                        <div class="form-group">
                            <label for="descricao_linha">Descrição</label>
                            <input type="text" id="descricao_linha" name="descricao_linha" placeholder="Ex: Fluxo principal de processamento">
                        </div>
                        <div class="form-group">
                            <button type="submit" name="adicionar_linha" class="btn btn-primary">➕ Adicionar Linha</button>
                        </div>
                    </form>
                </div>
                
                <!-- Lista de linhas -->
                <h3>📋 Linhas Configuradas (<?php echo count($linhas_do_setor); ?>)</h3>
                
                <?php if (empty($linhas_do_setor)): ?>
                    <div class="empty-message">
                        Nenhuma linha criada para este setor. Comece adicionando uma acima! 🚀
                    </div>
                <?php else: ?>
                    <div class="linhas-list">
                        <?php foreach ($linhas_do_setor as $idx => $linha): ?>
                            <div class="linha-card">
                                <div class="linha-card-header">
                                    <div>
                                        <div class="linha-card-title">
                                            <a href="postos.php?linha_id=<?php echo urlencode($linha['id']); ?>">
                                                📍 <?php echo htmlspecialchars($linha['nome']); ?>
                                            </a>
                                        </div>
                                        <div class="linha-card-id">ID: <?php echo htmlspecialchars($linha['id']); ?></div>
                                        <?php if (isset($linha['descricao']) && !empty($linha['descricao'])): ?>
                                            <div style="font-size: 13px; color: #666; margin-top: 4px;">
                                                <?php echo htmlspecialchars($linha['descricao']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="linha-actions">
                                        <a href="postos.php?linha_id=<?php echo urlencode($linha['id']); ?>" class="btn-small btn-small-primary">
                                            ✏️ Editar
                                        </a>
                                        <a href="linhas.php?remover_linha=<?php echo urlencode($linha['id']); ?>&setor_id=<?php echo urlencode($setor_id_ativo); ?>&confirmar=1" class="btn-small btn-small-danger" onclick="return confirm('Tem certeza que deseja remover esta linha?')">
                                            🗑️ Remover
                                        </a>
                                    </div>
                                </div>
                                <div style="font-size: 12px; color: #999; margin-top: 8px;">
                                    <strong>Postos:</strong> <?php echo count($linha['postos'] ?? []); ?> | 
                                    <strong>Conexões:</strong> <?php echo count($linha['conexoes'] ?? []); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Seletor de setor -->
                <hr>
                <h3>🔀 Mudar de Setor</h3>
                <form method="get" style="display: flex; gap: 10px; align-items: center;">
                    <select name="setor_id" onchange="this.form.submit()">
                        <?php foreach ($setores as $setor): ?>
                            <option value="<?php echo htmlspecialchars($setor['id']); ?>" <?php echo ($setor['id'] === $setor_id_ativo) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($setor['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

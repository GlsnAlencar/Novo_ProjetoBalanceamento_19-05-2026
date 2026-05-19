<?php
/**
 * Gerenciamento de Setores
 * Permite criar, editar e remover setores
 * Cada setor pode ter múltiplas linhas vinculadas
 */
session_start();
include 'data_store.php';

// Carregar dados
$setores = load_json_data('setores');
$linhas = load_json_data('linhas');

// ========== ADICIONAR NOVO SETOR ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_setor'])) {
    $nome = trim($_POST['nome_setor'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    
    if (!empty($nome)) {
        $novo_setor = create_default_setor($nome, $descricao);
        
        // Ao criar um setor, cria automaticamente a linha vinculada (1:1)
        $nova_linha = create_default_linha($nome, $novo_setor['id']);
        $linhas[] = $nova_linha;
        $novo_setor['linhas_ids'] = [$nova_linha['id']];
        
        $setores[] = $novo_setor;
        save_json_data('linhas', $linhas);
        save_json_data('setores', $setores);
        header('Location: setores.php?sucesso=1');
        exit;
    }
}

// ========== ATUALIZAR SETOR ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_setor'])) {
    $setor_id = $_POST['setor_id'] ?? '';
    $novo_nome = trim($_POST['nome_setor'] ?? '');
    $nova_descricao = trim($_POST['descricao'] ?? '');
    
    if (!empty($novo_nome) && !empty($setor_id)) {
        $setor = find_setor_by_id($setores, $setor_id);
        if ($setor !== null) {
            $setor['nome'] = $novo_nome;
            $setor['descricao'] = $nova_descricao;
            save_json_data('setores', $setores);
            header('Location: setores.php?sucesso=1');
            exit;
        }
    }
}

// ========== REMOVER SETOR ==========
if (isset($_GET['remover'])) {
    $id_remover = $_GET['remover'];
    
    // Remover setor
    $setores = array_filter($setores, function($s) use ($id_remover) {
        return $s['id'] !== $id_remover;
    });
    $setores = array_values($setores);
    save_json_data('setores', $setores);
    
    // Remover linhas associadas ao setor
    $linhas = array_filter($linhas, function($l) use ($id_remover) {
        return ($l['setor_id'] ?? null) !== $id_remover;
    });
    $linhas = array_values($linhas);
    save_json_data('linhas', $linhas);
    
    header('Location: setores.php?sucesso=1');
    exit;
}

$sucesso = isset($_GET['sucesso']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Setores</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .setor-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            transition: all 0.3s ease;
        }
        .setor-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-color: #ff6f00;
        }
        .setor-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        .setor-card-title {
            font-weight: 600;
            font-size: 18px;
            color: #333;
        }
        .setor-card-id {
            font-size: 12px;
            color: #999;
            font-family: monospace;
        }
        .setor-stats {
            display: flex;
            gap: 15px;
            margin: 10px 0;
            font-size: 13px;
            color: #666;
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .setor-description {
            color: #666;
            margin: 10px 0;
            font-size: 13px;
            line-height: 1.4;
        }
        .setor-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
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
        .btn-small-primary {
            color: #1976d2;
            border-color: #1976d2;
        }
        .btn-small-primary:hover {
            background: #e3f2fd;
        }
        .btn-small-success {
            color: #388e3c;
            border-color: #388e3c;
        }
        .btn-small-success:hover {
            background: #e8f5e9;
        }
        .btn-small-danger {
            color: #d32f2f;
            border-color: #d32f2f;
        }
        .btn-small-danger:hover {
            background: #ffebee;
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="container">
            <?php include 'menu.php'; ?>

            <h1>🏢 Cadastro de Setores <span class="badge badge-primary"><?php echo count($setores); ?></span></h1>

            <?php if ($sucesso): ?>
                <div class="success-message">
                    ✅ Operação realizada com sucesso!
                </div>
            <?php endif; ?>

            <div class="form-section">
                <h3>➕ Adicionar Novo Setor</h3>
                <p class="text-muted mb-20">Cada setor pode ter múltiplas linhas de trabalho vinculadas.</p>
                <form method="post" class="form-grid">
                    <div class="form-group">
                        <label for="nome_setor" class="required">Nome do Setor</label>
                        <input type="text" id="nome_setor" name="nome_setor" placeholder="Ex: Máquina Grande, Recebimento" required>
                    </div>
                    <div class="form-group">
                        <label for="descricao">Descrição / Objetivo</label>
                        <input type="text" id="descricao" name="descricao" placeholder="Ex: Setor de processamento de frutas">
                    </div>
                    <div class="form-group">
                        <button type="submit" name="adicionar_setor" class="btn btn-primary" style="width: 100%;">➕ Cadastrar Setor</button>
                    </div>
                </form>
            </div>

            <h2>📋 Setores Configurados</h2>
            <?php if (empty($setores)): ?>
                <div class="empty-message">
                    Nenhum setor cadastrado. Comece adicionando um novo acima! 🚀
                </div>
            <?php else: ?>
                <div class="setores-list">
                    <?php foreach ($setores as $setor): ?>
                        <?php
                        // Contar linhas do setor
                        $linhas_do_setor = get_linhas_by_setor($linhas, $setor['id']);
                        $total_postos = 0;
                        $total_conexoes = 0;
                        foreach ($linhas_do_setor as $linha) {
                            $total_postos += count($linha['postos'] ?? []);
                            $total_conexoes += count($linha['conexoes'] ?? []);
                        }
                        ?>
                        <div class="setor-card">
                            <div class="setor-card-header">
                                <div style="flex: 1;">
                                    <div class="setor-card-title">🏢 <?php echo htmlspecialchars($setor['nome']); ?></div>
                                    <div class="setor-card-id">ID: <?php echo htmlspecialchars($setor['id']); ?></div>
                                    
                                    <?php if (!empty($setor['descricao'])): ?>
                                        <div class="setor-description">
                                            <?php echo htmlspecialchars($setor['descricao']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Estatísticas -->
                            <div class="setor-stats">
                                <div class="stat-item">
                                    <strong>📦 Linhas:</strong> <?php echo count($linhas_do_setor); ?>
                                </div>
                                <div class="stat-item">
                                    <strong>📍 Postos:</strong> <?php echo $total_postos; ?>
                                </div>
                                <div class="stat-item">
                                    <strong>🔗 Conexões:</strong> <?php echo $total_conexoes; ?>
                                </div>
                                <?php if (!empty($setor['data_criacao'])): ?>
                                    <div class="stat-item">
                                        <strong>📅 Criado:</strong> <?php echo date('d/m/Y', strtotime($setor['data_criacao'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ações -->
                            <div class="setor-actions">
                                <a href="postos.php?setor_id=<?php echo urlencode($setor['id']); ?>" class="btn-small btn-small-success">
                                    📍 Gerenciar Postos
                                </a>
                                <a href="index.php?setor_id=<?php echo urlencode($setor['id']); ?>" class="btn-small btn-small-primary">
                                    📊 Visualizar Fluxo
                                </a>
                                <a href="setores.php?remover=<?php echo urlencode($setor['id']); ?>" class="btn-small btn-small-danger" onclick="return confirm('⚠️ Tem certeza? Isso removerá o setor e todas as linhas associadas.')">
                                    🗑️ Remover Setor
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <hr style="margin-top: 30px; border: none; border-top: 1px solid #dee2e6;">
            <div style="margin-top: 20px; text-align: center;">
                <a href="index.php" class="btn btn-secondary">← Voltar ao Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
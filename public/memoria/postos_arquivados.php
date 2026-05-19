<?php
// Gerenciamento de Postos Arquivados
session_start();
include 'data_store.php';

// Carregar dados das linhas
$linhas_json = load_json_data('linhas');

// ========== RESTAURAR POSTO ==========
if (isset($_GET['restaurar_posto']) && isset($_GET['linha_id']) && is_numeric($_GET['restaurar_posto'])) {
    $linha_id = $_GET['linha_id'];
    $post_index = (int)$_GET['restaurar_posto'];
    
    foreach ($linhas_json as &$linha) {
        if ($linha['id'] === $linha_id && isset($linha['postos'][$post_index])) {
            $linha['postos'][$post_index]['status'] = 'ativo';
            break;
        }
    }
    unset($linha);
    
    save_json_data('linhas', $linhas_json);
    header('Location: postos_arquivados.php?sucesso=1');
    exit;
}

// ========== DELETAR PERMANENTEMENTE ==========
if (isset($_GET['deletar_permanente']) && isset($_GET['linha_id']) && is_numeric($_GET['deletar_permanente'])) {
    $linha_id = $_GET['linha_id'];
    $post_index = (int)$_GET['deletar_permanente'];
    
    foreach ($linhas_json as &$linha) {
        if ($linha['id'] === $linha_id && isset($linha['postos'][$post_index])) {
            unset($linha['postos'][$post_index]);
            $linha['postos'] = array_values($linha['postos']);
            break;
        }
    }
    unset($linha);
    
    save_json_data('linhas', $linhas_json);
    header('Location: postos_arquivados.php?sucesso=1');
    exit;
}

$sucesso = isset($_GET['sucesso']) ? true : false;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postos Arquivados</title>
    <?php include 'menu.php'; ?>
</head>

<body>
    <div class="content">
        <div class="container">
            <h1>🗂️ Postos Arquivados</h1>

            <?php if ($sucesso): ?>
            <div class="success-message">
                ✅ Operação realizada com sucesso!
            </div>
            <?php endif; ?>

            <?php
            $total_arquivados = 0;
            foreach ($linhas_json as $linha) {
                foreach ($linha['postos'] as $posto) {
                    if (isset($posto['status']) && $posto['status'] === 'arquivado') {
                        $total_arquivados++;
                    }
                }
            }
            ?>

            <?php if ($total_arquivados === 0): ?>
                <div class="empty-message" style="margin-top: 30px;">
                    📦 Nenhum posto arquivado.<br>
                    Todos os seus postos estão ativos! ✨
                </div>
            <?php else: ?>
                <div class="info-box" style="background-color: #e7f3ff; border-left: 4px solid #0066cc; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <strong>ℹ️ Informação:</strong> Os postos arquivados não aparecem no fluxo da linha. 
                    Você pode restaurá-los (voltar a ativo) ou deletar permanentemente.
                </div>

                <table>
                    <thead>
                        <tr>
                            <th width="25%">Linha de Produção</th>
                            <th width="40%">Nome do Posto</th>
                            <th width="35%">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhas_json as $linha): ?>
                            <?php if (!empty($linha['postos'])): ?>
                                <?php foreach ($linha['postos'] as $post_index => $posto): ?>
                                    <?php if (!isset($posto['status']) || $posto['status'] !== 'arquivado') continue; ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($linha['nome']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($linha['id']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($posto['nome']); ?></strong>
                                        </td>
                                        <td>
                                            <a href="?restaurar_posto=<?php echo $post_index; ?>&linha_id=<?php echo urlencode($linha['id']); ?>" 
                                               class="btn btn-sm btn-success" 
                                               onclick="return confirm('Restaurar este posto para ativo?')">↩️ Restaurar</a>
                                            <a href="?deletar_permanente=<?php echo $post_index; ?>&linha_id=<?php echo urlencode($linha['id']); ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('⚠️ DELETAR PERMANENTEMENTE? Esta ação não pode ser desfeita!')">🗑️ Deletar Permanente</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <hr style="margin-top: 40px; border: none; border-top: 2px solid #e9ecef;">

            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <a href="postos.php" class="back-button" style="background-color: #6c757d;">
                    ← Voltar aos Postos Ativos
                </a>
                <a href="index.php" class="back-button" style="background-color: #495057;">
                    ↑ Fluxo da Linha
                </a>
            </div>
        </div>
    </div>

    <style>
        .back-button {
            display: inline-block;
            padding: 12px 24px;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: opacity 0.2s;
        }
        
        .back-button:hover {
            opacity: 0.8;
        }

        .info-box {
            font-size: 14px;
            line-height: 1.5;
        }

        .empty-message {
            text-align: center;
            color: #6c757d;
            padding: 60px 20px;
            background-color: #f8f9fa;
            border-radius: 6px;
            font-size: 16px;
        }
    </style>
</body>
</html>

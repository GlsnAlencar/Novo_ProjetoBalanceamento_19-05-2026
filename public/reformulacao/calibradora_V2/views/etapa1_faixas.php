<?php
/**
 * ETAPA 1: Cadastro de ConfiguraÃ§Ãµes e Faixas de Peso da Calibradora
 * 
 * Permite cadastrar mÃºltiplas configuraÃ§Ãµes/programas (Ex: ExportaÃ§Ã£o, MI, 4KG EXP)
 * Com suas respectivas faixas de classificaÃ§Ã£o de peso.
 */

require_once __DIR__ . '/../bootstrap.php';

$message = '';
$message_type = '';
$id_config_selecionado = null;
$config_selecionada = null;
$faixas_config = [];

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'criar_config') {
        $result = $controller->processarRequisicao('criar_config_calibrador', [
            'nome' => $_POST['nome'] ?? '',
            'descricao' => $_POST['descricao'] ?? '',
            'ativo' => isset($_POST['ativo']) ? 1 : 0
        ]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
        if ($result['sucesso'] && isset($result['dados'])) {
            $id_config_selecionado = $result['dados']['id'];
        }
    } elseif ($action === 'atualizar_config') {
        $result = $controller->processarRequisicao('atualizar_config_calibrador', [
            'id' => $_POST['id'] ?? 0,
            'nome' => $_POST['nome'] ?? '',
            'descricao' => $_POST['descricao'] ?? '',
            'ativo' => isset($_POST['ativo']) ? 1 : 0
        ]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    } elseif ($action === 'deletar_config') {
        $result = $controller->processarRequisicao('deletar_config_calibrador', [
            'id' => $_POST['id'] ?? 0
        ]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
        if ($result['sucesso']) {
            $id_config_selecionado = null;
        }
    } elseif ($action === 'criar_faixa') {
        $result = $controller->processarRequisicao('criar_faixa', [
            'seq' => $_POST['seq'] ?? 0,
            'calibre' => $_POST['calibre'] ?? '',
            'peso_inicial' => $_POST['peso_inicial'] ?? 0,
            'peso_final' => $_POST['peso_final'] ?? 0,
            'nome_configuracao' => $_POST['nome_configuracao'] ?? ''
        ]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    } elseif ($action === 'atualizar_faixa') {
        $result = $controller->processarRequisicao('atualizar_faixa', [
            'id' => $_POST['id'] ?? 0,
            'seq' => $_POST['seq'] ?? 0,
            'calibre' => $_POST['calibre'] ?? '',
            'peso_inicial' => $_POST['peso_inicial'] ?? 0,
            'peso_final' => $_POST['peso_final'] ?? 0
        ]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    } elseif ($action === 'deletar_faixa') {
        $result = $controller->processarRequisicao('deletar_faixa', [
            'id' => $_POST['id'] ?? 0
        ]);
        $message = $result['mensagem'];
        $message_type = $result['sucesso'] ? 'success' : 'error';
    }
}

// Obter ID da configuraÃ§Ã£o selecionada (via GET ou POST)
$id_config_selecionado = $id_config_selecionado ?? (int)($_GET['config_id'] ?? 0);

// Obter todas as configuraÃ§Ãµes
$all_configs = $controller->processarRequisicao('obter_configs_calibrador')['dados'] ?? [];

// Obter configuraÃ§Ã£o selecionada e suas faixas
if ($id_config_selecionado > 0) {
    $result_config = $controller->processarRequisicao('obter_config_calibrador', ['id' => $id_config_selecionado]);
    if ($result_config['sucesso']) {
        $config_selecionada = $result_config['dados'];
        
        // Obter faixas de peso desta configuraÃ§Ã£o
        $result_faixas = $controller->processarRequisicao('obter_faixas_config', ['nome_configuracao' => $config_selecionada['nome']]);
        $faixas_config = $result_faixas['dados'] ?? [];
    }
}
?>
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConfiguraÃ§Ãµes de CalibraÃ§Ã£o - Calibradora</title>
    <?php include '../calibradora/styles_ui.php'; ?>
    <style>
        .config-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .config-header h1 {
            margin: 0;
            font-size: 28px;
        }

        .config-header p {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .tab-container {
            display: flex;
            gap: 0;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }

        .tab-button {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #666;
            font-weight: bold;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-button:hover:not(.active) {
            color: #333;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .editable-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .editable-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #ddd;
        }

        .editable-table th {
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #333;
        }

        .editable-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        .editable-table td input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .editable-table td input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 4px rgba(102, 126, 234, 0.3);
        }

        .row-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .row-actions button {
            padding: 6px 12px;
            font-size: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .empty-state p {
            font-size: 16px;
            margin: 0;
        }

        .config-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .config-item-info {
            flex: 1;
        }

        .config-item-name {
            font-weight: bold;
            font-size: 16px;
            color: #333;
        }

        .config-item-desc {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .config-item-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .config-item-status.ativo {
            background: #d4edda;
            color: #155724;
        }

        .config-item-status.inativo {
            background: #f8d7da;
            color: #721c24;
        }

        .config-item-actions {
            display: flex;
            gap: 8px;
            margin-left: 15px;
        }

        .config-item-actions button {
            padding: 8px 12px;
            font-size: 12px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h2 {
            margin: 0;
            color: #333;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-row.row-2 {
            grid-template-columns: 1fr 1fr;
        }

        .form-row.row-5 {
            grid-template-columns: 80px 2fr 1fr 1fr 150px;
        }

        @media (max-width: 768px) {
            .form-row.row-5 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="calibradora-page">
<div class="calibradora-container">
    <!-- BREADCRUMB -->
    <div class="breadcrumb-nav">
        <a href="index.php">â† Voltar ao Hub</a>
    </div>

    <!-- HEADER -->
    <div class="config-header">
        <h1>âš™ï¸ ConfiguraÃ§Ãµes de CalibraÃ§Ã£o</h1>
        <p>Gerencie programas de classificaÃ§Ã£o e suas faixas de peso</p>
    </div>

    <!-- FEEDBACK -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tab-container">
        <button class="tab-button active" onclick="switchTab('tab-configuracoes')">
            ðŸ“‹ Minhas ConfiguraÃ§Ãµes
        </button>
        <button class="tab-button" onclick="switchTab('tab-criar')">
            âž• Nova ConfiguraÃ§Ã£o
        </button>
    </div>

    <!-- TAB: LISTAR CONFIGURAÃ‡Ã•ES -->
    <div id="tab-configuracoes" class="tab-content active">
        <?php if (empty($all_configs)): ?>
            <div class="empty-state">
                <p>Nenhuma configuraÃ§Ã£o cadastrada ainda.</p>
                <p>Clique em "Nova ConfiguraÃ§Ã£o" para comeÃ§ar.</p>
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 10px;">
                <?php foreach ($all_configs as $config): ?>
                    <div class="config-item">
                        <div class="config-item-info">
                            <div class="config-item-name"><?php echo htmlspecialchars($config['nome']); ?></div>
                            <?php if (!empty($config['descricao'])): ?>
                                <div class="config-item-desc"><?php echo htmlspecialchars($config['descricao']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span class="config-item-status <?php echo $config['ativo'] ? 'ativo' : 'inativo'; ?>">
                                <?php echo $config['ativo'] ? 'âœ“ Ativo' : 'âœ— Inativo'; ?>
                            </span>
                            <div class="config-item-actions">
                                <button type="button" class="btn btn-primary btn-sm" 
                                    onclick="selecionarConfiguracao(<?php echo $config['id']; ?>)">
                                    Editar Faixas
                                </button>
                                <button type="button" class="btn btn-info btn-sm" 
                                    onclick="editarConfiguracao(<?php echo $config['id']; ?>)">
                                    Editar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB: CRIAR CONFIGURAÃ‡ÃƒO -->
    <div id="tab-criar" class="tab-content">
        <div class="card">
            <div class="card-header">Criar Nova ConfiguraÃ§Ã£o</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="criar_config">

                    <div class="form-row row-2">
                        <div class="form-group">
                            <label class="form-label">
                                Nome da ConfiguraÃ§Ã£o <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" name="nome" 
                                placeholder="Ex: ExportaÃ§Ã£o, Mercado Interno, 4KG EXP"
                                required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                DescriÃ§Ã£o (opcional)
                            </label>
                            <input type="text" class="form-control" name="descricao" 
                                placeholder="Ex: Programa para frutas destinadas Ã  exportaÃ§Ã£o">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="ativo" value="1" checked>
                            Ativo (disponÃ­vel para usar)
                        </label>
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-success">ðŸ’¾ Criar ConfiguraÃ§Ã£o</button>
                        <button type="button" class="btn btn-secondary" onclick="switchTab('tab-configuracoes')">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIÃ‡ÃƒO DE FAIXAS (Mostrado quando configuraÃ§Ã£o estÃ¡ selecionada) -->
    <?php if ($config_selecionada): ?>
    <div class="card" style="margin-top: 30px;">
        <div class="card-header">
            ðŸ“Š Faixas de Peso - "<?php echo htmlspecialchars($config_selecionada['nome']); ?>"
        </div>
        <div class="card-body">
            <!-- CABEÃ‡ALHO DA CONFIGURAÃ‡ÃƒO -->
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #ddd;">
                <div>
                    <strong>Nome:</strong> <?php echo htmlspecialchars($config_selecionada['nome']); ?>
                </div>
                <div>
                    <strong>DescriÃ§Ã£o:</strong> <?php echo !empty($config_selecionada['descricao']) ? htmlspecialchars($config_selecionada['descricao']) : 'â€”'; ?>
                </div>
                <div>
                    <strong>Status:</strong> 
                    <span class="badge <?php echo $config_selecionada['ativo'] ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo $config_selecionada['ativo'] ? 'Ativo' : 'Inativo'; ?>
                    </span>
                </div>
            </div>

            <!-- TABELA EDITÃVEL DE FAIXAS -->
            <?php if (empty($faixas_config)): ?>
                <div class="empty-state">
                    <p>Nenhuma faixa de peso cadastrada para esta configuraÃ§Ã£o.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="editable-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Gr.</th>
                                <th style="width: 35%;">DescriÃ§Ã£o</th>
                                <th style="width: 20%;">Peso Inicial (g)</th>
                                <th style="width: 20%;">Peso Final (g)</th>
                                <th style="width: 15%; text-align: center;">AÃ§Ãµes</th>
                            </tr>
                        </thead>
                        <tbody id="faixas-tbody">
                            <?php foreach ($faixas_config as $faixa): ?>
                                <tr data-faixa-id="<?php echo $faixa['id']; ?>">
                                    <td>
                                        <span class="faixa-seq"><?php echo $faixa['seq']; ?></span>
                                    </td>
                                    <td>
                                        <span class="faixa-calibre"><?php echo htmlspecialchars($faixa['calibre']); ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <span class="faixa-peso-inicial"><?php echo number_format($faixa['peso_inicial'], 0, ',', '.'); ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <span class="faixa-peso-final"><?php echo number_format($faixa['peso_final'], 0, ',', '.'); ?></span>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <button type="button" class="btn btn-primary btn-sm" 
                                                onclick="editarFaixa(<?php echo $faixa['id']; ?>, this)">
                                                âœŽ Editar
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="deletar_faixa">
                                                <input type="hidden" name="id" value="<?php echo $faixa['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Remover esta faixa?')">
                                                    ðŸ—‘ Remover
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- FORMULÃRIO NOVA FAIXA -->
            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #ddd;">
                <h4>Adicionar Nova Faixa de Peso</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="criar_faixa">
                    <input type="hidden" name="nome_configuracao" value="<?php echo htmlspecialchars($config_selecionada['nome']); ?>">

                    <div class="form-row row-5">
                        <div class="form-group">
                            <label class="form-label">Gr. <span class="required">*</span></label>
                            <input type="number" class="form-control input-number" name="seq" 
                                placeholder="Ex: 1" min="1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">DescriÃ§Ã£o <span class="required">*</span></label>
                            <input type="text" class="form-control" name="calibre" 
                                placeholder="Ex: REFUGO ou Caixa 12" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Peso Inicial (g) <span class="required">*</span></label>
                            <input type="number" class="form-control input-number" name="peso_inicial" 
                                placeholder="Ex: 50" step="1" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Peso Final (g) <span class="required">*</span></label>
                            <input type="number" class="form-control input-number" name="peso_final" 
                                placeholder="Ex: 150" step="1" min="0" required>
                        </div>
                        <div class="form-group" style="display: flex; flex-direction: column; justify-content: flex-end;">
                            <button type="submit" class="btn btn-success">âž• Adicionar Faixa</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- BOTÃ•ES DE AÃ‡ÃƒO -->
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='?'">
                    â† Voltar
                </button>
                <button type="button" class="btn btn-info" onclick="editarConfiguracao(<?php echo $config_selecionada['id']; ?>)">
                    âš™ï¸ Editar ConfiguraÃ§Ã£o
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- MODAL EDITAR CONFIGURAÃ‡ÃƒO -->
<div id="modal-editar-config" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar ConfiguraÃ§Ã£o</h2>
        </div>
        <form id="form-editar-config" method="POST">
            <input type="hidden" name="action" value="atualizar_config">
            <input type="hidden" name="id" id="config-edit-id">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nome <span class="required">*</span></label>
                    <input type="text" class="form-control" id="config-edit-nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label class="form-label">DescriÃ§Ã£o</label>
                    <input type="text" class="form-control" id="config-edit-descricao" name="descricao">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="config-edit-ativo" name="ativo" value="1">
                        Ativo
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modal-editar-config')">
                    Cancelar
                </button>
                <button type="button" class="btn btn-danger" onclick="deletarConfiguracaoModal()">
                    ðŸ—‘ Deletar
                </button>
                <button type="submit" class="btn btn-success">
                    ðŸ’¾ Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR FAIXA -->
<div id="modal-editar-faixa" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Faixa de Peso</h2>
        </div>
        <form id="form-editar-faixa" method="POST">
            <input type="hidden" name="action" value="atualizar_faixa">
            <input type="hidden" name="id" id="faixa-edit-id">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Gr. <span class="required">*</span></label>
                    <input type="number" class="form-control" id="faixa-edit-seq" name="seq" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">DescriÃ§Ã£o <span class="required">*</span></label>
                    <input type="text" class="form-control" id="faixa-edit-calibre" name="calibre" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Peso Inicial (g) <span class="required">*</span></label>
                    <input type="number" class="form-control" id="faixa-edit-peso-inicial" name="peso_inicial" 
                        step="1" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Peso Final (g) <span class="required">*</span></label>
                    <input type="number" class="form-control" id="faixa-edit-peso-final" name="peso_final" 
                        step="1" min="0" required>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modal-editar-faixa')">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    ðŸ’¾ Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tabName) {
    // Ocultar todos os tabs
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-button').forEach(el => el.classList.remove('active'));

    // Mostrar tab selecionado
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}

function selecionarConfiguracao(configId) {
    window.location.href = '?config_id=' + configId;
}

function editarConfiguracao(configId) {
    const config = <?php echo json_encode($all_configs ?? []); ?>.find(c => c.id === configId);
    if (!config) return;

    document.getElementById('config-edit-id').value = config.id;
    document.getElementById('config-edit-nome').value = config.nome;
    document.getElementById('config-edit-descricao').value = config.descricao || '';
    document.getElementById('config-edit-ativo').checked = config.ativo ? true : false;

    abrirModal('modal-editar-config');
}

function deletarConfiguracaoModal() {
    if (!confirm('Deseja realmente deletar esta configuraÃ§Ã£o? Todas as faixas associadas serÃ£o removidas.')) {
        return;
    }

    const id = document.getElementById('config-edit-id').value;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="deletar_config">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function editarFaixa(faixaId, btn) {
    const row = btn.closest('tr');
    const seq = row.querySelector('.faixa-seq').textContent;
    const calibre = row.querySelector('.faixa-calibre').textContent;
    const peso_inicial = row.querySelector('.faixa-peso-inicial').textContent.replace(/\D/g, '');
    const peso_final = row.querySelector('.faixa-peso-final').textContent.replace(/\D/g, '');

    document.getElementById('faixa-edit-id').value = faixaId;
    document.getElementById('faixa-edit-seq').value = seq;
    document.getElementById('faixa-edit-calibre').value = calibre;
    document.getElementById('faixa-edit-peso-inicial').value = peso_inicial;
    document.getElementById('faixa-edit-peso-final').value = peso_final;

    abrirModal('modal-editar-faixa');
}

function abrirModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function fecharModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// Fechar modal ao clicar fora do conteÃºdo
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.remove('show');
        }
    });
});
</script>
</body>

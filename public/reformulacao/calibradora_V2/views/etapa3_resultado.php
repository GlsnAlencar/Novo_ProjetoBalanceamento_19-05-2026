<?php
/**
 * ETAPA 3: Lançamento de Partida - Resultado da Calibradora
 * 
 * Tela principal onde usuário:
 * 1. Seleciona configuração de faixas (EXP, MI, CLASSIF)
 * 2. Preenche dados da partida
 * 3. Insere peso total e percentuais
 * 4. Sistema calcula pesos automaticamente
 * 5. Salva no histórico
 */

require_once __DIR__ . '/../bootstrap.php';

$message = '';
$message_type = '';
$configuracoes = [];
$faixas_selecionadas = [];

// Obter configurações
$result = $controller->processarRequisicao('obter_faixas');
$todas_faixas = $result['dados'] ?? [];

// Extrair configurações únicas
foreach ($todas_faixas as $faixa) {
    if (!in_array($faixa['nome_configuracao'], $configuracoes)) {
        $configuracoes[] = $faixa['nome_configuracao'];
    }
}
sort($configuracoes);

// Processar POST (salvamento de partida)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'salvar_partida') {
        $controle = trim($_POST['controle'] ?? '');
        $config = trim($_POST['configuracao'] ?? '');
        $peso_total = (float)($_POST['peso_total'] ?? 0);

        if (empty($controle)) {
            $message = 'Número de controle é obrigatório';
            $message_type = 'error';
        } elseif (empty($config)) {
            $message = 'Configuração é obrigatória';
            $message_type = 'error';
        } elseif ($peso_total <= 0) {
            $message = 'Peso total deve ser maior que zero';
            $message_type = 'error';
        } else {
            // Validar percentuais
            $percentuais = $_POST['percentual'] ?? [];
            $soma_percent = array_sum(array_map(fn($p) => (float)$p, $percentuais));

            if ($soma_percent != 100) {
                $message = "Soma dos percentuais deve ser 100% (atual: {$soma_percent}%)";
                $message_type = 'warning';
            } else {
                // TODO: Implementar salvamento em registros_lote.json
                $message = 'Partida salva com sucesso!';
                $message_type = 'success';
            }
        }
    }
}

// Se configuração selecionada, carregar faixas
$config_selecionada = $_GET['config'] ?? ($_POST['configuracao'] ?? '');
if (!empty($config_selecionada)) {
    $faixas_selecionadas = array_filter($todas_faixas, fn($f) => $f['nome_configuracao'] === $config_selecionada);
    $faixas_selecionadas = array_values($faixas_selecionadas);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lançamento de Partida - Calibradora</title>
    <?php include '../calibradora/styles_ui.php'; ?>
</head>
<body class="calibradora-page">
<div class="calibradora-container">
    <!-- BREADCRUMB -->
    <div class="breadcrumb-nav">
        <a href="index.php">← Voltar ao Hub</a>
    </div>

    <!-- HEADER -->
    <div class="calibradora-header">
        <h1>📦 Lançamento de Partida - Calibradora</h1>
    </div>

    <!-- FEEDBACK -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- FORMULÁRIO PRINCIPAL -->
    <div class="card">
        <div class="card-header">
            📋 Dados da Partida
        </div>
        <div class="card-body">
            <form method="POST" id="formPartida">
                <input type="hidden" name="action" value="salvar_partida">

                <!-- ROW 1: Controle, Configuração -->
                <div class="form-row row-2">
                    <div class="form-group">
                        <label class="form-label">
                            Nº Controle
                            <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            name="controle"
                            placeholder="Ex: CTRL-001"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            Configuração/Faixas
                            <span class="required">*</span>
                        </label>
                        <select 
                            name="configuracao"
                            class="form-control select-search"
                            id="selecConfig"
                            required
                            onchange="carregarFaixas(this.value)"
                        >
                            <option value="">-- Selecione uma configuração --</option>
                            <?php foreach ($configuracoes as $config): ?>
                                <option value="<?php echo htmlspecialchars($config); ?>" 
                                    <?php echo ($config === $config_selecionada) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($config); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- ROW 2: Peso Total -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Peso Total da Partida (g)
                            <span class="required">*</span>
                        </label>
                        <input 
                            type="number" 
                            class="form-control input-number" 
                            name="peso_total"
                            placeholder="Ex: 1000000"
                            step="1"
                            min="0"
                            id="pesoTotal"
                            required
                        >
                    </div>
                </div>

                <!-- ROW 3: Dados Opcionais -->
                <div class="form-row row-3">
                    <div class="form-group">
                        <label class="form-label">Produtor</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            name="produtor"
                            placeholder="Ex: João Silva"
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label">Variedade</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            name="variedade"
                            placeholder="Ex: Palmer"
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label">Classe</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            name="classe"
                            placeholder="Ex: A"
                        >
                    </div>
                </div>

                <!-- ROW 4: Observação -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Observação</label>
                        <textarea 
                            class="form-control form-control-lg" 
                            name="observacao"
                            placeholder="Observações sobre a partida..."
                            rows="3"
                        ></textarea>
                    </div>
                </div>

                <!-- TABELA DE FAIXAS -->
                <div style="margin-top: 30px; margin-bottom: 20px;">
                    <h2 style="font-size: 16px; font-weight: 800; color: var(--text-color); margin-bottom: 16px;">
                        📊 Distribuição por Faixa
                    </h2>

                    <?php if (empty($faixas_selecionadas)): ?>
                        <div class="alert alert-info">
                            Selecione uma configuração acima para carregar as faixas
                        </div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Seq</th>
                                        <th>Calibre</th>
                                        <th>Peso Inicial</th>
                                        <th>Peso Final</th>
                                        <th>Tipo Embalamento</th>
                                        <th style="text-align: center;">% do Total</th>
                                        <th style="text-align: right;">Peso Calculado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($faixas_selecionadas as $idx => $faixa): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($faixa['seq']); ?></td>
                                            <td><?php echo htmlspecialchars($faixa['calibre']); ?></td>
                                            <td><?php echo number_format($faixa['peso_inicial'], 0, ',', '.'); ?> g</td>
                                            <td><?php echo number_format($faixa['peso_final'], 0, ',', '.'); ?> g</td>
                                            <td>
                                                <span class="badge badge-primary">
                                                    <?php echo htmlspecialchars($faixa['tipo_embalamento'] ?? 'Não definido'); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <input 
                                                    type="number" 
                                                    class="form-control input-number percentual-input"
                                                    name="percentual[]"
                                                    placeholder="0"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    data-faixa="<?php echo $idx; ?>"
                                                    onchange="calcularPeso(<?php echo $idx; ?>)"
                                                    style="text-align: center;"
                                                >
                                            </td>
                                            <td style="text-align: right;">
                                                <strong id="peso-calc-<?php echo $idx; ?>">0 g</strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr style="background: #f5f7fa; font-weight: 800;">
                                        <td colspan="5" style="text-align: right;">SOMA DOS PERCENTUAIS:</td>
                                        <td style="text-align: center;">
                                            <span id="somaPorcentagem">0</span>%
                                        </td>
                                        <td style="text-align: right;">
                                            <span id="somaPeso">0</span> g
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- VALIDAÇÃO VISUAL -->
                        <div id="validacaoPercentual" style="margin-top: 12px; padding: 12px; border-radius: 5px; display: none;">
                            <!-- Será preenchido pelo JavaScript -->
                        </div>
                    <?php endif; ?>
                </div>

                <!-- FOOTER -->
                <div class="card-footer">
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success">💾 Salvar Partida</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
/**
 * FUNÇÕES JAVASCRIPT
 */

// Carregar faixas via AJAX quando configuração muda
function carregarFaixas(config) {
    if (!config) {
        location.href = '?';
        return;
    }
    location.href = '?config=' + encodeURIComponent(config);
}

// Calcular peso automaticamente
function calcularPeso(idx) {
    const pesoTotal = parseFloat(document.getElementById('pesoTotal').value) || 0;
    const inputs = document.querySelectorAll('.percentual-input');
    
    if (pesoTotal <= 0) {
        alert('Preencha o peso total primeiro');
        return;
    }

    // Calcular cada peso
    let somaPorcentagem = 0;
    let somaPeso = 0;

    inputs.forEach((input, i) => {
        const percentual = parseFloat(input.value) || 0;
        const pesoCal = (pesoTotal * percentual) / 100;
        
        document.getElementById(`peso-calc-${i}`).textContent = 
            pesoCal > 0 ? Math.round(pesoCal).toLocaleString('pt-BR') + ' g' : '0 g';
        
        somaPorcentagem += percentual;
        somaPeso += pesoCal;
    });

    // Atualizar somas
    document.getElementById('somaPorcentagem').textContent = somaPorcentagem.toFixed(2);
    document.getElementById('somaPeso').textContent = Math.round(somaPeso).toLocaleString('pt-BR');

    // Validação visual
    const validacao = document.getElementById('validacaoPercentual');
    if (somaPorcentagem > 0) {
        validacao.style.display = 'block';
        if (Math.abs(somaPorcentagem - 100) < 0.01) {
            validacao.className = 'alert alert-success';
            validacao.textContent = '✓ Total de percentuais = 100%';
        } else {
            validacao.className = 'alert alert-warning';
            validacao.innerHTML = `⚠ Total de percentuais = ${somaPorcentagem.toFixed(2)}% (deve ser 100%)`;
        }
    } else {
        validacao.style.display = 'none';
    }
}

// Validar formulário antes de submeter
document.getElementById('formPartida').addEventListener('submit', function(e) {
    const pesoTotal = parseFloat(document.getElementById('pesoTotal').value) || 0;
    const somaPorcentagem = parseFloat(document.getElementById('somaPorcentagem').textContent) || 0;

    if (Math.abs(somaPorcentagem - 100) > 0.01) {
        e.preventDefault();
        alert(`Soma dos percentuais deve ser 100%\nAtual: ${somaPorcentagem.toFixed(2)}%`);
        return false;
    }

    if (pesoTotal <= 0) {
        e.preventDefault();
        alert('Peso total deve ser maior que zero');
        return false;
    }
});

// Ao carregar página, se houver faixas, recalcular
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.percentual-input');
    if (inputs.length > 0) {
        calcularPeso(0);
    }
});
</script>

</body>
</html>

<?php
/**
 * Módulo de Cronoanálise: Gerenciamento de Atividades do Posto
 * Centraliza operações Estáticas, de Transporte e Mistas.
 */
session_start();

// Simulação de carregamento de dados (ajustar conforme data_store.php real)
$linha_id = $_GET['linha'] ?? 'L001';
$post_index = $_GET['post'] ?? 0;
$back_page = $_GET['back'] ?? 'fluxo';

// Inclui o menu que carrega o styles.css padronizado
include 'menu.php'; 
?>

<div class="content">
    <div class="container">
        <div class="header-breadcrumb">
            <h1>⏱️ Cronoanálise de Atividade</h1>
            <p class="text-muted">Posto: <span id="posto_nome">Carregando...</span> | Linha: <?php echo htmlspecialchars($linha_id); ?></p>
        </div>

        <div class="form-section">
            <h3>Configuração da Operação</h3>
            <form id="form_atividade" method="POST" action="api_cronoanalise.php">
                <input type="hidden" name="linha_id" value="<?php echo htmlspecialchars($linha_id); ?>">
                <input type="hidden" name="post_index" value="<?php echo htmlspecialchars($post_index); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="tipo_operacao" class="required">Tipo de Operação</label>
                        <select name="tipo_operacao" id="tipo_operacao" class="form-control" onchange="toggleTipoOperacao()" required>
                            <option value="ESTATICA">ESTÁTICA (Embalagem, Seleção...)</option>
                            <option value="TRANSPORTE">TRANSPORTE (Puxar Pallet, Movimentação...)</option>
                            <option value="MISTA">MISTA (Híbrida / Decomposição)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="descricao" class="required">Descrição da Atividade</label>
                        <input type="text" id="descricao" name="descricao" class="form-control" placeholder="Ex: Etiquetagem de caixas" required>
                    </div>
                </div>

                <hr class="mt-20 mb-20">

                <!-- SEÇÃO 1: OPERAÇÃO ESTÁTICA -->
                <div id="secao_estatica" class="secao-dinamica">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Unidade Referência</label>
                            <input type="text" name="unidade_ref" class="form-control" placeholder="Ex: Cx, Un, Pallet">
                        </div>
                        <div class="form-group">
                            <label>Quantidade Referência</label>
                            <input type="number" step="0.01" name="quantidade_ref" class="form-control" oninput="calcularTempoUnitario()">
                        </div>
                        <div class="form-group">
                            <label>Tempo Total (s)</label>
                            <input type="number" step="0.1" name="tempo_total" id="tempo_total_estatica" class="form-control" oninput="calcularTempoUnitario()">
                        </div>
                        <div class="form-group">
                            <label>Tempo Unitário (s)</label>
                            <input type="text" id="tempo_unitario" class="form-control" readonly style="background: #f8f9fa;">
                        </div>
                        <div class="form-group">
                            <label>Pessoas</label>
                            <input type="number" name="pessoas" class="form-control" value="1">
                        </div>
                        <div class="form-group">
                            <label>Eficiência (%)</label>
                            <input type="number" name="eficiencia" class="form-control" value="100">
                        </div>
                    </div>
                </div>

                <!-- SEÇÃO 2: OPERAÇÃO DE TRANSPORTE -->
                <div id="secao_transporte" class="secao-dinamica" style="display:none;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Distância (metros)</label>
                            <input type="number" step="0.1" name="distancia_m" id="distancia_m" class="form-control" oninput="calcularVelocidade()">
                        </div>
                        <div class="form-group">
                            <label>Tempo Total (s)</label>
                            <input type="number" step="0.1" name="tempo_total_transp" id="tempo_total_transp" class="form-control" oninput="calcularVelocidade()">
                        </div>
                        <div class="form-group">
                            <label>Velocidade Média (m/s)</label>
                            <input type="text" id="velocidade_media" class="form-control" readonly style="background: #f8f9fa;">
                        </div>
                        <div class="form-group">
                            <label>Meio de Transporte</label>
                            <input type="text" name="meio_transporte" class="form-control" placeholder="Ex: Empilhadeira, Carrinho">
                        </div>
                        <div class="form-group">
                            <label>Origem</label>
                            <input type="text" name="origem" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Destino</label>
                            <input type="text" name="destino" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Capacidade Carga</label>
                            <input type="text" name="capacidade_carga" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- SEÇÃO 3: OPERAÇÃO MISTA (ELEMENTOS OPERACIONAIS) -->
                <div id="secao_mista" class="secao-dinamica" style="display:none;">
                    <div class="card mb-20">
                        <div class="card-header bg-light">
                            <strong>📋 Elementos Operacionais (Decomposição)</strong>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm" id="tabela_elementos">
                                <thead>
                                    <tr>
                                        <th width="50">Seq</th>
                                        <th width="120">Tipo</th>
                                        <th>Descrição</th>
                                        <th width="100">Dist. (m)</th>
                                        <th width="100">Qtd.</th>
                                        <th width="100">Tempo (s)</th>
                                        <th width="50"></th>
                                    </tr>
                                </thead>
                                <tbody id="lista_elementos">
                                    <!-- Linhas dinâmicas -->
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-sm btn-info" onclick="addLinhaElemento()">+ Adicionar Elemento</button>
                        </div>
                        <div class="card-footer text-right">
                            <strong>Tempo Total Acumulado: <span id="total_mista">0.0</span> s</strong>
                        </div>
                    </div>
                </div>

                <div class="mt-30">
                    <button type="submit" class="btn btn-success">✓ Salvar Atividade</button>
                    <a href="<?php echo $back_page; ?>.php?linha=<?php echo $linha_id; ?>" class="btn btn-secondary">← Voltar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/**
 * Alterna a visibilidade dos campos com base no tipo de operação
 */
function toggleTipoOperacao() {
    const tipo = document.getElementById('tipo_operacao').value;
    
    // Oculta todas as seções
    document.querySelectorAll('.secao-dinamica').forEach(el => el.style.display = 'none');
    
    // Exibe a seção correspondente
    if (tipo === 'ESTATICA') {
        document.getElementById('secao_estatica').style.display = 'block';
    } else if (tipo === 'TRANSPORTE') {
        document.getElementById('secao_transporte').style.display = 'block';
    } else if (tipo === 'MISTA') {
        document.getElementById('secao_mista').style.display = 'block';
    }
}

/**
 * Cálculos para Operação Estática
 */
function calcularTempoUnitario() {
    const qtd = parseFloat(document.getElementsByName('quantidade_ref')[0].value) || 0;
    const tempo = parseFloat(document.getElementById('tempo_total_estatica').value) || 0;
    const display = document.getElementById('tempo_unitario');
    
    if (qtd > 0 && tempo > 0) {
        display.value = (tempo / qtd).toFixed(3);
    } else {
        display.value = '-';
    }
}

/**
 * Cálculos para Operação de Transporte
 */
function calcularVelocidade() {
    const dist = parseFloat(document.getElementById('distancia_m').value) || 0;
    const tempo = parseFloat(document.getElementById('tempo_total_transp').value) || 0;
    const display = document.getElementById('velocidade_media');
    
    if (dist > 0 && tempo > 0) {
        display.value = (dist / tempo).toFixed(2);
    } else {
        display.value = '-';
    }
}

/**
 * Gerenciamento de Elementos da Operação Mista
 */
let seqElemento = 1;
function addLinhaElemento() {
    const tbody = document.getElementById('lista_elementos');
    const row = document.createElement('tr');
    
    row.innerHTML = `
        <td><input type="text" name="elem_seq[]" class="form-control form-control-sm" value="${seqElemento++}" readonly></td>
        <td>
            <select name="elem_tipo[]" class="form-control form-control-sm">
                <option value="ESTATICA">ESTÁTICA</option>
                <option value="TRANSPORTE">TRANSPORTE</option>
            </select>
        </td>
        <td><input type="text" name="elem_desc[]" class="form-control form-control-sm"></td>
        <td><input type="number" name="elem_dist[]" class="form-control form-control-sm" oninput="somaTotalMista()"></td>
        <td><input type="number" name="elem_qtd[]" class="form-control form-control-sm"></td>
        <td><input type="number" name="elem_tempo[]" class="form-control form-control-sm elem-tempo" oninput="somaTotalMista()"></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove(); somaTotalMista();">×</button></td>
    `;
    
    tbody.appendChild(row);
}

function somaTotalMista() {
    let total = 0;
    document.querySelectorAll('.elem-tempo').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    document.getElementById('total_mista').innerText = total.toFixed(1);
}

// Inicia com Estática visível
window.onload = toggleTipoOperacao;
</script>

<style>
/* Estilos específicos para refinamento da tela de cronoanálise */
.form-section {
    background: #fff;
    border: 1px solid #e0e6ed;
    border-radius: 8px;
    padding: 25px;
    margin-top: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.header-breadcrumb h1 {
    font-size: 24px;
    color: #1a1a1a;
    margin-bottom: 5px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 13px;
    color: #4b5563;
}

.required::after {
    content: " *";
    color: #ef4444;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.form-control:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.card {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    overflow: hidden;
}

.card-header { padding: 10px 15px; border-bottom: 1px solid #e5e7eb; }
.card-body { padding: 15px; }
.card-footer { padding: 10px 15px; border-top: 1px solid #e5e7eb; background: #f9fafb; }
</style>
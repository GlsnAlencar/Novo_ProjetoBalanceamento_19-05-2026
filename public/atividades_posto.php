<?php 
// Inclusão de segurança e menu padrão do sistema
// require_once __DIR__ . '/security.php'; 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Cronoanálise | Engenharia Industrial</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include __DIR__ . '/menu.php'; ?>

    <div class="main-container">
        <!-- 1. CABEÇALHO -->
        <header class="page-header card mb-4">
            <div class="d-flex align-items-center mb-3">
                <i class="fas fa-stopwatch icon-crono-blue fa-2x me-3"></i>
                <h1 class="h3 mb-0">Nova Cronoanálise</h1>
            </div>
            
            <div class="header-grid">
                <div class="fields-left">
                    <div class="header-row">
                        <div class="input-group-custom">
                            <label>Tipo de Operação</label>
                            <input type="text" id="tipoOperacao" placeholder="Ex: Embalamento">
                        </div>
                        <div class="input-group-custom">
                            <label>Produto (Árvore de Estrutura)</label>
                            <input type="text" id="produtoAe" placeholder="Selecionar SKU...">
                        </div>
                        <div class="input-group-custom">
                            <label>Descrição da Atividade</label>
                            <input type="text" id="descAtividade" placeholder="Detalhes da tarefa...">
                        </div>
                    </div>
                    <div class="header-row mt-3">
                        <div class="input-group-custom">
                            <label>Unidade Referência</label>
                            <input type="text" id="unidadeRef" placeholder="Ex: Caixa, Kg">
                        </div>
                        <div class="input-group-custom">
                            <label>Quantidade Referência</label>
                            <input type="number" id="qtyRef" value="1" min="1">
                        </div>
                        <div class="input-group-custom">
                            <label>Tempo Total (s)</label>
                            <input type="number" id="tempoTotal" value="0" step="0.01">
                        </div>
                        <div class="input-group-custom highlight-unit">
                            <label>Tempo Unitário Utilizado</label>
                            <input type="text" id="tempoUnitarioResult" class="fw-bold" readonly value="0.00">
                        </div>
                    </div>
                </div>

                <!-- SELEÇÃO DO TEMPO UTILIZADO -->
                <div class="time-selector-card">
                    <label class="d-block mb-2 fw-bold text-secondary">Utilizar qual tempo?</label>
                    <div class="radio-ticagem">
                        <label class="ticagem-item">
                            <input type="radio" name="tempoBase" value="TR">
                            <div class="ticagem-content">
                                <strong>TR</strong>
                                <span>Tempo Real</span>
                            </div>
                        </label>
                        <label class="ticagem-item">
                            <input type="radio" name="tempoBase" value="TN">
                            <div class="ticagem-content">
                                <strong>TN</strong>
                                <span>Tempo Normal</span>
                            </div>
                        </label>
                        <label class="ticagem-item">
                            <input type="radio" name="tempoBase" value="TP" checked>
                            <div class="ticagem-content">
                                <strong>TP</strong>
                                <span>Tempo Padrão</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </header>

        <!-- 3. BLOCO FATORES DE CÁLCULO -->
        <section class="mb-4">
            <div class="section-title mb-3">
                <i class="fas fa-gear icon-crono-blue me-2"></i>
                <h2 class="h5 d-inline">FATORES DE CÁLCULO</h2>
            </div>

            <div class="factors-grid">
                <!-- CARD TR -->
                <div class="calc-card tr-block">
                    <h3>TEMPO REAL - TR</h3>
                    <div class="card-body">
                        <div class="mb-2"><small>Tempo Total: <span id="view_total">0</span>s</small></div>
                        <div class="mb-3"><small>Qtd Referência: <span id="view_qty">1</span></small></div>
                        <div class="result-box">
                            <span class="label">Tempo Real Unitário - TR</span>
                            <span class="value" id="res_tr">0.00</span>
                        </div>
                        <div class="formula-box">TR = Tempo Total / Quantidade</div>
                    </div>
                </div>

                <!-- CARD TN -->
                <div class="calc-card tn-block">
                    <h3>TEMPO NORMAL - TN</h3>
                    <div class="card-body">
                        <label class="small fw-bold">Fator de Ritmo</label>
                        <select id="fatorRitmo" class="form-select mb-3">
                            <option value="0.90">0.90 - Abaixo do Normal</option>
                            <option value="1.00" selected>1.00 - Normal</option>
                            <option value="1.05">1.05 - Levemente Acima</option>
                            <option value="1.10">1.10 - Acima do Normal</option>
                        </select>
                        <div class="result-box">
                            <span class="label">Tempo Normal - TN</span>
                            <span class="value" id="res_tn">0.00</span>
                        </div>
                        <div class="formula-box">TN = TR × Fator de Ritmo</div>
                    </div>
                </div>

                <!-- CARD TP -->
                <div class="calc-card tp-block">
                    <h3>Fatores de Tolerância (TP)</h3>
                    <div class="card-body">
                        <table class="table-tolerancia mb-3">
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>%</th>
                                    <th>Ativo</th>
                                </tr>
                            </thead>
                            <tbody id="tol_rows">
                                <tr><td>Pessoal</td><td><input type="number" class="tol-val" data-idx="0" value="0"></td><td><input type="checkbox" checked class="tol-chk"></td></tr>
                                <tr><td>Fadiga</td><td><input type="number" class="tol-val" data-idx="1" value="0"></td><td><input type="checkbox" checked class="tol-chk"></td></tr>
                                <tr><td>Ambiente</td><td><input type="number" class="tol-val" data-idx="2" value="0"></td><td><input type="checkbox" checked class="tol-chk"></td></tr>
                                <tr><td>Espera</td><td><input type="number" class="tol-val" data-idx="3" value="0"></td><td><input type="checkbox" checked class="tol-chk"></td></tr>
                                <tr><td>Operacional</td><td><input type="number" class="tol-val" data-idx="4" value="0"></td><td><input type="checkbox" checked class="tol-chk"></td></tr>
                            </tbody>
                        </table>
                        <div class="result-box">
                            <span class="label">Tempo Padrão - TP</span>
                            <span class="value" id="res_tp">0.00</span>
                        </div>
                        <div class="formula-box">TP = TN × (1 + Tol/100)</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 4. RESUMO DOS RESULTADOS -->
        <div class="kpi-row mb-4">
            <div class="kpi-mini tr">TR: <span id="kpi_tr">0.00</span>s</div>
            <div class="kpi-mini tn">TN: <span id="kpi_tn">0.00</span>s</div>
            <div class="kpi-mini tol">Tol. Total: <span id="kpi_tol">0</span>%</div>
            <div class="kpi-mini factor">Fator Tol: <span id="kpi_ftol">1.00</span></div>
            <div class="kpi-mini tp">TP: <span id="kpi_tp">0.00</span>s</div>
        </div>

        <!-- 5. MEMÓRIA DE CÁLCULO -->
        <div class="memory-card mb-4">
            <h3 class="h6 mb-3 text-uppercase fw-bold"><i class="fas fa-brain me-2"></i>Memória de Cálculo</h3>
            <div id="memoria_content" class="monospace-font">
                <!-- JS injeta aqui -->
            </div>
        </div>

        <!-- 6. BOTÕES -->
        <footer class="d-flex justify-content-end gap-3 mt-5">
            <button class="btn btn-dark" onclick="window.history.back()">Voltar</button>
            <button class="btn btn-secondary" id="btnLimpar">Limpar</button>
            <button class="btn btn-success px-5" id="btnSalvar">Salvar Cronoanálise</button>
        </footer>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const inputs = ['tempoTotal', 'qtyRef', 'fatorRitmo'];
        const tolVals = document.querySelectorAll('.tol-val');
        const tolChks = document.querySelectorAll('.tol-chk');
        const timeRadios = document.getElementsByName('tempoBase');

        const calculate = () => {
            const total = parseFloat(document.getElementById('tempoTotal').value) || 0;
            const qty = parseFloat(document.getElementById('qtyRef').value) || 1;
            const ritmo = parseFloat(document.getElementById('fatorRitmo').value) || 1;
            
            // TR
            const tr = total / qty;
            
            // TN
            const tn = tr * ritmo;
            
            // Tolerâncias
            let tolSum = 0;
            tolVals.forEach((input, i) => {
                if(tolChks[i].checked) tolSum += parseFloat(input.value) || 0;
            });
            const fTol = 1 + (tolSum / 100);
            
            // TP
            const tp = tn * fTol;
            
            // Atualizar UI
            document.getElementById('res_tr').innerText = tr.toFixed(2);
            document.getElementById('res_tn').innerText = tn.toFixed(2);
            document.getElementById('res_tp').innerText = tp.toFixed(2);
            document.getElementById('view_total').innerText = total;
            document.getElementById('view_qty').innerText = qty;
            
            document.getElementById('kpi_tr').innerText = tr.toFixed(2);
            document.getElementById('kpi_tn').innerText = tn.toFixed(2);
            document.getElementById('kpi_tol').innerText = tolSum;
            document.getElementById('kpi_ftol').innerText = fTol.toFixed(2);
            document.getElementById('kpi_tp').innerText = tp.toFixed(2);
            
            // Tempo Utilizado
            const selectedType = Array.from(timeRadios).find(r => r.checked).value;
            const finalValue = selectedType === 'TR' ? tr : (selectedType === 'TN' ? tn : tp);
            document.getElementById('tempoUnitarioResult').value = finalValue.toFixed(2);

            // Memória
            document.getElementById('memoria_content').innerHTML = `
                <div>TR = ${total.toFixed(2)} / ${qty} = <strong>${tr.toFixed(2)}</strong></div>
                <div class="text-muted my-1">↓</div>
                <div>TN = ${tr.toFixed(2)} × ${ritmo.toFixed(2)} = <strong>${tn.toFixed(2)}</strong></div>
                <div class="text-muted my-1">↓</div>
                <div>TP = ${tn.toFixed(2)} × (1 + ${tolSum}/100) = <strong>${tp.toFixed(2)}</strong></div>
            `;
        };

        // Bind events
        inputs.forEach(id => document.getElementById(id).addEventListener('input', calculate));
        tolVals.forEach(el => el.addEventListener('input', calculate));
        tolChks.forEach(el => el.addEventListener('change', calculate));
        timeRadios.forEach(el => el.addEventListener('change', calculate));
        
        document.getElementById('btnLimpar').addEventListener('click', () => {
            document.querySelectorAll('input:not([type="radio"]):not([type="checkbox"])').forEach(i => i.value = i.defaultValue || "");
            calculate();
        });

        document.getElementById('btnSalvar').addEventListener('click', () => {
            const data = {
                tr: document.getElementById('kpi_tr').innerText,
                tn: document.getElementById('kpi_tn').innerText,
                tp: document.getElementById('kpi_tp').innerText,
                base: Array.from(timeRadios).find(r => r.checked).value,
                // ... capturar demais campos para o fetch
            };
            alert("Cronoanálise calculada com sucesso: " + data.tp + " s/un");
        });

        calculate(); // Inicial
    });
    </script>

</body>
</html>

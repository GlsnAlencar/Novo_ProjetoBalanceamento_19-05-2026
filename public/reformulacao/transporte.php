<?php
session_start();

require_once __DIR__ . '/module_routes.php';
require_once rf_route_path('arvore_estrutura', 'api');
require_once __DIR__ . '/safe_storage.php';
require_once __DIR__ . '/shared/CadastrosBasicosRepository.php';
require_once __DIR__ . '/cronoanalise/repositories/CronoanaliseRepository.php';

function tr_names_from_catalog($catalog, $fallback = []) {
    $names = [];
    foreach (cb_list($catalog, true) as $row) {
        $name = trim((string)($row['nome'] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }
    $names = array_values(array_unique($names));
    return !empty($names) ? $names : $fallback;
}

$setores = cb_list('setores', true);
$linhas = cb_list('linhas', true);
$linha_padrao = $linhas[0] ?? [];
$crono_repository = new CronoanaliseRepository(rf_route('editor_bpm', 'storage'));
$transportes_cadastrados = array_values(array_filter(
    $crono_repository->listarCronoanalises([]),
    fn($row) => stripos((string)($row['tipo_atividade'] ?? $row['tipo_operacao'] ?? ''), 'transporte') !== false
));
$origens = [];
$destinos = [];
foreach ($transportes_cadastrados as $row) {
    $origem = trim((string)($row['origem'] ?? ''));
    $destino = trim((string)($row['destino'] ?? ''));
    if ($origem !== '') $origens[$origem] = $origem;
    if ($destino !== '') $destinos[$destino] = $destino;
}
natcasesort($origens);
natcasesort($destinos);
$arvore_data = ae_api_load_data();
$itens_arvore = [];
foreach (($arvore_data['tabela_itens'] ?? []) as $item) {
    if ((int)($item['ativo'] ?? 1) !== 1) {
        continue;
    }
    $id = trim((string)($item['id'] ?? ''));
    if ($id === '') {
        continue;
    }
    $codigo = trim((string)($item['codigo'] ?? ''));
    $nome = trim((string)($item['nome'] ?? ($item['descricao_item'] ?? '')));
    $itens_arvore[] = [
        'id' => $id,
        'label' => trim(($codigo !== '' ? $codigo . ' - ' : '') . $nome),
    ];
}
usort($itens_arvore, fn($a, $b) => strnatcasecmp($a['label'], $b['label']));

$atividades = tr_names_from_catalog('atividades_padrao', ['Transporte pallet', 'Transporte caixa', 'Abastecimento']);
if (empty($atividades)) {
    $atividades = ['Transporte pallet', 'Transporte caixa', 'Abastecimento'];
}
$grupos = tr_names_from_catalog('tipos_embalagem', ['Caixa 4kg', 'Caixa 6kg', 'Caixa 18kg', 'IFCO']);
$variacoes = ['Sem papel', 'Papel simples', 'Fraldinha', 'Com touca'];
$calibres = tr_names_from_catalog('faixas_calibradora', ['05', '06', '07', '08', '09', '10', '12', 'P', 'M', 'G']);
$setor_padrao = $setores[0]['nome'] ?? '';
$grupo_padrao = $grupos[0] ?? '';

function tr_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tr_combo_options($options, $value_key = null, $id_key = null) {
    foreach ($options as $option) {
        $value = $value_key === null ? $option : ($option[$value_key] ?? '');
        $id = $id_key === null ? '' : ($option[$id_key] ?? '');
        if (trim((string)$value) === '') {
            continue;
        }
        echo '<button type="button" class="tr-combo-option" data-value="' . tr_h($value) . '" data-id="' . tr_h($id) . '">' . tr_h($value) . '</button>';
    }
}

include __DIR__ . '/menu.php';
?>

<div class="content crono-page">
    <div class="crono-shell">
        <form id="form_transporte" class="crono-form-card">
            <input type="hidden" name="action" value="save_transporte">

            <div class="crono-title-row">
                <div class="crono-title">
                    <span class="crono-title-icon">&gt;</span>
                    <h1>Novo transporte</h1>
                </div>
            </div>

            <div class="crono-header-grid row-three">
                <div class="crono-field">
                    <label>Tipo de Operacao <span>*</span></label>
                    <div class="tr-combo" data-free="0">
                        <input type="text" name="tipo_operacao" id="tipo_operacao" class="crono-input tr-combo-input" value="TRANSPORTE" autocomplete="off" required>
                        <div class="tr-combo-menu">
                            <?php tr_combo_options(['OPERACAO', 'TRANSPORTE', 'HIBRIDA']); ?>
                            <div class="tr-combo-empty">Nenhum cadastro encontrado</div>
                        </div>
                    </div>
                </div>
                <div class="crono-field">
                    <label>Atividade <span>*</span></label>
                    <div class="tr-combo" data-free="1">
                        <input type="text" name="atividade" id="atividade" class="crono-input tr-combo-input" placeholder="Pesquisar ou digitar atividade" autocomplete="off" required>
                        <div class="tr-combo-menu" id="atividades_transporte_opcoes">
                            <?php tr_combo_options($atividades); ?>
                            <div class="tr-combo-empty">Digite para inserir novo cadastro</div>
                        </div>
                    </div>
                </div>
                <div class="crono-field">
                    <label>Origem</label>
                    <div class="tr-combo" data-free="1">
                        <input name="origem" id="origem" class="crono-input tr-combo-input" placeholder="Pesquisar ou digitar origem" autocomplete="off">
                        <div class="tr-combo-menu">
                            <?php tr_combo_options(array_values($origens)); ?>
                            <div class="tr-combo-empty">Digite para inserir novo cadastro</div>
                        </div>
                    </div>
                </div>
                <div class="crono-field">
                    <label>Destino</label>
                    <div class="tr-combo" data-free="1">
                        <input name="destino" id="destino" class="crono-input tr-combo-input" placeholder="Pesquisar ou digitar destino" autocomplete="off">
                        <div class="tr-combo-menu">
                            <?php tr_combo_options(array_values($destinos)); ?>
                            <div class="tr-combo-empty">Digite para inserir novo cadastro</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="crono-header-grid row-three">
                <div class="crono-field">
                    <label>Setor</label>
                    <div class="tr-combo" data-free="0">
                        <input type="text" name="setor" id="setor" class="crono-input tr-combo-input" value="<?php echo tr_h($setor_padrao); ?>" autocomplete="off">
                        <div class="tr-combo-menu">
                            <?php tr_combo_options($setores, 'nome', 'id'); ?>
                            <div class="tr-combo-empty">Nenhum cadastro encontrado</div>
                        </div>
                    </div>
                    <input type="hidden" name="setor_id" id="setor_id" value="<?php echo tr_h($setores[0]['id'] ?? ''); ?>">
                </div>
                <input type="hidden" name="linha_nome" id="linha_nome" value="<?php echo tr_h($linha_padrao['nome'] ?? 'Transporte'); ?>">
                <input type="hidden" name="linha_id" id="linha_id" value="<?php echo tr_h($linha_padrao['id'] ?? 'transporte'); ?>">
                <input type="hidden" name="linha_ref_id" id="linha_ref_id" value="<?php echo tr_h($linha_padrao['id'] ?? ''); ?>">
                <input type="hidden" name="posto" value="">
            </div>

            <div class="crono-header-grid row-three">
                <div class="crono-field">
                    <label>Item</label>
                    <div class="tr-combo tr-combo-multi" data-free="0" data-multiple="1">
                        <div id="produto_chips" class="tr-combo-chips"></div>
                        <input type="text" id="produto_label" class="crono-input tr-combo-input" placeholder="Pesquisar item cadastrado" autocomplete="off">
                        <div class="tr-combo-menu">
                            <?php tr_combo_options($itens_arvore, 'label', 'id'); ?>
                            <div class="tr-combo-empty">Nenhum item encontrado</div>
                        </div>
                    </div>
                    <input type="hidden" name="produto_id" id="produto_id" value="">
                    <input type="hidden" name="item" id="item_label" value="">
                </div>
                <div class="crono-field">
                    <label>Grupo Embalagem</label>
                    <div class="tr-combo" data-free="0">
                        <input type="text" name="grupo_embalagem" id="grupo_embalagem" class="crono-input tr-combo-input" value="<?php echo tr_h($grupo_padrao); ?>" autocomplete="off">
                        <div class="tr-combo-menu">
                            <?php tr_combo_options($grupos); ?>
                            <div class="tr-combo-empty">Nenhum cadastro encontrado</div>
                        </div>
                    </div>
                </div>
                <div class="crono-field">
                    <label>Variacao</label>
                    <div class="tr-combo" data-free="0">
                        <input type="text" name="variacao" id="variacao" class="crono-input tr-combo-input" placeholder="Vazio" autocomplete="off">
                        <div class="tr-combo-menu">
                            <?php tr_combo_options($variacoes); ?>
                            <div class="tr-combo-empty">Nenhum cadastro encontrado</div>
                        </div>
                    </div>
                </div>
                <div class="crono-field">
                    <label>Calibre</label>
                    <div class="tr-combo" data-free="0">
                        <input type="text" name="calibre" id="calibre" class="crono-input tr-combo-input" placeholder="Vazio" autocomplete="off">
                        <div class="tr-combo-menu">
                            <?php tr_combo_options($calibres); ?>
                            <div class="tr-combo-empty">Nenhum cadastro encontrado</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="crono-header-grid row-four">
                <div class="crono-field"><label>Distancia metros <span>*</span></label><input type="number" min="0" step="0.01" name="distancia_m" id="distancia_m" class="crono-input" required></div>
                <div class="crono-field"><label>Tempo total (s) <span>*</span></label><input type="number" min="0.01" step="0.01" name="tempo_total" id="tempo_total" class="crono-input" required></div>
                <div class="crono-field"><label>Velocidade calculada (m/s)</label><input id="velocidade" class="crono-input crono-readonly" readonly value="0,00"></div>
                <div class="crono-field"><label>Operadores</label><input type="number" min="0" step="1" name="operadores" class="crono-input"></div>
            </div>

            <div id="crono_feedback" class="crono-feedback" hidden></div>
            <footer class="crono-footer">
                <button type="button" class="crono-btn save" onclick="salvarTransporte()">Salvar Transporte</button>
                <button type="reset" class="crono-btn clear">Limpar</button>
            </footer>
        </form>
    </div>
</div>

<style>
.tr-combo {
    position: relative;
}
.tr-combo-menu {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 230px;
    overflow-y: auto;
    z-index: 40;
    background: #fff;
    border: 1px solid #cbd8ea;
    border-radius: 6px;
    box-shadow: 0 12px 28px rgba(14, 35, 70, 0.16);
}
.tr-combo.open .tr-combo-menu {
    display: block;
}
.tr-combo-option,
.tr-combo-empty {
    width: 100%;
    padding: 10px 12px;
    border: 0;
    background: #fff;
    color: #071a3c;
    font: inherit;
    font-size: 14px;
    text-align: left;
}
.tr-combo-option {
    cursor: pointer;
}
.tr-combo-option.is-hidden {
    display: none !important;
}
.tr-combo-option:hover,
.tr-combo-option:focus {
    background: #eef6ff;
    outline: none;
}
.tr-combo-empty {
    display: none;
    color: #6b7280;
    cursor: default;
}
.tr-combo.has-empty .tr-combo-empty {
    display: block;
}
.tr-combo-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 6px;
}
.tr-combo-chip {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    gap: 6px;
    padding: 5px 9px;
    border: 1px solid #a7e7bf;
    border-radius: 6px;
    background: #ecfdf3;
    color: #05883a;
    font-size: 13px;
    font-weight: 700;
}
.tr-combo-chip span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.tr-combo-chip button {
    border: 0;
    background: transparent;
    color: #5f6f68;
    cursor: pointer;
    font: inherit;
    font-size: 18px;
    line-height: 1;
    padding: 0 1px;
}
</style>

<script>
const fmt = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function parseNumber(value) { return parseFloat(String(value || '').replace(/\./g, '').replace(',', '.')) || 0; }
function calcularVelocidade() {
    const distancia = parseNumber(document.getElementById('distancia_m').value);
    const tempo = parseNumber(document.getElementById('tempo_total').value);
    document.getElementById('velocidade').value = fmt.format(tempo > 0 ? distancia / tempo : 0);
}
function feedback(message, type = 'error') {
    const box = document.getElementById('crono_feedback');
    box.textContent = message;
    box.className = `crono-feedback ${type}`;
    box.hidden = false;
}

function comboOf(inputOrId) {
    const input = typeof inputOrId === 'string' ? document.getElementById(inputOrId) : inputOrId;
    return input?.closest('.tr-combo') || null;
}

function normalizeComboText(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

function findComboOption(inputOrId, value = null) {
    const input = typeof inputOrId === 'string' ? document.getElementById(inputOrId) : inputOrId;
    const combo = comboOf(input);
    const alvo = normalizeComboText(value ?? input?.value ?? '');
    if (!combo || !alvo) return null;
    return Array.from(combo.querySelectorAll('.tr-combo-option'))
        .find(option => normalizeComboText(option.dataset.value || '') === alvo) || null;
}

function filterCombo(input) {
    const combo = comboOf(input);
    if (!combo) return;
    const tokens = normalizeComboText(input?.value || '').split(' ').filter(Boolean);
    let visibleCount = 0;
    combo.querySelectorAll('.tr-combo-option').forEach(option => {
        const value = normalizeComboText(option.dataset.value || option.textContent || '');
        const match = tokens.length === 0 || tokens.every(token => value.includes(token));
        option.hidden = !match;
        option.classList.toggle('is-hidden', !match);
        if (match) visibleCount++;
    });
    combo.classList.toggle('has-empty', visibleCount === 0);
}

function openCombo(input) {
    document.querySelectorAll('.tr-combo.open').forEach(combo => {
        if (combo !== comboOf(input)) combo.classList.remove('open');
    });
    comboOf(input)?.classList.add('open');
    filterCombo(input);
}

function selectComboOption(option) {
    const combo = option.closest('.tr-combo');
    const input = combo?.querySelector('.tr-combo-input');
    if (!input) return;
    if (combo?.dataset.multiple === '1') {
        addMultiSelection(input, option);
        return;
    }
    combo.querySelectorAll('.tr-combo-option').forEach(item => {
        item.hidden = false;
        item.classList.remove('is-hidden');
    });
    combo.classList.remove('has-empty');
    input.value = option.dataset.value || option.textContent.trim();
    input.dataset.selectedId = option.dataset.id || '';
    input.dataset.justSelected = '1';
    combo.classList.remove('open');
    input.dispatchEvent(new Event('change', { bubbles: true }));
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function selectedItems() {
    const chips = document.getElementById('produto_chips');
    if (!chips) return [];
    return Array.from(chips.querySelectorAll('.tr-combo-chip')).map(chip => ({
        id: chip.dataset.id || '',
        value: chip.dataset.value || '',
    }));
}

function updateMultiHiddenFields() {
    const items = selectedItems();
    document.getElementById('produto_id').value = items.map(item => item.id).filter(Boolean).join(',');
    document.getElementById('item_label').value = items.map(item => item.value).filter(Boolean).join(' | ');
}

function addMultiSelection(input, option) {
    const combo = comboOf(input);
    const chips = document.getElementById('produto_chips');
    const value = option.dataset.value || option.textContent.trim();
    const id = option.dataset.id || '';
    if (!combo || !chips || !value) return;
    const alreadySelected = selectedItems().some(item => item.id === id && item.value === value);
    if (!alreadySelected) {
        const chip = document.createElement('span');
        chip.className = 'tr-combo-chip';
        chip.dataset.id = id;
        chip.dataset.value = value;
        const label = document.createElement('span');
        label.textContent = value;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.setAttribute('aria-label', 'Remover item');
        remove.textContent = '×';
        chip.append(label, remove);
        chips.appendChild(chip);
    }
    input.value = '';
    delete input.dataset.selectedId;
    combo.querySelectorAll('.tr-combo-option').forEach(item => {
        item.hidden = false;
        item.classList.remove('is-hidden');
    });
    combo.classList.remove('has-empty');
    combo.classList.add('open');
    updateMultiHiddenFields();
    filterCombo(input);
    input.focus();
}

function validateCombo(inputId, label, required = false) {
    const input = document.getElementById(inputId);
    const combo = comboOf(input);
    const value = String(input?.value || '').trim();
    if (combo?.dataset.multiple === '1') {
        const exactOption = value ? findComboOption(input, value) : null;
        if (value && !exactOption) {
            feedback(`Selecione ${label} da lista cadastrada.`);
            input?.focus();
            openCombo(input);
            return false;
        }
        if (exactOption) {
            addMultiSelection(input, exactOption);
        }
        return true;
    }
    if (required && !value) {
        feedback(`Preencha ${label}.`);
        input?.focus();
        return false;
    }
    if (!value || combo?.dataset.free === '1') return true;
    if (findComboOption(input, value)) return true;
    feedback(`Selecione ${label} da lista cadastrada.`);
    input?.focus();
    openCombo(input);
    return false;
}

function syncTransporteRefs() {
    const setor = document.getElementById('setor');
    const setorOption = findComboOption(setor, setor?.value || '');
    document.getElementById('setor_id').value = setorOption?.dataset.id || '';
    if (!document.getElementById('linha_id').value) {
        document.getElementById('linha_id').value = 'transporte';
    }
}

function syncItemSelecionado() {
    const produtoLabel = document.getElementById('produto_label');
    if (comboOf(produtoLabel)?.dataset.multiple === '1') {
        updateMultiHiddenFields();
        return true;
    }
    const itemOption = findComboOption(produtoLabel, produtoLabel?.value || '');
    document.getElementById('produto_id').value = itemOption?.dataset.id || '';
    document.getElementById('item_label').value = itemOption?.dataset.value || '';
    return !String(produtoLabel?.value || '').trim() || !!itemOption;
}

function atividadeJaCadastrada(nome) {
    const alvo = String(nome || '').trim().toLowerCase();
    if (!alvo) return true;
    return Array.from(document.querySelectorAll('#atividades_transporte_opcoes .tr-combo-option'))
        .some(option => String(option.dataset.value || '').trim().toLowerCase() === alvo);
}

function adicionarAtividadeSugestao(nome) {
    if (atividadeJaCadastrada(nome)) return;
    const option = document.createElement('button');
    option.type = 'button';
    option.className = 'tr-combo-option';
    option.dataset.value = String(nome || '').trim();
    option.textContent = option.dataset.value;
    const menu = document.getElementById('atividades_transporte_opcoes');
    menu?.insertBefore(option, menu.querySelector('.tr-combo-empty'));
}

async function salvarAtividadeTransporteSeNova() {
    const input = document.getElementById('atividade');
    const nome = String(input?.value || '').trim();
    if (!nome || atividadeJaCadastrada(nome)) return;

    const formData = new FormData();
    formData.append('action', 'save_cadastro_basico');
    formData.append('catalog', 'atividades_padrao');
    formData.append('nome', nome);

    const response = await fetch('api_cronoanalise.php', { method: 'POST', body: formData });
    if (response.ok) {
        adicionarAtividadeSugestao(nome);
    }
}

async function salvarTransporte() {
    calcularVelocidade();
    const form = document.getElementById('form_transporte');
    if (!form.reportValidity()) return;
    if (!validateCombo('tipo_operacao', 'Tipo de Operacao', true)) return;
    if (!validateCombo('setor', 'Setor')) return;
    if (!validateCombo('produto_label', 'Item')) return;
    if (!validateCombo('grupo_embalagem', 'Grupo Embalagem')) return;
    if (!validateCombo('variacao', 'Variacao')) return;
    if (!validateCombo('calibre', 'Calibre')) return;
    syncTransporteRefs();
    if (!syncItemSelecionado()) {
        feedback('Selecione um item ja cadastrado na Arvore de Estrutura.');
        return;
    }
    await salvarAtividadeTransporteSeNova();
    const response = await fetch('api_cronoanalise.php', { method: 'POST', body: new FormData(form) });
    const result = await response.json().catch(() => ({ status: 'error', message: 'Resposta invalida do servidor.' }));
    feedback(result.status === 'success' ? 'Transporte salvo com sucesso.' : (result.message || 'Erro ao salvar transporte.'), result.status === 'success' ? 'success' : 'error');
    if (result.status === 'success') form.reset();
    syncItemSelecionado();
    syncTransporteRefs();
    calcularVelocidade();
}
document.addEventListener('input', calcularVelocidade);
document.addEventListener('click', (event) => {
    if (!event.target.closest('.tr-combo')) {
        document.querySelectorAll('.tr-combo.open').forEach(combo => combo.classList.remove('open'));
    }
});
document.addEventListener('mousedown', (event) => {
    const option = event.target.closest('.tr-combo-option');
    if (!option) return;
    event.preventDefault();
    selectComboOption(option);
});
document.addEventListener('click', (event) => {
    const remove = event.target.closest('.tr-combo-chip button');
    if (!remove) return;
    remove.closest('.tr-combo-chip')?.remove();
    updateMultiHiddenFields();
});
document.querySelectorAll('.tr-combo-input').forEach(input => {
    input.addEventListener('focus', () => openCombo(input));
    input.addEventListener('click', () => openCombo(input));
    input.addEventListener('input', () => {
        if (input.dataset.justSelected === '1') {
            delete input.dataset.justSelected;
            return;
        }
        delete input.dataset.selectedId;
        openCombo(input);
    });
    input.addEventListener('keyup', () => filterCombo(input));
});
document.getElementById('setor')?.addEventListener('input', syncTransporteRefs);
document.getElementById('produto_label')?.addEventListener('input', syncItemSelecionado);
document.getElementById('form_transporte')?.addEventListener('reset', () => {
    setTimeout(() => {
        document.getElementById('produto_chips')?.replaceChildren();
        updateMultiHiddenFields();
        syncTransporteRefs();
        calcularVelocidade();
    }, 0);
});
syncTransporteRefs();
syncItemSelecionado();
calcularVelocidade();
</script>

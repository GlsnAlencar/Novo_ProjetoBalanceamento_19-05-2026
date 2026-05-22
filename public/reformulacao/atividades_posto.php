<?php
/**
 * Modulo de Cronoanalise: coleta de tempos para balanceamento.
 */
session_start();

require_once __DIR__ . '/module_routes.php';
require_once rf_route_path('arvore_estrutura', 'api');
require_once __DIR__ . '/safe_storage.php';
require_once __DIR__ . '/shared/CadastrosBasicosRepository.php';
require_once __DIR__ . '/cronoanalise/repositories/CronoanaliseRepository.php';

$linha_id = $_GET['linha'] ?? '';
$post_index = $_GET['post'] ?? 0;
$back_page = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $_GET['back'] ?? 'fluxo');
$edit_id = trim((string)($_GET['edit_id'] ?? ''));
$return_to = trim((string)($_GET['return_to'] ?? ''));
$return_url = trim((string)($_GET['return_url'] ?? ''));
if ($return_to !== 'consulta' || !preg_match('/^consultar_cronoanalises\.php(\?.*)?$/', $return_url)) {
    $return_url = '';
}

$arvore_data = ae_api_load_data();
$itens_arvore = $arvore_data['tabela_itens'] ?? [];
$itens_por_id = [];
foreach ($itens_arvore as $item) {
    $itens_por_id[$item['id'] ?? ''] = $item;
}
$produtos_options = [];
foreach ($itens_arvore as $item) {
    if ((int)($item['ativo'] ?? 1) !== 1) {
        continue;
    }
    $produto_id = (string)($item['id'] ?? '');
    if ($produto_id === '') {
        continue;
    }
    $codigo = trim((string)($item['codigo'] ?? ''));
    $descricao = trim((string)($item['nome'] ?? ($item['descricao_item'] ?? '')));
    $produtos_options[$produto_id] = [
        'id' => $produto_id,
        'codigo' => $codigo,
        'descricao_item' => $descricao,
        'unidade' => trim((string)($item['unidade_base'] ?? '')),
        'label' => trim(($codigo !== '' ? $codigo . ' - ' : '') . $descricao)
    ];
}
uasort($produtos_options, fn($a, $b) => strnatcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));

$cad_setores = cb_list('setores', true);
$cad_linhas = cb_list('linhas', true);
$cad_postos = cb_list('postos', true);
$cad_atividades = cb_list('atividades_padrao', true);
$cad_tipos_embalagem = cb_list('tipos_embalagem', true);
$cad_faixas = cb_list('faixas_calibradora', true);
$cad_unidades = cb_list('unidades', true);

$repository = new CronoanaliseRepository(rf_route('editor_bpm', 'storage'));
$edit_atividade = null;
if ($edit_id !== '') {
    foreach ($repository->listarCronoanalises([]) as $row) {
        if ((string)($row['id'] ?? '') === $edit_id) {
            $edit_atividade = $row;
            if ($linha_id === '') {
                $linha_id = (string)($row['linha_id'] ?? '');
            }
            if (!isset($_GET['post'])) {
                $post_index = (int)($row['post_index'] ?? 0);
            }
            break;
        }
    }
}

$fluxo_json = cod_12_05_safe_load_json(
    rf_route('editor_bpm', 'storage'),
    fn() => ['version' => 1, 'setores' => []],
    fn($data) => isset($data['setores']) && is_array($data['setores'])
);

if ($linha_id === '') {
    foreach ($fluxo_json['setores'] ?? [] as $setor) {
        if (!empty($setor['linhas'][0]['id'])) {
            $linha_id = $setor['linhas'][0]['id'];
            break;
        }
    }
}

$atividades_atuais = [];
$setor_atual = '';
$linha_atual = '';
$setor_atual_id = '';
$linha_atual_id = $linha_id;
foreach ($fluxo_json['setores'] ?? [] as $setor) {
    foreach (($setor['linhas'] ?? []) as $linha) {
        if (($linha['id'] ?? '') === $linha_id) {
            $atividades_atuais = $linha['atividades_por_posto']['posto_' . $post_index] ?? [];
            $setor_atual = $setor['nome'] ?? '';
            $linha_atual = $linha['nome'] ?? '';
            $setor_atual_id = $setor['id'] ?? '';
            break 2;
        }
    }
}

$linha_compartilhada = null;
foreach ($cad_linhas as $linha) {
    if (($linha['id'] ?? '') === $linha_id) {
        $linha_compartilhada = $linha;
        $linha_atual = $linha['nome'] ?? $linha_atual;
        $linha_atual_id = $linha['id'] ?? $linha_atual_id;
        break;
    }
}
if ($linha_compartilhada && ($linha_compartilhada['setor_id'] ?? '') !== '') {
    foreach ($cad_setores as $setor) {
        if (($setor['id'] ?? '') === ($linha_compartilhada['setor_id'] ?? '')) {
            $setor_atual = $setor['nome'] ?? $setor_atual;
            $setor_atual_id = $setor['id'] ?? $setor_atual_id;
            break;
        }
    }
}

$posto_atual = 'Posto ' . $post_index;
$posto_atual_id = '';
foreach ($cad_postos as $posto) {
    if (($posto['nome'] ?? '') === $posto_atual) {
        $posto_atual_id = $posto['id'] ?? '';
        break;
    }
}

$atividades_padrao = array_map(fn($row) => $row['nome'] ?? '', $cad_atividades);
$grupos_embalagem = array_map(fn($row) => $row['nome'] ?? '', $cad_tipos_embalagem);
if (empty(array_filter($grupos_embalagem))) {
    $grupos_embalagem = ['Caixa 4kg', 'Caixa 6kg', 'Caixa 18kg', 'IFCO'];
}
$variacoes_embalagem = ['Sem papel', 'Papel simples', 'Fraldinha', 'Com touca'];
$calibres_embalagem = array_map(fn($row) => $row['nome'] ?? '', $cad_faixas);
if (empty(array_filter($calibres_embalagem))) {
    $calibres_embalagem = ['05', '06', '07', '08', '09', '10', '12', 'P', 'M', 'G'];
}
$unidades_ref = array_map(fn($row) => $row['nome'] ?? '', $cad_unidades);

function crono_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function crono_fmt($value, $decimals = 2) {
    return number_format((float)$value, $decimals, ',', '.');
}

$edit_atividade_form = null;
if (is_array($edit_atividade)) {
    $pick = function (array $row, array $keys, $fallback = '') {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
                return $row[$key];
            }
        }
        return $fallback;
    };

    $edit_atividade_form = $edit_atividade;
    $edit_atividade_form['descricao'] = $pick($edit_atividade, ['descricao', 'atividade']);
    $edit_atividade_form['tipo_operacao'] = strtoupper((string)$pick($edit_atividade, ['tipo_operacao', 'tipo_calculo'], 'OPERACAO'));
    $edit_atividade_form['linha'] = $pick($edit_atividade, ['linha', 'linha_nome'], $linha_atual);
    $edit_atividade_form['unidade_ref'] = $pick($edit_atividade, ['unidade_ref', 'unidade_base', 'unidade_carga']);
    $edit_atividade_form['quantidade_ref'] = $pick($edit_atividade, ['quantidade_ref', 'qtd_ref', 'qtd_base', 'numero_frutos', 'num_frutos'], 1);
    $edit_atividade_form['tempo_total'] = $pick($edit_atividade, ['tempo_total', 'tempo_total_s', 'tempo_s']);
    $edit_atividade_form['tempo_base_utilizado'] = strtoupper((string)$pick($edit_atividade, ['tempo_base_utilizado'], 'TP'));
    $edit_atividade_form['fator_ritmo'] = (float)$pick($edit_atividade, ['fator_ritmo'], 1);
    $edit_atividade_form['fator_tolerancia'] = (float)$pick($edit_atividade, ['fator_tolerancia'], 1);
    $edit_atividade_form['tolerancias'] = is_array($edit_atividade['tolerancias'] ?? null) ? $edit_atividade['tolerancias'] : [];
}

include __DIR__ . '/menu.php';
?>

<div class="content crono-page">
    <div class="crono-shell">
        <section class="crono-list-card">
            <div class="crono-list-header">Atividades Cronometradas neste Posto</div>
            <div class="crono-table-wrap">
                <table class="crono-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Descricao</th>
                            <th>Embalagem</th>
                            <th>Setor / Linha / Posto</th>
                            <th>Tempo usado</th>
                            <th>Base</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($atividades_atuais as $act): ?>
                            <?php
                                $produto = $itens_por_id[$act['produto_id'] ?? ''] ?? null;
                                $produto_label = $produto ? (($produto['codigo'] ?? '') . ' - ' . ($produto['nome'] ?? '')) : '-';
                                $embalagem_label = trim(($act['grupo_embalagem'] ?? '') . ' / ' . ($act['variacao'] ?? '') . ' / ' . ($act['calibre'] ?? ''), ' /');
                                $local_label = trim(($act['setor'] ?? $setor_atual) . ' / ' . ($act['linha'] ?? $linha_atual) . ' / ' . ($act['posto'] ?? $posto_atual), ' /');
                                $tempo_usado = $act['tempo_unitario_utilizado'] ?? ($act['tempo_unitario'] ?? 0);
                            ?>
                            <tr>
                                <td><span class="crono-badge"><?php echo crono_h($act['tipo_operacao'] ?? '-'); ?></span></td>
                                <td><?php echo crono_h($act['descricao'] ?? '-'); ?></td>
                                <td><?php echo crono_h($embalagem_label ?: $produto_label); ?></td>
                                <td><?php echo crono_h($local_label ?: '-'); ?></td>
                                <td><strong><?php echo crono_fmt($tempo_usado); ?> s/un</strong></td>
                                <td><?php echo crono_h($act['tempo_base_utilizado'] ?? 'TP'); ?></td>
                                <td class="crono-actions-cell">
                                    <button type="button" class="crono-icon-btn" onclick='editarAtividade(<?php echo json_encode($act, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="Editar">Editar</button>
                                    <button type="button" class="crono-icon-btn danger" onclick="excluirAtividade('<?php echo crono_h($act['id'] ?? ''); ?>')" title="Arquivar">Arquivar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($atividades_atuais)): ?>
                            <tr>
                                <td colspan="7" class="crono-empty">Nenhuma atividade registrada para este posto.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <form id="form_atividade" class="crono-form-card">
            <input type="hidden" name="linha_id" value="<?php echo crono_h($linha_id); ?>">
            <input type="hidden" name="post_index" value="<?php echo crono_h($post_index); ?>">
            <input type="hidden" name="setor_id" id="setor_id" value="<?php echo crono_h($setor_atual_id); ?>">
            <input type="hidden" name="linha_ref_id" id="linha_ref_id" value="<?php echo crono_h($linha_atual_id); ?>">
            <input type="hidden" name="posto_id" id="posto_id" value="<?php echo crono_h($posto_atual_id); ?>">
            <input type="hidden" name="linha_nome" id="linha_nome" value="<?php echo crono_h($linha_atual); ?>">
            <input type="hidden" name="posto" id="posto" value="<?php echo crono_h($posto_atual); ?>">
            <input type="hidden" name="id" id="act_id" value="">
            <input type="hidden" name="tipo_atividade" id="tipo_atividade" value="operacao">
            <input type="hidden" name="tempo_base_utilizado" id="tempo_base_utilizado" value="TP">
            <input type="hidden" name="tempo_unitario_utilizado" id="tempo_unitario_utilizado_hidden" value="0">
            <input type="hidden" name="tr" id="tr_hidden" value="0">
            <input type="hidden" name="tn" id="tn_hidden" value="0">
            <input type="hidden" name="tp" id="tp_hidden" value="0">
            <input type="hidden" name="tolerancia_total" id="tolerancia_total_hidden" value="0">
            <input type="hidden" name="fator_tolerancia" id="fator_tolerancia_hidden" value="1">
            <input type="hidden" name="tolerancias_json" id="tolerancias_json" value="[]">
            <input type="hidden" name="chave_tecnica" id="chave_tecnica" value="">

            <div class="crono-title-row">
                <div class="crono-title">
                    <span class="crono-title-icon">⏱</span>
                    <h1 id="form_title">Nova Cronoanalise</h1>
                </div>
                <div class="crono-context">Linha: <?php echo crono_h($linha_atual ?: $linha_id ?: 'nao selecionada'); ?> | Posto: <?php echo crono_h($post_index); ?></div>
            </div>

            <div class="crono-header-grid row-one">
                <div class="crono-field">
                    <label for="tipo_operacao">Tipo de Operacao <span>*</span></label>
                    <select name="tipo_operacao" id="tipo_operacao" class="crono-input" required>
                        <option value="OPERACAO">OPERACAO</option>
                        <option value="TRANSPORTE">TRANSPORTE</option>
                        <option value="HIBRIDA">HIBRIDA</option>
                    </select>
                </div>

                <div class="crono-field">
                    <label for="setor">Setor</label>
                    <input type="text" name="setor" id="setor" class="crono-input" list="setor_opcoes" value="<?php echo crono_h($setor_atual); ?>">
                    <datalist id="setor_opcoes">
                        <?php foreach ($cad_setores as $setor): ?>
                            <?php $setor_nome = $setor['nome'] ?? ''; ?>
                            <option value="<?php echo crono_h($setor_nome); ?>" data-id="<?php echo crono_h($setor['id'] ?? ''); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="crono-field">
                    <label for="produto_id">Produto <a href="<?php echo crono_h(rf_route('arvore_estrutura', 'page')); ?>" target="_blank" rel="noopener">Editar na Arvore</a></label>
                    <select name="produto_id" id="produto_id" class="ae-native-select-hidden">
                        <option value="">-- Selecionar Produto --</option>
                        <?php foreach ($produtos_options as $produto): ?>
                            <option value="<?php echo crono_h($produto['id'] ?? ''); ?>" data-unidade="<?php echo crono_h($produto['unidade'] ?? ''); ?>">
                                <?php echo crono_h($produto['label'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="ae-combo" id="combo_produto" data-select-id="produto_id">
                        <button type="button" class="ae-combo-toggle" onclick="toggleCombo(this)">
                            <span id="combo_produto_label">-- Selecionar Produto --</span>
                            <b>⌄</b>
                        </button>
                        <div class="ae-combo-panel">
                            <input type="search" placeholder="Pesquisar produto..." oninput="filterCombo(this)">
                            <div class="ae-combo-options">
                                <div class="ae-combo-option selected" data-value="" data-unidade="" onclick="selectComboOption(this)">-- Selecionar Produto --</div>
                                <?php foreach ($produtos_options as $produto): ?>
                                    <div class="ae-combo-option" data-value="<?php echo crono_h($produto['id'] ?? ''); ?>" data-unidade="<?php echo crono_h($produto['unidade'] ?? ''); ?>" onclick="selectComboOption(this)">
                                        <?php echo crono_h($produto['label'] ?? ''); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="crono-header-grid row-two">
                <div class="crono-field">
                    <label for="grupo_embalagem">Grupo Embalagem</label>
                    <input type="text" name="grupo_embalagem" id="grupo_embalagem" class="crono-input" list="grupo_embalagem_opcoes" placeholder="-- Selecionar --">
                    <datalist id="grupo_embalagem_opcoes">
                        <?php foreach ($grupos_embalagem as $grupo): ?>
                            <option value="<?php echo crono_h($grupo); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="crono-field">
                    <label for="variacao">Variacao Embalagem</label>
                    <input type="text" name="variacao" id="variacao" class="crono-input" list="variacao_opcoes" placeholder="-- Selecionar --">
                    <datalist id="variacao_opcoes">
                        <?php foreach ($variacoes_embalagem as $variacao): ?>
                            <option value="<?php echo crono_h($variacao); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="crono-field">
                    <label for="calibre">Calibre</label>
                    <input type="text" name="calibre" id="calibre" class="crono-input" list="calibre_opcoes" placeholder="-- Selecionar --">
                    <datalist id="calibre_opcoes">
                        <?php foreach ($calibres_embalagem as $calibre): ?>
                            <option value="<?php echo crono_h($calibre); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <div class="crono-header-grid row-description">
                <div class="crono-field crono-wide">
                    <label for="descricao">Descricao da Atividade <span>*</span></label>
                    <input type="text" id="descricao" name="descricao" class="crono-input" list="atividades_padrao" placeholder="-- Selecionar ou digitar nova --" required>
                    <datalist id="atividades_padrao">
                        <?php foreach ($atividades_padrao as $atividade): ?>
                            <option value="<?php echo crono_h($atividade); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <div class="crono-header-grid second">
                <div class="crono-field">
                    <label for="unidade_ref">Unidade Referencia</label>
                    <input type="text" name="unidade_ref" id="unidade_ref" class="crono-input" list="unidade_ref_opcoes" placeholder="Ex: Cx, Un, Pallet">
                    <datalist id="unidade_ref_opcoes">
                        <?php foreach ($unidades_ref as $unidade): ?>
                            <option value="<?php echo crono_h($unidade); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="crono-field">
                    <label for="quantidade_ref">Total de caixa <span>*</span></label>
                    <input type="number" step="0.01" min="1" name="quantidade_ref" id="quantidade_ref" class="crono-input" placeholder="Ex: 1" value="1" required>
                </div>
                <div class="crono-field">
                    <label for="tempo_total">Tempo [s] <span>*</span></label>
                    <input type="number" step="0.01" min="0.01" name="tempo_total" id="tempo_total" class="crono-input" placeholder="Ex: 120,00" required>
                </div>
                <div class="crono-field">
                    <label for="er">ER</label>
                    <input type="number" step="0.01" min="0" name="er" id="er" class="crono-input" placeholder="Ex: 241">
                </div>
                <div class="crono-field">
                    <label for="tempo_unitario_utilizado">Tempo Unitario (s) - Utilizado</label>
                    <input type="text" id="tempo_unitario_utilizado" class="crono-input crono-readonly" value="0,00" readonly>
                </div>

                <fieldset class="crono-time-selector">
                    <legend>Utilizar qual tempo?</legend>
                    <p>Selecione qual tempo sera considerado como Tempo Unitario</p>
                    <div class="crono-radio-grid">
                        <label class="crono-radio-card">
                            <input type="radio" name="tempo_base" value="TR">
                            <span><strong>TR</strong><small>Tempo Real</small></span>
                        </label>
                        <label class="crono-radio-card">
                            <input type="radio" name="tempo_base" value="TN">
                            <span><strong>TN</strong><small>Tempo Normal</small></span>
                        </label>
                        <label class="crono-radio-card">
                            <input type="radio" name="tempo_base" value="TP" checked>
                            <span><strong>TP</strong><small>Tempo Padrao</small></span>
                        </label>
                    </div>
                </fieldset>
            </div>

            <section class="crono-factors">
                <div class="crono-section-title"><span>▦</span> FATORES DE CALCULO</div>
                <button type="button" class="crono-section-toggle" id="toggle_fatores_calculo" aria-expanded="true" aria-controls="fatores_calculo_body" onclick="toggleFatoresCalculo()">Recolher fatores</button>
                <div class="crono-factor-grid" id="fatores_calculo_body">
                    <article class="crono-calc-card tr">
                        <h2>1. TEMPO REAL - TR</h2>
                        <label>Tempo Total (s)</label>
                        <input type="text" class="crono-input mirror" id="mirror_tempo_total" readonly value="0,00">
                        <label>Total de caixa</label>
                        <input type="text" class="crono-input mirror" id="mirror_quantidade" readonly value="0">
                        <div class="crono-result-box">
                            <small>Tempo Real Unitario - TR (s/un)</small>
                            <strong id="res_tr">0,00</strong>
                        </div>
                        <div class="crono-formula">TR = Tempo Total / Total de caixa</div>
                    </article>

                    <article class="crono-calc-card tn">
                        <h2>2. TEMPO NORMAL - TN</h2>
                        <label for="fator_ritmo">Fator de Ritmo</label>
                        <div class="crono-inline">
                            <select name="fator_ritmo" id="fator_ritmo" class="crono-input">
                                <option value="0.90">0,90 - Abaixo do normal</option>
                                <option value="1.00" selected>1,00 - Normal</option>
                                <option value="1.05">1,05 - Levemente acima do normal</option>
                                <option value="1.10">1,10 - Acima do normal</option>
                            </select>
                            <input type="text" id="fator_ritmo_decimal" class="crono-input rhythm" value="100%" readonly>
                        </div>
                        <div class="crono-result-box">
                            <small>Tempo Normal - TN (s/un)</small>
                            <strong id="res_tn">0,00</strong>
                        </div>
                        <div class="crono-formula">TN = TR × Fator de Ritmo</div>
                    </article>

                    <article class="crono-calc-card tp">
                        <h2>3. TEMPO PADRAO - TP</h2>
                        <div class="crono-tolerance-title">Fatores de Tolerancia <span>(todos ativos por padrao)</span></div>
                        <div class="crono-tolerance-wrap">
                            <table class="crono-tolerance-table">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th>Descricao</th>
                                        <th>Percentual (%)</th>
                                        <th>Ativo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr data-category="Pessoal" data-description="Necessidades pessoais">
                                        <td>Pessoal</td><td>Necessidades pessoais</td>
                                        <td><label class="crono-percent-input"><input type="number" class="tol-percent" value="0" min="0" step="0.01"><span>%</span></label></td>
                                        <td><label class="crono-switch"><input type="checkbox" class="tol-active" checked><span></span></label></td>
                                    </tr>
                                    <tr data-category="Fadiga" data-description="Esforco, postura, repetitividade">
                                        <td>Fadiga</td><td>Esforco, postura, repetitividade</td>
                                        <td><label class="crono-percent-input"><input type="number" class="tol-percent" value="0" min="0" step="0.01"><span>%</span></label></td>
                                        <td><label class="crono-switch"><input type="checkbox" class="tol-active" checked><span></span></label></td>
                                    </tr>
                                    <tr data-category="Ambiente" data-description="Calor, ruido, umidade, iluminacao">
                                        <td>Ambiente</td><td>Calor, ruido, umidade, iluminacao</td>
                                        <td><label class="crono-percent-input"><input type="number" class="tol-percent" value="0" min="0" step="0.01"><span>%</span></label></td>
                                        <td><label class="crono-switch"><input type="checkbox" class="tol-active" checked><span></span></label></td>
                                    </tr>
                                    <tr data-category="Espera inevitavel" data-description="Pequenas paradas inevitaveis">
                                        <td>Espera inevitavel</td><td>Pequenas paradas inevitaveis</td>
                                        <td><label class="crono-percent-input"><input type="number" class="tol-percent" value="0" min="0" step="0.01"><span>%</span></label></td>
                                        <td><label class="crono-switch"><input type="checkbox" class="tol-active" checked><span></span></label></td>
                                    </tr>
                                    <tr data-category="Operacional especifica" data-description="Condicao especial da atividade">
                                        <td>Operacional especifica</td><td>Condicao especial da atividade</td>
                                        <td><label class="crono-percent-input"><input type="number" class="tol-percent" value="0" min="0" step="0.01"><span>%</span></label></td>
                                        <td><label class="crono-switch"><input type="checkbox" class="tol-active" checked><span></span></label></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="crono-tp-summary">
                            <div><span>Tolerancia Total (%)</span><strong id="res_tol_total">0,00</strong></div>
                            <div><span>Fator de Tolerancia</span><strong id="res_fator_tol">1,0000</strong></div>
                        </div>
                        <div class="crono-result-box">
                            <small>Tempo Padrao - TP (s/un)</small>
                            <strong id="res_tp">0,00</strong>
                        </div>
                        <div class="crono-formula">TP = TN × Fator de Tolerancia</div>
                    </article>
                </div>
            </section>

            <section class="crono-kpis" aria-label="Resumo dos resultados">
                <div class="crono-kpi tr"><span>TR - Tempo Real<br>(s/un)</span><strong id="kpi_tr">0,00</strong></div>
                <div class="crono-kpi tn"><span>TN - Tempo Normal<br>(s/un)</span><strong id="kpi_tn">0,00</strong></div>
                <div class="crono-kpi tol"><span>Tolerancia Total<br>(%)</span><strong id="kpi_tol">0,00</strong></div>
                <div class="crono-kpi factor"><span>Fator de Tolerancia</span><strong id="kpi_fator_tol">1,0000</strong></div>
                <div class="crono-kpi tp"><span>TP - Tempo Padrao<br>(s/un)</span><strong id="kpi_tp">0,00</strong></div>
            </section>

            <section class="crono-memory">
                <h2>⊞ MEMORIA DE CALCULO</h2>
                <div class="crono-memory-grid">
                    <div><strong>TR = Tempo Total / Quantidade</strong><span id="mem_tr">0,00 = 0,00 / 0</span></div>
                    <b>→</b>
                    <div><strong>TN = TR × Fator de Ritmo</strong><span id="mem_tn">0,00 = 0,00 × 100%</span></div>
                    <b>→</b>
                    <div><strong>TP = TN × (1 + Tolerancia / 100)</strong><span id="mem_tp">0,00 = 0,00 × (1 + 0,00 / 100)</span></div>
                </div>
            </section>

            <div id="crono_feedback" class="crono-feedback" hidden></div>

            <footer class="crono-footer">
                <button type="button" class="crono-btn save" onclick="salvarAtividade()">✓ Salvar Cronoanalise</button>
                <button type="button" class="crono-btn clear" onclick="resetForm()">Limpar</button>
                <a href="<?php echo crono_h($back_page); ?>.php?linha=<?php echo urlencode($linha_id); ?>" class="crono-btn back">← Voltar</a>
            </footer>
        </form>
    </div>
</div>

<script>
const cronoFormat = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const cronoFormat4 = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
const cronoPercentFormat = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const cronoInitialEdit = <?php echo json_encode($edit_atividade_form, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const cronoReturnUrl = <?php echo json_encode($return_url, JSON_UNESCAPED_UNICODE); ?>;
let cronoState = { tr: 0, tn: 0, tp: 0, toleranciaTotal: 0, fatorTolerancia: 1, tempoUtilizado: 0, tempoBase: 'TP' };

function parseNumber(value) {
    if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
    const raw = String(value || '').trim().replace(/[^\d,.-]/g, '');
    if (!raw) return 0;
    const normalized = raw.includes(',') && raw.includes('.')
        ? raw.replace(/\./g, '').replace(',', '.')
        : raw.replace(',', '.');
    return parseFloat(normalized) || 0;
}

function fmt(value) { return cronoFormat.format(Number.isFinite(value) ? value : 0); }
function fmt4(value) { return cronoFormat4.format(Number.isFinite(value) ? value : 0); }
function fmtPercentFactor(value) { return `${cronoPercentFormat.format((Number.isFinite(value) ? value : 0) * 100)}%`; }
function fmtPercentValue(value) { return `${fmt(value)}%`; }
function setText(id, value) { const el = document.getElementById(id); if (el) el.textContent = value; }
function setValue(id, value) { const el = document.getElementById(id); if (el) el.value = value; }
function syncDescricaoAtividade() {
    const descricao = document.getElementById('descricao');
    if (descricao) descricao.value = descricao.value.trimStart();
}
function setDescricaoAtividade(value) {
    setValue('descricao', value || '');
    syncDescricaoAtividade();
}
function findDatalistOption(listId, value) {
    const normalized = String(value || '').trim().toLowerCase();
    if (!normalized) return null;
    return Array.from(document.querySelectorAll(`#${listId} option`))
        .find(option => String(option.value || '').trim().toLowerCase() === normalized) || null;
}
function tecnicoSlug(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9]+/g, ' ')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .map(part => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
        .join('');
}
function updateChaveTecnica() {
    const parts = ['grupo_embalagem', 'variacao', 'calibre'].map(id => tecnicoSlug(document.getElementById(id)?.value || ''));
    setValue('chave_tecnica', parts.filter(Boolean).join('_'));
}

function getTolerancias() {
    return Array.from(document.querySelectorAll('.crono-tolerance-table tbody tr')).map(row => ({
        categoria: row.dataset.category || '',
        descricao: row.dataset.description || '',
        percentual: parseNumber(row.querySelector('.tol-percent').value),
        ativo: row.querySelector('.tol-active').checked
    }));
}

function calculate() {
    updateChaveTecnica();
    const tempoTotal = parseNumber(document.getElementById('tempo_total').value);
    const quantidade = parseNumber(document.getElementById('quantidade_ref').value);
    const fatorRitmo = parseNumber(document.getElementById('fator_ritmo').value) || 1;
    const tolerancias = getTolerancias();
    const toleranciaTotal = tolerancias.reduce((sum, row) => row.ativo ? sum + row.percentual : sum, 0);
    const fatorTolerancia = 1 + (toleranciaTotal / 100);
    const tr = quantidade > 0 ? tempoTotal / quantidade : 0;
    const tn = tr * fatorRitmo;
    const tp = tn * fatorTolerancia;
    const tempoBase = document.querySelector('input[name="tempo_base"]:checked')?.value || 'TP';
    const tempoUtilizado = tempoBase === 'TR' ? tr : (tempoBase === 'TN' ? tn : tp);

    cronoState = { tr, tn, tp, toleranciaTotal, fatorTolerancia, tempoUtilizado, tempoBase };

    setValue('mirror_tempo_total', fmt(tempoTotal));
    setValue('mirror_quantidade', quantidade > 0 ? fmt(quantidade) : '0,00');
    setValue('fator_ritmo_decimal', fmtPercentFactor(fatorRitmo));
    setValue('tempo_unitario_utilizado', fmt(tempoUtilizado));

    setText('res_tr', fmt(tr));
    setText('res_tn', fmt(tn));
    setText('res_tp', fmt(tp));
    setText('res_tol_total', fmtPercentValue(toleranciaTotal));
    setText('res_fator_tol', fmt4(fatorTolerancia));
    setText('kpi_tr', fmt(tr));
    setText('kpi_tn', fmt(tn));
    setText('kpi_tp', fmt(tp));
    setText('kpi_tol', fmtPercentValue(toleranciaTotal));
    setText('kpi_fator_tol', fmt4(fatorTolerancia));

    setText('mem_tr', `${fmt(tr)} = ${fmt(tempoTotal)} / ${quantidade > 0 ? fmt(quantidade) : '0,00'}`);
    setText('mem_tp', `${fmt(tp)} = ${fmt(tn)} × (1 + ${fmt(toleranciaTotal)} / 100)`);

    setValue('tempo_base_utilizado', tempoBase);
    setText('mem_tn', `${fmt(tn)} = ${fmt(tr)} × ${fmtPercentFactor(fatorRitmo)}`);
    setValue('tempo_unitario_utilizado_hidden', tempoUtilizado.toFixed(6));
    setValue('tr_hidden', tr.toFixed(6));
    setValue('tn_hidden', tn.toFixed(6));
    setValue('tp_hidden', tp.toFixed(6));
    setValue('tolerancia_total_hidden', toleranciaTotal.toFixed(6));
    setValue('fator_tolerancia_hidden', fatorTolerancia.toFixed(6));
    setValue('tolerancias_json', JSON.stringify(tolerancias));
}

function showFeedback(message, type = 'error') {
    const box = document.getElementById('crono_feedback');
    box.textContent = message;
    box.className = `crono-feedback ${type}`;
    box.hidden = false;
}

function applyFatoresCalculoState(collapsed) {
    const body = document.getElementById('fatores_calculo_body');
    const button = document.getElementById('toggle_fatores_calculo');
    if (!body || !button) return;

    body.hidden = collapsed;
    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    button.textContent = collapsed ? 'Expandir fatores' : 'Recolher fatores';
    button.classList.toggle('collapsed', collapsed);
}

function toggleFatoresCalculo() {
    const body = document.getElementById('fatores_calculo_body');
    const collapsed = !(body?.hidden || false);
    localStorage.setItem('crono-fatores-calculo-collapsed', collapsed ? '1' : '0');
    applyFatoresCalculoState(collapsed);
}

function validarCrono() {
    syncDescricaoAtividade();
    if (parseNumber(document.getElementById('tempo_total').value) <= 0) {
        showFeedback('Informe um Tempo Total maior que zero.');
        return false;
    }
    if (parseNumber(document.getElementById('quantidade_ref').value) < 1) {
        showFeedback('Informe um Total de caixa maior ou igual a 1.');
        return false;
    }
    if (!document.getElementById('descricao').value.trim()) {
        showFeedback('Informe a Descricao da Atividade.');
        return false;
    }
    return true;
}

async function salvarAtividade() {
    syncDescricaoAtividade();
    calculate();
    if (!validarCrono()) return;

    await persistirCadastrosBasicosDigitados();
    syncSharedReferenceIds();
    const formData = new FormData(document.getElementById('form_atividade'));
    formData.append('action', 'save_atividade');

    const response = await fetch('api_cronoanalise.php', { method: 'POST', body: formData });
    const result = await response.json().catch(() => ({ status: 'error', message: 'Resposta invalida do servidor.' }));

    if (response.ok && result.status === 'success') {
        showFeedback('Cronoanalise salva com sucesso.', 'success');
        window.setTimeout(() => {
            if (cronoReturnUrl) {
                window.location.href = cronoReturnUrl;
                return;
            }
            location.reload();
        }, 500);
        return;
    }
    showFeedback(result.message || 'Erro ao salvar cronoanalise.');
}

async function salvarAtividadePadrao(nome) {
    return salvarCadastroBasico('atividades_padrao', nome);
}

async function salvarCadastroBasico(catalog, nome) {
    const value = String(nome || '').trim();
    if (!value) return null;
    const formData = new FormData();
    formData.append('action', 'save_cadastro_basico');
    formData.append('catalog', catalog);
    formData.append('nome', value);
    try {
        const response = await fetch('api_cronoanalise.php', { method: 'POST', body: formData });
        const result = await response.json().catch(() => null);
        return response.ok ? (result?.row || result?.atividade || null) : null;
    } catch (error) {
        console.warn('Nao foi possivel atualizar o cadastro basico.', error);
        return null;
    }
}

async function persistirCadastrosBasicosDigitados() {
    const setorNome = document.getElementById('setor').value;
    const setorOption = findDatalistOption('setor_opcoes', setorNome);
    if (!setorOption && setorNome.trim()) {
        const setor = await salvarCadastroBasico('setores', setorNome);
        if (setor?.id) setValue('setor_id', setor.id);
    }

    const grupoNome = document.getElementById('grupo_embalagem').value;
    if (!findDatalistOption('grupo_embalagem_opcoes', grupoNome)) {
        await salvarCadastroBasico('tipos_embalagem', grupoNome);
    }

    const calibreNome = document.getElementById('calibre').value;
    if (!findDatalistOption('calibre_opcoes', calibreNome)) {
        await salvarCadastroBasico('faixas_calibradora', calibreNome);
    }

    const unidadeNome = document.getElementById('unidade_ref').value;
    if (!findDatalistOption('unidade_ref_opcoes', unidadeNome)) {
        await salvarCadastroBasico('unidades', unidadeNome);
    }

    const descricao = document.getElementById('descricao').value;
    if (!findDatalistOption('atividades_padrao', descricao)) {
        await salvarAtividadePadrao(descricao);
    }
}

async function excluirAtividade(id) {
    if (!id || !confirm('Deseja arquivar esta cronoanalise? Ela so sera arquivada se nao estiver vinculada a nenhum posto.')) return;
    const formData = new FormData();
    formData.append('action', 'delete_atividade');
    formData.append('id', id);
    formData.append('linha_id', '<?php echo crono_h($linha_id); ?>');
    formData.append('post_index', '<?php echo crono_h($post_index); ?>');

    const response = await fetch('api_cronoanalise.php', { method: 'POST', body: formData });
    const result = await response.json().catch(() => ({ status: 'error', message: 'Resposta invalida do servidor.' }));
    if (response.ok && result.status === 'success') {
        location.reload();
        return;
    }
    showFeedback(result.message || 'Erro ao arquivar cronoanalise.');
}

function editarAtividade(act) {
    resetForm(false);
    document.getElementById('form_title').textContent = 'Editando Cronoanalise';
    setValue('act_id', act.id || '');
    setValue('tipo_atividade', act.tipo_atividade || 'operacao');
    setValue('tipo_operacao', act.tipo_operacao || 'OPERACAO');
    setValue('setor', act.setor || '<?php echo crono_h($setor_atual); ?>');
    setValue('linha_nome', act.linha || '<?php echo crono_h($linha_atual); ?>');
    setValue('posto', act.posto || '<?php echo crono_h($posto_atual); ?>');
    setValue('grupo_embalagem', act.grupo_embalagem || '');
    setValue('variacao', act.variacao || '');
    setValue('calibre', act.calibre || '');
    setValue('chave_tecnica', act.chave_tecnica || '');
    setDescricaoAtividade(act.descricao || act.atividade || '');
    setValue('unidade_ref', act.unidade_ref || act.unidade_base || act.unidade_carga || '');
    setValue('quantidade_ref', act.quantidade_ref || act.qtd_ref || act.qtd_base || act.numero_frutos || act.num_frutos || '');
    setValue('tempo_total', act.tempo_total || act.tempo_total_s || act.tempo_s || '');
    setValue('peso_fruto_g', act.peso_fruto_g || '');
    setValue('er', act.er || act.ER || '');
    setValue('fator_ritmo', Number(act.fator_ritmo || 1).toFixed(2));
    syncProdutoCombo(act.produto_id || '');

    const base = act.tempo_base_utilizado || 'TP';
    const radio = document.querySelector(`input[name="tempo_base"][value="${base}"]`);
    if (radio) radio.checked = true;

    if (Array.isArray(act.tolerancias)) {
        document.querySelectorAll('.crono-tolerance-table tbody tr').forEach(row => {
            const saved = act.tolerancias.find(item => item.categoria === row.dataset.category);
            if (saved) {
                row.querySelector('.tol-percent').value = saved.percentual ?? 0;
                row.querySelector('.tol-active').checked = saved.ativo !== false;
            }
        });
    }

    calculate();
    document.querySelector('.crono-form-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function resetForm(scroll = true) {
    document.getElementById('form_atividade').reset();
    setValue('act_id', '');
    document.getElementById('form_title').textContent = 'Nova Cronoanalise';
    setValue('tipo_atividade', 'operacao');
    document.querySelector('input[name="tempo_base"][value="TP"]').checked = true;
    setValue('setor', '<?php echo crono_h($setor_atual); ?>');
    setValue('linha_nome', '<?php echo crono_h($linha_atual); ?>');
    setValue('posto', '<?php echo crono_h($posto_atual); ?>');
    setValue('grupo_embalagem', '');
    setValue('variacao', '');
    setValue('calibre', '');
    setValue('chave_tecnica', '');
    setDescricaoAtividade('');
    setValue('peso_fruto_g', '');
    setValue('er', '');
    document.querySelectorAll('.tol-percent').forEach(input => input.value = 0);
    document.querySelectorAll('.tol-active').forEach(input => input.checked = true);
    syncProdutoCombo('');
    calculate();
    document.getElementById('crono_feedback').hidden = true;
    if (scroll) document.querySelector('.crono-form-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function toggleCombo(btn) {
    const combo = btn.closest('.ae-combo');
    const open = combo.classList.contains('open');
    document.querySelectorAll('.ae-combo.open').forEach(el => el.classList.remove('open'));
    if (!open) {
        combo.classList.add('open');
        const search = combo.querySelector('input[type="search"]');
        search.value = '';
        filterCombo(search);
        search.focus();
    }
}

function filterCombo(input) {
    const term = input.value.toLowerCase().trim();
    input.closest('.ae-combo-panel').querySelectorAll('.ae-combo-option').forEach(option => {
        option.style.display = option.textContent.toLowerCase().includes(term) ? 'block' : 'none';
    });
}

function selectComboOption(option) {
    const combo = option.closest('.ae-combo');
    const select = document.getElementById(combo.dataset.selectId);
    select.value = option.dataset.value || '';
    combo.querySelector('.ae-combo-toggle span').textContent = option.textContent.trim();
    combo.querySelectorAll('.ae-combo-option').forEach(item => item.classList.remove('selected'));
    option.classList.add('selected');
    combo.classList.remove('open');

    if (combo.dataset.selectId === 'produto_id') {
        const unidade = option.dataset.unidade || '';
        const inputUnidade = document.getElementById('unidade_ref');
        if (unidade) inputUnidade.value = unidade;
    }
}

function syncProdutoCombo(value) {
    const combo = document.getElementById('combo_produto');
    const select = document.getElementById('produto_id');
    select.value = value || '';
    let label = '-- Selecionar Produto --';
    combo.querySelectorAll('.ae-combo-option').forEach(option => {
        option.classList.remove('selected');
        if ((option.dataset.value || '') === (value || '')) {
            option.classList.add('selected');
            label = option.textContent.trim();
            if (option.dataset.unidade) document.getElementById('unidade_ref').value = option.dataset.unidade;
        }
    });
    document.getElementById('combo_produto_label').textContent = label;
}

function syncSharedReferenceIds() {
    const setor = document.getElementById('setor');
    const linha = document.getElementById('linha_nome');
    const posto = document.getElementById('posto');
    const setorOption = setor?.tagName === 'SELECT' ? setor.options[setor.selectedIndex] : findDatalistOption('setor_opcoes', setor?.value || '');
    const linhaOption = linha?.tagName === 'SELECT' ? linha.options[linha.selectedIndex] : null;
    const postoOption = posto?.tagName === 'SELECT' ? posto.options[posto.selectedIndex] : null;

    setValue('setor_id', setorOption?.dataset.id || document.getElementById('setor_id').value || '');
    if (linhaOption) setValue('linha_ref_id', linhaOption.dataset.id || '');
    if (postoOption) setValue('posto_id', postoOption.dataset.id || '');
}

document.addEventListener('input', event => {
    if (event.target.closest('#form_atividade')) calculate();
});
document.addEventListener('change', event => {
    if (event.target.closest('#form_atividade')) {
        syncSharedReferenceIds();
        calculate();
    }
});
document.addEventListener('click', event => {
    if (!event.target.closest('.ae-combo')) {
        document.querySelectorAll('.ae-combo.open').forEach(el => el.classList.remove('open'));
    }
});

syncSharedReferenceIds();
syncDescricaoAtividade();
applyFatoresCalculoState(localStorage.getItem('crono-fatores-calculo-collapsed') === '1');
if (cronoInitialEdit) {
    editarAtividade(cronoInitialEdit);
}
calculate();
</script>

<style>
.crono-page { background: #f5f7fb; }
.crono-shell { max-width: 1540px; margin: 0 auto; }
.crono-list-card, .crono-form-card { background: #fff; border: 1px solid #dce4ef; border-radius: 8px; box-shadow: 0 2px 10px rgba(20, 35, 55, .06); }
.crono-list-card { margin-bottom: 18px; overflow: hidden; }
.crono-list-header { background: linear-gradient(90deg, #0b7bec, #0759b4); color: #fff; font-weight: 800; padding: 14px 20px; font-size: 18px; }
.crono-table-wrap { padding: 20px; overflow-x: auto; }
.crono-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
.crono-table th { background: #0b70d7; color: #fff; padding: 12px; text-align: left; }
.crono-table td { padding: 12px; border-bottom: 1px solid #edf1f7; }
.crono-empty { text-align: center; color: #78838f; font-size: 16px; }
.crono-badge { display: inline-flex; padding: 4px 8px; border-radius: 4px; background: #e8f2ff; color: #0759b4; font-weight: 700; font-size: 12px; }
.crono-actions-cell { white-space: nowrap; }
.crono-icon-btn { border: 1px solid #cbd8e8; background: #fff; color: #164b86; border-radius: 4px; padding: 6px 8px; cursor: pointer; }
.crono-icon-btn.danger { color: #b42318; border-color: #f1c2bd; }
.crono-form-card { padding: 14px; }
.crono-title-row { display: flex; justify-content: space-between; gap: 14px; align-items: center; padding: 0 4px 12px; border-bottom: 1px solid #e6edf5; }
.crono-title { display: flex; align-items: center; gap: 12px; }
.crono-title-icon { color: #0668d8; font-size: 28px; line-height: 1; }
.crono-title h1 { margin: 0; padding: 0; border: 0; color: #121a2f; font-size: 20px; font-weight: 800; }
.crono-context { color: #647084; font-size: 12px; font-weight: 700; }
.crono-header-grid { display: grid; grid-template-columns: 1fr 1.1fr 1.7fr; gap: 18px; padding: 14px 0; }
.crono-header-grid.row-one { grid-template-columns: repeat(3, minmax(220px, 1fr)); }
.crono-header-grid.row-two { grid-template-columns: repeat(3, 1fr); padding-top: 0; }
.crono-header-grid.row-description { grid-template-columns: 1fr; padding-top: 0; }
.crono-header-grid.second { grid-template-columns: 1fr .95fr .75fr .85fr 1.35fr; align-items: end; }
.crono-field label, .crono-calc-card label { display: block; margin-bottom: 6px; color: #25344d; font-size: 13px; font-weight: 800; }
.crono-field label span { color: #d92d20; }
.crono-input { width: 100%; height: 42px; border: 1px solid #cfd9e6; border-radius: 5px; background: #fff; color: #152034; padding: 8px 12px; font-size: 14px; }
.crono-input:focus { outline: 0; border-color: #0b75e5; box-shadow: 0 0 0 3px rgba(11, 117, 229, .12); }
.crono-readonly, .crono-input.mirror { background: #f5f7fa; font-weight: 800; }
.crono-inline-new { display: grid; grid-template-columns: minmax(220px, .45fr) minmax(240px, .55fr); gap: 10px; align-items: center; }
.crono-new-hidden { display: none; }
.crono-time-selector { border: 0; padding: 0; margin: 0; }
.crono-time-selector legend { color: #111827; font-size: 13px; font-weight: 900; }
.crono-time-selector p { color: #647084; font-size: 11px; margin: 2px 0 8px; }
.crono-radio-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; }
.crono-radio-card { cursor: pointer; }
.crono-radio-card input { position: absolute; opacity: 0; pointer-events: none; }
.crono-radio-card span { min-height: 60px; display: flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid #d5deea; border-radius: 6px; background: #fff; color: #142033; }
.crono-radio-card small { display: block; font-size: 11px; color: #667085; }
.crono-radio-card input:checked + span { border-color: #0b70d7; background: #eef6ff; box-shadow: inset 0 0 0 1px #0b70d7; color: #064db0; }
.crono-factors { border: 1px solid #e2e9f2; border-radius: 7px; padding: 12px; margin-top: 0; }
.crono-section-title { color: #101828; font-weight: 900; margin-bottom: 12px; }
.crono-section-title span { color: #0969da; }
.crono-section-toggle { min-height: 34px; margin: -4px 0 12px; border: 1px solid #cfd9e6; border-radius: 5px; background: #fff; color: #164b86; padding: 0 12px; font-size: 12px; font-weight: 900; cursor: pointer; }
.crono-section-toggle:hover { border-color: #0b70d7; background: #eef6ff; }
.crono-section-toggle::after { content: " v"; }
.crono-section-toggle.collapsed::after { content: " >"; }
.crono-factor-grid { display: grid; grid-template-columns: 1fr 1fr 1.75fr; gap: 16px; }
.crono-calc-card { border: 1px solid #dbe5ef; border-radius: 6px; padding: 14px; min-height: 310px; }
.crono-calc-card h2 { margin: -14px -14px 14px; padding: 10px 14px; border-bottom: 1px solid; font-size: 15px; font-weight: 900; }
.crono-calc-card.tr { background: #f8fbff; border-color: #9cc9ff; }
.crono-calc-card.tr h2 { color: #075fd0; background: #eef7ff; border-color: #9cc9ff; }
.crono-calc-card.tn { background: #fbfffc; border-color: #a8dfbd; }
.crono-calc-card.tn h2 { color: #07833f; background: #f0fbf3; border-color: #a8dfbd; }
.crono-calc-card.tp { background: #fdfbff; border-color: #c8a8f0; }
.crono-calc-card.tp h2 { color: #4c1d95; background: #faf5ff; border-color: #c8a8f0; }
.crono-inline { display: grid; grid-template-columns: 1fr 74px; gap: 10px; }
.crono-result-box { margin-top: 18px; padding: 14px; border: 1px solid currentColor; border-radius: 6px; background: rgba(255,255,255,.58); text-align: center; }
.crono-result-box small { display: block; font-size: 12px; font-weight: 900; }
.crono-result-box strong { display: block; margin-top: 5px; font-size: 26px; }
.tr .crono-result-box { color: #075fd0; }
.tn .crono-result-box { color: #07833f; }
.tp .crono-result-box { color: #4c1d95; }
.crono-formula { margin-top: 10px; text-align: center; color: #56637a; font-size: 12px; font-weight: 700; }
.crono-tolerance-title { font-weight: 800; font-size: 13px; margin-bottom: 8px; }
.crono-tolerance-title span { color: #667085; font-weight: 500; }
.crono-tolerance-wrap { overflow-x: auto; }
.crono-tolerance-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.crono-tolerance-table th, .crono-tolerance-table td { border: 1px solid #e4e9f0; padding: 6px 8px; text-align: left; }
.crono-tolerance-table th { background: #f8fafc; color: #1f2937; }
.crono-percent-input { width: 110px; height: 28px; display: inline-flex; align-items: center; border: 1px solid #d6dfeb; border-radius: 4px; background: #fff; overflow: hidden; }
.crono-percent-input input[type="number"] { width: 100%; height: 100%; min-width: 0; border: 0; border-radius: 0; padding: 4px 2px 4px 8px; background: transparent; text-align: right; }
.crono-percent-input input[type="number"]:focus { outline: 0; box-shadow: none; }
.crono-percent-input span { flex: 0 0 auto; padding: 0 8px 0 2px; color: #334155; font-weight: 800; }
.crono-switch input { display: none; }
.crono-switch span { width: 34px; height: 18px; display: inline-block; border-radius: 999px; background: #c7d1df; position: relative; cursor: pointer; }
.crono-switch span::after { content: ""; position: absolute; width: 14px; height: 14px; top: 2px; left: 2px; border-radius: 50%; background: #fff; transition: .2s; }
.crono-switch input:checked + span { background: #0b70d7; }
.crono-switch input:checked + span::after { left: 18px; }
.crono-tp-summary { margin-top: 0; border: 1px solid #dacbf1; border-bottom: 0; background: rgba(250,245,255,.75); }
.crono-tp-summary div { display: flex; justify-content: space-between; gap: 12px; padding: 7px 10px; border-bottom: 1px solid #dacbf1; color: #4c1d95; font-weight: 800; }
.crono-kpis { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; border: 1px solid #e2e9f2; border-radius: 7px; padding: 12px; margin-top: 12px; }
.crono-kpi { border: 1px solid currentColor; border-radius: 6px; padding: 8px; text-align: center; background: #fff; }
.crono-kpi span { display: block; font-size: 12px; font-weight: 900; line-height: 1.25; }
.crono-kpi strong { display: block; font-size: 22px; line-height: 1.2; margin-top: 2px; }
.crono-kpi.tr { color: #075fd0; background: #f0f7ff; }
.crono-kpi.tn { color: #07833f; background: #f0fbf3; }
.crono-kpi.tol, .crono-kpi.tp { color: #4c1d95; background: #faf5ff; }
.crono-kpi.factor { color: #c2410c; background: #fff7ed; }
.crono-memory { margin-top: 10px; border: 1px solid #f3cd74; border-radius: 7px; background: #fff8e5; padding: 12px; color: #7a4a00; }
.crono-memory h2 { margin: 0 0 8px; font-size: 14px; color: #7a4a00; }
.crono-memory-grid { display: grid; grid-template-columns: 1fr 28px 1fr 28px 1fr; gap: 12px; align-items: center; text-align: center; color: #111827; }
.crono-memory-grid strong, .crono-memory-grid span { display: block; font-size: 12px; }
.crono-memory-grid b { color: #7a4a00; font-size: 22px; }
.crono-footer { display: flex; gap: 10px; margin-top: 10px; align-items: center; }
.crono-btn { min-height: 40px; border: 0; border-radius: 5px; padding: 0 20px; color: #fff; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; cursor: pointer; }
.crono-btn.save { background: #22a846; min-width: 190px; }
.crono-btn.clear { background: #6c7682; }
.crono-btn.back { background: #4b5563; }
.crono-feedback { margin-top: 10px; padding: 10px 12px; border-radius: 5px; font-weight: 800; }
.crono-feedback.error { color: #842029; background: #f8d7da; border: 1px solid #f1aeb5; }
.crono-feedback.success { color: #0f5132; background: #d1e7dd; border: 1px solid #a3cfbb; }
.ae-native-select-hidden { display: none !important; }
.ae-combo { position: relative; }
.ae-combo-toggle { width: 100%; min-height: 42px; display: flex; align-items: center; justify-content: space-between; gap: 10px; border: 1px solid #cfd9e6; border-radius: 5px; background: #fff; padding: 8px 12px; cursor: pointer; text-align: left; font-size: 14px; }
.ae-combo-panel { display: none; position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; right: 0; padding: 10px; background: #fff; border: 1px solid #d8e1ec; border-radius: 6px; box-shadow: 0 10px 24px rgba(15, 23, 42, .16); }
.ae-combo.open .ae-combo-panel { display: block; }
.ae-combo-panel input[type="search"] { width: 100%; height: 36px; border: 1px solid #d5deea; border-radius: 4px; padding: 7px 9px; }
.ae-combo-options { max-height: 220px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf1f7; border-radius: 4px; }
.ae-combo-option { padding: 8px 10px; cursor: pointer; border-bottom: 1px solid #edf1f7; }
.ae-combo-option:hover, .ae-combo-option.selected { background: #eef6ff; color: #064db0; font-weight: 800; }
@media (max-width: 1180px) {
    .crono-header-grid, .crono-header-grid.second, .crono-factor-grid, .crono-kpis { grid-template-columns: 1fr; }
    .crono-memory-grid { grid-template-columns: 1fr; }
    .crono-memory-grid b { transform: rotate(90deg); }
}
</style>

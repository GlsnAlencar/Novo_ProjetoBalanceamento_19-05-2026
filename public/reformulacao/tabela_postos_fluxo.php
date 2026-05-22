<?php
require_once __DIR__ . '/module_routes.php';
require_once __DIR__ . '/safe_storage.php';
require_once rf_route_path('arvore_estrutura', 'api');

function tpf_default_data() {
    return ['setores' => []];
}

function tpf_data_path() {
    return rf_route('editor_bpm', 'storage');
}

function tpf_load_data() {
    return cod_12_05_safe_load_json(
        tpf_data_path(),
        'tpf_default_data',
        fn($candidate) => isset($candidate['setores']) && is_array($candidate['setores'])
    );
}

function tpf_kg_por_ctt() {
    try {
        $data = ae_api_load_data();
    } catch (Throwable $e) {
        return null;
    }

    foreach (($data['tabela_item_conversoes'] ?? []) as $row) {
        $destino = strtolower(trim((string)($row['unidade_destino'] ?? '')));
        $descricao = strtolower((string)($row['descricao'] ?? ''));
        $fator = is_numeric($row['fator'] ?? null) ? (float)$row['fator'] : 0.0;
        if ((int)($row['ativo'] ?? 1) === 1 && $destino === 'kg' && $fator > 0 && str_contains($descricao, 'ctt')) {
            return $fator;
        }
    }

    return null;
}

function tpf_node_count($linha) {
    $nodes = $linha['drawflow_data']['drawflow']['Home']['data'] ?? [];
    return is_array($nodes) ? count($nodes) : 0;
}

function tpf_extract_postos($linha) {
    $rows = [];
    $nodes = $linha['drawflow_data']['drawflow']['Home']['data'] ?? [];
    if (!is_array($nodes)) {
        return $rows;
    }

    $nodeTypes = [];
    foreach ($nodes as $drawflowId => $node) {
        $data = $node['data'] ?? [];
        $nodeTypes[(string)$drawflowId] = $data['type'] ?? ($node['class'] ?? ($node['name'] ?? 'node'));
    }

    foreach ($nodes as $drawflowId => $node) {
        $data = $node['data'] ?? [];
        $type = $data['type'] ?? ($node['class'] ?? ($node['name'] ?? 'node'));
        if ($type !== 'node') {
            continue;
        }

        $rows[] = [
            'id' => (string)($data['id'] ?? ('node_' . $drawflowId)),
            'name' => (string)($data['name'] ?? ($node['name'] ?? 'Posto sem nome')),
            'type' => 'node',
            'tc' => (float)($data['tc'] ?? 0),
            'tcContentor' => (float)($data['tcContentor'] ?? ($data['tc_contentor'] ?? 0)),
            'tc_contentor' => (float)($data['tc_contentor'] ?? ($data['tcContentor'] ?? 0)),
            'pessoas' => (int)($data['pessoas'] ?? 0),
            'ritmo' => (float)($data['ritmo'] ?? 0),
            'atividades' => is_array($data['atividades'] ?? null) ? $data['atividades'] : [],
            'drawflow_id' => (int)$drawflowId,
            '_sequence' => tpf_sequence_index($nodes, $nodeTypes, (string)$drawflowId),
            '_pos_x' => (float)($node['pos_x'] ?? 0),
            '_pos_y' => (float)($node['pos_y'] ?? 0),
        ];
    }

    usort($rows, function($a, $b) {
        return [$a['_sequence'], $a['_pos_x'], $a['_pos_y'], $a['drawflow_id']] <=> [$b['_sequence'], $b['_pos_x'], $b['_pos_y'], $b['drawflow_id']];
    });

    foreach ($rows as $idx => $row) {
        $rows[$idx]['sequencia'] = $idx + 1;
        unset($rows[$idx]['_sequence'], $rows[$idx]['_pos_x'], $rows[$idx]['_pos_y']);
    }
    return $rows;
}

function tpf_sequence_index($nodes, $nodeTypes, $startId) {
    $incoming = [];
    $outgoing = [];
    foreach ($nodes as $sourceId => $node) {
        foreach (($node['outputs'] ?? []) as $output) {
            foreach (($output['connections'] ?? []) as $conn) {
                $targetId = (string)($conn['node'] ?? '');
                if ($targetId === '') {
                    continue;
                }
                $outgoing[(string)$sourceId][] = $targetId;
                $incoming[$targetId][] = (string)$sourceId;
            }
        }
    }

    $roots = [];
    foreach ($nodes as $id => $node) {
        if (empty($incoming[(string)$id])) {
            $roots[] = (string)$id;
        }
    }
    usort($roots, function($a, $b) use ($nodes) {
        return [(float)($nodes[$a]['pos_x'] ?? 0), (float)($nodes[$a]['pos_y'] ?? 0), (int)$a] <=> [(float)($nodes[$b]['pos_x'] ?? 0), (float)($nodes[$b]['pos_y'] ?? 0), (int)$b];
    });

    $visited = [];
    $order = [];
    $queue = $roots ?: array_map('strval', array_keys($nodes));
    while (!empty($queue)) {
        $current = array_shift($queue);
        if (isset($visited[$current])) {
            continue;
        }
        $visited[$current] = true;
        if (($nodeTypes[$current] ?? 'node') === 'node') {
            $order[$current] = count($order) + 1;
        }
        $next = $outgoing[$current] ?? [];
        usort($next, function($a, $b) use ($nodes) {
            return [(float)($nodes[$a]['pos_x'] ?? 0), (float)($nodes[$a]['pos_y'] ?? 0), (int)$a] <=> [(float)($nodes[$b]['pos_x'] ?? 0), (float)($nodes[$b]['pos_y'] ?? 0), (int)$b];
        });
        foreach ($next as $target) {
            if (!isset($visited[$target])) {
                $queue[] = $target;
            }
        }
    }

    return $order[$startId] ?? (100000 + (int)$startId);
}

function tpf_first_line($data) {
    foreach (($data['setores'] ?? []) as $setor) {
        foreach (($setor['linhas'] ?? []) as $linha) {
            return [$setor['id'] ?? '', $linha['id'] ?? ''];
        }
    }
    return ['', ''];
}

function tpf_find_selection($data, $setorId, $linhaId) {
    foreach (($data['setores'] ?? []) as $setor) {
        if (($setor['id'] ?? '') !== $setorId) {
            continue;
        }
        foreach (($setor['linhas'] ?? []) as $linha) {
            if (($linha['id'] ?? '') === $linhaId) {
                return [$setor, $linha];
            }
        }
    }
    return [null, null];
}

$data = tpf_load_data();
$setorId = (string)($_GET['setor_id'] ?? '');
$linhaId = (string)($_GET['linha_id'] ?? '');
if ($setorId === '' || $linhaId === '') {
    [$setorId, $linhaId] = tpf_first_line($data);
}
[$setorSelecionado, $linhaSelecionada] = tpf_find_selection($data, $setorId, $linhaId);
$postosSelecionados = $linhaSelecionada ? tpf_extract_postos($linhaSelecionada) : [];
$balanceamentoParams = $linhaSelecionada['drawflow_data']['balanceamento_params'] ?? [];
$kgPorCtt = tpf_kg_por_ctt();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabela de postos</title>
    <script src="balanceamentoService.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sidebar-width: 270px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #f4f7fb;
            color: #17202a;
            font-family: Arial, sans-serif;
        }
        .cod-app-shell {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 22px;
            transition: margin-left .25s ease;
        }
        .menu-toggle-btn {
            position: fixed;
            top: 8px;
            left: calc(var(--sidebar-width, 270px) + 12px);
            z-index: 1200;
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 4px;
            background: #17202a;
            color: #fff;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,.22);
            transition: left .25s ease, background .2s ease;
        }
        .menu-toggle-btn:hover { background: #1f6feb; }
        .sidebar {
            transition: transform .25s ease;
        }
        body.cod-menu-collapsed .sidebar {
            transform: translateX(calc(-1 * var(--sidebar-width, 270px)));
        }
        body.cod-menu-collapsed .cod-app-shell {
            margin-left: 0;
        }
        body.cod-menu-collapsed .menu-toggle-btn {
            left: 12px;
        }
        .page-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }
        .page-head h1 {
            margin: 0;
            font-size: 24px;
            color: #17202a;
        }
        .page-head p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 13px;
        }
        .layout {
            display: grid;
            grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
            gap: 16px;
        }
        .flow-list,
        .table-panel {
            background: #fff;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .06);
        }
        .flow-list {
            align-self: start;
            max-height: calc(100vh - 120px);
            overflow: auto;
            padding: 12px;
        }
        .sector-group + .sector-group {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }
        .sector-title {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin: 0 0 8px;
            padding: 7px 8px;
            border: 1px solid #dbe3ef;
            border-radius: 6px;
            background: #fff;
            color: #334155;
            font-size: 12px;
            font-weight: 900;
            text-align: left;
            text-transform: uppercase;
            cursor: pointer;
        }
        .sector-title::after {
            content: "v";
            flex: 0 0 auto;
            color: #64748b;
            font-size: 11px;
            transition: transform .16s ease;
        }
        .sector-group.collapsed .sector-title::after {
            transform: rotate(-90deg);
        }
        .sector-flows {
            display: block;
        }
        .sector-group.collapsed .sector-flows {
            display: none;
        }
        .flow-link {
            display: block;
            margin-bottom: 7px;
            padding: 9px 10px;
            border: 1px solid #dbe3ef;
            border-radius: 6px;
            color: #17202a;
            text-decoration: none;
            background: #f8fafc;
        }
        .flow-link:hover {
            border-color: #1f6feb;
            background: #eef6ff;
        }
        .flow-link.active {
            border-color: #1f6feb;
            background: #e7f0fb;
            color: #174a7c;
            font-weight: 800;
        }
        .flow-link small {
            display: block;
            margin-top: 3px;
            color: #64748b;
            font-size: 11px;
        }
        .table-panel {
            min-width: 0;
            padding: 16px;
        }
        .selected-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }
        .selected-title h2 {
            margin: 0;
            font-size: 18px;
        }
        .selected-title a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 10px;
            border-radius: 6px;
            background: #1f6feb;
            color: #fff;
            text-decoration: none;
            font-size: 12px;
            font-weight: 800;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 14px;
        }
        .metric {
            min-width: 0;
            padding: 9px;
            border: 1px solid #dbe3ef;
            border-radius: 6px;
            background: #f8fafc;
        }
        .metric span {
            display: block;
            color: #64748b;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .metric strong {
            display: block;
            margin-top: 4px;
            overflow: hidden;
            color: #17202a;
            font-size: 13px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .table-scroll { overflow-x: auto; }
        table {
            width: 100%;
            min-width: 1080px;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            padding: 8px 7px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }
        th {
            color: #475569;
            font-size: 10px;
            text-transform: uppercase;
            background: #f8fafc;
        }
        .sequence-cell {
            width: 44px;
            color: #64748b;
            font-weight: 900;
            text-align: center;
        }
        .posto-name { font-weight: 900; min-width: 150px; }
        .activities { color: #475569; line-height: 1.35; }
        .badge {
            display: inline-flex;
            min-height: 22px;
            align-items: center;
            padding: 3px 7px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 900;
            white-space: nowrap;
        }
        .badge.sem_dados { background: #e5e7eb; color: #374151; }
        .badge.balanceado { background: #dcfce7; color: #166534; }
        .badge.atencao { background: #fef3c7; color: #92400e; }
        .badge.gargalo { background: #fee2e2; color: #991b1b; }
        .badge.ocioso { background: #e0f2fe; color: #075985; }
        .empty {
            padding: 24px;
            color: #64748b;
            text-align: center;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
        }
        @media (max-width: 1100px) {
            .layout { grid-template-columns: 1fr; }
            .flow-list { max-height: none; }
            .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/menu.php'; ?>
<button id="menuToggleBtn" class="menu-toggle-btn" type="button" title="Recolher menu" aria-label="Recolher menu">
    <i class="fa-solid fa-bars"></i>
</button>
<main class="cod-app-shell">
    <div class="page-head">
        <div>
            <h1>Tabela de postos</h1>
            <p>Consulta por setor e fluxo usando os mesmos dados da tabela do Fluxo do processo.</p>
        </div>
    </div>

    <div class="layout">
        <aside class="flow-list" aria-label="Fluxos por setor">
            <?php if (empty($data['setores'])): ?>
                <div class="empty">Nenhum setor cadastrado no Fluxo.</div>
            <?php else: ?>
                <?php foreach ($data['setores'] as $setor): ?>
                    <?php $setorAtivo = ($setor['id'] ?? '') === $setorId; ?>
                    <section class="sector-group <?php echo $setorAtivo ? '' : 'collapsed'; ?>">
                        <button class="sector-title" type="button" aria-expanded="<?php echo $setorAtivo ? 'true' : 'false'; ?>" onclick="toggleSectorGroup(this)">
                            <span><?php echo htmlspecialchars($setor['nome'] ?? 'Setor sem nome'); ?></span>
                        </button>
                        <div class="sector-flows">
                            <?php if (empty($setor['linhas'])): ?>
                                <div class="flow-link">Sem fluxos cadastrados</div>
                            <?php endif; ?>
                            <?php foreach (($setor['linhas'] ?? []) as $linha): ?>
                                <?php
                                $isActive = ($setor['id'] ?? '') === $setorId && ($linha['id'] ?? '') === $linhaId;
                                $href = 'tabela_postos_fluxo.php?setor_id=' . rawurlencode((string)($setor['id'] ?? '')) . '&linha_id=' . rawurlencode((string)($linha['id'] ?? ''));
                                ?>
                                <a class="flow-link <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($href); ?>">
                                    <?php echo htmlspecialchars($linha['nome'] ?? 'Fluxo sem nome'); ?>
                                    <small><?php echo tpf_node_count($linha); ?> nós no fluxo</small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </aside>

        <section class="table-panel">
            <?php if (!$setorSelecionado || !$linhaSelecionada): ?>
                <div class="empty">Selecione um fluxo na lista para consultar a tabela de postos.</div>
            <?php else: ?>
                <div class="selected-title">
                    <div>
                        <h2><?php echo htmlspecialchars(($setorSelecionado['nome'] ?? 'Setor') . ' / ' . ($linhaSelecionada['nome'] ?? 'Fluxo')); ?></h2>
                    </div>
                    <a href="fluxo.php?setor_id=<?php echo rawurlencode($setorId); ?>&linha_id=<?php echo rawurlencode($linhaId); ?>">
                        <i class="fa-solid fa-diagram-project"></i> Abrir fluxo
                    </a>
                </div>
                <div id="tpfConteudo"></div>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
    const postosFluxo = <?php echo json_encode($postosSelecionados, JSON_UNESCAPED_UNICODE); ?>;
    const balanceamentoParams = <?php echo json_encode($balanceamentoParams, JSON_UNESCAPED_UNICODE); ?>;
    const kgPorCtt = <?php echo json_encode($kgPorCtt); ?>;

    function parseLocalNumber(value, fallback = 0) {
        const raw = String(value ?? '').trim();
        const normalized = raw.includes(',') ? raw.replace(/\./g, '').replace(',', '.') : raw;
        const parsed = parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function formatSeconds(value) {
        const num = parseLocalNumber(value, 0);
        return num > 0 ? num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + 's' : '0,00s';
    }

    function formatNumber(value) {
        const num = parseLocalNumber(value, 0);
        return num > 0 ? num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0,00';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function atividadesHtml(atividades) {
        if (!Array.isArray(atividades) || atividades.length === 0) return '-';
        return atividades.map(item => {
            const nome = item.nome || item.atividade || item.descricao || '-';
            const tipo = item.tipo || item.tipo_operacao || item.tipo_atividade || '';
            return `${escapeHtml(nome)}${tipo ? ' / ' + escapeHtml(tipo) : ''}`;
        }).join('<br>');
    }

    function deltaLabel(row) {
        if (!row || row.status === 'sem_dados' || !row.takt || !row.ritmo) return '-';
        if (row.sobrecarga) return '+' + formatSeconds(row.sobrecarga);
        if (row.ociosidade) return '-' + formatSeconds(row.ociosidade);
        return '0,00s';
    }

    function renderTabelaPostos() {
        const target = document.getElementById('tpfConteudo');
        if (!target) return;

        if (!postosFluxo.length) {
            target.innerHTML = '<div class="empty">Este fluxo ainda não possui postos para listar.</div>';
            return;
        }

        const snapshot = BalanceamentoService.analyze({
            postos: postosFluxo,
            params: balanceamentoParams,
            kgPorCtt
        });
        const resumo = snapshot.resumo || {};
        const rows = postosFluxo.map(posto => {
            const analisado = (snapshot.postos || []).find(row => String(row.id) === String(posto.id)) || {};
            return `
                <tr>
                    <td class="sequence-cell">${escapeHtml(posto.sequencia || '')}</td>
                    <td class="posto-name">${escapeHtml(posto.name)}</td>
                    <td>${escapeHtml(posto.pessoas || 1)}</td>
                    <td>${escapeHtml(formatSeconds(posto.tc || 0))}</td>
                    <td>${escapeHtml(formatSeconds(posto.tcContentor || posto.tc_contentor || posto.ritmo || posto.tc || 0))}</td>
                    <td>${escapeHtml(formatSeconds(posto.ritmo || 0))}</td>
                    <td>${analisado.takt ? escapeHtml(formatSeconds(analisado.takt)) : '-'}</td>
                    <td>${analisado.capacidadeHora ? escapeHtml(formatNumber(analisado.capacidadeHora)) : '-'}</td>
                    <td>${analisado.capacidadeKgHora ? escapeHtml(formatNumber(analisado.capacidadeKgHora)) : '-'}</td>
                    <td><span class="badge ${escapeHtml(analisado.status || 'sem_dados')}">${escapeHtml(analisado.statusLabel || 'Sem dados')}</span></td>
                    <td>${escapeHtml(deltaLabel(analisado))}</td>
                    <td class="activities">${atividadesHtml(posto.atividades)}</td>
                </tr>
            `;
        }).join('');

        target.innerHTML = `
            <div class="summary-grid">
                <div class="metric"><span>Takt time</span><strong>${resumo.takt ? escapeHtml(formatSeconds(resumo.takt)) : '-'}</strong></div>
                <div class="metric"><span>Gargalo atual</span><strong title="${escapeHtml(resumo.gargalo || '-')}">${escapeHtml(resumo.gargalo || '-')}</strong></div>
                <div class="metric"><span>Capacidade estimada CTT/h</span><strong>${resumo.capacidadeLinhaHora ? escapeHtml(formatNumber(resumo.capacidadeLinhaHora)) : '-'}</strong></div>
                <div class="metric"><span>Capacidade estimada Kg/h</span><strong>${resumo.capacidadeLinhaKgHora ? escapeHtml(formatNumber(resumo.capacidadeLinhaKgHora)) : '-'}</strong></div>
                <div class="metric"><span>Eficiência estimada</span><strong>${resumo.eficienciaEstimativa ? escapeHtml(formatNumber(resumo.eficienciaEstimativa)) + '%' : '-'}</strong></div>
                <div class="metric"><span>Postos analisados</span><strong>${escapeHtml(resumo.postosAnalisados || 0)}</strong></div>
                <div class="metric"><span>Postos sem dados</span><strong>${escapeHtml(resumo.postosSemDados || 0)}</strong></div>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Seq.</th>
                            <th>Posto</th>
                            <th>Pessoas</th>
                            <th>TC</th>
                            <th>TC/ctt</th>
                            <th>Ritmo</th>
                            <th>Takt</th>
                            <th>Capacidade CTT/h</th>
                            <th>Capacidade Kg/h</th>
                            <th>Status</th>
                            <th>Ociosidade/Sobrecarga</th>
                            <th>Atividades</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    }

    function toggleSectorGroup(button) {
        const group = button.closest('.sector-group');
        if (!group) return;
        const collapsed = !group.classList.contains('collapsed');
        group.classList.toggle('collapsed', collapsed);
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    function setupRetractableMenu() {
        const storageKey = 'cod_12_05_menu_collapsed';
        const button = document.getElementById('menuToggleBtn');
        const icon = button ? button.querySelector('i') : null;

        function applyState(collapsed) {
            document.body.classList.toggle('cod-menu-collapsed', collapsed);
            if (button) {
                button.title = collapsed ? 'Expandir menu' : 'Recolher menu';
                button.setAttribute('aria-label', button.title);
            }
            if (icon) {
                icon.className = collapsed ? 'fa-solid fa-chevron-right' : 'fa-solid fa-bars';
            }
        }

        applyState(localStorage.getItem(storageKey) === '1');

        if (button) {
            button.addEventListener('click', function() {
                const collapsed = !document.body.classList.contains('cod-menu-collapsed');
                localStorage.setItem(storageKey, collapsed ? '1' : '0');
                applyState(collapsed);
            });
        }
    }

    setupRetractableMenu();
    renderTabelaPostos();
</script>
</body>
</html>

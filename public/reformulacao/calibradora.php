<?php
/**
 * Tela isolada cod_12_05 - Calibradora de fruta.
 *
 * Persistencia independente:
 *   data/reformulacao/calibradora.json
 */
require_once __DIR__ . '/safe_storage.php';

function cod_12_05_calibradora_data_path() {
    $dir = __DIR__ . '/../../data/reformulacao';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . '/calibradora.json';
}

function cod_12_05_calibradora_default_rows() {
    return [
        ['descricao' => 'REFUGO', 'faixa_min' => 50, 'faixa_max' => 150, 'saida' => '1', 'embalagem' => 'Refugo', 'processo' => 'Descarte', 'posto' => 'Refugo', 'gramas' => 65639, 'capacidade_pessoa_kg_h' => 180, 'pessoas_alocadas' => 1],
        ['descricao' => '14 (DESCARTE POLPA BIRITA)', 'faixa_min' => 150, 'faixa_max' => 270, 'saida' => '2', 'embalagem' => 'Polpa', 'processo' => 'Descarte polpa', 'posto' => 'Polpa', 'gramas' => 172664, 'capacidade_pessoa_kg_h' => 180, 'pessoas_alocadas' => 1],
        ['descricao' => '12', 'faixa_min' => 270, 'faixa_max' => 385, 'saida' => '3', 'embalagem' => 'Caixa 12', 'processo' => 'Embalagem', 'posto' => 'Posto 12', 'gramas' => 2069841, 'capacidade_pessoa_kg_h' => 220, 'pessoas_alocadas' => 2],
        ['descricao' => '10', 'faixa_min' => 385, 'faixa_max' => 445, 'saida' => '4', 'embalagem' => 'Caixa 10', 'processo' => 'Embalagem', 'posto' => 'Posto 10', 'gramas' => 3273654, 'capacidade_pessoa_kg_h' => 220, 'pessoas_alocadas' => 3],
        ['descricao' => '09', 'faixa_min' => 445, 'faixa_max' => 495, 'saida' => '5', 'embalagem' => 'Caixa 09', 'processo' => 'Embalagem', 'posto' => 'Posto 09', 'gramas' => 3214806, 'capacidade_pessoa_kg_h' => 220, 'pessoas_alocadas' => 3],
        ['descricao' => '08', 'faixa_min' => 495, 'faixa_max' => 560, 'saida' => '6', 'embalagem' => 'Caixa 08', 'processo' => 'Embalagem', 'posto' => 'Posto 08', 'gramas' => 3199618, 'capacidade_pessoa_kg_h' => 220, 'pessoas_alocadas' => 3],
        ['descricao' => '07', 'faixa_min' => 560, 'faixa_max' => 650, 'saida' => '7', 'embalagem' => 'Caixa 07', 'processo' => 'Embalagem', 'posto' => 'Posto 07', 'gramas' => 1922020, 'capacidade_pessoa_kg_h' => 220, 'pessoas_alocadas' => 2],
        ['descricao' => '06', 'faixa_min' => 650, 'faixa_max' => 770, 'saida' => '8', 'embalagem' => 'Caixa 06', 'processo' => 'Embalagem', 'posto' => 'Posto 06', 'gramas' => 750031, 'capacidade_pessoa_kg_h' => 220, 'pessoas_alocadas' => 1],
        ['descricao' => '05 (EMBALAGEM CALIBRE 8 / 6KG M.I)', 'faixa_min' => 770, 'faixa_max' => 950, 'saida' => '9', 'embalagem' => '6 kg M.I', 'processo' => 'Embalagem especial', 'posto' => 'Posto 05', 'gramas' => 364860, 'capacidade_pessoa_kg_h' => 180, 'pessoas_alocadas' => 1],
        ['descricao' => 'REFUGO', 'faixa_min' => 950, 'faixa_max' => 7800, 'saida' => '10', 'embalagem' => 'Refugo grande', 'processo' => 'Descarte', 'posto' => 'Refugo', 'gramas' => 118458, 'capacidade_pessoa_kg_h' => 180, 'pessoas_alocadas' => 1],
    ];
}

function cod_12_05_calibradora_default_data() {
    return [
        'version' => 1,
        'updated_at' => date('Y-m-d H:i:s'),
        'partida' => [
            'nome' => 'A NG TESTE (EXP)',
            'fruta' => 'Manga',
            'programa' => 'MANGO PALMER',
            'data_partida' => date('Y-m-d'),
            'duracao_minutos' => 60,
            'observacao' => 'Tabela inicial baseada na tela da calibradora.'
        ],
        'linhas' => cod_12_05_calibradora_default_rows()
    ];
}

function cod_12_05_calibradora_load_data() {
    $path = cod_12_05_calibradora_data_path();
    return cod_12_05_safe_load_json(
        $path,
        'cod_12_05_calibradora_default_data',
        fn($candidate) => isset($candidate['linhas']) && is_array($candidate['linhas'])
    );
}

function cod_12_05_calibradora_save_data($data) {
    $data['updated_at'] = date('Y-m-d H:i:s');
    cod_12_05_safe_write_json(cod_12_05_calibradora_data_path(), $data);
}

function cod_12_05_number($value, $default = 0) {
    if (is_string($value)) {
        $value = str_replace(',', '.', $value);
    }
    return is_numeric($value) ? (float)$value : $default;
}

function cod_12_05_int($value, $default = 0) {
    return is_numeric($value) ? (int)$value : $default;
}

$data = cod_12_05_calibradora_load_data();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'reset') {
        $data = cod_12_05_calibradora_default_data();
        cod_12_05_calibradora_save_data($data);
        header('Location: calibradora.php?reset=1');
        exit;
    }

    $partida = [
        'nome' => trim($_POST['partida_nome'] ?? ''),
        'fruta' => trim($_POST['fruta'] ?? ''),
        'programa' => trim($_POST['programa'] ?? ''),
        'data_partida' => trim($_POST['data_partida'] ?? ''),
        'duracao_minutos' => max(1, cod_12_05_int($_POST['duracao_minutos'] ?? 60, 60)),
        'observacao' => trim($_POST['observacao'] ?? '')
    ];

    $rows = [];
    $descricoes = $_POST['descricao'] ?? [];
    foreach ($descricoes as $idx => $descricao) {
        $descricao = trim($descricao);
        $faixa_min = cod_12_05_number($_POST['faixa_min'][$idx] ?? 0);
        $faixa_max = cod_12_05_number($_POST['faixa_max'][$idx] ?? 0);
        $saida = trim($_POST['saida'][$idx] ?? '');
        $embalagem = trim($_POST['embalagem'][$idx] ?? '');
        $processo = trim($_POST['processo'][$idx] ?? '');
        $posto = trim($_POST['posto'][$idx] ?? '');
        $gramas = max(0, cod_12_05_number($_POST['gramas'][$idx] ?? 0));
        $capacidade = max(1, cod_12_05_number($_POST['capacidade_pessoa_kg_h'][$idx] ?? 1));
        $pessoas = max(0, cod_12_05_int($_POST['pessoas_alocadas'][$idx] ?? 0));

        if ($descricao === '' && $gramas <= 0 && $saida === '') {
            continue;
        }

        $rows[] = [
            'descricao' => $descricao,
            'faixa_min' => $faixa_min,
            'faixa_max' => $faixa_max,
            'saida' => $saida,
            'embalagem' => $embalagem,
            'processo' => $processo,
            'posto' => $posto,
            'gramas' => $gramas,
            'capacidade_pessoa_kg_h' => $capacidade,
            'pessoas_alocadas' => $pessoas
        ];
    }

    $extra_rows = max(0, cod_12_05_int($_POST['extra_rows'] ?? 0));
    for ($i = 0; $i < $extra_rows; $i++) {
        $rows[] = [
            'descricao' => '',
            'faixa_min' => 0,
            'faixa_max' => 0,
            'saida' => '',
            'embalagem' => '',
            'processo' => '',
            'posto' => '',
            'gramas' => 0,
            'capacidade_pessoa_kg_h' => 180,
            'pessoas_alocadas' => 0
        ];
    }

    $data = [
        'version' => 1,
        'updated_at' => date('Y-m-d H:i:s'),
        'partida' => $partida,
        'linhas' => $rows
    ];
    cod_12_05_calibradora_save_data($data);
    header('Location: calibradora.php?sucesso=1');
    exit;
}

$partida = $data['partida'] ?? [];
$linhas = $data['linhas'] ?? [];
$duracao_horas = max(1, cod_12_05_number($partida['duracao_minutos'] ?? 60, 60)) / 60;
$total_gramas = array_sum(array_map(fn($row) => cod_12_05_number($row['gramas'] ?? 0), $linhas));
$total_kg = $total_gramas / 1000;
$total_pessoas_necessarias = 0;
$total_pessoas_alocadas = 0;
$gargalos = 0;
$postos_resumo = [];

foreach ($linhas as $row) {
    $kg = cod_12_05_number($row['gramas'] ?? 0) / 1000;
    $carga_kg_h = $kg / $duracao_horas;
    $capacidade = max(1, cod_12_05_number($row['capacidade_pessoa_kg_h'] ?? 1));
    $pessoas_necessarias = (int)ceil($carga_kg_h / $capacidade);
    $pessoas_alocadas = max(0, cod_12_05_int($row['pessoas_alocadas'] ?? 0));
    $capacidade_total = $pessoas_alocadas * $capacidade;
    $is_gargalo = $pessoas_alocadas > 0 && $carga_kg_h > $capacidade_total;

    $total_pessoas_necessarias += $pessoas_necessarias;
    $total_pessoas_alocadas += $pessoas_alocadas;
    if ($is_gargalo) {
        $gargalos++;
    }

    $posto = trim($row['posto'] ?? '') ?: 'Sem posto';
    if (!isset($postos_resumo[$posto])) {
        $postos_resumo[$posto] = ['kg' => 0, 'carga_kg_h' => 0, 'necessarias' => 0, 'alocadas' => 0, 'gargalos' => 0];
    }
    $postos_resumo[$posto]['kg'] += $kg;
    $postos_resumo[$posto]['carga_kg_h'] += $carga_kg_h;
    $postos_resumo[$posto]['necessarias'] += $pessoas_necessarias;
    $postos_resumo[$posto]['alocadas'] += $pessoas_alocadas;
    $postos_resumo[$posto]['gargalos'] += $is_gargalo ? 1 : 0;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>cod_12_05_calibradora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #edf1f5;
            color: #17202a;
            font-family: Arial, sans-serif;
        }
        .cod-app-shell {
            min-height: 100vh;
            margin-left: var(--sidebar-width, 270px);
            transition: margin-left .25s ease;
        }
        .menu-toggle-btn {
            position: fixed;
            top: 12px;
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
        .sidebar { transition: transform .25s ease; }
        body.cod-menu-collapsed .sidebar { transform: translateX(calc(-1 * var(--sidebar-width, 270px))); }
        body.cod-menu-collapsed .cod-app-shell { margin-left: 0; }
        body.cod-menu-collapsed .menu-toggle-btn { left: 12px; }
        .top-shell {
            padding: 18px 22px 14px 66px;
            background: #17202a;
            color: #fff;
            border-bottom: 1px solid #2c3e50;
        }
        .top-shell h1 {
            margin: 0 0 4px;
            font-size: 20px;
        }
        .top-shell p {
            margin: 0;
            color: #b8c4cf;
            font-size: 13px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(150px, 1fr));
            gap: 10px;
            padding: 14px 18px;
            background: #fff;
            border-bottom: 1px solid #d5dde6;
        }
        .metric {
            padding: 12px;
            border: 1px solid #d5dde6;
            border-radius: 6px;
            background: #f8fafc;
        }
        .metric span {
            display: block;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .metric strong {
            display: block;
            margin-top: 4px;
            font-size: 20px;
        }
        .content-area {
            padding: 18px;
        }
        .panel {
            margin-bottom: 16px;
            border: 1px solid #d5dde6;
            border-radius: 6px;
            background: #fff;
            overflow: hidden;
        }
        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-bottom: 1px solid #d5dde6;
            background: #f8fafc;
        }
        .panel-header h2 {
            margin: 0;
            font-size: 15px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(150px, 1fr));
            gap: 12px;
            padding: 14px;
        }
        label {
            display: grid;
            gap: 4px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }
        input, textarea {
            width: 100%;
            padding: 7px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            color: #17202a;
            font: inherit;
            font-size: 13px;
            font-weight: 400;
        }
        textarea { min-height: 62px; resize: vertical; }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1320px;
            font-size: 12px;
        }
        th, td {
            padding: 6px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #e8eef5;
            color: #334155;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        td:first-child, th:first-child { border-left: 1px solid #e2e8f0; }
        tbody tr:nth-child(even) { background: #fbfdff; }
        .num { text-align: right; }
        .calibre-cell input { background: #dcfce7; }
        .saida-cell input { background: #dff7ff; }
        .status-ok {
            color: #147d52;
            font-weight: 700;
        }
        .status-alert {
            color: #b42318;
            font-weight: 700;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 12px 14px;
            border-top: 1px solid #d5dde6;
            background: #f8fafc;
        }
        button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 12px;
            border: 0;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            font-weight: 700;
        }
        .btn-save { background: #1f9d55; }
        .btn-add { background: #1f6feb; }
        .btn-reset { background: #d64545; }
        .notice {
            margin-bottom: 14px;
            padding: 10px 12px;
            border: 1px solid #b7ebc6;
            border-radius: 4px;
            background: #dcfce7;
            color: #166534;
            font-weight: 700;
        }
        .two-cols {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 16px;
        }
        .mini-table {
            min-width: 0;
        }
        .mini-table th, .mini-table td {
            padding: 8px;
        }
        @media (max-width: 1100px) {
            .summary-grid, .form-grid, .two-cols { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/menu.php'; ?>
    <button id="menuToggleBtn" class="menu-toggle-btn" type="button" title="Recolher menu" aria-label="Recolher menu">
        <i class="fa-solid fa-bars"></i>
    </button>

    <main class="cod-app-shell">
        <header class="top-shell">
            <h1>cod_12_05_calibradora</h1>
            <p>Leitura de peso/calibre, classificacao por faixa, direcionamento fisico e impacto operacional por posto.</p>
        </header>

        <section class="summary-grid">
            <div class="metric"><span>Total classificado</span><strong><?php echo number_format($total_kg, 2, ',', '.'); ?> kg</strong></div>
            <div class="metric"><span>Duracao</span><strong><?php echo htmlspecialchars((string)($partida['duracao_minutos'] ?? 60)); ?> min</strong></div>
            <div class="metric"><span>Carga media</span><strong><?php echo number_format($total_kg / $duracao_horas, 2, ',', '.'); ?> kg/h</strong></div>
            <div class="metric"><span>Pessoas necessarias</span><strong><?php echo $total_pessoas_necessarias; ?></strong></div>
            <div class="metric"><span>Gargalos</span><strong><?php echo $gargalos; ?></strong></div>
        </section>

        <div class="content-area">
            <?php if (isset($_GET['sucesso'])): ?>
                <div class="notice">Dados da calibradora salvos com sucesso.</div>
            <?php endif; ?>
            <?php if (isset($_GET['reset'])): ?>
                <div class="notice">Tabela da calibradora restaurada para o modelo inicial.</div>
            <?php endif; ?>

            <form method="post" id="calibradoraForm">
                <section class="panel">
                    <div class="panel-header">
                        <h2>Dados da partida</h2>
                        <span>Arquivo: data/reformulacao/calibradora.json</span>
                    </div>
                    <div class="form-grid">
                        <label>Nome da partida
                            <input type="text" name="partida_nome" value="<?php echo htmlspecialchars($partida['nome'] ?? ''); ?>">
                        </label>
                        <label>Fruta
                            <input type="text" name="fruta" value="<?php echo htmlspecialchars($partida['fruta'] ?? ''); ?>">
                        </label>
                        <label>Programa/variedade
                            <input type="text" name="programa" value="<?php echo htmlspecialchars($partida['programa'] ?? ''); ?>">
                        </label>
                        <label>Data
                            <input type="date" name="data_partida" value="<?php echo htmlspecialchars($partida['data_partida'] ?? date('Y-m-d')); ?>">
                        </label>
                        <label>Duracao da amostra (min)
                            <input type="number" min="1" step="1" name="duracao_minutos" value="<?php echo htmlspecialchars((string)($partida['duracao_minutos'] ?? 60)); ?>">
                        </label>
                        <label style="grid-column: 1 / -1;">Observacao
                            <textarea name="observacao"><?php echo htmlspecialchars($partida['observacao'] ?? ''); ?></textarea>
                        </label>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <h2>Faixas de calibre, saidas fisicas e impacto operacional</h2>
                        <span><?php echo count($linhas); ?> faixas</span>
                    </div>
                    <div class="table-wrap">
                        <table id="calibreTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Calibre / descricao</th>
                                    <th>Min g</th>
                                    <th>Max g</th>
                                    <th>Saida fisica</th>
                                    <th>Embalagem</th>
                                    <th>Processo</th>
                                    <th>Posto alimentado</th>
                                    <th>Gramas</th>
                                    <th>%</th>
                                    <th>Kg/h</th>
                                    <th>Cap. pessoa kg/h</th>
                                    <th>Pessoas aloc.</th>
                                    <th>Pessoas nec.</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($linhas as $idx => $row): ?>
                                <?php
                                    $gramas = cod_12_05_number($row['gramas'] ?? 0);
                                    $kg = $gramas / 1000;
                                    $percentual = $total_gramas > 0 ? ($gramas / $total_gramas) * 100 : 0;
                                    $carga_kg_h = $kg / $duracao_horas;
                                    $capacidade = max(1, cod_12_05_number($row['capacidade_pessoa_kg_h'] ?? 1));
                                    $pessoas_alocadas = max(0, cod_12_05_int($row['pessoas_alocadas'] ?? 0));
                                    $pessoas_necessarias = (int)ceil($carga_kg_h / $capacidade);
                                    $capacidade_total = $pessoas_alocadas * $capacidade;
                                    $gargalo = $pessoas_alocadas > 0 && $carga_kg_h > $capacidade_total;
                                ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td class="calibre-cell"><input type="text" name="descricao[]" value="<?php echo htmlspecialchars($row['descricao'] ?? ''); ?>"></td>
                                    <td><input class="num" type="number" step="0.01" name="faixa_min[]" value="<?php echo htmlspecialchars((string)($row['faixa_min'] ?? 0)); ?>"></td>
                                    <td><input class="num" type="number" step="0.01" name="faixa_max[]" value="<?php echo htmlspecialchars((string)($row['faixa_max'] ?? 0)); ?>"></td>
                                    <td class="saida-cell"><input type="text" name="saida[]" value="<?php echo htmlspecialchars($row['saida'] ?? ''); ?>"></td>
                                    <td><input type="text" name="embalagem[]" value="<?php echo htmlspecialchars($row['embalagem'] ?? ''); ?>"></td>
                                    <td><input type="text" name="processo[]" value="<?php echo htmlspecialchars($row['processo'] ?? ''); ?>"></td>
                                    <td><input type="text" name="posto[]" value="<?php echo htmlspecialchars($row['posto'] ?? ''); ?>"></td>
                                    <td><input class="num" type="number" min="0" step="1" name="gramas[]" value="<?php echo htmlspecialchars((string)$gramas); ?>"></td>
                                    <td class="num"><?php echo number_format($percentual, 2, ',', '.'); ?>%</td>
                                    <td class="num"><?php echo number_format($carga_kg_h, 2, ',', '.'); ?></td>
                                    <td><input class="num" type="number" min="1" step="0.01" name="capacidade_pessoa_kg_h[]" value="<?php echo htmlspecialchars((string)$capacidade); ?>"></td>
                                    <td><input class="num" type="number" min="0" step="1" name="pessoas_alocadas[]" value="<?php echo htmlspecialchars((string)$pessoas_alocadas); ?>"></td>
                                    <td class="num"><?php echo $pessoas_necessarias; ?></td>
                                    <td class="<?php echo $gargalo ? 'status-alert' : 'status-ok'; ?>"><?php echo $gargalo ? 'Gargalo' : 'OK'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="actions">
                        <button class="btn-save" type="submit" name="action" value="save"><i class="fa-solid fa-floppy-disk"></i> Salvar calibradora</button>
                        <button class="btn-add" type="submit" name="action" value="save" onclick="document.getElementById('extraRows').value='1';"><i class="fa-solid fa-plus"></i> Adicionar faixa vazia</button>
                        <button class="btn-reset" type="submit" name="action" value="reset" onclick="return confirm('Restaurar a tabela inicial da calibradora?')"><i class="fa-solid fa-rotate-left"></i> Restaurar modelo</button>
                        <input type="hidden" id="extraRows" name="extra_rows" value="0">
                    </div>
                </section>
            </form>

            <section class="two-cols">
                <div class="panel">
                    <div class="panel-header"><h2>Resumo por posto</h2></div>
                    <div class="table-wrap">
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Posto</th>
                                    <th>Kg</th>
                                    <th>Kg/h</th>
                                    <th>Pessoas nec.</th>
                                    <th>Alocadas</th>
                                    <th>Gargalos</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($postos_resumo as $posto => $resumo): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($posto); ?></td>
                                    <td class="num"><?php echo number_format($resumo['kg'], 2, ',', '.'); ?></td>
                                    <td class="num"><?php echo number_format($resumo['carga_kg_h'], 2, ',', '.'); ?></td>
                                    <td class="num"><?php echo $resumo['necessarias']; ?></td>
                                    <td class="num"><?php echo $resumo['alocadas']; ?></td>
                                    <td class="num"><?php echo $resumo['gargalos']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header"><h2>Leitura operacional</h2></div>
                    <div style="padding: 14px; line-height: 1.55; color: #475569;">
                        <p><strong>1.</strong> A calibradora le peso/calibre por faixa de gramas.</p>
                        <p><strong>2.</strong> Cada faixa aponta para uma saida fisica.</p>
                        <p><strong>3.</strong> A saida alimenta embalagem/processo e posto.</p>
                        <p><strong>4.</strong> O volume classificado vira carga por hora.</p>
                        <p><strong>5.</strong> A carga compara capacidade por pessoa e pessoas alocadas para indicar gargalos.</p>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script>
        setupRetractableMenu();

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
    </script>
</body>
</html>

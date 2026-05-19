<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/safe_storage.php';
require_once __DIR__ . '/module_routes.php';
require_once __DIR__ . '/shared/CadastrosBasicosRepository.php';

const COD_12_05_MODULE_SCOPE = 'REFORMULACAO_COD_12_05';
const COD_12_05_MODULE_LABEL = 'REFORMULACAO';
const COD_12_05_LEGACY_LABEL = 'MEMORIA';

function cod_12_05_data_path() {
    $path = rf_route('editor_bpm', 'storage');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $path;
}

function cod_12_05_generate_id($prefix) {
    $safe_prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $prefix);
    if ($safe_prefix === '') {
        $safe_prefix = 'cod_12_05';
    }
    return $safe_prefix . '_' . date('YmdHis') . '_' . random_int(1000, 9999);
}

function cod_12_05_default_data() {
    return [
        'version' => 1,
        'module_context' => cod_12_05_module_context(),
        'updated_at' => date('Y-m-d H:i:s'),
        'setores' => [
            [
                'id' => 'cod_12_05_setor_1',
                'nome' => 'Setor 1',
                'linhas' => [],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]
    ];
}

function cod_12_05_module_context() {
    return [
        'scope' => COD_12_05_MODULE_SCOPE,
        'label' => COD_12_05_MODULE_LABEL,
        'legacy_label' => COD_12_05_LEGACY_LABEL,
        'storage' => 'data/ativos/fluxo_teste02.json',
        'isolated_from' => [
            'public/memoria/FLUXO_TESTE02.php',
            'data/memoria/linhas.json',
            'data/memoria/setores.json'
        ]
    ];
}

function cod_12_05_load_data() {
    $path = cod_12_05_data_path();
    $data = cod_12_05_safe_load_json(
        $path,
        'cod_12_05_default_data',
        fn($candidate) => isset($candidate['setores']) && is_array($candidate['setores'])
    );
    $data['module_context'] = cod_12_05_module_context();
    return cod_12_05_normalize_shared_catalogs($data);
}

function cod_12_05_save_data($data) {
    $data['module_context'] = cod_12_05_module_context();
    $data['updated_at'] = date('Y-m-d H:i:s');
    cod_12_05_safe_write_json(cod_12_05_data_path(), $data);
}

function cod_12_05_validate_module_scope() {
    $scope = $_POST['module_scope'] ?? '';
    if ($scope !== COD_12_05_MODULE_SCOPE) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Escopo invalido. Este endpoint aceita apenas dados da REFORMULACAO.'
        ]);
        exit;
    }
}

function cod_12_05_find_setor_index($data, $setor_id) {
    foreach ($data['setores'] as $idx => $setor) {
        if (($setor['id'] ?? '') === $setor_id) {
            return $idx;
        }
    }
    return -1;
}

function cod_12_05_find_linha_index($setor, $linha_id) {
    foreach (($setor['linhas'] ?? []) as $idx => $linha) {
        if (($linha['id'] ?? '') === $linha_id) {
            return $idx;
        }
    }
    return -1;
}

function cod_12_05_row_timestamp($row) {
    $raw = $row['updated_at'] ?? $row['created_at'] ?? '';
    $ts = strtotime((string)$raw);
    return $ts !== false ? $ts : 0;
}

function cod_12_05_apply_drawflow_to_matching_lines(&$data, $linha_id, $drawflow_data) {
    $updated = 0;
    $now = date('Y-m-d H:i:s');

    foreach (($data['setores'] ?? []) as $setor_idx => $setor) {
        foreach (($setor['linhas'] ?? []) as $linha_idx => $linha) {
            if (($linha['id'] ?? '') !== $linha_id) {
                continue;
            }

            $data['setores'][$setor_idx]['linhas'][$linha_idx]['drawflow_data'] = $drawflow_data;
            $data['setores'][$setor_idx]['linhas'][$linha_idx]['updated_at'] = $now;
            $data['setores'][$setor_idx]['updated_at'] = $now;
            $updated++;
        }
    }

    return $updated;
}

function cod_12_05_first_ids($data) {
    $setor = $data['setores'][0] ?? null;
    $linha = $setor['linhas'][0] ?? null;
    return [
        'setor_id' => $setor['id'] ?? '',
        'linha_id' => $linha['id'] ?? ''
    ];
}

function cod_12_05_normalize_shared_catalogs($data) {
    cb_import_fluxo_data($data);

    $drawflows_by_line = [];
    foreach (($data['setores'] ?? []) as $setor) {
        foreach (($setor['linhas'] ?? []) as $linha) {
            $line_id = $linha['id'] ?? '';
            $candidate = [
                'drawflow_data' => $linha['drawflow_data'] ?? null,
                'created_at' => $linha['created_at'] ?? cb_now(),
                'updated_at' => $linha['updated_at'] ?? cb_now(),
                '_timestamp' => cod_12_05_row_timestamp($linha)
            ];
            if (
                !isset($drawflows_by_line[$line_id]) ||
                ($candidate['_timestamp'] >= ($drawflows_by_line[$line_id]['_timestamp'] ?? 0) && !empty($candidate['drawflow_data']))
            ) {
                $drawflows_by_line[$line_id] = $candidate;
            }
        }
    }

    $shared_setores = [];
    foreach (cb_list('setores', true) as $setor) {
        $setor['linhas'] = [];
        foreach (cb_linhas_por_setor($setor['id'] ?? '') as $linha) {
            $line_flow = $drawflows_by_line[$linha['id'] ?? ''] ?? [];
            $setor['linhas'][] = array_merge($linha, [
                'drawflow_data' => $line_flow['drawflow_data'] ?? null,
                'created_at' => $line_flow['created_at'] ?? ($linha['created_at'] ?? cb_now()),
                'updated_at' => $line_flow['updated_at'] ?? ($linha['updated_at'] ?? cb_now())
            ]);
        }
        $shared_setores[] = $setor;
    }

    $data['setores'] = $shared_setores;
    $data['module_context']['shared_catalogs'] = [
        'setores' => 'cadastros_basicos.setores',
        'linhas' => 'cadastros_basicos.linhas',
        'postos' => 'cadastros_basicos.postos'
    ];
    return $data;
}

$method = $_SERVER['REQUEST_METHOD'];
$data = cod_12_05_load_data();

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'generate_id') {
        $prefix = $_GET['prefix'] ?? 'cod_12_05_node';
        echo json_encode(['status' => 'success', 'id' => cod_12_05_generate_id($prefix)]);
        exit;
    }

    if ($action === 'load_drawflow_data') {
        $setor_id = $_GET['setor_id'] ?? '';
        $linha_id = $_GET['linha_id'] ?? '';
        $setor_idx = cod_12_05_find_setor_index($data, $setor_id);

        if ($setor_idx < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Setor nao encontrado.']);
            exit;
        }

        $linha_idx = cod_12_05_find_linha_index($data['setores'][$setor_idx], $linha_id);
        if ($linha_idx < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Linha nao encontrada.']);
            exit;
        }

        $flow = $data['setores'][$setor_idx]['linhas'][$linha_idx]['drawflow_data'] ?? null;
        echo json_encode(['status' => 'success', 'drawflow_data' => $flow]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Acao GET desconhecida.']);
    exit;
}

if ($method !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metodo nao permitido.']);
    exit;
}

$action = $_POST['action'] ?? '';
$setor_id = $_POST['setor_id'] ?? '';
$linha_id = $_POST['linha_id'] ?? '';

cod_12_05_validate_module_scope();

switch ($action) {
    case 'save_drawflow_data':
        $setor_idx = cod_12_05_find_setor_index($data, $setor_id);
        if ($setor_idx < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Setor nao encontrado.']);
            break;
        }

        $linha_idx = cod_12_05_find_linha_index($data['setores'][$setor_idx], $linha_id);
        if ($linha_idx < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Linha nao encontrada.']);
            break;
        }

        $drawflow_json = $_POST['drawflow_data'] ?? '';
        $drawflow_data = json_decode($drawflow_json, true);
        if (!is_array($drawflow_data)) {
            echo json_encode(['status' => 'error', 'message' => 'JSON do fluxo invalido.']);
            break;
        }
        $drawflow_data['module_context'] = cod_12_05_module_context();

        cod_12_05_apply_drawflow_to_matching_lines($data, $linha_id, $drawflow_data);
        cod_12_05_save_data($data);
        echo json_encode(['status' => 'success', 'setor_id' => $setor_id, 'linha_id' => $linha_id]);
        break;

    case 'add_setor':
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            echo json_encode(['status' => 'error', 'message' => 'Nome do setor e obrigatorio.']);
            break;
        }

        $setor = cb_upsert('setores', ['nome' => $nome, 'ativo' => 1, 'origem' => 'editor_bpm']);
        echo json_encode(['status' => 'success', 'setor_id' => $setor['id'] ?? '', 'linha_id' => '']);
        break;

    case 'delete_setor':
        $setor_idx = cod_12_05_find_setor_index($data, $setor_id);
        if ($setor_idx < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Setor nao encontrado.']);
            break;
        }

        cb_set_active('setores', $setor_id, 0);
        $fresh = cod_12_05_load_data();
        echo json_encode(array_merge(['status' => 'success'], cod_12_05_first_ids($fresh)));
        break;

    case 'add_linha':
        $setor_idx = cod_12_05_find_setor_index($data, $setor_id);
        if ($setor_idx < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Setor nao encontrado.']);
            break;
        }

        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            echo json_encode(['status' => 'error', 'message' => 'Nome da linha e obrigatorio.']);
            break;
        }

        $linha = cb_upsert('linhas', ['nome' => $nome, 'setor_id' => $setor_id, 'ativo' => 1, 'origem' => 'editor_bpm']);
        echo json_encode(['status' => 'success', 'setor_id' => $setor_id, 'linha_id' => $linha['id'] ?? '']);
        break;

    case 'rename_linha':
        $setor_idx = cod_12_05_find_setor_index($data, $setor_id);
        if ($setor_idx < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Setor nao encontrado.']);
            break;
        }

        $linha_idx = cod_12_05_find_linha_index($data['setores'][$setor_idx], $linha_id);
        if ($linha_idx < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Linha nao encontrada.']);
            break;
        }

        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            echo json_encode(['status' => 'error', 'message' => 'Nome da linha e obrigatorio.']);
            break;
        }

        cb_upsert('linhas', [
            'id' => $linha_id,
            'nome' => $nome,
            'setor_id' => $setor_id,
            'ativo' => 1,
            'origem' => 'editor_bpm'
        ]);
        echo json_encode(['status' => 'success', 'setor_id' => $setor_id, 'linha_id' => $linha_id]);
        break;

    case 'delete_linha':
        $setor_idx = cod_12_05_find_setor_index($data, $setor_id);
        if ($setor_idx < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Setor nao encontrado.']);
            break;
        }

        $linha_idx = cod_12_05_find_linha_index($data['setores'][$setor_idx], $linha_id);
        if ($linha_idx < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Linha nao encontrada.']);
            break;
        }

        cb_set_active('linhas', $linha_id, 0);
        $fresh = cod_12_05_load_data();
        $setor_idx = cod_12_05_find_setor_index($fresh, $setor_id);
        $target_linha = $setor_idx >= 0 ? ($fresh['setores'][$setor_idx]['linhas'][0]['id'] ?? '') : '';
        echo json_encode(['status' => 'success', 'setor_id' => $setor_id, 'linha_id' => $target_linha]);
        break;

    case 'save_posto_master':
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            echo json_encode(['status' => 'error', 'message' => 'Nome do posto e obrigatorio.']);
            break;
        }
        $posto = cb_upsert('postos', [
            'id' => trim($_POST['posto_id'] ?? ''),
            'nome' => $nome,
            'tempo_ciclo' => (float)str_replace(',', '.', $_POST['tempo_ciclo'] ?? 0),
            'ativo' => 1,
            'origem' => 'editor_bpm'
        ]);
        echo json_encode(['status' => 'success', 'posto' => $posto]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acao POST desconhecida.']);
        break;
}

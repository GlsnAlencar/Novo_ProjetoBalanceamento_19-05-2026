<?php
/**
 * API somente leitura da Arvore de Estrutura.
 *
 * Fronteira oficial para consultas externas, incluindo uso futuro pela
 * Cronoanalise. Este endpoint nao altera dados da arvore.
 */
require_once __DIR__ . '/module_routes.php';

function ae_api_data_path() {
    return rf_route('arvore_estrutura', 'storage');
}

function ae_api_response($payload, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ae_api_load_data() {
    $path = ae_api_data_path();
    if (!is_file($path)) {
        ae_api_response([
            'status' => 'error',
            'message' => 'Arquivo de dados da Arvore de Estrutura nao encontrado.'
        ], 404);
    }

    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        ae_api_response([
            'status' => 'error',
            'message' => 'JSON da Arvore de Estrutura invalido.'
        ], 500);
    }

    return $data;
}

function ae_api_index_by_id($rows) {
    $indexed = [];
    foreach (($rows ?? []) as $row) {
        $id = (string)($row['id'] ?? '');
        if ($id !== '') {
            $indexed[$id] = $row;
        }
    }
    return $indexed;
}

function ae_api_is_active($row) {
    return (int)($row['ativo'] ?? 1) === 1;
}

function ae_api_num($value, $default = 0) {
    return is_numeric($value) ? (float)$value : $default;
}

function ae_api_listar_para_cronoanalise($data) {
    $items = ae_api_index_by_id($data['tabela_itens'] ?? []);
    $trees = ae_api_index_by_id($data['tabela_arvores'] ?? []);
    $rows = [];

    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        if (!ae_api_is_active($comp)) {
            continue;
        }

        $tree = $trees[(string)($comp['arvore_id'] ?? '')] ?? null;
        $parent = $items[(string)($comp['item_pai_id'] ?? '')] ?? null;
        $child = $items[(string)($comp['item_filho_id'] ?? '')] ?? null;

        if (!$tree || !$parent || !$child) {
            continue;
        }

        if (!ae_api_is_active($tree) || !ae_api_is_active($parent) || !ae_api_is_active($child)) {
            continue;
        }

        $rows[] = [
            'id_arvore' => $tree['id'] ?? '',
            'codigo_arvore' => $tree['codigo'] ?? '',
            'produto_pai_id' => $parent['id'] ?? '',
            'codigo_produto_pai' => $parent['codigo'] ?? '',
            'descricao_produto_pai' => $parent['nome'] ?? '',
            'produto_filho_id' => $child['id'] ?? '',
            'codigo_item' => $child['codigo'] ?? '',
            'descricao_item' => $child['nome'] ?? '',
            'unidade' => ($comp['unidade'] ?? '') !== '' ? $comp['unidade'] : ($child['unidade_base'] ?? ''),
            'quantidade' => ae_api_num($comp['quantidade'] ?? 0),
            'fator_conversao' => ae_api_num($comp['fator_conversao'] ?? 1, 1),
            'ativo' => 1
        ];
    }

    usort($rows, function ($a, $b) {
        return [
            (string)$a['codigo_arvore'],
            (string)$a['codigo_produto_pai'],
            (string)$a['codigo_item']
        ] <=> [
            (string)$b['codigo_arvore'],
            (string)$b['codigo_produto_pai'],
            (string)$b['codigo_item']
        ];
    });

    return $rows;
}

function ae_api_dispatch() {
    header('Content-Type: application/json; charset=utf-8');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        ae_api_response([
            'status' => 'error',
            'message' => 'Metodo nao permitido. Esta API aceita somente GET.'
        ], 405);
    }

    $acao = $_GET['acao'] ?? '';
    $data = ae_api_load_data();

    if ($acao === 'listar_para_cronoanalise') {
        ae_api_response(ae_api_listar_para_cronoanalise($data));
    }

    ae_api_response([
        'status' => 'error',
        'message' => 'Acao desconhecida.'
    ], 400);
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    ae_api_dispatch();
}

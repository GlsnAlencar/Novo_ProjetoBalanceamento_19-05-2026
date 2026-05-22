<?php
/**
 * Rotas oficiais dos modulos da Reformulacao.
 *
 * Mantem os contratos em um unico ponto para que os modulos possam ser
 * separados na navegacao sem quebrar consumidores existentes.
 */

function rf_module_routes() {
    return [
        'editor_bpm' => [
            'label' => 'Editor BPM',
            'page' => 'fluxo.php',
            'post_table' => 'tabela_postos_fluxo.php',
            'api' => 'api_fluxo.php',
            'storage' => __DIR__ . '/../../data/ativos/fluxo_teste02.json',
            'scope' => 'REFORMULACAO_COD_12_05',
        ],
        'arvore_estrutura' => [
            'label' => 'Arvore de Estrutura',
            'page' => 'arvore_estrutura.php',
            'table' => 'tabela_arvore_estrutura.php',
            'items_report' => 'relatorio_itens_arvore.php',
            'api' => 'api_arvore_estrutura.php',
            'storage' => __DIR__ . '/../../data/ativos/arvore_estrutura.json',
            'read_action' => 'listar_para_cronoanalise',
        ],
    ];
}

function rf_route($module, $key, $default = '') {
    $routes = rf_module_routes();
    return $routes[$module][$key] ?? $default;
}

function rf_route_path($module, $key) {
    $route = rf_route($module, $key);
    return $route !== '' ? __DIR__ . '/' . $route : '';
}

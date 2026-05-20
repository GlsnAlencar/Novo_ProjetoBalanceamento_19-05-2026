<?php
/**
 * COD_12_05_FLUXO_TESTE02.php - Ambiente isolado para correcoes e evolucao do editor BPM.
 *
 * Hierarquia propria:
 *   multiplos setores -> multiplas linhas -> um fluxo Drawflow por linha
 *
 * Persistencia independente:
 *   data/ativos/fluxo_teste02.json
 */
require_once __DIR__ . '/safe_storage.php';
require_once __DIR__ . '/module_routes.php';
require_once rf_route_path('arvore_estrutura', 'api');
require_once __DIR__ . '/shared/CadastrosBasicosRepository.php';
require_once __DIR__ . '/cronoanalise/repositories/CronoanaliseRepository.php';

const COD_12_05_MODULE_SCOPE = 'REFORMULACAO_COD_12_05';
const COD_12_05_MODULE_LABEL = 'REFORMULACAO';
const COD_12_05_LEGACY_LABEL = 'MEMORIA';

function cod_12_05_fluxo_teste02_data_path() {
    $path = rf_route('editor_bpm', 'storage');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $path;
}

function cod_12_05_fluxo_teste02_generate_id($prefix) {
    return $prefix . '_' . date('YmdHis') . '_' . random_int(1000, 9999);
}

function cod_12_05_fluxo_teste02_default_data() {
    return [
        'version' => 1,
        'module_context' => [
            'scope' => COD_12_05_MODULE_SCOPE,
            'label' => COD_12_05_MODULE_LABEL,
            'legacy_label' => COD_12_05_LEGACY_LABEL,
            'storage' => 'data/ativos/fluxo_teste02.json',
            'isolated_from' => [
                'public/memoria/FLUXO_TESTE02.php',
                'data/memoria/linhas.json',
                'data/memoria/setores.json'
            ]
        ],
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

function cod_12_05_fluxo_teste02_load_data() {
    $path = cod_12_05_fluxo_teste02_data_path();
    $data = cod_12_05_safe_load_json(
        $path,
        'cod_12_05_fluxo_teste02_default_data',
        fn($candidate) => isset($candidate['setores']) && is_array($candidate['setores'])
    );

    $data['module_context'] = [
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

    return $data;
}

function cod_12_05_fluxo_teste02_find_setor($setores, $setor_id) {
    foreach ($setores as $setor) {
        if (($setor['id'] ?? '') === $setor_id) {
            return $setor;
        }
    }
    return null;
}

function cod_12_05_fluxo_teste02_find_linha($setor, $linha_id) {
    foreach (($setor['linhas'] ?? []) as $linha) {
        if (($linha['id'] ?? '') === $linha_id) {
            return $linha;
        }
    }
    return null;
}

function cod_12_05_fluxo_teste02_row_timestamp($row) {
    $raw = $row['updated_at'] ?? $row['created_at'] ?? '';
    $ts = strtotime((string)$raw);
    return $ts !== false ? $ts : 0;
}

function cod_12_05_fluxo_teste02_num($value) {
    if (is_string($value)) {
        $raw = trim(str_replace(['%', ' '], '', $value));
        $raw = str_contains($raw, ',') ? str_replace('.', '', $raw) : $raw;
        $value = str_replace(',', '.', $raw);
    }
    return is_numeric($value) ? (float)$value : 0.0;
}

function cod_12_05_fluxo_teste02_norm($value): string {
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$value);
    return strtolower(preg_replace('/[^a-z0-9]+/i', ' ', (string)$value));
}

function cod_12_05_fluxo_teste02_fruta_codes(): array {
    return ['FR-001', 'PF-001'];
}

function cod_12_05_fluxo_teste02_arvore_children(array $data): array {
    $children = [];
    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        if ((int)($comp['ativo'] ?? 1) !== 1) {
            continue;
        }
        $key = (string)($comp['arvore_id'] ?? '') . '|' . (string)($comp['item_pai_id'] ?? '');
        $children[$key][] = $comp;
    }
    return $children;
}

function cod_12_05_fluxo_teste02_sum_target(string $treeId, string $parentId, array $targetCodes, float $factor, array $visited, array $children, array $itemsById): float {
    $key = $treeId . '|' . $parentId;
    $total = 0.0;
    foreach (($children[$key] ?? []) as $comp) {
        $childId = (string)($comp['item_filho_id'] ?? '');
        if ($childId === '' || isset($visited[$childId])) {
            continue;
        }
        $qty = max(0.000001, cod_12_05_fluxo_teste02_num($comp['quantidade'] ?? 1));
        $nextFactor = $factor * $qty;
        $code = (string)($itemsById[$childId]['codigo'] ?? '');
        if (in_array($code, $targetCodes, true)) {
            $total += $nextFactor;
            continue;
        }
        $nextVisited = $visited;
        $nextVisited[$childId] = true;
        $total += cod_12_05_fluxo_teste02_sum_target($treeId, $childId, $targetCodes, $nextFactor, $nextVisited, $children, $itemsById);
    }
    return $total;
}

function cod_12_05_fluxo_teste02_contentor_padrao_kg(array $data, array $itemsById, array $children): float {
    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        $rootId = (string)($tree['item_raiz_id'] ?? '');
        if ((int)($tree['ativo'] ?? 1) !== 1 || (string)($itemsById[$rootId]['codigo'] ?? '') !== 'ITM-00002') {
            continue;
        }
        $kg = cod_12_05_fluxo_teste02_sum_target(
            (string)($tree['id'] ?? ''),
            $rootId,
            cod_12_05_fluxo_teste02_fruta_codes(),
            1.0,
            [$rootId => true],
            $children,
            $itemsById
        );
        if ($kg > 0) {
            return $kg;
        }
    }

    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        $parent = $itemsById[(string)($comp['item_pai_id'] ?? '')] ?? [];
        $child = $itemsById[(string)($comp['item_filho_id'] ?? '')] ?? [];
        if (($parent['codigo'] ?? '') === 'ITM-00002' && in_array((string)($child['codigo'] ?? ''), cod_12_05_fluxo_teste02_fruta_codes(), true)) {
            return max(0.000001, cod_12_05_fluxo_teste02_num($comp['quantidade'] ?? 0));
        }
    }

    return 20.0;
}

function cod_12_05_fluxo_teste02_nominal_kg_item(array $data, array $itemsById, array $children, string $itemId): float {
    if ($itemId === '') {
        return 0.0;
    }

    $activeTrees = [];
    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        if ((int)($tree['ativo'] ?? 1) === 1) {
            $activeTrees[(string)($tree['id'] ?? '')] = true;
        }
    }

    $best = 0.0;
    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        $treeId = (string)($comp['arvore_id'] ?? '');
        if ((int)($comp['ativo'] ?? 1) !== 1 || !isset($activeTrees[$treeId]) || (string)($comp['item_pai_id'] ?? '') !== $itemId) {
            continue;
        }
        $child = $itemsById[(string)($comp['item_filho_id'] ?? '')] ?? [];
        if (in_array((string)($child['codigo'] ?? ''), cod_12_05_fluxo_teste02_fruta_codes(), true)) {
            $best = max($best, max(0.000001, cod_12_05_fluxo_teste02_num($comp['quantidade'] ?? 0)));
        }
    }
    return $best;
}

function cod_12_05_fluxo_teste02_nominal_kg_tree(string $treeId, string $parentId, float $factor, array $visited, array $children, array $itemsById, array $data): float {
    $total = 0.0;
    foreach (($children[$treeId . '|' . $parentId] ?? []) as $comp) {
        $childId = (string)($comp['item_filho_id'] ?? '');
        if ($childId === '' || isset($visited[$childId])) {
            continue;
        }
        $qty = max(0.000001, cod_12_05_fluxo_teste02_num($comp['quantidade'] ?? 1));
        $nextFactor = $factor * $qty;
        $nominalKg = cod_12_05_fluxo_teste02_nominal_kg_item($data, $itemsById, $children, $childId);
        if ($nominalKg > 0) {
            $total += $nextFactor * $nominalKg;
            continue;
        }
        $nextVisited = $visited;
        $nextVisited[$childId] = true;
        $total += cod_12_05_fluxo_teste02_nominal_kg_tree($treeId, $childId, $nextFactor, $nextVisited, $children, $itemsById, $data);
    }
    return $total;
}

function cod_12_05_fluxo_teste02_equivalente_subarvore(string $treeId, string $rootId, array $data, array $itemsById, array $children): array {
    foreach (['ITM-00002', 'ITM-00015'] as $targetCode) {
        $total = cod_12_05_fluxo_teste02_sum_target($treeId, $rootId, [$targetCode], 1.0, [$rootId => true], $children, $itemsById);
        if ($total > 0) {
            return ['quantidade' => $total, 'codigo' => $targetCode];
        }
    }

    $kgPorContentor = cod_12_05_fluxo_teste02_contentor_padrao_kg($data, $itemsById, $children);
    $kgManga = cod_12_05_fluxo_teste02_sum_target($treeId, $rootId, cod_12_05_fluxo_teste02_fruta_codes(), 1.0, [$rootId => true], $children, $itemsById);
    if ($kgManga > 0 && $kgPorContentor > 0) {
        return ['quantidade' => $kgManga / $kgPorContentor, 'codigo' => 'FRUTA-IN-NATURA'];
    }

    return ['quantidade' => 0.0, 'codigo' => ''];
}

function cod_12_05_fluxo_teste02_equivalente_contexto_item(array $data, array $itemsById, array $children, string $itemId): array {
    if ($itemId === '') {
        return ['quantidade' => 0.0, 'codigo' => ''];
    }

    $activeTrees = [];
    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        if ((int)($tree['ativo'] ?? 1) === 1) {
            $activeTrees[(string)($tree['id'] ?? '')] = true;
        }
    }

    $parentsByTreeChild = [];
    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        $treeId = (string)($comp['arvore_id'] ?? '');
        if (!isset($activeTrees[$treeId])) {
            continue;
        }
        $childId = (string)($comp['item_filho_id'] ?? '');
        $parentId = (string)($comp['item_pai_id'] ?? '');
        if ($childId !== '' && $parentId !== '') {
            $parentsByTreeChild[$treeId . '|' . $childId][] = $parentId;
        }
    }

    foreach (array_keys($activeTrees) as $treeId) {
        $frontier = $parentsByTreeChild[$treeId . '|' . $itemId] ?? [];
        $visited = [$itemId => true];
        while (!empty($frontier)) {
            $parentId = array_shift($frontier);
            if ($parentId === '' || isset($visited[$parentId])) {
                continue;
            }
            $visited[$parentId] = true;

            $base = cod_12_05_fluxo_teste02_equivalente_subarvore($treeId, $parentId, $data, $itemsById, $children);
            if (cod_12_05_fluxo_teste02_num($base['quantidade'] ?? 0) > 0) {
                return $base;
            }

            foreach (($parentsByTreeChild[$treeId . '|' . $parentId] ?? []) as $nextParentId) {
                if (!isset($visited[$nextParentId])) {
                    $frontier[] = $nextParentId;
                }
            }
        }
    }

    return ['quantidade' => 0.0, 'codigo' => ''];
}

function cod_12_05_fluxo_teste02_equivalente_item(array $data, array $itemsById, array $children, string $itemId): array {
    $itemCode = (string)($itemsById[$itemId]['codigo'] ?? '');
    if (in_array($itemCode, ['ITM-00002', 'ITM-00015'], true)) {
        return ['quantidade' => 1.0, 'codigo' => $itemCode];
    }

    $trees = array_values(array_filter(
        $data['tabela_arvores'] ?? [],
        fn($tree) => (int)($tree['ativo'] ?? 1) === 1 && (string)($tree['item_raiz_id'] ?? '') === $itemId
    ));

    $parentTrees = array_values(array_filter(
        $data['tabela_arvore_composicao'] ?? [],
        fn($comp) => (int)($comp['ativo'] ?? 1) === 1 && (string)($comp['item_pai_id'] ?? '') === $itemId
    ));

    if (empty($trees) && empty($parentTrees)) {
        return cod_12_05_fluxo_teste02_equivalente_contexto_item($data, $itemsById, $children, $itemId);
    }

    if (empty($trees) && !empty($parentTrees)) {
        $seenTrees = [];
        foreach ($parentTrees as $comp) {
            $treeId = (string)($comp['arvore_id'] ?? '');
            if ($treeId === '' || isset($seenTrees[$treeId])) {
                continue;
            }
            $seenTrees[$treeId] = true;
            $base = cod_12_05_fluxo_teste02_equivalente_subarvore($treeId, $itemId, $data, $itemsById, $children);
            if (cod_12_05_fluxo_teste02_num($base['quantidade'] ?? 0) > 0) {
                return $base;
            }
        }

        return cod_12_05_fluxo_teste02_equivalente_contexto_item($data, $itemsById, $children, $itemId);
    }

    foreach (['ITM-00002', 'ITM-00015'] as $targetCode) {
        $total = 0.0;
        foreach ($trees as $tree) {
            $rootId = (string)($tree['item_raiz_id'] ?? '');
            $total += cod_12_05_fluxo_teste02_sum_target((string)($tree['id'] ?? ''), $rootId, [$targetCode], 1.0, [$rootId => true], $children, $itemsById);
        }
        if ($total > 0) {
            return ['quantidade' => $total, 'codigo' => $targetCode];
        }
    }

    $kgPorContentor = cod_12_05_fluxo_teste02_contentor_padrao_kg($data, $itemsById, $children);
    $kgManga = 0.0;
    $kgNominal = 0.0;
    foreach ($trees as $tree) {
        $treeId = (string)($tree['id'] ?? '');
        $rootId = (string)($tree['item_raiz_id'] ?? '');
        $kgManga += cod_12_05_fluxo_teste02_sum_target($treeId, $rootId, cod_12_05_fluxo_teste02_fruta_codes(), 1.0, [$rootId => true], $children, $itemsById);
        $kgNominal += cod_12_05_fluxo_teste02_nominal_kg_tree($treeId, $rootId, 1.0, [$rootId => true], $children, $itemsById, $data);
    }
    $kgBase = $kgManga > 0 ? $kgManga : $kgNominal;
    if ($kgBase > 0 && $kgPorContentor > 0) {
        return ['quantidade' => $kgBase / $kgPorContentor, 'codigo' => $kgManga > 0 ? 'FRUTA-IN-NATURA' : 'FRUTA-IN-NATURA-NOMINAL'];
    }

    return ['quantidade' => 0.0, 'codigo' => ''];
}

function cod_12_05_fluxo_teste02_item_ids_equivalencia(array $row, array $itemsById): array {
    $ids = preg_split('/\s*,\s*/', (string)($row['produto_id'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $ids = array_values(array_filter(array_map('trim', $ids)));
    if (!empty($ids)) {
        return $ids;
    }

    $terms = array_filter(array_map('trim', [
        (string)($row['codigo_arvore_estrutura'] ?? $row['codigo_arvore'] ?? ''),
        (string)($row['grupo_embalagem'] ?? ''),
        (string)($row['item'] ?? $row['item_embalagem'] ?? $row['item_carga'] ?? $row['produto'] ?? ''),
    ]));

    $matches = [];
    foreach ($terms as $term) {
        $termNorm = cod_12_05_fluxo_teste02_norm($term);
        if ($termNorm === '' || in_array($termNorm, ['embalagem', 'componente', 'vazio'], true)) {
            continue;
        }
        foreach ($itemsById as $id => $item) {
            if ($termNorm === cod_12_05_fluxo_teste02_norm($item['codigo'] ?? '') || $termNorm === cod_12_05_fluxo_teste02_norm($item['nome'] ?? '')) {
                $matches[] = (string)$id;
            }
        }
    }
    return array_values(array_unique($matches));
}

function cod_12_05_fluxo_teste02_peso_nominal_kg(array $row): float {
    foreach ([$row['grupo_embalagem'] ?? '', $row['item'] ?? $row['item_embalagem'] ?? $row['item_carga'] ?? $row['produto'] ?? '', $row['observacao'] ?? ''] as $term) {
        if (str_contains(cod_12_05_fluxo_teste02_norm($term), 'caixa') && preg_match('/(\d+(?:[,.]\d+)?)\s*kg/i', (string)$term, $match)) {
            return cod_12_05_fluxo_teste02_num($match[1]);
        }
    }
    return 0.0;
}

function cod_12_05_fluxo_teste02_contentores_equivalentes(array $row): float {
    static $data = null;
    static $itemsById = null;
    static $children = null;

    if ($data === null) {
        $data = ae_api_load_data();
        $itemsById = [];
        foreach (($data['tabela_itens'] ?? []) as $item) {
            $itemsById[(string)($item['id'] ?? '')] = $item;
        }
        $children = cod_12_05_fluxo_teste02_arvore_children($data);
    }

    $ids = cod_12_05_fluxo_teste02_item_ids_equivalencia($row, $itemsById);
    if (empty($ids)) {
        $kgPorContentor = cod_12_05_fluxo_teste02_contentor_padrao_kg($data, $itemsById, $children);
        $kgNominal = cod_12_05_fluxo_teste02_peso_nominal_kg($row);
        return ($kgNominal > 0 && $kgPorContentor > 0) ? $kgNominal / $kgPorContentor : 0.0;
    }

    foreach (['ITM-00002', 'ITM-00015', 'FRUTA-IN-NATURA', 'FRUTA-IN-NATURA-NOMINAL'] as $codigo) {
        $quantidade = 0.0;
        foreach ($ids as $id) {
            $base = cod_12_05_fluxo_teste02_equivalente_item($data, $itemsById, $children, (string)$id);
            if (($base['codigo'] ?? '') === $codigo) {
                $quantidade += cod_12_05_fluxo_teste02_num($base['quantidade'] ?? 0);
            }
        }
        if ($quantidade > 0) {
            return $quantidade;
        }
    }

    return 0.0;
}

function cod_12_05_fluxo_teste02_crono_tempos(array $row): array {
    $tempoTotal = cod_12_05_fluxo_teste02_num($row['tempo_total_s'] ?? $row['tempo_total'] ?? $row['tempo_s'] ?? 0);
    $tempoUnitario = cod_12_05_fluxo_teste02_num($row['tempo_unitario'] ?? $row['tempo_unitario_utilizado'] ?? $row['TP'] ?? $row['tp'] ?? 0);
    $tr = cod_12_05_fluxo_teste02_num($row['tr_s'] ?? $row['TR'] ?? $row['tr'] ?? $tempoUnitario);
    $tn = cod_12_05_fluxo_teste02_num($row['tn_s'] ?? $row['TN'] ?? $row['tn'] ?? $tr);
    $tp = cod_12_05_fluxo_teste02_num($row['tp_s'] ?? $row['TP'] ?? $row['tp'] ?? $tempoUnitario);
    $tempoBase = strtoupper(trim((string)($row['tempo_base_utilizado'] ?? 'TP')));
    if (!in_array($tempoBase, ['TR', 'TN', 'TP'], true)) {
        $tempoBase = 'TP';
    }

    $atividadeNorm = trim(cod_12_05_fluxo_teste02_norm($row['atividade'] ?? $row['descricao'] ?? ''));
    if ($atividadeNorm === 'embalar' && $tempoTotal > 0) {
        $tr = $tempoTotal;
        $tn = $tempoTotal;
        $tp = $tempoTotal;
    }

    $tcOperacao = $tp > 0 ? $tp : ($tempoUnitario > 0 ? $tempoUnitario : $tempoTotal);
    $tempoAtivo = $tempoBase === 'TR' ? $tr : ($tempoBase === 'TN' ? $tn : $tp);
    $equivalentes = cod_12_05_fluxo_teste02_contentores_equivalentes($row);
    $tcContentor = $equivalentes > 0 && $tempoAtivo > 0 ? $tempoAtivo / $equivalentes : cod_12_05_fluxo_teste02_num($row['ritmo_contentor'] ?? 0);

    return [
        'tc' => $tcOperacao,
        'tc_contentor' => $tcContentor,
        'contentores_equivalentes' => $equivalentes,
    ];
}

function cod_12_05_fluxo_teste02_crono_atividades() {
    $repository = new CronoanaliseRepository(rf_route('editor_bpm', 'storage'));
    $rows = $repository->listarCronoanalises([]);
    $atividades = [];
    $seen = [];

    foreach ($rows as $row) {
        $nome = trim((string)($row['atividade'] ?? $row['descricao'] ?? ''));
        if ($nome === '') {
            continue;
        }

        $tipo = trim((string)($row['tipo_operacao'] ?? $row['tipo_atividade'] ?? $row['tipo_calculo'] ?? ''));
        $tempos = cod_12_05_fluxo_teste02_crono_tempos($row);
        $tc = cod_12_05_fluxo_teste02_num($tempos['tc'] ?? 0);
        $tcContentor = cod_12_05_fluxo_teste02_num($tempos['tc_contentor'] ?? 0);
        $key = strtolower($nome . '|' . $tipo . '|' . $tc . '|' . $tcContentor);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $atividades[] = [
            'id' => (string)($row['id'] ?? $key),
            'nome' => $nome,
            'tipo' => $tipo,
            'tc' => $tc,
            'tcContentor' => $tcContentor,
            'ritmo' => $tcContentor,
            'contentoresEquivalentes' => cod_12_05_fluxo_teste02_num($tempos['contentores_equivalentes'] ?? 0),
            'posto' => (string)($row['posto'] ?? ''),
            'linha' => (string)($row['linha'] ?? ''),
        ];
    }

    usort($atividades, fn($a, $b) => strcasecmp($a['nome'] . $a['tipo'], $b['nome'] . $b['tipo']));
    return $atividades;
}

function cod_12_05_fluxo_teste02_normalize_shared_catalogs($data) {
    cb_import_fluxo_data($data);

    $drawflows_by_line = [];
    foreach (($data['setores'] ?? []) as $setor) {
        foreach (($setor['linhas'] ?? []) as $linha) {
            $line_id = $linha['id'] ?? '';
            $candidate = [
                'drawflow_data' => $linha['drawflow_data'] ?? null,
                'created_at' => $linha['created_at'] ?? cb_now(),
                'updated_at' => $linha['updated_at'] ?? cb_now(),
                '_timestamp' => cod_12_05_fluxo_teste02_row_timestamp($linha)
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

$fluxo_data = cod_12_05_fluxo_teste02_normalize_shared_catalogs(cod_12_05_fluxo_teste02_load_data());
$setores = $fluxo_data['setores'];
$shared_postos = cb_list('postos', true);
$crono_atividades = cod_12_05_fluxo_teste02_crono_atividades();

$setor_id_ativo = $_GET['setor_id'] ?? null;
if (!$setor_id_ativo && !empty($setores)) {
    $setor_id_ativo = $setores[0]['id'];
}

$setor_ativo = cod_12_05_fluxo_teste02_find_setor($setores, $setor_id_ativo);
if (!$setor_ativo && !empty($setores)) {
    $setor_ativo = $setores[0];
    $setor_id_ativo = $setor_ativo['id'];
}

$linhas_do_setor = $setor_ativo['linhas'] ?? [];
$linha_id_ativo = $_GET['linha_id'] ?? null;
if (!$linha_id_ativo && !empty($linhas_do_setor)) {
    $linha_id_ativo = $linhas_do_setor[0]['id'];
}

$linha_ativa = $setor_ativo ? cod_12_05_fluxo_teste02_find_linha($setor_ativo, $linha_id_ativo) : null;
if (!$linha_ativa && !empty($linhas_do_setor)) {
    $linha_ativa = $linhas_do_setor[0];
    $linha_id_ativo = $linha_ativa['id'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>cod_12_05_fluxo_teste02 - BPM Isolado</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.css">
    <script src="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            background: #eef1f5;
            color: #1f2933;
            font-family: Arial, sans-serif;
        }
        .cod-app-shell {
            min-height: 100vh;
            margin-left: var(--sidebar-width, 270px);
            display: flex;
            flex-direction: column;
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
        .top-shell {
            position: sticky;
            top: 0;
            z-index: 900;
            background: #fff;
            color: #17202a;
            border-bottom: 1px solid #d7dee7;
            box-shadow: 0 1px 4px rgba(15, 23, 42, .08);
        }
        .title-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 44px;
            padding: 6px 18px 6px 54px;
        }
        .title-bar h1 {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0;
            white-space: nowrap;
        }
        .title-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }
        .module-badge,
        .memory-badge {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .02em;
            white-space: nowrap;
        }
        .module-badge {
            background: #dff7ea;
            color: #147d52;
            border: 1px solid #9edebc;
        }
        .memory-badge {
            background: #eef2f6;
            color: #64748b;
            border: 1px solid #cbd5e1;
        }
        .sector-row {
            display: flex;
            align-items: center;
            gap: 8px;
            width: min(360px, 42vw);
            margin-left: auto;
        }
        .sector-row strong {
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
        }
        .sector-row select {
            min-width: 130px;
            flex: 1;
            height: 30px;
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            color: #1f2933;
            font-weight: 600;
        }
        .icon-btn,
        .toolbar button,
        .line-tabs button {
            border: 0;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
        }
        .icon-btn {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-add { background: #1f9d55; color: #fff; }
        .btn-danger { background: #d64545; color: #fff; }
        .btn-primary { background: #1f6feb; color: #fff; }
        .btn-muted { background: #6b7280; color: #fff; }
        .line-tabs {
            display: flex;
            align-items: end;
            gap: 3px;
            min-height: 36px;
            padding: 0 18px;
            background: #eef2f6;
            border-bottom: 1px solid #d7dee7;
            border-top: 1px solid #e3e9ef;
            overflow-x: auto;
        }
        .line-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 31px;
            padding: 6px 12px;
            margin-top: 4px;
            border: 1px solid #b8c3ce;
            border-bottom: 0;
            border-radius: 5px 5px 0 0;
            color: #334155;
            text-decoration: none;
            background: #cfd8e3;
            white-space: nowrap;
            font-size: 13px;
        }
        .line-tab.active {
            background: #fff;
            color: #1f6feb;
            border-top: 3px solid #1f6feb;
            font-weight: 700;
        }
        .line-actions {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 31px;
            padding: 0 8px;
            margin-top: 4px;
            background: #fff;
            border-top: 1px solid #b8c3ce;
        }
        .line-actions i {
            cursor: pointer;
            font-size: 12px;
            color: #475569;
        }
        .line-actions .fa-trash { color: #d64545; }
        .toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: #fff;
            border-bottom: 1px solid #d3dae2;
        }
        .toolbar button { padding: 7px 11px; }
        .toolbar-mini-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #f8fafc;
            color: #334155;
            padding: 6px 9px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .toolbar-mini-btn:hover {
            border-color: #1f6feb;
            color: #1f6feb;
        }
        .zoom-controls {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #f8fafc;
        }
        .zoom-controls button {
            width: 28px;
            height: 28px;
            padding: 0;
            border: 0;
            border-radius: 3px;
            background: #fff;
            color: #334155;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }
        .zoom-controls button:hover {
            background: #1f6feb;
            color: #fff;
        }
        .zoom-value {
            min-width: 46px;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            text-align: center;
        }
        .toolbar-spacer { flex: 1; }
        #saveStatus {
            color: #1f9d55;
            font-size: 12px;
            font-weight: 700;
            opacity: 0;
            transition: opacity .2s;
        }
        #drawflow {
            flex: 1;
            width: 100%;
            min-height: 560px;
            background-color: #fff;
            background-image:
                linear-gradient(rgba(0,0,0,0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,0,0,0.05) 1px, transparent 1px);
            background-size: 20px 20px;
        }
        .flow-workbench {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            flex: 1;
            min-height: 560px;
            transition: grid-template-columns .22s ease;
        }
        .flow-workbench.posto-table-open {
            grid-template-columns: minmax(0, 1fr) minmax(420px, 34vw);
        }
        .flow-workbench.properties-collapsed {
            grid-template-columns: minmax(0, 1fr) 46px;
        }
        .properties-panel {
            border-left: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 12px 14px;
            overflow-y: auto;
            min-width: 0;
        }
        .properties-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }
        .properties-header h2 {
            font-size: 15px;
            color: #17202a;
            white-space: nowrap;
        }
        .properties-toggle {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            color: #334155;
            cursor: pointer;
        }
        .properties-toggle:hover {
            border-color: #1f6feb;
            color: #1f6feb;
        }
        .flow-workbench.properties-collapsed .properties-panel {
            padding: 8px;
            overflow: hidden;
        }
        .flow-workbench.properties-collapsed .properties-header {
            justify-content: center;
            margin-bottom: 0;
        }
        .flow-workbench.properties-collapsed .properties-header h2,
        .flow-workbench.properties-collapsed #propertiesContent {
            display: none;
        }
        .property-row {
            display: grid;
            gap: 4px;
            margin-bottom: 10px;
        }
        .property-row label {
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .property-row input,
        .property-row textarea {
            width: 100%;
            padding: 7px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            color: #17202a;
            font: inherit;
            font-size: 13px;
        }
        .property-row textarea {
            min-height: 72px;
            resize: vertical;
        }
        .property-pill-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .property-pill {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            min-height: 24px;
            padding: 4px 7px;
            border-radius: 4px;
            background: #e7f0fb;
            color: #174a7c;
            font-size: 11px;
            font-weight: 700;
        }
        .property-help {
            color: #64748b;
            font-size: 13px;
            line-height: 1.4;
        }
        .flow-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1600;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, .36);
        }
        .flow-modal-backdrop.open {
            display: flex;
        }
        .flow-modal {
            width: min(940px, 100%);
            max-height: min(720px, 92vh);
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 18px 48px rgba(15, 23, 42, .24);
            overflow: hidden;
        }
        .flow-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 13px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .flow-modal-header h3 {
            margin: 0;
            font-size: 16px;
            color: #17202a;
        }
        .flow-modal-body {
            overflow: auto;
            padding: 14px 16px 16px;
        }
        .posto-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .posto-table th,
        .posto-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }
        .posto-table th {
            background: #f8fafc;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
        }
        .posto-table .empty-cell {
            color: #64748b;
            text-align: center;
        }
        .posto-panel-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .posto-panel-table th,
        .posto-panel-table td {
            padding: 8px 6px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }
        .posto-panel-table th {
            color: #475569;
            font-size: 10px;
            text-transform: uppercase;
        }
        .posto-panel-table input {
            width: 58px;
            min-height: 30px;
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            color: #17202a;
            font: inherit;
        }
        .posto-panel-table .posto-name-cell {
            min-width: 118px;
            font-weight: 800;
        }
        .posto-panel-table .posto-activities-cell {
            color: #475569;
            line-height: 1.35;
        }
        .posto-editor-grid {
            display: grid;
            gap: 14px;
        }
        .posto-editor-field {
            display: grid;
            gap: 6px;
        }
        .posto-editor-field label {
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .posto-editor-field input {
            width: 100%;
            min-height: 36px;
            padding: 7px 9px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            color: #17202a;
            font: inherit;
        }
        .atividade-combo {
            position: relative;
            min-height: 96px;
            padding: 8px;
            border: 2px solid #16a34a;
            border-radius: 8px;
            background: #fff;
        }
        .atividade-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 7px;
        }
        .atividade-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            max-width: 100%;
            min-height: 30px;
            padding: 5px 9px;
            border: 1px solid #bbf7d0;
            border-radius: 7px;
            background: #f0fdf4;
            color: #049342;
            font-size: 13px;
            font-weight: 700;
        }
        .atividade-chip button {
            border: 0;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            font-size: 15px;
            line-height: 1;
        }
        .atividade-search {
            border: 0 !important;
            min-height: 34px !important;
            padding: 4px 5px !important;
            outline: none;
            font-size: 15px !important;
        }
        .atividade-dropdown {
            display: none;
            margin-top: 6px;
            max-height: 190px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .14);
        }
        .atividade-combo.is-open .atividade-dropdown {
            display: block;
        }
        .atividade-option {
            display: grid;
            gap: 2px;
            width: 100%;
            padding: 10px 12px;
            border: 0;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
            color: #17202a;
            text-align: left;
            cursor: pointer;
        }
        .atividade-option:hover,
        .atividade-option.active {
            background: #f8fafc;
        }
        .atividade-option strong {
            font-size: 14px;
            font-weight: 700;
        }
        .atividade-option span {
            color: #64748b;
            font-size: 12px;
        }
        .posto-editor-metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }
        .posto-editor-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding-top: 4px;
        }
        .empty-state {
            padding: 30px;
            text-align: center;
            color: #64748b;
            background: #fff;
        }
        .drawflow-node {
            min-width: 160px;
            min-height: 90px;
            padding: 6px !important;
            border: 1px solid #00a6b4 !important;
            border-radius: 5px !important;
            background: #e0f7fa !important;
            color: #075985;
            text-align: center;
            font-weight: 700;
        }
        .drawflow-node.selected {
            border-color: #f59e0b !important;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, .25) !important;
        }
        .drawflow-node .inputs,
        .drawflow-node .outputs { background: #00a6b4 !important; }
        .node-content { padding: 5px; font-size: 12px; }
        .node-content strong { display: block; margin-bottom: 5px; color: #17202a; }
        .node-icon { margin-bottom: 7px; color: #0284c7; font-size: 20px; }
        .node-actions {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 6px;
        }
        .node-actions button {
            padding: 3px 7px;
            border: 0;
            border-radius: 3px;
            color: #fff;
            cursor: pointer;
            font-size: 10px;
        }
        .node-actions button i { pointer-events: none; }
        .edit-btn { background: #1f9d55; }
        .delete-btn { background: #d64545; }
        .add-derived-btn { background: #1f6feb; }
        .drawflow-node.decision_exclusive,
        .drawflow-node.decision_parallel {
            --gateway-node-size: 120px;
            --gateway-diamond-size: 78px;
            --gateway-connector-size: 22px;
            --gateway-connector-offset: calc(
                ((var(--gateway-node-size) - (var(--gateway-diamond-size) * 1.41421356)) / 2)
                - (var(--gateway-connector-size) / 2)
            );
            min-width: 120px !important;
            min-height: 120px !important;
            width: var(--gateway-node-size) !important;
            height: var(--gateway-node-size) !important;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
        .gateway-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gateway-diamond {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 78px;
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: translate(-50%, -50%) rotate(45deg);
            border: 2px solid #f59e0b;
            background: #fff7cc;
            box-shadow: 2px 2px 5px rgba(0,0,0,.12);
        }
        .gateway-diamond i {
            transform: rotate(-45deg);
            color: #b45309;
            font-size: 18px;
        }
        .decision_parallel .gateway-diamond {
            border-color: #22a06b;
            background: #dff7ea;
        }
        .decision_parallel .gateway-diamond i { color: #147d52; }
        .gateway-label {
            position: absolute;
            top: calc(50% + 40px);
            left: 50%;
            width: 120px;
            transform: translateX(-50%);
            color: #17202a;
            text-align: center;
            font-size: 11px;
            overflow-wrap: break-word;
        }
        .drawflow-node.decision_exclusive .inputs,
        .drawflow-node.decision_parallel .inputs,
        .drawflow-node.decision_exclusive .outputs,
        .drawflow-node.decision_parallel .outputs {
            position: absolute !important;
            top: 50% !important;
            z-index: 8;
            width: var(--gateway-connector-size) !important;
            height: var(--gateway-connector-size) !important;
            transform: translateY(-50%) !important;
            margin: 0 !important;
        }
        .drawflow-node.decision_exclusive .inputs,
        .drawflow-node.decision_parallel .inputs {
            left: var(--gateway-connector-offset) !important;
        }
        .drawflow-node.decision_exclusive .outputs,
        .drawflow-node.decision_parallel .outputs {
            right: var(--gateway-connector-offset) !important;
        }
        .drawflow-node.decision_exclusive .input,
        .drawflow-node.decision_parallel .input,
        .drawflow-node.decision_exclusive .output,
        .drawflow-node.decision_parallel .output {
            position: absolute !important;
            top: 50% !important;
            width: var(--gateway-connector-size) !important;
            height: var(--gateway-connector-size) !important;
            margin: 0 !important;
            transform: translateY(-50%) !important;
        }
        .drawflow-node.decision_exclusive .input,
        .drawflow-node.decision_parallel .input {
            left: var(--gateway-connector-offset) !important;
        }
        .drawflow-node.decision_exclusive .output,
        .drawflow-node.decision_parallel .output {
            right: var(--gateway-connector-offset) !important;
        }
        @media (max-width: 900px) {
            .title-bar {
                align-items: stretch;
                flex-direction: column;
                min-height: auto;
                padding-left: 54px;
            }
            .title-meta { flex-wrap: wrap; }
            .sector-row {
                width: 100%;
                min-width: 0;
                margin-left: 0;
            }
            .flow-workbench { grid-template-columns: 1fr; }
            .flow-workbench.properties-collapsed { grid-template-columns: 1fr; }
            .properties-panel { border-left: 0; border-top: 1px solid #cbd5e1; }
            .flow-workbench.properties-collapsed .properties-panel { max-height: 46px; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/menu.php'; ?>
    <button id="menuToggleBtn" class="menu-toggle-btn" type="button" title="Recolher menu" aria-label="Recolher menu">
        <i class="fa-solid fa-bars"></i>
    </button>

    <main class="cod-app-shell">
    <div class="top-shell">
        <div class="title-bar">
            <div class="title-meta">
                <h1>Fluxo Teste02</h1>
                <span class="module-badge">REFORMULACAO</span>
                <span class="memory-badge">MEMORIA isolada</span>
            </div>
            <div class="sector-row">
                <strong>Setor</strong>
                <select id="setor_select" onchange="changeSetor(this.value)">
                    <?php foreach ($setores as $setor): ?>
                        <option value="<?php echo htmlspecialchars($setor['id']); ?>" <?php echo (($setor['id'] ?? '') === $setor_id_ativo) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($setor['nome'] ?? 'Setor sem nome'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="icon-btn btn-add" type="button" onclick="crudAction('add_setor')" title="Novo setor no cadastro mestre"><i class="fa-solid fa-plus"></i></button>
                <button class="icon-btn btn-danger" type="button" onclick="crudAction('delete_setor')" title="Excluir setor"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>

        <div class="line-tabs">
            <?php foreach ($linhas_do_setor as $linha): ?>
                <a class="line-tab <?php echo (($linha['id'] ?? '') === $linha_id_ativo) ? 'active' : ''; ?>"
                   href="?setor_id=<?php echo urlencode($setor_id_ativo ?? ''); ?>&linha_id=<?php echo urlencode($linha['id'] ?? ''); ?>">
                    <i class="fa-solid fa-diagram-project"></i>
                    <?php echo htmlspecialchars($linha['nome'] ?? 'Linha sem nome'); ?>
                </a>
                <?php if (($linha['id'] ?? '') === $linha_id_ativo): ?>
                    <span class="line-actions">
                        <i class="fa-solid fa-pen" onclick="crudAction('rename_linha', '<?php echo htmlspecialchars($linha['id']); ?>')" title="Renomear linha"></i>
                        <i class="fa-solid fa-trash" onclick="crudAction('delete_linha', '<?php echo htmlspecialchars($linha['id']); ?>')" title="Excluir linha"></i>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
            <button class="line-tab" type="button" onclick="crudAction('add_linha')">
                <i class="fa-solid fa-plus"></i> Nova Linha
            </button>
            <a class="line-tab" href="cadastros-basicos/index.php?tipo=linhas" title="Abrir cadastro mestre de linhas">
                <i class="fa-solid fa-database"></i> Mestre
            </a>
        </div>

    <?php if (!$setor_ativo || !$linha_ativa): ?>
    </div>
        <div class="empty-state">
            Crie um setor e uma linha para iniciar um fluxo independente.
        </div>
    <?php else: ?>
        <div class="toolbar">
            <button class="btn-primary" type="button" onclick="addNodeToFlow()"><i class="fa-solid fa-plus"></i> Adicionar Posto</button>
            <a class="btn-primary" href="cadastros-basicos/index.php?tipo=postos" style="text-decoration:none;"><i class="fa-solid fa-database"></i> + Novo Mestre</a>
            <button class="btn-add" type="button" onclick="saveFlowState()"><i class="fa-solid fa-floppy-disk"></i> Salvar Fluxo</button>
            <button class="btn-muted" type="button" onclick="clearFlow()"><i class="fa-solid fa-trash"></i> Limpar Fluxo</button>
            <div class="toolbar-spacer"></div>
            <div class="zoom-controls" aria-label="Controle de zoom">
                <button type="button" onclick="adjustFlowZoom(-0.1)" title="Reduzir zoom em 10%" aria-label="Reduzir zoom">-</button>
                <span id="zoomValue" class="zoom-value">100%</span>
                <button type="button" onclick="adjustFlowZoom(0.1)" title="Aumentar zoom em 10%" aria-label="Aumentar zoom">+</button>
            </div>
            <span id="saveStatus">Sincronizado</span>
            <span>Nos no fluxo: <strong id="nodeCount">0</strong></span>
            <button class="toolbar-mini-btn" type="button" onclick="openPostoResumo()" title="Consultar tabela posto x quantidade x ritmo">
                <i class="fa-solid fa-table-list"></i> Tabela postos
            </button>
        </div>
    </div>
        <div class="flow-workbench" id="flowWorkbench">
            <div id="drawflow"></div>
            <aside class="properties-panel">
                <div class="properties-header">
                    <h2 id="propertiesTitle">Detalhes - Posto</h2>
                    <button id="propertiesToggleBtn" class="properties-toggle" type="button" title="Recolher detalhes" aria-label="Recolher detalhes" aria-expanded="true">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
                <div id="propertiesContent" class="property-help">
                    Selecione um posto ou gateway no fluxo para ver as propriedades BPM.
                </div>
            </aside>
        </div>
        <div id="postoResumoModal" class="flow-modal-backdrop" onclick="closePostoResumo(event)">
            <div class="flow-modal" role="dialog" aria-modal="true" aria-labelledby="postoResumoTitle" onclick="event.stopPropagation()">
                <div class="flow-modal-header">
                    <h3 id="postoResumoTitle">Posto x quantidade x ritmo</h3>
                    <button class="icon-btn btn-muted" type="button" onclick="closePostoResumo()" title="Fechar"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="flow-modal-body" id="postoResumoBody"></div>
            </div>
        </div>
        <div id="postoEditorModal" class="flow-modal-backdrop">
            <div class="flow-modal" role="dialog" aria-modal="true" aria-labelledby="postoEditorTitle" onclick="event.stopPropagation()">
                <div class="flow-modal-header">
                    <h3 id="postoEditorTitle">Propriedades do posto</h3>
                    <button class="icon-btn btn-muted" type="button" onclick="cancelPostoEditor()" title="Fechar"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="flow-modal-body">
                    <div class="posto-editor-grid">
                        <div class="posto-editor-field">
                            <label for="postoEditorNome">Nome do posto</label>
                            <input id="postoEditorNome" type="text" autocomplete="off">
                        </div>
                        <div class="posto-editor-field">
                            <label for="postoEditorSearch">Atividades da cronoanalise</label>
                            <div id="postoEditorCombo" class="atividade-combo">
                                <div id="postoEditorChips" class="atividade-chips"></div>
                                <input id="postoEditorSearch" class="atividade-search" type="text" autocomplete="off" placeholder="Selecione uma opcao">
                                <div id="postoEditorDropdown" class="atividade-dropdown"></div>
                            </div>
                        </div>
                        <div class="posto-editor-metrics">
                            <div class="posto-editor-field">
                                <label for="postoEditorPessoas">Nº de pessoas</label>
                                <input id="postoEditorPessoas" type="number" min="1" step="1">
                            </div>
                            <div class="posto-editor-field">
                                <label for="postoEditorTc">TC</label>
                                <input id="postoEditorTc" type="text" readonly>
                            </div>
                            <div class="posto-editor-field">
                                <label for="postoEditorTcContentor">TC/ctt</label>
                                <input id="postoEditorTcContentor" type="text" readonly>
                            </div>
                            <div class="posto-editor-field">
                                <label for="postoEditorRitmo">Ritmo do posto</label>
                                <input id="postoEditorRitmo" type="text" readonly>
                            </div>
                        </div>
                        <div class="posto-editor-actions">
                            <button class="btn-muted" type="button" onclick="cancelPostoEditor()">Cancelar</button>
                            <button class="btn-primary" type="button" onclick="confirmPostoEditor()">Aplicar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    </main>

    <script>
        const apiUrl = 'api_fluxo.php';
        const moduleScope = <?php echo json_encode(COD_12_05_MODULE_SCOPE); ?>;
        const moduleLabel = <?php echo json_encode(COD_12_05_MODULE_LABEL); ?>;
        const legacyLabel = <?php echo json_encode(COD_12_05_LEGACY_LABEL); ?>;
        const currentSetorId = <?php echo json_encode($setor_id_ativo ?? ''); ?>;
        const currentLinhaId = <?php echo json_encode($linha_id_ativo ?? ''); ?>;
        const sharedPostos = <?php echo json_encode($shared_postos, JSON_UNESCAPED_UNICODE); ?>;
        const cronoAtividades = <?php echo json_encode($crono_atividades, JSON_UNESCAPED_UNICODE); ?>;

        let editor = null;
        let mockPostos = [];
        let mockConnections = [];
        let isSaving = false;
        let isLoading = false;
        let isDirty = false;
        let saveTimer = null;
        let currentSavePromise = null;
        let pendingSaveAfterCurrent = false;
        let postoEditorResolver = null;
        let postoEditorState = { selected: [] };

        setupRetractableMenu();
        setupRetractableProperties();

        if (document.getElementById('drawflow')) {
            window.addEventListener('load', initDrawflow);
            window.addEventListener('beforeunload', flushFlowSaveBeforeUnload);
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

        function setupRetractableProperties() {
            const storageKey = 'cod_12_05_properties_collapsed';
            const workbench = document.getElementById('flowWorkbench');
            const button = document.getElementById('propertiesToggleBtn');
            const icon = button ? button.querySelector('i') : null;
            if (!workbench || !button) return;

            function applyState(collapsed) {
                workbench.classList.toggle('properties-collapsed', collapsed);
                button.title = collapsed ? 'Expandir detalhes' : 'Recolher detalhes';
                button.setAttribute('aria-label', button.title);
                button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                if (icon) {
                    icon.className = collapsed ? 'fa-solid fa-chevron-left' : 'fa-solid fa-chevron-right';
                }
                if (editor) {
                    setTimeout(function() {
                        mockPostos.forEach(function(posto) {
                            editor.updateConnectionNodes('node-' + posto.drawflow_id);
                        });
                    }, 230);
                }
            }

            applyState(localStorage.getItem(storageKey) === '1');

            button.addEventListener('click', function() {
                const collapsed = !workbench.classList.contains('properties-collapsed');
                localStorage.setItem(storageKey, collapsed ? '1' : '0');
                applyState(collapsed);
            });
        }

        function setPropertiesPanelCollapsed(collapsed) {
            const workbench = document.getElementById('flowWorkbench');
            const button = document.getElementById('propertiesToggleBtn');
            const icon = button ? button.querySelector('i') : null;
            if (!workbench || !button) return;

            workbench.classList.toggle('properties-collapsed', collapsed);
            button.title = collapsed ? 'Expandir detalhes' : 'Recolher detalhes';
            button.setAttribute('aria-label', button.title);
            button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (icon) {
                icon.className = collapsed ? 'fa-solid fa-chevron-left' : 'fa-solid fa-chevron-right';
            }
            localStorage.setItem('cod_12_05_properties_collapsed', collapsed ? '1' : '0');
        }

        function changeSetor(setorId) {
            window.location.href = '?setor_id=' + encodeURIComponent(setorId);
        }

        async function initDrawflow() {
            const container = document.getElementById('drawflow');
            editor = new Drawflow(container);
            editor.reroute = true;
            editor.zoom_max = 2;
            editor.zoom_min = 0.3;
            editor.start();

            isLoading = true;
            try {
                const response = await fetch(apiUrl + '?action=load_drawflow_data&setor_id=' + encodeURIComponent(currentSetorId) + '&linha_id=' + encodeURIComponent(currentLinhaId));
                const data = await response.json();
                if (data.status === 'success' && data.drawflow_data && isValidDrawflowData(data.drawflow_data)) {
                    editor.import(data.drawflow_data);
                    rebuildLocalStateFromEditor();
                    refreshImportedNodes();
                    applySavedViewport(data.drawflow_data);
                } else {
                    mockPostos = [];
                    mockConnections = [];
                }
            } catch (error) {
                console.error('Erro ao carregar fluxo isolado:', error);
            } finally {
                isLoading = false;
            }

            setupDrawflowEvents();
            updateNodeCount();
            updateZoomDisplay();
        }

        function isValidDrawflowData(flowData) {
            return !!(flowData && flowData.drawflow && flowData.drawflow.Home && flowData.drawflow.Home.data);
        }

        function getCurrentViewport() {
            return {
                zoom: clampNumber(editor.zoom, editor.zoom_min || 0.3, editor.zoom_max || 2, 1),
                x: normalizePosition(editor.canvas_x, 0),
                y: normalizePosition(editor.canvas_y, 0)
            };
        }

        function applySavedViewport(flowData) {
            const viewport = flowData.viewport_state || flowData.viewport || null;
            if (!viewport || !editor) return;

            const zoom = clampNumber(viewport.zoom, editor.zoom_min || 0.3, editor.zoom_max || 2, 1);
            const x = normalizePosition(viewport.x, 0);
            const y = normalizePosition(viewport.y, 0);

            editor.zoom = zoom;
            editor.canvas_x = x;
            editor.canvas_y = y;

            if (editor.precanvas) {
                editor.precanvas.style.transform = 'translate(' + x + 'px, ' + y + 'px) scale(' + zoom + ')';
            }
            updateZoomDisplay();
        }

        function clampNumber(value, min, max, fallback) {
            const parsed = parseFloat(value);
            if (!Number.isFinite(parsed)) return fallback;
            return Math.min(max, Math.max(min, parsed));
        }

        function adjustFlowZoom(delta) {
            if (!editor) return;
            const current = clampNumber(editor.zoom, editor.zoom_min || 0.3, editor.zoom_max || 2, 1);
            const next = clampNumber(Math.round((current + delta) * 10) / 10, editor.zoom_min || 0.3, editor.zoom_max || 2, 1);
            setFlowZoom(next);
            scheduleFlowSave(100);
        }

        function setFlowZoom(zoom) {
            if (!editor) return;
            const next = clampNumber(zoom, editor.zoom_min || 0.3, editor.zoom_max || 2, 1);
            editor.zoom = next;
            if (editor.precanvas) {
                editor.precanvas.style.transform = 'translate(' + editor.canvas_x + 'px, ' + editor.canvas_y + 'px) scale(' + next + ')';
            }
            updateZoomDisplay();
        }

        function updateZoomDisplay() {
            const label = document.getElementById('zoomValue');
            if (!label || !editor) return;
            label.textContent = Math.round(clampNumber(editor.zoom, editor.zoom_min || 0.3, editor.zoom_max || 2, 1) * 100) + '%';
        }

        function rebuildLocalStateFromEditor() {
            mockPostos = [];
            mockConnections = [];
            const exportData = editor.export();
            const nodes = exportData.drawflow.Home.data || {};

            Object.keys(nodes).forEach(drawflowId => {
                const node = nodes[drawflowId];
                const data = node.data || {};
                mockPostos.push({
                    id: data.id || 'node_' + drawflowId,
                    name: data.name || node.name || 'No sem nome',
                    type: data.type || node.class || node.name || 'node',
                    tc: parseLocalNumber(data.tc || 0, 0),
                    tcContentor: parseLocalNumber(data.tcContentor || data.tc_contentor || data.ritmo || data.tc || 0, 0),
                    icon: data.icon || 'fa-industry',
                    atividades: normalizeNodeAtividades(data.atividades || []),
                    pessoas: parseInt(data.pessoas || 0, 10) || 0,
                    ritmo: parseLocalNumber(data.ritmo || 0, 0),
                    drawflow_id: parseInt(drawflowId, 10),
                    x: normalizePosition(node.pos_x, 100),
                    y: normalizePosition(node.pos_y, 160),
                    autoPlace: data.autoPlace !== false
                });

                Object.keys(node.outputs || {}).forEach(outputName => {
                    (node.outputs[outputName].connections || []).forEach(conn => {
                        mockConnections.push({
                            sourceDfId: parseInt(drawflowId, 10),
                            targetDfId: parseInt(conn.node, 10),
                            outputName,
                            inputName: conn.output || conn.input || 'input_1'
                        });
                    });
                });
            });
        }

        function refreshImportedNodes() {
            mockPostos.forEach(posto => {
                const nodeEl = document.getElementById('node-' + posto.drawflow_id);
                if (!nodeEl) return;

                nodeEl.style.left = posto.x + 'px';
                nodeEl.style.top = posto.y + 'px';

                const content = nodeEl.querySelector('.drawflow_content_node');
                if (content) {
                    content.innerHTML = generateNodeHtml(posto);
                }
            });
        }

        function normalizePosition(value, fallback) {
            const parsed = parseFloat(value);
            return Number.isFinite(parsed) ? parsed : fallback;
        }

        function parseLocalNumber(value, fallback = 0) {
            const raw = String(value ?? '').trim();
            const normalized = raw.includes(',') ? raw.replace(/\./g, '').replace(',', '.') : raw;
            const parsed = parseFloat(normalized);
            return Number.isFinite(parsed) ? parsed : fallback;
        }

        async function requestId(prefix = 'cod_12_05_node') {
            const response = await fetch(apiUrl + '?action=generate_id&prefix=' + encodeURIComponent(prefix));
            const data = await response.json();
            if (data.status !== 'success') {
                throw new Error(data.message || 'Falha ao gerar ID');
            }
            return data.id;
        }

        async function addSimpleNode(name, tc, x, y, saveAfter = true) {
            const uniqueId = await requestId('cod_12_05_node');
            const nodeData = { id: uniqueId, name, tc, tcContentor: tc, type: 'node', atividades: [], pessoas: 0, ritmo: 0, autoPlace: true };
            const htmlContent = generateNodeHtml(nodeData);
            const drawflowId = editor.addNode('node', 1, 1, x, y, 'node', nodeData, htmlContent);

            mockPostos.push({ ...nodeData, drawflow_id: drawflowId, x, y });
            updateNodeCount();

            if (saveAfter) {
                await saveFlowState();
            }

            return uniqueId;
        }

        async function addNodeToFlow(name = null, type = 'node', parentId = null) {
            let selectedMaster = null;
            let nodeName = name;
            let tc = 0;
            if (!nodeName && type === 'node' && sharedPostos.length > 0) {
                const options = sharedPostos.map((posto, index) => `${index + 1} - ${posto.nome}`).join('\n');
                const choice = prompt('Selecione um posto mestre pelo numero ou digite um novo nome:\n\n' + options, 'Novo Posto');
                if (!choice || !choice.trim()) return null;
                const number = parseInt(choice, 10);
                if (Number.isInteger(number) && sharedPostos[number - 1]) {
                    selectedMaster = sharedPostos[number - 1];
                    nodeName = selectedMaster.nome;
                    tc = parseLocalNumber(selectedMaster.tempo_ciclo || 0, 0);
                } else {
                    nodeName = choice.trim();
                }
            } else {
                nodeName = nodeName || prompt(type === 'node' ? 'Nome do novo posto:' : 'Nome da decisao/gateway:');
            }
            if (!nodeName || !nodeName.trim()) return null;

            let x = 120;
            let y = 160;

            if (parentId) {
                const parent = mockPostos.find(p => p.id == parentId);
                if (parent) {
                    const placement = getNextChildPlacement(parent);
                    x = placement.x;
                    y = placement.y;
                }
            } else if (mockPostos.length > 0) {
                const rightmost = mockPostos.reduce((prev, current) => ((prev.x || 0) > (current.x || 0) ? prev : current));
                x = (rightmost.x || 120) + 300;
                y = rightmost.y || 160;
            }

            const intrinsic = type === 'node' ? await editPostoIntrinsics({}, nodeName.trim()) : { atividades: [], pessoas: 0, tc: 0, ritmo: 0 };
            if (!intrinsic) return null;
            nodeName = (intrinsic.name || nodeName).trim();
            tc = intrinsic.tc;
            if (type === 'node' && !selectedMaster) {
                selectedMaster = await savePostoMaster('', nodeName.trim(), tc);
            }
            const uniqueId = selectedMaster?.id || await requestId(type === 'node' ? 'cod_12_05_node' : 'cod_12_05_gateway');
            const nodeData = {
                id: uniqueId,
                name: nodeName.trim(),
                type,
                tc,
                tcContentor: intrinsic.tcContentor || tc,
                atividades: intrinsic.atividades,
                pessoas: intrinsic.pessoas,
                ritmo: intrinsic.ritmo,
                autoPlace: true
            };
            const htmlContent = generateNodeHtml(nodeData);
            const drawflowId = editor.addNode(type, 1, 1, x, y, type, nodeData, htmlContent);

            mockPostos.push({ ...nodeData, drawflow_id: drawflowId, x, y });

            if (parentId) {
                const parent = mockPostos.find(p => p.id == parentId);
                if (parent && parent.drawflow_id) {
                    editor.addConnection(parent.drawflow_id, drawflowId, 'output_1', 'input_1');
                    registerConnection(parent.drawflow_id, drawflowId, 'output_1', 'input_1');
                    rebalanceChildren(parent.id);
                }
            }

            updateNodeCount();
            await saveFlowState();
            return uniqueId;
        }

        async function savePostoMaster(id, nome, tc) {
            const formData = new FormData();
            formData.append('action', 'save_posto_master');
            formData.append('module_scope', moduleScope);
            formData.append('posto_id', id || '');
            formData.append('nome', nome || '');
            formData.append('tempo_ciclo', String(tc || 0));
            try {
                const response = await fetch(apiUrl, { method: 'POST', body: formData });
                const data = await response.json();
                return data.status === 'success' ? data.posto : null;
            } catch (error) {
                return null;
            }
        }

        function getNextChildPlacement(parent) {
            return {
                x: normalizePosition(parent.x, 120) + 300,
                y: normalizePosition(parent.y, 160)
            };
        }

        function getOutgoingChildren(parentId) {
            const parent = mockPostos.find(p => p.id == parentId);
            if (!parent) return [];

            return mockConnections
                .filter(conn => conn.sourceDfId == parent.drawflow_id)
                .map(conn => mockPostos.find(p => p.drawflow_id == conn.targetDfId))
                .filter(Boolean);
        }

        function registerConnection(sourceDfId, targetDfId, outputName = 'output_1', inputName = 'input_1') {
            const source = parseInt(sourceDfId, 10);
            const target = parseInt(targetDfId, 10);
            const exists = mockConnections.some(conn => conn.sourceDfId === source && conn.targetDfId === target);

            if (!exists) {
                mockConnections.push({ sourceDfId: source, targetDfId: target, outputName, inputName });
            }
        }

        function getSymmetricOffsets(count, gap = 140) {
            if (count <= 1) return [0];

            const offsets = [];
            const start = -((count - 1) * gap) / 2;
            for (let i = 0; i < count; i++) {
                offsets.push(start + (i * gap));
            }
            return offsets;
        }

        function rebalanceChildren(parentId) {
            const parent = mockPostos.find(p => p.id == parentId);
            if (!parent) return;

            const children = getOutgoingChildren(parentId).filter(child => child.autoPlace !== false);
            if (children.length <= 1) return;

            const parentX = normalizePosition(parent.x, 120);
            const parentY = normalizePosition(parent.y, 160);
            const offsets = getSymmetricOffsets(children.length);

            children.forEach((child, index) => {
                child.x = parentX + 300;
                child.y = parentY + offsets[index];
                updateNodePosition(child);
            });
        }

        function updateNodePosition(posto) {
            const nodeEl = document.getElementById('node-' + posto.drawflow_id);
            if (nodeEl) {
                nodeEl.style.left = posto.x + 'px';
                nodeEl.style.top = posto.y + 'px';
            }

            const node = editor.getNodeFromId(posto.drawflow_id);
            if (node) {
                node.pos_x = posto.x;
                node.pos_y = posto.y;
            }

            if (typeof editor.updateConnectionNodes === 'function') {
                editor.updateConnectionNodes('node-' + posto.drawflow_id);
            }
        }

        function generateNodeHtml(node) {
            const id = escapeJsString(node.id);
            const safeName = escapeHtml(node.name || 'No sem nome');
            const tc = Number.isFinite(parseLocalNumber(node.tc, NaN)) ? parseLocalNumber(node.tc, 0) : 0;
            const tcContentor = Number.isFinite(parseLocalNumber(node.tcContentor || node.tc_contentor, NaN)) ? parseLocalNumber(node.tcContentor || node.tc_contentor, 0) : tc;
            const icon = escapeHtml(node.icon || 'fa-industry');
            const pessoas = parseInt(node.pessoas || 0, 10) || 0;
            const ritmo = Number.isFinite(parseLocalNumber(node.ritmo, NaN)) ? parseLocalNumber(node.ritmo, 0) : 0;

            if (node.type === 'decision_exclusive' || node.type === 'decision_parallel') {
                const icon = node.type === 'decision_parallel' ? 'fa-plus' : 'fa-xmark';
                return `
                    <div class="gateway-container">
                        <div class="gateway-diamond"><i class="fa-solid ${icon}"></i></div>
                        <div class="gateway-label"><strong>${safeName}</strong></div>
                        <div class="node-actions">
                            <button type="button" class="edit-btn" onclick="event.stopPropagation(); editNodeInFlow('${id}')" title="Editar"><i class="fa-solid fa-pen"></i></button>
                            <button type="button" class="add-derived-btn" onclick="event.stopPropagation(); showDeriveOptions('${id}')" title="Derivar"><i class="fa-solid fa-plus"></i></button>
                            <button type="button" class="delete-btn" onclick="event.stopPropagation(); removeNodeFromFlow('${id}')" title="Remover"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                `;
            }

            return `
                <div class="node-content">
                    <div class="node-icon"><i class="fa-solid ${icon}"></i></div>
                    <strong>${safeName}</strong>
                    <div>TC: <span class="tc-value">${tc}</span>s</div>
                    <div>TC/ctt: <span class="tc-contentor-value">${tcContentor}</span>s</div>
                    <div>Pessoas: ${pessoas || '-'}</div>
                    <div>Ritmo: ${ritmo || '-'}s</div>
                    <div class="node-actions">
                        <button type="button" class="edit-btn" onclick="event.stopPropagation(); editNodeInFlow('${id}')" title="Editar"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="add-derived-btn" onclick="event.stopPropagation(); showDeriveOptions('${id}')" title="Derivar"><i class="fa-solid fa-plus"></i></button>
                        <button type="button" class="delete-btn" onclick="event.stopPropagation(); removeNodeFromFlow('${id}')" title="Remover"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
            `;
        }

        function escapeJsString(value) {
            return String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\r?\n/g, ' ');
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function normalizeNodeAtividades(atividades) {
            if (!Array.isArray(atividades)) return [];
            return atividades
                .map(item => {
                    if (typeof item === 'string') {
                        return { id: item, nome: item, tipo: '', ritmo: 0 };
                    }
                    return {
                        id: String(item.id || item.nome || ''),
                        nome: String(item.nome || item.atividade || item.descricao || ''),
                        tipo: String(item.tipo || item.tipo_operacao || item.tipo_atividade || ''),
                        tc: parseLocalNumber(item.tc || item.tempo_operacao_principal || item.tempo_unitario || item.tempo_total_s || 0, 0),
                        tcContentor: parseLocalNumber(item.tcContentor || item.tc_contentor || item.ritmo || item.ritmo_contentor || 0, 0),
                        ritmo: parseLocalNumber(item.ritmo || item.tcContentor || item.tc_contentor || item.ritmo_contentor || 0, 0)
                    };
                })
                .filter(item => item.nome !== '');
        }

        function formatAtividadeOption(atividade, index) {
            const tipo = atividade.tipo ? ' / ' + atividade.tipo : '';
            const tc = atividade.tc > 0 ? ' / TC ' + formatSeconds(atividade.tc) : '';
            const ritmo = atividade.tcContentor > 0 ? ' / TC/ctt ' + formatSeconds(atividade.tcContentor) : '';
            return `${index + 1} - ${atividade.nome}${tipo}${tc}${ritmo}`;
        }

        function formatSeconds(value) {
            const num = parseLocalNumber(value, 0);
            return num > 0 ? num.toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + 's' : '0s';
        }

        function calcTcFromAtividades(atividades) {
            return normalizeNodeAtividades(atividades).reduce((sum, item) => sum + parseLocalNumber(item.tc || 0, 0), 0);
        }

        function calcTcContentorFromAtividades(atividades) {
            return normalizeNodeAtividades(atividades).reduce((sum, item) => {
                const tcContentor = parseLocalNumber(item.tcContentor || item.ritmo || 0, 0);
                const tc = parseLocalNumber(item.tc || 0, 0);
                return sum + (tcContentor > 0 ? tcContentor : tc);
            }, 0);
        }

        function calcRitmoPosto(tcContentor, pessoas) {
            const qtd = parseInt(pessoas || 0, 10) || 0;
            return qtd > 0 ? parseLocalNumber(tcContentor, 0) / qtd : 0;
        }

        function normalizeSearchText(value) {
            return String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();
        }

        function editPostoIntrinsics(posto = {}, nodeName = '') {
            return new Promise(resolve => {
                const modal = document.getElementById('postoEditorModal');
                const nomeInput = document.getElementById('postoEditorNome');
                const search = document.getElementById('postoEditorSearch');
                const pessoasInput = document.getElementById('postoEditorPessoas');
                const title = document.getElementById('postoEditorTitle');
                if (!modal || !nomeInput || !search || !pessoasInput || !title) {
                    resolve(null);
                    return;
                }

                if (!cronoAtividades.length) {
                    alert('Nenhuma cronoanalise cadastrada para listar atividades.');
                }

                postoEditorResolver = resolve;
                postoEditorState = {
                    editingPostoId: posto.id || null,
                    selected: normalizeNodeAtividades(posto.atividades || []),
                    query: '',
                    dropdownOpen: true
                };

                title.textContent = 'Propriedades do posto' + (nodeName ? ': ' + nodeName : '');
                nomeInput.value = nodeName || posto.name || '';
                pessoasInput.value = parseInt(posto.pessoas || 1, 10) || 1;
                search.value = '';
                modal.classList.add('open');
                renderPostoEditor();
                setTimeout(() => nomeInput.focus(), 50);
            });
        }

        function selectedAtividadeIds() {
            return new Set((postoEditorState.selected || []).map(item => String(item.id)));
        }

        function renderPostoEditor() {
            updatePostoEditorDropdownState();
            renderPostoEditorChips();
            renderPostoEditorDropdown();
            updatePostoEditorMetrics();
        }

        function setPostoEditorDropdownOpen(open) {
            postoEditorState.dropdownOpen = !!open;
            updatePostoEditorDropdownState();
            if (postoEditorState.dropdownOpen) {
                renderPostoEditorDropdown();
            }
        }

        function updatePostoEditorDropdownState() {
            const combo = document.getElementById('postoEditorCombo');
            const dropdown = document.getElementById('postoEditorDropdown');
            const isOpen = !!postoEditorState.dropdownOpen;
            if (combo) combo.classList.toggle('is-open', isOpen);
            if (dropdown) dropdown.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        }

        function renderPostoEditorChips() {
            const chips = document.getElementById('postoEditorChips');
            if (!chips) return;
            chips.innerHTML = (postoEditorState.selected || []).map(item => `
                <span class="atividade-chip">
                    ${escapeHtml(item.nome)}
                    <button type="button" onclick="removePostoEditorAtividade('${escapeJsString(item.id)}')" title="Remover atividade">&times;</button>
                </span>
            `).join('');
        }

        function renderPostoEditorDropdown() {
            const dropdown = document.getElementById('postoEditorDropdown');
            const search = document.getElementById('postoEditorSearch');
            if (!dropdown || !search) return;

            const query = normalizeSearchText(search.value).trim();
            const selected = selectedAtividadeIds();
            const rows = cronoAtividades
                .filter(item => !selected.has(String(item.id)))
                .filter(item => {
                    if (!query) return true;
                    return normalizeSearchText(item.nome + ' ' + item.tipo + ' ' + item.posto + ' ' + item.linha).includes(query);
                })
                .slice(0, 80);

            dropdown.innerHTML = rows.length ? rows.map(item => `
                <button class="atividade-option" type="button" onclick="addPostoEditorAtividade('${escapeJsString(item.id)}')">
                    <strong>${escapeHtml(item.nome)}</strong>
                    <span>${escapeHtml(item.tipo || 'Cronoanalise')} | TC: ${escapeHtml(formatSeconds(item.tc || 0))} | TC/ctt: ${escapeHtml(formatSeconds(item.tcContentor || item.ritmo || 0))}</span>
                </button>
            `).join('') : '<div class="atividade-option"><strong>Nenhuma atividade encontrada</strong><span>Ajuste a busca ou cadastre na cronoanalise.</span></div>';
        }

        function addPostoEditorAtividade(id) {
            const atividade = cronoAtividades.find(item => String(item.id) === String(id));
            if (!atividade) return;
            postoEditorState.selected = [...(postoEditorState.selected || []), atividade];
            const search = document.getElementById('postoEditorSearch');
            if (search) search.value = '';
            setPostoEditorDropdownOpen(true);
            renderPostoEditor();
            autosavePostoEditorDraft();
            if (search) search.focus();
        }

        function removePostoEditorAtividade(id) {
            postoEditorState.selected = (postoEditorState.selected || []).filter(item => String(item.id) !== String(id));
            renderPostoEditor();
            autosavePostoEditorDraft();
        }

        function autosavePostoEditorDraft() {
            const postoId = postoEditorState.editingPostoId;
            if (!postoId) return;

            const posto = mockPostos.find(p => String(p.id) === String(postoId));
            if (!posto || (posto.type || 'node') !== 'node') return;

            const nomeInput = document.getElementById('postoEditorNome');
            const pessoasInput = document.getElementById('postoEditorPessoas');
            const nextName = (nomeInput?.value || posto.name || '').trim();
            const pessoas = Math.max(1, parseInt(pessoasInput?.value || posto.pessoas || 1, 10) || 1);
            const atividades = normalizeNodeAtividades(postoEditorState.selected || []);
            const tc = calcTcFromAtividades(atividades);
            const tcContentor = calcTcContentorFromAtividades(atividades);
            const ritmo = calcRitmoPosto(tcContentor, pessoas);

            posto.name = nextName || posto.name;
            posto.tc = tc;
            posto.tcContentor = tcContentor;
            posto.atividades = atividades;
            posto.pessoas = pessoas;
            posto.ritmo = ritmo;
            syncPostoNodeData(posto);
            isDirty = true;
            saveFlowState();
        }

        function syncPostoNodeData(posto) {
            if (!posto || !posto.drawflow_id || !editor) return;

            editor.updateNodeDataFromId(posto.drawflow_id, {
                id: posto.id,
                name: posto.name,
                type: posto.type,
                tc: posto.tc,
                tcContentor: posto.tcContentor,
                tc_contentor: posto.tcContentor,
                icon: posto.icon || 'fa-industry',
                atividades: posto.atividades,
                pessoas: posto.pessoas,
                ritmo: posto.ritmo,
                module_scope: moduleScope,
                module_label: moduleLabel,
                autoPlace: posto.autoPlace !== false
            });

            const nodeEl = document.getElementById('node-' + posto.drawflow_id);
            if (nodeEl) {
                const content = nodeEl.querySelector('.drawflow_content_node');
                if (content) {
                    content.innerHTML = generateNodeHtml(posto);
                }
            }
        }

        function updatePostoEditorMetrics() {
            const pessoasInput = document.getElementById('postoEditorPessoas');
            const tcInput = document.getElementById('postoEditorTc');
            const tcContentorInput = document.getElementById('postoEditorTcContentor');
            const ritmoInput = document.getElementById('postoEditorRitmo');
            const pessoas = parseInt(pessoasInput?.value || 0, 10) || 0;
            const tc = calcTcFromAtividades(postoEditorState.selected || []);
            const tcContentor = calcTcContentorFromAtividades(postoEditorState.selected || []);
            const ritmo = calcRitmoPosto(tcContentor, pessoas);
            if (tcInput) tcInput.value = formatSeconds(tc);
            if (tcContentorInput) tcContentorInput.value = formatSeconds(tcContentor);
            if (ritmoInput) ritmoInput.value = formatSeconds(ritmo);
        }

        function confirmPostoEditor() {
            if (!postoEditorResolver) return;
            const nomeInput = document.getElementById('postoEditorNome');
            const pessoasInput = document.getElementById('postoEditorPessoas');
            const name = (nomeInput?.value || '').trim();
            if (!name) {
                alert('Informe o nome do posto.');
                if (nomeInput) nomeInput.focus();
                return;
            }
            const pessoas = Math.max(1, parseInt(pessoasInput?.value || 1, 10) || 1);
            const atividades = normalizeNodeAtividades(postoEditorState.selected || []);
            const tc = calcTcFromAtividades(atividades);
            const tcContentor = calcTcContentorFromAtividades(atividades);
            const ritmo = calcRitmoPosto(tcContentor, pessoas);
            closePostoEditorModal();
            postoEditorResolver({ name, atividades, pessoas, tc, tcContentor, ritmo });
            postoEditorResolver = null;
        }

        function cancelPostoEditor(event = null) {
            if (event && event.target !== event.currentTarget) return;
            if (postoEditorResolver) {
                closePostoEditorModal();
                postoEditorResolver(null);
                postoEditorResolver = null;
            }
        }

        function closePostoEditorModal() {
            const modal = document.getElementById('postoEditorModal');
            if (modal) modal.classList.remove('open');
            setPostoEditorDropdownOpen(false);
        }

        document.addEventListener('input', function(event) {
            if (event.target && event.target.id === 'postoEditorSearch') {
                setPostoEditorDropdownOpen(true);
                renderPostoEditorDropdown();
            }
            if (event.target && event.target.id === 'postoEditorPessoas') {
                updatePostoEditorMetrics();
            }
        });

        document.addEventListener('focusin', function(event) {
            if (event.target && event.target.id === 'postoEditorSearch') {
                setPostoEditorDropdownOpen(true);
            }
        });

        document.addEventListener('mousedown', function(event) {
            const combo = document.getElementById('postoEditorCombo');
            const modal = document.getElementById('postoEditorModal');
            if (!combo || !modal || !modal.classList.contains('open')) return;
            if (!combo.contains(event.target)) {
                setPostoEditorDropdownOpen(false);
            }
        });

        async function editNodeInFlow(id) {
            const posto = mockPostos.find(p => p.id == id);
            if (!posto) return;

            const intrinsic = posto.type === 'node'
                ? await editPostoIntrinsics(posto, posto.name)
                : { name: prompt('Novo nome:', posto.name), atividades: [], pessoas: 0, tc: 0, ritmo: 0 };
            if (!intrinsic) return;
            const newName = (intrinsic.name || '').trim();
            if (!newName) return;
            const newTc = posto.type === 'node' ? intrinsic.tc : 0;

            posto.name = newName;
            posto.tc = newTc;
            posto.tcContentor = posto.type === 'node' ? (intrinsic.tcContentor || intrinsic.tc_contentor || newTc) : 0;
            posto.atividades = intrinsic.atividades;
            posto.pessoas = intrinsic.pessoas;
            posto.ritmo = intrinsic.ritmo;
            await savePostoMaster(posto.id, posto.name, posto.tc);
            syncPostoNodeData(posto);

            isDirty = true;
            await saveFlowState();
        }

        function removeNodeFromFlow(id) {
            if (!confirm('Remover este no do fluxo?')) return;

            const posto = mockPostos.find(p => p.id == id);
            if (!posto) return;

            editor.removeNodeId('node-' + posto.drawflow_id);
            mockPostos = mockPostos.filter(p => p.id != id);
            mockConnections = mockConnections.filter(conn => conn.sourceDfId != posto.drawflow_id && conn.targetDfId != posto.drawflow_id);
            isDirty = true;
            updateNodeCount();
            saveFlowState();
        }

        function clearFlow() {
            if (!confirm('Limpar todo o fluxo desta linha?')) return;
            editor.clear();
            mockPostos = [];
            mockConnections = [];
            isDirty = true;
            updateNodeCount();
            saveFlowState();
        }

        async function saveFlowState(force = false) {
            if (!currentSetorId || !currentLinhaId || !editor || (!force && isLoading)) return false;
            if (isSaving && !force) {
                pendingSaveAfterCurrent = true;
                return currentSavePromise || false;
            }

            if (saveTimer) {
                clearTimeout(saveTimer);
                saveTimer = null;
            }

            isSaving = true;
            currentSavePromise = (async function persistPendingFlowSaves() {
                let saved = false;
                try {
                    do {
                        pendingSaveAfterCurrent = false;
                        const data = await postFlowSave();
                        if (!data || data.status !== 'success') {
                            console.error('Erro ao salvar fluxo isolado:', data?.message || 'resposta invalida');
                            return saved;
                        }

                        saved = true;
                        isDirty = false;
                        showSaveStatus();
                    } while (pendingSaveAfterCurrent && !isLoading);

                    return saved;
                } catch (error) {
                    console.error('Erro na requisicao de salvamento:', error);
                    return saved;
                } finally {
                    isSaving = false;
                    currentSavePromise = null;
                }
            })();

            return currentSavePromise;
        }

        async function postFlowSave() {
            const formData = buildFlowSaveFormData();
            const response = await fetch(apiUrl, { method: 'POST', body: formData });
            return response.json();
        }

        function buildFlowSaveFormData() {
            const exportData = editor.export();
            sanitizeExportData(exportData);
            exportData.viewport_state = getCurrentViewport();
            const formData = new FormData();
            formData.append('action', 'save_drawflow_data');
            formData.append('module_scope', moduleScope);
            formData.append('setor_id', currentSetorId);
            formData.append('linha_id', currentLinhaId);
            formData.append('drawflow_data', JSON.stringify(exportData));
            return formData;
        }

        function showSaveStatus() {
            const status = document.getElementById('saveStatus');
            if (status) {
                status.style.opacity = '1';
                setTimeout(() => { status.style.opacity = '0'; }, 900);
            }
        }

        function flushFlowSaveBeforeUnload() {
            if (!currentSetorId || !currentLinhaId || !editor || isLoading) return;
            if (!isDirty && !isSaving && !pendingSaveAfterCurrent && !saveTimer) return;

            if (saveTimer) {
                clearTimeout(saveTimer);
                saveTimer = null;
            }

            const formData = buildFlowSaveFormData();
            if (navigator.sendBeacon) {
                navigator.sendBeacon(apiUrl, formData);
                return;
            }

            fetch(apiUrl, { method: 'POST', body: formData, keepalive: true }).catch(function(error) {
                console.error('Erro no salvamento final do fluxo:', error);
            });
        }

        function scheduleFlowSave(delay = 350) {
            if (isLoading) return;
            isDirty = true;
            if (saveTimer) clearTimeout(saveTimer);
            saveTimer = setTimeout(function() {
                saveTimer = null;
                saveFlowState();
            }, delay);
        }

        function sanitizeExportData(exportData) {
            const nodes = exportData?.drawflow?.Home?.data || {};
            Object.keys(nodes).forEach(drawflowId => {
                const node = nodes[drawflowId];
                const posto = mockPostos.find(p => p.drawflow_id == drawflowId);

                node.pos_x = normalizePosition(node.pos_x, posto ? posto.x : 120);
                node.pos_y = normalizePosition(node.pos_y, posto ? posto.y : 160);

                if (posto) {
                    node.data = {
                        id: posto.id,
                        name: posto.name,
                        type: posto.type || 'node',
                        tc: posto.tc || 0,
                        tcContentor: posto.tcContentor || posto.tc_contentor || posto.tc || 0,
                        tc_contentor: posto.tcContentor || posto.tc_contentor || posto.tc || 0,
                        icon: posto.icon || 'fa-industry',
                        atividades: normalizeNodeAtividades(posto.atividades || []),
                        pessoas: parseInt(posto.pessoas || 0, 10) || 0,
                        ritmo: parseLocalNumber(posto.ritmo || 0, 0),
                        module_scope: moduleScope,
                        module_label: moduleLabel,
                        autoPlace: posto.autoPlace !== false
                    };
                    node.html = generateNodeHtml(posto);
                }
            });
        }

        function crudAction(type, id = null) {
            const formData = new FormData();
            formData.append('action', type);
            formData.append('module_scope', moduleScope);
            formData.append('setor_id', currentSetorId);
            if (id) formData.append('linha_id', id);

            if (type === 'add_setor') {
                const nome = prompt('Nome do novo setor:');
                if (!nome || !nome.trim()) return;
                formData.append('nome', nome.trim());
            } else if (type === 'delete_setor') {
                if (!confirm('Excluir este setor e todas as linhas/fluxos dele?')) return;
            } else if (type === 'add_linha') {
                const nome = prompt('Nome da nova linha:');
                if (!nome || !nome.trim()) return;
                formData.append('nome', nome.trim());
            } else if (type === 'rename_linha') {
                const nome = prompt('Novo nome da linha:');
                if (!nome || !nome.trim()) return;
                formData.append('nome', nome.trim());
            } else if (type === 'delete_linha') {
                if (!confirm('Excluir esta linha e o fluxo independente dela?')) return;
            }

            fetch(apiUrl, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') {
                        alert('Erro: ' + (data.message || 'acao nao realizada'));
                        return;
                    }
                    const setorId = data.setor_id || currentSetorId || '';
                    const linhaId = data.linha_id || '';
                    let url = '?setor_id=' + encodeURIComponent(setorId);
                    if (linhaId) url += '&linha_id=' + encodeURIComponent(linhaId);
                    window.location.href = url;
                })
                .catch(error => alert('Erro na requisicao: ' + error.message));
        }

        function showDeriveOptions(parentId) {
            const posto = mockPostos.find(p => p.id == parentId);
            if (!posto) return;

            const nodeEl = document.getElementById('node-' + posto.drawflow_id);
            if (!nodeEl) return;

            const existing = nodeEl.querySelector('.derive-options');
            if (existing) {
                existing.remove();
                return;
            }

            const escapedParentId = escapeJsString(parentId);
            const div = document.createElement('div');
            div.className = 'derive-options';
            div.style.cssText = 'position:absolute;z-index:100;left:50%;transform:translateX(-50%);min-width:230px;margin-top:8px;padding:10px;background:#fff;border:1px solid #cbd5e1;border-radius:5px;box-shadow:0 4px 14px rgba(0,0,0,.14);';
            div.innerHTML = `
                <strong style="display:block;margin-bottom:8px;font-size:11px;color:#17202a;">Derivar de: ${escapeHtml(posto.name)}</strong>
                <div style="display:flex;gap:5px;">
                    <button type="button" onclick="event.stopPropagation(); addNodeToFlow(null, 'node', '${escapedParentId}')" style="padding:4px 8px;font-size:10px;cursor:pointer;background:#1f6feb;color:white;border:0;border-radius:3px;"><i class="fa-solid fa-industry"></i> Posto</button>
                    <button type="button" onclick="event.stopPropagation(); addNodeToFlow(null, 'decision_exclusive', '${escapedParentId}')" style="padding:4px 8px;font-size:10px;cursor:pointer;background:#f59e0b;color:#111827;border:0;border-radius:3px;"><i class="fa-solid fa-xmark"></i> Decisao</button>
                    <button type="button" onclick="event.stopPropagation(); addNodeToFlow(null, 'decision_parallel', '${escapedParentId}')" style="padding:4px 8px;font-size:10px;cursor:pointer;background:#22a06b;color:white;border:0;border-radius:3px;"><i class="fa-solid fa-plus"></i> Paralelo</button>
                </div>
            `;

            const content = nodeEl.querySelector('.drawflow_content_node');
            if (content) content.appendChild(div);
        }

        function removeDeriveOptions(drawflowId) {
            const nodeEl = document.getElementById('node-' + drawflowId);
            if (!nodeEl) return;
            const existing = nodeEl.querySelector('.derive-options');
            if (existing) existing.remove();
        }

        function updateNodeCount() {
            if (!editor) return;
            const data = editor.export();
            const count = Object.keys(data.drawflow.Home.data || {}).length;
            document.getElementById('nodeCount').textContent = count;
        }

        function openPostoResumo() {
            const title = document.getElementById('propertiesTitle');
            const body = document.getElementById('propertiesContent');
            const workbench = document.getElementById('flowWorkbench');
            if (!body) return;

            setPropertiesPanelCollapsed(false);
            if (workbench) workbench.classList.add('posto-table-open');
            if (title) title.textContent = 'Postos x quantidade x ritmo';

            const postos = mockPostos.filter(posto => (posto.type || 'node') === 'node');
            if (!postos.length) {
                body.innerHTML = '<div class="property-help">Nenhum posto no fluxo atual.</div>';
            } else {
                const rows = postos.map(posto => {
                    const atividades = normalizeNodeAtividades(posto.atividades || []);
                    const atividadeLabel = atividades.length
                        ? atividades.map(item => `${escapeHtml(item.nome)}${item.tipo ? ' / ' + escapeHtml(item.tipo) : ''}`).join('<br>')
                        : '-';
                    return `
                        <tr>
                            <td class="posto-name-cell">${escapeHtml(posto.name)}</td>
                            <td>
                                <input type="number" min="1" step="1" value="${escapeHtml(posto.pessoas || 1)}" onchange="updatePostoResumoPessoas('${escapeJsString(posto.id)}', this.value)">
                            </td>
                            <td>${escapeHtml(formatSeconds(posto.tc || 0))}</td>
                            <td>${escapeHtml(formatSeconds(posto.tcContentor || posto.tc_contentor || posto.ritmo || posto.tc || 0))}</td>
                            <td id="postoResumoRitmo-${escapeHtml(String(posto.id))}">${escapeHtml(formatSeconds(posto.ritmo || 0))}</td>
                            <td class="posto-activities-cell">${atividadeLabel}</td>
                        </tr>
                    `;
                }).join('');

                body.innerHTML = `
                    <table class="posto-panel-table">
                        <thead>
                            <tr>
                                <th>Posto</th>
                                <th>Pessoas</th>
                                <th>TC</th>
                                <th>TC/ctt</th>
                                <th>Ritmo</th>
                                <th>Atividades</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                `;
            }
        }

        function updatePostoResumoPessoas(id, value) {
            const posto = mockPostos.find(p => String(p.id) === String(id));
            if (!posto || (posto.type || 'node') !== 'node') return;

            const pessoas = Math.max(1, parseInt(value || 1, 10) || 1);
            posto.pessoas = pessoas;
            posto.ritmo = calcRitmoPosto(posto.tcContentor || posto.tc_contentor || posto.tc || 0, pessoas);
            syncPostoNodeData(posto);

            const ritmoCell = document.getElementById('postoResumoRitmo-' + id);
            if (ritmoCell) ritmoCell.textContent = formatSeconds(posto.ritmo || 0);

            isDirty = true;
            saveFlowState();
        }

        function closePostoResumo() {
            const workbench = document.getElementById('flowWorkbench');
            if (workbench) workbench.classList.remove('posto-table-open');
            setPropertiesPanelCollapsed(true);
        }

        function setupDrawflowEvents() {
            editor.on('connectionCreated', function(info) {
                if (isLoading) return;
                registerConnection(info.output_id, info.input_id, info.output_class || 'output_1', info.input_class || 'input_1');
                isDirty = true;
                saveFlowState();
            });

            editor.on('connectionRemoved', function(info) {
                if (isLoading) return;
                mockConnections = mockConnections.filter(conn => !(conn.sourceDfId === parseInt(info.output_id, 10) && conn.targetDfId === parseInt(info.input_id, 10)));
                isDirty = true;
                saveFlowState();
            });

            editor.on('nodeMoved', function(id) {
                if (isLoading) return;
                const node = editor.getNodeFromId(id);
                const posto = mockPostos.find(p => p.drawflow_id == id);
                if (posto && node) {
                    posto.x = normalizePosition(node.pos_x, posto.x || 120);
                    posto.y = normalizePosition(node.pos_y, posto.y || 160);
                    posto.autoPlace = false;
                    isDirty = true;
                    showBizagiProperties(id);
                    saveFlowState();
                }
            });

            editor.on('nodeRemoved', function() {
                if (isLoading) return;
                rebuildLocalStateFromEditor();
                updateNodeCount();
                isDirty = true;
                saveFlowState();
            });

            editor.on('zoom', function() {
                if (isLoading) return;
                updateZoomDisplay();
                scheduleFlowSave();
            });

            editor.on('translate', function() {
                if (isLoading) return;
                scheduleFlowSave();
            });

            editor.on('nodeSelected', function(id) {
                mockPostos.forEach(p => {
                    if (p.drawflow_id !== parseInt(id, 10)) {
                        removeDeriveOptions(p.drawflow_id);
                    }
                });
                showBizagiProperties(id);
            });

            editor.on('nodeUnselected', function() {
                resetBizagiProperties();
            });
        }

        function showBizagiProperties(drawflowId) {
            const posto = mockPostos.find(p => p.drawflow_id == drawflowId);
            const title = document.getElementById('propertiesTitle');
            const workbench = document.getElementById('flowWorkbench');
            const panel = document.getElementById('propertiesContent');
            if (!posto || !panel) return;
            if (workbench) workbench.classList.remove('posto-table-open');
            if (title) title.textContent = 'Detalhes - Posto';

            const node = editor.getNodeFromId(drawflowId);
            const x = node ? normalizePosition(node.pos_x, posto.x || 120) : posto.x;
            const y = node ? normalizePosition(node.pos_y, posto.y || 160) : posto.y;
            const typeLabel = getBizagiTypeLabel(posto.type);
            const outgoing = mockConnections.filter(c => c.sourceDfId == posto.drawflow_id).length;
            const incoming = mockConnections.filter(c => c.targetDfId == posto.drawflow_id).length;
            const behavior = getBpmnBehavior(posto.type, incoming, outgoing);
            const atividades = normalizeNodeAtividades(posto.atividades || []);
            const atividadeHtml = atividades.length
                ? `<div class="property-pill-list">${atividades.map(item => `<span class="property-pill">${escapeHtml(item.nome)}${item.tipo ? ' / ' + escapeHtml(item.tipo) : ''}</span>`).join('')}</div>`
                : '<input type="text" value="Nenhuma atividade vinculada" readonly>';
            const intrinsicHtml = posto.type === 'node' ? `
                <div class="property-row">
                    <label>Atividades</label>
                    ${atividadeHtml}
                </div>
                <div class="property-row">
                    <label>Numero de pessoas no posto</label>
                    <input type="text" value="${escapeHtml(posto.pessoas || 0)}" readonly>
                </div>
                <div class="property-row">
                    <label>TC/ctt</label>
                    <input type="text" value="${escapeHtml(formatSeconds(posto.tcContentor || posto.tc_contentor || posto.tc || 0))}" readonly>
                </div>
                <div class="property-row">
                    <label>Ritmo do posto</label>
                    <input type="text" value="${escapeHtml(formatSeconds(posto.ritmo || 0))}" readonly>
                </div>
            ` : '';

            panel.innerHTML = `
                <div class="property-row">
                    <label>Elemento</label>
                    <input type="text" value="${escapeHtml(typeLabel)}" readonly>
                </div>
                <div class="property-row">
                    <label>Nome</label>
                    <input type="text" value="${escapeHtml(posto.name)}" readonly>
                </div>
                <div class="property-row">
                    <label>ID</label>
                    <input type="text" value="${escapeHtml(posto.id)}" readonly>
                </div>
                <div class="property-row">
                    <label>TC</label>
                    <input type="text" value="${escapeHtml(posto.type === 'node' ? formatSeconds(posto.tc || 0) : 'Nao aplicavel')}" readonly>
                </div>
                ${intrinsicHtml}
                <div class="property-row">
                    <label>Comportamento BPMN</label>
                    <textarea readonly>${escapeHtml(behavior)}</textarea>
                </div>
                <div class="property-row">
                    <label>Posicao X</label>
                    <input type="text" value="${Math.round(x)}" readonly>
                </div>
                <div class="property-row">
                    <label>Posicao Y</label>
                    <input type="text" value="${Math.round(y)}" readonly>
                </div>
                <div class="property-row">
                    <label>Conexoes</label>
                    <textarea readonly>Entradas: ${incoming}\nSaidas: ${outgoing}\nLayout automatico: ${posto.autoPlace === false ? 'Nao' : 'Sim'}</textarea>
                </div>
            `;
        }

        function resetBizagiProperties() {
            const panel = document.getElementById('propertiesContent');
            if (!panel) return;
            panel.className = 'property-help';
            panel.innerHTML = 'Selecione um posto ou gateway no fluxo para ver as propriedades BPM.';
        }

        function getBizagiTypeLabel(type) {
            if (type === 'decision_exclusive') return 'Gateway exclusivo';
            if (type === 'decision_parallel') return 'Gateway paralelo';
            return 'Tarefa / Posto';
        }

        function getBpmnBehavior(type, incoming, outgoing) {
            if (type === 'decision_parallel') {
                if (outgoing > 1) return 'AND-split: abre caminhos paralelos executados em conjunto.';
                if (incoming > 1) return 'AND-join: sincroniza caminhos paralelos antes de prosseguir.';
                return 'Gateway paralelo: use multiplas saidas para dividir ou multiplas entradas para sincronizar.';
            }

            if (type === 'decision_exclusive') {
                if (outgoing > 1) return 'XOR-split: escolhe apenas um caminho de saida.';
                if (incoming > 1) return 'XOR-merge: recebe caminhos alternativos sem sincronizar.';
                return 'Gateway exclusivo: use multiplas saidas para alternativas.';
            }

            return outgoing > 1
                ? 'Tarefa com multiplas saidas; para BPMN formal, prefira gateway antes de ramificar.'
                : 'Tarefa BPMN: atividade executada por um posto.';
        }
    </script>
</body>
</html>

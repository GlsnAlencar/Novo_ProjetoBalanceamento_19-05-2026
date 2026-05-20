<?php
session_start();

require_once __DIR__ . '/module_routes.php';
require_once __DIR__ . '/safe_storage.php';
require_once rf_route_path('arvore_estrutura', 'api');
require_once __DIR__ . '/cronoanalise/repositories/CronoanaliseRepository.php';

$repository = new CronoanaliseRepository(rf_route('editor_bpm', 'storage'));
$filtros = [
    'periodo_inicio' => trim((string)($_GET['periodo_inicio'] ?? '')),
    'periodo_fim' => trim((string)($_GET['periodo_fim'] ?? '')),
    'setor' => trim((string)($_GET['setor'] ?? '')),
    'linha' => trim((string)($_GET['linha'] ?? '')),
    'item' => trim((string)($_GET['item'] ?? '')),
    'calibre' => trim((string)($_GET['calibre'] ?? '')),
    'tipo_atividade' => trim((string)($_GET['tipo_atividade'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
];

function cc_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function cc_num($value): float {
    if (is_string($value)) {
        $raw = trim(str_replace(['%', ' '], '', $value));
        $raw = str_contains($raw, ',') ? str_replace('.', '', $raw) : $raw;
        $value = str_replace(',', '.', $raw);
    }
    return is_numeric($value) ? (float)$value : 0.0;
}

function cc_fmt($value, int $decimals = 2): string {
    $num = cc_num($value);
    return $num > 0 ? number_format($num, $decimals, ',', '.') : '';
}

function cc_taxa_label(array $row): string {
    $taxa = cc_fmt($row['taxa_producao'] ?? 0);
    return $taxa !== '' ? $taxa . ' s/contentor' : '';
}

function cc_taxa_memoria_label(array $row): string {
    $memoria = trim((string)($row['taxa_base_memoria'] ?? ''));
    return $memoria !== '' ? $memoria : cc_fmt($row['taxa_base_quantidade'] ?? 0);
}

function cc_text($value, string $fallback = ''): string {
    $text = trim((string)$value);
    return $text !== '' ? $text : $fallback;
}

function cc_norm($value): string {
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$value);
    return strtolower(preg_replace('/[^a-z0-9]+/i', ' ', (string)$value));
}

function cc_item_label(array $row): string {
    $direct = cc_text($row['item'] ?? $row['item_embalagem'] ?? $row['item_carga'] ?? $row['produto'] ?? '');
    if ($direct !== '') {
        return $direct;
    }
    return trim(implode(' - ', array_filter([
        cc_text($row['grupo_embalagem'] ?? ''),
        cc_text($row['variacao'] ?? ''),
    ])));
}

function cc_split_multi_values($value, string $separatorPattern): array {
    $parts = preg_split($separatorPattern, (string)$value, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_map(fn($part) => trim((string)$part), $parts ?: []));
}

function cc_expandir_itens_transporte(array $rows): array {
    $expanded = [];
    foreach ($rows as $row) {
        if (cc_tipo_atividade($row) !== 'transporte') {
            $expanded[] = $row;
            continue;
        }

        $produtoIds = cc_split_multi_values($row['produto_id'] ?? '', '/\s*,\s*/');
        $items = cc_split_multi_values($row['item'] ?? $row['item_carga'] ?? '', '/\s*\|\s*/');
        $count = max(count($produtoIds), count($items));
        if ($count <= 1) {
            $expanded[] = $row;
            continue;
        }

        for ($index = 0; $index < $count; $index++) {
            $copy = $row;
            $copy['_cc_group_raw'] = $row;
            $copy['_cc_group_count'] = $count;
            $copy['_cc_group_index'] = $index;
            $copy['produto_id'] = $produtoIds[$index] ?? '';
            $copy['item'] = $items[$index] ?? ($row['item'] ?? '');
            $copy['item_carga'] = $copy['item'];
            $expanded[] = $copy;
        }
    }
    return $expanded;
}

function cc_arvore_children_by_parent(array $data): array {
    $childrenByTreeParent = [];
    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        if ((int)($comp['ativo'] ?? 1) !== 1) {
            continue;
        }
        $key = (string)($comp['arvore_id'] ?? '') . '|' . (string)($comp['item_pai_id'] ?? '');
        $childrenByTreeParent[$key][] = $comp;
    }
    return $childrenByTreeParent;
}

function cc_fruta_in_natura_codes(): array {
    return ['FR-001', 'PF-001'];
}

function cc_sum_arvore_target(string $treeId, string $parentId, array $targetCodes, float $factor, array $visited, array $childrenByTreeParent, array $itemsById): float {
    $key = $treeId . '|' . $parentId;
    $total = 0.0;
    foreach (($childrenByTreeParent[$key] ?? []) as $comp) {
        $childId = (string)($comp['item_filho_id'] ?? '');
        if ($childId === '' || isset($visited[$childId])) {
            continue;
        }
        $child = $itemsById[$childId] ?? [];
        $qty = max(0.000001, cc_num($comp['quantidade'] ?? 1));
        $nextFactor = $factor * $qty;
        $code = (string)($child['codigo'] ?? '');
        if (in_array($code, $targetCodes, true)) {
            $total += $nextFactor;
            continue;
        }
        $nextVisited = $visited;
        $nextVisited[$childId] = true;
        $total += cc_sum_arvore_target($treeId, $childId, $targetCodes, $nextFactor, $nextVisited, $childrenByTreeParent, $itemsById);
    }
    return $total;
}

function cc_contentor_padrao_kg(array $data, array $itemsById, array $childrenByTreeParent): float {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $contentorIds = [];
    foreach ($itemsById as $id => $item) {
        if (($item['codigo'] ?? '') === 'ITM-00002') {
            $contentorIds[] = (string)$id;
        }
    }

    foreach (($data['tabela_arvores'] ?? []) as $tree) {
        if ((int)($tree['ativo'] ?? 1) !== 1 || !in_array((string)($tree['item_raiz_id'] ?? ''), $contentorIds, true)) {
            continue;
        }
        $kg = cc_sum_arvore_target(
            (string)($tree['id'] ?? ''),
            (string)($tree['item_raiz_id'] ?? ''),
            cc_fruta_in_natura_codes(),
            1.0,
            [(string)($tree['item_raiz_id'] ?? '') => true],
            $childrenByTreeParent,
            $itemsById
        );
        if ($kg > 0) {
            $cache = $kg;
            return $cache;
        }
    }

    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        $parent = $itemsById[(string)($comp['item_pai_id'] ?? '')] ?? [];
        $child = $itemsById[(string)($comp['item_filho_id'] ?? '')] ?? [];
        if (($parent['codigo'] ?? '') === 'ITM-00002' && in_array((string)($child['codigo'] ?? ''), cc_fruta_in_natura_codes(), true)) {
            $cache = max(0.000001, cc_num($comp['quantidade'] ?? 0));
            return $cache;
        }
    }

    $cache = 20.0;
    return $cache;
}

function cc_nominal_manga_kg_item(array $data, array $itemsById, array $childrenByTreeParent, string $itemId): float {
    static $cache = [];
    if ($itemId === '') {
        return 0.0;
    }
    if (array_key_exists($itemId, $cache)) {
        return $cache[$itemId];
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
        if (in_array((string)($child['codigo'] ?? ''), cc_fruta_in_natura_codes(), true)) {
            $best = max($best, max(0.000001, cc_num($comp['quantidade'] ?? 0)));
        }
    }
    if ($best > 0) {
        $cache[$itemId] = $best;
        return $best;
    }

    foreach (($data['tabela_arvore_composicao'] ?? []) as $comp) {
        $treeId = (string)($comp['arvore_id'] ?? '');
        $parentId = (string)($comp['item_pai_id'] ?? '');
        if ((int)($comp['ativo'] ?? 1) !== 1 || !isset($activeTrees[$treeId]) || (string)($comp['item_filho_id'] ?? '') !== $itemId || $parentId === '') {
            continue;
        }

        $key = $treeId . '|' . $parentId;
        foreach (($childrenByTreeParent[$key] ?? []) as $sibling) {
            $siblingItem = $itemsById[(string)($sibling['item_filho_id'] ?? '')] ?? [];
            if (in_array((string)($siblingItem['codigo'] ?? ''), cc_fruta_in_natura_codes(), true)) {
                $best = max($best, max(0.000001, cc_num($sibling['quantidade'] ?? 0)));
            }
        }
    }

    $cache[$itemId] = $best;
    return $best;
}

function cc_nominal_manga_kg_tree(string $treeId, string $parentId, float $factor, array $visited, array $childrenByTreeParent, array $itemsById, array $data): float {
    $key = $treeId . '|' . $parentId;
    $total = 0.0;
    foreach (($childrenByTreeParent[$key] ?? []) as $comp) {
        $childId = (string)($comp['item_filho_id'] ?? '');
        if ($childId === '' || isset($visited[$childId])) {
            continue;
        }
        $qty = max(0.000001, cc_num($comp['quantidade'] ?? 1));
        $nextFactor = $factor * $qty;
        $nominalKg = cc_nominal_manga_kg_item($data, $itemsById, $childrenByTreeParent, $childId);
        if ($nominalKg > 0) {
            $total += $nextFactor * $nominalKg;
            continue;
        }
        $nextVisited = $visited;
        $nextVisited[$childId] = true;
        $total += cc_nominal_manga_kg_tree($treeId, $childId, $nextFactor, $nextVisited, $childrenByTreeParent, $itemsById, $data);
    }
    return $total;
}

function cc_contentores_equivalentes_item(array $data, array $itemsById, array $childrenByTreeParent, string $itemId): array {
    if ($itemId === '') {
        return ['quantidade' => 0.0, 'kg_manga' => 0.0, 'kg_por_contentor' => 0.0, 'codigo' => ''];
    }

    $itemCode = (string)($itemsById[$itemId]['codigo'] ?? '');
    if (in_array($itemCode, ['ITM-00002', 'ITM-00015'], true)) {
        return ['quantidade' => 1.0, 'kg_manga' => 0.0, 'kg_por_contentor' => 0.0, 'codigo' => $itemCode];
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
        return cc_contentores_equivalentes_contexto_item($data, $itemsById, $childrenByTreeParent, $itemId);
    }

    if (empty($trees) && !empty($parentTrees)) {
        $seenTrees = [];
        foreach ($parentTrees as $comp) {
            $treeId = (string)($comp['arvore_id'] ?? '');
            if ($treeId === '' || isset($seenTrees[$treeId])) {
                continue;
            }
            $seenTrees[$treeId] = true;
            $base = cc_contentores_equivalentes_subarvore($treeId, $itemId, $data, $itemsById, $childrenByTreeParent);
            if (cc_num($base['quantidade'] ?? 0) > 0) {
                return $base + ['contexto_item_id' => $itemId];
            }
        }

        return cc_contentores_equivalentes_contexto_item($data, $itemsById, $childrenByTreeParent, $itemId);
    }

    foreach (['ITM-00002', 'ITM-00015'] as $targetCode) {
        $total = 0.0;
        foreach ($trees as $tree) {
            $treeId = (string)($tree['id'] ?? '');
            $rootId = (string)($tree['item_raiz_id'] ?? '');
            $total += cc_sum_arvore_target($treeId, $rootId, [$targetCode], 1.0, [$rootId => true], $childrenByTreeParent, $itemsById);
        }
        if ($total > 0) {
            return ['quantidade' => $total, 'kg_manga' => 0.0, 'kg_por_contentor' => 0.0, 'codigo' => $targetCode];
        }
    }

    $kgManga = 0.0;
    foreach ($trees as $tree) {
        $treeId = (string)($tree['id'] ?? '');
        $rootId = (string)($tree['item_raiz_id'] ?? '');
        $kgManga += cc_sum_arvore_target($treeId, $rootId, cc_fruta_in_natura_codes(), 1.0, [$rootId => true], $childrenByTreeParent, $itemsById);
    }
    $kgPorContentor = cc_contentor_padrao_kg($data, $itemsById, $childrenByTreeParent);
    if ($kgManga > 0 && $kgPorContentor > 0) {
        return [
            'quantidade' => $kgManga / $kgPorContentor,
            'kg_manga' => $kgManga,
            'kg_por_contentor' => $kgPorContentor,
            'codigo' => 'FRUTA-IN-NATURA',
        ];
    }

    $kgNominal = 0.0;
    foreach ($trees as $tree) {
        $treeId = (string)($tree['id'] ?? '');
        $rootId = (string)($tree['item_raiz_id'] ?? '');
        $kgNominal += cc_nominal_manga_kg_tree($treeId, $rootId, 1.0, [$rootId => true], $childrenByTreeParent, $itemsById, $data);
    }
    if ($kgNominal > 0 && $kgPorContentor > 0) {
        return [
            'quantidade' => $kgNominal / $kgPorContentor,
            'kg_manga' => $kgNominal,
            'kg_por_contentor' => $kgPorContentor,
            'codigo' => 'FRUTA-IN-NATURA-NOMINAL',
        ];
    }

    return ['quantidade' => 0.0, 'kg_manga' => 0.0, 'kg_por_contentor' => $kgPorContentor, 'codigo' => ''];
}

function cc_contentores_equivalentes_subarvore(string $treeId, string $rootId, array $data, array $itemsById, array $childrenByTreeParent): array {
    foreach (['ITM-00002', 'ITM-00015'] as $targetCode) {
        $total = cc_sum_arvore_target($treeId, $rootId, [$targetCode], 1.0, [$rootId => true], $childrenByTreeParent, $itemsById);
        if ($total > 0) {
            return ['quantidade' => $total, 'kg_manga' => 0.0, 'kg_por_contentor' => 0.0, 'codigo' => $targetCode];
        }
    }

    $kgPorContentor = cc_contentor_padrao_kg($data, $itemsById, $childrenByTreeParent);
    $kgManga = cc_sum_arvore_target($treeId, $rootId, cc_fruta_in_natura_codes(), 1.0, [$rootId => true], $childrenByTreeParent, $itemsById);
    if ($kgManga > 0 && $kgPorContentor > 0) {
        return [
            'quantidade' => $kgManga / $kgPorContentor,
            'kg_manga' => $kgManga,
            'kg_por_contentor' => $kgPorContentor,
            'codigo' => 'FRUTA-IN-NATURA',
        ];
    }

    return ['quantidade' => 0.0, 'kg_manga' => 0.0, 'kg_por_contentor' => $kgPorContentor, 'codigo' => ''];
}

function cc_contentores_equivalentes_contexto_item(array $data, array $itemsById, array $childrenByTreeParent, string $itemId): array {
    if ($itemId === '') {
        return ['quantidade' => 0.0, 'kg_manga' => 0.0, 'kg_por_contentor' => 0.0, 'codigo' => ''];
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
        if ($childId === '' || $parentId === '') {
            continue;
        }
        $parentsByTreeChild[$treeId . '|' . $childId][] = $parentId;
    }

    $best = ['quantidade' => 0.0, 'kg_manga' => 0.0, 'kg_por_contentor' => cc_contentor_padrao_kg($data, $itemsById, $childrenByTreeParent), 'codigo' => ''];
    foreach (array_keys($activeTrees) as $treeId) {
        $frontier = $parentsByTreeChild[$treeId . '|' . $itemId] ?? [];
        $visited = [$itemId => true];
        while (!empty($frontier)) {
            $parentId = array_shift($frontier);
            if ($parentId === '' || isset($visited[$parentId])) {
                continue;
            }
            $visited[$parentId] = true;

            $base = cc_contentores_equivalentes_subarvore($treeId, $parentId, $data, $itemsById, $childrenByTreeParent);
            if (cc_num($base['quantidade'] ?? 0) > 0) {
                return $base + ['contexto_item_id' => $parentId];
            }

            foreach (($parentsByTreeChild[$treeId . '|' . $parentId] ?? []) as $nextParentId) {
                if (!isset($visited[$nextParentId])) {
                    $frontier[] = $nextParentId;
                }
            }
        }
    }

    return $best;
}

function cc_item_ids_para_equivalencia(array $row, array $itemsById): array {
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
        $termNorm = cc_norm($term);
        if ($termNorm === '' || in_array($termNorm, ['embalagem', 'componente', 'vazio'], true)) {
            continue;
        }
        foreach ($itemsById as $id => $item) {
            $codeNorm = cc_norm($item['codigo'] ?? '');
            $nameNorm = cc_norm($item['nome'] ?? '');
            if ($termNorm === $codeNorm || $termNorm === $nameNorm) {
                $matches[] = (string)$id;
            }
        }
    }

    return array_values(array_unique($matches));
}

function cc_peso_nominal_texto_kg(array $row): float {
    $terms = [
        (string)($row['grupo_embalagem'] ?? ''),
        (string)($row['item'] ?? $row['item_embalagem'] ?? $row['item_carga'] ?? $row['produto'] ?? ''),
        (string)($row['observacao'] ?? ''),
    ];

    foreach ($terms as $term) {
        $termNorm = cc_norm($term);
        if (!str_contains($termNorm, 'caixa') || !preg_match('/(\d+(?:[,.]\d+)?)\s*kg/i', $term, $match)) {
            continue;
        }
        return cc_num($match[1]);
    }

    return 0.0;
}

function cc_contentores_equivalentes(array $row): array {
    static $data = null;
    static $itemsById = null;
    static $childrenByTreeParent = null;

    if ($data === null) {
        $data = ae_api_load_data();
        $itemsById = [];
        foreach (($data['tabela_itens'] ?? []) as $item) {
            $itemsById[(string)($item['id'] ?? '')] = $item;
        }
        $childrenByTreeParent = cc_arvore_children_by_parent($data);
    }

    $ids = cc_item_ids_para_equivalencia($row, $itemsById);
    if (empty($ids)) {
        $kgPorContentor = cc_contentor_padrao_kg($data, $itemsById, $childrenByTreeParent);
        $kgNominal = cc_peso_nominal_texto_kg($row);
        if ($kgNominal > 0 && $kgPorContentor > 0) {
            return [
                'quantidade' => $kgNominal / $kgPorContentor,
                'kg_manga' => $kgNominal,
                'kg_por_contentor' => $kgPorContentor,
                'codigo' => 'FRUTA-IN-NATURA-NOMINAL',
            ];
        }
        return ['quantidade' => 0.0, 'kg_manga' => 0.0, 'kg_por_contentor' => $kgPorContentor, 'codigo' => ''];
    }

    foreach (['ITM-00002', 'ITM-00015', 'FRUTA-IN-NATURA', 'FRUTA-IN-NATURA-NOMINAL'] as $codigo) {
        $quantidade = 0.0;
        $kgManga = 0.0;
        $kgPorContentor = cc_contentor_padrao_kg($data, $itemsById, $childrenByTreeParent);
        foreach ($ids as $id) {
            $base = cc_contentores_equivalentes_item($data, $itemsById, $childrenByTreeParent, (string)$id);
            if (($base['codigo'] ?? '') !== $codigo) {
                continue;
            }
            $quantidade += cc_num($base['quantidade'] ?? 0);
            $kgManga += cc_num($base['kg_manga'] ?? 0);
            $kgPorContentor = cc_num($base['kg_por_contentor'] ?? $kgPorContentor);
        }
        if ($quantidade > 0) {
            return [
                'quantidade' => $quantidade,
                'kg_manga' => $kgManga,
                'kg_por_contentor' => $kgPorContentor,
                'codigo' => $codigo,
            ];
        }
    }

    $kgPorContentor = cc_contentor_padrao_kg($data, $itemsById, $childrenByTreeParent);
    $kgNominal = cc_peso_nominal_texto_kg($row);
    if ($kgNominal > 0 && $kgPorContentor > 0) {
        return [
            'quantidade' => $kgNominal / $kgPorContentor,
            'kg_manga' => $kgNominal,
            'kg_por_contentor' => $kgPorContentor,
            'codigo' => 'FRUTA-IN-NATURA-NOMINAL',
        ];
    }

    return ['quantidade' => 0.0, 'kg_manga' => 0.0, 'kg_por_contentor' => $kgPorContentor, 'codigo' => ''];
}

function cc_tipo_atividade(array $row): string {
    $tipo = cc_norm(($row['tipo_atividade'] ?? '') . ' ' . ($row['tipo_calculo'] ?? '') . ' ' . ($row['tipo_operacao'] ?? '') . ' ' . ($row['origem_registro'] ?? ''));
    if (str_contains($tipo, 'transporte') || str_contains($tipo, 'movimentacao')) {
        return 'transporte';
    }

    $tempoDesloc = cc_num($row['tempo_deslocamento_s'] ?? 0);
    $tempoOper = cc_num($row['tempo_operacao_s'] ?? 0);
    if (str_contains($tipo, 'hibrid') || str_contains($tipo, 'deslocamento') || ($tempoDesloc > 0 && $tempoOper > 0)) {
        return 'hibrida';
    }

    return 'operacao';
}

function cc_hibrida_componentes(array $row): array {
    $componentes = $row['componentes_hibridos'] ?? $row['componentes_hibrida'] ?? [];
    if (is_string($componentes)) {
        $decoded = json_decode($componentes, true);
        $componentes = is_array($decoded) ? $decoded : [];
    }
    return is_array($componentes) ? array_values(array_filter($componentes, 'is_array')) : [];
}

function cc_hibrida_ritmo_componentes(array $row): array {
    $componentes = cc_hibrida_componentes($row);
    if (empty($componentes)) {
        return ['ritmo' => 0.0, 'memoria' => '', 'equivalencias' => []];
    }

    $ritmoTotal = 0.0;
    $memorias = [];
    $equivalencias = [];
    foreach ($componentes as $componente) {
        $tipo = cc_tipo_atividade($componente);
        $tempo = 0.0;
        if ($tipo === 'transporte') {
            $tempo = cc_num($componente['tempo_total_s'] ?? $componente['tempo_total'] ?? $componente['tempo_s'] ?? $componente['tempo_deslocamento_s'] ?? 0);
        } else {
            $tempo = cc_num($componente['tempo_unitario_utilizado'] ?? $componente['tp'] ?? $componente['TP'] ?? $componente['tempo_operacao_s'] ?? $componente['tempo_total_s'] ?? $componente['tempo_total'] ?? 0);
        }

        $base = cc_contentores_equivalentes($componente);
        $equivalencia = cc_num($base['quantidade'] ?? 0);
        if ($tempo <= 0 || $equivalencia <= 0) {
            continue;
        }

        $ritmo = $tempo / $equivalencia;
        $ritmoTotal += $ritmo;
        $equivalencias[] = $equivalencia;

        $nome = cc_text($componente['atividade'] ?? $componente['descricao'] ?? '', $tipo);
        $memorias[] = $nome . ': ' . cc_fmt($tempo) . '/' . cc_fmt($equivalencia, 0) . ' = ' . cc_fmt($ritmo);
    }

    return [
        'ritmo' => $ritmoTotal,
        'memoria' => implode(' | ', $memorias),
        'equivalencias' => $equivalencias,
    ];
}

function cc_status(array $row): string {
    if (array_key_exists('status', $row)) {
        return cc_text($row['status'], 'Ativa');
    }
    return ((int)($row['ativo'] ?? 1) === 1) ? 'Ativa' : 'Inativa';
}

function cc_is_embalar(array $row): bool {
    $atividade = cc_norm(cc_text($row['atividade'] ?? $row['descricao'] ?? ''));
    return $atividade === 'embalar';
}

function cc_normalizar(array $row): array {
    $tipo = cc_tipo_atividade($row);
    $tempoTotal = cc_num($row['tempo_total_s'] ?? $row['tempo_total'] ?? $row['tempo_s'] ?? 0);
    $tempoUnitario = cc_num($row['tempo_unitario'] ?? $row['tempo_unitario_utilizado'] ?? $row['TP'] ?? $row['tp'] ?? 0);
    $tr = cc_num($row['tr_s'] ?? $row['TR'] ?? $row['tr'] ?? $tempoUnitario);
    $tn = cc_num($row['tn_s'] ?? $row['TN'] ?? $row['tn'] ?? $tr);
    $tp = cc_num($row['tp_s'] ?? $row['TP'] ?? $row['tp'] ?? $tempoUnitario);
    $tempoOperacao = cc_num($row['tempo_operacao_s'] ?? 0);
    $tempoDeslocamento = cc_num($row['tempo_deslocamento_s'] ?? 0);
    $tempoHibrido = cc_num($row['tempo_total_s'] ?? 0);
    $qtdRef = cc_num($row['qtd_ref'] ?? $row['qtd_base'] ?? $row['quantidade_ref'] ?? 0);
    $tempoBaseUtilizado = strtoupper(trim((string)($row['tempo_base_utilizado'] ?? 'TP')));
    if (!in_array($tempoBaseUtilizado, ['TR', 'TN', 'TP'], true)) {
        $tempoBaseUtilizado = 'TP';
    }
    if ($tipo === 'operacao' && cc_is_embalar($row)) {
        $qtdRef = 1.0;
        if ($tempoTotal > 0) {
            $tr = $tempoTotal / $qtdRef;
            $tn = $tr;
            $tp = $tr;
            $tempoUnitario = $tr;
        }
    }

    if ($tipo === 'hibrida') {
        if ($tempoOperacao <= 0) {
            $tempoOperacao = $tempoUnitario > 0 ? $tempoUnitario : $tempoTotal;
        }
        if ($tempoHibrido <= 0) {
            $tempoHibrido = $tempoDeslocamento + $tempoOperacao;
        }
    }

    if ($tipo === 'transporte' && $tempoTotal <= 0) {
        $tempoTotal = $tempoUnitario;
    }

    $taxaBase = ['quantidade' => 0.0, 'kg_manga' => 0.0, 'kg_por_contentor' => 0.0, 'codigo' => ''];
    $taxaBaseAplicada = false;
    if (in_array($tipo, ['operacao', 'transporte', 'hibrida'], true)) {
        $taxaBase = cc_contentores_equivalentes($row);
        $taxaQuantidade = cc_num($taxaBase['quantidade'] ?? 0);
        if ($tipo === 'transporte' && $taxaQuantidade > 0 && $tempoTotal > 0) {
            $tr = $tempoTotal / $taxaQuantidade;
            $tn = $tr;
            $tp = $tr;
            $taxaBaseAplicada = true;
        }
    }

    $taxaQuantidade = cc_num($taxaBase['quantidade'] ?? 0);
    $tempoAtivo = $tempoBaseUtilizado === 'TR' ? $tr : ($tempoBaseUtilizado === 'TN' ? $tn : $tp);
    if ($tipo === 'hibrida' && $tempoHibrido > 0) {
        $tempoAtivo = $tempoHibrido;
    }
    $ritmoContentor = $taxaQuantidade > 0 && $tempoAtivo > 0 ? $tempoAtivo / $taxaQuantidade : 0.0;
    $taxaBaseMemoria = '';
    if ($tipo === 'hibrida') {
        $ritmoComponentes = cc_hibrida_ritmo_componentes($row);
        if (cc_num($ritmoComponentes['ritmo'] ?? 0) > 0) {
            $ritmoContentor = cc_num($ritmoComponentes['ritmo']);
            $taxaBaseMemoria = implode(' + ', array_map(fn($value) => cc_fmt($value, 0), $ritmoComponentes['equivalencias'] ?? []));
            $taxaQuantidade = 0.0;
        }
    }

    $tempoOperacaoPrincipal = $tp > 0 ? $tp : ($tempoUnitario > 0 ? $tempoUnitario : $tempoTotal);
    $capacidadeBase = $tipo === 'hibrida' ? $tempoHibrido : $tempoOperacaoPrincipal;
    $capacidade = cc_num($row['capacidade_un_h'] ?? $row['prod_h'] ?? 0);
    if ($taxaBaseAplicada) {
        $capacidade = $taxaQuantidade;
    } elseif ($tipo === 'transporte') {
        $tr = 0.0;
        $tn = 0.0;
        $tp = 0.0;
        $capacidade = 0.0;
    } elseif ($capacidade <= 0 && $capacidadeBase > 0) {
        $capacidade = 3600 / $capacidadeBase;
    }

    $distancia = cc_num($row['distancia_m'] ?? 0);
    $velocidade = cc_num($row['velocidade_m_s'] ?? $row['velocidade_calculada'] ?? 0);
    if (!isset($row['velocidade_m_s']) && isset($row['velocidade_m_min'])) {
        $velocidade = cc_num($row['velocidade_m_min']) / 60;
    }
    if ($velocidade <= 0 && $distancia > 0 && $tempoTotal > 0) {
        $velocidade = $distancia / $tempoTotal;
    }

    return [
        'id' => cc_text($row['id'] ?? ''),
        'linha_id' => cc_text($row['linha_id'] ?? $row['linha_ref_id'] ?? ''),
        'post_index' => (int)($row['post_index'] ?? 0),
        'raw' => $row,
        'group_count' => (int)($row['_cc_group_count'] ?? 1),
        'group_index' => (int)($row['_cc_group_index'] ?? 0),
        'tipo' => $tipo,
        'tipo_label' => [
            'operacao' => 'Operacao / Posto Fixo',
            'transporte' => 'Transporte / Movimentacao',
            'hibrida' => 'Hibrida / Operacao + Deslocamento',
        ][$tipo],
        'atividade' => cc_text($row['atividade'] ?? $row['descricao'] ?? ''),
        'setor' => cc_text($row['setor'] ?? ''),
        'linha' => cc_text($row['linha'] ?? ''),
        'item' => cc_item_label($row),
        'calibre' => cc_text($row['calibre'] ?? ''),
        'status' => cc_status($row),
        'data' => substr((string)($row['criado_em'] ?? $row['atualizado_em'] ?? ''), 0, 10),
        'tempo_operacao_principal' => $tempoOperacaoPrincipal,
        'tempo_total' => $tempoTotal,
        'tempo_deslocamento' => $tempoDeslocamento,
        'tempo_operacao' => $tempoOperacao,
        'tempo_hibrido' => $tempoHibrido,
        'unidade' => cc_text($row['unidade'] ?? $row['unidade_base'] ?? $row['unidade_ref'] ?? ''),
        'unidade_carga' => cc_text($row['unidade_carga'] ?? $row['unidade_base'] ?? $row['unidade_ref'] ?? ''),
        'qtd_ref' => $qtdRef,
        'taxa_base_quantidade' => cc_num($taxaBase['quantidade'] ?? 0),
        'taxa_base_memoria' => $taxaBaseMemoria,
        'taxa_base_unidade' => $taxaBaseAplicada ? 'contentor' : '',
        'taxa_base_codigo' => cc_text($taxaBase['codigo'] ?? ''),
        'taxa_base_kg_manga' => cc_num($taxaBase['kg_manga'] ?? 0),
        'taxa_base_kg_por_contentor' => cc_num($taxaBase['kg_por_contentor'] ?? 0),
        'taxa_producao' => $taxaBaseAplicada ? $tr : 0,
        'taxa_producao_unidade' => $taxaBaseAplicada ? 's/contentor' : '',
        'tempo_base_utilizado' => $tempoBaseUtilizado,
        'tempo_ativo' => $tempoAtivo,
        'ritmo_contentor' => $ritmoContentor,
        'tr' => $tr,
        'tn' => $tn,
        'tp' => $tp,
        'capacidade' => $capacidade,
        'posto' => cc_text($row['posto'] ?? ''),
        'observacao' => cc_text($row['observacao'] ?? $row['obs'] ?? ''),
        'distancia' => $distancia,
        'velocidade' => $velocidade,
        'origem' => cc_text($row['origem'] ?? ''),
        'destino' => cc_text($row['destino'] ?? ''),
        'meio_transporte' => cc_text($row['meio_transporte'] ?? $row['tipo_transporte'] ?? ''),
    ];
}

function cc_row_json(array $row): string {
    $displayRaw = $row['raw'] ?? [];
    $raw = ($row['group_count'] ?? 1) > 1 && is_array($displayRaw['_cc_group_raw'] ?? null)
        ? $displayRaw['_cc_group_raw']
        : $displayRaw;
    $raw['_cc_group_count'] = $row['group_count'] ?? 1;
    $raw['_cc_group_index'] = $row['group_index'] ?? 0;
    $raw['_cc_display_produto_id'] = $displayRaw['produto_id'] ?? '';
    $raw['_cc_display_item'] = $displayRaw['item'] ?? '';
    $raw['_consulta_tipo'] = $row['tipo'] ?? cc_tipo_atividade($raw);
    $raw['id'] = $row['id'] ?? ($raw['id'] ?? '');
    $raw['linha_id'] = $row['linha_id'] ?? ($raw['linha_id'] ?? '');
    $raw['post_index'] = $row['post_index'] ?? ($raw['post_index'] ?? 0);
    $raw['atividade'] = $row['atividade'] ?? ($raw['atividade'] ?? '');
    $raw['descricao'] = $raw['descricao'] ?? ($row['atividade'] ?? '');
    $raw['setor'] = $row['setor'] ?? ($raw['setor'] ?? '');
    $raw['linha'] = $row['linha'] ?? ($raw['linha'] ?? '');
    $raw['grupo_embalagem'] = $raw['grupo_embalagem'] ?? '';
    $raw['variacao'] = $raw['variacao'] ?? '';
    $raw['calibre'] = $row['calibre'] ?? ($raw['calibre'] ?? '');
    $raw['unidade_ref'] = $row['unidade'] ?? ($raw['unidade_ref'] ?? $raw['unidade_base'] ?? $raw['unidade_carga'] ?? '');
    $raw['quantidade_ref'] = $row['qtd_ref'] ?? ($raw['quantidade_ref'] ?? $raw['qtd_ref'] ?? 1);
    $raw['tempo_total'] = $row['tempo_total'] ?: ($raw['tempo_total'] ?? $raw['tempo_s'] ?? $row['tempo_operacao_principal'] ?? 0);
    $raw['tempo_total_s'] = $raw['tempo_total_s'] ?? $raw['tempo_total'];
    $raw['tempo_operacao_s'] = $row['tempo_operacao'] ?: ($raw['tempo_operacao_s'] ?? $row['tempo_operacao_principal'] ?? 0);
    $raw['tempo_deslocamento_s'] = $row['tempo_deslocamento'] ?? ($raw['tempo_deslocamento_s'] ?? 0);
    $raw['distancia_m'] = $row['distancia'] ?? ($raw['distancia_m'] ?? 0);
    $raw['origem'] = $row['origem'] ?? ($raw['origem'] ?? '');
    $raw['destino'] = $row['destino'] ?? ($raw['destino'] ?? '');
    $raw['meio_transporte'] = $row['meio_transporte'] ?? ($raw['meio_transporte'] ?? '');
    $raw['posto'] = $row['posto'] ?? ($raw['posto'] ?? '');
    $raw['observacao'] = $row['observacao'] ?? ($raw['observacao'] ?? '');
    $raw['tr'] = $row['tr'] ?? ($raw['tr'] ?? $raw['TR'] ?? 0);
    $raw['tn'] = $row['tn'] ?? ($raw['tn'] ?? $raw['TN'] ?? 0);
    $raw['tp'] = $row['tp'] ?? ($raw['tp'] ?? $raw['TP'] ?? 0);
    $raw['tempo_unitario_utilizado'] = $row['tempo_operacao_principal'] ?? ($raw['tempo_unitario_utilizado'] ?? $raw['tempo_unitario'] ?? 0);
    $raw['tempo_base_utilizado'] = $raw['tempo_base_utilizado'] ?? 'TP';

    return cc_h(json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT));
}

function cc_actions(array $row): string {
    if (($row['id'] ?? '') === '') {
        return '';
    }
    $json = cc_row_json($row);
    $editLabel = ($row['group_count'] ?? 1) > 1 ? 'Editar grupo' : 'Editar';
    $editUrl = 'atividades_posto.php?edit_id=' . rawurlencode((string)($row['id'] ?? ''))
        . '&linha=' . rawurlencode((string)($row['linha_id'] ?? ''))
        . '&post=' . rawurlencode((string)($row['post_index'] ?? 0))
        . '&return_to=consulta&return_url=' . rawurlencode('consultar_cronoanalises.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
    $detach = ($row['group_count'] ?? 1) > 1
        ? '<button type="button" class="crono-action-btn" onclick="ccDetach(this)" data-row="' . $json . '">Desacoplar</button>'
        : '';
    return '<div class="crono-row-actions">'
        . '<a class="crono-action-btn" href="' . cc_h($editUrl) . '">' . $editLabel . '</a>'
        . $detach
        . '<button type="button" class="crono-action-btn danger" onclick="ccDelete(this)" data-row="' . $json . '">Excluir</button>'
        . '</div>';
}

function cc_match_filter(string $value, string $filter): bool {
    return $filter === '' || stripos($value, $filter) !== false;
}

function cc_aplicar_filtros(array $row, array $filtros): bool {
    if ($filtros['periodo_inicio'] !== '' && $row['data'] !== '' && $row['data'] < $filtros['periodo_inicio']) return false;
    if ($filtros['periodo_fim'] !== '' && $row['data'] !== '' && $row['data'] > $filtros['periodo_fim']) return false;
    if (!cc_match_filter($row['setor'], $filtros['setor'])) return false;
    if (!cc_match_filter($row['linha'], $filtros['linha'])) return false;
    if (!cc_match_filter($row['item'], $filtros['item'])) return false;
    if (!cc_match_filter($row['calibre'], $filtros['calibre'])) return false;
    if ($filtros['tipo_atividade'] !== '' && $row['tipo'] !== $filtros['tipo_atividade']) return false;
    if ($filtros['status'] !== '' && strcasecmp($row['status'], $filtros['status']) !== 0) return false;
    return true;
}

function cc_options(array $rows, string $field): array {
    $values = [];
    foreach ($rows as $row) {
        $value = cc_text($row[$field] ?? '');
        if ($value !== '') {
            $values[$value] = $value;
        }
    }
    natcasesort($values);
    return array_values($values);
}

function cc_groups(array $rows): array {
    return [
        'operacao' => array_values(array_filter($rows, fn($row) => $row['tipo'] === 'operacao')),
        'transporte' => array_values(array_filter($rows, fn($row) => $row['tipo'] === 'transporte')),
        'hibrida' => array_values(array_filter($rows, fn($row) => $row['tipo'] === 'hibrida')),
    ];
}

function cc_count_label(int $count): string {
    return $count . ' ' . ($count === 1 ? 'registro' : 'registros');
}

$allRows = array_map('cc_normalizar', cc_expandir_itens_transporte($repository->listarCronoanalises([])));
$rows = array_values(array_filter($allRows, fn($row) => cc_aplicar_filtros($row, $filtros)));
usort($rows, fn($a, $b) => strnatcasecmp(implode('|', [$a['tipo'], $a['setor'], $a['atividade'], $a['item'], $a['calibre']]), implode('|', [$b['tipo'], $b['setor'], $b['atividade'], $b['item'], $b['calibre']])));
$groups = cc_groups($rows);

if (($_GET['export'] ?? '') === 'excel') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="consultar_cronoanalises.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Tipo', 'Atividade', 'Setor', 'Linha', 'Item/Embalagem', 'Calibre', 'Tempo (s)', 'Unidade', 'Qtd Ref', 'TR', 'TN', 'TP', 'Capacidade', 'Taxa producao', 'Contentores equivalentes', 'Ritmo/contentor', 'Distancia (m)', 'Tempo desloc. (s)', 'Tempo operacao (s)', 'Velocidade (m/s)', 'Origem', 'Destino', 'Meio transporte', 'Posto', 'Status', 'Observacao'], ';');
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['tipo_label'], $row['atividade'], $row['setor'], $row['linha'], $row['item'], $row['calibre'],
            cc_fmt($row['tempo_operacao_principal']), $row['unidade'], cc_fmt($row['qtd_ref']), cc_fmt($row['tr']), cc_fmt($row['tn']), cc_fmt($row['tp']),
            cc_fmt($row['capacidade']), cc_taxa_label($row), cc_taxa_memoria_label($row), cc_fmt($row['ritmo_contentor']), cc_fmt($row['distancia']), cc_fmt($row['tempo_deslocamento']), cc_fmt($row['tempo_operacao']),
            cc_fmt($row['velocidade']), $row['origem'], $row['destino'], $row['meio_transporte'], $row['posto'], $row['status'], $row['observacao']
        ], ';');
    }
    fclose($out);
    exit;
}

$setores = cc_options($allRows, 'setor');
$linhas = cc_options($allRows, 'linha');
$itens = cc_options($allRows, 'item');
$calibres = cc_options($allRows, 'calibre');

include __DIR__ . '/menu.php';
?>

<div class="content crono-page">
    <div class="crono-shell">
        <section class="crono-consulta-header">
            <div class="crono-title">
                <span class="crono-title-icon">#</span>
                <div>
                    <h1>Consultar Cronoanalises</h1>
                    <p>Consulta central de atividades cronometradas da fabrica.</p>
                </div>
            </div>
            <div class="crono-header-actions">
                <a class="crono-btn clear" href="?<?php echo cc_h(http_build_query(array_merge($filtros, ['export' => 'excel']))); ?>">Exportar Excel</a>
                <a class="crono-btn save" href="atividades_posto.php">+ Nova cronoanalise</a>
            </div>
        </section>

        <section class="crono-form-card crono-filter-card">
            <form method="get" class="crono-query-grid">
                <div class="crono-field"><label>Periodo inicial</label><input type="date" class="crono-input" name="periodo_inicio" value="<?php echo cc_h($filtros['periodo_inicio']); ?>"></div>
                <div class="crono-field"><label>Periodo final</label><input type="date" class="crono-input" name="periodo_fim" value="<?php echo cc_h($filtros['periodo_fim']); ?>"></div>
                <div class="crono-field"><label>Setor</label><input class="crono-input" name="setor" list="cc_setores" value="<?php echo cc_h($filtros['setor']); ?>" placeholder="Todos"></div>
                <div class="crono-field"><label>Linha</label><input class="crono-input" name="linha" list="cc_linhas" value="<?php echo cc_h($filtros['linha']); ?>" placeholder="Todas"></div>
                <div class="crono-field"><label>Item / Embalagem</label><input class="crono-input" name="item" list="cc_itens" value="<?php echo cc_h($filtros['item']); ?>" placeholder="Todos"></div>
                <div class="crono-field"><label>Calibre</label><input class="crono-input" name="calibre" list="cc_calibres" value="<?php echo cc_h($filtros['calibre']); ?>" placeholder="Todos"></div>
                <div class="crono-field">
                    <label>Tipo de atividade</label>
                    <select class="crono-input" name="tipo_atividade">
                        <option value="">Todos</option>
                        <option value="operacao" <?php echo $filtros['tipo_atividade'] === 'operacao' ? 'selected' : ''; ?>>Operacao / Posto fixo</option>
                        <option value="transporte" <?php echo $filtros['tipo_atividade'] === 'transporte' ? 'selected' : ''; ?>>Transporte / Movimentacao</option>
                        <option value="hibrida" <?php echo $filtros['tipo_atividade'] === 'hibrida' ? 'selected' : ''; ?>>Hibrida</option>
                    </select>
                </div>
                <div class="crono-field">
                    <label>Status</label>
                    <select class="crono-input" name="status">
                        <option value="">Todos</option>
                        <option value="Ativa" <?php echo $filtros['status'] === 'Ativa' ? 'selected' : ''; ?>>Ativa</option>
                        <option value="Inativa" <?php echo $filtros['status'] === 'Inativa' ? 'selected' : ''; ?>>Inativa</option>
                    </select>
                </div>
                <div class="crono-filter-actions">
                    <button class="crono-btn save" type="submit">Filtrar</button>
                    <a class="crono-btn clear" href="consultar_cronoanalises.php">Limpar</a>
                </div>
            </form>
            <datalist id="cc_setores"><?php foreach ($setores as $value): ?><option value="<?php echo cc_h($value); ?>"></option><?php endforeach; ?></datalist>
            <datalist id="cc_linhas"><?php foreach ($linhas as $value): ?><option value="<?php echo cc_h($value); ?>"></option><?php endforeach; ?></datalist>
            <datalist id="cc_itens"><?php foreach ($itens as $value): ?><option value="<?php echo cc_h($value); ?>"></option><?php endforeach; ?></datalist>
            <datalist id="cc_calibres"><?php foreach ($calibres as $value): ?><option value="<?php echo cc_h($value); ?>"></option><?php endforeach; ?></datalist>
        </section>

        <section class="crono-group-card">
            <div class="crono-group-title" role="button" tabindex="0" aria-expanded="true" aria-controls="cc_group_operacao" onclick="ccToggleGroup(this)" onkeydown="ccToggleGroupKey(event, this)"><strong>OPERACAO / POSTO FIXO</strong><span><?php echo cc_count_label(count($groups['operacao'])); ?></span></div>
            <div class="crono-table-wrap" id="cc_group_operacao">
                <table class="crono-table">
                    <thead><tr><th>Atividade</th><th>Setor</th><th>Item/Embalagem</th><th>Calibre</th><th>Tempo (s)</th><th>Unidade</th><th>Total de caixa</th><th>Qtdd Eq. CTT</th><th>Ritmo/contentor</th><th>TR</th><th>TN</th><th>TP</th><th>Capacidade</th><th>Posto</th><th>Observacao</th><th>Acoes</th></tr></thead>
                    <tbody>
                        <?php foreach ($groups['operacao'] as $row): ?>
                            <tr><td><?php echo cc_h($row['atividade']); ?></td><td><?php echo cc_h($row['setor']); ?></td><td><?php echo cc_h($row['item']); ?></td><td><?php echo cc_h($row['calibre']); ?></td><td><?php echo cc_fmt($row['tempo_operacao_principal']); ?></td><td><?php echo cc_h($row['unidade']); ?></td><td><?php echo cc_fmt($row['qtd_ref']); ?></td><td><?php echo cc_fmt($row['taxa_base_quantidade']); ?></td><td><?php echo cc_fmt($row['ritmo_contentor']); ?></td><td><?php echo cc_fmt($row['tr']); ?></td><td><?php echo cc_fmt($row['tn']); ?></td><td><?php echo cc_fmt($row['tp']); ?></td><td><?php echo cc_fmt($row['capacidade'], 0); ?></td><td><?php echo cc_h($row['posto']); ?></td><td><?php echo cc_h($row['observacao']); ?></td><td><?php echo cc_actions($row); ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($groups['operacao'])): ?><tr><td colspan="16" class="crono-empty">Nenhum registro de operacao encontrado.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="crono-group-card">
            <div class="crono-group-title" role="button" tabindex="0" aria-expanded="true" aria-controls="cc_group_transporte" onclick="ccToggleGroup(this)" onkeydown="ccToggleGroupKey(event, this)"><strong>TRANSPORTE / MOVIMENTACAO</strong><span><?php echo cc_count_label(count($groups['transporte'])); ?></span></div>
            <div class="crono-table-wrap" id="cc_group_transporte">
                <table class="crono-table">
                    <thead><tr><th>Atividade</th><th>Setor</th><th>Item/Carga</th><th>Calibre</th><th>Distancia (m)</th><th>Tempo (s)</th><th>TR</th><th>TN</th><th>TP</th><th>Qtdd Eq. CTT</th><th>Ritmo/contentor</th><th>Velocidade (m/s)</th><th>Unidade carga</th><th>Qtd Ref</th><th>Origem</th><th>Destino</th><th>Meio transporte</th><th>Observacao</th><th>Acoes</th></tr></thead>
                    <tbody>
                        <?php foreach ($groups['transporte'] as $row): ?>
                            <tr><td><?php echo cc_h($row['atividade']); ?></td><td><?php echo cc_h($row['setor']); ?></td><td><?php echo cc_h($row['item']); ?></td><td><?php echo cc_h($row['calibre']); ?></td><td><?php echo cc_fmt($row['distancia']); ?></td><td><?php echo cc_fmt($row['tempo_total']); ?></td><td><?php echo cc_fmt($row['tr']); ?></td><td><?php echo cc_fmt($row['tn']); ?></td><td><?php echo cc_fmt($row['tp']); ?></td><td><?php echo cc_fmt($row['capacidade'], 0); ?></td><td><?php echo cc_h(cc_taxa_label($row)); ?></td><td><?php echo cc_fmt($row['velocidade']); ?></td><td><?php echo cc_h($row['unidade_carga']); ?></td><td><?php echo cc_fmt($row['qtd_ref']); ?></td><td><?php echo cc_h($row['origem']); ?></td><td><?php echo cc_h($row['destino']); ?></td><td><?php echo cc_h($row['meio_transporte']); ?></td><td><?php echo cc_h($row['observacao']); ?></td><td><?php echo cc_actions($row); ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($groups['transporte'])): ?><tr><td colspan="19" class="crono-empty">Nenhum registro de transporte encontrado.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="crono-group-card">
            <div class="crono-group-title" role="button" tabindex="0" aria-expanded="true" aria-controls="cc_group_hibrida" onclick="ccToggleGroup(this)" onkeydown="ccToggleGroupKey(event, this)"><strong>HIBRIDA / OPERACAO + DESLOCAMENTO</strong><span><?php echo cc_count_label(count($groups['hibrida'])); ?></span></div>
            <div class="crono-table-wrap" id="cc_group_hibrida">
                <table class="crono-table">
                    <thead><tr><th>Atividade</th><th>Setor</th><th>Item/Embalagem</th><th>Calibre</th><th>Distancia (m)</th><th>Tempo desloc. (s)</th><th>Tempo operacao (s)</th><th>Tempo total (s)</th><th>Unidade</th><th>Qtd Ref</th><th>Qtdd Eq. CTT</th><th>Ritmo/contentor</th><th>Capacidade</th><th>Posto</th><th>Observacao</th><th>Acoes</th></tr></thead>
                    <tbody>
                        <?php foreach ($groups['hibrida'] as $row): ?>
                            <tr><td><?php echo cc_h($row['atividade']); ?></td><td><?php echo cc_h($row['setor']); ?></td><td><?php echo cc_h($row['item']); ?></td><td><?php echo cc_h($row['calibre']); ?></td><td><?php echo cc_fmt($row['distancia']); ?></td><td><?php echo cc_fmt($row['tempo_deslocamento']); ?></td><td><?php echo cc_fmt($row['tempo_operacao']); ?></td><td><?php echo cc_fmt($row['tempo_hibrido']); ?></td><td><?php echo cc_h($row['unidade']); ?></td><td><?php echo cc_fmt($row['qtd_ref']); ?></td><td><?php echo cc_h(cc_taxa_memoria_label($row)); ?></td><td><?php echo cc_fmt($row['ritmo_contentor']); ?></td><td><?php echo cc_fmt($row['capacidade'], 0); ?></td><td><?php echo cc_h($row['posto']); ?></td><td><?php echo cc_h($row['observacao']); ?></td><td><?php echo cc_actions($row); ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($groups['hibrida'])): ?><tr><td colspan="16" class="crono-empty">Nenhum registro hibrido encontrado.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<div class="crono-modal-backdrop" id="cc_edit_modal" hidden>
    <div class="crono-modal" role="dialog" aria-modal="true" aria-labelledby="cc_modal_title">
        <form id="cc_edit_form">
            <div class="crono-modal-head">
                <h2 id="cc_modal_title">Editar cronoanalise</h2>
                <button type="button" class="crono-modal-close" onclick="ccCloseEdit()" aria-label="Fechar">x</button>
            </div>
            <div class="crono-modal-grid">
                <div class="crono-field"><label>Atividade</label><input class="crono-input" name="atividade" id="cc_atividade" required></div>
                <div class="crono-field"><label>Setor</label><input class="crono-input" name="setor" id="cc_setor"></div>
                <div class="crono-field"><label>Linha</label><input class="crono-input" name="linha_nome" id="cc_linha"></div>
                <div class="crono-field"><label>Posto</label><input class="crono-input" name="posto" id="cc_posto"></div>
                <div class="crono-field"><label>Grupo embalagem</label><input class="crono-input" name="grupo_embalagem" id="cc_grupo"></div>
                <div class="crono-field"><label>Variacao</label><input class="crono-input" name="variacao" id="cc_variacao"></div>
                <div class="crono-field"><label>Calibre</label><input class="crono-input" name="calibre" id="cc_calibre"></div>
                <div class="crono-field"><label>Unidade</label><input class="crono-input" name="unidade_ref" id="cc_unidade"></div>
                <div class="crono-field"><label>Total de caixa</label><input type="number" min="0.01" step="0.01" class="crono-input" name="quantidade_ref" id="cc_qtd" required></div>
                <div class="crono-field"><label>Tempo total (s)</label><input type="number" min="0.01" step="0.01" class="crono-input" name="tempo_total" id="cc_tempo" required></div>
                <div class="crono-field"><label>Distancia (m)</label><input type="number" min="0" step="0.01" class="crono-input" name="distancia_m" id="cc_distancia"></div>
                <div class="crono-field"><label>Tempo desloc. (s)</label><input type="number" min="0" step="0.01" class="crono-input" name="tempo_deslocamento_s" id="cc_deslocamento"></div>
                <div class="crono-field"><label>Tempo operacao (s)</label><input type="number" min="0" step="0.01" class="crono-input" name="tempo_operacao_s" id="cc_operacao"></div>
                <div class="crono-field"><label>Origem</label><input class="crono-input" name="origem" id="cc_origem"></div>
                <div class="crono-field"><label>Destino</label><input class="crono-input" name="destino" id="cc_destino"></div>
                <div class="crono-field"><label>Meio transporte</label><input class="crono-input" name="meio_transporte" id="cc_meio"></div>
                <div class="crono-field crono-field-wide"><label>Observacao</label><textarea class="crono-input crono-textarea" name="observacao" id="cc_observacao"></textarea></div>
            </div>
            <div class="crono-feedback" id="cc_modal_feedback" hidden></div>
            <div class="crono-modal-actions">
                <button type="button" class="crono-btn clear" onclick="ccCloseEdit()">Cancelar</button>
                <button type="submit" class="crono-btn save">Salvar alteracoes</button>
            </div>
        </form>
    </div>
</div>

<script>
let ccEditingRow = null;
const ccScalarKeys = new Set([
    'id', 'linha_id', 'post_index', 'tipo_atividade', 'tipo_operacao', 'atividade', 'descricao',
    'grupo_embalagem', 'variacao', 'calibre', 'chave_tecnica', 'setor', 'setor_id', 'linha',
    'linha_nome', 'linha_ref_id', 'posto', 'posto_id', 'produto_id', 'unidade_ref', 'unidade_base',
    'unidade_carga', 'quantidade_ref', 'qtd_ref', 'qtd_base', 'numero_frutos', 'num_frutos',
    'peso_fruto_g', 'er', 'tempo_total', 'tempo_s', 'tempo_total_s', 'tempo_cx_s',
    'tempo_caixa_s', 'tempo_fruto_s', 'tempo_operacao_s', 'tempo_deslocamento_s',
    'tempo_base_utilizado', 'tempo_unitario_utilizado', 'tempo_unitario', 'tr', 'tn', 'tp',
    'TR', 'TN', 'TP', 'fator_ritmo', 'tolerancia_total', 'fator_tolerancia', 'distancia_m',
    'velocidade_m_min', 'tipo_transporte', 'fluxo_bpm_id', 'codigo_arvore_estrutura',
    'origem', 'destino', 'meio_transporte', 'operadores'
]);

function ccParseRow(button) {
    try {
        return JSON.parse(button.dataset.row || '{}');
    } catch (error) {
        return {};
    }
}

function ccValue(row, ...keys) {
    for (const key of keys) {
        if (typeof key !== 'string') return key;
        if (row[key] !== undefined && row[key] !== null && row[key] !== '') return row[key];
    }
    return '';
}

function ccNumber(value) {
    return parseFloat(String(value || '').replace(/\./g, '').replace(',', '.')) || 0;
}

function ccSet(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
}

function ccShowFeedback(message, type = 'error') {
    const box = document.getElementById('cc_modal_feedback');
    box.textContent = message;
    box.className = `crono-feedback ${type}`;
    box.hidden = false;
}

function ccToggleGroup(title) {
    const target = document.getElementById(title.getAttribute('aria-controls') || '');
    if (!target) return;

    const collapsed = !target.hidden;
    target.hidden = collapsed;
    title.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
}

function ccToggleGroupKey(event, title) {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();
    ccToggleGroup(title);
}

function ccOpenEdit(button) {
    ccEditingRow = ccParseRow(button);
    document.getElementById('cc_modal_feedback').hidden = true;
    ccSet('cc_atividade', ccValue(ccEditingRow, 'atividade', 'descricao'));
    ccSet('cc_setor', ccValue(ccEditingRow, 'setor'));
    ccSet('cc_linha', ccValue(ccEditingRow, 'linha_nome', 'linha'));
    ccSet('cc_posto', ccValue(ccEditingRow, 'posto'));
    ccSet('cc_grupo', ccValue(ccEditingRow, 'grupo_embalagem'));
    ccSet('cc_variacao', ccValue(ccEditingRow, 'variacao'));
    ccSet('cc_calibre', ccValue(ccEditingRow, 'calibre'));
    ccSet('cc_unidade', ccValue(ccEditingRow, 'unidade_ref', 'unidade_base', 'unidade_carga'));
    ccSet('cc_qtd', ccValue(ccEditingRow, 'quantidade_ref', 'qtd_ref', 'qtd_base', 'numero_frutos', 'num_frutos', 1));
    ccSet('cc_tempo', ccValue(ccEditingRow, 'tempo_total', 'tempo_s', 'tempo_total_s', 'tempo_unitario_utilizado', 'tempo_unitario'));
    ccSet('cc_distancia', ccValue(ccEditingRow, 'distancia_m'));
    ccSet('cc_deslocamento', ccValue(ccEditingRow, 'tempo_deslocamento_s'));
    ccSet('cc_operacao', ccValue(ccEditingRow, 'tempo_operacao_s', 'tempo_unitario_utilizado', 'tempo_unitario'));
    ccSet('cc_origem', ccValue(ccEditingRow, 'origem'));
    ccSet('cc_destino', ccValue(ccEditingRow, 'destino'));
    ccSet('cc_meio', ccValue(ccEditingRow, 'meio_transporte', 'tipo_transporte'));
    ccSet('cc_observacao', ccValue(ccEditingRow, 'observacao'));
    document.getElementById('cc_edit_modal').hidden = false;
}

function ccCloseEdit() {
    document.getElementById('cc_edit_modal').hidden = true;
    ccEditingRow = null;
}

async function ccDelete(button) {
    const row = ccParseRow(button);
    if (!row.id || !confirm('Deseja excluir esta cronoanalise?')) return;
    const formData = new FormData();
    formData.append('action', 'delete_atividade');
    formData.append('id', row.id);
    formData.append('linha_id', row.linha_id || '');
    formData.append('post_index', row.post_index || 0);

    const response = await fetch('api_cronoanalise.php', { method: 'POST', body: formData });
    const result = await response.json().catch(() => ({ status: 'error', message: 'Resposta invalida do servidor.' }));
    if (response.ok && result.status === 'success') {
        location.reload();
        return;
    }
    alert(result.message || 'Erro ao excluir cronoanalise.');
}

async function ccDetach(button) {
    const row = ccParseRow(button);
    if (!row.id || !confirm('Deseja desacoplar este item do grupo? Ele passara a ser um transporte separado.')) return;

    const formData = new FormData();
    formData.append('action', 'desacoplar_transporte_item');
    formData.append('id', row.id);
    formData.append('item_index', row._cc_group_index ?? 0);

    const response = await fetch('api_cronoanalise.php', { method: 'POST', body: formData });
    const result = await response.json().catch(() => ({ status: 'error', message: 'Resposta invalida do servidor.' }));
    if (response.ok && result.status === 'success') {
        location.reload();
        return;
    }
    alert(result.message || 'Erro ao desacoplar item.');
}

document.getElementById('cc_edit_form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!ccEditingRow) return;

    const form = event.currentTarget;
    const formData = new FormData();
    for (const [key, value] of Object.entries(ccEditingRow)) {
        if (ccScalarKeys.has(key) && value !== null && typeof value !== 'object') {
            formData.append(key, value);
        }
    }
    if (Array.isArray(ccEditingRow.tolerancias)) {
        formData.set('tolerancias_json', JSON.stringify(ccEditingRow.tolerancias));
    }

    new FormData(form).forEach((value, key) => formData.set(key, value));
    const tipo = ccEditingRow._consulta_tipo || ccEditingRow.tipo_atividade || '';
    formData.set('action', tipo === 'transporte' ? 'save_transporte' : 'save_atividade');
    formData.set('id', ccEditingRow.id || '');
    formData.set('linha_id', ccEditingRow.linha_id || '');
    formData.set('post_index', ccEditingRow.post_index || 0);
    formData.set('descricao', formData.get('atividade') || '');
    formData.set('linha_ref_id', ccEditingRow.linha_ref_id || ccEditingRow.linha_id || '');
    formData.set('tipo_atividade', tipo || 'operacao');
    const tempoTotal = ccNumber(formData.get('tempo_total'));
    const quantidade = Math.max(ccNumber(formData.get('quantidade_ref')), 1);
    const fatorRitmo = ccNumber(ccEditingRow.fator_ritmo || 1) || 1;
    const fatorTolerancia = ccNumber(ccEditingRow.fator_tolerancia || 1) || 1;
    const tr = tipo === 'transporte' ? tempoTotal : tempoTotal / quantidade;
    const tn = tr * fatorRitmo;
    const tp = tn * fatorTolerancia;
    const tempoBase = String(ccEditingRow.tempo_base_utilizado || 'TP').toUpperCase();
    const tempoUnitario = tempoBase === 'TR' ? tr : (tempoBase === 'TN' ? tn : tp);

    formData.set('tempo_total_s', tempoTotal);
    formData.set('tempo_operacao_s', tipo === 'hibrida' ? (ccNumber(formData.get('tempo_operacao_s')) || tempoUnitario) : tempoUnitario);
    formData.set('tempo_unitario_utilizado', tempoUnitario);
    formData.set('tr', tr);
    formData.set('tn', tn);
    formData.set('tp', tp);

    const response = await fetch('api_cronoanalise.php', { method: 'POST', body: formData });
    const result = await response.json().catch(() => ({ status: 'error', message: 'Resposta invalida do servidor.' }));
    if (response.ok && result.status === 'success') {
        ccShowFeedback('Cronoanalise atualizada com sucesso.', 'success');
        window.setTimeout(() => location.reload(), 450);
        return;
    }
    ccShowFeedback(result.message || 'Erro ao salvar alteracoes.');
});
</script>

<style>
.crono-page { background: #f5f7fb; }
.crono-shell { max-width: 1540px; margin: 0 auto; }
.crono-consulta-header { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 16px; }
.crono-title { display: flex; align-items: center; gap: 12px; }
.crono-title-icon { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border: 2px solid #0b70d7; border-radius: 50%; color: #0b70d7; font-size: 22px; font-weight: 900; }
.crono-title h1 { margin: 0; padding: 0; border: 0; color: #0f1e3a; font-size: 24px; font-weight: 900; }
.crono-title p { margin: 3px 0 0; color: #52627a; font-size: 13px; }
.crono-header-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
.crono-form-card, .crono-group-card { background: #fff; border: 1px solid #dce4ef; border-radius: 8px; box-shadow: 0 2px 10px rgba(20, 35, 55, .06); }
.crono-filter-card { padding: 16px; margin-bottom: 16px; }
.crono-query-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; align-items: end; }
.crono-field label { display: block; margin-bottom: 6px; color: #25344d; font-size: 12px; font-weight: 800; }
.crono-input { width: 100%; height: 38px; border: 1px solid #cfd9e6; border-radius: 5px; background: #fff; color: #152034; padding: 7px 10px; font-size: 13px; }
.crono-input:focus { outline: 0; border-color: #0b75e5; box-shadow: 0 0 0 3px rgba(11, 117, 229, .12); }
.crono-btn { min-height: 38px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #cbd8e8; border-radius: 5px; padding: 0 14px; background: #fff; color: #164b86; font-size: 13px; font-weight: 900; text-decoration: none; cursor: pointer; }
.crono-btn.save { border-color: #0b70d7; background: #0b70d7; color: #fff; }
.crono-btn.clear { background: #fff; }
.crono-filter-actions { display: flex; gap: 8px; }
.crono-group-card { margin-bottom: 16px; overflow: hidden; }
.crono-group-title { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid #e4e9f0; background: #0b70d7; color: #fff; cursor: pointer; }
.crono-group-title strong { font-size: 14px; }
.crono-group-title span { font-size: 12px; font-weight: 900; }
.crono-table-wrap { overflow-x: auto; }
.crono-table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 1180px; }
.crono-table th { background: #f8fafc; color: #263852; font-weight: 900; text-align: left; }
.crono-table th, .crono-table td { border-bottom: 1px solid #e7edf5; border-right: 1px solid #eef2f7; padding: 8px 10px; white-space: nowrap; }
.crono-table td:last-child, .crono-table th:last-child { border-right: 0; white-space: normal; min-width: 160px; }
.crono-empty { text-align: center; color: #78838f; font-size: 13px; padding: 18px !important; }
.crono-row-actions { display: flex; gap: 6px; align-items: center; }
.crono-action-btn { min-height: 28px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #cbd8e8; border-radius: 5px; background: #fff; color: #164b86; padding: 0 9px; font-size: 12px; font-weight: 900; text-decoration: none; cursor: pointer; }
.crono-action-btn.danger { border-color: #f3c1c1; color: #b42318; }
.crono-modal-backdrop { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 18px; background: rgba(15, 30, 58, .42); }
.crono-modal-backdrop[hidden] { display: none; }
.crono-modal { width: min(920px, 100%); max-height: 92vh; overflow: auto; background: #fff; border: 1px solid #dce4ef; border-radius: 8px; box-shadow: 0 18px 45px rgba(15, 30, 58, .22); }
.crono-modal-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 18px; border-bottom: 1px solid #e4e9f0; }
.crono-modal-head h2 { margin: 0; color: #0f1e3a; font-size: 18px; font-weight: 900; }
.crono-modal-close { width: 32px; height: 32px; border: 1px solid #cbd8e8; border-radius: 5px; background: #fff; color: #263852; font-size: 16px; font-weight: 900; cursor: pointer; }
.crono-modal-grid { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 12px; padding: 16px 18px; }
.crono-field-wide { grid-column: span 4; }
.crono-textarea { min-height: 74px; resize: vertical; }
.crono-modal-actions { display: flex; justify-content: flex-end; gap: 8px; padding: 0 18px 18px; }
.crono-modal .crono-feedback { margin: 0 18px 14px; padding: 10px 12px; border-radius: 5px; font-size: 13px; font-weight: 800; }
.crono-feedback.success { background: #e9f8ef; color: #16703a; border: 1px solid #bfe9cc; }
.crono-feedback.error { background: #fff0f0; color: #b42318; border: 1px solid #f3c1c1; }
@media (max-width: 1180px) {
    .crono-consulta-header { flex-direction: column; align-items: flex-start; }
    .crono-query-grid { grid-template-columns: repeat(2, minmax(180px, 1fr)); }
}
@media (max-width: 720px) {
    .crono-query-grid { grid-template-columns: 1fr; }
    .crono-modal-grid { grid-template-columns: 1fr; }
    .crono-field-wide { grid-column: span 1; }
    .crono-filter-actions, .crono-header-actions { width: 100%; }
    .crono-btn { flex: 1; }
}
</style>

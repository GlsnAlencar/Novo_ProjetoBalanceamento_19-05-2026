<?php

class CronoanaliseRepository {
    private string $path;

    public function __construct(string $path) {
        $this->path = $path;
    }

    public function load(): array {
        return cod_12_05_safe_load_json(
            $this->path,
            fn() => ['version' => 1, 'setores' => []],
            fn($data) => isset($data['setores']) && is_array($data['setores'])
        );
    }

    public function save(array $data): void {
        cod_12_05_safe_write_json($this->path, $data);
    }

    public function salvarCronoanalise(string $linha_id, int $post_index, array $atividade): array {
        $data = $this->load();
        $key = 'posto_' . $post_index;
        if (!isset($data['cronoanalises']) || !is_array($data['cronoanalises'])) {
            $data['cronoanalises'] = [];
        }

        $central = $this->normalizarRegistroCentral($atividade, $linha_id, $post_index);
        $foundCentral = false;
        foreach ($data['cronoanalises'] as &$row) {
            if (($row['id'] ?? '') === $central['id']) {
                $central['criado_em'] = $row['criado_em'] ?? $central['criado_em'];
                $row = $central;
                $foundCentral = true;
                break;
            }
        }
        unset($row);
        if (!$foundCentral) {
            $data['cronoanalises'][] = $central;
        }

        foreach ($data['setores'] as &$setor) {
            foreach (($setor['linhas'] ?? []) as &$linha) {
                if (($linha['id'] ?? '') !== $linha_id) {
                    continue;
                }

                if (!isset($linha['atividades_por_posto']) || !is_array($linha['atividades_por_posto'])) {
                    $linha['atividades_por_posto'] = [];
                }
                if (!isset($linha['atividades_por_posto'][$key]) || !is_array($linha['atividades_por_posto'][$key])) {
                    $linha['atividades_por_posto'][$key] = [];
                }

                $found = false;
                foreach ($linha['atividades_por_posto'][$key] as &$current) {
                    if (($current['id'] ?? '') === $atividade['id']) {
                        $atividade['criado_em'] = $current['criado_em'] ?? $atividade['criado_em'];
                        $current = $atividade;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $linha['atividades_por_posto'][$key][] = $atividade;
                }

                $linha['updated_at'] = date('Y-m-d H:i:s');
                $setor['updated_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');
                $this->save($data);

                return ['status' => 'success', 'atividade' => $atividade];
            }
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->save($data);

        return ['status' => 'success', 'atividade' => $atividade];
    }

    public function excluirCronoanalise(string $linha_id, int $post_index, string $id): array {
        $data = $this->load();
        $usage = $this->localizarUsoCronoanalise($data, $id);
        if (!empty($usage)) {
            return [
                'status' => 'error',
                'message' => 'Esta cronoanalise esta vinculada a um posto/fluxo e nao pode ser arquivada.',
                'usos' => $usage,
            ];
        }

        $archived = false;
        $now = date('Y-m-d H:i:s');

        if (isset($data['cronoanalises']) && is_array($data['cronoanalises'])) {
            foreach ($data['cronoanalises'] as &$row) {
                if (($row['id'] ?? '') !== $id) {
                    continue;
                }
                $row['ativo'] = 0;
                $row['status'] = 'Arquivada';
                $row['arquivado_em'] = $now;
                $row['atualizado_em'] = $now;
                $archived = true;
                break;
            }
            unset($row);
        }

        if (!$archived) {
            return ['status' => 'error', 'message' => 'Cronoanalise nao encontrada.'];
        }

        $data['updated_at'] = $now;
        $this->save($data);

        return ['status' => 'success', 'message' => 'Cronoanalise arquivada com sucesso.'];
    }

    private function localizarUsoCronoanalise(array $data, string $id): array {
        $usage = [];
        if ($id === '') {
            return $usage;
        }

        foreach (($data['setores'] ?? []) as $setor) {
            $setorNome = (string)($setor['nome'] ?? '');
            foreach (($setor['linhas'] ?? []) as $linha) {
                $linhaNome = (string)($linha['nome'] ?? '');
                foreach (($linha['atividades_por_posto'] ?? []) as $postoKey => $atividades) {
                    foreach ((array)$atividades as $atividade) {
                        if (($atividade['id'] ?? '') === $id) {
                            $usage[] = trim($setorNome . ' / ' . $linhaNome . ' / ' . (string)$postoKey, ' /');
                        }
                    }
                }

                $nodes = $linha['drawflow_data']['drawflow']['Home']['data'] ?? [];
                if (!is_array($nodes)) {
                    continue;
                }
                foreach ($nodes as $node) {
                    $nodeData = $node['data'] ?? [];
                    foreach (($nodeData['atividades'] ?? []) as $atividade) {
                        if (is_array($atividade) && ($atividade['id'] ?? '') === $id) {
                            $usage[] = trim($setorNome . ' / ' . $linhaNome . ' / ' . (string)($nodeData['name'] ?? 'Fluxo'), ' /');
                        }
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($usage)));
    }

    public function desacoplarTransporteItem(string $id, int $item_index): array {
        $data = $this->load();
        $newId = 'transp_' . date('YmdHis') . '_' . random_int(1000, 9999);
        $changed = false;

        $splitRow = function (array $row) use ($id, $item_index, $newId, &$changed): ?array {
            if (($row['id'] ?? '') !== $id) {
                return null;
            }

            $produtoIds = preg_split('/\s*,\s*/', (string)($row['produto_id'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $items = preg_split('/\s*\|\s*/', (string)($row['item'] ?? $row['item_carga'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $produtoIds = array_values(array_map('trim', $produtoIds));
            $items = array_values(array_map('trim', $items));
            $count = max(count($produtoIds), count($items));
            if ($count <= 1 || $item_index < 0 || $item_index >= $count) {
                return null;
            }

            $selected = $row;
            $selected['id'] = $newId;
            $selected['produto_id'] = $produtoIds[$item_index] ?? '';
            $selected['item'] = $items[$item_index] ?? ($row['item'] ?? '');
            $selected['item_carga'] = $selected['item'];
            $selected['criado_em'] = date('Y-m-d H:i:s');
            $selected['atualizado_em'] = date('Y-m-d H:i:s');

            unset($produtoIds[$item_index], $items[$item_index]);
            $row['produto_id'] = implode(',', array_values($produtoIds));
            $row['item'] = implode(' | ', array_values($items));
            $row['item_carga'] = $row['item'];
            $row['atualizado_em'] = date('Y-m-d H:i:s');

            $changed = true;
            return [$row, $selected];
        };

        if (isset($data['cronoanalises']) && is_array($data['cronoanalises'])) {
            foreach ($data['cronoanalises'] as $idx => $row) {
                $result = $splitRow((array)$row);
                if ($result) {
                    [$data['cronoanalises'][$idx], $selected] = $result;
                    $data['cronoanalises'][] = $selected;
                    break;
                }
            }
        }

        foreach ($data['setores'] as &$setor) {
            foreach (($setor['linhas'] ?? []) as &$linha) {
                foreach (($linha['atividades_por_posto'] ?? []) as $postoKey => $atividades) {
                    foreach ((array)$atividades as $idx => $row) {
                        $result = $splitRow((array)$row);
                        if (!$result) {
                            continue;
                        }
                        [$linha['atividades_por_posto'][$postoKey][$idx], $selected] = $result;
                        $linha['atividades_por_posto'][$postoKey][] = $selected;
                        $linha['updated_at'] = date('Y-m-d H:i:s');
                        $setor['updated_at'] = date('Y-m-d H:i:s');
                        break 3;
                    }
                }
            }
        }
        unset($setor, $linha);

        if (!$changed) {
            return ['status' => 'error', 'message' => 'Nao foi possivel desacoplar este item.'];
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->save($data);

        return ['status' => 'success', 'id' => $newId];
    }

    public function listarCronoanalises(array $filtros = []): array {
        $data = $this->load();
        $rows = is_array($data['cronoanalises'] ?? null) ? $data['cronoanalises'] : [];
        $seenIds = [];
        foreach ($rows as $row) {
            $id = trim((string)($row['id'] ?? ''));
            if ($id !== '') {
                $seenIds[$id] = true;
            }
        }

        foreach ($this->migrarLegadoParaConsulta($data) as $legacyRow) {
            $id = trim((string)($legacyRow['id'] ?? ''));
            if ($id !== '' && isset($seenIds[$id])) {
                continue;
            }
            if ($id !== '') {
                $seenIds[$id] = true;
            }
            $rows[] = $legacyRow;
        }

        foreach ($this->migrarDrawflowParaConsulta($data) as $flowRow) {
            $id = trim((string)($flowRow['id'] ?? ''));
            if ($id !== '' && isset($seenIds[$id])) {
                continue;
            }
            if ($id !== '') {
                $seenIds[$id] = true;
            }
            $rows[] = $flowRow;
        }

        return array_values(array_filter($rows, function ($row) use ($filtros) {
            $statusFilter = trim((string)($filtros['status'] ?? ''));
            $isArchived = (int)($row['ativo'] ?? 1) !== 1 || strcasecmp((string)($row['status'] ?? ''), 'Arquivada') === 0;
            if ($statusFilter === '' && $isArchived) {
                return false;
            }
            if ($statusFilter !== '') {
                $statusText = $isArchived ? 'Arquivada' : (string)($row['status'] ?? 'Ativa');
                if (stripos($statusText, $statusFilter) === false) {
                    return false;
                }
            }

            foreach (['atividade', 'setor', 'linha', 'calibre'] as $field) {
                $filter = trim((string)($filtros[$field] ?? ''));
                if ($filter !== '' && stripos((string)($row[$field] ?? ''), $filter) === false) {
                    return false;
                }
            }

            $embalagem = trim((string)($filtros['embalagem'] ?? ''));
            if ($embalagem !== '') {
                $haystack = trim(($row['grupo_embalagem'] ?? '') . ' ' . ($row['variacao'] ?? ''));
                if (stripos($haystack, $embalagem) === false) {
                    return false;
                }
            }

            $inicio = trim((string)($filtros['periodo_inicio'] ?? ''));
            $fim = trim((string)($filtros['periodo_fim'] ?? ''));
            $dataRef = substr((string)($row['criado_em'] ?? ''), 0, 10);
            if ($inicio !== '' && $dataRef !== '' && $dataRef < $inicio) {
                return false;
            }
            if ($fim !== '' && $dataRef !== '' && $dataRef > $fim) {
                return false;
            }

            return true;
        }));
    }

    public function salvarCadastros(array $cadastros): array {
        $data = $this->load();
        $data['crono_cadastros'] = $cadastros;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->save($data);

        return ['status' => 'success'];
    }

    public function carregarCadastros(): array {
        $data = $this->load();
        return is_array($data['crono_cadastros'] ?? null) ? $data['crono_cadastros'] : [];
    }

    private function normalizarRegistroCentral(array $atividade, string $linha_id, int $post_index): array {
        $tempo = (float)($atividade['tempo_unitario_utilizado'] ?? $atividade['tempo_unitario'] ?? 0);
        $tempoTotal = (float)($atividade['tempo_total'] ?? 0);
        $qtd = (float)($atividade['quantidade_ref'] ?? 0);
        $prodH = $tempo > 0 ? 3600 / $tempo : 0;

        return [
            'id' => $atividade['id'] ?? '',
            'origem_registro' => $atividade['origem_registro'] ?? 'cronoanalise',
            'tipo_atividade' => $atividade['tipo_atividade'] ?? '',
            'atividade' => $atividade['atividade'] ?? $atividade['descricao'] ?? '',
            'descricao' => $atividade['descricao'] ?? '',
            'observacao' => $atividade['observacao'] ?? '',
            'grupo_embalagem' => $atividade['grupo_embalagem'] ?? '',
            'variacao' => $atividade['variacao'] ?? '',
            'calibre' => $atividade['calibre'] ?? '',
            'chave_tecnica' => $atividade['chave_tecnica'] ?? '',
            'setor' => $atividade['setor'] ?? '',
            'setor_id' => $atividade['setor_id'] ?? '',
            'linha' => $atividade['linha'] ?? $linha_id,
            'linha_id' => $linha_id,
            'linha_ref_id' => $atividade['linha_ref_id'] ?? '',
            'posto' => $atividade['posto'] ?? ('Posto ' . $post_index),
            'posto_id' => $atividade['posto_id'] ?? '',
            'post_index' => $post_index,
            'produto_id' => $atividade['produto_id'] ?? '',
            'item' => $atividade['item'] ?? $atividade['item_carga'] ?? $atividade['produto'] ?? '',
            'item_carga' => $atividade['item_carga'] ?? $atividade['item'] ?? $atividade['produto'] ?? '',
            'tipo_calculo' => $atividade['tipo_calculo'] ?? $atividade['tipo_operacao'] ?? '',
            'tipo_operacao' => $atividade['tipo_operacao'] ?? '',
            'unidade_base' => $atividade['unidade_ref'] ?? '',
            'unidade_carga' => $atividade['unidade_carga'] ?? $atividade['unidade_ref'] ?? '',
            'qtd_base' => $qtd,
            'qtd_ref' => (float)($atividade['qtd_ref'] ?? $qtd),
            'numero_frutos' => (float)($atividade['numero_frutos'] ?? $atividade['num_frutos'] ?? $atividade['qtd_ref'] ?? $qtd),
            'num_frutos' => (float)($atividade['numero_frutos'] ?? $atividade['num_frutos'] ?? $atividade['qtd_ref'] ?? $qtd),
            'peso_fruto_g' => (float)($atividade['peso_fruto_g'] ?? $atividade['peso_fruto'] ?? 0),
            'er' => (float)($atividade['er'] ?? $atividade['ER'] ?? 0),
            'tempo_s' => $tempoTotal,
            'tempo_cx_s' => (float)($atividade['tempo_cx_s'] ?? $atividade['tempo_caixa_s'] ?? $tempoTotal),
            'tempo_caixa_s' => (float)($atividade['tempo_cx_s'] ?? $atividade['tempo_caixa_s'] ?? $tempoTotal),
            'tempo_fruto_s' => (float)($atividade['tempo_fruto_s'] ?? $atividade['tempo_unitario_fruto_s'] ?? 0),
            'tempo_total_s' => (float)($atividade['tempo_total_s'] ?? $tempoTotal),
            'tempo_deslocamento_s' => (float)($atividade['tempo_deslocamento_s'] ?? 0),
            'tempo_operacao_s' => (float)($atividade['tempo_operacao_s'] ?? 0),
            'tempo_unitario' => $tempo,
            'prod_h' => $prodH,
            'distancia_m' => (float)($atividade['distancia_m'] ?? 0),
            'velocidade_m_s' => (float)($atividade['velocidade_m_s'] ?? $atividade['velocidade_calculada'] ?? 0),
            'velocidade_m_min' => (float)($atividade['velocidade_m_min'] ?? $atividade['velocidade_calculada'] ?? 0),
            'tipo_transporte' => $atividade['tipo_transporte'] ?? $atividade['meio_transporte'] ?? '',
            'fluxo_bpm_id' => $atividade['fluxo_bpm_id'] ?? '',
            'codigo_arvore_estrutura' => $atividade['codigo_arvore_estrutura'] ?? $atividade['codigo_arvore'] ?? '',
            'origem' => $atividade['origem'] ?? '',
            'destino' => $atividade['destino'] ?? '',
            'meio_transporte' => $atividade['meio_transporte'] ?? '',
            'operadores' => (float)($atividade['operadores'] ?? 0),
            'TR' => (float)($atividade['tr'] ?? 0),
            'TN' => (float)($atividade['tn'] ?? 0),
            'TP' => (float)($atividade['tp'] ?? 0),
            'tolerancias' => $atividade['tolerancias'] ?? [],
            'fator_ritmo' => (float)($atividade['fator_ritmo'] ?? 1),
            'fator_tolerancia' => (float)($atividade['fator_tolerancia'] ?? 1),
            'criado_em' => $atividade['criado_em'] ?? date('Y-m-d H:i:s'),
            'atualizado_em' => $atividade['atualizado_em'] ?? date('Y-m-d H:i:s'),
        ];
    }

    private function migrarLegadoParaConsulta(array $data): array {
        $rows = [];
        foreach ($data['setores'] ?? [] as $setor) {
            foreach (($setor['linhas'] ?? []) as $linha) {
                foreach (($linha['atividades_por_posto'] ?? []) as $postoKey => $atividades) {
                    $postIndex = (int)str_replace('posto_', '', (string)$postoKey);
                    foreach ((array)$atividades as $atividade) {
                        $atividade['setor'] = $atividade['setor'] ?? ($setor['nome'] ?? '');
                        $atividade['linha'] = $atividade['linha'] ?? ($linha['nome'] ?? '');
                        $rows[] = $this->normalizarRegistroCentral($atividade, $linha['id'] ?? '', $postIndex);
                    }
                }
            }
        }
        return $rows;
    }

    private function migrarDrawflowParaConsulta(array $data): array {
        $rows = [];
        foreach ($data['setores'] ?? [] as $setor) {
            foreach (($setor['linhas'] ?? []) as $linha) {
                $nodes = $linha['drawflow_data']['drawflow']['Home']['data'] ?? [];
                if (!is_array($nodes)) {
                    continue;
                }

                foreach ($nodes as $node) {
                    $nodeData = $node['data'] ?? [];
                    $nodeName = (string)($nodeData['name'] ?? $node['name'] ?? '');
                    foreach (($nodeData['atividades'] ?? []) as $atividade) {
                        if (!is_array($atividade)) {
                            continue;
                        }

                        $tempo = (float)($atividade['tc'] ?? $atividade['tempo_unitario'] ?? 0);
                        $tempoContentor = (float)($atividade['tcContentor'] ?? $atividade['tc_contentor'] ?? $atividade['ritmo'] ?? 0);
                        $tempoReferencia = $tempo > 0 ? $tempo : $tempoContentor;
                        $row = [
                            'id' => $atividade['id'] ?? '',
                            'origem_registro' => 'cronoanalise',
                            'tipo_atividade' => 'operacao',
                            'tipo_operacao' => $atividade['tipo'] ?? 'OPERACAO',
                            'atividade' => $atividade['nome'] ?? $atividade['atividade'] ?? $atividade['descricao'] ?? $nodeName,
                            'descricao' => $atividade['nome'] ?? $atividade['atividade'] ?? $atividade['descricao'] ?? $nodeName,
                            'setor' => $atividade['setor'] ?? ($setor['nome'] ?? ''),
                            'linha' => $atividade['linha'] ?? ($linha['nome'] ?? ''),
                            'posto' => $atividade['posto'] ?? $nodeName,
                            'tempo_total' => $tempoReferencia,
                            'tempo_total_s' => $tempoReferencia,
                            'tempo_s' => $tempoReferencia,
                            'tempo_unitario_utilizado' => $tempoReferencia,
                            'tempo_unitario' => $tempoReferencia,
                            'tempo_operacao_s' => $tempoReferencia,
                            'tr' => $tempoReferencia,
                            'tn' => $tempoReferencia,
                            'tp' => $tempoReferencia,
                            'TR' => $tempoReferencia,
                            'TN' => $tempoReferencia,
                            'TP' => $tempoReferencia,
                            'qtd_ref' => 1,
                            'quantidade_ref' => 1,
                            'linha_id' => $linha['id'] ?? '',
                            'linha_ref_id' => $linha['id'] ?? '',
                            'post_index' => 0,
                            'criado_em' => $atividade['criado_em'] ?? ($linha['created_at'] ?? date('Y-m-d H:i:s')),
                            'atualizado_em' => $atividade['atualizado_em'] ?? ($linha['updated_at'] ?? date('Y-m-d H:i:s')),
                        ];
                        $rows[] = $this->normalizarRegistroCentral($row, $linha['id'] ?? '', 0);
                    }
                }
            }
        }
        return $rows;
    }
}

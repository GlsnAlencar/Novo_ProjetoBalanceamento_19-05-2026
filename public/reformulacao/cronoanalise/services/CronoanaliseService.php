<?php

class CronoanaliseService {
    private CronoanaliseRepository $repository;

    public function __construct(CronoanaliseRepository $repository) {
        $this->repository = $repository;
    }

    public function salvarCronoanalise(array $input): array {
        $linha_id = trim((string)($input['linha_id'] ?? ''));
        $post_index = (int)($input['post_index'] ?? 0);
        $quantidade = $this->num($input['quantidade_ref'] ?? 0);
        $tempo_total = $this->num($input['tempo_total'] ?? 0);
        $isEmbalar = $this->isEmbalar($input);
        if ($isEmbalar) {
            $quantidade = 1;
        }

        if ($linha_id === '') {
            return ['status' => 'error', 'message' => 'Linha obrigatoria.'];
        }
        if ($tempo_total <= 0) {
            return ['status' => 'error', 'message' => 'Tempo Total deve ser maior que zero.'];
        }
        if ($quantidade < 1) {
            return ['status' => 'error', 'message' => 'Total de caixa deve ser maior ou igual a 1.'];
        }

        $tempo_base = strtoupper(trim((string)($input['tempo_base_utilizado'] ?? 'TP')));
        if (!in_array($tempo_base, ['TR', 'TN', 'TP'], true)) {
            $tempo_base = 'TP';
        }

        $tr = $this->num($input['tr'] ?? ($tempo_total / $quantidade));
        $tn = $this->num($input['tn'] ?? $tr);
        $tp = $this->num($input['tp'] ?? $tn);
        $tempo_utilizado = $this->num($input['tempo_unitario_utilizado'] ?? ($tempo_base === 'TR' ? $tr : ($tempo_base === 'TN' ? $tn : $tp)));
        $numero_frutos = $isEmbalar ? $quantidade : $this->num($input['numero_frutos'] ?? $input['num_frutos'] ?? $input['qtd_ref'] ?? $quantidade);
        $tempo_cx = $this->num($input['tempo_cx_s'] ?? $input['tempo_caixa_s'] ?? $tempo_total);
        $tempo_fruto = $this->num($input['tempo_fruto_s'] ?? 0);
        if ($tempo_fruto <= 0 && $tempo_cx > 0 && $numero_frutos > 0) {
            $tempo_fruto = $tempo_cx / $numero_frutos;
        } elseif ($tempo_fruto <= 0) {
            $tempo_fruto = $tempo_utilizado;
        }

        $atividade = [
            'id' => trim((string)($input['id'] ?? '')) ?: 'act_' . date('YmdHis') . '_' . random_int(1000, 9999),
            'tipo_atividade' => trim((string)($input['tipo_atividade'] ?? 'operacao')),
            'tipo_operacao' => trim((string)($input['tipo_operacao'] ?? 'OPERACAO')),
            'atividade' => trim((string)($input['atividade'] ?? $input['descricao'] ?? '')),
            'tipo_calculo' => trim((string)($input['tipo_calculo'] ?? $input['tipo_operacao'] ?? 'OPERACAO')),
            'grupo_embalagem' => trim((string)($input['grupo_embalagem'] ?? '')),
            'variacao' => trim((string)($input['variacao'] ?? '')),
            'calibre' => trim((string)($input['calibre'] ?? '')),
            'chave_tecnica' => trim((string)($input['chave_tecnica'] ?? $this->chaveTecnica($input))),
            'setor' => trim((string)($input['setor'] ?? '')),
            'setor_id' => trim((string)($input['setor_id'] ?? '')),
            'linha' => trim((string)($input['linha_nome'] ?? $input['linha'] ?? '')),
            'linha_ref_id' => trim((string)($input['linha_ref_id'] ?? '')),
            'posto' => trim((string)($input['posto'] ?? ('Posto ' . $post_index))),
            'posto_id' => trim((string)($input['posto_id'] ?? '')),
            'produto_id' => trim((string)($input['produto_id'] ?? '')),
            'descricao' => trim((string)($input['descricao'] ?? '')),
            'observacao' => trim((string)($input['observacao'] ?? '')),
            'unidade_ref' => trim((string)($input['unidade_ref'] ?? '')),
            'quantidade_ref' => $quantidade,
            'qtd_ref' => $quantidade,
            'numero_frutos' => $numero_frutos,
            'num_frutos' => $numero_frutos,
            'peso_fruto_g' => $isEmbalar ? 0 : $this->num($input['peso_fruto_g'] ?? $input['peso_fruto'] ?? 0),
            'er' => $this->num($input['er'] ?? $input['ER'] ?? 0),
            'tempo_total' => $tempo_total,
            'tempo_cx_s' => $tempo_cx,
            'tempo_caixa_s' => $tempo_cx,
            'tempo_fruto_s' => $tempo_fruto,
            'tempo_operacao_s' => $this->num($input['tempo_operacao_s'] ?? $tempo_utilizado),
            'tempo_deslocamento_s' => $this->num($input['tempo_deslocamento_s'] ?? 0),
            'tempo_total_s' => $this->num($input['tempo_total_s'] ?? $tempo_total),
            'tempo_base_utilizado' => $tempo_base,
            'tempo_unitario_utilizado' => $tempo_utilizado,
            'tempo_unitario' => $tempo_utilizado,
            'tr' => $tr,
            'tn' => $tn,
            'tp' => $tp,
            'fator_ritmo' => $this->num($input['fator_ritmo'] ?? 1),
            'tolerancia_total' => $this->num($input['tolerancia_total'] ?? 0),
            'fator_tolerancia' => $this->num($input['fator_tolerancia'] ?? 1),
            'tolerancias' => $this->tolerancias($input),
            'distancia_m' => $this->num($input['distancia_m'] ?? 0),
            'velocidade_m_min' => $this->num($input['velocidade_m_min'] ?? 0),
            'tipo_transporte' => trim((string)($input['tipo_transporte'] ?? '')),
            'fluxo_bpm_id' => trim((string)($input['fluxo_bpm_id'] ?? '')),
            'codigo_arvore_estrutura' => trim((string)($input['codigo_arvore_estrutura'] ?? $input['codigo_arvore'] ?? '')),
            'criado_em' => date('Y-m-d H:i:s'),
            'atualizado_em' => date('Y-m-d H:i:s'),
        ];

        if ($atividade['descricao'] === '') {
            return ['status' => 'error', 'message' => 'Descricao da atividade obrigatoria.'];
        }

        return $this->repository->salvarCronoanalise($linha_id, $post_index, $atividade);
    }

    public function salvarTransporte(array $input): array {
        $linha_id = trim((string)($input['linha_id'] ?? 'transporte'));
        $post_index = (int)($input['post_index'] ?? 0);
        $tempo_total = $this->num($input['tempo_total'] ?? 0);
        $distancia = $this->num($input['distancia_m'] ?? 0);
        $operadores = $this->num($input['operadores'] ?? 0);

        if ($tempo_total <= 0) {
            return ['status' => 'error', 'message' => 'Tempo Total deve ser maior que zero.'];
        }
        if ($distancia < 0) {
            return ['status' => 'error', 'message' => 'Distancia nao pode ser negativa.'];
        }

        $atividadeNome = trim((string)($input['atividade'] ?? 'Transporte'));
        $tipoOperacao = trim((string)($input['tipo_operacao'] ?? 'TRANSPORTE'));
        if ($tipoOperacao === '') {
            $tipoOperacao = 'TRANSPORTE';
        }
        $velocidade = $tempo_total > 0 ? $distancia / $tempo_total : 0;
        $atividade = [
            'id' => trim((string)($input['id'] ?? '')) ?: 'transp_' . date('YmdHis') . '_' . random_int(1000, 9999),
            'origem_registro' => 'transporte',
            'tipo_atividade' => 'transporte',
            'tipo_operacao' => $tipoOperacao,
            'tipo_calculo' => $tipoOperacao,
            'atividade' => $atividadeNome,
            'descricao' => $atividadeNome . ' - ' . trim((string)($input['origem'] ?? '')) . ' > ' . trim((string)($input['destino'] ?? '')),
            'origem' => trim((string)($input['origem'] ?? '')),
            'destino' => trim((string)($input['destino'] ?? '')),
            'setor' => trim((string)($input['setor'] ?? '')),
            'setor_id' => trim((string)($input['setor_id'] ?? '')),
            'linha' => trim((string)($input['linha_nome'] ?? $input['linha'] ?? '')),
            'linha_ref_id' => trim((string)($input['linha_ref_id'] ?? '')),
            'posto' => trim((string)($input['posto'] ?? '')),
            'posto_id' => trim((string)($input['posto_id'] ?? '')),
            'produto_id' => trim((string)($input['produto_id'] ?? '')),
            'item' => trim((string)($input['item'] ?? $input['item_carga'] ?? $input['produto'] ?? '')),
            'item_carga' => trim((string)($input['item_carga'] ?? $input['item'] ?? $input['produto'] ?? '')),
            'meio_transporte' => trim((string)($input['meio_transporte'] ?? '')),
            'grupo_embalagem' => trim((string)($input['grupo_embalagem'] ?? '')),
            'variacao' => trim((string)($input['variacao'] ?? '')),
            'calibre' => trim((string)($input['calibre'] ?? '')),
            'chave_tecnica' => $this->chaveTecnica($input),
            'unidade_ref' => trim((string)($input['unidade_carga'] ?? $input['unidade_ref'] ?? 'mov')),
            'unidade_carga' => trim((string)($input['unidade_carga'] ?? $input['unidade_ref'] ?? 'mov')),
            'quantidade_ref' => $this->num($input['qtd_ref'] ?? $input['quantidade_ref'] ?? 1) ?: 1,
            'qtd_ref' => $this->num($input['qtd_ref'] ?? $input['quantidade_ref'] ?? 1) ?: 1,
            'numero_frutos' => $this->num($input['numero_frutos'] ?? $input['num_frutos'] ?? 0),
            'num_frutos' => $this->num($input['numero_frutos'] ?? $input['num_frutos'] ?? 0),
            'peso_fruto_g' => $this->num($input['peso_fruto_g'] ?? $input['peso_fruto'] ?? 0),
            'er' => $this->num($input['er'] ?? $input['ER'] ?? 0),
            'tempo_total' => $tempo_total,
            'tempo_cx_s' => $this->num($input['tempo_cx_s'] ?? $input['tempo_caixa_s'] ?? 0),
            'tempo_fruto_s' => $this->num($input['tempo_fruto_s'] ?? 0),
            'tempo_total_s' => $tempo_total,
            'tempo_deslocamento_s' => $tempo_total,
            'tempo_operacao_s' => 0,
            'tempo_base_utilizado' => 'TR',
            'tempo_unitario_utilizado' => $tempo_total,
            'tempo_unitario' => $tempo_total,
            'distancia_m' => $distancia,
            'velocidade_calculada' => $velocidade,
            'velocidade_m_s' => $velocidade,
            'velocidade_m_min' => $velocidade * 60,
            'tipo_transporte' => trim((string)($input['tipo_transporte'] ?? $input['meio_transporte'] ?? '')),
            'fluxo_bpm_id' => trim((string)($input['fluxo_bpm_id'] ?? '')),
            'codigo_arvore_estrutura' => trim((string)($input['codigo_arvore_estrutura'] ?? $input['codigo_arvore'] ?? '')),
            'operadores' => $operadores,
            'observacao' => trim((string)($input['observacao'] ?? '')),
            'tr' => $tempo_total,
            'tn' => $tempo_total,
            'tp' => $tempo_total,
            'fator_ritmo' => 1,
            'tolerancia_total' => 0,
            'fator_tolerancia' => 1,
            'tolerancias' => [],
            'criado_em' => date('Y-m-d H:i:s'),
            'atualizado_em' => date('Y-m-d H:i:s'),
        ];

        if ($atividade['atividade'] === '') {
            return ['status' => 'error', 'message' => 'Atividade obrigatoria.'];
        }

        return $this->repository->salvarCronoanalise($linha_id, $post_index, $atividade);
    }

    public function listarCronoanalises(array $filtros = []): array {
        return ['status' => 'success', 'rows' => $this->repository->listarCronoanalises($filtros)];
    }

    public function excluirCronoanalise(array $input): array {
        $linha_id = trim((string)($input['linha_id'] ?? ''));
        $post_index = (int)($input['post_index'] ?? 0);
        $id = trim((string)($input['id'] ?? ''));

        if ($id === '') {
            return ['status' => 'error', 'message' => 'ID obrigatorio para exclusao.'];
        }

        return $this->repository->excluirCronoanalise($linha_id, $post_index, $id);
    }

    public function desacoplarTransporteItem(array $input): array {
        $id = trim((string)($input['id'] ?? ''));
        $itemIndex = (int)($input['item_index'] ?? $input['_cc_group_index'] ?? -1);
        if ($id === '' || $itemIndex < 0) {
            return ['status' => 'error', 'message' => 'Item do grupo invalido para desacoplar.'];
        }
        return $this->repository->desacoplarTransporteItem($id, $itemIndex);
    }

    private function tolerancias(array $input): array {
        $json = (string)($input['tolerancias_json'] ?? '[]');
        $rows = json_decode($json, true);
        return is_array($rows) ? $rows : [];
    }

    private function isEmbalar(array $input): bool {
        $text = strtolower(trim((string)($input['atividade'] ?? $input['descricao'] ?? '')));
        return $text === 'embalar';
    }

    private function num($value): float {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }
        return is_numeric($value) ? (float)$value : 0.0;
    }

    private function chaveTecnica(array $input): string {
        $grupo = $this->slugTecnico((string)($input['grupo_embalagem'] ?? ''));
        $variacao = $this->slugTecnico((string)($input['variacao'] ?? ''));
        $calibre = $this->slugTecnico((string)($input['calibre'] ?? ''));
        return trim($grupo . '_' . $variacao . '_' . $calibre, '_');
    }

    private function slugTecnico(string $value): string {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = preg_replace('/[^A-Za-z0-9]+/', ' ', (string)$value);
        $parts = array_filter(explode(' ', trim($value)));
        $slug = '';
        foreach ($parts as $index => $part) {
            $part = strtolower($part);
            $slug .= $index === 0 ? ucfirst($part) : ucfirst($part);
        }
        return $slug;
    }
}

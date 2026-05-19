<?php
/**
 * Model: DistribuicaoLote
 * 
 * Armazena a distribuição (gramas/percentual) de um lote.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Models;

class DistribuicaoLote {
    public ?int $id;
    public int $lote_id;
    public int $configuracao_embalamento_id;
    public array $itens; // array de ['gr' => int, 'descricao' => str, 'faixa_peso' => str, 'produto_operacional' => str, 'gramas' => float, 'percentual' => float]
    public float $total_gramas;
    public string $status;
    public string $created_at;
    public string $updated_at;

    public function __construct(
        ?int $id = null,
        int $lote_id = 0,
        int $configuracao_embalamento_id = 0,
        array $itens = [],
        float $total_gramas = 0.0,
        string $status = 'rascunho'
    ) {
        $this->id = $id;
        $this->lote_id = $lote_id;
        $this->configuracao_embalamento_id = $configuracao_embalamento_id;
        $this->itens = $itens;
        $this->total_gramas = $total_gramas;
        $this->status = $status;
        $this->created_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
    }

    /**
     * Adicionar um item à distribuição
     */
    public function adicionarItem(int $gr, string $descricao, string $faixa_peso, string $produto_operacional, float $gramas = 0.0): void {
        $this->itens[] = [
            'gr' => $gr,
            'descricao' => $descricao,
            'faixa_peso' => $faixa_peso,
            'produto_operacional' => $produto_operacional,
            'gramas' => $gramas,
            'percentual' => 0.0
        ];
    }

    /**
     * Recalcular percentuais com base em gramas
     */
    public function recalcularPercentuais(): void {
        $this->total_gramas = 0;
        foreach ($this->itens as &$item) {
            $this->total_gramas += $item['gramas'];
        }

        if ($this->total_gramas > 0) {
            foreach ($this->itens as &$item) {
                $item['percentual'] = ($item['gramas'] / $this->total_gramas) * 100;
            }
        }
    }

    /**
     * Validar se a distribuição soma 100%
     */
    public function validar(): bool {
        $this->recalcularPercentuais();
        $total_percentual = array_sum(array_column($this->itens, 'percentual'));
        return abs($total_percentual - 100.0) < 0.01; // tolerância de 0.01%
    }

    /**
     * Converter para array (para persistência)
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'lote_id' => $this->lote_id,
            'configuracao_embalamento_id' => $this->configuracao_embalamento_id,
            'itens' => $this->itens,
            'total_gramas' => $this->total_gramas,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Criar a partir de array
     */
    public static function fromArray(array $data): self {
        $dist = new self(
            $data['id'] ?? null,
            (int)($data['lote_id'] ?? 0),
            (int)($data['configuracao_embalamento_id'] ?? 0),
            $data['itens'] ?? [],
            (float)($data['total_gramas'] ?? 0),
            $data['status'] ?? 'rascunho'
        );
        $dist->created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
        $dist->updated_at = $data['updated_at'] ?? date('Y-m-d H:i:s');
        return $dist;
    }
}

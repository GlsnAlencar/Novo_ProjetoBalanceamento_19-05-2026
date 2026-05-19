<?php
/**
 * Model: ConfiguracaoEmbalamento
 * 
 * Define o Produto Operacional gerado por cada faixa.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Models;

class ConfiguracaoEmbalamento {
    public ?int $id;
    public string $nome;
    public int $faixa_peso_id; // referência ao ID da faixa base
    public array $mapeamentos; // array de ['gr' => int, 'descricao' => str, 'produto_operacional' => str]

    public function __construct(
        ?int $id = null,
        string $nome = '',
        int $faixa_peso_id = 0,
        array $mapeamentos = []
    ) {
        $this->id = $id;
        $this->nome = $nome;
        $this->faixa_peso_id = $faixa_peso_id;
        $this->mapeamentos = $mapeamentos;
    }

    /**
     * Adicionar um mapeamento GR -> Produto Operacional
     */
    public function adicionarMapeamento(int $gr, string $descricao, string $produto_operacional): void {
        $this->mapeamentos[] = [
            'gr' => $gr,
            'descricao' => $descricao,
            'produto_operacional' => $produto_operacional
        ];
    }

    /**
     * Converter para array (para persistência)
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'faixa_peso_id' => $this->faixa_peso_id,
            'mapeamentos' => $this->mapeamentos
        ];
    }

    /**
     * Criar a partir de array
     */
    public static function fromArray(array $data): self {
        return new self(
            $data['id'] ?? null,
            $data['nome'] ?? '',
            (int)($data['faixa_peso_id'] ?? 0),
            $data['mapeamentos'] ?? []
        );
    }
}

<?php
/**
 * Model: ConfiguracaoCalbradoraFaixa
 * 
 * Representa uma faixa de peso dentro de uma configuração da calibradora.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Models;

class ConfiguracaoCalbradoraFaixa {
    public ?int $id;
    public int $configuracao_id;
    public int $sequencia_grupo;
    public string $descricao;
    public float $peso_inicial;
    public float $peso_final;
    public string $created_at;
    public string $updated_at;

    public function __construct(
        ?int $id = null,
        int $configuracao_id = 0,
        int $sequencia_grupo = 0,
        string $descricao = '',
        float $peso_inicial = 0.0,
        float $peso_final = 0.0,
        string $created_at = '',
        string $updated_at = ''
    ) {
        $this->id = $id;
        $this->configuracao_id = $configuracao_id;
        $this->sequencia_grupo = $sequencia_grupo;
        $this->descricao = $descricao;
        $this->peso_inicial = $peso_inicial;
        $this->peso_final = $peso_final;
        $this->created_at = $created_at ?: date('Y-m-d H:i:s');
        $this->updated_at = $updated_at ?: date('Y-m-d H:i:s');
    }

    /**
     * Validar dados
     */
    public function validar(): array {
        $erros = [];
        
        if (empty(trim($this->descricao))) {
            $erros[] = 'Descrição da faixa é obrigatória.';
        }
        
        if ($this->peso_inicial < 0) {
            $erros[] = 'Peso inicial não pode ser negativo.';
        }
        
        if ($this->peso_final < 0) {
            $erros[] = 'Peso final não pode ser negativo.';
        }
        
        if ($this->peso_inicial >= $this->peso_final) {
            $erros[] = 'Peso inicial deve ser menor que o peso final.';
        }
        
        if ($this->sequencia_grupo <= 0) {
            $erros[] = 'Sequência do grupo deve ser um número positivo.';
        }
        
        return $erros;
    }

    /**
     * Verificar se sobrepõe com outra faixa
     */
    public function sobrepoeComFaixa(self $outra): bool {
        // Não sobrepõe se as faixas não se tocam
        if ($this->peso_final <= $outra->peso_inicial) {
            return false;
        }
        if ($this->peso_inicial >= $outra->peso_final) {
            return false;
        }
        return true;
    }

    /**
     * Converter para array (para persistência)
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'configuracao_id' => $this->configuracao_id,
            'sequencia_grupo' => $this->sequencia_grupo,
            'descricao' => $this->descricao,
            'peso_inicial' => $this->peso_inicial,
            'peso_final' => $this->peso_final,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Criar a partir de array
     */
    public static function fromArray(array $data): self {
        return new self(
            $data['id'] ?? null,
            (int)($data['configuracao_id'] ?? 0),
            (int)($data['sequencia_grupo'] ?? 0),
            $data['descricao'] ?? '',
            (float)($data['peso_inicial'] ?? 0),
            (float)($data['peso_final'] ?? 0),
            $data['created_at'] ?? '',
            $data['updated_at'] ?? ''
        );
    }
}

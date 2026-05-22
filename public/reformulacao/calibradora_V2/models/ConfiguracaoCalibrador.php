<?php
/**
 * Model: ConfiguracaoCalibrador
 * 
 * Representa um programa/configuração de classificação da calibradora.
 * Exemplo: Exportação, Mercado Interno, 4KG EXP, Tommy Exportação, Palmer MI
 * 
 * Cada configuração possui múltiplas faixas de peso.
 */

namespace CalbradoraModule\Models;

class ConfiguracaoCalibrador {
    public ?int $id;
    public string $nome;                    // Nome do programa (Exportação, MI, etc)
    public string $descricao;               // Descrição opcional
    public bool $ativo;                     // Status: ativo/inativo
    public string $created_at;              // Data criação
    public string $updated_at;              // Data atualização

    public function __construct(
        ?int $id = null,
        string $nome = '',
        string $descricao = '',
        bool $ativo = true,
        string $created_at = '',
        string $updated_at = ''
    ) {
        $this->id = $id;
        $this->nome = $nome;
        $this->descricao = $descricao;
        $this->ativo = $ativo;
        $this->created_at = $created_at ?: date('Y-m-d H:i:s');
        $this->updated_at = $updated_at ?: date('Y-m-d H:i:s');
    }

    /**
     * Validar dados da configuração
     */
    public function validar(): array {
        $erros = [];

        if (empty(trim($this->nome))) {
            $erros[] = 'Nome da configuração é obrigatório';
        }

        if (strlen($this->nome) > 100) {
            $erros[] = 'Nome não pode ter mais de 100 caracteres';
        }

        if (strlen($this->descricao) > 500) {
            $erros[] = 'Descrição não pode ter mais de 500 caracteres';
        }

        return $erros;
    }

    /**
     * Converter para array (para persistência)
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'nome' => trim($this->nome),
            'descricao' => trim($this->descricao),
            'ativo' => (bool)$this->ativo,
            'created_at' => $this->created_at,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Criar a partir de array
     */
    public static function fromArray(array $data): self {
        return new self(
            $data['id'] ?? null,
            $data['nome'] ?? '',
            $data['descricao'] ?? '',
            $data['ativo'] ?? true,
            $data['created_at'] ?? '',
            $data['updated_at'] ?? ''
        );
    }
}

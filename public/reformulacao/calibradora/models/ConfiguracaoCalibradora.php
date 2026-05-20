<?php
/**
 * Model: ConfiguracaoCalibradora
 * 
 * Representa uma configuração/programa de classificação da calibradora.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Models;

class ConfiguracaoCalibradora {
    public ?int $id;
    public string $nome;
    public string $descricao;
    public bool $ativo;
    public string $created_at;
    public string $updated_at;

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
     * Validar dados básicos
     */
    public function validar(): array {
        $erros = [];
        
        if (empty(trim($this->nome))) {
            $erros[] = 'Nome da configuração é obrigatório.';
        }
        
        if (strlen(trim($this->nome)) > 255) {
            $erros[] = 'Nome da configuração não pode ter mais de 255 caracteres.';
        }
        
        return $erros;
    }

    /**
     * Converter para array (para persistência)
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'ativo' => $this->ativo,
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
            $data['nome'] ?? '',
            $data['descricao'] ?? '',
            $data['ativo'] ?? true,
            $data['created_at'] ?? '',
            $data['updated_at'] ?? ''
        );
    }
}

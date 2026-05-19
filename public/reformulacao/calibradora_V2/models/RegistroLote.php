<?php
/**
 * Model: RegistroLote
 * 
 * Registro de um lote processado pela calibradora.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Models;

class RegistroLote {
    public ?int $id;
    public string $controle; // obrigatório
    public int $configuracao_embalamento_id;
    public string $programa;
    public string $partida;
    public string $produtor;
    public string $variedade;
    public string $classe;
    public string $observacoes;
    public string $status; // 'rascunho' ou 'salvo'
    public string $created_at;
    public string $updated_at;

    public function __construct(
        ?int $id = null,
        string $controle = '',
        int $configuracao_embalamento_id = 0,
        string $programa = '',
        string $partida = '',
        string $produtor = '',
        string $variedade = '',
        string $classe = '',
        string $observacoes = '',
        string $status = 'rascunho'
    ) {
        $this->id = $id;
        $this->controle = $controle;
        $this->configuracao_embalamento_id = $configuracao_embalamento_id;
        $this->programa = $programa;
        $this->partida = $partida;
        $this->produtor = $produtor;
        $this->variedade = $variedade;
        $this->classe = $classe;
        $this->observacoes = $observacoes;
        $this->status = $status;
        $this->created_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
    }

    /**
     * Validar se o lote está completo
     */
    public function validar(): bool {
        return !empty($this->controle);
    }

    /**
     * Converter para array (para persistência)
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'controle' => $this->controle,
            'configuracao_embalamento_id' => $this->configuracao_embalamento_id,
            'programa' => $this->programa,
            'partida' => $this->partida,
            'produtor' => $this->produtor,
            'variedade' => $this->variedade,
            'classe' => $this->classe,
            'observacoes' => $this->observacoes,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Criar a partir de array
     */
    public static function fromArray(array $data): self {
        $lote = new self(
            $data['id'] ?? null,
            $data['controle'] ?? '',
            (int)($data['configuracao_embalamento_id'] ?? 0),
            $data['programa'] ?? '',
            $data['partida'] ?? '',
            $data['produtor'] ?? '',
            $data['variedade'] ?? '',
            $data['classe'] ?? '',
            $data['observacoes'] ?? '',
            $data['status'] ?? 'rascunho'
        );
        $lote->created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
        $lote->updated_at = $data['updated_at'] ?? date('Y-m-d H:i:s');
        return $lote;
    }
}

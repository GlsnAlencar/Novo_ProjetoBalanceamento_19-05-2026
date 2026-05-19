<?php
/**
 * Model: FaixaPeso
 * 
 * Representa uma faixa de peso da calibradora.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Models;

class FaixaPeso {
    public ?int $id;
    public int $seq;                       // Sequência da faixa
    public string $calibre;                // Calibre (número ou texto)
    public float $peso_inicial;
    public float $peso_final;
    public string $nome_configuracao;      // referência para qual config esta faixa pertence

    public function __construct(
        ?int $id = null,
        int $seq = 0,
        string $calibre = '',
        float $peso_inicial = 0.0,
        float $peso_final = 0.0,
        string $nome_configuracao = ''
    ) {
        $this->id = $id;
        $this->seq = $seq;
        $this->calibre = $calibre;
        $this->peso_inicial = $peso_inicial;
        $this->peso_final = $peso_final;
        $this->nome_configuracao = $nome_configuracao;
    }

    /**
     * Validar se as faixas se sobrepõem dentro do mesmo nome_configuracao
     */
    public static function validarSobreposicao(array $faixas): bool {
        foreach ($faixas as $i => $faixa1) {
            foreach ($faixas as $j => $faixa2) {
                if ($i === $j) continue;

                // Verificar sobreposição
                if (!($faixa1->peso_final <= $faixa2->peso_inicial || 
                      $faixa1->peso_inicial >= $faixa2->peso_final)) {
                    return false; // Sobreposição detectada
                }
            }
        }
        return true;
    }

    /**
     * Converter para array (para persistência)
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'seq' => $this->seq,
            'calibre' => $this->calibre,
            'peso_inicial' => $this->peso_inicial,
            'peso_final' => $this->peso_final,
            'nome_configuracao' => $this->nome_configuracao
        ];
    }

    /**
     * Criar a partir de array
     */
    public static function fromArray(array $data): self {
        return new self(
            $data['id'] ?? null,
            (int)($data['seq'] ?? 0),
            $data['calibre'] ?? '',
            (float)($data['peso_inicial'] ?? 0),
            (float)($data['peso_final'] ?? 0),
            $data['nome_configuracao'] ?? ''
        );
    }
}

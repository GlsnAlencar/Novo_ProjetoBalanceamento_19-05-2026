<?php
/**
 * Repository: ConfiguracaoCalbradoraFaixaRepository
 * 
 * Gerencia a persistência de Faixas de Configurações da Calibradora.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Repositories;

use CalbradoraModule\Models\ConfiguracaoCalbradoraFaixa;

class ConfiguracaoCalbradoraFaixaRepository extends BaseRepository {
    private static int $next_id = 1;

    public function __construct(string $data_dir) {
        parent::__construct($data_dir, 'configuracoes_calibradora_faixas.json');
        $this->initializeNextId();
    }

    protected function getDefaultData(): array {
        return [
            'version' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'faixas' => []
        ];
    }

    private function initializeNextId(): void {
        $data = $this->loadData();
        $max_id = 0;
        foreach ($data['faixas'] ?? [] as $faixa) {
            if ($faixa['id'] > $max_id) {
                $max_id = $faixa['id'];
            }
        }
        self::$next_id = $max_id + 1;
    }

    /**
     * Obter todas as faixas
     */
    public function getAll(): array {
        $data = $this->loadData();
        $faixas = [];
        foreach ($data['faixas'] ?? [] as $row) {
            $faixas[] = ConfiguracaoCalbradoraFaixa::fromArray($row);
        }
        return $faixas;
    }

    /**
     * Obter faixa por ID
     */
    public function getById(int $id): ?ConfiguracaoCalbradoraFaixa {
        $data = $this->loadData();
        foreach ($data['faixas'] ?? [] as $row) {
            if ($row['id'] === $id) {
                return ConfiguracaoCalbradoraFaixa::fromArray($row);
            }
        }
        return null;
    }

    /**
     * Obter faixas por configuração, ordenadas por sequência
     */
    public function getByConfiguracaoId(int $configuracao_id): array {
        $data = $this->loadData();
        $faixas = [];
        foreach ($data['faixas'] ?? [] as $row) {
            if ($row['configuracao_id'] === $configuracao_id) {
                $faixas[] = ConfiguracaoCalbradoraFaixa::fromArray($row);
            }
        }
        // Ordenar por sequência_grupo
        usort($faixas, fn($a, $b) => $a->sequencia_grupo <=> $b->sequencia_grupo);
        return $faixas;
    }

    /**
     * Obter próxima sequência para uma configuração
     */
    public function getProxSequencia(int $configuracao_id): int {
        $data = $this->loadData();
        $max_seq = 0;
        foreach ($data['faixas'] ?? [] as $row) {
            if ($row['configuracao_id'] === $configuracao_id && $row['sequencia_grupo'] > $max_seq) {
                $max_seq = $row['sequencia_grupo'];
            }
        }
        return $max_seq + 1;
    }

    /**
     * Criar nova faixa
     */
    public function create(ConfiguracaoCalbradoraFaixa $faixa): ConfiguracaoCalbradoraFaixa {
        $data = $this->loadData();
        $faixa->id = self::$next_id++;
        $faixa->created_at = date('Y-m-d H:i:s');
        $faixa->updated_at = date('Y-m-d H:i:s');

        $data['faixas'][] = $faixa->toArray();
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->saveData($data);
        return $faixa;
    }

    /**
     * Atualizar faixa
     */
    public function update(ConfiguracaoCalbradoraFaixa $faixa): bool {
        $data = $this->loadData();
        $faixa->updated_at = date('Y-m-d H:i:s');

        foreach ($data['faixas'] as &$row) {
            if ($row['id'] === $faixa->id) {
                $row = $faixa->toArray();
                $data['updated_at'] = date('Y-m-d H:i:s');
                return $this->saveData($data);
            }
        }
        return false;
    }

    /**
     * Deletar faixa
     */
    public function delete(int $id): bool {
        $data = $this->loadData();
        $found = false;

        $data['faixas'] = array_filter($data['faixas'], function($row) use ($id, &$found) {
            if ($row['id'] === $id) {
                $found = true;
                return false;
            }
            return true;
        });

        if ($found) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            return $this->saveData($data);
        }
        return false;
    }

    /**
     * Deletar todas as faixas de uma configuração
     */
    public function deleteByConfiguracaoId(int $configuracao_id): bool {
        $data = $this->loadData();
        $found = false;

        $data['faixas'] = array_filter($data['faixas'], function($row) use ($configuracao_id, &$found) {
            if ($row['configuracao_id'] === $configuracao_id) {
                $found = true;
                return false;
            }
            return true;
        });

        if ($found) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            return $this->saveData($data);
        }
        return false;
    }

    /**
     * Verificar se há sobreposição de faixas para uma configuração
     */
    public function temSobreposicao(int $configuracao_id, ?int $excluirFaixaId = null): bool {
        $faixas = $this->getByConfiguracaoId($configuracao_id);
        
        foreach ($faixas as $i => $faixa1) {
            if ($excluirFaixaId !== null && $faixa1->id === $excluirFaixaId) {
                continue;
            }
            
            foreach ($faixas as $j => $faixa2) {
                if ($i >= $j) continue;
                
                if ($excluirFaixaId !== null && $faixa2->id === $excluirFaixaId) {
                    continue;
                }
                
                // Verificar sobreposição
                if ($faixa1->sobrepoeComFaixa($faixa2)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Atualizar sequências de uma configuração
     * Recebe um array de [id => nova_sequencia]
     */
    public function atualizarSequencias(int $configuracao_id, array $sequencias): bool {
        $data = $this->loadData();
        $modificado = false;

        foreach ($data['faixas'] as &$row) {
            if ($row['configuracao_id'] === $configuracao_id && isset($sequencias[$row['id']])) {
                $row['sequencia_grupo'] = (int)$sequencias[$row['id']];
                $modificado = true;
            }
        }

        if ($modificado) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            return $this->saveData($data);
        }
        return false;
    }
}

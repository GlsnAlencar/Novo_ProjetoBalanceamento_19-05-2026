<?php
/**
 * Repository: FaixaPesoRepository
 * 
 * Gerencia a persistência de Faixas de Peso.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Repositories;

use CalbradoraModule\Models\FaixaPeso;

class FaixaPesoRepository extends BaseRepository {
    private static int $next_id = 1;

    public function __construct(string $data_dir) {
        parent::__construct($data_dir, 'faixas_peso.json');
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
            $faixas[] = FaixaPeso::fromArray($row);
        }
        return $faixas;
    }

    /**
     * Obter faixa por ID
     */
    public function getById(int $id): ?FaixaPeso {
        $data = $this->loadData();
        foreach ($data['faixas'] ?? [] as $row) {
            if ($row['id'] === $id) {
                return FaixaPeso::fromArray($row);
            }
        }
        return null;
    }

    /**
     * Obter faixas por nome de configuração
     */
    public function getByConfiguracao(string $nome_config): array {
        $data = $this->loadData();
        $faixas = [];
        foreach ($data['faixas'] ?? [] as $row) {
            if ($row['nome_configuracao'] === $nome_config) {
                $faixas[] = FaixaPeso::fromArray($row);
            }
        }
        // Ordenar por sequência
        usort($faixas, fn($a, $b) => $a->seq <=> $b->seq);
        return $faixas;
    }

    /**
     * Obter próxima sequência para uma configuração
     */
    public function getProxSeq(string $nome_config): int {
        $data = $this->loadData();
        $max_seq = 0;
        foreach ($data['faixas'] ?? [] as $row) {
            if ($row['nome_configuracao'] === $nome_config && $row['seq'] > $max_seq) {
                $max_seq = $row['seq'];
            }
        }
        return $max_seq + 1;
    }

    /**
     * Criar nova faixa
     */
    public function create(FaixaPeso $faixa): FaixaPeso {
        $data = $this->loadData();
        $faixa->id = self::$next_id++;

        $data['faixas'][] = $faixa->toArray();
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->saveData($data);
        return $faixa;
    }

    /**
     * Atualizar faixa
     */
    public function update(FaixaPeso $faixa): bool {
        $data = $this->loadData();

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
}

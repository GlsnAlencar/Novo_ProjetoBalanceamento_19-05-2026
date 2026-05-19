<?php
/**
 * Repository: DistribuicaoLoteRepository
 * 
 * Gerencia a persistência de Distribuições de Lote.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Repositories;

use CalbradoraModule\Models\DistribuicaoLote;

class DistribuicaoLoteRepository extends BaseRepository {
    private static int $next_id = 1;

    public function __construct(string $data_dir) {
        parent::__construct($data_dir, 'distribuicoes_lote.json');
        $this->initializeNextId();
    }

    protected function getDefaultData(): array {
        return [
            'version' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'distribuicoes' => []
        ];
    }

    private function initializeNextId(): void {
        $data = $this->loadData();
        $max_id = 0;
        foreach ($data['distribuicoes'] ?? [] as $dist) {
            if ($dist['id'] > $max_id) {
                $max_id = $dist['id'];
            }
        }
        self::$next_id = $max_id + 1;
    }

    /**
     * Obter todas as distribuições
     */
    public function getAll(): array {
        $data = $this->loadData();
        $dists = [];
        foreach ($data['distribuicoes'] ?? [] as $row) {
            $dists[] = DistribuicaoLote::fromArray($row);
        }
        return $dists;
    }

    /**
     * Obter distribuição por ID
     */
    public function getById(int $id): ?DistribuicaoLote {
        $data = $this->loadData();
        foreach ($data['distribuicoes'] ?? [] as $row) {
            if ($row['id'] === $id) {
                return DistribuicaoLote::fromArray($row);
            }
        }
        return null;
    }

    /**
     * Obter distribuição por Lote ID
     */
    public function getByLoteId(int $lote_id): ?DistribuicaoLote {
        $data = $this->loadData();
        foreach ($data['distribuicoes'] ?? [] as $row) {
            if ($row['lote_id'] === $lote_id) {
                return DistribuicaoLote::fromArray($row);
            }
        }
        return null;
    }

    /**
     * Obter distribuições por Configuração
     */
    public function getByConfiguracaoId(int $config_id): array {
        $data = $this->loadData();
        $dists = [];
        foreach ($data['distribuicoes'] ?? [] as $row) {
            if ($row['configuracao_embalamento_id'] === $config_id) {
                $dists[] = DistribuicaoLote::fromArray($row);
            }
        }
        return $dists;
    }

    /**
     * Criar nova distribuição
     */
    public function create(DistribuicaoLote $dist): DistribuicaoLote {
        $data = $this->loadData();
        $dist->id = self::$next_id++;

        $data['distribuicoes'][] = $dist->toArray();
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->saveData($data);
        return $dist;
    }

    /**
     * Atualizar distribuição
     */
    public function update(DistribuicaoLote $dist): bool {
        $dist->updated_at = date('Y-m-d H:i:s');
        $data = $this->loadData();

        foreach ($data['distribuicoes'] as &$row) {
            if ($row['id'] === $dist->id) {
                $row = $dist->toArray();
                $data['updated_at'] = date('Y-m-d H:i:s');
                return $this->saveData($data);
            }
        }
        return false;
    }

    /**
     * Deletar distribuição
     */
    public function delete(int $id): bool {
        $data = $this->loadData();
        $found = false;

        $data['distribuicoes'] = array_filter($data['distribuicoes'], function($row) use ($id, &$found) {
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

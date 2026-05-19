<?php
/**
 * Repository: RegistroLoteRepository
 * 
 * Gerencia a persistência de Registros de Lote.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Repositories;

use CalbradoraModule\Models\RegistroLote;

class RegistroLoteRepository extends BaseRepository {
    private static int $next_id = 1;

    public function __construct(string $data_dir) {
        parent::__construct($data_dir, 'registros_lote.json');
        $this->initializeNextId();
    }

    protected function getDefaultData(): array {
        return [
            'version' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'lotes' => []
        ];
    }

    private function initializeNextId(): void {
        $data = $this->loadData();
        $max_id = 0;
        foreach ($data['lotes'] ?? [] as $lote) {
            if ($lote['id'] > $max_id) {
                $max_id = $lote['id'];
            }
        }
        self::$next_id = $max_id + 1;
    }

    /**
     * Obter todos os lotes
     */
    public function getAll(): array {
        $data = $this->loadData();
        $lotes = [];
        foreach ($data['lotes'] ?? [] as $row) {
            $lotes[] = RegistroLote::fromArray($row);
        }
        // Ordenar por data de criação descendente
        usort($lotes, fn($a, $b) => strcmp($b->created_at, $a->created_at));
        return $lotes;
    }

    /**
     * Obter lote por ID
     */
    public function getById(int $id): ?RegistroLote {
        $data = $this->loadData();
        foreach ($data['lotes'] ?? [] as $row) {
            if ($row['id'] === $id) {
                return RegistroLote::fromArray($row);
            }
        }
        return null;
    }

    /**
     * Obter lote por Controle
     */
    public function getByControle(string $controle): ?RegistroLote {
        $data = $this->loadData();
        foreach ($data['lotes'] ?? [] as $row) {
            if ($row['controle'] === $controle) {
                return RegistroLote::fromArray($row);
            }
        }
        return null;
    }

    /**
     * Obter lotes por status
     */
    public function getByStatus(string $status): array {
        $data = $this->loadData();
        $lotes = [];
        foreach ($data['lotes'] ?? [] as $row) {
            if ($row['status'] === $status) {
                $lotes[] = RegistroLote::fromArray($row);
            }
        }
        return $lotes;
    }

    /**
     * Criar novo lote
     */
    public function create(RegistroLote $lote): RegistroLote {
        $data = $this->loadData();
        $lote->id = self::$next_id++;

        $data['lotes'][] = $lote->toArray();
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->saveData($data);
        return $lote;
    }

    /**
     * Atualizar lote
     */
    public function update(RegistroLote $lote): bool {
        $lote->updated_at = date('Y-m-d H:i:s');
        $data = $this->loadData();

        foreach ($data['lotes'] as &$row) {
            if ($row['id'] === $lote->id) {
                $row = $lote->toArray();
                $data['updated_at'] = date('Y-m-d H:i:s');
                return $this->saveData($data);
            }
        }
        return false;
    }

    /**
     * Deletar lote
     */
    public function delete(int $id): bool {
        $data = $this->loadData();
        $found = false;

        $data['lotes'] = array_filter($data['lotes'], function($row) use ($id, &$found) {
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

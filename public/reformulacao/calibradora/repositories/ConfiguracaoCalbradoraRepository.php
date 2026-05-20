<?php
/**
 * Repository: ConfiguracaoCalbradoraRepository
 * 
 * Gerencia a persistência de Configurações de Calibradora.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Repositories;

use CalbradoraModule\Models\ConfiguracaoCalibradora;

class ConfiguracaoCalbradoraRepository extends BaseRepository {
    private static int $next_id = 1;

    public function __construct(string $data_dir) {
        parent::__construct($data_dir, 'configuracoes_calibradora.json');
        $this->initializeNextId();
    }

    protected function getDefaultData(): array {
        return [
            'version' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'configuracoes' => []
        ];
    }

    private function initializeNextId(): void {
        $data = $this->loadData();
        $max_id = 0;
        foreach ($data['configuracoes'] ?? [] as $config) {
            if ($config['id'] > $max_id) {
                $max_id = $config['id'];
            }
        }
        self::$next_id = $max_id + 1;
    }

    /**
     * Obter todas as configurações
     */
    public function getAll(): array {
        $data = $this->loadData();
        $configs = [];
        foreach ($data['configuracoes'] ?? [] as $row) {
            $configs[] = ConfiguracaoCalibradora::fromArray($row);
        }
        // Ordenar por nome
        usort($configs, fn($a, $b) => strcmp($a->nome, $b->nome));
        return $configs;
    }

    /**
     * Obter apenas as configurações ativas
     */
    public function getAllAtivos(): array {
        $data = $this->loadData();
        $configs = [];
        foreach ($data['configuracoes'] ?? [] as $row) {
            if ($row['ativo'] ?? false) {
                $configs[] = ConfiguracaoCalibradora::fromArray($row);
            }
        }
        usort($configs, fn($a, $b) => strcmp($a->nome, $b->nome));
        return $configs;
    }

    /**
     * Obter configuração por ID
     */
    public function getById(int $id): ?ConfiguracaoCalibradora {
        $data = $this->loadData();
        foreach ($data['configuracoes'] ?? [] as $row) {
            if ($row['id'] === $id) {
                return ConfiguracaoCalibradora::fromArray($row);
            }
        }
        return null;
    }

    /**
     * Obter configuração por nome
     */
    public function getByNome(string $nome): ?ConfiguracaoCalibradora {
        $data = $this->loadData();
        foreach ($data['configuracoes'] ?? [] as $row) {
            if (strcasecmp($row['nome'] ?? '', $nome) === 0) {
                return ConfiguracaoCalibradora::fromArray($row);
            }
        }
        return null;
    }

    /**
     * Criar nova configuração
     */
    public function create(ConfiguracaoCalibradora $config): ConfiguracaoCalibradora {
        $data = $this->loadData();
        $config->id = self::$next_id++;
        $config->created_at = date('Y-m-d H:i:s');
        $config->updated_at = date('Y-m-d H:i:s');

        $data['configuracoes'][] = $config->toArray();
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->saveData($data);
        return $config;
    }

    /**
     * Atualizar configuração
     */
    public function update(ConfiguracaoCalibradora $config): bool {
        $data = $this->loadData();
        $config->updated_at = date('Y-m-d H:i:s');

        foreach ($data['configuracoes'] as &$row) {
            if ($row['id'] === $config->id) {
                $row = $config->toArray();
                $data['updated_at'] = date('Y-m-d H:i:s');
                return $this->saveData($data);
            }
        }
        return false;
    }

    /**
     * Deletar configuração
     */
    public function delete(int $id): bool {
        $data = $this->loadData();
        $found = false;

        $data['configuracoes'] = array_filter($data['configuracoes'], function($row) use ($id, &$found) {
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
     * Verificar se uma configuração com este nome já existe
     */
    public function existeNome(string $nome, ?int $excluirId = null): bool {
        $data = $this->loadData();
        foreach ($data['configuracoes'] ?? [] as $row) {
            if (strcasecmp($row['nome'] ?? '', $nome) === 0) {
                if ($excluirId !== null && $row['id'] === $excluirId) {
                    continue;
                }
                return true;
            }
        }
        return false;
    }
}

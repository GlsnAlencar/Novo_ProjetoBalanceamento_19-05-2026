<?php
/**
 * Repository: ConfiguracaoCalibradorRepository
 * 
 * Gerencia a persistência de Configurações da Calibradora.
 * Armazena programas/configurações de classificação.
 */

namespace CalbradoraModule\Repositories;

use CalbradoraModule\Models\ConfiguracaoCalibrador;

class ConfiguracaoCalibradorRepository extends BaseRepository {
    private static int $next_id = 1;

    public function __construct(string $data_dir) {
        parent::__construct($data_dir, 'configuracoes_calibrador.json');
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
            $configs[] = ConfiguracaoCalibrador::fromArray($row);
        }
        usort($configs, fn($a, $b) => $a->nome <=> $b->nome);
        return $configs;
    }

    /**
     * Obter todas as configurações ativas
     */
    public function getAllAtivas(): array {
        $data = $this->loadData();
        $configs = [];
        foreach ($data['configuracoes'] ?? [] as $row) {
            if ($row['ativo'] ?? false) {
                $configs[] = ConfiguracaoCalibrador::fromArray($row);
            }
        }
        usort($configs, fn($a, $b) => $a->nome <=> $b->nome);
        return $configs;
    }

    /**
     * Obter configuração por ID
     */
    public function getById(int $id): ?ConfiguracaoCalibrador {
        $data = $this->loadData();
        foreach ($data['configuracoes'] ?? [] as $row) {
            if ($row['id'] === $id) {
                return ConfiguracaoCalibrador::fromArray($row);
            }
        }
        return null;
    }

    /**
     * Obter configuração por nome
     */
    public function getByNome(string $nome): ?ConfiguracaoCalibrador {
        $data = $this->loadData();
        foreach ($data['configuracoes'] ?? [] as $row) {
            if (strtolower(trim($row['nome'])) === strtolower(trim($nome))) {
                return ConfiguracaoCalibrador::fromArray($row);
            }
        }
        return null;
    }

    /**
     * Criar nova configuração
     */
    public function create(ConfiguracaoCalibrador $config): ConfiguracaoCalibrador {
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
     * Atualizar configuração existente
     */
    public function update(ConfiguracaoCalibrador $config): bool {
        $data = $this->loadData();

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

        foreach ($data['configuracoes'] as $index => $row) {
            if ($row['id'] === $id) {
                unset($data['configuracoes'][$index]);
                $data['configuracoes'] = array_values($data['configuracoes']);
                $data['updated_at'] = date('Y-m-d H:i:s');
                return $this->saveData($data);
            }
        }

        return false;
    }

    /**
     * Verificar se nome já existe
     */
    public function nomeJaExiste(string $nome, ?int $exclude_id = null): bool {
        $data = $this->loadData();
        foreach ($data['configuracoes'] ?? [] as $row) {
            if (strtolower(trim($row['nome'])) === strtolower(trim($nome))) {
                if ($exclude_id === null || $row['id'] !== $exclude_id) {
                    return true;
                }
            }
        }
        return false;
    }
}

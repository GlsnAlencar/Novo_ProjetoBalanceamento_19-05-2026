<?php
/**
 * Repository: ConfiguracaoEmbalamentoRepository
 * 
 * Gerencia a persistência de Configurações de Embalamento.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Repositories;

use CalbradoraModule\Models\ConfiguracaoEmbalamento;

class ConfiguracaoEmbalamentoRepository extends BaseRepository {
    private static int $next_id = 1;

    public function __construct(string $data_dir) {
        parent::__construct($data_dir, 'configuracoes_embalamento.json');
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
            $configs[] = ConfiguracaoEmbalamento::fromArray($row);
        }
        return $configs;
    }

    /**
     * Obter configuração por ID
     */
    public function getById(int $id): ?ConfiguracaoEmbalamento {
        $data = $this->loadData();
        foreach ($data['configuracoes'] ?? [] as $row) {
            if ($row['id'] === $id) {
                return ConfiguracaoEmbalamento::fromArray($row);
            }
        }
        return null;
    }

    /**
     * Obter configuração por nome
     */
    public function getByNome(string $nome): ?ConfiguracaoEmbalamento {
        $data = $this->loadData();
        foreach ($data['configuracoes'] ?? [] as $row) {
            if ($row['nome'] === $nome) {
                return ConfiguracaoEmbalamento::fromArray($row);
            }
        }
        return null;
    }

    /**
     * Criar nova configuração
     */
    public function create(ConfiguracaoEmbalamento $config): ConfiguracaoEmbalamento {
        $data = $this->loadData();
        $config->id = self::$next_id++;

        $data['configuracoes'][] = $config->toArray();
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->saveData($data);
        return $config;
    }

    /**
     * Atualizar configuração
     */
    public function update(ConfiguracaoEmbalamento $config): bool {
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
}

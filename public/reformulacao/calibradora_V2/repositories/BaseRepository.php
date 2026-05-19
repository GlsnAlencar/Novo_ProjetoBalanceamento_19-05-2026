<?php
/**
 * Repository Base
 * 
 * Classe base para acesso seguro aos dados em JSON.
 * Isolado do módulo legado.
 */

namespace CalbradoraModule\Repositories;

abstract class BaseRepository {
    protected string $data_dir;
    protected string $file_name;

    public function __construct(string $data_dir, string $file_name) {
        $this->data_dir = $data_dir;
        $this->file_name = $file_name;
        
        // Garantir que o diretório existe
        if (!is_dir($data_dir)) {
            mkdir($data_dir, 0755, true);
        }
    }

    /**
     * Obter caminho completo do arquivo de dados
     */
    protected function getDataPath(): string {
        return $this->data_dir . DIRECTORY_SEPARATOR . $this->file_name;
    }

    /**
     * Carregar dados do arquivo JSON de forma segura
     */
    protected function loadData(): array {
        $path = $this->getDataPath();
        
        if (!file_exists($path)) {
            return $this->getDefaultData();
        }

        // Lock para leitura
        $lock_file = $path . '.lock';
        $lock = fopen($lock_file, 'c');
        if (!$lock) {
            return $this->getDefaultData();
        }
        flock($lock, LOCK_SH);
        
        $content = file_get_contents($path);
        flock($lock, LOCK_UN);
        fclose($lock);

        if (!$content) {
            return $this->getDefaultData();
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $this->getDefaultData();
        }

        return $data;
    }

    /**
     * Salvar dados no arquivo JSON de forma segura
     */
    protected function saveData(array $data): bool {
        $path = $this->getDataPath();

        // Lock para escrita
        $lock_file = $path . '.lock';
        $lock = fopen($lock_file, 'c');
        if (!$lock) {
            return false;
        }
        flock($lock, LOCK_EX);

        $success = file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;

        flock($lock, LOCK_UN);
        fclose($lock);

        return $success;
    }

    /**
     * Deve ser implementado pelas subclasses
     */
    protected abstract function getDefaultData(): array;
}

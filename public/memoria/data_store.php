<?php
/**
 * Data Store - Sistema de Balanceamento
 * Gerencia carregamento/salvamento de dados JSON
 */

function data_directory_path() {
    return realpath(__DIR__ . '/../../data/memoria');
}

function ensure_data_directory() {
    $path = __DIR__ . '/../../data/memoria';
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
    return $path;
}

function data_file_path($name) {
    $dir = ensure_data_directory();
    return $dir . '/' . basename($name) . '.json';
}

function memoria_backup_path($path) {
    $backup_dir = dirname($path) . '/_backups';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }

    $base = pathinfo($path, PATHINFO_FILENAME);
    return $backup_dir . '/' . $base . '_' . date('Ymd_His') . '_' . random_int(1000, 9999) . '.json';
}

function memoria_write_json_file($path, $data) {
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        throw new RuntimeException('Falha ao converter dados da Memoria para JSON: ' . json_last_error_msg());
    }

    json_decode($encoded, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('JSON gerado para Memoria invalido: ' . json_last_error_msg());
    }

    $lock_path = $path . '.lock';
    $lock = fopen($lock_path, 'c');
    if ($lock === false) {
        throw new RuntimeException('Nao foi possivel criar trava de escrita da Memoria.');
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Nao foi possivel bloquear a escrita da Memoria.');
        }

        if (file_exists($path) && filesize($path) > 0) {
            copy($path, memoria_backup_path($path));
        }

        $tmp = tempnam(dirname($path), '.tmp_json_');
        if ($tmp === false) {
            throw new RuntimeException('Nao foi possivel criar arquivo temporario da Memoria.');
        }

        if (file_put_contents($tmp, $encoded, LOCK_EX) === false) {
            @unlink($tmp);
            throw new RuntimeException('Falha ao gravar arquivo temporario da Memoria.');
        }

        $check = json_decode((string)file_get_contents($tmp), true);
        if (!is_array($check) || json_last_error() !== JSON_ERROR_NONE) {
            @unlink($tmp);
            throw new RuntimeException('Arquivo temporario da Memoria gerou JSON invalido.');
        }

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Falha ao substituir arquivo oficial da Memoria.');
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function load_json_data($name) {
    $path = data_file_path($name);
    if (!file_exists($path)) {
        memoria_write_json_file($path, []);
        return [];
    }
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    if (is_array($data)) {
        return $data;
    }

    if (filesize($path) > 0) {
        copy($path, memoria_backup_path($path) . '.corrupt');
    }

    memoria_write_json_file($path, []);
    return [];
}

function save_json_data($name, $data) {
    $path = data_file_path($name);
    memoria_write_json_file($path, $data);
}

/**
 * ========== FUNÇÕES ESTENDIDAS PARA FLUXOS E ESTRUTURA ==========
 */

/**
 * Gera um ID único baseado em timestamp
 */
function generate_id($prefix = '') {
    return $prefix . '_' . time() . '_' . rand(1000, 9999);
}

/**
 * Encontra um setor pelo ID na lista de setores
 */
function find_setor_by_id($setores, $setor_id) {
    foreach ($setores as $idx => $setor) {
        if ($setor['id'] === $setor_id) {
            return $setor;
        }
    }
    return null;
}

/**
 * Encontra uma linha pelo ID na lista de linhas
 */
function find_linha_by_id($linhas, $linha_id) {
    foreach ($linhas as $idx => $linha) {
        if ($linha['id'] === $linha_id) {
            return $linha;
        }
    }
    return null;
}

/**
 * Encontra um posto em uma linha pelo ID
 */
function find_posto_in_linha($linha, $posto_id) {
    if (!isset($linha['postos'])) {
        return null;
    }
    
    foreach ($linha['postos'] as $idx => $posto) {
        if (isset($posto['id']) && $posto['id'] === $posto_id) {
            return $posto;
        }
    }
    return null;
}

/**
 * Retorna todas as linhas de um setor
 */
function get_linhas_by_setor($linhas, $setor_id) {
    $resultado = [];
    foreach ($linhas as $idx => $linha) {
        if (isset($linha['setor_id']) && $linha['setor_id'] === $setor_id) {
            $resultado[] = $linha;
        }
    }
    return $resultado;
}

/**
 * Cria uma estrutura padrão para um novo setor
 */
function create_default_setor($nome, $descricao = '') {
    return [
        'id' => generate_id('setor'),
        'nome' => $nome,
        'descricao' => $descricao,
        'data_criacao' => date('Y-m-d H:i:s'),
        'linhas_ids' => []
    ];
}

/**
 * Cria uma estrutura padrão para uma nova linha
 */
function create_default_linha($nome, $setor_id) {
    return [
        'id' => generate_id('linha'),
        'nome' => $nome,
        'setor_id' => $setor_id,
        'postos' => [],
        'conexoes' => [],
        'unidade_basica' => null,
        'drawflow_data' => null // Novo campo para armazenar o JSON do Drawflow
    ];
}

/**
 * Gera um ID único para uso no frontend (Drawflow nodes, etc.)
 */
function generate_unique_id($prefix = 'df_') {
    return $prefix . uniqid();
}

/**
 * Cria uma estrutura padrão para um novo posto
 */
function create_default_posto($nome) {
    return [
        'id' => generate_id('posto'),
        'nome' => $nome,
        'tipo' => 'node',  // node, flow, parallel, merge
        'paralelo' => false,
        'postos_origem' => [],
        'postos_destino' => [],
        'configs' => [],
        'detalhes' => [
            'unidade_tipica' => '',
            'quantidade_item' => '',
            'tempo_total' => '',
            'tempo_por_item' => '',
            'fator_correlacao' => '',
            'observacao' => '',
            'categoria_atividade' => '',
            'tipo_item' => ''
        ],
        'atividades' => [],
        'recursos' => [
            'num_pessoas' => 1
        ],
        'posicao' => [
            'x' => 100,
            'y' => 100
        ]
    ];
}

/**
 * Adiciona uma conexão entre dois postos
 */
function add_conexao($linha, $origem_id, $destino_id, $tipo = 'serie') {
    if (!isset($linha['conexoes'])) {
        $linha['conexoes'] = [];
    }
    
    $conexao = [
        'id' => generate_id('conexao'),
        'origem' => $origem_id,
        'destino' => $destino_id,
        'tipo' => $tipo  // serie, paralelo
    ];
    
    $linha['conexoes'][] = $conexao;
    return $conexao;
}

/**
 * Remove uma conexão entre postos
 */
function remove_conexao($linha, $conexao_id) {
    if (!isset($linha['conexoes'])) {
        return false;
    }
    
    foreach ($linha['conexoes'] as $idx => $conexao) {
        if ($conexao['id'] === $conexao_id) {
            unset($linha['conexoes'][$idx]);
            $linha['conexoes'] = array_values($linha['conexoes']);
            return true;
        }
    }
    return false;
}

/**
 * Obtém postos que conectam para um determinado posto
 */
function get_postos_origem($linha, $posto_id) {
    $resultado = [];
    if (!isset($linha['conexoes'])) {
        return $resultado;
    }
    
    foreach ($linha['conexoes'] as $conexao) {
        if ($conexao['destino'] === $posto_id && isset($linha['postos'])) {
            foreach ($linha['postos'] as $idx => $posto) {
                if ($posto['id'] === $conexao['origem']) {
                    $resultado[] = $posto;
                }
            }
        }
    }
    return $resultado;
}

/**
 * Obtém postos para os quais um determinado posto conecta
 */
function get_postos_destino($linha, $posto_id) {
    $resultado = [];
    if (!isset($linha['conexoes'])) {
        return $resultado;
    }
    
    foreach ($linha['conexoes'] as $conexao) {
        if ($conexao['origem'] === $posto_id && isset($linha['postos'])) {
            foreach ($linha['postos'] as $idx => $posto) {
                if ($posto['id'] === $conexao['destino']) {
                    $resultado[] = $posto;
                }
            }
        }
    }
    return $resultado;
}
?>

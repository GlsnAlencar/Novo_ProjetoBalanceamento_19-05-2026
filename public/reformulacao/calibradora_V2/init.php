<?php
/**
 * Arquivo de Inicialização e Exemplo de Uso do Módulo Calibradora
 * 
 * Este arquivo demonstra como usar os serviços do módulo programaticamente.
 * Não é necessário para o funcionamento normal das telas.
 */

// ============================================================
// EXEMPLO 1: Usar o Service diretamente
// ============================================================

require_once __DIR__ . '/services/CalbradoraService.php';
require_once __DIR__ . '/models/FaixaPeso.php';
require_once __DIR__ . '/repositories/FaixaPesoRepository.php';

use CalbradoraModule\Services\CalbradoraService;
use CalbradoraModule\Models\FaixaPeso;

// Inicializar service
$data_dir = __DIR__ . '/../../../data/reformulacao/calibradora';
$service = new CalbradoraService($data_dir);

// Exemplo: Criar faixa
/*
$faixa = $service->criarFaixa(
    'REFUGO',
    50.0,
    150.0,
    'Exportação 4KG Palmer'
);

if ($faixa) {
    echo "Faixa criada: " . $faixa->descricao . " (" . $faixa->peso_inicial . "-" . $faixa->peso_final . "g)";
} else {
    echo "Erro ao criar faixa (pode haver sobreposição)";
}
*/

// ============================================================
// EXEMPLO 2: Usar o Controller para requisições
// ============================================================

require_once __DIR__ . '/controllers/CalbradoraController.php';

use CalbradoraModule\Controllers\CalbradoraController;

// Inicializar controller
$controller = new CalbradoraController($service);

// Exemplo: Processar ação de criar faixa
/*
$result = $controller->processarRequisicao('criar_faixa', [
    'descricao' => 'Teste',
    'peso_inicial' => 100,
    'peso_final' => 200,
    'nome_configuracao' => 'Teste'
]);

if ($result['sucesso']) {
    echo "Sucesso: " . $result['mensagem'];
    echo json_encode($result['dados']);
} else {
    echo "Erro: " . $result['mensagem'];
}
*/

// ============================================================
// INICIALIZAÇÃO DO MÓDULO
// ============================================================

/**
 * Se este arquivo foi executado diretamente (via CLI ou include),
 * ele pode ser usado para tarefas de inicialização.
 */

if (php_sapi_name() === 'cli') {
    echo "=== Módulo Calibradora - Inicialização ===\n";
    echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

    // Verificar se os diretórios de dados existem
    $data_dir = __DIR__ . '/../../../data/reformulacao/calibradora';
    if (!is_dir($data_dir)) {
        mkdir($data_dir, 0755, true);
        echo "[OK] Diretório de dados criado: $data_dir\n";
    } else {
        echo "[OK] Diretório de dados existe\n";
    }

    // Listar arquivos de dados
    echo "\nArquivos de dados:\n";
    $files = [
        'faixas_peso.json',
        'configuracoes_embalamento.json',
        'registros_lote.json',
        'distribuicoes_lote.json'
    ];

    foreach ($files as $file) {
        $path = $data_dir . '/' . $file;
        if (file_exists($path)) {
            $size = filesize($path);
            echo "  ✓ $file (" . number_format($size) . " bytes)\n";
        } else {
            echo "  - $file (não criado ainda)\n";
        }
    }

    echo "\n=== Módulo pronto para uso ===\n";
    echo "Acesse: http://seu-dominio/reformulacao/calibradora/\n";
}

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================

/**
 * Exportar dados para análise
 */
function calibradora_export_data($format = 'json') {
    $data_dir = __DIR__ . '/../../../data/reformulacao/calibradora';

    $data = [
        'exported_at' => date('Y-m-d H:i:s'),
        'faixas' => json_decode(file_get_contents("$data_dir/faixas_peso.json"), true),
        'configuracoes' => json_decode(file_get_contents("$data_dir/configuracoes_embalamento.json"), true),
        'lotes' => json_decode(file_get_contents("$data_dir/registros_lote.json"), true),
        'distribuicoes' => json_decode(file_get_contents("$data_dir/distribuicoes_lote.json"), true),
    ];

    if ($format === 'json') {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else if ($format === 'csv') {
        // Exportar em CSV simplificado
        $csv = "tipo,id,dados\n";
        foreach ($data['faixas']['faixas'] ?? [] as $f) {
            $csv .= "faixa," . $f['id'] . "," . json_encode($f) . "\n";
        }
        return $csv;
    }

    return $data;
}

/**
 * Limpar todos os dados (USE COM CUIDADO!)
 */
function calibradora_reset_all() {
    $data_dir = __DIR__ . '/../../../data/reformulacao/calibradora';

    $files = [
        'faixas_peso.json',
        'configuracoes_embalamento.json',
        'registros_lote.json',
        'distribuicoes_lote.json'
    ];

    foreach ($files as $file) {
        $path = $data_dir . '/' . $file;
        if (file_exists($path)) {
            unlink($path);
        }
        if (file_exists($path . '.lock')) {
            unlink($path . '.lock');
        }
    }

    return true;
}

/**
 * Gerar dados de demonstração
 */
function calibradora_generate_demo_data() {
    $service = new CalbradoraService(__DIR__ . '/../../../data/reformulacao/calibradora');

    // Criar faixas
    $service->criarFaixa('REFUGO', 50, 150, 'Exportação 4KG Palmer');
    $service->criarFaixa('14', 150, 270, 'Exportação 4KG Palmer');
    $service->criarFaixa('12', 270, 385, 'Exportação 4KG Palmer');
    $service->criarFaixa('10', 385, 445, 'Exportação 4KG Palmer');

    echo "Dados de demonstração gerados com sucesso!\n";
}
?>

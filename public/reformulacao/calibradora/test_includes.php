<?php
/**
 * TESTE: Validação de Includes
 * 
 * Verifica se todos os arquivos necessários existem e podem ser carregados.
 */

echo "<h1>Validação de Includes do Módulo Calibradora</h1>";
echo "<pre>";

$files_to_check = [
    'safe_storage (ISOLADO)' => __DIR__ . '/safe_storage.php',
    'bootstrap' => __DIR__ . '/bootstrap.php',
    'FaixaPeso' => __DIR__ . '/models/FaixaPeso.php',
    'ConfiguracaoEmbalamento' => __DIR__ . '/models/ConfiguracaoEmbalamento.php',
    'RegistroLote' => __DIR__ . '/models/RegistroLote.php',
    'DistribuicaoLote' => __DIR__ . '/models/DistribuicaoLote.php',
    'BaseRepository' => __DIR__ . '/repositories/BaseRepository.php',
    'FaixaPesoRepository' => __DIR__ . '/repositories/FaixaPesoRepository.php',
    'ConfiguracaoEmbalamentoRepository' => __DIR__ . '/repositories/ConfiguracaoEmbalamentoRepository.php',
    'RegistroLoteRepository' => __DIR__ . '/repositories/RegistroLoteRepository.php',
    'DistribuicaoLoteRepository' => __DIR__ . '/repositories/DistribuicaoLoteRepository.php',
    'CalbradoraService' => __DIR__ . '/services/CalbradoraService.php',
    'CalbradoraController' => __DIR__ . '/controllers/CalbradoraController.php',
];

$all_ok = true;

foreach ($files_to_check as $name => $path) {
    if (file_exists($path)) {
        echo "✓ $name: OK\n";
    } else {
        echo "✗ $name: ARQUIVO NÃO ENCONTRADO\n";
        echo "  Caminho: $path\n";
        $all_ok = false;
    }
}

echo "\n";

// Tentar carregar bootstrap
if ($all_ok) {
    echo "Tentando carregar bootstrap.php...\n";
    try {
        require_once __DIR__ . '/bootstrap.php';
        echo "✓ Bootstrap carregado com sucesso!\n";
    } catch (Exception $e) {
        echo "✗ Erro ao carregar bootstrap: " . $e->getMessage() . "\n";
        $all_ok = false;
    }
}

echo "\n";
echo ($all_ok ? "✓ TODOS OS INCLUDES ESTÃO CORRETOS!" : "✗ EXISTEM PROBLEMAS");
echo "\n</pre>";
?>

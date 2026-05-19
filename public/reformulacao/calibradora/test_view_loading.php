<?php
/**
 * TEST: Simular carregamento de uma view (etapa1_faixas.php)
 * 
 * Este teste verifica se todas as classes estão disponíveis
 * após o bootstrap ser carregado.
 */

echo "<h1>TESTE: Carregamento Simplificado com Bootstrap</h1>";
echo "<pre>";

try {
    // Simular exatamente o que a view faria agora
    echo "1. Carregando bootstrap.php...\n";
    require_once __DIR__ . '/bootstrap.php';
    echo "   ✓ Bootstrap carregado com sucesso\n\n";

    // Verificar $service e $controller
    echo "2. Verificando variáveis globais...\n";
    if (!isset($service)) {
        throw new Exception("$service não foi inicializado pelo bootstrap!");
    }
    echo "   ✓ \$service disponível\n";

    if (!isset($controller)) {
        throw new Exception("$controller não foi inicializado pelo bootstrap!");
    }
    echo "   ✓ \$controller disponível\n";
    echo "   ✓ \$data_dir = " . $data_dir . "\n\n";

    // Testar instanciação de cada repository
    echo "3. Testando acesso às repositories via service...\n";
    
    // O service deveria ter instanciado todas as repositories
    if ($service === null) {
        throw new Exception("CalbradoraService não foi criado!");
    }
    echo "   ✓ CalbradoraService instanciado\n";

    // Testar chamada ao controller
    echo "4. Testando acesso ao controller...\n";
    if ($controller === null) {
        throw new Exception("CalbradoraController não foi criado!");
    }
    echo "   ✓ CalbradoraController instanciado\n";

    // Testar processamento de requisição simples
    echo "5. Testando requisição ao controller...\n";
    $result = $controller->processarRequisicao('obter_faixas');
    if (!is_array($result)) {
        throw new Exception("Controller não retornou array!");
    }
    echo "   ✓ Controller responde às requisições\n";
    echo "   ✓ Resposta: " . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

    // Verificar classes carregadas
    echo "6. Verificando classes carregadas...\n";
    
    $classes_required = [
        'CalbradoraModule\Models\FaixaPeso',
        'CalbradoraModule\Models\ConfiguracaoEmbalamento',
        'CalbradoraModule\Models\RegistroLote',
        'CalbradoraModule\Models\DistribuicaoLote',
        'CalbradoraModule\Repositories\BaseRepository',
        'CalbradoraModule\Repositories\FaixaPesoRepository',
        'CalbradoraModule\Repositories\ConfiguracaoEmbalamentoRepository',
        'CalbradoraModule\Repositories\RegistroLoteRepository',
        'CalbradoraModule\Repositories\DistribuicaoLoteRepository',
        'CalbradoraModule\Services\CalbradoraService',
        'CalbradoraModule\Controllers\CalbradoraController',
    ];

    $all_ok = true;
    foreach ($classes_required as $class) {
        if (!class_exists($class)) {
            echo "   ✗ Classe NÃO ENCONTRADA: $class\n";
            $all_ok = false;
        } else {
            echo "   ✓ $class\n";
        }
    }

    if (!$all_ok) {
        throw new Exception("Algumas classes não foram carregadas!");
    }

    echo "\n";
    echo "==========================================\n";
    echo "✓ TESTE COMPLETO - SUCESSO!\n";
    echo "==========================================\n";
    echo "\nTODAS as classes estão disponíveis.\n";
    echo "As views podem ser carregadas sem erro de 'Class not found'.\n";
    echo "\nStatus: PRONTO PARA PRODUÇÃO\n";

} catch (Exception $e) {
    echo "\n";
    echo "==========================================\n";
    echo "✗ ERRO DETECTADO:\n";
    echo "==========================================\n";
    echo $e->getMessage() . "\n";
    echo "\nArquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
}

echo "\n</pre>";
?>

<?php
/**
 * Bootstrap do Módulo Calibradora
 * 
 * Arquivo central que inicializa todas as inclusões necessárias.
 * Use este arquivo em todas as views do módulo.
 */

// Inclusão de arquivo de segurança (ISOLADO PARA CALIBRADORA)
require_once __DIR__ . '/safe_storage.php';

// Inclusão de TODOS os models (ordem importa)
require_once __DIR__ . '/models/FaixaPeso.php';
require_once __DIR__ . '/models/ConfiguracaoEmbalamento.php';
require_once __DIR__ . '/models/RegistroLote.php';
require_once __DIR__ . '/models/DistribuicaoLote.php';
require_once __DIR__ . '/models/ConfiguracaoCalibradora.php';
require_once __DIR__ . '/models/ConfiguracaoCalbradoraFaixa.php';

// Inclusão de repositories
require_once __DIR__ . '/repositories/BaseRepository.php';
require_once __DIR__ . '/repositories/FaixaPesoRepository.php';
require_once __DIR__ . '/repositories/ConfiguracaoEmbalamentoRepository.php';
require_once __DIR__ . '/repositories/RegistroLoteRepository.php';
require_once __DIR__ . '/repositories/DistribuicaoLoteRepository.php';
require_once __DIR__ . '/repositories/ConfiguracaoCalbradoraRepository.php';
require_once __DIR__ . '/repositories/ConfiguracaoCalbradoraFaixaRepository.php';

// Inclusão de services
require_once __DIR__ . '/services/CalbradoraService.php';

// Inclusão de controllers
require_once __DIR__ . '/controllers/CalbradoraController.php';

// Use statements
use CalbradoraModule\Services\CalbradoraService;
use CalbradoraModule\Controllers\CalbradoraController;

// Inicializar service e controller (opcionalmente)
if (!isset($data_dir)) {
    $data_dir = __DIR__ . '/../../../../data/reformulacao/calibradora';
}

if (!isset($service)) {
    $service = new CalbradoraService($data_dir);
}

if (!isset($controller)) {
    $controller = new CalbradoraController($service);
}
